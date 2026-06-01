<?php

namespace JsonApi\Schemas\Forum;

use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Posting extends SchemaProvider
{
    const TYPE = 'forum-postings';
    const REL_AUTHOR = 'author';
    const REL_DISCUSSION = 'discussion';
    const REL_POSTING = 'posting';
    const REL_RANGE = 'range';
    const REL_REACTIONS = 'reactions';
    const REL_REACTIONS_USER = 'reactions.user';
    const REL_OPENGRAPH_URLS = 'opengraph-urls';

    protected array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_DISCUSSION,
        self::REL_POSTING,
        self::REL_RANGE,
        self::REL_REACTIONS,
        self::REL_REACTIONS_USER,
        self::REL_OPENGRAPH_URLS,
    ];

    /**
     * @param \Forum\Posting $resource
     */
    public function getId($resource): ?string
    {
        return $resource->posting_id;
    }

    /**
     * @param \Forum\Posting $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'content' => \Studip\Markup::markupToHtml($resource->content),
            'content-html' => formatReady($resource->content),
            'anonymous' => (bool) $resource->anonymous,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate)
        ];
    }

    /**
     * @param \Forum\Posting $resource
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * @param \Forum\Posting $resource
     */
    public function getResourceMeta($resource)
    {
        return [
            self::REL_OPENGRAPH_URLS => array_map(fn($og) => [
                'url' => $og['url'],
                'is-opengraph' => (bool) $og['is_opengraph'],
                'title' => $og['title'],
                'description' => $og['description'],
                'image' => $og['image'],
            ], $resource->getOpenGraphURLs())
        ];
    }

    /**
     * @param \Forum\Posting $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];
        $relationships = $this->addAuthorRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_AUTHOR));
        $relationships = $this->addDiscussionRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_DISCUSSION));
        $relationships = $this->addPostingRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_POSTING));
        $relationships = $this->addReactionsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_REACTIONS));

        return $relationships;
    }

    private function addAuthorRelationship(array $relationships, \Forum\Posting $posting, bool $withAuthor = false)
    {
        $author = $posting->author;

        if ($withAuthor && $author) {
            $relationships[self::REL_AUTHOR] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($author)
                ],
                self::RELATIONSHIP_DATA => $author
            ];
        }

        return $relationships;
    }

    private function addDiscussionRelationship(array $relationships, \Forum\Posting $posting, bool $withDiscussion = false)
    {
        if ($withDiscussion) {
            $relationships[self::REL_DISCUSSION] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($posting->discussion)
                ],
                self::RELATIONSHIP_DATA => $posting->discussion
            ];
        }

        return $relationships;
    }

    private function addPostingRelationship(array $relationships, \Forum\Posting $posting, bool $withPosting = false)
    {
        $posting = $posting->posting;

        if ($withPosting && $posting) {
            $relationships[self::REL_POSTING] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($posting)
                ],
                self::RELATIONSHIP_DATA => $posting
            ];
        }

        return $relationships;
    }

    private function addReactionsRelationship(array $relationships, \Forum\Posting $posting, bool $withReactions = false)
    {
        if ($withReactions) {
            $relationships[self::REL_REACTIONS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($posting, self::REL_REACTIONS)
                ],
                self::RELATIONSHIP_DATA => $posting->reactions
            ];
        }

        return $relationships;
    }

}
