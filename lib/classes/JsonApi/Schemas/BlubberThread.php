<?php

namespace JsonApi\Schemas;

use JsonApi\Errors\InternalServerError;
use Neomerx\JsonApi\Schema\Link;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;

class BlubberThread extends SchemaProvider
{
    const TYPE = 'blubber-threads';
    const REL_AUTHOR = 'author';
    const REL_COMMENTS = 'comments';
    const REL_CONTEXT = 'context';
    const REL_MENTIONS = 'mentions';

    protected ?array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_COMMENTS,
        self::REL_CONTEXT,
        self::REL_MENTIONS,
    ];

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $userId = $this->currentUser->id;

        $contextInfo = null;
        $contextTemplate = $resource->getContextTemplate();
        if ($contextTemplate) {
            $contextInfo = $contextTemplate->render();
        }

        $attributes = [
            'name' => $resource->getName(),

            'context-type' => $resource['context_type'],
            'context-info' => $contextInfo,

            'content' => $resource['content'],
            'content-html' => formatReady($resource['content']),

            'is-commentable' => (bool) $resource->isCommentable($userId),
            'is-readable' => (bool) $resource->isReadable($userId),
            'is-writable' => (bool) $resource->isWritable($userId),

            'is-visible-in-stream' => (bool) $resource->isVisibleInStream(),
            'is-followed' => (bool) $resource->isFollowedByUser($userId),
            'may-disable-notifications' => (bool) $resource->mayDisableNotifications($userId),

            'latest-activity' => date('c', $resource->getLatestActivity()),
            'visited-at' => date('c', $resource->getLastVisit($userId)),
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];

        return $attributes;
    }

    /**
     * In dieser Methode können Relationships zu anderen Objekten
     * spezifiziert werden.
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];
        $relationships = $this->getAuthorRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_AUTHOR)
        );

        $relationships = $this->getCommentsRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_COMMENTS)
        );
        $relationships = $this->getContextRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_CONTEXT)
        );
        $relationships = $this->getMentionsRelationship(
            $relationships,
            $resource,
            $this->shouldInclude($context, self::REL_MENTIONS)
        );

        return $relationships;
    }

    // #### PRIVATE HELPERS ####

    private function getAuthorRelationship($relationships, $resource, $includeData)
    {
        if (!$resource['external_contact'] && $resource['user_id']) {
            $userId = $resource['user_id'];
            $related = $includeData ? \User::find($userId) : \User::build(['id' => $userId], false);
            $relationships[self::REL_AUTHOR] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($related),
                ],
                self::RELATIONSHIP_DATA => $related,
            ];
        } else {
            $relationships[self::REL_AUTHOR] = [
                self::RELATIONSHIP_DATA => null,
            ];
        }

        return $relationships;
    }

    // TODO #10245
    private function getMentionsRelationship(array $relationships, \BlubberThread $resource, $includeData)
    {
        if ($includeData) {
            $relatedUsers = $resource->mentions->pluck('user');
        } else {
            $relatedUsers = array_map(function ($mention) {
                return \User::build(['user_id' => $mention->user_id], false);
            }, \BlubberMention::findBySQL('thread_id = ?', [$resource->id]));
        }

        $relationships[self::REL_MENTIONS] = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_LINKS => [],
            self::RELATIONSHIP_DATA => $relatedUsers,
        ];

        return $relationships;
    }

    private function getCommentsRelationship(array $relationships, \BlubberThread $resource, $includeData)
    {
        $relationship = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getFactory()->createLink(
                    true,
                    $this->getSelfSubUrl($resource) . '/' . self::REL_COMMENTS,
                    true,
                    ['unseen-comments' => $resource->countUnseenComments($this->currentUser->id)]
                ),
            ],
        ];

        if ($includeData) {
            $relationship[self::RELATIONSHIP_DATA] = $resource->comments;
        }

        $relationships[self::REL_COMMENTS] = $relationship;

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getContextRelationship(array $relationships, \BlubberThread $resource, $includeData)
    {
        $related = $data = null;

        if ('course' === $resource['context_type']) {
            $course = \Course::find($resource['context_id']);
            if (!$course) {
                throw new InternalServerError('Inconsistent data in BlubberThread.');
            }

            $related = $this->createLinkToResource($course);
            $data = $course;
        }

        if ('institute' === $resource['context_type']) {
            $institute = \Institute::find($resource['context_id']);
            if (!$institute) {
                throw new InternalServerError('Inconsistent data in BlubberThread.');
            }

            $related = $this->createLinkToResource($institute);
            $data = $institute;
        }

        if ($related && $data) {
            $relationships[self::REL_CONTEXT] = [
                self::RELATIONSHIP_LINKS_SELF => true,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $related,
                ],
                self::RELATIONSHIP_DATA => $data,
            ];
        }

        return $relationships;
    }

    /**
     * @inheritdoc
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceMeta($resource)
    {
        return [
            'avatar' => $resource->getAvatar(),
        ];
    }
}
