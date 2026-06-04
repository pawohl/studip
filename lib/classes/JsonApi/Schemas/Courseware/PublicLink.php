<?php

namespace JsonApi\Schemas\Courseware;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class PublicLink extends SchemaProvider
{
    const TYPE = 'courseware-public-links';

    const REL_STRUCTURAL_ELEMENT = 'structural-element';

    protected ?array $allowedIncludes = [
        self::REL_STRUCTURAL_ELEMENT,
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
    public function getAttributes($resource, ContextInterface $context): array
    {
        return [
            'password' => $resource['password'],
            'expire-date' => $resource['expire_date'] ? date('Y-m-d', $resource['expire_date']) : null,
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

        $relationships[self::REL_STRUCTURAL_ELEMENT] = $resource->structural_element
        ? [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->structural_element),
            ],
            self::RELATIONSHIP_DATA => $resource->structural_element,
        ]
        : [self::RELATIONSHIP_DATA => null];

        return $relationships;
    }
}
