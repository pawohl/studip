<?php

namespace JsonApi\Schemas\Forum;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Subscription extends SchemaProvider
{
    const TYPE = 'forum-subscriptions';
    const REL_USER = 'user';
    const REL_RANGE = 'range';
    const REL_SUBJECT = 'subject';

    protected ?array $allowedIncludes = [
        self::REL_USER,
        self::REL_RANGE,
        self::REL_SUBJECT,
    ];

    /**
     * @param \Forum\Subscription $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'notification-type' => $resource->notification_type,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate)
        ];
    }

    /**
     * @param \Forum\Subscription $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addUserRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_USER));
        $relationships = $this->addSubjectRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_SUBJECT));
        $relationships = $this->addRangeRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_RANGE));

        return $relationships;
    }

    private function addUserRelationship(array $relationships, \Forum\Subscription $subscription, bool $withUser = false)
    {
        $user = $subscription->user;

        if ($withUser && $user) {
            $relationships[self::REL_USER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($user)
                ],
                self::RELATIONSHIP_DATA => $user
            ];
        }

        return $relationships;
    }

    private function addSubjectRelationship(array $relationships, $subscription, bool $withSubject = false)
    {
        $subject = $subscription->subject_object;

        if ($withSubject && $subject) {
            $relationships[self::REL_SUBJECT] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($subject)
                ],
                self::RELATIONSHIP_DATA => $subject
            ];
        }

        return $relationships;
    }

    private function addRangeRelationship(array $relationships, $subscription, bool $withRange = false)
    {
        if ($withRange) {
            $relationships[self::REL_RANGE] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($subscription->range),
                ],
                self::RELATIONSHIP_DATA => $subscription->range,
            ];
        }

        return $relationships;
    }
}
