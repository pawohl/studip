<?php

namespace JsonApi\Schemas;

use Forum\Category as ForumCategory;
use JsonApi\Routes\Courses\CourseMembershipsTrait;
use JsonApi\Routes\Feedback\Authority as FeedbackAuthority;
use JsonApi\Routes\Files\Authority as FilesAuthority;
use JsonApi\Routes\Forum\Authority as ForumAuthority;
use JsonApi\Routes\News\Authority as NewsAuthority;
use JsonApi\Routes\Wiki\Authority as WikiAuthority;
use Neomerx\JsonApi\Contracts\Schema\ContextInterface;
use Neomerx\JsonApi\Schema\Link;

class Course extends SchemaProvider
{
    use CourseMembershipsTrait;

    const TYPE = 'courses';

    const REL_BLUBBER = 'blubber-threads';
    const REL_COURSEWARE = 'courseware';
    const REL_END_SEMESTER = 'end-semester';
    const REL_EVENTS = 'events';
    const REL_FEEDBACK = 'feedback-elements';
    const REL_FILES = 'file-refs';
    const REL_FOLDERS = 'folders';
    const REL_FORUM_CATEGORIES = 'forum-categories';
    const REL_INSTITUTE = 'institute';
    const REL_MEMBERSHIPS = 'memberships';
    const REL_NEWS = 'news';
    const REL_PARTICIPATING_INSTITUTES = 'participating-institutes';
    const REL_SEM_CLASS = 'sem-class';
    const REL_SEM_TYPE = 'sem-type';
    const REL_START_SEMESTER = 'start-semester';
    const REL_STATUS_GROUPS = 'status-groups';
    const REL_TAGS = 'tags';
    const REL_TOOLS = 'tools';
    const REL_WIKI_PAGES = 'wiki-pages';

    protected ?array $allowedIncludes = [
        self::REL_BLUBBER,
        self::REL_COURSEWARE,
        self::REL_END_SEMESTER,
        self::REL_EVENTS,
        self::REL_FEEDBACK,
        self::REL_FORUM_CATEGORIES,
        self::REL_INSTITUTE,
        self::REL_MEMBERSHIPS,
        self::REL_NEWS,
        self::REL_PARTICIPATING_INSTITUTES,
        self::REL_SEM_CLASS,
        self::REL_SEM_TYPE,
        self::REL_START_SEMESTER,
        self::REL_STATUS_GROUPS,
        self::REL_TAGS,
        self::REL_TOOLS,
        self::REL_WIKI_PAGES,
    ];

    /**
     * @param \Course $course
     */
    public function getId($course): ?string
    {
        return $course->id;
    }

    /**
     * @param \Course $course
     */
    public function getAttributes($course, ContextInterface $context): iterable
    {
        $stringOrNull = function ($item) {
            return trim($item) != '' ? (string) $item : null;
        };

        return [
            'course-number' => $stringOrNull($course->veranstaltungsnummer),

            'title' => (string) $course->name,
            'subtitle' => $stringOrNull($course->untertitel),
            'course-type' => (int) $course->status,
            'description' => $stringOrNull($course->beschreibung),
            'location' => $stringOrNull($course->ort),
            'miscellaneous' => $stringOrNull($course->sonstiges),

            // 'read-access' => (int) $course->lesezugriff,
            // 'write-access' => (int) $course->schreibzugriff,
        ];
    }

    /**
     * @param \Course $course
     */
    public function getRelationships($course, ContextInterface $context): iterable
    {
        $relationships = [];

        $relationships[self::REL_INSTITUTE] = $this->getInstitute($course);

        $semester = $this->getStartSemester($course);
        if ($semester) {
            $relationships[self::REL_START_SEMESTER] = $semester;
        }

        $semester = $this->getEndSemester($course);
        if ($semester) {
            $relationships[self::REL_END_SEMESTER] = $semester;
        }

        $relationships = $this->getParticipatingInstitutes($relationships, $course, $this->shouldInclude($context, self::REL_PARTICIPATING_INSTITUTES));
        $relationships = $this->getFilesRelationship($relationships, $course);
        $relationships = $this->getForumCategoriesRelationship($relationships, $course, $this->shouldInclude($context, self::REL_FORUM_CATEGORIES));
        $relationships = $this->getBlubberRelationship($relationships, $course, $this->shouldInclude($context, self::REL_BLUBBER));
        $relationships = $this->getCoursewareRelationship($relationships, $course, $this->shouldInclude($context, self::REL_COURSEWARE));
        $relationships = $this->getEventsRelationship($relationships, $course, $this->shouldInclude($context, self::REL_EVENTS));
        $relationships = $this->getFeedbackRelationship($relationships, $course, $this->shouldInclude($context, self::REL_FEEDBACK));
        $relationships = $this->getMembershipsRelationship($relationships, $course, $this->shouldInclude($context, self::REL_MEMBERSHIPS));
        $relationships = $this->getNewsRelationship($relationships, $course, $this->shouldInclude($context, self::REL_NEWS));
        $relationships = $this->getSemClassRelationship($relationships, $course, $this->shouldInclude($context, self::REL_SEM_CLASS));
        $relationships = $this->getSemTypeRelationship($relationships, $course, $this->shouldInclude($context, self::REL_SEM_TYPE));
        $relationships = $this->getStatusGroupsRelationship($relationships, $course, $this->shouldInclude($context, self::REL_STATUS_GROUPS));
        $relationships = $this->getTagsRelationship($relationships, $course, $this->shouldInclude($context, self::REL_TAGS));
        $relationships = $this->getToolsRelationship($relationships, $course, $this->shouldInclude($context, self::REL_TOOLS));
        $relationships = $this->getWikiPagesRelationship($relationships, $course, $this->shouldInclude($context, self::REL_WIKI_PAGES));

        return $relationships;
    }

    private function getInstitute(\Course $course): array
    {
        if (!$course->institut_id) {
            return [self::RELATIONSHIP_DATA => null];
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($course->home_institut),
            ],
            self::RELATIONSHIP_DATA => $course->home_institut,
        ];
    }

    private function getStartSemester(\Course $course)
    {
        if (!$course->start_semester) {
            return null;
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($course->start_semester),
            ],
            self::RELATIONSHIP_DATA => $course->start_semester,
        ];
    }

    private function getEndSemester(\Course $course)
    {
        if (!$course->end_semester) {
            return null;
        }

        return [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($course->end_semester),
            ],
            self::RELATIONSHIP_DATA => $course->end_semester,
        ];
    }

    private function getFilesRelationship(array $relationships, \Course $resource)
    {
        $user = $this->currentUser;

        if ($user && FilesAuthority::canShowFileArea($user, $resource)) {
            $filesLink = $this->getRelationshipRelatedLink($resource, self::REL_FILES);

            $relationships[self::REL_FILES] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $filesLink,
                ],
            ];

            $foldersLink = $this->getRelationshipRelatedLink($resource, self::REL_FOLDERS);
            $relationships[self::REL_FOLDERS] = [
                self::RELATIONSHIP_LINKS => [
                    Link::RELATED => $foldersLink,
                ],
            ];
        }

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getForumCategoriesRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relationship = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_FORUM_CATEGORIES)
            ],
        ];

        if ($includeData && ForumAuthority::canShowForum($this->currentUser, $course)) {
            $relationship[self::RELATIONSHIP_DATA] = ForumCategory::getCourseCategories($course->id);
        }

        return array_merge($relationships, [self::REL_FORUM_CATEGORIES => $relationship]);
    }

    private function getBlubberRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relationship = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_BLUBBER),
            ],
        ];

        if ($includeData) {
            $relationship[self::RELATIONSHIP_DATA] = \BlubberThread::findBySeminar($course->id, false, $this->currentUser->id);
        }

        return array_merge($relationships, [self::REL_BLUBBER => $relationship]);
    }

        /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getCoursewareRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relationships[self::REL_COURSEWARE] = [
            self::RELATIONSHIP_DATA =>
                \Courseware\Instance::existsForRange($course) ? \Courseware\Instance::findForRange($course) : null,
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_COURSEWARE),
            ],
        ];

        return $relationships;
    }


    private function getEventsRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_EVENTS)
            ],
        ];

        if ($includeData) {
            $all_dates = array_merge(
                $course->dates->getArrayCopy(),
                $course->ex_dates->getArrayCopy()
            );
            usort(
                $all_dates,
                fn($date1, $date2) => intval($date1->date) <=> intval($date2->date)
            );

            $relation[self::RELATIONSHIP_DATA] = $all_dates;
        }

        return array_merge($relationships, [self::REL_EVENTS => $relation]);
    }

    private function getFeedbackRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        if (!\Feedback::isActivated($course->id)) {
            return $relationships;
        }

        $relationship = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_FEEDBACK)
            ],
        ];

        if ($includeData && FeedbackAuthority::canIndexFeedbackElementsOfCourse($this->currentUser, $course)) {
            $relationship[self::RELATIONSHIP_DATA] = \FeedbackElement::findBySQL(
                'course_id = ?',
                [$course->id]
            );
        }

        return array_merge($relationships, [self::REL_FEEDBACK => $relationship]);
    }

    private function getMembershipsRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relationship = [
            self::RELATIONSHIP_LINKS_SELF => true,
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_MEMBERSHIPS)
            ],
        ];

        if ($includeData) {
            $relationship[self::RELATIONSHIP_DATA] = $this->getCourseMemberships($course, $this->currentUser);
        }

        return array_merge($relationships, [self::REL_MEMBERSHIPS => $relationship]);
    }

    private function getNewsRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relationship = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_NEWS)
            ],
        ];

        if ($includeData) {
            $relationship[self::RELATIONSHIP_DATA] = \StudipNews::GetNewsByRange($course->id, true, true);
        }

        return array_merge($relationships, [self::REL_NEWS => $relationship]);
    }

    private function getWikiPagesRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relationship = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_WIKI_PAGES)
            ],
        ];

        if ($includeData && WikiAuthority::canIndexWiki($this->currentUser, $course)) {
            $relationship[self::RELATIONSHIP_DATA] = \WikiPage::findBySQL(
                '`range_id` = ? ORDER BY name',
                [$course->id]
            );
        }

        return array_merge($relationships, [self::REL_WIKI_PAGES => $relationship]);
    }


    private function getToolsRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_TOOLS),
            ]
        ];
        if ($includeData) {
            $relation[self::RELATIONSHIP_DATA] = $course->tools->getArrayCopy();
        }

        return array_merge($relationships, [self::REL_TOOLS => $relation]);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getParticipatingInstitutes(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $institutes = $course->institutes->filter(
            function (\Institute $institute) use ($course) {
                return $institute->id != $course->institut_id;
            }
        );

        $relationships[self::REL_PARTICIPATING_INSTITUTES] = [
            self::RELATIONSHIP_DATA => $institutes
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getSemClassRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $related = $course->getSemClass();
        $relationships[self::REL_SEM_CLASS] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($related),
            ],
            self::RELATIONSHIP_DATA => $related
        ];

        return $relationships;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getSemTypeRelationship(
        array $relationships,
        \Course $course,
        $includeData
    ) {
        $related = $course->getSemType();
        $relationships[self::REL_SEM_TYPE] = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->createLinkToResource($related),
            ],
            self::RELATIONSHIP_DATA => $related
        ];

        return $relationships;
    }

    private function getStatusGroupsRelationship(
        array $relationships,
        \Course $resource,
        $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($resource, self::REL_STATUS_GROUPS),
            ]
        ];
        if ($includeData) {
            $relation[self::RELATIONSHIP_DATA] = $resource->statusgruppen;
        }

        return array_merge($relationships, [self::REL_STATUS_GROUPS => $relation]);
    }

    private function getTagsRelationship(
        array   $relationships,
        \Course $course,
        $includeData
    ) {
        $relation = [
            self::RELATIONSHIP_LINKS => [
                Link::RELATED => $this->getRelationshipRelatedLink($course, self::REL_TAGS),
            ]
        ];
        if ($includeData) {
            $relation[self::RELATIONSHIP_DATA] = $course->tags;
        }

        return array_merge($relationships, [self::REL_TAGS => $relation]);
    }

    /**
     * @inheritdoc
     */
    public function hasResourceMeta($resource): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @param \Course $resource
     */
    public function getResourceMeta($resource)
    {
        $avatar = $resource->isStudygroup()
                ? \StudygroupAvatar::getAvatar($resource->id)
                : \CourseAvatar::getAvatar($resource->id);

        return [
            'avatar' => [
                'small' => $avatar->getURL(\Avatar::SMALL),
                'medium' => $avatar->getURL(\Avatar::MEDIUM),
                'normal' => $avatar->getURL(\Avatar::NORMAL),
            ],
            'members-count' => count($resource->members),
        ];
    }

}
