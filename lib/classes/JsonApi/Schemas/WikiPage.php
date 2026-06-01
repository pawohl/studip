<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class WikiPage extends SchemaProvider
{
    const REGEXP_KEYWORD = '/([\w\.\-\:\(\)_§\/@# ]|&[AOUaou]uml;|&szlig;)+/A';

    const TYPE = 'wiki-pages';
    const REL_AUTHOR = 'author';
    const REL_CHILDREN = 'children';
    const REL_DESCENDANTS = 'descendants';
    const REL_PARENT = 'parent';
    const REL_RANGE = 'range';

    protected array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_CHILDREN,
        self::REL_DESCENDANTS,
        self::REL_PARENT,
        self::REL_RANGE,
    ];

    public static function getRangeClasses(): array
    {
        return [
            'sem' => \Course::class,
            'inst' => \Institute::class,
        ];
    }

    public static function getRangeTypes(): array
    {
        return [
            'sem' => Course::TYPE,
            'inst' => Institute::TYPE,
        ];
    }

    /**
     * @param mixed $resource
     *
     * @return ?string
     */
    public static function getRangeClass($resource)
    {
        $classes = self::getRangeClasses();

        return $classes[
            get_object_type(
                $resource->range_id,
                array_keys($classes)
            )
        ];
    }

    /**
     * @param mixed $resource
     *
     * @return ?string
     */
    public static function getRangeType($resource)
    {
        $types = self::getRangeTypes();

        return $types[
            get_object_type(
                $resource->range_id,
                array_keys($types)
            )
        ];
    }

    public function getId($wiki): ?string
    {
        return $wiki->id;
    }

    public function getAttributes($wiki, ContextInterface $context): iterable
    {
        return [
            'name' => $wiki->name,
            'content' => $wiki->content,
            'chdate' => date('c', $wiki->chdate),
            'version' => count($wiki->versions) + 1,
        ];
    }

    public function getRelationships($wiki, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addAuthorRelationship($relationships, $wiki, $this->shouldInclude($context, self::REL_AUTHOR));
        $relationships = $this->addChildrenRelationship($relationships, $wiki, $this->shouldInclude($context, self::REL_CHILDREN));
        $relationships = $this->addDescendantsRelationship($relationships, $wiki, $this->shouldInclude($context, self::REL_DESCENDANTS));
        $relationships = $this->addParentRelationship($relationships, $wiki, $this->shouldInclude($context, self::REL_PARENT));
        $relationships = $this->addRangeRelationship($relationships, $wiki, $this->shouldInclude($context, self::REL_RANGE));

        return $relationships;
    }

    private function addParentRelationship(array $relationships, \WikiPage $wiki, bool $includeData): array
    {
        $related = $wiki->parent;
        $relationships[self::REL_PARENT] = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_DATA => $related
        ];
        if ($related) {
            $relationships[self::REL_PARENT][self::RELATIONSHIP_LINKS] = [
                Link::RELATED => $this->createLinkToResource($related),
            ];
        }

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function addChildrenRelationship(array $relationships, \WikiPage $wiki, bool $includeData): array
    {
        $relationships[self::REL_CHILDREN] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($wiki, self::REL_CHILDREN),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function addDescendantsRelationship(array $relationships, \WikiPage $wiki, bool $includeData): array
    {
        $relationships[self::REL_DESCENDANTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($wiki, self::REL_DESCENDANTS),
            ],
        ];

        return $relationships;
    }

    /**
     * @param array $relationships
     * @param \WikiPage $wiki
     * @param bool $includeData
     *
     * @return array
     */
    private function addAuthorRelationship($relationships, $wiki, bool $includeData)
    {
        if ($wiki->user_id && $wiki->user_id !== 'nobody') {
            $relationships[self::REL_AUTHOR] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($wiki->user),
                ],
                self::RELATIONSHIP_DATA => $wiki->user,
            ];
        }

        return $relationships;
    }

    /**
     * @param array $relationships
     * @param \WikiPage $wiki
     * @param bool $includeData
     *
     * @return array
     */
    private function addRangeRelationship($relationships, $wiki, bool $includeData)
    {
        $range = $this->prepareRange($wiki);
        $relationships[self::REL_RANGE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($range),
            ],
            self::RELATIONSHIP_DATA => $range,
        ];

        return $relationships;
    }

    private function prepareRange(\WikiPage $wiki): \Range
    {
        $class = self::getRangeClass($wiki);

        /** @var \Range $range */
        $range = $class::build(['id' => $wiki->range_id], false);

        return  $range;
    }
}
