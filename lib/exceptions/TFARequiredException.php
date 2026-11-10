<?php

namespace Studip;

use AccessDeniedException;
use Exception;

use Studip\Framework\Exceptions\ProductionSafeException;

class TFARequiredException extends AccessDeniedException implements ProductionSafeException
{
    public function __construct(?string $message = null, int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message ?: $this->getSafeMessage(), $code, $previous);
    }

    public function getSafeTitle(): string
    {
        return _('Zugriff verweigert');
    }

    public function getSafeMessage(): string
    {
        return _('Bitte bestätigen Sie die Zwei-Faktor-Authentifizierung.');
    }
}
