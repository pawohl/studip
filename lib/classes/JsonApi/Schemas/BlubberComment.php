<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Schema\Link;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;

class BlubberComment extends SchemaProvider
{
    const TYPE = 'blubber-comments';
    const REL_AUTHOR = 'author';
    const REL_MENTIONS = 'mentions';
    const REL_THREAD = 'thread';

    protected array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_MENTIONS,
        self::REL_THREAD,
    ];

    public function getId($resource): ?string
    {
        return $resource->id;
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $userId = $this->currentUser->id;

        $attributes = [
            # `network` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
            'content' => $resource['content'],
            'content-html' => blubberReady($resource['content']),

            'is-writable' => $resource->isWritable($userId),

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
        $relationships = $this->getAuthorRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_AUTHOR));

        $relationships = $this->getMentionsRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_MENTIONS));
        $relationships = $this->getThreadRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_THREAD));

        return $relationships;
    }

    // #### PRIVATE HELPERS ####

    private function getAuthorRelationship($relationships, $resource, $includeData)
    {
        if (!$resource['external_contact']) {
            $userId = $resource['user_id'];

            $user = \User::find($userId);
            if ($user) {
                $data = $includeData ? $user : \User::build(['id' => $userId], false);
                $relationships[self::REL_AUTHOR] = [
                    self::RELATIONSHIP_DATA => $data,
                    self::RELATIONSHIP_LINKS => [
                        Link::RELATED => $this->createLinkToResource($data),
                    ]
                ];
            } else {
                $relationships[self::REL_AUTHOR] = [ self::RELATIONSHIP_DATA => null ];
            }
        }

        return $relationships;
    }

    private function getMentionsRelationship(array $relationships, \BlubberComment $resource, $includeData)
    {
        if ($includeData) {
            $relatedUsers = $resource->mentions->pluck('user');
        } else {
            $relatedUsers = array_map(function ($mention) {
                return \User::build(['user_id' => $mention->user_id], false);
            }, \BlubberMention::findBySQL('thread_id = ?', [$resource->id]));
        }

        $relationships[self::REL_MENTIONS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_MENTIONS),
            ],
            self::RELATIONSHIP_DATA => $relatedUsers,
        ];

        return $relationships;
    }

    private function getThreadRelationship(array $relationships, \BlubberComment $resource, $includeData)
    {
        if ($includeData) {
            $related = $resource->thread;
        } else {
            $related = \BlubberThread::build(['id' => $resource->thread_id], false);
        }

        $relationships[self::REL_THREAD] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($related),
            ],
            self::RELATIONSHIP_DATA => $related,
        ];

        return $relationships;
    }
}
