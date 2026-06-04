<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Degree extends SchemaProvider
{
    const TYPE = 'degrees';

    const REL_AUTHOR = 'author';
    const REL_EDITOR = 'editor';

    protected ?array $allowedIncludes = [
        self::REL_AUTHOR,
        self::REL_EDITOR,
    ];

    /**
     * @param \Degree $resource
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * @param \Degree $resource
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'name' => $resource->name,
            'shortname' => $resource->name_kurz,
            'description' => $resource->beschreibung,
            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate)
        ];
    }

    /**
     * @param \Degree $resource
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getAuthorRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_AUTHOR));
        $relationships = $this->getEditorRelationship($relationships, $resource, $this->shouldInclude($context, self::REL_EDITOR));

        return $relationships;
    }

    private function getAuthorRelationship(array $relationships, \Degree $degree, $includeData)
    {
        $author = \User::find($degree->author_id);

        if ($author) {
            $relationships[self::REL_AUTHOR] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($author),
                ]
            ];

            if ($includeData) {
                $relationships[self::REL_AUTHOR][self::RELATIONSHIP_DATA] = $author;
            }
        }

        return $relationships;
    }

    private function getEditorRelationship(array $relationships, \Degree $degree, $includeData)
    {
        $editor = \User::find($degree->editor_id);

        if ($editor) {
            $relationships[self::REL_EDITOR] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($editor),
                ]
            ];

            if ($includeData) {
                $relationships[self::REL_EDITOR][self::RELATIONSHIP_DATA] = $editor;
            }
        }

        return $relationships;
    }
}
