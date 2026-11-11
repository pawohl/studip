<?php

namespace Studip;
use AccessDeniedException;

class TFARequiredException extends AccessDeniedException
{
    public function __construct($message = '', $code = 403, ?Exception $previous = null)
    {
        $defaultMessage = implode('|', [
            _('Zugriff verweigert'),
            _('Bitte bestätigen Sie die Zwei-Faktor-Authentifizierung.'),
        ]);

        parent::__construct(
            $message ?: $defaultMessage,
            $code,
            $previous
        );
    }
}
