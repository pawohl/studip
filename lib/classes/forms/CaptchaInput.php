<?php

namespace Studip\Forms;

use CaptchaChallenge;
use URLHelper;

/**
 * The Text class represents a part of a form that displays a captcha.
 */
final class CaptchaInput extends Input
{
    public function hasValidation(): bool
    {
        return true;
    }

    public function getValidationCallback(): callable
    {
        return fn($value) => \CaptchaChallenge::validatePayload($value);
    }

    public function render(): string
    {
        return sprintf(
            '<captcha-input challenge="%s" v-model="%s" auto="onload"></captcha-input>',
            URLHelper::getLink('dispatch.php/captcha/challenge', [], true),
            htmlReady($this->name)
        );
    }

    public function renderWithCondition(): string
    {
        return $this->render();
    }


}
