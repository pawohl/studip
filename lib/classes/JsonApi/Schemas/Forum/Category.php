<?php
namespace JsonApi\Schemas\Forum;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Category extends SchemaProvider
{
    const TYPE = 'forum-categories';
    const REL_TOPICS = 'topics';

    protected ?array $allowedIncludes = [
        self::REL_TOPICS,
    ];

    /**
     * @param \Forum\Category $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * @param \Forum\Category $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'name' => $resource->name,
            'description' => $resource->description,
            'color' => $resource->color,
            'position' => (int) $resource->position,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate)
        ];
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
        $metadata = $resource->getMetadata();

        return [
            'topics-count' => (int) $metadata['topics_count'],
            'discussions-count' => (int) $metadata['discussions_count'],
            'postings-count' => (int) $metadata['postings_count'],
            'unread-postings-count' => (int) $metadata['unread_postings_count'],
            'user-read-index' => (int) $metadata['user_read_index'],
            'users-count' => (int) $metadata['users_count'],
            'recent-activity' => $metadata['recent_activity'] ? date('c', $metadata['recent_activity']) : '',
        ];
    }

    /**
     * @param \Forum\Category $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];
        $relationships = $this->addTopicsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_TOPICS));

        return $relationships;
    }

    private function addTopicsRelationship(array $relationships, \Forum\Category $category, $withTopics = false)
    {
        if ($withTopics) {
            $relationships[self::REL_TOPICS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($category, self::REL_TOPICS)
                ],
                self::RELATIONSHIP_DATA => $category->topics
            ];
        }

        return $relationships;
    }
}
