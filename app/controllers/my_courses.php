<?php
/**
 * my_courses.php - Controller for user and seminar related
 * pages under "Meine Veranstaltungen"
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 * @author    Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @author    David Siegfried <david@ds-labs.de>
 * @license   http://www.gnu.org/licenses/gpl-2.0.html GPL version 2 or later
 * @category  Stud.IP
 * @since     3.1
 */

use DI\Attribute\Inject;

require_once 'lib/object.inc.php';

class MyCoursesController extends AuthenticatedController
{
    #[Inject]
    private readonly MyCoursesHelper $helper;

    private ?Closure $performance_timer = null;

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if ($GLOBALS['perm']->have_perm('admin')) {
            $this->redirect('admin/courses/index');
            return;
        }

        // we are defintely not in an lecture or institute
        closeObject();
        $_SESSION['links_admin_data'] = '';

        // measure performance of #index_action
        if ($action === 'index') {
            $this->performance_timer = Metrics::startTimer();
        }
    }

    public function after_filter($action, $args)
    {
        parent::after_filter($action, $args);

        // send performance metric
        if (isset($this->performance_timer)) {
            $timer = $this->performance_timer;
            $timer('core.my_courses_time');
        }
    }

    /**
     * Autor / Tutor / Teacher action
     */
    public function index_action()
    {
        if ($GLOBALS['perm']->have_perm('root')) {
            throw new AccessDeniedException();
        }

        if ($GLOBALS['perm']->have_perm('admin')) {
            $this->redirect('my_courses/admin');
            return;
        }

        Navigation::activateItem('/browse/my_courses/list');
        PageLayout::setHelpKeyword('Basis.MeineVeranstaltungen');
        PageLayout::setTitle(_('Meine Veranstaltungen'));

        $sem_key     = $this->getSemesterKey();
        $group_field = $this->getGroupField();

        $sem_courses  = $this->helper->getCourses($sem_key, $group_field);

        // Waiting list
        $this->waiting_list = MyRealmModel::getWaitingList($GLOBALS['user']->id);

        // Deputies
        $this->my_bosses                   = Config::get()->DEPUTIES_DEFAULTENTRY_ENABLE ? Deputy::findDeputyBosses() : [];
        $this->deputies_edit_about_enabled = Config::get()->DEPUTIES_EDIT_ABOUT_ENABLE;

        // Check for new contents
        $tabularasa = $this->flash['tabularasa'];
        if ($tabularasa) {
            $details = [];
            if ($this->check_for_new($sem_courses, $group_field)) {
                $details[] = sprintf(
                    _('Seit Ihrem letzten Seitenaufruf (%s) sind allerdings neue Inhalte hinzugekommen.'),
                    reltime($tabularasa)
                );
            }

            PageLayout::postSuccess(_('Alles als gelesen markiert!'), $details);
        }

        $this->setupSidebar($sem_key, $group_field, $this->check_for_new($sem_courses, $group_field));

        $this->vueApp = Studip\VueApp::create('my-courses/MyCourses')
            ->withVuexStore(
                'MyCoursesStore',
                'mycourses',
                $this->helper->getVueAppData($sem_courses, $group_field)
            );
    }

    /**
     * PDF export of course overview
     */
    public function courseexport_action()
    {
        if ($GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }

        $template = $this->get_template_factory()->open('my_courses/courseexport');
        $template->sem_courses = MyRealmModel::getPreparedCourses('', [
            'group_field'         => 'sem_number',
            'order_by'            => null,
            'order'               => 'asc',
            'studygroups_enabled' => Config::get()->MY_COURSES_ENABLE_STUDYGROUPS,
            'deputies_enabled'    => Config::get()->DEPUTIES_ENABLE,
        ]);
        $template->sem_data = Semester::getAllAsArray();
        $template->with_modules = Request::bool('modules');
        $template->image_style = 'height: 6px; width: 8px;';

        $doc = new ExportPDF();
        $doc->addPage();
        $doc->SetFont('helvetica', '', 10);
        $doc->writeHTML($template->render(), false, false, true);

        $this->render_pdf($doc, 'courseexport.pdf');
    }

    /**
     * Seminar group administration - cluster your seminars by colors or
     * change grouping mechanism
     */
    public function groups_action($sem = null, $studygroups = false)
    {
        if ($GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }

        $this->title = _('Meine Veranstaltungen') . ' - ' . _('Farbgruppierungen');

        PageLayout::setTitle($this->title);

        PageLayout::setHelpKeyword('Basis.VeranstaltungenOrdnen');
        Navigation::activateItem('/browse/my_courses/list');

        $this->current_semester = $sem ?: Semester::findCurrent()->semester_id;
        $this->semesters = Semester::findAllVisible();

        $this->render_vue_app(
            Studip\VueApp::create('my-courses/ColorGroupSelector')
                ->withProps([
                    'store-url' => $this->store_groupsURL($studygroups),
                    'cid'       => Request::get('option', ''),
                ])
                ->withVuexStore(
                    'MyCoursesStore',
                    'mycourses',
                    $this->helper->createVueAppData(
                        sem_key: '',
                        studygroups: $studygroups
                    ),
                )
        );
    }

    /**
     * Storage function for the groups action.
     * Stores selected grouping category and actual group settings.
     */
    public function store_groups_action($studygroups = false)
    {
        if ($GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }

        $deputies_enabled = Config::get()->DEPUTIES_ENABLE;
        $GLOBALS['user']->cfg->store(
            'MY_COURSES_GROUPING',
            Request::get('select_group_field', $GLOBALS['user']->cfg->MY_COURSES_GROUPING)
        );
        $gruppe = Request::getArray('gruppe');

        if (count($gruppe) > 0) {
            CourseMember::findEachBySQL(
                function (CourseMember $member) use (&$gruppe) {
                    $member->gruppe = $gruppe[$member->seminar_id];
                    $member->store();

                    unset($gruppe[$member->seminar_id]);
                },
                'user_id = ? AND Seminar_id IN (?)',
                [
                    User::findCurrent()->id,
                    array_keys($gruppe)
                ]
            );

            if (count($gruppe) > 0 && $deputies_enabled) {
                Deputy::findEachBySQL(
                    function (Deputy $deputy) use ($gruppe) {
                        $deputy->gruppe = $gruppe[$deputy->range_id];
                        $deputy->store();
                    },
                    'user_id = ? AND range_id IN (?)',
                    [
                        User::findCurrent()->id,
                        array_keys($gruppe),
                    ]
                );
            }
        }

        if (Request::get('cid')) {
            $redirect = "course/overview?cid=" . Request::get('cid');
        } else {
            $redirect = 'my_courses/index';
        }

        $this->redirect($studygroups ? 'my_studygroups/index' : $redirect);
    }


    /**
     * @param string $type
     * @param string $sem
     */
    public function tabularasa_action($sem = '', $timestamp = null)
    {
        NotificationCenter::postNotification('OverviewWillClear', $GLOBALS['user']->id);

        $timestamp        = $timestamp ?: time();
        $deputies_enabled = Config::get()->DEPUTIES_ENABLE;

        $semesters   = MyRealmModel::getSelectedSemesters($sem);
        $min_sem_key = min($semesters);
        $max_sem_key = max($semesters);
        $courses     = MyRealmModel::getCourses($min_sem_key, $max_sem_key, [
            'deputies_enabled' => $deputies_enabled,
            'exactly'          => $semesters,
        ]);
        foreach ($courses as $index => $course) {
            MyRealmModel::setObjectVisits($course, $GLOBALS['user']->id, $timestamp);
        }

        NotificationCenter::postNotification('OverviewDidClear', $GLOBALS['user']->id);

        $this->flash['tabularasa'] = $timestamp;
        $this->redirect('my_courses/index');
    }


    /**
     * This action display only a message
     */
    public function decline_binding_action()
    {
        if ($GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }
        PageLayout::postError(_('Die Anmeldung ist verbindlich. Bitte wenden Sie sich an die Lehrenden.'));
        $this->redirect('my_courses/index');
    }

    /**
     * This action remove a user from course
     * @param $course_id
     */
    public function decline_action($course_id, $waiting = null)
    {
        $course = Course::find($course_id);
        $ticket_check    = check_ticket(Request::option('studipticket'));
        if (LockRules::Check($course_id, 'participants')) {
            $lockdata = LockRules::getObjectRule($course_id);
            PageLayout::postError(sprintf(
                _('Sie können sich nicht von der Veranstaltung <b>%s</b> abmelden.'),
                htmlReady($course->name)
            ));
            if ($lockdata['description']) {
                PageLayout::postInfo(formatLinks($lockdata['description']));
            }
            $this->redirect('my_courses/index');
            return;
        }

        // Ensure last teacher cannot leave course
        $teacher = $course->members->findOneBy('user_id', User::findCurrent()->id);
        if (
            $teacher
            && $teacher->status === 'dozent'
            && count($course->getMembersWithStatus('dozent')) === 1
        ) {
            PageLayout::postError(sprintf(
                _('Sie können sich nicht von der Veranstaltung <b>%s</b> abmelden.'),
                htmlReady($course->name)
            ));
            $this->redirect('my_courses/index');
            return;
        }

        if (Request::option('cmd') == 'back') {
            $this->redirect('my_courses/index');
            return;
        }

        if (Request::option('cmd') != 'kill' && Request::option('cmd') != 'kill_admission') {
            if (
                $course->admission_binding
                && Request::get('cmd') != 'suppose_to_kill_admission'
                && !LockRules::Check($course->id, 'participants')
            ) {
                PageLayout::postError(sprintf(_("Die Veranstaltung <b>%s</b> ist als <b>bindend</b> angelegt.
                    Wenn Sie sich abmelden wollen, müssen Sie sich an die Lehrende der Veranstaltung wenden."),
                    htmlReady($course->name)));
                $this->redirect('my_courses/index');
                return;
            }

            if (Request::get('cmd') == 'suppose_to_kill') {
                // check course admission
                $admission_end_time = $course->getAdmissionTimeFrame()['end_time'] ?? null;

                $admission_enabled = $course->isAdmissionEnabled();
                $admission_locked   = $course->isAdmissionLocked();

                if ($admission_enabled || $admission_locked || (int) $course->admission_prelim === 1) {
                    $message = sprintf(
                        _('Wollen Sie sich von der teilnahmebeschränkten Veranstaltung "%s" wirklich abmelden? Sie verlieren damit die Berechtigung für die Veranstaltung und müssen sich ggf. neu anmelden!'),
                        htmlReady($course->name)
                    );
                } else if (isset($admission_end_time) && $admission_end_time < time()) {
                    $message = sprintf(
                        _('Wollen Sie sich von der teilnahmebeschränkten Veranstaltung "%s" wirklich abmelden? Der Anmeldezeitraum ist abgelaufen und Sie können sich nicht wieder anmelden!'),
                        htmlReady($course->name)
                    );
                } else {
                    $message = sprintf(_('Wollen Sie sich von der Veranstaltung "%s" wirklich abmelden?'), htmlReady($course->name));
                }
                $cmd = 'kill';
            } else {
                if (AdmissionApplication::checkMemberPosition($GLOBALS['user']->id, $course_id) === false) {
                    $message = sprintf(
                        _('Wollen Sie sich von der Anmeldeliste der Veranstaltung "%s" wirklich abmelden?'),
                        htmlReady($course->name)
                    );
                } else {
                    $message = sprintf(
                        _('Wollen Sie sich von der Warteliste der Veranstaltung "%s" wirklich abmelden? Sie verlieren damit die bereits erreichte Position und müssen sich ggf. neu anmelden!'),
                        htmlReady($course->name)
                    );
                }
                $cmd = 'kill_admission';
            }

            PageLayout::postQuestion(
                $message,
                $this->declineURL($course_id, ['cmd' => $cmd, 'studipticket' => get_ticket()]),
                $this->declineURL($course_id, ['cmd' => 'back', 'studipticket' => get_ticket()])
            );
            $this->redirect('my_courses/index');
            return;
        } else {
            if (!LockRules::Check($course_id, 'participants') && $ticket_check && Request::option('cmd') != 'back' && Request::get('cmd') != 'kill_admission') {
                if (CourseMember::deleteBySQL('user_id = ? AND Seminar_id = ?', [$GLOBALS['user']->id, $course_id]) === 0) {
                    PageLayout::postError(
                        _('In der ausgewählten Veranstaltung wurde die gesuchten Personen nicht gefunden und konnte daher nicht ausgetragen werden.')
                    );
                } else {
                    // LOGGING
                    StudipLog::log('SEM_USER_DEL', $course_id, $GLOBALS['user']->id, 'Hat sich selbst ausgetragen');
                    // enable others to do something after the user has been deleted
                    NotificationCenter::postNotification('UserDidLeaveCourse', $course_id, $GLOBALS['user']->id);

                    // Delete course related datafield entries
                    DatafieldEntryModel::deleteBySQL('range_id = ? AND sec_range_id = ?', [$GLOBALS['user']->id, $course_id]);

                    // Delete from statusgroups
                    foreach (Statusgruppen::findBySeminar_id($course_id) as $group) {
                        $group->removeUser($GLOBALS['user']->id, true);
                    }

                    // Are successor available
                    AdmissionApplication::addMembers($course_id);

                    // If this course is a child of another course...
                    if ($course->parent) {
                        // ... check if user is member in another sibling ...
                        $other = CourseMember::findBySQL(
                            "`user_id` = :user AND `Seminar_id` IN (:courses) AND `Seminar_id` != :this",
                            [
                                'user' => $GLOBALS['user']->id,
                                'courses' => $course->parent->children->pluck('seminar_id'),
                                'this' => $course_id
                            ]
                        );

                        // ... and delete from parent course if this was the only
                        // course membership in this family.
                        if (count($other) == 0) {
                            $m = CourseMember::find([$course->parent_course, $GLOBALS['user']->id]);
                            if ($m) {
                                $m->delete();
                            }
                        }
                    }

                    PageLayout::postSuccess(sprintf(
                        _("Erfolgreich von Veranstaltung <b>%s</b> abgemeldet."),
                        htmlReady($course->name)
                    ));
                }
            } else {
                $prio_delete = false;
                // LOGGING
                StudipLog::log('SEM_USER_DEL', $course_id, $GLOBALS['user']->id, 'Hat sich selbst aus der Warteliste ausgetragen');
                if ($course->isAdmissionEnabled()) {
                    $prio_delete = AdmissionPriority::unsetPriority($course->getCourseSet()->getId(), $GLOBALS['user']->id, $course_id);
                }
                NotificationCenter::postNotification('UserDidLeaveWaitingList', $course_id, $GLOBALS['user']->id);
                $deleted = AdmissionApplication::deleteBySQL(
                    'user_id = ? AND seminar_id = ?',
                    [$GLOBALS['user']->id, $course_id]
                );
                if ($deleted || $prio_delete) {
                    //Warteliste neu sortieren
                    AdmissionApplication::renumberAdmission($course_id);
                    //Pruefen, ob es Nachruecker gibt
                    AdmissionApplication::addMembers($course_id);
                    PageLayout::postSuccess(sprintf(
                        _("Der Eintrag in der Anmelde- bzw. Warteliste der Veranstaltung <b>%s</b> wurde aufgehoben. Wenn Sie an der Veranstaltung teilnehmen wollen, müssen Sie sich erneut bewerben."),
                        htmlReady($course->name)
                    ));
                }
            }
            $this->redirect(Request::option('from') === 'studygroups' ? 'my_studygroups' : 'my_courses/index');
        }
    }


    /**
     * Overview for achived courses
     */
    public function archive_action()
    {
        if ($GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }

        PageLayout::setTitle(_('Meine archivierten Veranstaltungen'));
        PageLayout::setHelpKeyword('Basis.MeinArchiv');
        Navigation::activateItem('/browse/my_courses/archive');

        if (Config::get()->ENABLE_ARCHIVE_SEARCH) {
            $actions = Sidebar::get()->addWidget(new ActionsWidget());
            $actions->addLink(
                _('Suche im Archiv'),
                URLHelper::getURL('dispatch.php/search/archive'),
                Icon::create('search')
            );
        }

        $sortby = Request::option('sortby', 'name');

        $query = "SELECT semester, name, seminar_id, status,
                         archiv_file_id, archiv_protected_file_id,
                         LENGTH(forumdump) > 0 AS forumdump, # Test for existence
                         LENGTH(wikidump) > 0 AS wikidump    # Test for existence
                  FROM archiv_user
                  LEFT JOIN archiv USING (seminar_id)
                  WHERE user_id = :user_id
                  GROUP BY seminar_id
                  ORDER BY mkdate DESC, :sortby";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':user_id', $GLOBALS['user']->id);
        $statement->bindValue(':sortby', $sortby, StudipPDO::PARAM_COLUMN);
        $statement->execute();
        $this->seminars = $statement->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC); // Groups by semester
    }

    /**
     * Checks the whole course selection deppending on grouping eneabled or not
     * @param $my_obj
     * @param string $group_field
     * @return bool
     */
    public function check_for_new($my_obj, $group_field = 'sem_number')
    {
        if (empty($my_obj)) {
            return false;
        }

        foreach ($my_obj as $courses) {

            if (is_array($courses)) {
                if ($group_field !== 'sem_number') {
                    // tlx: If array is 2-dimensional, merge it into a 1-dimensional
                    $courses = array_merge(...array_values($courses));
                }

                foreach ($courses as $course) {
                    if ($this->check_course($course)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }



    /**
     * Set the selected semester and redirects to index
     * @param null $sem
     */
    public function set_semester_action()
    {
        $sem = Request::option('sem_select');

        if (!is_null($sem)) {
            $GLOBALS['user']->cfg->store('MY_COURSES_SELECTED_CYCLE', $sem);
            PageLayout::postSuccess(
                _('Das gewünschte Semester bzw. die gewünschte Semester-Filteroption wurde ausgewählt!')
            );
        }

        $this->redirect('my_courses/index');
    }

    /**
     * Checks the selected courses for news (e.g. forum posts,...)
     * Returns true if something new happens and enables the reset function
     * @param $seminar_content
     * @return bool
     */
    public function check_course($seminar_content)
    {
        $last_modified_timestamp = $seminar_content['last_modified'] ?? 0;
        if ($seminar_content['visitdate'] <= $seminar_content['chdate'] || $last_modified_timestamp > 0) {
            $last_modified = $seminar_content['visitdate'] <= $seminar_content['chdate']
            && $seminar_content['chdate'] > $last_modified_timestamp
                ? $seminar_content['chdate']
                : $last_modified_timestamp;
            if ($last_modified) {
                return true;
            }
        }

        $plugins_navigation = $seminar_content['navigation'];

        foreach ($plugins_navigation as $navigation) {
            if ($navigation && $navigation->isVisible(true) && $navigation->hasBadgeNumber()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove yourself as default deputy of the given boss.
     * @param $boss_id
     */
    public function delete_boss_action($boss_id)
    {
        $deputy = Deputy::find([$boss_id, $GLOBALS['user']->id]);
        $boss = $deputy->boss;
        if ($deputy && $deputy->delete()) {
            PageLayout::postSuccess(sprintf(
                _('Sie wurden als Standardvertretung von %s entfernt.'),
                htmlReady($boss->getFullName())
            ));
        } else {
            PageLayout::postError(sprintf(
                _('Sie konnten nicht als Standardvertretung von %s entfernt werden.'),
                htmlReady($boss->getFullName())
            ));
        }
        $this->redirect($this->url_for('my_courses'));
    }

    /**
     * Get the data array for presenting the course list in the portal widget.
     */
    public function getPortalWidgetData()
    {
        $sem_key     = $this->getSemesterKey();
        $group_field = $this->getGroupField();

        $sem_courses  = $this->helper->getCourses($sem_key, $group_field);

        return $this->helper->getVueAppData($sem_courses, $group_field);
    }

    /**
     * Get widget for grouping selected courses (e.g. by colors, ...)
     *
     * @param string $group_field
     */
    private function setGroupingSelector($group_field)
    {
        $groups  = [
            'sem_number'  => _('Standard'),
            'sem_tree_id' => _('Studienbereich'),
            'sem_status'  => _('Typ'),
            'gruppe'      => _('Farbgruppen'),
            'dozent_id'   => _('Lehrende'),
        ];

        if (LvgruppeSeminar::countBySql('1') > 0) {
            $groups['mvv'] = _('Modul');
        }

        $views = Sidebar::get()->addWidget(new ViewsWidget());
        $views->setTitle(_('Gruppierung'));
        foreach ($groups as $key => $group) {
            $views->addLink(
                $group,
                $this->url_for('my_courses/store_groups', ['select_group_field' => $key])
            )->setActive($key === $group_field);
        }
    }

    /**
     * Returns a widget for semester selection
     * @param $sem
     */
    private function setSemesterWidget($sem)
    {
        $semesters = new SimpleCollection(Semester::getAll());
        $semesters = $semesters->orderBy('beginn desc');

        $sidebar = Sidebar::Get();

        $widget = new SelectWidget(_('Semesterfilter'), $this->url_for('my_courses/set_semester'), 'sem_select');
        $widget->setMaxLength(50);
        foreach ($this->getTextualSemesterEntries() as $key => $label) {
            $widget->addElement(new SelectElement($key, $label, $sem === $key));
        }

        $query = "SELECT semester_data.semester_id
                  FROM seminare
                  LEFT JOIN semester_courses ON (semester_courses.course_id = seminare.Seminar_id)
                  LEFT JOIN semester_data ON (semester_courses.semester_id = semester_data.semester_id)
                  LEFT JOIN seminar_user USING (Seminar_id)
                  WHERE seminar_user.user_id = ? AND seminare.status NOT IN (?)
                  GROUP BY semester_data.semester_id";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([
            $GLOBALS['user']->id,
            studygroup_sem_types(),
        ]);
        $courses = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($semesters)) {
            $group = new SelectGroupElement(_('Semester auswählen'));
            foreach ($semesters as $semester) {
                if ($semester->visible || in_array($semester->id,$courses)) {
                    $group->addElement(new SelectElement($semester->id, $semester->name, $sem === $semester->id));
                }
            }
            $widget->addElement($group);
        }
        $sidebar->addWidget($widget);
    }

    protected function setupSidebar($sem, $group_field, $new_contents)
    {
        // Get permission that allows creating courses
        $sem_create_perm = Config::get()->SEM_CREATE_PERM;
        if (!in_array($sem_create_perm, ['root', 'admin', 'dozent'])) {
            $sem_create_perm = 'dozent';
        }

        // create settings url depended on selected cycle
        if (isset($sem) && !$this->isValidTextualSemesterEntry($sem)) {
            $this->settings_url = "dispatch.php/my_courses/groups/{$sem}";
        } else {
            $this->settings_url = 'dispatch.php/my_courses/groups';
        }

        $sidebar = Sidebar::get();
        $this->setSemesterWidget($sem);

        $setting_widget = $sidebar->addWidget(new ActionsWidget());

        if ($new_contents) {
            $setting_widget->addLink(
                _('Alles als gelesen markieren'),
                $this->url_for("my_courses/tabularasa/{$sem}/" . time()),
                Icon::create('accept'),
                [
                    'data-confirm' => implode("\n", [
                        _('Sie sind dabei alle Veranstaltungen auf gelesen zu setzen.'),
                        _('Diesen Schritt können sie nicht rückgängig machen.')
                    ]),
                ]
            );
        }
        $setting_widget->addLink(
            _('Farbgruppierung ändern'),
            URLHelper::getURL($this->settings_url),
            Icon::create('group4')
        )->asDialog();

        if (Config::get()->MAIL_NOTIFICATION_ENABLE) {
            $setting_widget->addLink(
                _('Benachrichtigungen anpassen'),
                URLHelper::getURL('dispatch.php/settings/notification'),
                Icon::create('mail')
            );
        }

        if ($sem_create_perm === 'dozent' && $GLOBALS['perm']->have_perm('dozent')) {
            $setting_widget->addLink(
                _('Neue Veranstaltung anlegen'),
                URLHelper::getURL('dispatch.php/course/wizard'),
                Icon::create('add')
            )->asDialog();
        }

        $setting_widget->addLink(
            _('Veranstaltung hinzufügen'),
            URLHelper::getURL('dispatch.php/search/courses'),
            Icon::create('search')
        );
        if (Config::get()->STUDYGROUPS_ENABLE) {
            $setting_widget->addLink(
                _('Neue Studiengruppe anlegen'),
                URLHelper::getURL('dispatch.php/course/wizard', ['studygroup' => 1]),
                Icon::create('studygroup')
            )->asDialog();
        }

        $this->setGroupingSelector($group_field);

        $views = $sidebar->addWidget(new ViewsWidget());
        $views->id = 'tiled-courses-sidebar-switch';
        $views->addLink(
            _('Tabellarische Ansicht'),
            '#tabular'
        )->setActive(!$GLOBALS['user']->cfg->MY_COURSES_TILED_DISPLAY);
        $views->addLink(
            _('Kachelansicht'),
            '#tiles'
        )->setActive($GLOBALS['user']->cfg->MY_COURSES_TILED_DISPLAY);

        $options = $sidebar->addWidget(new OptionsWidget());
        $options->id = 'tiled-courses-new-contents-toggle';
        $options->addCheckbox(
            _('Nur neue Inhalte anzeigen'),
            $GLOBALS['user']->cfg->MY_COURSES_SHOW_NEW_ICONS_ONLY,
            '#'
        );

        $export_widget = $sidebar->addWidget(new ExportWidget());
        $export_widget->addLink(
            _('Veranstaltungsübersicht'),
            $this->url_for('my_courses/courseexport', ['modules' => '1']),
            Icon::create('export')
        );
        $export_widget->addLink(
            _('Veranstaltungsübersicht ohne Module'),
            $this->url_for('my_courses/courseexport'),
            Icon::create('export')
        );
    }

    private function getSemesterKey(): string
    {
        $config_sem = $GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE;
        if (!Config::get()->MY_COURSES_ENABLE_ALL_SEMESTERS && $config_sem === '') {
            $config_sem = 'future';
        }

        if (
            $config_sem
            && !$this->isValidTextualSemesterEntry($config_sem)
            && !Semester::exists($config_sem)
        ) {
            $config_sem = null;
        }

        if (!Config::get()->MY_COURSES_ENABLE_ALL_SEMESTERS && !$config_sem) {
            $config_sem = Config::get()->MY_COURSES_DEFAULT_CYCLE;
        }
        $sem = Request::get('sem_select', $config_sem);

        if ($sem && !$this->isValidTextualSemesterEntry($sem)) {
            Request::set('sem_select', $sem);
        }

        return $sem ?? Config::get()->getValue('MY_COURSES_DEFAULT_CYCLE');
    }

    private function getGroupField()
    {
        $group_field = $GLOBALS['user']->cfg->MY_COURSES_GROUPING;

        $forced_grouping = in_array(Config::get()->MY_COURSES_FORCE_GROUPING, $this->getValidGroupingFields())
                         ? Config::get()->MY_COURSES_FORCE_GROUPING
                         : 'sem_number';

        if ($forced_grouping === 'not_grouped') {
            $forced_grouping = 'sem_number';
        }

        if (!$group_field || !in_array($group_field, $this->getValidGroupingFields())) {
            $group_field = 'sem_number';
        }

        if ($group_field === 'sem_number' && $forced_grouping !== 'sem_number') {
            $group_field = $forced_grouping;
        }

        return $group_field === 'not_grouped' ? 'sem_number' : $group_field;
    }

    private function getValidGroupingFields(): array
    {
        $valid = [
            'not_grouped',
            'sem_number',
            'sem_tree_id',
            'sem_status',
            'gruppe',
            'dozent_id',
        ];

        if (LvgruppeSeminar::countBySql('1') > 0) {
            $valid[] = 'mvv';
        }

        return $valid;
    }

    /**
     * Returns all valid textual semester entries like 'last', 'future' etc
     *
     * @return array<string, string>
     */
    private function getTextualSemesterEntries(): array
    {
        $entries = [
            'current'     => _('Aktuelles Semester'),
            'future'      => _('Aktuelles und nächstes Semester'),
            'last'        => _('Aktuelles und letztes Semester'),
            'lastandnext' => _('Letztes, aktuelles, nächstes Semester'),
            'lastbutone'  => _('Aktuelles und vorletztes Semester'),
        ];

        if (Config::get()->MY_COURSES_ENABLE_ALL_SEMESTERS) {
            $entries[''] = _('Alle Semester');
        }

        return $entries;
    }

    /**
     * Returns whether the given entry is a valid textual semester entry.
     *
     * @see getTextualSemesterEntries()
     * @return bool
     */
    private function isValidTextualSemesterEntry(string $entry): bool
    {
        return array_key_exists($entry, $this->getTextualSemesterEntries());
    }
}
