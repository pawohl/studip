<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class TaskFeedback extends SchemaProvider
{
    const TYPE = 'courseware-task-feedback';

    const REL_TASK = 'task';
    const REL_LECTURER = 'lecturer';

    protected ?array $allowedIncludes = [
        self::REL_TASK,
        self::REL_LECTURER,
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
            'content'   => (string) $resource['content'],
            'mkdate'    => date('c', $resource['mkdate']),
            'chdate'    => date('c', $resource['chdate']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships[self::REL_LECTURER] = $resource['lecturer_id']
        ? [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->lecturer),
            ],
            self::RELATIONSHIP_DATA => $resource->lecturer,
        ]
        : [self::RELATIONSHIP_DATA => $resource->lecturer];

        $relationships[self::REL_TASK] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_TASK),
            ],
        ];

        return $relationships;
    }
}
