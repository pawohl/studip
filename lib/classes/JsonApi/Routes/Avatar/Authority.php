<?php

namespace JsonApi\Routes\Avatar;

use Avatar;
use Range;
use User;
use Course;
use Institute;

class Authority
{
    public static function canShowAvatarOfRange(User $user, Range $range): bool
    {
        return true;
    }

    public static function canEditAvatarOfRange(User $user, Range $range): bool
    {
        return $range->isEditableByUser($user->id);
    }
}
