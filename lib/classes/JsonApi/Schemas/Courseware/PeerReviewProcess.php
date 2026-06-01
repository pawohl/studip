<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class PeerReviewProcess extends SchemaProvider
{
    const TYPE = 'courseware-peer-review-processes';

    const REL_COURSE = 'course';
    const REL_OWNER = 'owner';
    const REL_PEER_REVIEWS = 'reviews';
    const REL_TASK_GROUP = 'task-group';

    protected array $allowedIncludes = [
        self::REL_COURSE,
        self::REL_OWNER,
        self::REL_PEER_REVIEWS,
        self::REL_TASK_GROUP,
    ];

    /**
     * {@inheritdoc}
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'configuration' => $resource['configuration']->getIterator(),
            'review-start' => date('c', $resource['review_start']),
            'review-end' => date('c', $resource['review_end']),
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $course = $resource->getCourse();
        $relationships[self::REL_COURSE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($course),
            ],
            self::RELATIONSHIP_DATA => $course,
        ];

        $relationships[self::REL_OWNER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->owner),
            ],
            self::RELATIONSHIP_DATA => $resource->owner,
        ];

        $relationships[self::REL_PEER_REVIEWS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_PEER_REVIEWS),
            ],
        ];

        $relationships[self::REL_TASK_GROUP] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->task_group),
            ],
            self::RELATIONSHIP_DATA => $resource->task_group,
        ];

        return $relationships;
    }
}
