<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

final class ShortUrl extends SchemaProvider
{
    public const TYPE     = 'short-urls';
    public const REL_USER = 'user';

    protected ?array $allowedIncludes = [
        self::REL_USER,
    ];

    /**
     * @param \Clipboard $resource
     */
    public function getId($resource): ?string
    {
        return (string)$resource->id;
    }

    /**
     * @param \Forum\Category $resource
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * @param \Forum\Category $resource
     */
    public function getResourceMeta($resource)
    {
        return [
            'alias-link' => \URLHelper::getLink('dispatch.php/u/r/' . $resource->alias, [], true)
        ];
    }

    /**
     * @param \ShortUrl $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'alias'  => $resource->alias,
            'path'   => $resource->path,
            'title'  => $resource->title,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate),
        ];
    }

    /**
     * @param \ShortUrl $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getUserRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_USER));

        return $relationships;
    }

    private function getUserRelationship(array $relationships, \ShortUrl $short_url, bool $includeData): array
    {
        $relationships[self::REL_USER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($short_url->user),
            ],
            self::RELATIONSHIP_DATA  => $includeData ? $short_url->user : \User::build(['id' => $short_url->user_id], false),
        ];

        return $relationships;
    }
}
