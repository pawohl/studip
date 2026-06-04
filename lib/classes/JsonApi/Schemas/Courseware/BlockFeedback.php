<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class BlockFeedback extends SchemaProvider
{
    const TYPE = 'courseware-block-feedback';

    const REL_USER = 'user';
    const REL_BLOCK = 'block';

    protected ?array $allowedIncludes = [
        self::REL_USER,
        self::REL_BLOCK,
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
            'feedback' => (string) $resource['feedback'],
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

        $relationships[self::REL_BLOCK] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->block),
            ],
            self::RELATIONSHIP_DATA => $resource->block,
        ];

        $relationships[self::REL_USER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->user),
            ],
            self::RELATIONSHIP_DATA => $resource->user,
        ];

        return $relationships;
    }
}
