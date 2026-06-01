<?php
namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

final class ClipboardItem extends SchemaProvider
{
    public const TYPE = 'clipboard-items';
    public const REL_CLIPBOARD = 'clipboard';

    protected array $allowedIncludes = [
        self::REL_CLIPBOARD,
    ];

    /**
     * @param \ClipboardItem $resource
     */
    public function getId($resource): ?string
    {
        return (string) $resource->id;
    }

    /**
     * @param \ClipboardItem $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'range_id'   => $resource->range_id,
            'range_type' => $resource->range_type,
            'name'       => $resource->name,
            'mkdate'     => date('c', $resource->mkdate),
            'chdate'     => date('c', $resource->chdate),
        ];
    }

    /**
     * @param \ClipboardItem $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getClipboardRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_CLIPBOARD));

        return $relationships;
    }

    private function getClipboardRelationship(array $relationships, \ClipboardItem $clipboardItem, bool $includeData): array
    {
        $relationships[self::REL_CLIPBOARD] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($clipboardItem->clipboard),
            ],
            self::RELATIONSHIP_DATA => $includeData ? $clipboardItem->clipboard : \User::build(['id' => $clipboardItem->clipboard_id], false),
        ];

        return $relationships;
    }
}
