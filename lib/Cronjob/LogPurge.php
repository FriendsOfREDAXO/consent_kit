<?php

namespace KLXM\ConsentKit\Cronjob;

use KLXM\ConsentKit\Log;
use rex_cronjob;
use rex_i18n;

final class LogPurge extends rex_cronjob
{
    public function execute(): bool
    {
        $this->setMessage(rex_i18n::msg('consent_kit_log_purged', (string) Log::purge()));
        return true;
    }

    public function getTypeName(): string
    {
        return rex_i18n::msg('consent_kit_cronjob_log_purge');
    }
}
