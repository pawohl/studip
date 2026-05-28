<?php

namespace JsonApi\Schemas;

use JsonApi\Routes\Courses\Authority as CourseAuthority;
use JsonApi\Routes\CourseMemberships\Authority as MembershipAuthority;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class CourseMember extends SchemaProvider
{
    const TYPE = 'course-memberships';
    const REL_COURSE = 'course';
    const REL_USER = 'user';

    public function getId($membership): ?string
    {
        return $membership->id;
    }

    public function getAttributes($membership, ContextInterface $context): iterable
    {
        $attributes = [
            'permission' => $membership->status,
            'position' => (int) $membership->position,
            'group' => (int) $membership->gruppe,
            'mkdate' => date('c', $membership->mkdate),
            'label' => $membership->label,
        ];

        if ($this->currentUser) {
            if (MembershipAuthority::canIndexMembershipsOfUser($this->currentUser, $membership->user)) {
                $attributes['visible'] = $membership->visible;
            }
            if (CourseAuthority::canEditCourse($this->currentUser, $membership->course)) {
                $attributes['comment'] = $membership->comment;
                $attributes['visible'] = $membership->visible;
            }
        }

        return $attributes;
    }

    public function getRelationships($membership, ContextInterface $context): iterable
    {
        $relationships = [];

        $course = \Course::build(['id' => $membership['seminar_id']], false);
        $relationships[self::REL_COURSE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($course)
            ],
            self::RELATIONSHIP_DATA => $membership->course,
        ];

        $user = \User::build(['id' => $membership['user_id']], false);
        $relationships[self::REL_USER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($user)
            ],
            self::RELATIONSHIP_DATA => $membership->user,
        ];

        return $relationships;
    }
}
