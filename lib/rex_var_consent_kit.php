<?php

/**
 * REX_CONSENT_KIT[] im <head> des Templates – Alternative zur automatischen Einbindung.
 * REX_CONSENT_KIT[output=overview] gibt die Dienste-Uebersicht fuer die Datenschutzerklaerung aus.
 */
class rex_var_consent_kit extends rex_var
{
    protected function getOutput()
    {
        if ('overview' === $this->getArg('output', '', true)) {
            return '\KLXM\ConsentKit\Consent::overview(' . (int) $this->getArg('level', 3, true) . ')';
        }
        return '\KLXM\ConsentKit\Frontend::head()';
    }
}
