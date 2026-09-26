/*!
 * Consent Kit – <consent-kit> und <consent-embed>
 * Friends Of REDAXO, MIT License – entwickelt von KLXM Crossmedia GmbH
 */
const configElement = document.getElementById('consent-kit-config');
const cfg = configElement ? JSON.parse(configElement.textContent || '{}') : null;

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const safeUrl = (url) => (/^(https?:)?\/\//i.test(url) || /^[/?#.]/.test(url) ? url : '#');
const fill = (text, values) => String(text ?? '').replace(/\{(\w+)\}/g, (m, key) => (key in values ? values[key] : m));
const wildcard = (pattern) => new RegExp('^' + pattern.split('*').map((p) => p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('.*') + '$');

/* ---------------------------------------------------------------- Kern --- */

const core = {
    cfg,
    services: new Map(),
    optional: [],
    loaded: new Set(),
    state: null,
    gpc: false,

    init() {
        for (const group of cfg.groups) {
            for (const service of group.services) {
                service.required = group.required;
                this.services.set(service.key, service);
                if (!group.required) this.optional.push(service);
            }
        }
        this.gpc = cfg.gpc !== 'ignore' && navigator.globalPrivacyControl === true;
        this.state = this.readCookie();
        if (this.state && this.state.e !== cfg.epoch) this.state = null;
    },

    readCookie() {
        const match = document.cookie.match(new RegExp('(?:^|; )' + cfg.cookie + '=([^;]*)'));
        if (!match) return null;
        try {
            const data = JSON.parse(decodeURIComponent(match[1]));
            return data && typeof data.id === 'string' ? { a: {}, r: {}, ...data } : null;
        } catch (e) {
            return null;
        }
    },

    has(key) {
        const service = this.services.get(key);
        if (!service) return false;
        if (service.required) return true;
        return !!this.state && this.state.a[key] === service.h;
    },

    accepted() {
        return this.optional.filter((s) => this.has(s.key)).map((s) => s.key);
    },

    /** Gibt es optionale Dienste, zu denen (in ihrer aktuellen Fassung) noch keine Entscheidung vorliegt? */
    needsDecision() {
        return this.optional.some((s) => !this.state || (this.state.a[s.key] !== s.h && this.state.r[s.key] !== s.h));
    },

    decide(keys, action) {
        const before = new Set(this.accepted());
        const accepted = {};
        const rejected = {};
        for (const service of this.optional) {
            (keys.includes(service.key) ? accepted : rejected)[service.key] = service.h;
        }
        this.state = {
            id: this.state?.id || uuid(),
            e: cfg.epoch,
            rev: cfg.rev,
            ts: Math.floor(Date.now() / 1000),
            a: accepted,
            r: rejected,
        };

        if (cfg.preview) {
            this.emit('change', action);
            return;
        }
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = cfg.cookie + '=' + encodeURIComponent(JSON.stringify(this.state))
            + '; Max-Age=' + cfg.days * 86400 + '; Path=/; SameSite=Lax' + secure;

        const request = fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: this.state.id, action, accepted: Object.keys(accepted), gpc: this.gpc, lang: cfg.lang }),
        }).catch(() => null);

        const revoked = [...before].filter((key) => !accepted[key]);
        let needsReload = false;
        for (const key of revoked) {
            const service = this.services.get(key);
            run(service.jsRevoke, key + ' js_revoke');
            clearItems(service);
            needsReload = needsReload || this.loaded.has(key);
        }
        this.apply();
        this.emit('change', action);

        if (needsReload && cfg.reload) {
            Promise.race([request, new Promise((resolve) => setTimeout(resolve, 1500))]).then(() => location.reload());
        }
    },

    /**
     * Widerruf: alle Dienste stoppen, Cookie loeschen, protokollieren. Danach gilt
     * "keine Entscheidung" – der Hinweis erscheint beim naechsten Aufruf erneut.
     */
    withdraw() {
        const accepted = this.accepted();
        const id = this.state?.id;
        for (const key of accepted) {
            const service = this.services.get(key);
            run(service.jsRevoke, key + ' js_revoke');
            clearItems(service);
        }
        this.state = null;
        document.cookie = cfg.cookie + '=; Max-Age=0; Path=/';
        try { sessionStorage.removeItem('consent_kit_dismissed'); } catch (e) { /* Storage gesperrt */ }
        const request = cfg.preview || !id ? Promise.resolve() : fetch(cfg.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action: 'withdraw', accepted: [], gpc: this.gpc, lang: cfg.lang }),
        }).catch(() => null);
        this.emit('change', 'withdraw');
        if (cfg.preview) return;
        Promise.race([request, new Promise((resolve) => setTimeout(resolve, 1500))]).then(() => location.reload());
    },

    /** Laedt alles, wofuer eine Einwilligung vorliegt. Mehrfach aufrufbar. */
    apply() {
        // Ohne Entscheidung gelten die Defaults aus dem <head>; ein verfruehtes update wuerde wait_for_update beenden.
        if (cfg.gcm && this.state && typeof window.gtag === 'function') {
            const update = {};
            for (const service of this.optional) {
                for (const signal of service.gcm) {
                    if (this.has(service.key)) update[signal] = 'granted';
                    else if (!(signal in update)) update[signal] = 'denied';
                }
            }
            if (Object.keys(update).length) window.gtag('consent', 'update', update);
        }
        for (const service of this.services.values()) {
            if (!this.has(service.key)) continue;
            if (!this.loaded.has(service.key)) {
                this.loaded.add(service.key);
                inject(service.head, document.head);
                inject(service.body, document.body);
                run(service.jsEvents, service.key + ' events');
            }
            run(service.jsAccept, service.key + ' js_accept');
        }
        this.activateScripts();
    },

    /** <script type="text/plain" data-consent="dienst"> im Seitenquelltext. */
    activateScripts(root = document) {
        for (const blocked of root.querySelectorAll('script[type="text/plain"][data-consent]')) {
            if (!this.has(blocked.dataset.consent)) continue;
            const script = cloneScript(blocked);
            script.type = blocked.dataset.type || 'text/javascript';
            if (blocked.dataset.src) script.src = blocked.dataset.src;
            script.removeAttribute('data-consent');
            blocked.replaceWith(script);
        }
    },

    emit(name, action = null) {
        const detail = { accepted: this.accepted(), rejected: this.optional.filter((s) => !this.has(s.key)).map((s) => s.key), action };
        document.dispatchEvent(new CustomEvent('consentkit:' + name, { detail }));
        if (cfg.gcm && Array.isArray(window.dataLayer)) {
            window.dataLayer.push({ event: 'consentkit_' + name, consentkit: detail });
        }
    },
};

function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40;
    b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

function run(code, label) {
    if (!code) return;
    try {
        new Function(code)();
    } catch (error) {
        console.error('[consent-kit] ' + label, error);
    }
}

const nonce = () => document.querySelector('script[nonce]')?.nonce || '';

function cloneScript(source) {
    const script = document.createElement('script');
    for (const attr of source.attributes) script.setAttribute(attr.name, attr.value);
    if (nonce()) script.nonce = nonce();
    script.textContent = source.textContent;
    // Dynamisch eingefuegte externe Scripts sind sonst async und verlieren ihre Reihenfolge.
    if (script.src && !source.hasAttribute('async')) script.async = false;
    return script;
}

function inject(html, target) {
    if (!html) return;
    const template = document.createElement('template');
    template.innerHTML = html;
    for (const script of template.content.querySelectorAll('script')) script.replaceWith(cloneScript(script));
    target.append(template.content);
}

function clearItems(service) {
    const host = location.hostname;
    const domains = ['', host, '.' + host];
    const parts = host.split('.');
    for (let i = 1; i < parts.length - 1; i++) domains.push('.' + parts.slice(i).join('.'));
    const cookieNames = document.cookie.split('; ').map((c) => c.split('=')[0]).filter(Boolean);

    for (const item of service.items) {
        const pattern = wildcard(item.name);
        if (item.type === 'cookie') {
            for (const name of cookieNames.filter((n) => pattern.test(decodeURIComponent(n)))) {
                for (const domain of domains) {
                    document.cookie = name + '=; Max-Age=0; Path=/' + (domain ? '; Domain=' + domain : '');
                }
            }
        } else if (item.type === 'local_storage' || item.type === 'session_storage') {
            try {
                const storage = item.type === 'local_storage' ? localStorage : sessionStorage;
                Object.keys(storage).filter((k) => pattern.test(k)).forEach((k) => storage.removeItem(k));
            } catch (e) { /* Storage gesperrt */ }
        }
    }
}

/* ----------------------------------------------------------------- CSS --- */

const baseCss = `
:host {
    --_font: var(--ck-font, inherit);
    --_size: var(--ck-font-size, 1rem);
    --_line-height: var(--ck-line-height, 1.5);
    --_heading-size: var(--ck-heading-size, 1.2em);
    --_heading-weight: var(--ck-heading-weight, 700);
    --_small-size: var(--ck-small-size, .875em);
    --_bg: var(--ck-bg, #ffffff);
    --_text: var(--ck-text, #1a1a1a);
    --_muted: var(--ck-muted, #595959);
    --_border: var(--ck-border, #8c8c8c);
    --_line: var(--ck-line, #d9d9d9);
    --_accent: var(--ck-accent, #1d4ed8);
    --_btn-bg: var(--ck-button-bg, #1f2937);
    --_btn-text: var(--ck-button-text, #ffffff);
    --_btn-border: var(--ck-button-border, #1f2937);
    --_radius: var(--ck-radius, 12px);
    --_btn-radius: var(--ck-button-radius, 8px);
    --_shadow: var(--ck-shadow, 0 12px 40px rgba(0, 0, 0, .22));
    --_backdrop: var(--ck-backdrop, rgba(0, 0, 0, .55));
    /* Abstandsraster: eine Basis, alles andere leitet sich daraus ab. */
    --_space: var(--ck-space, 1.25rem);
    --_gap: var(--ck-gap, .6rem);
    --_border-width: var(--ck-border-width, 1px);
    --_btn-padding: var(--ck-button-padding, .55rem 1rem);
    --_btn-weight: var(--ck-button-weight, 600);
    --_btn-border-width: var(--ck-button-border-width, 2px);
    --_btn-transform: var(--ck-button-transform, none);
    --_btn-letter-spacing: var(--ck-button-letter-spacing, normal);
    /* Ohne eigene Hover-Werte bleibt es bei der Helligkeitsaenderung, siehe .btn:hover. */
    --_btn-hover-bg: var(--ck-button-hover-bg, var(--_btn-bg));
    --_btn-hover-text: var(--ck-button-hover-text, var(--_btn-text));
    --_btn-hover-border: var(--ck-button-hover-border, var(--_btn-border));
    --_btn-hover-filter: var(--ck-button-hover-filter, brightness(1.15));
    --_switch-width: var(--ck-switch-width, 2.75rem);
    --_switch-height: var(--ck-switch-height, 1.5rem);
    --_group-radius: var(--ck-group-radius, 10px);
    --_backdrop-filter: var(--ck-backdrop-filter, none);
    --_tap: var(--ck-tap-size, 2.75rem);
    color-scheme: light;
    font-family: var(--_font);
    font-size: var(--_size);
    line-height: var(--_line-height);
}
:host([theme="dark"]) { ${darkVars()} }
@media (prefers-color-scheme: dark) { :host([theme="auto"]) { ${darkVars()} } }
*, *::before, *::after { box-sizing: border-box; }
[hidden] { display: none !important; }
button { font: inherit; color: inherit; cursor: pointer; }
a { color: var(--_accent); text-underline-offset: .15em; }
:focus-visible { outline: 3px solid var(--_accent); outline-offset: 2px; border-radius: 4px; }
.btn {
    appearance: none; display: inline-flex; align-items: center; justify-content: center;
    min-height: var(--_tap); padding: var(--_btn-padding); text-align: center;
    font-weight: var(--_btn-weight); line-height: 1.25;
    text-transform: var(--_btn-transform); letter-spacing: var(--_btn-letter-spacing);
    color: var(--_btn-text); background: var(--_btn-bg);
    border: var(--_btn-border-width) solid var(--_btn-border); border-radius: var(--_btn-radius);
}
/*
 * Standard ist eine Helligkeitsaenderung, damit jede Farbkombination ohne Zutun einen Hover hat.
 * Eigene Hover-Farben ersetzen sie; --ck-button-hover-filter: none schaltet sie ab.
 */
.btn:hover { filter: var(--_btn-hover-filter); background: var(--_btn-hover-bg); color: var(--_btn-hover-text); border-color: var(--_btn-hover-border); }
.btn:active { filter: brightness(.92); }
.foot, .placeholder { container-type: inline-size; }
.buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--_gap); }
/* Entweder alle nebeneinander oder alle untereinander – nie eine Schaltflaeche allein in einer Zeile. */
@container (max-width: 36rem) { .buttons { grid-template-columns: 1fr; } }
@media (forced-colors: active) {
    .btn, dialog, .track, .placeholder { border: 2px solid CanvasText; }
    .track::after { background: CanvasText; }
}
`;

function darkVars() {
    return `
    --_bg: var(--ck-dark-bg, #18181b);
    --_text: var(--ck-dark-text, #f4f4f5);
    --_muted: var(--ck-dark-muted, #b4b4bb);
    --_border: var(--ck-dark-border, #8e8e96);
    --_line: var(--ck-dark-line, #3f3f46);
    --_accent: var(--ck-dark-accent, #93c5fd);
    --_btn-bg: var(--ck-dark-button-bg, #f4f4f5);
    --_btn-text: var(--ck-dark-button-text, #18181b);
    --_btn-border: var(--ck-dark-button-border, #f4f4f5);
    --_shadow: var(--ck-dark-shadow, 0 12px 40px rgba(0, 0, 0, .6));
    --_backdrop: var(--ck-dark-backdrop, rgba(0, 0, 0, .7));
    --_btn-hover-bg: var(--ck-dark-button-hover-bg, var(--_btn-bg));
    --_btn-hover-text: var(--ck-dark-button-hover-text, var(--_btn-text));
    --_btn-hover-border: var(--ck-dark-button-hover-border, var(--_btn-border));
    color-scheme: dark;`;
}

const kitCss = baseCss + `
dialog {
    position: fixed; inset: auto; margin: 0; padding: 0; border: var(--_border-width) solid var(--_border);
    width: min(var(--ck-width, 30rem), calc(100vw - 2rem)); max-width: none;
    max-height: calc(100dvh - 2rem); overflow: hidden;
    color: var(--_text); background: var(--_bg);
    border-radius: var(--_radius); box-shadow: var(--_shadow);
    z-index: var(--ck-z, 2147483000);
}
dialog[open] { display: flex; flex-direction: column; }
dialog::backdrop { background: var(--_backdrop); backdrop-filter: var(--_backdrop-filter); }
dialog.modal, dialog.settings { inset: 0; margin: auto; }
dialog.settings { width: min(var(--ck-settings-width, 44rem), calc(100vw - 2rem)); }
/* Einstellungen als Off-Canvas: Seitenlage schlaegt die mittige Voreinstellung. */
dialog.settings.offcanvas { inset: auto; margin: 0; width: min(var(--ck-offcanvas-settings-width, var(--ck-offcanvas-width, 26rem)), 100vw); }
dialog.box.bottom-left { left: 1rem; bottom: 1rem; }
dialog.box.bottom-right { right: 1rem; bottom: 1rem; }
dialog.box.top-left { left: 1rem; top: 1rem; }
dialog.box.top-right { right: 1rem; top: 1rem; }
dialog.bar { left: 0; right: 0; width: 100%; border-radius: 0; border-width: var(--_border-width) 0 0; }
dialog.bar.bottom-left, dialog.bar.bottom-right { bottom: 0; }
dialog.bar.top-left, dialog.bar.top-right { top: 0; border-width: 0 0 var(--_border-width); }
/*
 * Off-Canvas: volle Hoehe an einer Seite. Die Ecken-Angabe aus "position" bestimmt die Seite,
 * damit dieselbe Einstellung fuer alle Layouts gilt.
 */
dialog.offcanvas {
    top: 0; bottom: 0; height: 100dvh; max-height: 100dvh;
    /* Auf schmalen Schirmen ueber die volle Breite – ein Rand neben einem randlosen Panel wirkt wie ein Fehler. */
    width: min(var(--ck-offcanvas-width, 26rem), 100vw);
    border-radius: 0; border-width: 0;
}
dialog.offcanvas.bottom-left, dialog.offcanvas.top-left { left: 0; border-right-width: var(--_border-width); }
dialog.offcanvas.bottom-right, dialog.offcanvas.top-right { right: 0; border-left-width: var(--_border-width); }
/* Inhalt oben, Schaltflaechen unten – die Mitte scrollt, falls der Text lang ist. */
dialog.offcanvas .inner { height: 100%; }
dialog.offcanvas .body { flex: 1; }
dialog.bar .inner { width: min(72rem, 100%); margin: 0 auto; }
@media (min-width: 60rem) {
    dialog.bar:not(.settings) .inner { display: grid; grid-template-columns: 1fr auto; column-gap: 2rem; align-items: center; }
    dialog.bar:not(.settings) .foot { min-width: 38rem; }
}
.inner { display: flex; flex-direction: column; min-height: 0; max-height: 100%; }
/*
 * Der Textbereich muss schrumpfen duerfen, sonst schiebt langer Inhalt (z. B. Gruppen im Hinweis)
 * die Schaltflaechen aus dem Dialog. Gescrollt wird in .body.
 */
.text { display: flex; flex-direction: column; min-height: 0; }
.head, .foot { padding: var(--_space) var(--_space) 0; }
.head { display: flex; align-items: flex-start; gap: .75rem; }
.head h2 { flex: 1; }
.close {
    flex: none; display: inline-flex; align-items: center; justify-content: center;
    width: var(--_tap); height: var(--_tap); margin: -.6rem -.6rem 0 0; padding: 0;
    color: var(--_muted); background: none; border: 0; border-radius: 50%;
}
.close:hover { color: var(--_text); background: var(--_line); }
.close svg { width: 1.25rem; height: 1.25rem; }
.foot { padding-bottom: var(--_space); border-top: var(--_border-width) solid var(--_line); padding-top: 1rem; }
.banner .foot { border-top: 0; padding-top: 0; }
.body { padding: .75rem var(--_space) 1rem; overflow-y: auto; overscroll-behavior: contain; }
h2 { margin: 0; font-size: var(--_heading-size); font-weight: var(--_heading-weight); line-height: 1.3; }
h2:focus { outline: none; }
h3 { margin: 0; font-size: 1em; }
p { margin: 0 0 .75rem; }
.muted, .links, .meta { color: var(--_muted); font-size: var(--_small-size); }
.links { display: flex; flex-wrap: wrap; gap: .25rem 1rem; margin: 0 0 .9rem; padding: 0; list-style: none; }
.meta { margin: .75rem 0 0; overflow-wrap: anywhere; }
.notice { padding: .6rem .75rem; border: var(--_border-width) solid var(--_border); border-radius: var(--_group-radius); font-size: .9em; }
.group { border: var(--_border-width) solid var(--_line); border-radius: var(--_group-radius); margin-bottom: var(--_gap); }
.group-head, .service-head { display: flex; align-items: center; gap: .75rem; }
.group-head { padding: .35rem .75rem .35rem .25rem; }
.group > .muted { margin: 0; padding: 0 .75rem .7rem 2.45rem; }
/* Gruppen im Hinweis: ohne Aufklapp-Schaltflaeche buendig zum Text einruecken. */
.group.plain .group-head { padding: .5rem .75rem; min-height: var(--_tap); }
.group.plain .group-head h3 { font-weight: 600; }
.group.plain .group-head .count { font-weight: 400; color: var(--_muted); font-size: var(--_small-size); }
.group.plain > .muted { padding-left: .75rem; }
.expand {
    flex: 1; display: flex; align-items: center; gap: .5rem; min-height: var(--_tap); padding: .25rem .5rem;
    text-align: left; font-weight: 600; background: none; border: 0; border-radius: 6px;
}
.expand .count { font-weight: 400; color: var(--_muted); font-size: var(--_small-size); }
.chev { flex: none; width: .55rem; height: .55rem; margin: 0 .3rem; border: solid currentColor; border-width: 0 2px 2px 0; transform: rotate(-45deg); }
[aria-expanded="true"] > .chev { transform: rotate(45deg); }
.always { font-size: var(--_small-size); color: var(--_muted); white-space: nowrap; }
.services { border-top: var(--_border-width) solid var(--_line); }
.service { padding: .7rem .75rem; }
.service + .service { border-top: var(--_border-width) solid var(--_line); }
.service-head .name { flex: 1; font-weight: 600; }
.service p { margin: .25rem 0 .35rem; font-size: .925em; }
.more { padding: .2rem 0; min-height: var(--_tap); background: none; border: 0; font-size: var(--_small-size); color: var(--_accent); text-decoration: underline; text-underline-offset: .15em; }
.details { margin-top: .5rem; font-size: var(--_small-size); }
.details dl { display: grid; grid-template-columns: auto 1fr; gap: .2rem .75rem; margin: 0 0 .6rem; }
.details dt { color: var(--_muted); }
.details dd { margin: 0; overflow-wrap: anywhere; }
.table { overflow-x: auto; border: var(--_border-width) solid var(--_line); border-radius: var(--_group-radius); }
table { width: 100%; border-collapse: collapse; }
caption { text-align: left; padding: .45rem .6rem; font-weight: 600; }
th, td { padding: .4rem .6rem; text-align: left; vertical-align: top; border-top: var(--_border-width) solid var(--_line); }
th { color: var(--_muted); font-weight: 600; white-space: nowrap; }
td:first-child { min-width: 9rem; overflow-wrap: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .95em; }
.switch { flex: none; position: relative; display: inline-flex; align-items: center; min-height: var(--_tap); cursor: pointer; }
.switch input { position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: pointer; }
.track { position: relative; width: var(--_switch-width); height: var(--_switch-height); border-radius: 1rem; border: 2px solid var(--_border); background: transparent; }
.track::after { content: ""; position: absolute; top: 2px; left: 2px; width: calc(var(--_switch-height) - .5rem); height: calc(var(--_switch-height) - .5rem); border-radius: 50%; background: var(--_border); }
input:checked + .track { background: var(--_accent); border-color: var(--_accent); }
input:checked + .track::after { left: calc(100% - (var(--_switch-height) - .5rem) - 2px); background: var(--_bg); }
input:indeterminate + .track::after { left: calc(50% - .5rem); border-radius: 3px; height: .3rem; top: calc(50% - .15rem); background: var(--_accent); }
input:focus-visible + .track { outline: 3px solid var(--_accent); outline-offset: 2px; }
.trigger {
    position: fixed; z-index: var(--ck-z, 2147483000); display: inline-flex; align-items: center; justify-content: center;
    width: var(--_tap); height: var(--_tap); padding: 0; color: var(--_text); background: var(--_bg);
    border: var(--_border-width) solid var(--_border); border-radius: 50%; box-shadow: var(--ck-trigger-shadow, 0 2px 10px rgba(0, 0, 0, .18));
}
.trigger.bottom-left { left: 1rem; bottom: 1rem; }
.trigger.bottom-right { right: 1rem; bottom: 1rem; }
.trigger svg { width: 1.4rem; height: 1.4rem; }
@media (prefers-reduced-motion: no-preference) {
    dialog[open] { animation: ck-in .2s ease-out; }
    dialog.offcanvas.bottom-left[open], dialog.offcanvas.top-left[open] { animation: ck-in-left .25s ease-out; }
    dialog.offcanvas.bottom-right[open], dialog.offcanvas.top-right[open] { animation: ck-in-right .25s ease-out; }
    .track::after, .chev { transition: left .15s, transform .15s; }
    @keyframes ck-in { from { opacity: 0; transform: translateY(.5rem); } }
    @keyframes ck-in-left { from { transform: translateX(-100%); } }
    @keyframes ck-in-right { from { transform: translateX(100%); } }
}
@media (max-width: 30rem) {
    .settings .foot { padding: .6rem .9rem .75rem; }
    .settings .buttons { gap: .4rem; }
    .settings .head { padding: 1rem .9rem 0; }
    .settings .body { padding: .6rem .9rem .75rem; }
    /* Tabelle als Liste: kein horizontales Scrollen auf dem Telefon. */
    .table { overflow: visible; border: 0; }
    table, tbody, tr, td { display: block; }
    thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
    caption { display: block; padding-left: 0; }
    tr { padding: .5rem .6rem; border: var(--_border-width) solid var(--_line); border-radius: var(--_group-radius); }
    tr + tr { margin-top: .4rem; }
    td { padding: .1rem 0; border: 0; }
    td::before { content: attr(data-label) ": "; color: var(--_muted); }
    td:first-child { min-width: 0; font-weight: 600; }
    td:first-child::before { content: none; }
    dialog.box { left: .5rem !important; right: .5rem !important; width: auto; }
    .group > .muted { padding-left: .75rem; }
}
`;

const embedCss = baseCss + `
:host { display: block; }
:host([loaded]) .placeholder { display: none; }
.placeholder {
    display: flex; flex-direction: column; justify-content: center; gap: var(--_gap);
    aspect-ratio: var(--ck-embed-ratio, 16 / 9); min-height: var(--ck-embed-min-height, 14rem); padding: var(--_space);
    color: var(--_text); background: var(--_bg);
    border: var(--_border-width) solid var(--_border); border-radius: var(--_radius);
    overflow: auto;
}
.placeholder > * { width: min(36rem, 100%); margin-inline: auto; }
h3 { margin: 0; font-size: 1.05em; }
p { margin: 0; font-size: .925em; color: var(--_muted); }
`;

/*
 * Eigenes Stylesheet im Shadow DOM. Als <link> statt adoptedStyleSheets, damit die Datei
 * keinen CORS-Header braucht und der Browser sie normal cachen kann. Sie steht hinter dem
 * Basis-CSS und gewinnt damit bei gleicher Spezifitaet.
 */
function customStyle() {
    return cfg.cssUrl ? `<link rel="stylesheet" href="${esc(cfg.cssUrl)}">` : '';
}

const cookieIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.3A9 9 0 1 1 11.7 3a4 4 0 0 0 4.6 4.7A4 4 0 0 0 21 12.3Z"/><path d="M8.5 9.5h.01M8 14.5h.01M12.5 12.5h.01M13 17h.01M16.5 14h.01"/></svg>';

/* ---------------------------------------------------------- <consent-kit> --- */

class ConsentKitElement extends HTMLElement {
    connectedCallback() {
        if (this.shadowRoot || !cfg) return;
        this.attachShadow({ mode: 'open' });
        this.view = null;
        this.expanded = new Set();
        this.selection = new Set();
        if (!this.hasAttribute('theme')) this.setAttribute('theme', cfg.theme);
        if (cfg.lang) this.setAttribute('lang', cfg.lang.replace('_', '-'));
        for (const [name, value] of Object.entries(cfg.cssVars || {})) {
            if (name.startsWith('--ck-')) this.style.setProperty(name, value);
        }
        this.shadowRoot.innerHTML = `<style>${kitCss}</style>${customStyle()}<dialog part="dialog" aria-labelledby="ck-title"></dialog><button type="button" class="trigger ${esc(cfg.triggerPosition)}" part="trigger" hidden aria-label="${esc(cfg.texts.trigger)}" title="${esc(cfg.texts.trigger)}">${cookieIcon}</button>`;
        this.dialog = this.shadowRoot.querySelector('dialog');
        this.trigger = this.shadowRoot.querySelector('.trigger');

        this.trigger.addEventListener('click', () => this.open('settings'));
        this.dialog.addEventListener('click', (event) => this.onClick(event));
        this.dialog.addEventListener('change', (event) => this.onChange(event));
        this.dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            if (this.view === 'settings' || cfg.dismiss) this.dismiss();
        });
        // Nicht-modale Hinweise bekommen kein cancel-Ereignis.
        this.dialog.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !this.dialog.matches(':modal') && cfg.dismiss) this.dismiss();
        });

        if (!core.optional.length) {
            this.updateTrigger();
        } else if (core.needsDecision()) {
            if (core.gpc && cfg.gpc === 'reject' && !core.state) {
                core.decide([], 'gpc');
                this.updateTrigger();
            } else if (this.wasDismissed() && !cfg.preview) {
                this.updateTrigger();
            } else {
                this.open('banner');
            }
        } else {
            this.updateTrigger();
        }
    }

    get layout() {
        const layout = this.getAttribute('layout') || cfg.layout;
        // Datenschutz und Impressum muessen ohne Entscheidung lesbar sein. Mit Schliessen-Schaltflaeche
        // ist das gegeben; nur ein erzwungener Dialog weicht dort auf die Box aus.
        return cfg.quiet && layout === 'modal' && !cfg.dismiss ? 'box' : layout;
    }

    open(view = 'settings') {
        if (view === 'settings' && this.view !== 'settings') {
            this.selection = new Set(core.accepted());
            this.returnFocus = document.activeElement;
        } else if (view === 'banner' && this.view !== 'banner') {
            // Ohne vorliegende Entscheidung ist nichts ausgewaehlt; eine frueher getroffene wird uebernommen.
            this.selection = new Set(core.accepted());
        }
        this.view = view;
        const modal = view === 'settings' || this.layout === 'modal';
        if (this.dialog.open) this.dialog.close();
        this.dialog.removeAttribute('open');
        // Off-Canvas behaelt seine Form auch fuer die Einstellungen, sonst spraenge der Dialog in die Mitte.
        const offcanvas = this.layout === 'offcanvas';
        this.dialog.className = (view === 'settings' ? 'settings' + (offcanvas ? ' offcanvas' : '') : this.layout + ' banner') + ' ' + (this.getAttribute('position') || cfg.position);
        this.dialog.innerHTML = view === 'settings' ? this.settingsHtml() : this.bannerHtml();
        this.trigger.hidden = true;
        if (modal) {
            this.dialog.setAttribute('aria-modal', 'true');
            this.dialog.showModal();
            document.documentElement.style.setProperty('overflow', 'hidden');
            this.dialog.querySelector('h2').focus();
        } else {
            // Attribut statt show(): der nicht-modale Hinweis soll beim Seitenaufruf keinen Fokus an sich ziehen.
            this.dialog.setAttribute('aria-modal', 'false');
            this.dialog.setAttribute('open', '');
            document.documentElement.style.removeProperty('overflow');
        }
        // Im Hinweis mit Gruppen ist bewusst nichts vorausgewaehlt: keine Vorab-Einwilligung.
        if (view === 'settings' || this.withGroups) this.syncSwitches();
    }

    close() {
        if (this.dialog.open) this.dialog.close();
        this.dialog.removeAttribute('open');
        this.dialog.innerHTML = '';
        this.view = null;
        document.documentElement.style.removeProperty('overflow');
        this.updateTrigger();
        if (this.returnFocus && this.returnFocus !== this && this.returnFocus !== document.body && this.returnFocus.isConnected) this.returnFocus.focus();
        else if (!this.trigger.hidden) this.trigger.focus({ preventScroll: true });
        this.returnFocus = null;
    }

    /**
     * Schliessen ohne Entscheidung: nichts wird geladen. Der Hinweis bleibt fuer diese
     * Browser-Sitzung ausgeblendet; die Einstellungen sind ueber Schaltflaeche oder Link erreichbar.
     */
    dismiss() {
        if (this.view === 'settings' && core.needsDecision() && !this.wasDismissed()) {
            this.open('banner');
            return;
        }
        if (core.needsDecision()) {
            try { sessionStorage.setItem('consent_kit_dismissed', String(cfg.rev)); } catch (e) { /* Storage gesperrt */ }
        }
        this.close();
    }

    wasDismissed() {
        try { return sessionStorage.getItem('consent_kit_dismissed') === String(cfg.rev); } catch (e) { return false; }
    }

    updateTrigger() {
        this.trigger.hidden = !(cfg.trigger && this.view === null);
    }

    /* Alle drei Schaltflaechen teilen sich Klasse und ::part – keine laesst sich hervorheben. */
    buttons(middle) {
        const t = cfg.texts;
        return `<div class="buttons">
            <button type="button" class="btn" part="button" data-action="reject">${esc(t.reject_all)}</button>
            <button type="button" class="btn" part="button" data-action="${middle}">${esc(middle === 'save' ? t.save : t.settings)}</button>
            <button type="button" class="btn" part="button" data-action="accept">${esc(t.accept_all)}</button>
        </div>`;
    }

    closeHtml() {
        return `<button type="button" class="close" part="close" data-action="close" aria-label="${esc(cfg.texts.close)}" title="${esc(cfg.texts.close)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg></button>`;
    }

    linksHtml() {
        if (!cfg.links.length) return '';
        return '<ul class="links">' + cfg.links.map((l) => `<li><a href="${esc(safeUrl(l.url))}">${esc(l.label)}</a></li>`).join('') + '</ul>';
    }

    /** Zeigt der Hinweis die Gruppen direkt? Nur wo die Hoehe reicht. */
    get withGroups() {
        return !!cfg.bannerGroups && core.optional.length > 0 && ['modal', 'offcanvas'].includes(this.layout);
    }

    bannerHtml() {
        const t = cfg.texts;
        const groups = this.withGroups ? this.groupsHtml(false) : '';
        return `<div class="inner">
            <div class="text"><div class="head"><h2 id="ck-title" tabindex="-1">${esc(t.title)}</h2>${cfg.dismiss ? this.closeHtml() : ''}</div>
            <div class="body"><p>${esc(t.intro)}</p>${core.gpc ? `<p class="notice">${esc(t.gpc_notice)}</p>` : ''}${groups}${this.linksHtml()}</div></div>
            <div class="foot">${this.buttons(this.withGroups ? 'save' : 'settings')}</div>
        </div>`;
    }

    /**
     * Gruppenliste. Mit details=false (Hinweis) bleiben die Dienste eingeklappt und
     * werden gar nicht erst gerendert – der Hinweis soll knapp bleiben.
     */
    groupsHtml(details = true) {
        const t = cfg.texts;
        return cfg.groups.map((group, index) => {
            const id = 'ck-g' + index;
            const open = details && this.expanded.has(id);
            const count = group.services.length === 1 ? t.services_count_one : fill(t.services_count, { n: group.services.length });
            const toggle = group.required
                ? `<span class="always">${esc(t.always_active)}</span>`
                : `<label class="switch"><input type="checkbox" data-group="${esc(group.key)}" aria-label="${esc(fill(t.group_toggle, { name: group.name }))}"><span class="track"></span></label>`;
            const name = details
                ? `<h3 style="flex:1;display:flex"><button type="button" class="expand" aria-expanded="${open}" aria-controls="${id}" data-expand="${id}"><span class="chev"></span><span>${esc(group.name)}</span><span class="count">${esc(count)}</span></button></h3>`
                : `<h3 style="flex:1"><span>${esc(group.name)}</span> <span class="count">${esc(count)}</span></h3>`;
            const services = details
                ? `<div class="services" id="${id}" ${open ? '' : 'hidden'}>${group.services.map((s, i) => this.serviceHtml(group, s, id + 's' + i)).join('')}</div>`
                : '';
            return `<section class="group${details ? '' : ' plain'}" part="group">
                <div class="group-head">
                    ${name}
                    ${toggle}
                </div>
                <p class="muted">${esc(group.description)}</p>
                ${services}
            </section>`;
        }).join('');
    }

    settingsHtml() {
        const t = cfg.texts;
        const groups = this.groupsHtml(true);

        const meta = core.state
            ? `<p class="meta">${esc(fill(t.consent_info, { id: core.state.id, date: new Date(core.state.ts * 1000).toLocaleString(cfg.lang.replace('_', '-')) }))}</p>
               <p class="meta"><button type="button" class="more" part="withdraw" data-action="withdraw">${esc(t.withdraw)}</button></p>`
            : '';
        return `<div class="inner">
            <div class="head"><h2 id="ck-title" tabindex="-1">${esc(t.settings_title)}</h2>${this.closeHtml()}</div>
            <div class="body"><p>${esc(t.settings_intro)}</p>${core.gpc ? `<p class="notice">${esc(t.gpc_notice)}</p>` : ''}${groups}${this.linksHtml()}${meta}</div>
            <div class="foot">${this.buttons('save')}</div>
        </div>`;
    }

    serviceHtml(group, service, id) {
        const t = cfg.texts;
        const open = this.expanded.has(id);
        const toggle = group.required
            ? `<span class="always">${esc(t.always_active)}</span>`
            : `<label class="switch"><input type="checkbox" data-service="${esc(service.key)}" data-in-group="${esc(group.key)}" aria-labelledby="${id}-name"><span class="track"></span></label>`;
        const rows = service.items.map((item) => `<tr><td>${esc(item.name)}</td><td data-label="${esc(t.col_type)}">${esc(t['type_' + item.type] || item.type)}</td><td data-label="${esc(t.col_host)}">${esc(item.host || location.hostname)}</td><td data-label="${esc(t.col_duration)}">${esc(item.duration)}</td><td data-label="${esc(t.col_purpose)}">${esc(item.purpose)}</td></tr>`).join('');
        const table = rows
            ? `<div class="table" tabindex="0" role="region" aria-label="${esc(t.storage)}: ${esc(service.name)}"><table><caption>${esc(t.storage)}</caption><thead><tr><th scope="col">${esc(t.col_name)}</th><th scope="col">${esc(t.col_type)}</th><th scope="col">${esc(t.col_host)}</th><th scope="col">${esc(t.col_duration)}</th><th scope="col">${esc(t.col_purpose)}</th></tr></thead><tbody>${rows}</tbody></table></div>`
            : `<p class="muted">${esc(t.no_items)}</p>`;
        const provider = service.provider ? `<dt>${esc(t.provider)}</dt><dd>${esc(service.provider)}</dd>` : '';
        const privacy = service.privacyUrl ? `<dt>${esc(t.privacy_policy)}</dt><dd><a href="${esc(safeUrl(service.privacyUrl))}" target="_blank" rel="noopener noreferrer" aria-label="${esc(fill(t.privacy_policy_of, { name: service.name }))}">${esc(service.privacyUrl)}</a></dd>` : '';
        return `<div class="service" part="service">
            <div class="service-head"><span class="name" id="${id}-name">${esc(service.name)}</span>${toggle}</div>
            ${service.description ? `<p>${esc(service.description)}</p>` : ''}
            <button type="button" class="more" aria-expanded="${open}" aria-controls="${id}" data-expand="${id}" data-label-open="${esc(t.hide_details)}" data-label-closed="${esc(t.show_details)}" aria-describedby="${id}-name">${esc(open ? t.hide_details : t.show_details)}</button>
            <div class="details" id="${id}" ${open ? '' : 'hidden'}>${provider || privacy ? `<dl>${provider}${privacy}</dl>` : ''}${table}</div>
        </div>`;
    }

    syncSwitches() {
        for (const input of this.dialog.querySelectorAll('input[data-service]')) {
            input.checked = this.selection.has(input.dataset.service);
        }
        for (const input of this.dialog.querySelectorAll('input[data-group]')) {
            const inputs = [...this.dialog.querySelectorAll(`input[data-in-group="${CSS.escape(input.dataset.group)}"]`)];
            if (!inputs.length) {
                // Im Hinweis sind die Dienste nicht gerendert; dann zaehlt die Auswahl selbst.
                const services = cfg.groups.find((g) => g.key === input.dataset.group)?.services || [];
                const on = services.filter((s) => this.selection.has(s.key)).length;
                input.checked = on > 0 && on === services.length;
                input.indeterminate = on > 0 && on < services.length;
                continue;
            }
            const on = inputs.filter((i) => i.checked).length;
            input.checked = on > 0 && on === inputs.length;
            input.indeterminate = on > 0 && on < inputs.length;
        }
    }

    onChange(event) {
        const input = event.target;
        if (input.dataset.group) {
            for (const service of cfg.groups.find((g) => g.key === input.dataset.group).services) {
                input.checked ? this.selection.add(service.key) : this.selection.delete(service.key);
            }
        } else if (input.dataset.service) {
            input.checked ? this.selection.add(input.dataset.service) : this.selection.delete(input.dataset.service);
        }
        this.syncSwitches();
    }

    onClick(event) {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.expand) {
            const id = button.dataset.expand;
            const open = button.getAttribute('aria-expanded') !== 'true';
            button.setAttribute('aria-expanded', String(open));
            this.dialog.querySelector('#' + id).hidden = !open;
            open ? this.expanded.add(id) : this.expanded.delete(id);
            if (button.dataset.labelOpen) button.textContent = open ? button.dataset.labelOpen : button.dataset.labelClosed;
            return;
        }
        switch (button.dataset.action) {
            case 'settings': this.open('settings'); break;
            case 'close': this.dismiss(); break;
            case 'withdraw': core.withdraw(); this.close(); break;
            case 'accept': this.finish(core.optional.map((s) => s.key), 'accept_all'); break;
            case 'reject': this.finish([], 'reject_all'); break;
            case 'save': this.finish([...this.selection], 'custom'); break;
        }
    }

    finish(keys, action) {
        core.decide(keys, action);
        this.close();
    }
}

/* -------------------------------------------------------- <consent-embed> --- */

class ConsentEmbedElement extends HTMLElement {
    connectedCallback() {
        if (this.shadowRoot || !cfg) return;
        this.attachShadow({ mode: 'open' });
        const key = this.getAttribute('service') || '';
        const service = core.services.get(key);
        const name = service?.name || key;
        const t = cfg.texts;
        if (!this.hasAttribute('theme')) this.setAttribute('theme', cfg.theme);
        for (const [prop, value] of Object.entries(cfg.cssVars || {})) {
            if (prop.startsWith('--ck-')) this.style.setProperty(prop, value);
        }
        const label = this.getAttribute('label');
        this.shadowRoot.innerHTML = `<style>${embedCss}</style>${customStyle()}
            <div class="placeholder" part="placeholder" role="group" aria-labelledby="ck-embed-title">
                <h3 id="ck-embed-title">${esc(fill(t.embed_title, { name }))}${label ? ': ' + esc(label) : ''}</h3>
                <p>${esc(fill(t.embed_text, { name }))}</p>
                <div class="buttons">
                    <button type="button" class="btn" part="button" data-action="once">${esc(t.embed_once)}</button>
                    ${service && !service.required ? `<button type="button" class="btn" part="button" data-action="always">${esc(fill(t.embed_always, { name }))}</button>` : ''}
                    <button type="button" class="btn" part="button" data-action="settings">${esc(t.embed_settings)}</button>
                </div>
            </div><slot></slot>`;
        this.shadowRoot.addEventListener('click', (event) => {
            const action = event.target.closest('button')?.dataset.action;
            if (action === 'once') this.load(true);
            if (action === 'always') core.decide([...new Set([...core.accepted(), key])], 'embed');
            if (action === 'settings') api.open();
        });
        this.listener = () => core.has(key) && this.load(false);
        document.addEventListener('consentkit:change', this.listener);
        this.listener();
    }

    disconnectedCallback() {
        document.removeEventListener('consentkit:change', this.listener);
    }

    load(focus) {
        if (this.hasAttribute('loaded')) return;
        const template = this.querySelector(':scope > template');
        if (!template) return;
        this.setAttribute('loaded', '');
        const content = template.content.cloneNode(true);
        for (const script of content.querySelectorAll('script')) script.replaceWith(cloneScript(script));
        const first = content.firstElementChild;
        this.append(content);
        if (focus && first) {
            if (!first.hasAttribute('tabindex') && first.tabIndex < 0) first.setAttribute('tabindex', '-1');
            first.focus({ preventScroll: true });
        }
    }
}

/* ------------------------------------------------------------------ API --- */

const api = {
    has: (key) => core.has(key),
    accepted: () => core.accepted(),
    /**
     * Einwilligung fuer einzelne Dienste erteilen, wie "immer erlauben" am Platzhalter
     * (Protokoll-Aktion "embed"). Fuer eigene 2-Klick-Loesungen ohne <consent-embed>.
     * Unbekannte und notwendige Schluessel werden ignoriert; false, wenn keiner uebrig bleibt.
     */
    accept(keys) {
        const add = [].concat(keys).filter((key) => core.optional.some((s) => s.key === key));
        if (!add.length) return false;
        if (add.some((key) => !core.has(key))) core.decide([...new Set([...core.accepted(), ...add])], 'embed');
        return true;
    },
    open: () => kit()?.open('settings'),
    /** Verwirft die Entscheidung und zeigt den Hinweis erneut. */
    /** Einwilligung widerrufen (wie die Schaltflaeche im Dialog). */
    withdraw: () => core.withdraw(),
    reset() {
        document.cookie = cfg.cookie + '=; Max-Age=0; Path=/';
        try { sessionStorage.removeItem('consent_kit_dismissed'); } catch (e) { /* Storage gesperrt */ }
        location.reload();
    },
    onChange: (callback) => document.addEventListener('consentkit:change', (event) => callback(event.detail)),
};

function kit() {
    return document.querySelector('consent-kit');
}

if (cfg && !customElements.get('consent-kit')) {
    core.init();
    window.ConsentKit = api;
    customElements.define('consent-kit', ConsentKitElement);
    customElements.define('consent-embed', ConsentEmbedElement);

    const start = () => {
        if (!kit()) document.body.prepend(document.createElement('consent-kit'));
        core.apply();
        core.emit('ready');
        // Spaeter eingefuegte Inhalte (AJAX, Slider) nachziehen.
        new MutationObserver(() => core.activateScripts()).observe(document.body, { childList: true, subtree: true });
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();

    document.addEventListener('click', (event) => {
        const opener = event.target.closest?.('[data-consent-kit-open], .consent-kit-open, .consent_manager-show-box, a[href="#consent-kit"]');
        if (!opener) return;
        event.preventDefault();
        api.open();
    });
}
