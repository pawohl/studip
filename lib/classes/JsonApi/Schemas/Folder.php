<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Folder extends SchemaProvider
{
    const TYPE = 'folders';
    const REL_OWNER = 'owner';
    const REL_PARENT = 'parent';
    const REL_RANGE = 'range';
    const REL_FEEDBACK = 'feedback-elements';
    const REL_FILE_REFS = 'file-refs';
    const REL_FOLDERS = 'folders';

    protected array $allowedIncludes = [
        self::REL_OWNER,
        self::REL_PARENT,
        self::REL_RANGE,
        self::REL_FEEDBACK,
        self::REL_FILE_REFS,
        self::REL_FOLDERS,
    ];

    public function getId($resource): ?string
    {
        return $resource->getId();
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $user = $this->currentUser;

        $attributes = [
            'folder-type' => $resource->folder_type,
            'name' => $resource->name,
            'description' => $resource->description ?: null,

            'mkdate' => date('c', $resource->mkdate),
            'chdate' => date('c', $resource->chdate),

            'is-visible'  => (bool) $resource->isVisible($user->id),
            'is-readable' => (bool) $resource->isReadable($user->id),
            'is-writable' => (bool) $resource->isWritable($user->id),
            'is-editable' => (bool) $resource->isEditable($user->id),
            'is-empty'    => (bool) $resource->is_empty,
            'is-subfolder-allowed' => (bool) $resource->isSubfolderAllowed($user->id),
        ];

        // TODO: sollte das wirklich zugänglich sein?
        if ($resource->isReadable($user->id)) {
            $attributes['data-content'] = json_decode($resource->data_content);
        }

        return $attributes;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getFeedbackRelationship($relationships, $resource);
        $relationships = $this->getFilesRelationship($relationships, $resource);
        $relationships = $this->getFoldersRelationship($relationships, $resource);
        $relationships = $this->getOwnerRelationship($relationships, $resource);
        $relationships = $this->getParentRelationship($relationships, $resource);
        $relationships = $this->getRangeRelationship($relationships, $resource);

        return $relationships;
    }

    private function getOwnerRelationship(array $relationships, $resource)
    {
        if ($resource->user_id && $resource->owner) {
            $relationships[self::REL_OWNER] = [
                self::RELATIONSHIP_DATA => $resource->owner,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->owner),
                ],
                self::RELATIONSHIP_META => [
                    'name' => $resource->owner->getFullName('no_title_rev'),
                ]
            ];
        }

        return $relationships;
    }

    private function getParentRelationship(array $relationships, $resource)
    {
        if ($resource->parent_id) {
            $parent = $resource->parentfolder->getTypedFolder();
            $relationships[self::REL_PARENT] = [
                self::RELATIONSHIP_DATA => $parent,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($parent),
                ],
            ];
        }

        return $relationships;
    }

    private function getRangeRelationship(array $relationships, $resource)
    {
        if ($resource->range_id) {
            try {
                $relationships[self::REL_RANGE] = $this->getMeaningfulRangeRelationship($resource);
            } catch (\InvalidArgumentException $exception) {
                $relationships[self::REL_RANGE] = $this->getDefaultRangeRelationship($resource);
            }
        }

        return $relationships;
    }

    private function getMeaningfulRangeRelationship($resource)
    {
        $rangeType = $resource->range_type;
        if ($range = $resource->$rangeType) {

            return [
                self::RELATIONSHIP_DATA => $range,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($range),
                ],
            ];
        }

        return $this->getDefaultRangeRelationship($resource);
    }

    private function getDefaultRangeRelationship($resource)
    {
        return [
            self::RELATIONSHIP_META => [
                'range_id' => $resource->range_id,
                'range_type' => $resource->range_type,
            ],
        ];
    }

    private function getFeedbackRelationship(array $relationships, $resource): array
    {
        if ($resource->range_type === 'course') {
            if (\Feedback::isActivated($resource->range_id)) {
                $relationships[self::REL_FEEDBACK] = [
                    self::RELATIONSHIP_LINKS => [
                        Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_FEEDBACK)
                    ],
                ];
            }
        }

        return $relationships;
    }

    private function getFoldersRelationship(array $relationships, $resource)
    {
        $relationships[self::REL_FOLDERS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_FOLDERS),
            ],
            self::RELATIONSHIP_META => [
                'count' => count($resource->subfolders)
            ],
        ];

        return $relationships;
    }

    /**
     * @param \FolderType $resource
     */
    private function getFilesRelationship(array $relationships, $resource)
    {
        $fileRefs = array_map(
            function (\FileType $file): \FileRef {
                return $file->getFileRef();
            },
            $resource->getFiles()
        );

        $relationships[self::REL_FILE_REFS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_FILE_REFS),
            ],
            self::RELATIONSHIP_META => [
                'count' => count($fileRefs),
            ],
        ];

        return $relationships;
    }
}
