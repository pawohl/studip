<?php

namespace JsonApi\Routes\ActivityStream;

use User;

class Authority
{
    public static function canShowActivityStream(User $observer, User $user): bool
    {
        if ($GLOBALS['perm']->have_perm('root', $observer->id)) {
            return true;
        }

        return $observer->id === $user->id;
    }
}
