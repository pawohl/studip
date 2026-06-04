<?php

namespace JsonApi\Schemas;

use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class LibraryFile extends SchemaProvider
{
    const TYPE = 'file-refs';

    const REL_FEEDBACK = 'feedback-elements';
    const REL_FILE = 'file';
    const REL_OWNER = 'owner';
    const REL_PARENT = 'parent';
    const REL_RANGE = 'range';
    const REL_TERMS = 'terms-of-use';

    protected ?array $allowedIncludes = [
        self::REL_FEEDBACK,
        self::REL_FILE,
        self::REL_OWNER,
        self::REL_PARENT,
        self::REL_RANGE,
        self::REL_TERMS,
    ];

    const META_CONTENT = 'content';



    public function getId($resource): ?string
    {
        return $resource->getId();
    }

    /**
     * @inheritdoc
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    public function getResourceMeta($resource)
    {
        return [
            'download-url' => $resource->getDownloadURL(),
        ];
    }

    public function getAttributes($resource, ContextInterface $context): iterable
    {
        $attributes = [
            'name' => $resource->getFilename(),
            'description' => $resource->getDescription(),

            'mkdate' => date('c', $resource->getMakeDate()),
            'chdate' => date('c', $resource->getLastChangeDate()),

            'downloads' => (int) $resource->getDownloads(),

            'filesize' => (int) $resource->getSize()
        ];

        $userId = $this->currentUser->id;
        if ($folder = $resource->getFolderType()) {
            $filetype = $resource->getFileType();
            $attributes = array_merge(
                $attributes,
                [
                    'is-readable' => $folder->isReadable($userId),
                    'is-downloadable' => $filetype->isDownloadable($userId),
                    'is-editable' => $filetype->isEditable($userId),
                    'is-writable' => $filetype->isWritable($userId),
                ]
            );
        }

        return $attributes;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getFeedbackRelationship($relationships, $resource);
        $relationships = $this->addFileRelationship($relationships, $resource);
        $relationships = $this->addOwnerRelationship($relationships, $resource);
        $relationships = $this->addParentRelationship($relationships, $resource);
        $relationships = $this->addRangeRelationship($relationships, $resource);
        $relationships = $this->addTermsRelationship($relationships, $resource);

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getFeedbackRelationship(
        array $relationships,
        \FileRef $resource
    ) {
        if ($folder = $resource->getFolderType()) {
            if ($folder->range_id && $folder->range_type === 'course' && \Feedback::isActivated($folder->range_id)) {
                $relationships[self::REL_FEEDBACK] = [
                    self::RELATIONSHIP_LINKS => [
                        Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_FEEDBACK)
                    ],
                ];
            }
        }

        return $relationships;
    }

    private function addFileRelationship(array $relationships, \FileRef $resource)
    {
        if ($resource->file) {
            $relationships[self::REL_FILE] = [
                self::RELATIONSHIP_DATA => $resource->file,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->file),
                ],
            ];
        }

        return $relationships;
    }

    private function addOwnerRelationship(array $relationships, \FileRef $resource)
    {
        $relationships[self::REL_OWNER] = [
            self::RELATIONSHIP_META => [
                'name' => $resource->getAuthorName(),
            ],
            self::RELATIONSHIP_DATA => $resource->owner,
        ];

        if (isset($resource->owner)) {
            $relationships[self::REL_OWNER][self::RELATIONSHIP_LINKS] = [
                Link::RELATED => $this->createLinkToResource($resource->owner),
            ];
        }

        return $relationships;
    }

    private function addParentRelationship(array $relationships, \FileRef $resource)
    {
        if ($resource->folder_id) {
            $folder = $resource->getFolderType();
            $relationships[self::REL_PARENT] = [
                self::RELATIONSHIP_DATA => $folder,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($folder),
                ],
            ];
        }

        return $relationships;
    }

    private function addRangeRelationship(array $relationships, \FileRef $resource): array
    {
        if ($folder = $resource->getFolderType()) {
            if ($folder->range_id) {
                try {
                    $rangeType = $folder->range_type;
                    if ($range = $folder->$rangeType) {
                        $relationships[self::REL_RANGE] = [
                            self::RELATIONSHIP_DATA => $range,
                            self::RELATIONSHIP_LINKS => [
                                Link::RELATED => $this->createLinkToResource($range),
                            ],
                        ];
                    }
                } catch (\InvalidArgumentException $exception) {
                }
            }
        }

        return $relationships;
    }

    private function addTermsRelationship(array $relationships, \FileRef $resource)
    {
        $relationships[self::REL_TERMS] = [
            self::RELATIONSHIP_DATA => $resource->terms_of_use,
            self::RELATIONSHIP_LINKS_SELF => true,
        ];

        return $relationships;
    }
}
