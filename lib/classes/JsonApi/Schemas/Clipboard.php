<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

final class Clipboard extends SchemaProvider
{
    public const TYPE = 'clipboards';
    public const REL_USER = 'user';
    public const REL_ITEMS = 'clipboard-items';

    protected ?array $allowedIncludes = [
        self::REL_USER,
        self::REL_ITEMS,
    ];

    /**
     * @param \Clipboard $resource
     */
    public function getId($resource): ?string
    {
        return (string) $resource->id;
    }

    /**
     * @param \Clipboard $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'name'              => $resource->name,
            'handler'           => $resource->handler,
            'allows_item_class' => $resource->allowed_item_class,
            'mkdate'            => date('c', $resource->mkdate),
            'chdate'            => date('c', $resource->chdate),
        ];
    }

    /**
     * @param \Clipboard $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getUserRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_USER));
        $relationships = $this->getItemsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_ITEMS));

        return $relationships;
    }

    private function getUserRelationship(array $relationships, \Clipboard $clipboard, bool $includeData): array
    {
        $relationships[self::REL_USER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($clipboard->user),
            ],
            self::RELATIONSHIP_DATA => $includeData ? $clipboard->user : \User::build(['id' => $clipboard->user_id], false),
        ];

        return $relationships;
    }

    private function getItemsRelationship(array $relationships, \Clipboard $clipboard, bool $includeData): array
    {
        if ($includeData) {
            $relatedItems = $clipboard->items;
        } else {
            $relatedItems = $clipboard->items->map(fn($item) => \ClipboardItem::build(['id' => $item->id], false));
        }

        $relationships[self::REL_ITEMS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($clipboard, self::REL_ITEMS),
            ],
            self::RELATIONSHIP_DATA => $relatedItems,
        ];

        return $relationships;
    }
}
