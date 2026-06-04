<?php

namespace JsonApi\Schemas;

use JsonApi\Routes\Files\Authority as FilesAuthority;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class File extends SchemaProvider
{
    const TYPE = 'files';

    const REL_FILE_REFS = 'file-refs';
    const REL_OWNER = 'owner';

    protected ?array $allowedIncludes = [
        self::REL_FILE_REFS,
        self::REL_OWNER,
    ];

    public function getId($resource): ?string
    {
        return $resource->getId();
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $attributes = [
            'name' => $resource['name'],
            'mime-type' => $resource['mime_type'],
            'filesize' => (int) $resource['size'],

            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];

        if (!empty($resource['metadata']['url'])) {
            if (FilesAuthority::canUpdateFile($this->currentUser, $resource)) {
                $attributes['url'] = $resource['metadata']['url'];
            }
        }

        return $attributes;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->addOwnerRelationship($relationships, $resource);
        $relationships = $this->addFileRefsRelationship($relationships, $resource);

        return $relationships;
    }

    private function addFileRefsRelationship(array $relationships, \File $resource)
    {
        $refs = $resource->refs;

        $relationships[self::REL_FILE_REFS] = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_DATA => $refs,
        ];

        return $relationships;
    }

    private function addOwnerRelationship(array $relationships, \File $resource)
    {
        if ($resource->user_id) {
            $relationships[self::REL_OWNER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->owner),
                ],
            ];
        }

        return $relationships;
    }
}
