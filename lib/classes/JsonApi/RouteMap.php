<?php

namespace JsonApi;

use JsonApi\Contracts\JsonApiPlugin;
use JsonApi\Middlewares\Authentication;
use JsonApi\Middlewares\DangerousRouteHandler;
use JsonApi\Routes\Consultations\SlotCreationCount;
use JsonApi\Routes\Holidays\HolidaysShow;
use JsonApi\Routes\Vacations\VacationsShow;
use Slim\Routing\RouteCollectorProxy;
use Studip\JsonApi\Routes\AvailableRooms;

/**
 * Diese Klasse ist die JSON-API-Routemap, in der alle Routen
 * registriert werden und die Middleware hinzugefügt wird, die
 * JSON-API spezifische Fehlerbehandlung usw. übernimmt.
 *
 * Routen der Kernklassen sind hier explizit vermerkt.
 *
 * Routen aus Plugins werden über die PluginEngine abgefragt. Plugins
 * können genau dann eigene Routen registrieren, wenn sie das
 * Interface \JsonApi\Contracts\JsonApiPlugin implementieren.
 *
 * Routen können entweder mit Autorisierung oder auch ohne eingetragen
 * werden. Autorisierte Kernrouten werden in
 * RouteMap::authenticatedRoutes vermerkt. Kernrouten ohne
 * notwendige Autorisierung werden in
 * RouteMap::unauthenticatedRoutes registriert. Routen aus Plugins
 * werden jeweils in den Methoden
 * \JsonApi\Contracts\JsonApiPlugin::registerAuthenticatedRoutes und
 * \JsonApi\Contracts\JsonApiPlugin::registerUnauthenticatedRoutes
 * eingetragen.
 *
 * Zu authentifizierende Routen werden in \JsonApi\Middlewares\Authentication
 * authentifiziert.
 *
 * Wie Routen registriert werden, kann man im `User Guide` des
 * Slim-Frameworks nachlesen
 * (http://www.slimframework.com/docs/objects/router.html#how-to-create-routes)
 *
 * Route-Handler können als Funktionen, in der Slim-Syntax
 * "Klassenname:Methodenname" oder auch mit dem Klassennamen einer
 * Klasse, die __invoke implementiert, angegeben werden. Die
 * __invoke-Variante wird hier sehr empfohlen.
 *
 * Beispiel:
 *
 *   use Studip\MeineRoute;
 *
 *   $this->app->post('/article/{id}/comments', MeineRoute::class);
 *
 * @see \JsonApi\Middlewares\Authentication
 * @see \JsonApi\Contracts\JsonApiPlugin
 * @see http://www.slimframework.com/docs/objects/router.html#how-to-create-routes
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RouteMap
{
    /** @var \Slim\App */
    private $app;

    /**
     * Der Konstruktor.
     *
     * @param \Slim\App $app die Slim-Applikation, in der die Routen
     *                       definiert werden sollen
     */
    public function __construct(\Slim\App $app)
    {
        $this->app = $app;
    }

    /**
     * Hier werden die Routen tatsächlich eingetragen.
     * Autorisierte Routen werden mit der Middleware
     * \JsonApi\Middlewares\Authentication ausgestattet und in
     * RouteMap::authenticatedRoutes eingetragen. Routen ohne
     * Autorisierung werden in RouteMap::unauthenticatedRoutes vermerkt.
     */
    public function __invoke(RouteCollectorProxy $group): void
    {
        $group->group('', [$this, 'authenticatedRoutes'])->add(new Authentication($this->getAuthenticator()));
        $group->group('', [$this, 'unauthenticatedRoutes']);

        $group->get('/discovery', Routes\DiscoveryIndex::class);
    }

    /**
     * Hier werden autorisierte (Kern-)Routen explizit vermerkt.
     * Außerdem wird über die \PluginEngine allen JsonApiPlugins die
     * Möglichkeit gegeben, sich hier einzutragen.
     */
    public function authenticatedRoutes(RouteCollectorProxy $group): void
    {
        \PluginEngine::sendMessage(JsonApiPlugin::class, 'registerAuthenticatedRoutes', $group);

        $group->get('/users', Routes\Users\UsersIndex::class);
        $group->get('/users/me', Routes\Users\UsersShow::class)->setName('get-myself');
        $group->get('/users/{id}', Routes\Users\UsersShow::class);
        $group->delete('/users/{id}', Routes\Users\UsersDelete::class)->add(DangerousRouteHandler::class);

        $group->get('/users/{id}/activitystream', Routes\ActivityStream\ActivityStreamShow::class);
        $group->get('/users/{id}/institute-memberships', Routes\InstituteMemberships\ByUserIndex::class);
        $group->get('/users/{id}/course-memberships', Routes\CourseMemberships\ByUserIndex::class);
        $group->get('/course-memberships/{id}', Routes\CourseMemberships\CourseMembershipsShow::class);
        $group->patch('/course-memberships/{id}', Routes\CourseMemberships\CourseMembershipsUpdate::class);

        $group->get('/users/{id}/schedule', Routes\Schedule\UserScheduleShow::class)->setName('get-schedule');
        $group->get('/schedule-entries/{id}', Routes\Schedule\ScheduleEntriesShow::class);
        $group->get('/seminar-cycle-dates/{id}', Routes\Schedule\SeminarCycleDatesShow::class);

        $group->get('/users/{id}/config-values', Routes\ConfigValues\ByUserIndex::class);
        $group->get('/config-values/{id}', Routes\ConfigValues\ConfigValuesShow::class);
        $group->patch('/config-values/{id}', Routes\ConfigValues\ConfigValuesUpdate::class);

        $group->get('/status-groups/{id}', Routes\StatusgroupShow::class);

        $group->get('/available-rooms', AvailableRooms::class);

        $group->get('/studygroup-proposals', Routes\Studygroups\Proposals::class);

        $this->addAuthenticatedAdmissionRoutes($group);
        $this->addAuthenticatedBlubberRoutes($group);
        $this->addAuthenticatedClipboardRoutes($group);
        $this->addAuthenticatedConsultationRoutes($group);
        $this->addAuthenticatedContactsRoutes($group);
        $this->addAuthenticatedCoursesRoutes($group);

        if (\PluginManager::getInstance()->getPlugin(\CoursewareModule::class)) {
            $this->addAuthenticatedCoursewareRoutes($group);
        }

        $this->addAuthenticatedDatafieldRoutes($group);
        $this->addAuthenticatedAvatarRoutes($group);
        $this->addAuthenticatedMvvRoutes($group);
        $this->addAuthenticatedEventsRoutes($group);
        $this->addAuthenticatedFeedbackRoutes($group);
        $this->addAuthenticatedFilesRoutes($group);
        $this->addAuthenticatedForumRoutes($group);
        $this->addAuthenticatedInstitutesRoutes($group);
        $this->addAuthenticatedLtiRoutes($group);
        $this->addAuthenticatedMassMailRoutes($group);
        $this->addAuthenticatedMessagesRoutes($group);
        $this->addAuthenticatedNewsRoutes($group);
        $this->addAuthenticatedStockImagesRoutes($group);
        $this->addAuthenticatedStudyAreasRoutes($group);
        $this->addAuthenticatedThemesRoutes($group);
        $this->addAuthenticatedUserFilterRoutes($group);
        $this->addAuthenticatedWikiRoutes($group);
        $this->addAuthenticatedShortUrlRoutes($group);
    }

    /**
     * Hier werden unautorisierte (Kern-)Routen explizit vermerkt.
     * Außerdem wird über die \PluginEngine allen JsonApiPlugins die
     * Möglichkeit gegeben, sich hier einzutragen.
     */
    public function unauthenticatedRoutes(RouteCollectorProxy $group): void
    {
        \PluginEngine::sendMessage(JsonApiPlugin::class, 'registerUnauthenticatedRoutes', $group);

        $group->get('/holidays', HolidaysShow::class);
        $group->get('/vacations', VacationsShow::class);

        $group->get('/semesters', Routes\SemestersIndex::class);
        $group->get('/semesters/{id}', Routes\SemestersShow::class)->setName('get-semester');

        $group->get('/studip/properties', Routes\Studip\PropertiesIndex::class);

        if (\PluginManager::getInstance()->getPlugin(\CoursewareModule::class)) {
            $group->get('/public/courseware/{link_id}/courseware-structural-elements/{id}', Routes\Courseware\PublicStructuralElementsShow::class);
            $group->get('/public/courseware/{link_id}/courseware-structural-elements/{id}/descendants', Routes\Courseware\DescendantsOfPublicStructuralElementsIndex::class);
            $group->get('/public/courseware/{link_id}/courseware-structural-elements', Routes\Courseware\PublicStructuralElementsIndex::class);
        }

        $this->addUnauthenticatedTreeRoutes($group);
        $this->addUnAuthenticatedForumRoutes($group);
    }

    private function getAuthenticator(): callable
    {
        return $this->app->getContainer()->get('studip-authenticator');
    }

    private function addAuthenticatedAdmissionRoutes(RouteCollectorProxy $group): void {
        $group->post('/course-sets', Routes\Admission\CourseSetsCreate::class);
        $group->get('/course-sets/{id}', Routes\Admission\CourseSetsShow::class);
        $group->patch('/course-sets/{id}', Routes\Admission\CourseSetsUpdate::class);
        $group->delete('/course-sets/{id}', Routes\Admission\CourseSetsDelete::class);
        $group->post('/admission/available-courses', Routes\Admission\AvailableCoursesIndex::class);
        $group->get('/admission/rule-compatibility', Routes\Admission\RuleCompatibilityIndex::class);
        $group->get('/admission-rules', Routes\Admission\AdmissionRulesIndex::class);
        $group->post('/admission-rules/{type}', Routes\Admission\AdmissionRulesCreate::class);
        $group->get('/admission-rules/{id}', Routes\Admission\AdmissionRulesShow::class);
        $group->patch('/admission-rules/{id}', Routes\Admission\AdmissionRulesUpdate::class);
        $group->delete('/admission-rules/{id}', Routes\Admission\AdmissionRulesDelete::class);
    }

    private function addAuthenticatedBlubberRoutes(RouteCollectorProxy $group): void
    {
        // find BlubberThreads
        $group->get('/courses/{id}/blubber-threads', Routes\Blubber\ThreadsIndex::class)->setArgument('type', 'course');
        $group
            ->get('/institutes/{id}/blubber-threads', Routes\Blubber\ThreadsIndex::class)
            ->setArgument('type', 'institute');
        $group->get('/studip/blubber-threads', Routes\Blubber\ThreadsIndex::class)->setArgument('type', 'public');
        $group->get('/users/{id}/blubber-threads', Routes\Blubber\ThreadsIndex::class)->setArgument('type', 'private');
        $group->get('/blubber-threads', Routes\Blubber\ThreadsIndex::class)->setArgument('type', 'all');
        $group->get('/blubber-threads/{id}', Routes\Blubber\ThreadsShow::class);
        $group->post('/blubber-threads', Routes\Blubber\ThreadsCreate::class);
        $group->patch('/blubber-threads/{id}', Routes\Blubber\ThreadsUpdate::class);

        // create, read, update and delete BlubberComments
        $group->get('/blubber-threads/{id}/comments', Routes\Blubber\CommentsByThreadIndex::class);
        $group->post('/blubber-threads/{id}/comments', Routes\Blubber\CommentsCreate::class);
        $group->get('/blubber-comments', Routes\Blubber\CommentsIndex::class);
        $group->get('/blubber-comments/{id}', Routes\Blubber\CommentsShow::class);
        $group->post('/blubber-comments', Routes\Blubber\CommentsCreate::class);
        $group->patch('/blubber-comments/{id}', Routes\Blubber\CommentsUpdate::class);
        $group->delete('/blubber-comments/{id}', Routes\Blubber\CommentsDelete::class);

        // REL blubber-threads > mentions
        $this->addRelationship(
            $group,
            '/blubber-threads/{id}/relationships/mentions',
            Routes\Blubber\Rel\Mentions::class
        );

        // REL users > blubber-default-thread
        $this->addRelationship(
            $group,
            '/users/{id}/relationships/blubber-default-thread',
            Routes\Blubber\Rel\DefaultThread::class
        );
    }

    private function addAuthenticatedClipboardRoutes(RouteCollectorProxy $group): void
    {
        $group->post('/clipboards', Routes\Clipboards\ClipboardsCreate::class);
        $group->patch('/clipboards/{id}', Routes\Clipboards\ClipboardsUpdate::class);
        $group->delete('/clipboards/{id}', Routes\Clipboards\ClipboardsDelete::class);

        $group->get('/clipboard-items/{id}', Routes\Clipboards\ClipboardItemsShow::class);
        $group->post('/clipboards/{id}/items', Routes\Clipboards\ClipboardItemsCreate::class);
        $group->delete('/clipboards/{id}/items', Routes\Clipboards\ClipboardItemsDelete::class);
        $group->delete('/clipboards/{id}/items/{itemId}', Routes\Clipboards\ClipboardItemsDelete::class);

        $group->post('/clipboard-items', Routes\Clipboards\ClipboardItemsCreate::class);
        $group->delete('/clipboard-items/{id}', Routes\Clipboards\ClipboardItemsDelete::class);
    }

    private function addAuthenticatedConsultationRoutes(RouteCollectorProxy $group): void
    {
        // TODO: I know, not very JSONAPI-like but it's a NonJsonApiController ¯\_(ツ)_/¯
        $group->get('/consultation-slots/count', SlotCreationCount::class);

        $group->get('/{type:courses|institutes|users}/{id}/consultations', Routes\Consultations\BlocksByRangeIndex::class);

        $group->get('/consultation-blocks/{id}', Routes\Consultations\BlockShow::class);
        $group->get('/consultation-blocks/{id}/slots', Routes\Consultations\SlotsByBlockIndex::class);

        $group->get('/consultation-slots/{id}', Routes\Consultations\SlotShow::class);
        $group->get('/consultation-slots/{id}/bookings', Routes\Consultations\BookingsBySlotIndex::class);
        $group->post('/consultation-slots/{id}/bookings', Routes\Consultations\BookingsCreate::class);

        $group->post('/consultation-bookings', Routes\Consultations\BookingsCreate::class);
        $group->get('/consultation-bookings/{id}', Routes\Consultations\BookingsShow::class);
        $group->delete('/consultation-bookings/{id}', Routes\Consultations\BookingsDelete::class);
    }

    private function addAuthenticatedContactsRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/users/{id}/contacts', Routes\Users\ContactsIndex::class);
        $this->addRelationship($group, '/users/{id}/relationships/contacts', Routes\Users\Rel\Contacts::class);
    }

    private function addAuthenticatedEventsRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/courses/{id}/events', Routes\Events\CourseEventsIndex::class);
        $group->get('/users/{id}/events', Routes\Events\UserEventsIndex::class);

        // not a JSON:API route
        $group->get('/users/{id}/events.ics', Routes\Events\UserEventsIcal::class);
    }

    private function addAuthenticatedFeedbackRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/feedback-elements/{id}', Routes\Feedback\FeedbackElementsShow::class);
        $group->get('/courses/{id}/feedback-elements', Routes\Feedback\FeedbackElementsByCourseIndex::class);
        $group->get('/file-refs/{id}/feedback-elements', Routes\Feedback\FeedbackElementsByFileRefIndex::class);
        $group->get('/folders/{id}/feedback-elements', Routes\Feedback\FeedbackElementsByFolderIndex::class);

        $group->post('/feedback-elements', Routes\Feedback\FeedbackElementsCreate::class);
        $group->patch('/feedback-elements/{id}', Routes\Feedback\FeedbackElementsUpdate::class);
        $group->delete('/feedback-elements/{id}', Routes\Feedback\FeedbackElementsDelete::class);

        $group->get('/feedback-elements/{id}/entries', Routes\Feedback\FeedbackEntriesIndex::class);
        $group->post('/feedback-entries', Routes\Feedback\FeedbackEntriesCreate::class);

        $group->get('/feedback-entries/{id}', Routes\Feedback\FeedbackEntriesShow::class);
        $group->patch('/feedback-entries/{id}', Routes\Feedback\FeedbackEntriesUpdate::class);
        $group->delete('/feedback-entries/{id}', Routes\Feedback\FeedbackEntriesDelete::class);
    }

    private function addAuthenticatedInstitutesRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/institute-memberships/{id}', Routes\InstituteMemberships\InstituteMembershipsShow::class);
        $group->get('/institutes/{id}', Routes\Institutes\InstitutesShow::class);
        $group->get('/institutes', Routes\Institutes\InstitutesIndex::class);

        $group->get('/institutes/{id}/sub-institutes', Routes\Institutes\ByFacultyIndex::class);
        $group->get('/institutes/{id}/memberships', Routes\Institutes\InstituteMembershipsIndex::class);
        $group->get('/institutes/{id}/status-groups', Routes\Institutes\StatusGroupsOfInstitutes::class);
        $group->get('/institutes/{id}/courses-of-study', Routes\Institutes\CoursesOfStudyOfInstitutes::class);
    }

    private function addAuthenticatedLtiRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/lti-tools/{id}', Routes\Lti\LtiToolsShow::class);
        $group->get('/lti-tools', Routes\Lti\LtiToolsIndex::class);
    }


    private function addAuthenticatedMassMailRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/mass-mails/messages', Routes\MassMail\MassMailMessagesIndex::class);
        $group->get('/mass-mails/permissions', Routes\MassMail\MassMailPermissionsIndex::class);
        $group->get('/mass-mails/permissions/{id}', Routes\MassMail\MassMailPermissionsShow::class);
    }

    private function addAuthenticatedNewsRoutes(RouteCollectorProxy $group): void
    {
        $group->post('/courses/{id}/news', Routes\News\CourseNewsCreate::class);
        $group->post('/users/{id}/news', Routes\News\UserNewsCreate::class);
        $group->post('/news', Routes\News\StudipNewsCreate::class);
        $group->post('/news/{id}/comments', Routes\News\CommentCreate::class);
        $group->patch('/news/{id}', Routes\News\NewsUpdate::class);
        $group->get('/news/{id}', Routes\News\NewsShow::class);
        $group->get('/courses/{id}/news', Routes\News\ByCourseIndex::class);
        $group->get('/users/{id}/news', Routes\News\ByUserIndex::class);
        $group->get('/news/{id}/comments', Routes\News\CommentsIndex::class);
        $group->get('/news', Routes\News\ByCurrentUser::class);
        $group->get('/studip/news', Routes\News\GlobalNewsShow::class);
        $group->delete('/news/{id}', Routes\News\NewsDelete::class);
        $group->delete('/comments/{id}', Routes\News\CommentsDelete::class);

        // RELATIONSHIP: 'ranges'
        $this->addRelationship($group, '/news/{id}/relationships/ranges', Routes\News\Rel\Ranges::class);
    }

    private function addAuthenticatedStudyAreasRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/study-areas', Routes\StudyAreas\StudyAreasIndex::class);
        $group->get('/study-areas/{id}', Routes\StudyAreas\StudyAreasShow::class);

        $group->get('/study-areas/{id}/children', Routes\StudyAreas\ChildrenOfStudyAreas::class);
        $group->get('/study-areas/{id}/courses', Routes\StudyAreas\CoursesOfStudyAreas::class);
        $group->get('/study-areas/{id}/institute', Routes\StudyAreas\InstituteOfStudyAreas::class);
        $group->get('/study-areas/{id}/parent', Routes\StudyAreas\ParentOfStudyAreas::class);
    }

    private function addUnauthenticatedTreeRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/tree-node/{id}', Routes\Tree\TreeShow::class);

        $group->get('/tree-node/{id}/children', Routes\Tree\ChildrenOfTreeNode::class);
        $group->get('/tree-node/{id}/courseinfo', Routes\Tree\CourseInfoOfTreeNode::class);
        $group->get('/tree-node/{id}/courses', Routes\Tree\CoursesOfTreeNode::class);
        $group->get('/tree-node/course/pathinfo/{classname}/{id}', Routes\Tree\PathinfoOfTreeNodeCourse::class);
        $group->get('/tree-node/course/details/{id}', Routes\Tree\DetailsOfTreeNodeCourse::class);
    }

    private function addAuthenticatedWikiRoutes(RouteCollectorProxy $group): void
    {
        $this->addRelationship($group, '/wiki-pages/{id:.+}/relationships/parent', Routes\Wiki\Rel\ParentPage::class);
        $group->get('/wiki-pages/{id:.+}/children', Routes\Wiki\ChildrenIndex::class);
        $group->get('/wiki-pages/{id:.+}/descendants', Routes\Wiki\DescendantsIndex::class);

        $group->get('/courses/{id}/wiki-pages', Routes\Wiki\WikiIndex::class);
        $group->get('/wiki-pages/{id:.+}', Routes\Wiki\WikiShow::class)->setName('get-wiki-page');

        $group->post('/courses/{id}/wiki-pages', Routes\Wiki\WikiCreate::class);
        $group->patch('/wiki-pages/{id:.+}', Routes\Wiki\WikiUpdate::class);
        $group->delete('/wiki-pages/{id:.+}', Routes\Wiki\WikiDelete::class);
    }

    private function addAuthenticatedCoursesRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/courses', Routes\Courses\CoursesIndex::class);
        $group->get('/courses/{id}', Routes\Courses\CoursesShow::class);

        $group->get('/users/{id}/courses', Routes\Courses\CoursesByUserIndex::class);

        $group->get('/courses/{id}/memberships', Routes\Courses\CoursesMembershipsIndex::class);
        $this->addRelationship(
            $group,
            '/courses/{id}/relationships/memberships',
            Routes\Courses\Rel\Memberships::class
        );

        $group->get('/courses/{id}/status-groups', Routes\Courses\StatusGroupsOfCourses::class);

        $group->get('/sem-classes', Routes\Courses\SemClassesIndex::class);
        $group->get('/sem-classes/{id}', Routes\Courses\SemClassesShow::class);
        $group->get('/sem-classes/{id}/sem-types', Routes\Courses\SemTypesBySemClassIndex::class);
        $group->get('/sem-types', Routes\Courses\SemTypesIndex::class);
        $group->get('/sem-types/{id}', Routes\Courses\SemTypesShow::class);

        $group->get('/module-components/{id}/courses', Routes\Courses\CoursesByModuleComponentsIndex::class);
    }

    private function addAuthenticatedCoursewareRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/{type:courses|users|sharedusers}/{id}/courseware', Routes\Courseware\CoursewareInstancesShow::class);
        $group->patch('/courseware-instances/{id}', Routes\Courseware\CoursewareInstancesUpdate::class);
        $this->addRelationship(
            $group,
            '/courseware-instances/{id}/relationships/bookmarks',
            Routes\Courseware\Rel\BookmarkedStructuralElements::class
        );
        $group->get('/courseware-instances/{id}/bookmarks', Routes\Courseware\BookmarkedStructuralElementsIndex::class);

        $group->get('/users/{id}/courseware-bookmarks', Routes\Courseware\UsersBookmarkedStructuralElementsIndex::class);
        $this->addRelationship(
            $group,
            '/users/{id}/relationships/courseware-bookmarks',
            Routes\Courseware\Rel\UsersBookmarkedStructuralElements::class
        );

        $group->get('/courseware-blocks/{id}', Routes\Courseware\BlocksShow::class);
        $group->post('/courseware-blocks', Routes\Courseware\BlocksCreate::class);
        $group->patch('/courseware-blocks/{id}', Routes\Courseware\BlocksUpdate::class);
        $group->delete('/courseware-blocks/{id}', Routes\Courseware\BlocksDelete::class);

        $this->addRelationship(
            $group,
            '/courseware-blocks/{id}/relationships/edit-blocker',
            Routes\Courseware\Rel\BlocksEditBlocker::class
        );

        $this->addRelationship(
            $group,
            '/courseware-blocks/{id}/relationships/file-refs',
            Routes\Courseware\Rel\BlocksFilerefs::class
        );
        $group->get('/courseware-blocks/{id}/file-refs', Routes\Courseware\BlocksListFiles::class);

        // not a JSON route
        $group->post('/courseware-blocks/{id}/copy', Routes\Courseware\BlocksCopy::class);

        $group->get('/courseware-containers/{id}', Routes\Courseware\ContainersShow::class);
        $group->post('/courseware-containers', Routes\Courseware\ContainersCreate::class);
        $group->patch('/courseware-containers/{id}', Routes\Courseware\ContainersUpdate::class);
        $group->delete('/courseware-containers/{id}', Routes\Courseware\ContainersDelete::class);
        $group->get('/courseware-containers/{id}/blocks', Routes\Courseware\BlocksIndex::class);
        $this->addRelationship(
            $group,
            '/courseware-containers/{id}/relationships/blocks',
            Routes\Courseware\Rel\ContainersBlocks::class
        );
        $this->addRelationship(
            $group,
            '/courseware-containers/{id}/relationships/edit-blocker',
            Routes\Courseware\Rel\ContainersEditBlocker::class
        );

        // not a JSON route
        $group->post('/courseware-containers/{id}/copy', Routes\Courseware\ContainersCopy::class);

        $group->get('/courseware-structural-elements/{id}', Routes\Courseware\StructuralElementsShow::class);
        $group->get('/courseware-structural-elements', Routes\Courseware\StructuralElementsIndex::class);
        $group->post('/courseware-structural-elements', Routes\Courseware\StructuralElementsCreate::class);
        $group->patch('/courseware-structural-elements/{id}', Routes\Courseware\StructuralElementsUpdate::class);
        $group->delete('/courseware-structural-elements/{id}', Routes\Courseware\StructuralElementsDelete::class);

        $group->get(
            '/courseware-structural-elements/{id}/children',
            Routes\Courseware\ChildrenOfStructuralElementsIndex::class
        );
        $group->get('/courseware-structural-elements/{id}/containers', Routes\Courseware\ContainersIndex::class);
        $this->addRelationship(
            $group,
            '/courseware-structural-elements/{id}/relationships/containers',
            Routes\Courseware\Rel\StructuralElementsContainers::class
        );
        $this->addRelationship(
            $group,
            '/courseware-structural-elements/{id}/relationships/children',
            Routes\Courseware\Rel\StructuralElementsChildren::class
        );
        $group->get(
            '/courseware-structural-elements/{id}/descendants',
            Routes\Courseware\DescendantsOfStructuralElementsIndex::class
        );
        $this->addRelationship(
            $group,
            '/courseware-structural-elements/{id}/relationships/edit-blocker',
            Routes\Courseware\Rel\StructuralElementsEditBlocker::class
        );

        $group->post(
            '/courseware-structural-elements/{id}/image',
            Routes\Courseware\StructuralElementsImageUpload::class
        );
        $group->delete(
            '/courseware-structural-elements/{id}/image',
            Routes\Courseware\StructuralElementsImageDelete::class
        );

        // not a JSON route
        $group->post('/courseware-structural-elements/{id}/copy', Routes\Courseware\StructuralElementsCopy::class);
        $group->post('/courseware-structural-elements/{id}/link', Routes\Courseware\StructuralElementsLink::class);

        $group->get('/courseware-structural-elements/{id}/comments', Routes\Courseware\StructuralElementCommentsOfStructuralElementsIndex::class);
        $group->post('/courseware-structural-element-comments', Routes\Courseware\StructuralElementCommentsCreate::class);
        $group->get('/courseware-structural-element-comments/{id}', Routes\Courseware\StructuralElementCommentsShow::class);
        $group->patch('/courseware-structural-element-comments/{id}', Routes\Courseware\StructuralElementCommentsUpdate::class);
        $group->delete('/courseware-structural-element-comments/{id}', Routes\Courseware\StructuralElementCommentsDelete::class);

        $group->get('/courseware-structural-elements/{id}/feedback', Routes\Courseware\StructuralElementFeedbackOfStructuralElementsIndex::class);
        $group->post('/courseware-structural-element-feedback', Routes\Courseware\StructuralElementFeedbackCreate::class);
        $group->get('/courseware-structural-element-feedback/{id}', Routes\Courseware\StructuralElementFeedbackShow::class);
        $group->patch('/courseware-structural-element-feedback/{id}', Routes\Courseware\StructuralElementFeedbackUpdate::class);
        $group->delete('/courseware-structural-element-feedback/{id}', Routes\Courseware\StructuralElementFeedbackDelete::class);

        $group->get('/courseware-structural-elements-shared', Routes\Courseware\StructuralElementsSharedIndex::class);
        $group->get('/courseware-structural-elements-released', Routes\Courseware\StructuralElementsReleasedIndex::class);


        $group->get('/courseware-blocks/{id}/user-data-field', Routes\Courseware\UserDataFieldOfBlocksShow::class);
        $group->get('/courseware-user-data-fields/{id}', Routes\Courseware\UserDataFieldsShow::class);
        $group->patch('/courseware-user-data-fields/{id}', Routes\Courseware\UserDataFieldsUpdate::class);

        $group->get('/courseware-blocks/{id}/user-progress', Routes\Courseware\UserProgressOfBlocksShow::class);
        $group->get('/courseware-user-progresses/{id}', Routes\Courseware\UserProgressesShow::class);
        // not a JSON route
        $group->get('/courseware-units/{id}/courseware-user-progresses', Routes\Courseware\UserProgressesOfUnitsShow::class);
        $group->patch('/courseware-user-progresses/{id}', Routes\Courseware\UserProgressesUpdate::class);

        // not a JSON route
        $group->get('/courseware-units/{id}/certificate', Routes\Courseware\CertificateShow::class);
        $group->get('/courseware-units/{id}/certificate/{user}', Routes\Courseware\CertificateShow::class);

        $group->get('/courseware-blocks/{id}/comments', Routes\Courseware\BlockCommentsOfBlocksIndex::class);
        $group->post('/courseware-block-comments', Routes\Courseware\BlockCommentsCreate::class);
        $group->get('/courseware-block-comments/{id}', Routes\Courseware\BlockCommentsShow::class);
        $group->patch('/courseware-block-comments/{id}', Routes\Courseware\BlockCommentsUpdate::class);
        $group->delete('/courseware-block-comments/{id}', Routes\Courseware\BlockCommentsDelete::class);

        $group->get('/courseware-blocks/{id}/feedback', Routes\Courseware\BlockFeedbacksOfBlocksIndex::class);
        $group->post('/courseware-block-feedback', Routes\Courseware\BlockFeedbacksCreate::class);
        $group->get('/courseware-block-feedback/{id}', Routes\Courseware\BlockFeedbacksShow::class);
        $group->patch('/courseware-block-feedback/{id}', Routes\Courseware\BlockFeedbacksUpdate::class);
        $group->delete('/courseware-block-feedback/{id}', Routes\Courseware\BlockFeedbacksDelete::class);

        $group->get('/courseware-tasks/{id}', Routes\Courseware\TasksShow::class);
        $group->get('/courseware-tasks', Routes\Courseware\TasksIndex::class);
        $group->patch('/courseware-tasks/{id}', Routes\Courseware\TasksUpdate::class);
        $group->delete('/courseware-tasks/{id}', Routes\Courseware\TasksDelete::class);

        $group->get('/courseware-task-groups/{id}', Routes\Courseware\TaskGroupsShow::class);
        $group->post('/courseware-task-groups', Routes\Courseware\TaskGroupsCreate::class);
        $group->patch('/courseware-task-groups/{id}', Routes\Courseware\TaskGroupsUpdate::class);
        $group->delete('/courseware-task-groups/{id}', Routes\Courseware\TaskGroupsDelete::class);
        $this->addRelationship(
            $group,
            '/courseware-task-groups/{id}/relationships/solvers',
            Routes\Courseware\Rel\SolversOfTaskGroup::class
        );

        $group->get('/courseware-task-feedback/{id}', Routes\Courseware\TaskFeedbackShow::class);
        $group->post('/courseware-task-feedback', Routes\Courseware\TaskFeedbackCreate::class);
        $group->patch('/courseware-task-feedback/{id}', Routes\Courseware\TaskFeedbackUpdate::class);
        $group->delete('/courseware-task-feedback/{id}', Routes\Courseware\TaskFeedbackDelete::class);

        $group->get('/courseware-templates/{id}', Routes\Courseware\TemplatesShow::class);
        $group->get('/courseware-templates', Routes\Courseware\TemplatesIndex::class);
        $group->post('/courseware-templates', Routes\Courseware\TemplatesCreate::class);
        $group->patch('/courseware-templates/{id}', Routes\Courseware\TemplatesUpdate::class);
        $group->delete('/courseware-templates/{id}', Routes\Courseware\TemplatesDelete::class);

        $group->get('/courseware-public-links/{id}', Routes\Courseware\PublicLinksShow::class);
        $group->get('/courseware-public-links', Routes\Courseware\PublicLinksIndex::class);
        $group->post('/courseware-public-links', Routes\Courseware\PublicLinksCreate::class);
        $group->patch('/courseware-public-links/{id}', Routes\Courseware\PublicLinksUpdate::class);
        $group->delete('/courseware-public-links/{id}', Routes\Courseware\PublicLinksDelete::class);

        $group->get('/courses/{id}/courseware-units', Routes\Courseware\CoursesUnitsIndex::class);
        $group->get('/users/{id}/courseware-units', Routes\Courseware\UsersUnitsIndex::class);
        $group->get('/courseware-units/{id}', Routes\Courseware\UnitsShow::class);
        $group->post('/courseware-units', Routes\Courseware\UnitsCreate::class);
        $group->patch('/courseware-units/{id}', Routes\Courseware\UnitsUpdate::class);
        $group->delete('/courseware-units/{id}', Routes\Courseware\UnitsDelete::class);
        // not a JSON route
        $group->post('/courseware-units/{id}/copy', Routes\Courseware\UnitsCopy::class);
        $group->post('/{type:courses|users}/{id}/courseware-units/sort', Routes\Courseware\UnitsSort::class);

        $group->get('/courseware-clipboards', Routes\Courseware\ClipboardsIndex::class);
        $group->get('/users/{id}/courseware-clipboards', Routes\Courseware\UsersClipboardsIndex::class);
        $group->delete('/users/{id}/courseware-clipboards/{type:courseware-blocks|courseware-containers}', Routes\Courseware\UsersClipboardsDelete::class);
        $group->get('/courseware-clipboards/{id}', Routes\Courseware\ClipboardsShow::class);
        $group->post('/courseware-clipboards', Routes\Courseware\ClipboardsCreate::class);
        $group->patch('/courseware-clipboards/{id}', Routes\Courseware\ClipboardsUpdate::class);
        $group->delete('/courseware-clipboards/{id}', Routes\Courseware\ClipboardsDelete::class);

        $group->post('/courseware-clipboards/{id}/insert', Routes\Courseware\ClipboardsInsert::class);

        $group->get('/courseware-peer-review-processes', Routes\Courseware\PeerReview\ProcessesIndex::class);
        $group->get('/courseware-peer-review-processes/{id}', Routes\Courseware\PeerReview\ProcessesShow::class);
        $group->get('/courseware-peer-review-processes/{id}/peer-reviews', Routes\Courseware\PeerReview\ReviewsOfProcessesIndex::class);

        $group->patch('/courseware-peer-review-processes/{id}', Routes\Courseware\PeerReview\ProcessesUpdate::class);
        $group->delete('/courseware-peer-review-processes/{id}', Routes\Courseware\PeerReview\ProcessesDelete::class);

        $group->post('/courseware-peer-review-processes', Routes\Courseware\PeerReview\ProcessesCreate::class);

        $group->get('/courses/{id}/courseware-peer-reviews', Routes\Courseware\PeerReview\ReviewsIndex::class);
        $group->get('/courseware-tasks/{id}/peer-reviews', Routes\Courseware\PeerReview\ReviewsByTaskIndex::class);

        $group->get('/courseware-peer-reviews/{id}', Routes\Courseware\PeerReview\ReviewsShow::class);
        $group->post('/courseware-peer-reviews', Routes\Courseware\PeerReview\ReviewsCreate::class);
        $group->patch('/courseware-peer-reviews/{id}', Routes\Courseware\PeerReview\ReviewsUpdate::class);
        $group->delete('/courseware-peer-reviews/{id}', Routes\Courseware\PeerReview\ReviewsDelete::class);
    }

    private function addAuthenticatedDatafieldRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/datafields', Routes\Datafields\DatafieldsIndex::class);
        $group->get('/datafields/{id}', Routes\Datafields\DatafieldsShow::class);
    }

    private function addAuthenticatedFilesRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/terms-of-use', Routes\Files\TermsOfUseIndex::class);
        $group->get('/terms-of-use/{id}', Routes\Files\TermsOfUseShow::class);

        $group->get('/{type:courses|institutes|users}/{id}/file-refs', Routes\Files\RangeFileRefsIndex::class);
        $group->get('/{type:courses|institutes|users}/{id}/folders', Routes\Files\RangeFoldersIndex::class);

        $group->post('/{type:courses|institutes|users}/{id}/folders', Routes\Files\RangeFoldersCreate::class);

        $group->get('/file-refs/{id}', Routes\Files\FileRefsShow::class);
        $group->patch('/file-refs/{id}', Routes\Files\FileRefsUpdate::class);
        $group->delete('/file-refs/{id}', Routes\Files\FileRefsDelete::class);
        $this->addRelationship(
            $group,
            '/file-refs/{id}/relationships/terms-of-use',
            Routes\Files\Rel\TermsOfFileRef::class
        );

        $group->map(['HEAD'], '/file-refs/{id}/content', Routes\Files\FileRefsContentHead::class);
        $group->get('/file-refs/{id}/content', Routes\Files\FileRefsContentShow::class);
        $group->post('/file-refs/{id}/content', Routes\Files\FileRefsContentUpdate::class);

        $group->post('/file-refs/{id}/annotations', Routes\Files\FileRefsAnnotationCreate::class);

        $group->get('/folders/{id}', Routes\Files\FoldersShow::class);
        $group->patch('/folders/{id}', Routes\Files\FoldersUpdate::class);
        $group->delete('/folders/{id}', Routes\Files\FoldersDelete::class);

        // not a JSON route
        $group->post('/folders/{id}/copy', Routes\Files\FoldersCopy::class);

        $group->get('/folders/{id}/file-refs', Routes\Files\SubfilerefsIndex::class);
        $group->get('/folders/{id}/folders', Routes\Files\SubfoldersIndex::class);

        $group->post('/folders/{id}/file-refs', Routes\Files\NegotiateFileRefsCreate::class);
        $group->post('/folders/{id}/folders', Routes\Files\SubfoldersCreate::class);

        $group->get('/files/{id}', Routes\Files\FilesShow::class);
        $group->get('/files/{id}/file-refs', Routes\Files\FileRefsOfFilesShow::class);
        $group->get('/files/{id}/annotations', Routes\Files\FilesAnnotationsIndex::class);
        $this->addRelationship($group, '/files/{id}/relationships/file-refs', Routes\Files\Rel\FileRefsOfFile::class);
    }

    private function addAuthenticatedMessagesRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/users/{id}/inbox', Routes\Messages\InboxShow::class);

        $group->get('/users/{id}/outbox', Routes\Messages\OutboxShow::class);

        $group->post('/messages', Routes\Messages\MessageCreate::class);
        $group->get('/messages/{id}', Routes\Messages\MessageShow::class);
        $group->patch('/messages/{id}', Routes\Messages\MessageUpdate::class);
        $group->delete('/messages/{id}', Routes\Messages\MessageDelete::class);
    }

    private function addAuthenticatedForumRoutes(RouteCollectorProxy $group): void
    {
        $group->group('/courses/{range_id}', function ($forum) {
            $forum->get('/forum-configs', Routes\Forum\ConfigIndex::class);
            $forum->get('/forum-subscriptions', Routes\Forum\SubscriptionIndex::class);
        });

        $group->group('/forum-topics', function ($forum) {
            $forum->patch('/sort', Routes\Forum\TopicUpdateSort::class);
        });

        $group->group('/forum-categories', function ($forum) {
            $forum->patch('/sort', Routes\Forum\CategoryUpdateSort::class);
        });

        $group->group('/forum-subscriptions', function ($forum) {
            $forum->post('', Routes\Forum\SubscriptionStore::class);
            $forum->get('/{subscription_id}', Routes\Forum\SubscriptionShow::class);
            $forum->delete('/{subscription_id}', Routes\Forum\SubscriptionDelete::class);
        });

        $group->group('/forum-discussion-types', function ($forum) {
            $forum->get('', Routes\Forum\DiscussionTypeIndex::class);
            $forum->post('', Routes\Forum\DiscussionTypeStore::class);
            $forum->get('/{type_id}', Routes\Forum\DiscussionTypeShow::class);
            $forum->delete('/{type_id}', Routes\Forum\DiscussionTypeDelete::class);
        });

        $group->group('/forum-postings', function ($forum) {
            $forum->post('', Routes\Forum\PostingStore::class);
            $forum->get('/{posting_id}', Routes\Forum\PostingShow::class);
            $forum->patch('/{posting_id}', Routes\Forum\PostingUpdate::class);
            $forum->delete('/{posting_id}', Routes\Forum\PostingDelete::class);
        });

        $group->group('/forum-posting-reactions', function ($forum) {
            $forum->post('', Routes\Forum\PostingReactionStore::class);
            $forum->get('/{reaction_id}', Routes\Forum\PostingReactionShow::class);
            $forum->delete('/{reaction_id}', Routes\Forum\PostingReactionDelete::class);
        });
    }

    private function addUnAuthenticatedForumRoutes(RouteCollectorProxy $group): void
    {
        $group->group('/courses/{range_id}', function ($forum) {
            $forum->get('/forum-categories', Routes\Forum\CategoryIndex::class);
            $forum->get('/forum-discussions', Routes\Forum\DiscussionIndex::class);
            $forum->get('/forum-topics', Routes\Forum\TopicIndex::class);
        });

        $group->group('/forum-topics', function ($forum) {
            $forum->get('/{topic_id}', Routes\Forum\TopicShow::class);
            $forum->get('/{topic_id}/discussions', Routes\Forum\TopicDiscussions::class);
        });

        $group->group('/forum-categories', function ($forum) {
            $forum->get('/{category_id}', Routes\Forum\CategoryShow::class);
            $forum->get('/{category_id}/topics', Routes\Forum\CategoryTopics::class);
        });

        $group->group('/forum-discussions', function ($forum) {
            $forum->get('/{discussion_id}', Routes\Forum\DiscussionShow::class);
            $forum->get('/{discussion_id}/postings', Routes\Forum\DiscussionPostings::class);
        });

        $group->group('/forum-postings', function ($forum) {
            $forum->get('/{posting_id}/reactions', Routes\Forum\PostingReactions::class);
        });
    }

    private function addAuthenticatedStockImagesRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/stock-images', Routes\StockImages\StockImagesIndex::class);
        $group->post('/stock-images', Routes\StockImages\StockImagesCreate::class);
        $group->get('/stock-images/{id}', Routes\StockImages\StockImagesShow::class);
        $group->patch('/stock-images/{id}', Routes\StockImages\StockImagesUpdate::class);
        $group->delete('/stock-images/{id}', Routes\StockImages\StockImagesDelete::class);

        $group->post('/stock-images/{id}/blob', Routes\StockImages\StockImagesUpload::class);
        $group->post('/stock-images/zip', Routes\StockImages\StockImagesZipUpload::class);
    }

    private function addAuthenticatedThemesRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/studip-themes', Routes\Themes\ThemesIndex::class);
        $group->get('/studip-themes/{id}', Routes\Themes\ThemesShow::class);
        $group->post('/studip-themes', Routes\Themes\ThemesCreate::class);
        $group->patch('/studip-themes/{id}', Routes\Themes\ThemesUpdate::class);
        $group->delete('/studip-themes/{id}', Routes\Themes\ThemesDelete::class);
    }

    private function addAuthenticatedAvatarRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/{type:courses|institutes|users}/{id}/avatar', Routes\Avatar\AvatarOfRangeShow::class);
        $group->delete('/{type:courses|institutes|users}/{id}/avatar', Routes\Avatar\AvatarofRangeDelete::class);

        $group->post('/{type:courses|institutes|users}/{id}/avatar', Routes\Avatar\AvatarUpload::class);
    }

    private function addAuthenticatedUserFilterRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/user-filters/{id}', Routes\UserFilters\UserFiltersShow::class);
        $group->post('/user-filters', Routes\UserFilters\UserFiltersCreate::class);
        $group->patch('/user-filters/{id}', Routes\UserFilters\UserFiltersUpdate::class);
        $group->delete('/user-filters/{id}', Routes\UserFilters\UserFiltersDelete::class);
        $group->get('/user-filter-fields', Routes\UserFilters\UserFilterFieldsIndex::class);
        $group->get('/user-filter-fields/{id}', Routes\UserFilters\UserFilterFieldsShow::class);
    }

    private function addAuthenticatedMvvRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/courses-of-study', Routes\Mvv\CoursesOfStudyIndex::class);
        $group->get('/courses-of-study/{id}', Routes\Mvv\CoursesOfStudyShow::class);
        $group->get('/courses-of-study/{id}/components', Routes\Mvv\ComponentsByCoursesOfStudyIndex::class);
        $group->get('/course-of-study-components/{id}/versions', Routes\Mvv\VersionsByCourseOfStudyComponentsIndex::class);
        $group->get('/course-of-study-components/{id}/subject', Routes\Mvv\SubjectsByCourseOfStudyComponentsShow::class);
        $group->get('/courses-of-study/{id}/degree', Routes\Mvv\DegreesByCoursesOfStudyShow::class);
        $group->get('/degrees', Routes\Mvv\DegreesIndex::class);
        $group->get('/degrees/{id}', Routes\Mvv\DegreesShow::class);
        $group->get('/subjects',Routes\Mvv\SubjectsIndex::class);
        $group->get('/subjects/{id}',Routes\Mvv\SubjectsShow::class);
        $group->get('/component-versions/{id}', Routes\Mvv\ComponentVersionsShow::class);
        $group->get('/modules', Routes\Mvv\ModulesIndex::class);
        $group->get('/modules/{id}', Routes\Mvv\ModulesShow::class);
        $group->get('/modules/{id}/module-components', Routes\Mvv\ModuleComponentsByModuleIndex::class);
        $group->get('/module-components/{id}', Routes\Mvv\ModuleComponentsShow::class);
        // not a JSON:API route
        $group->get('/component-version-deep/{id}', Routes\Mvv\ComponentVersionsDeep::class);

    }

    private function addAuthenticatedShortUrlRoutes(RouteCollectorProxy $group): void
    {
        $group->get('/short-urls', Routes\ShortUrls\ShortUrlShow::class);
        $group->post('/short-urls', Routes\ShortUrls\ShortUrlCreate::class);
        $group->patch('/short-urls/{id}', Routes\ShortUrls\ShortUrlUpdate::class);
        $group->delete('/short-urls/{id}', Routes\ShortUrls\ShortUrlDelete::class);
    }

    private function addRelationship(RouteCollectorProxy $group, string $url, string $handler): void
    {
        $group->map(['GET', 'PATCH', 'POST', 'DELETE'], $url, $handler);
    }
}
