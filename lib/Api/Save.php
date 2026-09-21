<?php

namespace KLXM\ConsentKit\Api;

use KLXM\ConsentKit\Consent;
use KLXM\ConsentKit\I18n;
use rex;
use rex_api_function;
use rex_api_result;
use rex_extension;
use rex_extension_point;
use rex_request;
use rex_response;
use rex_sql;

/**
 * Nimmt die Entscheidung des Besuchers entgegen, protokolliert sie und setzt
 * den Consent-Cookie per HTTP-Header (Safari kappt per JS gesetzte Cookies
 * auf 7 Tage). Es werden weder IP noch User-Agent gespeichert.
 */
final class Save extends rex_api_function
{
    protected $published = true;

    private const ACTIONS = ['accept_all', 'reject_all', 'custom', 'gpc', 'embed'];

    public function execute(): rex_api_result
    {
        rex_response::cleanOutputBuffers();

        $fetchSite = (string) rex_request::server('HTTP_SEC_FETCH_SITE', 'string', '');
        if ('post' !== rex_request::requestMethod()) {
            $this->fail(rex_response::HTTP_BAD_REQUEST, 'POST required');
        }
        if ('' !== $fetchSite && !in_array($fetchSite, ['same-origin', 'none'], true)) {
            $this->fail(rex_response::HTTP_FORBIDDEN, 'Cross-site request');
        }

        $body = (string) file_get_contents('php://input', false, null, 0, 16384);
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            $this->fail(rex_response::HTTP_BAD_REQUEST, 'Invalid payload');
        }

        $config = Consent::config();
        $optional = [];
        foreach ($config['groups'] as $group) {
            if ($group['required']) {
                continue;
            }
            foreach ($group['services'] as $service) {
                $optional[$service['key']] = $service['h'];
            }
        }

        $requested = array_filter((array) ($payload['accepted'] ?? []), 'is_string');
        $accepted = array_intersect_key($optional, array_flip($requested));
        $rejected = array_diff_key($optional, $accepted);

        $action = (string) ($payload['action'] ?? 'custom');
        if (!in_array($action, self::ACTIONS, true)) {
            $action = 'custom';
        }

        $previous = Consent::parseState(['id' => $payload['id'] ?? null]);
        $state = [
            'id' => $previous['id'] ?? self::uuid(),
            'e' => (int) $config['epoch'],
            'rev' => (int) $config['rev'],
            'ts' => time(),
            'a' => (object) $accepted,
            'r' => (object) $rejected,
        ];

        rex_sql::factory()
            ->setTable(rex::getTable('consent_kit_log'))
            ->setValue('consent_id', $state['id'])
            ->setValue('host', Consent::domain()['host'])
            ->setValue('revision_id', $state['rev'])
            ->setValue('action', $action)
            ->setValue('accepted', implode(',', array_keys($accepted)))
            ->setValue('rejected', implode(',', array_keys($rejected)))
            ->setValue('gpc', !empty($payload['gpc']) ? 1 : 0)
            ->setValue('clang', substr(I18n::normalize((string) ($payload['lang'] ?? '')), 0, 10))
            ->setValue('createdate', date('Y-m-d H:i:s'))
            ->insert();

        rex_extension::registerPoint(new rex_extension_point('CONSENT_KIT_SAVED', $state['id'], [
            'action' => $action,
            'accepted' => array_keys($accepted),
            'rejected' => array_keys($rejected),
            'revision' => $state['rev'],
            'gpc' => !empty($payload['gpc']),
            'host' => Consent::domain()['host'],
        ]));

        rex_response::sendCookie(Consent::COOKIE, (string) json_encode($state), [
            'expires' => time() + 86400 * (int) $config['days'],
            'path' => '/',
            'secure' => rex_request::isHttps(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        // Ohne passenden Artikel haette die Antwort sonst den 404-Status des Frontends.
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::setHeader('Cache-Control', 'no-store');
        rex_response::sendJson(['ok' => true, 'state' => $state]);
        exit;
    }

    private function fail(string $status, string $message): never
    {
        rex_response::setStatus($status);
        rex_response::sendJson(['ok' => false, 'error' => $message]);
        exit;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
