/* Consent Kit – Backend */
(function () {
    'use strict';

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* Delegation ueberlebt PJAX; Rueckfragen (data-confirm) uebernimmt der Core. */
    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-ck-repeater-add]');
        if (add) {
            const type = add.dataset.ckRepeaterAdd;
            const list = document.querySelector('[data-ck-repeater="' + type + '"]');
            const index = 'n' + Date.now();
            const holder = document.createElement('div');
            holder.innerHTML = document.getElementById('ck-' + type + '-template').innerHTML.replace(/__INDEX__/g, index).replace(/ck-field-(\d+)/g, 'ck-field-' + index + '-$1');
            const row = holder.firstElementChild;
            list.append(row);
            row.querySelector('input, select').focus();
            updateCount(type);
            return;
        }

        const remove = event.target.closest('[data-ck-row-remove]');
        if (remove) {
            const row = remove.closest('[data-ck-row]');
            const list = row.parentElement;
            const next = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            (next?.querySelector('input, select') || document.querySelector('[data-ck-repeater-add="' + list.dataset.ckRepeater + '"]')).focus();
            updateCount(list.dataset.ckRepeater);
            return;
        }

        const filter = event.target.closest('[data-ck-filter]');
        if (filter) {
            for (const button of filter.parentElement.children) {
                const active = button === filter;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', String(active));
            }
            filterPresets();
            return;
        }

        const preview = event.target.closest('[data-ck-preview]');
        if (preview) {
            for (const button of preview.parentElement.children) {
                const active = button === preview;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', String(active));
            }
            if (preview.dataset.ckPreview === 'size') {
                document.querySelector('.ck-preview-frame').dataset.size = preview.dataset.value;
            }
            sendPreview();
            return;
        }

        if (event.target.closest('[data-ck-scan-start]')) scan();

        const translate = event.target.closest('[data-ck-translate]');
        if (translate) translateField(translate);

        const unlock = event.target.closest('[data-ck-key-unlock]');
        if (unlock) {
            if (!window.confirm(unlock.dataset.msg)) return;
            const input = unlock.closest('.ck-field').querySelector('[data-ck-key-locked]');
            input.readOnly = false;
            input.focus();
            input.select();
            unlock.remove();
            return;
        }

        const translateAll = event.target.closest('[data-ck-translate-all]');
        if (translateAll) translateAllFields(translateAll);
    });

    /* Alle leeren Sprachfelder des Formulars nacheinander uebersetzen. */
    async function translateAllFields(button) {
        const form = button.closest('form');
        const progress = form.querySelector('[data-ck-translate-progress]');
        const buttons = [...form.querySelectorAll('[data-ck-translate]')].filter((b) => {
            const target = document.getElementById(b.dataset.ckTranslate);
            const source = b.dataset.sourceId ? document.getElementById(b.dataset.sourceId)?.value : b.dataset.sourceText;
            return target && !target.value.trim() && source && source.trim();
        });
        if (!buttons.length) { progress.textContent = button.dataset.msgNone; return; }
        button.disabled = true;
        let done = 0;
        for (const b of buttons) {
            progress.textContent = button.dataset.msgProgress.replace('{0}', ++done).replace('{1}', buttons.length);
            await translateField(b, true);
        }
        button.disabled = false;
        progress.textContent = button.dataset.msgProgress.replace('{0}', buttons.length).replace('{1}', buttons.length);
        button.focus();
    }

    /* Sprachfeld per WriteAssist aus der Standardsprache fuellen. */
    async function translateField(button, silent = false) {
        const target = document.getElementById(button.dataset.ckTranslate);
        const source = button.dataset.sourceId ? document.getElementById(button.dataset.sourceId)?.value : button.dataset.sourceText;
        const status = button.closest('form')?.querySelector('[data-ck-translate-status]');
        const say = (text) => { if (status) status.textContent = text; };
        if (!target) return;
        if (!source || !source.trim()) { say(button.dataset.msgEmpty || 'Quelle ist leer'); target.focus(); return; }
        if (target.value.trim() && !window.confirm(button.dataset.msgOverwrite || 'Vorhandenen Text ersetzen?')) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            const body = new FormData();
            body.append('text', source);
            body.append('target_lang', button.dataset.targetLang);
            body.append('source_lang', button.dataset.sourceLang);
            body.append('preserve_formatting', '0');
            const response = await fetch('index.php?rex-api-call=writeassist_translate', { method: 'POST', body, credentials: 'same-origin' });
            const data = await response.json();
            if (!data.success || !data.translation) throw new Error(data.error || 'Übersetzungsfehler');
            target.value = data.translation.trim();
            target.dispatchEvent(new Event('input', { bubbles: true }));
            say((button.dataset.msgDone || 'Übersetzt') + ': ' + button.dataset.targetLang);
        } catch (error) {
            say(error.message);
            if (!silent) window.alert(error.message);
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (!silent) target.focus();
        }
    }

    document.addEventListener('change', (event) => {
        if (event.target.name === 'service[domain_mode]') {
            const list = document.querySelector('[data-ck-domain-list]');
            list.hidden = event.target.value !== 'selected';
            if (!list.hidden) list.querySelector('input')?.focus();
        }
    });

    document.addEventListener('input', (event) => {
        if (event.target.id === 'ck-preset-search') filterPresets();
        if (event.target.matches('[data-ck-var]')) {
            const output = event.target.parentElement.querySelector('output');
            if (output) output.textContent = event.target.value;
            sendPreview();
        }
    });

    /* Pflichtfeld in einem verdeckten Tab: Tab oeffnen, statt stumm zu scheitern. */
    document.addEventListener('invalid', (event) => {
        const pane = event.target.closest('.tab-pane:not(.active)');
        if (pane && window.jQuery) window.jQuery('a[href="#' + pane.id + '"]').tab('show');
    }, true);

    function updateCount(type) {
        const badge = document.querySelector('[data-ck-count="' + type + '"]');
        if (badge) badge.textContent = document.querySelectorAll('[data-ck-repeater="' + type + '"] [data-ck-row]').length;
    }

    /* Schalter in der Uebersicht ohne Seitenwechsel: absenden, Tabelle tauschen, Fokus halten. */
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('#ck-overview .ck-inline-form');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        const button = event.submitter || form.querySelector('button');
        const focusId = button?.dataset?.focus;
        const overview = document.getElementById('ck-overview');
        overview.setAttribute('aria-busy', 'true');
        // Sofortige Rueckmeldung; die Antwort des Servers ersetzt die Tabelle gleich danach.
        if (button?.getAttribute('role') === 'switch') {
            const on = button.getAttribute('aria-checked') !== 'true';
            button.setAttribute('aria-checked', String(on));
            button.classList.toggle('is-on', on);
            button.querySelector('.ck-switch')?.classList.toggle('is-on', on);
            button.querySelector('.rex-icon')?.setAttribute('class', 'rex-icon ' + (on ? 'fa-check-circle' : 'fa-circle-thin'));
        }
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'consent-kit' } });
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.getElementById('ck-overview');
            if (!fresh) throw new Error('no overview');
            overview.replaceWith(fresh);
            const checklist = doc.querySelector('.ck-checklist');
            if (checklist) document.querySelector('.ck-checklist')?.replaceWith(checklist);
            const alert = doc.querySelector('.alert');
            document.querySelectorAll('.ck-ajax-alert').forEach((el) => el.remove());
            if (alert) {
                alert.classList.add('ck-ajax-alert');
                alert.setAttribute('role', 'alert');
                fresh.before(alert);
            }
            if (focusId) {
                let target = document.querySelector('[data-focus="' + focusId + '"]');
                // Nach dem Verschieben ist die Schaltflaeche am Rand evtl. deaktiviert.
                if (target?.disabled) target = target.closest('tr').querySelector('button:not([disabled]), a');
                target?.focus();
            }
        } catch (error) {
            form.submit();
        }
    });

    function filterPresets() {
        const list = document.querySelector('.ck-presets');
        if (!list) return;
        const term = (document.getElementById('ck-preset-search').value || '').trim().toLowerCase();
        const group = document.querySelector('[data-ck-filter].active')?.dataset.ckFilter || '';
        let visible = 0;
        for (const card of list.children) {
            const custom = card.classList.contains('ck-preset-custom');
            const show = custom || ((!group || card.dataset.group === group) && (!term || card.dataset.search.includes(term)));
            card.hidden = !show;
            if (show && !custom) visible++;
        }
        document.getElementById('ck-preset-status').textContent = list.dataset.statusTemplate.replace('{0}', visible);
    }

    /* ------------------------------------------------------------ Design */

    function designValues() {
        const vars = {};
        for (const input of document.querySelectorAll('[data-ck-var]')) {
            if (input.value && input.value.toLowerCase() !== input.dataset.default.toLowerCase()) vars[input.dataset.ckVar] = input.value;
        }
        return vars;
    }

    function sendPreview() {
        const frame = document.querySelector('[data-ck-preview-frame]');
        if (!frame) return;
        const pick = (name) => document.querySelector('[data-ck-preview="' + name + '"].active')?.dataset.value;
        const vars = designValues();
        frame.contentWindow?.postMessage({
            source: 'consent-kit-design', vars, view: pick('view'), theme: pick('theme'),
            layout: frame.dataset.layout, position: frame.dataset.position,
        }, location.origin);

        const css = Object.entries(vars).map(([name, value]) => '    ' + name + ': ' + value + ';').join('\n');
        document.querySelector('[data-ck-css]').textContent = 'consent-kit,\nconsent-embed {\n' + (css || '    /* Standardwerte */') + '\n}';
        checkContrast(pick('theme'));
    }

    function luminance(hex) {
        const channel = (i) => {
            const v = parseInt(hex.slice(i, i + 2), 16) / 255;
            return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
        };
        return 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
    }

    function checkContrast() {
        const box = document.querySelector('[data-ck-contrast]');
        if (!box) return;
        const value = (name) => document.querySelector('[data-ck-var="' + name + '"]')?.value || '';
        const label = (name) => document.querySelector('label[for="ck-var-' + name.slice(5) + '"]')?.textContent || name;
        const failures = [];
        for (const prefix of ['--ck-', '--ck-dark-']) {
            for (const [fg, bg, min] of [['text', 'bg', 4.5], ['muted', 'bg', 4.5], ['accent', 'bg', 4.5], ['button-text', 'button-bg', 4.5], ['border', 'bg', 3]]) {
                const a = value(prefix + fg);
                const b = value(prefix + bg);
                if (!/^#[0-9a-f]{6}$/i.test(a) || !/^#[0-9a-f]{6}$/i.test(b)) continue;
                const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
                const ratio = (hi + 0.05) / (lo + 0.05);
                if (ratio < min) failures.push(label(prefix + fg) + ' / ' + label(prefix + bg) + (prefix === '--ck-dark-' ? ' (dark)' : '') + ': ' + ratio.toFixed(1) + ':1 < ' + min + ':1');
            }
        }
        box.className = 'ck-contrast ' + (failures.length ? 'ck-contrast-fail' : 'ck-contrast-ok');
        box.innerHTML = failures.length
            ? '<strong>' + esc(box.dataset.labelFail) + '</strong><ul><li>' + failures.map(esc).join('</li><li>') + '</li></ul>'
            : esc(box.dataset.labelOk);
    }

    window.addEventListener('message', (event) => {
        if (event.origin === location.origin && event.data?.source === 'consent-kit-preview') sendPreview();
    });

    /* ----------------------------------------------------------- Scanner */

    async function scan() {
        const root = document.querySelector('[data-ck-scan]');
        const result = root.querySelector('[data-ck-scan-result]');
        const labels = JSON.parse(root.dataset.labels);
        const url = document.getElementById('ck-scan-url').value;
        let target;
        try {
            target = new URL(url, location.href);
        } catch (e) {
            target = null;
        }
        if (!target || target.origin !== location.origin) {
            result.innerHTML = '<div class="alert alert-warning">' + esc(labels.scan_cross_origin) + '</div>';
            return;
        }
        result.innerHTML = '<p><i class="rex-icon fa-spinner fa-spin" aria-hidden="true"></i> …</p>';

        const frame = document.createElement('iframe');
        frame.hidden = true;
        frame.src = target.href;
        document.body.append(frame);
        try {
            await new Promise((resolve, reject) => {
                frame.addEventListener('load', resolve, { once: true });
                setTimeout(reject, 20000);
            });
            // Nachladende Tags brauchen einen Moment, bis sie schreiben.
            await new Promise((resolve) => setTimeout(resolve, 3000));
            const win = frame.contentWindow;
            const entries = [];
            // Die Cookie Store API kennt Ablaufdatum und Domain; document.cookie nur die Namen.
            const store = win.cookieStore ? await win.cookieStore.getAll().catch(() => null) : null;
            if (store) {
                for (const c of store) entries.push({ name: c.name, type: 'cookie', days: c.expires ? (c.expires - Date.now()) / 864e5 : 0, host: c.domain || '' });
            } else {
                for (const name of win.document.cookie.split('; ').map((c) => c.split('=')[0]).filter(Boolean)) entries.push({ name: decodeURIComponent(name), type: 'cookie', days: null, host: '' });
            }
            for (const name of Object.keys(win.localStorage)) entries.push({ name, type: 'local_storage' });
            for (const name of Object.keys(win.sessionStorage)) entries.push({ name, type: 'session_storage' });

            const body = new URLSearchParams();
            entries.forEach((entry, i) => {
                body.append('entries[' + i + '][name]', entry.name);
                body.append('entries[' + i + '][type]', entry.type);
            });
            const response = await fetch(root.dataset.lookup, { method: 'POST', body, credentials: 'same-origin' });
            const data = await response.json();
            if (!data.entries.length) {
                result.innerHTML = '<div class="alert alert-info">' + esc(labels.scan_none) + '</div>';
                return;
            }
            const measured = new Map(entries.map((e) => [e.type + ':' + e.name, e]));
            const formatDays = (days) => {
                if (days === null || days === undefined) return labels.scan_no_duration;
                if (days <= 0) return labels.duration_session;
                const pick = (n, unit) => { const v = Math.round(n); return (v === 1 ? labels['duration_' + unit + '_one'] : labels['duration_' + unit]).replace('{n}', v); };
                if (days < 1 / 12) return pick(days * 1440, 'minutes');
                if (days < 2) return pick(days * 24, 'hours');
                if (days < 60) return pick(days, 'days');
                if (days < 350) return pick(days / 30.44, 'months');
                return pick(days / 365.25, 'years');
            };
            const rows = data.entries.map((entry) => {
                let text;
                let state;
                const found = measured.get(entry.type + ':' + entry.name) || {};
                let duration = entry.type === 'cookie' ? esc(formatDays(found.days)) : '–';
                if (entry.type === 'cookie' && found.host) duration += '<br><span class="text-muted">' + esc(found.host) + '</span>';
                if (entry.service) {
                    state = entry.active ? 'ok' : 'warn';
                    text = esc((entry.active ? labels.scan_known : labels.scan_inactive).replace('{0}', entry.service));
                    if (entry.documented) {
                        text += '<br><span class="text-muted">' + esc(labels.scan_documented.replace('{0}', entry.documented.text)) + '</span>';
                        // Sitzung vs. Datum oder mehr als ein Viertel Unterschied: Dokumentation pruefen.
                        const d = entry.documented.days;
                        const m = found.days;
                        if (m !== null && m !== undefined && ((d === null) !== (m <= 0) || (d !== null && m > 0 && Math.abs(m - d) / d > 0.25))) {
                            state = 'warn';
                            text += '<br><strong>' + esc(labels.scan_deviates) + '</strong>';
                        }
                    }
                } else {
                    state = 'warn';
                    text = '<strong>' + esc(labels.scan_unknown) + '</strong>';
                    if (entry.catalog) {
                        text += '<br>' + esc(labels.scan_catalog) + ' ' + esc(entry.catalog.platform) + ' · ' + esc(entry.catalog.category) + ' · ' + esc(entry.catalog.retention)
                            + '<br><span class="text-muted">' + esc(entry.catalog.description) + '</span>';
                    }
                }
                return '<tr class="ck-scan-' + state + '"><td><code>' + esc(entry.name) + '</code></td><td>' + esc(labels['type_' + entry.type]) + '</td><td>' + duration + '</td><td>' + text + '</td></tr>';
            }).join('');
            result.innerHTML = '<table class="table"><thead><tr><th>' + esc(labels.scan_col_name) + '</th><th>' + esc(labels.scan_col_type) + '</th><th>' + esc(labels.scan_col_duration) + '</th><th>' + esc(labels.scan_col_result) + '</th></tr></thead><tbody>' + rows + '</tbody></table>';
        } catch (error) {
            result.innerHTML = '<div class="alert alert-danger">' + esc(labels.scan_failed) + '</div>';
        } finally {
            frame.remove();
        }
    }

    /* Felder, die nur bei bestimmten Einstellungen Sinn ergeben. */
    function syncConditional() {
        for (const block of document.querySelectorAll('[data-ck-show-if]')) {
            const inputs = [...document.getElementsByName(block.dataset.ckShowIf)];
            const current = inputs.filter((i) => (i.type === 'checkbox' || i.type === 'radio' ? i.checked : true)).map((i) => i.value);
            block.hidden = !block.dataset.ckShowValues.split(',').some((v) => current.includes(v));
        }
    }
    document.addEventListener('change', syncConditional);

    const ready = () => {
        syncConditional();
        document.querySelector('.ck-form [autofocus]')?.focus();
        if (document.querySelector('[data-ck-design]')) sendPreview();
    };
    if (window.jQuery) window.jQuery(document).on('rex:ready', ready);
    else document.addEventListener('DOMContentLoaded', ready);
})();
