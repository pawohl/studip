<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class StudipComment extends SchemaProvider
{
    const TYPE = 'comments';
    const REL_AUTHOR = 'author';
    const REL_NEWS = 'news';

    protected ?array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_NEWS,
    ];

    public function getId($comment): ?string
    {
        return $comment->comment_id;
    }

    public function getAttributes($comment, ContextInterface $context): iterable
    {
        return [
            'content' => $comment->content,
            'mkdate' => date('c', $comment->mkdate),
            'chdate' => date('c', $comment->chdate),
        ];
    }

    public function getRelationships($comment, ContextInterface $context): iterable
    {
        $relationships = [];

        if ($author = \User::find($comment->user_id)) {
            $relationships[self::REL_AUTHOR] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($author),
                ],
                self::RELATIONSHIP_DATA => $author,
            ];
        }

        if ($news = \StudipNews::find($comment->object_id)) {
            $relationships[self::REL_NEWS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($news),
                ],
                self::RELATIONSHIP_DATA => $news,
            ];
        }

        return $relationships;
    }
}
