<?php

namespace JsonApi\Schemas\Forum;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Discussion extends SchemaProvider
{
    const TYPE = 'forum-discussions';
    const REL_POSTINGS = 'postings';
    const REL_TOPIC = 'topic';
    const REL_CATEGORY = 'category';
    const REL_USER = 'user';
    const REL_DISCUSSION_TYPE = 'discussion-type';
    const REL_MEMBERS = 'members';
    const REL_TAGS = 'tags';

    protected ?array $allowedIncludes = [
        self::REL_POSTINGS,
        self::REL_TOPIC,
        self::REL_CATEGORY,
        self::REL_USER,
        self::REL_DISCUSSION_TYPE,
        self::REL_MEMBERS,
        self::REL_TAGS,
    ];

    /**
     * @param \Forum\Discussion $resource
     */
    public function getId($resource): ?string
    {
        return $resource->discussion_id;
    }

    /**
     * @param \Forum\Discussion $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'title' => $resource->title,
            'closed-at' => $resource->closed_at ? date('c', $resource->closed_at) : null,
            'sticky' => (bool) $resource->sticky,
            'view-count' => (int) $resource->view_count,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate)
        ];
    }

    /**
     * @param \Forum\Discussion $resource
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * @param \Forum\Discussion $resource
     */
    public function getResourceMeta($resource)
    {
        $metadata = $resource->getMetadata();

        return [
            'postings-count' => (int) $metadata['postings_count'],
            'recent-postings-count' => (int) $metadata['recent_postings_count'],
            'unread-postings-count' => (int) $metadata['unread_postings_count'],
            'user-read-index' => (int) $metadata['user_read_index'],
            'recent-activity' => $metadata['recent_activity'] ? date('c', $metadata['recent_activity']) : ''
        ];
    }

    /**
     * @param \Forum\Discussion $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addPostingsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_POSTINGS));
        $relationships = $this->addTopicRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_TOPIC));
        $relationships = $this->addCategoryRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_CATEGORY));
        $relationships = $this->addUserRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_USER));
        $relationships = $this->addDiscussionTypeRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_DISCUSSION_TYPE));
        $relationships = $this->addMembersRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_MEMBERS));
        $relationships = $this->addTagsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_TAGS));

        return $relationships;
    }

    private function addPostingsRelationship(array $relationships, \Forum\Discussion $discussion, bool $withPostings = false)
    {
        if ($withPostings) {
            $relationships[self::REL_POSTINGS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($discussion, self::REL_POSTINGS)
                ],
                self::RELATIONSHIP_DATA => $discussion->postings
            ];
        }

        return $relationships;
    }

    private function addTopicRelationship(array $relationships, \Forum\Discussion $discussion, bool $withTopic = false)
    {
        if ($withTopic) {
            $relationships[self::REL_TOPIC] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($discussion->topic)
                ],
                self::RELATIONSHIP_DATA => $discussion->topic
            ];
        }

        return $relationships;
    }

    private function addCategoryRelationship(array $relationships, \Forum\Discussion $discussion, bool $withCategory = false)
    {
        $category = $discussion->category;
        if ($withCategory && $category) {
            $relationships[self::REL_CATEGORY] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($category)
                ],
                self::RELATIONSHIP_DATA => $category
            ];
        }

        return $relationships;
    }

    private function addUserRelationship(array $relationships, \Forum\Discussion $discussion, bool $withUser = false)
    {
        $user = $discussion->user;
        if ($withUser && $user) {
            $relationships[self::REL_USER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($user)
                ],
                self::RELATIONSHIP_DATA => $user
            ];
        }

        return $relationships;
    }

    private function addDiscussionTypeRelationship(array $relationships, \Forum\Discussion $discussion, bool $withDiscussionType = false)
    {
        $discussionType = $discussion->discussion_type;

        if ($withDiscussionType && $discussionType) {
            $relationships[self::REL_DISCUSSION_TYPE] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($discussionType)
                ],
                self::RELATIONSHIP_DATA => $discussionType
            ];
        }

        return $relationships;
    }

    private function addMembersRelationship(array $relationships, \Forum\Discussion $discussion, bool $withMembers = false)
    {
        if ($withMembers) {
            $relationships[self::REL_MEMBERS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($discussion, self::REL_MEMBERS)
                ],
                self::RELATIONSHIP_DATA => $discussion->members
            ];
        }

        return $relationships;
    }

    private function addTagsRelationship(array $relationships, \Forum\Discussion $discussion, bool $withTags = false)
    {
        if ($withTags) {
            $relationships[self::REL_TAGS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($discussion, self::REL_TAGS)
                ],
                self::RELATIONSHIP_DATA => $discussion->tags
            ];
        }

        return $relationships;
    }
}
