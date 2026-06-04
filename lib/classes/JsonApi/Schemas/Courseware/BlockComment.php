<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class BlockComment extends SchemaProvider
{
    const TYPE = 'courseware-block-comments';

    const REL_BLOCK = 'block';
    const REL_USER = 'user';

    protected ?array $allowedIncludes = [
        self::REL_BLOCK,
        self::REL_USER,
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
            'comment' => (string) $resource['comment'],
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
