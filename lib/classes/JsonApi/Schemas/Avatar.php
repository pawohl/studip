<?php

namespace JsonApi\Schemas;

use Avatar as StudipAvatar;
use JsonApi\Errors\RecordNotFoundException;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Avatar extends SchemaProvider
{
    public const TYPE = 'avatar';
    const REL_RANGE = 'range';

    protected ?array $allowedIncludes = [
        self::REL_RANGE,
    ];

    /**
     * @param StudipAvatar $resource
     */
    public function getId($resource): ?string
    {
        return $resource->getId();
    }

    /**
     * @param StudipAvatar $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'type' => $resource::AVATAR_TYPE,
            'customized' => $resource->is_customized(),
            'is-nobody' => $resource->isNobody(),
        ];
    }

    /**
     * @param StudipAvatar $resource
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * @param StudipAvatar $resource
     */
    public function getResourceMeta($resource)
    {
        return [
            'url' => [
                'normal' => $resource->getURL(StudipAvatar::NORMAL),
                'medium' => $resource->getURL(StudipAvatar::MEDIUM),
                'small' => $resource->getURL(StudipAvatar::SMALL),
            ]
        ];
    }

    /**
     * @param StudipAvatar $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $range = \RangeFactory::createRange($resource::AVATAR_TYPE, $resource->getId());
        if ($range) {
            $relationships[self::REL_RANGE] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($range),
                ],
                self::RELATIONSHIP_DATA => $range,
            ];
        }

        return $relationships;
    }
}
