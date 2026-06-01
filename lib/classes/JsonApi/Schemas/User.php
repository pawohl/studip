<?php

namespace JsonApi\Schemas;

use JsonApi\Routes\Users\Authority as UsersAuthority;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class User extends SchemaProvider
{
    const TYPE = 'users';

    const REL_ACTIVITYSTREAM = 'activitystream';
    const REL_BLUBBER = 'blubber-threads';
    const REL_BLUBBER_DEFAULT_THREAD = 'blubber-default-thread';
    const REL_CONFIG_VALUES = 'config-values';
    const REL_CONTACTS = 'contacts';
    const REL_COURSES = 'courses';
    const REL_COURSE_MEMBERSHIPS = 'course-memberships';
    const REL_COURSEWARE_BOOKMARKS = 'courseware-bookmarks';
    const REL_EVENTS = 'events';
    const REL_FILES = 'file-refs';
    const REL_FOLDERS = 'folders';
    const REL_INBOX = 'inbox';
    const REL_INSTITUTE_MEMBERSHIPS = 'institute-memberships';
    const REL_NEWS = 'news';
    const REL_OUTBOX = 'outbox';
    const REL_SCHEDULE = 'schedule';

    protected array $allowedIncludes = [
        self::REL_ACTIVITYSTREAM,
        self::REL_BLUBBER,
        self::REL_BLUBBER_DEFAULT_THREAD,
        self::REL_CONFIG_VALUES,
        self::REL_CONTACTS,
        self::REL_COURSES,
        self::REL_COURSE_MEMBERSHIPS,
        self::REL_COURSEWARE_BOOKMARKS,
        self::REL_EVENTS,
        self::REL_FILES,
        self::REL_FOLDERS,
        self::REL_INBOX,
        self::REL_INSTITUTE_MEMBERSHIPS,
        self::REL_NEWS,
        self::REL_OUTBOX,
        self::REL_PROFILE_CATEGORIES,
        self::REL_SCHEDULE,
    ];

    /**
     * Diese Method entscheidet über die JSON-API-spezifische ID von
     * \User-Objekten.
     * {@inheritdoc}
     */
    public function getId($user): ?string
    {
        return $user->id;
    }

    /**
     * Hier können (ausgewählte) Instanzvariablen eines \User-Objekts
     * für die Ausgabe vorbereitet werden.
     * {@inheritdoc}
     */
    public function getAttributes($user, ContextInterface $context): iterable
    {
        $attrs = [
            'username' => $user->username,
            'formatted-name' => trim($user->getFullName()),
            'family-name' => $user->nachname,
            'given-name' => $user->vorname,
            'name-prefix' => $user->title_front,
            'name-suffix' => $user->title_rear,
            'permission' => $user->perms,
            'email' => get_visible_email($user->id),
        ];

        return $attrs + iterator_to_array($this->getProfileAttributes($user));
    }

    private function getProfileAttributes(\User $user): iterable
    {
        $visibilities = $this->getVisibilities($user);
        $observer = $this->currentUser;

        if (!$visibilities || !$observer) {
            return [];
        }

        $fields = [
            ['phone', 'privatnr', 'private_phone'],
            ['homepage', 'Home', 'homepage'],
            ['address', 'privadr', 'privadr'],
        ];

        foreach ($fields as list($attr, $field, $vis)) {
            $value =
                $user[$field] && is_element_visible_for_user($observer->id, $user->id, $visibilities[$vis] ?? null)
                    ? strip_tags((string) $user[$field])
                    : null;
            yield $attr => $value;
        }
    }

    private function getVisibilities(\User $user): array
    {
        $visibilities = get_local_visibility_by_id($user->id, 'homepage');
        if (is_array(json_decode($visibilities, true))) {
            return json_decode($visibilities, true);
        }

        return [];
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
        $avatar = \Avatar::getAvatar($resource->id);

        return [
            'avatar' => [
                'small' => $avatar->getURL(\Avatar::SMALL),
                'medium' => $avatar->getURL(\Avatar::MEDIUM),
                'normal' => $avatar->getURL(\Avatar::NORMAL),
                'original' => $avatar->getURL(\Avatar::NORMAL),
            ],
        ];
    }

    /**
     * In dieser Methode können Relationships zu anderen Objekten
     * spezifiziert werden. In diesem Beispiel kleben die Kontakte
     * eines Nutzers bei Bedarf am \User.
     * {@inheritdoc}
     */
    public function getRelationships($user, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships = $this->getActivityStreamRelationship(
            $relationships,
            $user,
            $this->shouldInclude($context, self::REL_ACTIVITYSTREAM)
        );
        $relationships = $this->getBlubberRelationship(
            $relationships,
            $user,
            $this->shouldInclude($context, self::REL_BLUBBER)
        );
        $relationships = $this->getConfigValuesRelationship(
            $relationships,
            $user,
            $this->shouldInclude($context, self::REL_CONFIG_VALUES)
        );
        $relationships = $this->getContactsRelationship(
            $relationships,
            $user,
            $this->shouldInclude($context, self::REL_CONTACTS)
        );
        $relationships = $this->getCoursesRelationship(
            $relationships,
            $user,
            $this->shouldInclude($context, self::REL_COURSES)
        );
        $relationships = $this->getCourseMembershipsRelationship(
            $relationships,
            $user,
            $this->shouldInclude($context, self::REL_COURSE_MEMBERSHIPS)
        );
        $relationships = $this->getEventsRelationship($relationships, $user, $this->shouldInclude($context, self::REL_EVENTS));
        $relationships = $this->getFileRefsRelationship($relationships, $user, $this->shouldInclude($context, self::REL_FILES));
        $relationships = $this->getFoldersRelationship($relationships, $user, $this->shouldInclude($context, self::REL_FOLDERS));
        $relationships = $this->getInboxRelationship($relationships, $user, $this->shouldInclude($context, self::REL_INBOX));
        $relationships = $this->getInstituteMembershipsRelationship(
            $relationships,
            $user,
            $this->shouldInclude($context, self::REL_INSTITUTE_MEMBERSHIPS)
        );
        $relationships = $this->getNewsRelationship($relationships, $user, $this->shouldInclude($context, self::REL_NEWS));
        $relationships = $this->getOutboxRelationship($relationships, $user, $this->shouldInclude($context, self::REL_OUTBOX));
        $relationships = $this->getScheduleRelationship($relationships, $user, $this->shouldInclude($context, self::REL_SCHEDULE));
        $relationships = $this->getCoursewareBookmarksRelationship($relationships, $user, $this->shouldInclude($context, self::REL_COURSEWARE_BOOKMARKS));

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getActivityStreamRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_ACTIVITYSTREAM] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_ACTIVITYSTREAM),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getBlubberRelationship(array $relationships, \User $user, $includeData)
    {
        if (\Config::get()->BLUBBER_GLOBAL_MESSENGER_ACTIVATE) {
            $relationships[self::REL_BLUBBER] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_BLUBBER),
                ],
            ];

            if (UsersAuthority::canEditUser($this->currentUser, $user)) {
                $threadId = $user->getConfiguration()->getValue('BLUBBER_DEFAULT_THREAD');
                $thread = $includeData
                    ? \BlubberThread::find($threadId)
                    : \BlubberThread::build(['id' => $threadId], false);
                $relationships[self::REL_BLUBBER_DEFAULT_THREAD] = [
                    self::RELATIONSHIP_LINKS_SELF => true,
                    self::RELATIONSHIP_LINKS => [
                        Link::RELATED => $this->createLinkToResource($thread),
                    ],
                ];
            }
        }

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getConfigValuesRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_CONFIG_VALUES] = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_CONFIG_VALUES),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getContactsRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_CONTACTS] = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_CONTACTS),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getCoursesRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_COURSES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_COURSES),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getCourseMembershipsRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_COURSE_MEMBERSHIPS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_COURSE_MEMBERSHIPS),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getCoursewareBookmarksRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_COURSEWARE_BOOKMARKS] = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_COURSEWARE_BOOKMARKS),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getFileRefsRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_FILES] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_FILES),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getFoldersRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_FOLDERS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_FOLDERS),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getInboxRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_INBOX] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_INBOX),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getInstituteMembershipsRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_INSTITUTE_MEMBERSHIPS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_INSTITUTE_MEMBERSHIPS),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getEventsRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_EVENTS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_EVENTS),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getNewsRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_NEWS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_NEWS),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getOutboxRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_OUTBOX] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_OUTBOX),
            ],
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getScheduleRelationship(array $relationships, \User $user, $includeData)
    {
        $relationships[self::REL_SCHEDULE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($user, self::REL_SCHEDULE),
            ],
        ];

        return $relationships;
    }
}
