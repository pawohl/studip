<?php
namespace JsonApi\Routes\Courses;

trait CourseMembershipsTrait
{
    private function getCourseMemberships(\Course $course, \User $user, array $filters = [])
    {
        $memberships = $course->members;

        // Filter by permission?
        if (isset($filters['permission'])) {
            $memberships = $memberships->filter(function (\CourseMember $membership) use ($filters) {
                return $membership->status === $filters['permission'];
            });
        }

        // Filter out invisible members if not teacher
        if (!Authority::canEditCourse($user, $course)) {
            $memberships = $memberships->filter(function (\CourseMember $membership) use ($user) {
                return $membership->user_id === $user->id || $membership->visible !== 'no';
            });
        }

        // Filter out students if not in course
        if (!Authority::canShowCourse($user, $course, Authority::SCOPE_EXTENDED)) {
            $memberships = $memberships->filter(function (\CourseMember $membership) use ($user) {
                return !in_array($membership->status, ['autor', 'user']);
            });
        }

        return $memberships;
    }
}
