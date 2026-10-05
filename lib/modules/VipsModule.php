<?php
/*
 * VipsModule.php - Vips plugin class for Stud.IP
 * Copyright (c) 2007-2021  Elmar Ludwig
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

use Courseware\CoursewarePlugin;

/**
 * Vips plugin class for Stud.IP
 */
class VipsModule extends CorePlugin implements StudipModule, SystemPlugin, PrivacyPlugin, CoursewarePlugin
{
    public static ?bool $exam_mode = null;
    public static ?VipsModule $instance = null;
    public static ?Flexi\Factory $template_factory = null;

    public function __construct()
    {
        global $perm, $user;

        parent::__construct();

        self::$instance = $this;
        self::$template_factory = new Flexi\Factory($GLOBALS['STUDIP_BASE_PATH'] . '/app/views/vips');

        NotificationCenter::addObserver($this, 'userDidDelete', 'UserDidDelete');
        NotificationCenter::addObserver($this, 'courseDidDelete', 'CourseDidDelete');
        NotificationCenter::addObserver($this, 'userDidLeaveCourse', 'UserDidLeaveCourse');
        NotificationCenter::addObserver($this, 'userDidMigrate', 'UserDidMigrate');
        NotificationCenter::addObserver($this, 'statusgruppeUserDidCreate', 'StatusgruppeUserDidCreate');
        NotificationCenter::addObserver($this, 'statusgruppeUserDidDelete', 'StatusgruppeUserDidDelete');

        Exercise::addExerciseType(_('Single Choice'), SingleChoiceTask::class, ['choice-single', '']);
        Exercise::addExerciseType(_('Multiple Choice'), MultipleChoiceTask::class, 'choice-multiple');
        Exercise::addExerciseType(_('Multiple Choice Matrix'), MatrixChoiceTask::class, 'choice-matrix');
        Exercise::addExerciseType(_('Freie Antwort'), TextLineTask::class, 'text-line');
        Exercise::addExerciseType(_('Textaufgabe'), TextTask::class, 'text-area');
        Exercise::addExerciseType(_('Lückentext'), ClozeTask::class, ['cloze-input', 'cloze-select', 'cloze-drag']);
        Exercise::addExerciseType(_('Zuordnung'), MatchingTask::class, ['matching', 'matching-multiple']);
        Exercise::addExerciseType(_('Reihenfolge'), SequenceTask::class, 'sequence');

        if ($perm->have_perm('root')) {
            $nav_item = new Navigation(_('Klausuren'), 'dispatch.php/vips/config');
            Navigation::addItem('/admin/config/vips', $nav_item);
        }

        if (Navigation::hasItem('/contents')) {
            $nav_item = new Navigation(_('Aufgaben'));
            $nav_item->setImage(Icon::create('tasks'));
            $nav_item->setDescription(_('Erstellen und Verwalten von Aufgabenblättern'));
            Navigation::addItem('/contents/vips', $nav_item);

            $sub_item = new Navigation(_('Aufgabenblätter'), 'dispatch.php/vips/pool/assignments');
            $nav_item->addSubNavigation('assignments', $sub_item);

            $sub_item = new Navigation(_('Aufgaben'), 'dispatch.php/vips/pool/exercises');
            $nav_item->addSubNavigation('exercises', $sub_item);
        }

        // check for running exams
        if (Config::get()->VIPS_EXAM_RESTRICTIONS && !isset(self::$exam_mode)) {
            $courses = self::getCoursesWithRunningExams($user->id);
            self::$exam_mode = count($courses) > 0;

            if (self::$exam_mode) {
                $page = basename($_SERVER['PHP_SELF']);
                $path_info = Request::pathInfo();
                $course_id = Context::getId();

                // redirect page calls if necessary
                if (match_route('dispatch.php/jsupdater/get')) {
                    // always allow jsupdater calls
                    UpdateInformation::setInformation('vips', ['exam_mode' => true]);
                } else if (match_route('dispatch.php/logout')) {
                    // always allow logout
                    $_SESSION['logout_ticket'] = get_ticket();
                } else if (match_route('sendfile.php')) {
                    // always allow file downloads
                } else if (isset($course_id, $courses[$course_id])) {
                    $this->setupExamNavigation();

                    // course with running exam is selected, allow all exam actions
                    if (!match_route('dispatch.php/vips/sheets') && !match_route('dispatch.php/vips/sheets/*')) {
                        header('Location: ' . URLHelper::getURL('dispatch.php/vips/sheets'));
                        sess()->save();
                        die();
                    }
                } else if (count($courses) === 1) {
                    // only one course with running exam, redirect there
                    header('Location: ' . URLHelper::getURL('dispatch.php/vips/sheets', ['cid' => key($courses)]));
                    sess()->save();

                    die();
                } else if (!match_route('dispatch.php/vips/exam_mode')) {
                    // forward to overview of all running courses with exams
                    header('Location: ' . URLHelper::getURL('dispatch.php/vips/exam_mode'));
                    sess()->save();
                    die();
                }
            } else {
                PageLayout::addHeadElement(
                   'script',
                   [],
                   'STUDIP.JSUpdater.register("vips", () => location.reload());'
                );
            }
        }
    }

    /**
     * Return whether or not the current user has the given status in a course.
     *
     * @param string $status    status name: 'autor', 'tutor' or 'dozent'
     * @param string $course_id course to check
     */
    public static function hasStatus(string $status, string $course_id): bool
    {
        return $course_id && $GLOBALS['perm']->have_studip_perm($status, $course_id);
    }

    /**
     * Check whether or not the current user has the required status in a course.
     *
     * @param string $status    required status: 'autor', 'tutor' or 'dozent'
     * @param string $course_id course to check
     * @throws AccessDeniedException if the requirement is not met, an exception is thrown
     */
    public static function requireStatus(string $status, string $course_id): void
    {
        if (!VipsModule::hasStatus($status, $course_id)) {
            throw new AccessDeniedException(_('Sie verfügen nicht über die notwendigen Rechte für diese Aktion.'));
        }
    }

    /**
     * Checks whether or not the current user may view an assignment.
     *
     * @param VipsAssignment|null $assignment  assignment to check
     * @param int|null            $exercise_id check that this exercise is on the assignment (optional)
     * @throws AccessDeniedException If the current user doesn't have access, an exception is thrown
     */
    public static function requireViewPermission(?VipsAssignment $assignment, ?int $exercise_id = null): void
    {
        if (!$assignment || !$assignment->checkViewPermission()) {
            throw new AccessDeniedException(_('Sie haben keinen Zugriff auf dieses Aufgabenblatt!'));
        }

        if ($exercise_id && !$assignment->hasExercise($exercise_id)) {
            throw new AccessDeniedException(_('Sie haben keinen Zugriff auf diese Aufgabe!'));
        }
    }

    /**
     * Checks whether or not the current user may edit an assignment.
     *
     * @param VipsAssignment|null $assignment  assignment to check
     * @param int|null            $exercise_id check that this exercise is on the assignment (optional)
     * @throws AccessDeniedException If the current user doesn't have access, an exception is thrown
     */
    public static function requireEditPermission(?VipsAssignment $assignment, ?int $exercise_id = null): void
    {
        if (!$assignment || !$assignment->checkEditPermission()) {
            throw new AccessDeniedException(_('Sie haben keinen Zugriff auf dieses Aufgabenblatt!'));
        }

        if ($exercise_id && !$assignment->hasExercise($exercise_id)) {
            throw new AccessDeniedException(_('Sie haben keinen Zugriff auf diese Aufgabe!'));
        }
    }

    /**
     * Get all courses where the user is at least tutor and Vips is activated.
     *
     * @return array with all course ids, null if no courses
     */
    public static function getActiveCourses(string $user_id): array
    {
        $plugin_manager = PluginManager::getInstance();
        $vips_plugin_id = VipsModule::$instance->getPluginId();

        $sql = "JOIN seminar_user USING(Seminar_id)
                WHERE user_id = ? AND seminar_user.status IN ('dozent', 'tutor')
                ORDER BY (SELECT MIN(beginn) FROM semester_data
                          JOIN semester_courses USING(semester_id)
                          WHERE course_id = Seminar_id) DESC, Name";
        $courses = Course::findBySQL($sql, [$user_id]);

        // remove courses where Vips is not active
        foreach ($courses as $key => $course) {
            if (!$plugin_manager->isPluginActivated($vips_plugin_id, $course->id)) {
                unset($courses[$key]);
            }
        }

        return $courses;
    }

    /**
     * Get all courses with currently running exams for the given user.
     *
     * @param string $user_id The user id
     *
     * @return array    associative array of course ids and course names
     */
    public static function getCoursesWithRunningExams(string $user_id): array
    {
        $db = DBManager::get();

        $courses = [];

        $sql = "SELECT DISTINCT seminare.Seminar_id, seminare.Name, etask_assignments.id
                FROM etask_assignments
                JOIN seminar_user ON seminar_user.Seminar_id = etask_assignments.range_id
                JOIN seminare USING(Seminar_id)
                WHERE etask_assignments.type = 'exam'
                  AND etask_assignments.start <= UNIX_TIMESTAMP()
                  AND etask_assignments.end > UNIX_TIMESTAMP()
                  AND seminar_user.user_id = ?
                  AND seminar_user.status = 'autor'
                ORDER BY seminare.Name";
        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id]);

        foreach ($stmt as $row) {
            $assignment = VipsAssignment::find($row['id']);
            $ip_range = $assignment->options['ip_range'];

            if ($assignment->isVisible($user_id)) {
                if (strlen($ip_range) > 0 && $assignment->checkIPAccess($_SERVER['REMOTE_ADDR'])) {
                    $courses[$row['Seminar_id']] = $row['Name'];
                }
            }
        }

        return $courses;
    }

    public function setupExamNavigation()
    {
        Navigation::getItem('/start')->setURL('dispatch.php/vips/exam_mode');
        Navigation::addItem('/links/logout', new Navigation(_('Logout'), 'dispatch.php/logout'));

        Config::get()->PERSONAL_NOTIFICATIONS_ACTIVATED = 0;
        PageLayout::addStyle('#navigation-level-1, #navigation-level-2, #avatar-wrapper, #context-title { display: none; }');
        PageLayout::addCustomQuicksearch('<div style="height: 30px; width: 120px;"></div>');
    }

    public function getIconNavigation($course_id, $last_visit, $user_id)
    {
        if (VipsModule::hasStatus('tutor', $course_id)) {
            // find all uncorrected exercises in finished assignments in this course
            // Added JOIN with seminar_user to filter out lecturer/tutor solutions.
            $new_items = VipsSolution::countBySql(
                "JOIN etask_assignments ON etask_responses.assignment_id = etask_assignments.id
                 LEFT JOIN seminar_user
                   ON seminar_user.Seminar_id = etask_assignments.range_id
                      AND seminar_user.user_id = etask_responses.user_id
                 WHERE etask_assignments.range_id = ?
                   AND etask_assignments.type IN ('exam', 'practice', 'selftest')
                   AND etask_assignments.end <= UNIX_TIMESTAMP()
                   AND etask_responses.state = 0
                   AND IFNULL(seminar_user.status, 'autor') = 'autor'",
                [$course_id]
            );

            $message = ngettext('%d unkorrigierte Lösung', '%d unkorrigierte Lösungen', $new_items);
        } else {
            // find all active assignments not yet seen by the student
            $assignments = VipsAssignment::findBySQL(
                "LEFT JOIN etask_assignment_attempts
                   ON etask_assignment_attempts.assignment_id = etask_assignments.id
                      AND etask_assignment_attempts.user_id = ?
                 WHERE etask_assignments.range_id = ?
                   AND etask_assignments.type IN ('exam', 'practice', 'selftest')
                   AND etask_assignments.start <= UNIX_TIMESTAMP()
                   AND (etask_assignments.end IS NULL OR etask_assignments.end > UNIX_TIMESTAMP())
                   AND etask_assignment_attempts.user_id IS NULL",
                [$user_id, $course_id]
            );

            $new_items = 0;

            foreach ($assignments as $assignment) {
                if ($assignment->isVisible($user_id)) {
                    ++$new_items;
                }
            }

            $message = ngettext('%d neues Aufgabenblatt', '%d neue Aufgabenblätter', $new_items);
        }

        $overview_message = $this->getPluginName();
        $icon = Icon::create('tasks');

        if ($new_items > 0) {
            $overview_message = sprintf($message, $new_items);
            $icon = Icon::create('tasks', Icon::ROLE_NEW);
        }

        $icon_navigation = new Navigation($this->getPluginName(), 'dispatch.php/vips/sheets');
        $icon_navigation->setImage($icon->copyWithAttributes(['title' => $overview_message]));

        return $icon_navigation;
    }

    public function getInfoTemplate($course_id)
    {
        return null;
    }

    public function getTabNavigation($course_id)
    {
        $navigation = new Navigation($this->getPluginName());
        $navigation->setImage(Icon::create('tasks'));

        $nav_item = new Navigation(_('Aufgabenblätter'), 'dispatch.php/vips/sheets');
        $navigation->addSubNavigation('sheets', $nav_item);

        if (!self::$exam_mode) {
            $nav_item = new Navigation(_('Ergebnisse'), 'dispatch.php/vips/solutions');
            $navigation->addSubNavigation('solutions', $nav_item);
        }

        return ['vips' => $navigation];
    }

    public function getMetadata()
    {
        $metadata['category'] = _('Inhalte und Aufgabenstellungen');
        $metadata['displayname'] = _('Aufgaben und Prüfungen');
        $metadata['summary'] =
            _('Erstellung und Durchführung von Übungen, Tests und Klausuren');
        $metadata['description'] =
            _('Mit diesem Werkzeug können Übungen, Tests und Klausuren online vorbereitet und durchgeführt werden. ' .
                  'Die Lehrenden erhalten eine Übersicht darüber, welche Teilnehmenden eine Übung oder einen ' .
                  'Test mit welchem Ergebnis abgeschlossen haben. Im Gegensatz zu herkömmlichen Übungszetteln ' .
                  'oder Klausurbögen sind in Stud.IP alle Texte gut lesbar und sortiert abgelegt. Lehrende ' .
                  'erhalten sofort einen Überblick darüber, was noch zu korrigieren ist. Neben allgemein ' .
                  'üblichen Fragetypen wie Multiple Choice und Freitextantwort verfügt das Werkzeug auch über ' .
                  'ungewöhnlichere, aber didaktisch durchaus sinnvolle Fragetypen wie Lückentext und Zuordnung.');
        $metadata['keywords'] =
            _('Einsatz bei Hausaufgaben und Präsenzprüfungen; Reduzierter Arbeitsaufwand bei der Auswertung; ' .
                  'Sortierte Übersicht der eingereichten Ergebnisse; Single-, Multiple-Choice- und Textaufgaben, ' .
                  'Lückentexte und Zuordnungen; Notwendige Korrekturen und erzielte Punktzahlen auf einen Blick');
        $metadata['icon'] = Icon::create('tasks');
        $metadata['screenshots'] = [
            'path' => 'assets/images/plus/screenshots/Vips',
            'pictures' => [
                0 => ['source' => 'Vips.jpg', 'title' => _('Aufgaben und Prüfungen')],
                1 => ['source' => 'Vips_Aufgaben.jpg', 'title' => _('Aufgabenübersicht')],
                2 => ['source' => 'Vips_Aufgaben_Typen.jpg', 'title' => _('Aufgaben-Typen')],
                3 => ['source' => 'Vips_Aufgabe_erstellen.jpg', 'title' => _('Aufgabe erstellen')],
                4 => ['source' => 'Vips_Aufgaben_Ergebnisse.jpg', 'title' => _('Ergebnisübersicht')]
            ]
        ];

        return $metadata;
    }

    public function userDidDelete($event, $user)
    {
        // delete all personal assignments
        VipsAssignment::deleteBySQL('range_id = ?', [$user->id]);

        // delete in etask_responses
        VipsSolution::deleteBySQL('user_id = ?', [$user->id]);

        // delete start times and group memberships
        VipsAssignmentAttempt::deleteBySQL('user_id = ?', [$user->id]);
        VipsGroupMember::deleteBySQL('user_id = ?', [$user->id]);
    }

    public function courseDidDelete($event, $course)
    {
        // delete all assignments in course
        VipsAssignment::deleteBySQL('range_id = ? AND type IN (?)', [$course->id, ['exam', 'practice', 'selftest']]);

        // delete other course related info
        VipsBlock::deleteBySQL('range_id = ?', [$course->id]);
    }

    public function userDidLeaveCourse($event, $course_id, $user_id)
    {
        // terminate group membership when leaving a course
        $group_member = VipsGroupMember::findOneBySQL(
            'JOIN statusgruppen ON statusgruppe_id = group_id WHERE range_id = ? AND user_id = ? AND end IS NULL',
            [$course_id, $user_id]
        );

        if ($group_member) {
            $group_member->end = time();
            $group_member->store();
        }
    }

    public function userDidMigrate($event, $user_id, $new_id)
    {
        $db = DBManager::get();

        $db->execute('UPDATE IGNORE etask_assignment_attempts SET user_id = ? WHERE user_id = ?', [$new_id, $user_id]);
        $db->execute('UPDATE etask_tasks SET user_id = ? WHERE user_id = ?', [$new_id, $user_id]);

        $db->execute('UPDATE IGNORE etask_responses SET user_id = ? WHERE user_id = ?', [$new_id, $user_id]);
        $db->execute('UPDATE etask_tests SET user_id = ? WHERE user_id = ?', [$new_id, $user_id]);
    }

    public function statusgruppeUserDidCreate($event, $statusgruppe_user)
    {
        VipsGroupMember::create([
            'group_id' => $statusgruppe_user->statusgruppe_id,
            'user_id'  => $statusgruppe_user->user_id,
            'start'    => time()
        ]);
    }

    public function statusgruppeUserDidDelete($event, $statusgruppe_user)
    {
        $member = VipsGroupMember::findOneBySQL(
            'group_id = ? AND user_id = ? AND end IS NULL',
            [$statusgruppe_user->statusgruppe_id, $statusgruppe_user->user_id]
        );

        if ($member) {
            $member->end = time();
            $member->store();
        }
    }

    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $store object to store data into
     */
    public function exportUserData(StoredUserData $store)
    {
        $db = DBManager::get();

        $data = $db->fetchAll('SELECT * FROM etask_group_members WHERE user_id = ?', [$store->user_id]);
        $store->addTabularData(_('Aufgaben-Gruppenzuordnung'), 'etask_group_members', $data);
    }

    /**
     * Implement this method to register more block types.
     *
     * You get the current list of block types and return an updated list
     * containing your own block types.
     */
    public function registerBlockTypes(array $otherBlockTypes): array
    {
        $otherBlockTypes[] = Courseware\BlockTypes\TestBlock::class;

        return $otherBlockTypes;
    }

    /**
     * Implement this method to register more container types.
     *
     * You get the current list of container types and return an updated list
     * containing your own container types.
     */
    public function registerContainerTypes(array $otherContainerTypes): array
    {
        return $otherContainerTypes;
    }
}
