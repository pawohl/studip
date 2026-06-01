<?php
namespace JsonApi\Schemas\Forum;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class DiscussionType extends SchemaProvider
{
    const TYPE = 'forum-discussion-types';

    const REL_DISCUSSIONS = 'discussions';

    protected array $allowedIncludes = [
        self::REL_DISCUSSIONS,
    ];

    /**
     * @inheritDoc
     * @param \Forum\DiscussionType $resource
     */
    public function getId($resource): ?string
    {
        return $resource->type_id;
    }

    /**
     * @inheritDoc
     * @param \Forum\DiscussionType $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $attributes = $resource->transformData();
        unset($attributes['id']);
        return $attributes;
    }

    /**
     * @inheritDoc
     * @param \Forum\DiscussionType $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addDiscussionsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_DISCUSSIONS));

        return $relationships;
    }

    private function addDiscussionsRelationship(array $relationships, \Forum\DiscussionType $discussionType, $withDiscussions = false): array
    {
        if ($withDiscussions) {
            $relationships[self::REL_DISCUSSIONS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($discussionType, self::REL_DISCUSSIONS)
                ],
                self::RELATIONSHIP_DATA => $discussionType->discussions
            ];
        }

        return $relationships;
    }
}
