<?php

namespace JsonApi\Routes\Admission;

use Config;
use CourseSet;
use Institute;
use User;

class Authority
{

    /**
     * Checks if the given user may create a courseset.
     * @param User $user
     * @param array $attributes courseset configuration
     * @return bool
     */
    public static function canCreateCourseSets(User $user, array $attributes): bool
    {
        // Check if we come from inside a course. If yes, we must at least have tutor permission in this course.
        if (
            count($attributes['institutes']) === 0
            && count($attributes['courses']) === 1
            && $GLOBALS['perm']->have_studip_perm('tutor', current($attributes['courses']))
        ) {
            return true;
        }

        // Do you have to be admin in order to administrate coursesets?
        $minperm = \Config::get()->ALLOW_DOZENT_COURSESET_ADMIN ? 'dozent' : 'admin';

        // Check institute permissions.
        foreach ($attributes['institutes'] as $inst) {
            if (!$GLOBALS['perm']->have_studip_perm($minperm, $inst, $user->id)) {
                return false;
            }
        }

        // The user must have at least tutor permissions in all assigned courses:
        foreach ($attributes['courses'] as $course_id) {
            if (!$GLOBALS['perm']->have_studip_perm('tutor', $course_id, $user->id)) {
                return false;
            }
        }

        // At this point, we've checked every possible obstacle.
        return true;
    }

    public static function canCreateAdmissionRules(User $user): bool
    {
        return $GLOBALS['perm']->have_perm('dozent', $user->id);
    }

    public static function canEditAdmissionRules(User $user, string $courseSetId = ''): bool
    {
        // This rule belongs to a courseset, check if the user my edit that.
        if ($courseSetId) {
            $courseSet = new \CourseSet($courseSetId);

            return $courseSet->isUserAllowedToEdit($user->id);
        } else {
            return $GLOBALS['perm']->have_perm('admin', $user->id)
                || (
                    Config::get()->ALLOW_DOZENT_COURSESET_ADMIN
                    && $GLOBALS['perm']->have_perm('tutor', $user->id)
                );
        }
    }

    /**
     * Checks if the given user may update the given courseset.
     *
     * @param User $user
     * @param CourseSet $courseset
     * @return bool
     */
    public static function canUpdateCourseSet(User $user, CourseSet $courseset)
    {
        return $courseset->isUserAllowedToEdit($user->id);
    }

}
