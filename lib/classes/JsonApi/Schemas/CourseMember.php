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

    protected ?array $allowedIncludes = [
        self::REL_COURSE,
        self::REL_USER,
    ];

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

        $course = $this->shouldInclude($context, self::REL_COURSE)
            ? $membership->course
            : \Course::build(['id' => $membership['seminar_id']], false);
        $relationships[self::REL_COURSE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($course)
            ],
            self::RELATIONSHIP_DATA => $course,
        ];

        $user = $this->shouldInclude($context, self::REL_USER)
            ? $membership->user
            : \User::build(['id' => $membership['user_id']], false);
        $relationships[self::REL_USER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($user)
            ],
            self::RELATIONSHIP_DATA => $user,
        ];

        return $relationships;
    }
}
