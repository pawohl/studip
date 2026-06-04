<?php

namespace JsonApi\Schemas\Courseware;

use Courseware\UserDataField;
use Courseware\UserProgress;
use JsonApi\Schemas\SchemaProvider;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Block extends SchemaProvider
{
    const TYPE = 'courseware-blocks';

    const REL_COMMENTS = 'comments';
    const REL_CONTAINER = 'container';
    const REL_EDITBLOCKER = 'edit-blocker';
    const REL_EDITOR = 'editor';
    const REL_FEEDBACK = 'feedback';
    const REL_OWNER = 'owner';
    const REL_USERDATAFIELD = 'user-data-field';
    const REL_USERPROGRESS = 'user-progress';
    const REL_FILES = 'file-refs';

    protected ?array $allowedIncludes = [
        self::REL_COMMENTS,
        self::REL_CONTAINER,
        self::REL_EDITBLOCKER,
        self::REL_EDITOR,
        self::REL_FEEDBACK,
        self::REL_OWNER,
        self::REL_USERDATAFIELD,
        self::REL_USERPROGRESS,
        self::REL_FILES,
    ];

    /**
     * {@inheritdoc}
     */
    public function getId($resource): ?string
    {
        return $resource->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes($resource, ContextInterface $context): iterable
    {
        return [
            'position' => (int) $resource['position'],
            'block-type' => (string) $resource->getBlockType(),
            'title' => (string) $resource->type->getTitle(),
            'visible' => (bool) $resource['visible'],
            'commentable' => (bool) $resource['commentable'],
            'payload' => $resource->type->getPayload(),
            'mkdate' => date('c', $resource['mkdate']),
            'chdate' => date('c', $resource['chdate']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRelationships($resource, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships[self::REL_COMMENTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_COMMENTS),
            ],
            self::RELATIONSHIP_DATA => $resource->comments,
        ];

        $relationships[self::REL_CONTAINER] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->container),
            ],
            self::RELATIONSHIP_DATA => $resource->container,
        ];

        $relationships[self::REL_OWNER] = $resource->owner
        ? [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($resource->owner),
            ],
            self::RELATIONSHIP_DATA => $resource->owner,
        ]
        : [self::RELATIONSHIP_DATA => null];

        $relationships[self::REL_EDITOR] = $resource->editor
            ? [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->editor),
                ],
                self::RELATIONSHIP_DATA => $resource->editor,
            ]
            : [self::RELATIONSHIP_DATA => null];


        $relationships[self::REL_EDITBLOCKER] = $resource->edit_blocker
            ? [
                self::RELATIONSHIP_LINKS_SELF => true,
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->createLinkToResource($resource->edit_blocker),
                ],
                self::RELATIONSHIP_DATA => $resource->edit_blocker,
            ]
            : [self::RELATIONSHIP_LINKS_SELF => true, self::RELATIONSHIP_DATA => null];

        $relationships[self::REL_FEEDBACK] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_FEEDBACK),
            ],
        ];

        $user = $this->currentUser;
        if ($user) {
            $userDataField = UserDataField::getUserDataField($user, $resource);
            $relationships[self::REL_USERDATAFIELD] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_USERDATAFIELD),
                ],
                self::RELATIONSHIP_DATA => $userDataField,
            ];

            $userProgress = UserProgress::getUserProgress($user, $resource);
            $relationships[self::REL_USERPROGRESS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_USERPROGRESS),
                ],
                self::RELATIONSHIP_DATA => $userProgress,
            ];
        }

        if ($resource->files) {
            $filesLink = $this->getRelationshipRelatedLink($resource, self::REL_FILES);

            $relationships[self::REL_FILES] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $filesLink,
                ],
            ];
        }

        return $relationships;
    }
}
