<?php

namespace JsonApi\Routes\Avatar;

use Avatar;
use CourseAvatar;
use InstituteAvatar;
use JsonApi\Errors\RecordNotFoundException;
use Range;
use RangeFactory;
use StudygroupAvatar;

trait AvatarHelpers
{
    protected static function getRange(string $rangeId, string $rangeType): Range
    {
        $range = RangeFactory::find($rangeId, match ($rangeType) {
            'users'      => ['user'],
            'institutes' => ['inst', 'fak'],
            'courses'    => ['sem'],
        });

        if (!$range) {
            throw new RecordNotFoundException('Unknown range given');
        }

        return $range;
    }

    /**
     * @return class-string<Avatar>
     */
    protected static function getAvatarClassForRange(Range $range): string
    {
        return match ($range->getRangeType()) {
            'user'      => Avatar::class,
            'institute' => InstituteAvatar::class,
            'course'    => $range->isStudygroup() ? StudygroupAvatar::class : CourseAvatar::class,
        };
    }
}
