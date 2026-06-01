<?php

namespace JsonApi\Schemas\Forum;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class PostingReaction extends SchemaProvider
{
    const TYPE = 'forum-posting-reactions';
    const REL_POSTING = 'posting';
    const REL_USER = 'user';

    protected array $allowedIncludes = [
        self::REL_POSTING,
        self::REL_USER,
    ];

    /**
     * @param \Forum\PostingReaction $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * @param \Forum\PostingReaction $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'emoji' => $resource->emoji,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate)
        ];
    }


    /**
     * @param \Forum\PostingReaction $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addPostingRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_POSTING));
        $relationships = $this->addUserRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_USER));

        return $relationships;
    }

    private function addPostingRelationship(array $relationships, \Forum\PostingReaction $postingReaction, bool $withPosting = false)
    {
        if ($withPosting) {
            $relationships[self::REL_POSTING] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($postingReaction->posting)
                ],
                self::RELATIONSHIP_DATA => $postingReaction->posting
            ];
        }

        return $relationships;
    }

    private function addUserRelationship(array $relationships, \Forum\PostingReaction $postingReaction, bool $withUser = false)
    {
        $user = $postingReaction->user;
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
}
