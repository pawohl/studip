<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class StudipNews extends SchemaProvider
{
    const TYPE = 'news';
    const REL_AUTHOR = 'author';
    const REL_COMMENTS = 'comments';
    const REL_RANGES = 'ranges';

    protected ?array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_COMMENTS,
        self::REL_RANGES,
    ];

    public static function getRangeClasses()
    {
        return [
            'sem' => \Course::class,
            'user' => \User::class,
            'inst' => \Institute::class,
            'fak' => \Institute::class,
        ];
    }

    public static function getRangeTypes()
    {
        return [
            'global' => Studip::TYPE,
            'sem' => Course::TYPE,
            'user' => User::TYPE,
            'inst' => Institute::TYPE,
            'fak' => Institute::TYPE,
        ];
    }

    public function getId($news): ?string
    {
        return $news->getId();
    }

    public function getAttributes($news, ContextInterface $context): iterable
    {
        return [
            'title' => (string) $news->topic,
            'content' => (string) $news->body,
            'mkdate' => date('c', $news->mkdate),
            'chdate' => date('c', $news->chdate),
            'publication-start' => date('c', $news->date),
            'publication-end' => date('c', $news->date + $news->expire),
            'comments-allowed' => (bool) $news->allow_comments,
        ];
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($news, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addAuthorRelationship($relationships, $news, $this->shouldInclude($context, self::REL_AUTHOR));
        $relationships = $this->addCommentsRelationship($relationships, $news, $this->shouldInclude($context, self::REL_COMMENTS));
        $relationships = $this->addRangesRelationship($relationships, $news, $this->shouldInclude($context, self::REL_RANGES));

        return $relationships;
    }

    private function addAuthorRelationship($relationships, $news, bool $includeData)
    {
        $data = $includeData
              ? $news->owner
              : \User::build(['id' => $news->user_id], false);
        $relationships[self::REL_AUTHOR] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($news->owner),
            ],
            self::RELATIONSHIP_DATA => $data,
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function addCommentsRelationship($relationships, $news, bool $includeData)
    {
        $relationships[self::REL_COMMENTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($news, self::REL_COMMENTS),
            ],
        ];

        return $relationships;
    }

    private function addRangesRelationship($relationships, $news, bool $includeData)
    {
        $relationships[self::REL_RANGES] = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_DATA => $this->prepareRanges($news, $includeData),
        ];

        return $relationships;
    }

    private function prepareRanges($news, bool $include)
    {
        return $news->news_ranges->map(function ($range) use ($include) {
            switch ($range->type) {
                case 'global':
                    return new \JsonApi\Models\Studip();

                case 'sem':
                    return $include
                        ? $range->course
                        : \Course::build(['id' => $range->range_id], false);

                case 'user':
                    return $include
                        ? $range->user
                        : \User::build(['id' => $range->range_id], false);

                case 'inst':
                case 'fak':
                    return $include
                        ? $range->institute
                        : \Institute::build(['id' => $range->range_id], false);
            }
        });
    }
}
