<?php
/**
 * vips/sheets.php - course assignments controller
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Elmar Ludwig
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 */

class Vips_SheetsController extends AuthenticatedController
{
    /**
     * Return the default action and arguments
     *
     * @return an array containing the action, an array of args and the format
     */
    public function default_action_and_args()
    {
        return ['list_assignments', [], null];
    }

    /**
     * Callback function being called before an action is executed. If this
     * function does not return FALSE, the action will be called, otherwise
     * an error will be generated and processing will be aborted. If this function
     * already #rendered or #redirected, further processing of the action is
     * withheld.
     *
     * @param string  Name of the action to perform.
     * @param array   An array of arguments to the action.
     *
     * @return bool|void
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $course_id = Context::getId();

        if ($action === 'list_assignments' && !VipsModule::hasStatus('tutor', $course_id)) {
            $action = 'list_assignments_stud';
        }

        if ($action !== 'relay') {
            if (Context::getId()) {
                Navigation::activateItem('/course/vips/sheets');
            } else {
                Navigation::activateItem('/contents/vips/assignments');
                PageLayout::setTitle(_('Meine Aufgabenblätter'));
            }
            PageLayout::setHelpKeyword('Basis.Vips');
        }
    }

    #####################################
    #                                   #
    #          Student Methods          #
    #                                   #
    #####################################

    /**
     * Restores an archived solution as the current solution.
     */
    public function restore_solution_action()
    {
        // CSRFProtection::verifyUnsafeRequest();

        $exercise_id = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $solver_id = Request::option('solver_id', $GLOBALS['user']->id);

        VipsModule::requireViewPermission($assignment, $exercise_id);

        if (!$assignment->checkEditPermission()) {
            $solver_id = $GLOBALS['user']->id;
        }

        $solutions = $assignment->getArchivedUserSolutions($solver_id, $exercise_id);

        if ($assignment->checkAccess($solver_id) || $assignment->checkEditPermission()) {
            if ($assignment->type === 'exam' && $solutions) {
                $assignment->restoreSolution($solutions[0]);
                PageLayout::postSuccess(_('Die vorherige Lösung wurde wiederhergestellt.'));
            }
        }

        $this->redirect($this->url_for('vips/sheets/show_exercise', compact('assignment_id', 'exercise_id', 'solver_id')));
    }

    /**
     * Only possible if test is selftest: Delete the solution of a student for
     * a particular exercise.
     */
    public function delete_solution_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $exercise_id = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $solver_id = Request::option('solver_id', $GLOBALS['user']->id);

        VipsModule::requireViewPermission($assignment, $exercise_id);

        if (!$assignment->checkEditPermission()) {
            $solver_id = $GLOBALS['user']->id;
        }

        if ($assignment->checkAccess($solver_id) || $assignment->checkEditPermission()) {
            if ($assignment->isResetAllowed() || $assignment->type === 'exam') {
                $assignment->deleteSolution($solver_id, $exercise_id);
                $undo_link = '';

                if ($assignment->type === 'exam' && !$assignment->isSelfAssessment()) {
                    $undo_link = sprintf(' <a href="%s">%s</a>',
                        $this->link_for('vips/sheets/restore_solution', compact('assignment_id', 'exercise_id', 'solver_id')),
                        _('Diese Aktion zurücknehmen.'));
                }

                PageLayout::postSuccess(_('Die Lösung wurde gelöscht.') . $undo_link);
            }
        }

        $this->redirect($this->url_for('vips/sheets/show_exercise', compact('assignment_id', 'exercise_id', 'solver_id')));
    }

    /**
     * Only possible if test is selftest: Deletes all the solutions of a student or
     * the student's group to enable him/her to redo it.
     */
    public function delete_solutions_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $solver_id = Request::option('solver_id', $GLOBALS['user']->id);

        VipsModule::requireViewPermission($assignment);

        if (!$assignment->checkEditPermission()) {
            $solver_id = $GLOBALS['user']->id;
        }

        if ($assignment->isRunning() || $assignment->checkEditPermission()) {
            if ($assignment->isResetAllowed()) {
                $assignment->deleteSolutions($solver_id);
                PageLayout::postSuccess(_('Die Lösungen wurden gelöscht.'));
            }
        }

        $this->redirect($this->url_for('vips/sheets/show_assignment', compact('assignment_id', 'solver_id')));
    }

    /**
     * Only possible if test is exam: Begin working on the exam.
     */
    public function begin_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $terms_accepted = Request::int('terms_accepted');
        $access_code = Request::get('access_code');
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $ip_address = $_SERVER['REMOTE_ADDR'];

        VipsModule::requireViewPermission($assignment);

        if ($assignment->type === 'exam') {
            if (!$assignment->getAssignmentAttempt($GLOBALS['user']->id)) {
                $exam_terms = Config::get()->VIPS_EXAM_TERMS;
            }

            if (!$assignment->isRunning() || !$assignment->active) {
                PageLayout::postError(_('Das Aufgabenblatt kann zur Zeit nicht bearbeitet werden.'));
            } else if (!$assignment->checkIPAccess($ip_address)) {
                PageLayout::postError(sprintf(_('Sie haben mit Ihrer IP-Adresse &bdquo;%s&ldquo; keinen Zugriff!'), htmlReady($ip_address)));
            } else if ($exam_terms && !$terms_accepted) {
                PageLayout::postError(_('Ein Start der Klausur ist nur mit Bestätigung der Teilnahmebedingungen möglich.'));
            } else if (!$assignment->checkAccessCode($access_code)) {
                PageLayout::postError(_('Der eingegebene Zugangscode ist nicht korrekt.'));
            } else {
                $assignment->recordAssignmentAttempt($GLOBALS['user']->id);
            }
        }

        $this->redirect($this->url_for('vips/sheets/show_assignment', compact('assignment_id', 'access_code')));
    }

    /**
     * Only possible if test is exam: Immediately finish working on the exam.
     */
    public function finish_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireViewPermission($assignment);

        if ($assignment->checkAccess($GLOBALS['user']->id)) {
            if ($assignment->finishAssignmentAttempt($GLOBALS['user']->id)) {
                PageLayout::postSuccess(_('Das Aufgabenblatt wurde abgeschlossen, eine weitere Bearbeitung ist nicht mehr möglich.'));
            } else {
                PageLayout::postError(_('Eine Abgabe ist erst nach Start des Aufgabenblatts möglich.'));
            }
        }

        $this->redirect($this->url_for('vips/sheets/show_assignment', compact('assignment_id')));
    }

    /**
     * SHEETS/EXAMS
     *
     * Is called when the submit button at the bottom of an exercise is called.
     * If there is already a solution of this exercise by the same user or same group,
     * a dialog pops up to confirm the submission. On database-level: EVERY solution is stored
     * (even the unconfirmed ones), with the last solution being marked as last.
     */
    public function submit_exercise_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $exercise_id = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireViewPermission($assignment, $exercise_id);

        ##################################################################
        # in case student solution is submitted by tutor or lecturer     #
        # (can happen if the student submits his/her solution by email)  #
        ##################################################################

        $solver_id = Request::option('solver_id');

        if ($solver_id == '' || !$assignment->checkEditPermission()) {
            $solver_id = $GLOBALS['user']->id;
        }

        ############################
        # Checks before submission #
        ############################

        if (!$assignment->checkEditPermission()) {
            $end = $assignment->getUserEndTime($solver_id);

            // not yet started
            if (!$assignment->isStarted()) {
                PageLayout::postError(_('Das Aufgabenblatt wurde noch nicht gestartet.'));
                $this->redirect('vips/sheets/list_assignments_stud');
                return;
            }

            // already ended
            if ($end && time() - $end > 120) {
                PageLayout::postError(_('Das Aufgabenblatt wurde bereits beendet.'));
                $this->redirect('vips/sheets/list_assignments_stud');
                return;
            }

            if (!$assignment->checkIPAccess($_SERVER['REMOTE_ADDR']) || !$assignment->checkAccessCode()) {
                PageLayout::postError(_('Kein Zugriff möglich!'));
                $this->redirect('vips/sheets/list_assignments_stud');
                return;
            }

            $assignment->recordAssignmentAttempt($solver_id);
        }

        /* if an exercise has been submitted */
        if (Request::submitted('submit_exercise') || Request::int('forced')) {
            $request  = Request::getInstance();
            $exercise = Exercise::find($exercise_id);
            $solution = $exercise->getSolutionFromRequest($request, $_FILES);
            $solution->user_id = $solver_id;

            if ($solution->isEmpty()) {
                PageLayout::postWarning(_('Ihre Lösung ist leer und wurde nicht gespeichert.'));
            } else {
                $assignment->storeSolution($solution);

                PageLayout::postSuccess(sprintf(_('Ihre Lösung zur Aufgabe &bdquo;%s&ldquo; wurde gespeichert.'), htmlReady($exercise->title)));
            }
        }

        $this->redirect($this->url_for('vips/sheets/show_exercise', compact('assignment_id', 'exercise_id', 'solver_id')));
    }

    /**
     * SHEETS/EXAMS
     *
     * Displays an exercise (from student perspective)
     */
    public function show_exercise_action()
    {
        $exercise_id   = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $solver_id     = Request::option('solver_id');  // solver is handed over via address line, ie. user is a lecturer

        VipsModule::requireViewPermission($assignment, $exercise_id);

        if ($solver_id == '' || !$assignment->checkEditPermission()) {
            $solver_id = $GLOBALS['user']->id;
        }

        ##############################################################
        #    check for ip_address, remaining time and interrupted    #
        ##############################################################

        // restrict access for students!
        if (!$assignment->checkEditPermission()) {
            // the assignment is not accessible any more after it has run out
            if (!$assignment->checkAccess()) {
                PageLayout::postError(_('Das Aufgabenblatt kann zur Zeit nicht bearbeitet werden.'));
                $this->redirect('vips/sheets/list_assignments_stud');
                return;
            }

            if ($assignment->isFinished($solver_id)) {
                PageLayout::postError(_('Die Zeit ist leider abgelaufen!'));
                $this->redirect($this->url_for('vips/sheets/show_assignment', compact('assignment_id')));
                return;
            }

            // enter user start time the moment he/she first clicks on any exercise
            $assignment->recordAssignmentAttempt($solver_id);
        }

        // fetch exercise info, type, points
        $exercise_ref = VipsExerciseRef::find([$assignment->test_id, $exercise_id]);
        $exercise     = $exercise_ref->exercise;

        ###################################
        # get user solution if applicable #
        ###################################

        $solution = $assignment->getSolution($solver_id, $exercise_id);
        $max_tries = $assignment->getMaxTries();
        $max_points = $exercise_ref->points;
        $exercise_position = $exercise_ref->position;
        $show_solution = false;
        $tries_left = null;

        // if a solution has been submitted during a selftest
        if ($max_tries && $solution) {
            $tries_left = $max_tries - $solution->countTries();

            if ($solution->points == $max_points || !$solution->state || $solution->grader_id || $tries_left <= 0) {
                $show_solution = true;
            }
        }

        ##############################
        #   set template variables   #
        ##############################

        $this->assignment            = $assignment;
        $this->assignment_id         = $assignment_id;
        $this->exercise              = $exercise;
        $this->exercise_id           = $exercise_id;
        $this->exercise_position     = $exercise_position;

        $this->solver_id             = $solver_id;
        $this->solution              = $solution;  // can be empty
        $this->max_points            = $max_points;
        $this->show_solution         = $show_solution;
        $this->tries_left            = $tries_left;
        $this->user_end_time         = $assignment->getUserEndTime($solver_id);
        $this->remaining_time        = $this->user_end_time - time();

        $this->contentbar = $this->create_contentbar($assignment, $exercise_id, 'show', $solver_id);

        $widget = new ActionsWidget();

        if (($assignment->isResetAllowed() || $assignment->type === 'exam') && $solution) {
            $widget->addLink(
                _('Lösung dieser Aufgabe löschen'),
                $this->url_for('vips/sheets/delete_solution', compact('assignment_id', 'exercise_id', 'solver_id')),
                Icon::create('refresh'),
                ['data-confirm' => _('Wollen Sie die Lösung dieser Aufgabe wirklich löschen?')]
            )->asButton();
        }

        Sidebar::get()->addWidget($widget);

        if ($assignment->checkEditPermission()) {
            Helpbar::get()->addPlainText('',
                _('Dies ist die Studierendenansicht (Vorschau) der Aufgabe. Sie können hier auch Lösungen von Teilnehmenden ansehen oder für sie abgeben.'));

            $widget = new ViewsWidget();
            $widget->addLink(
                _('Aufgabe bearbeiten'),
                $this->url_for('vips/sheets/edit_exercise', ['assignment_id' => $assignment_id, 'exercise_id' => $exercise_id])
            );
            $widget->addLink(
                _('Studierendensicht (Vorschau)'),
                $this->url_for('vips/sheets/show_exercise', ['assignment_id' => $assignment_id, 'exercise_id' => $exercise_id])
            )->setActive();
            Sidebar::get()->addWidget($widget);

            if ($assignment->range_type === 'course') {
                $widget = new SelectWidget(_('Anzeigen für'), $this->url_for('vips/sheets/show_exercise', compact('assignment_id', 'exercise_id')), 'solver_id');
                $widget->class = 'nested-select';
                $element = new SelectElement($GLOBALS['user']->id, ' ', $GLOBALS['user']->id == $solver_id);
                $widget->addElement($element);

                foreach ($assignment->course->members->findBy('status', 'autor')->orderBy('nachname, vorname') as $member) {
                    if ($assignment->isVisible($member->user_id)) {
                        $element = new SelectElement($member->user_id, $member->nachname . ', ' . $member->vorname, $member->user_id == $solver_id);
                        $widget->addElement($element);
                    }
                }
                Sidebar::get()->addWidget($widget);
            }
        } else {
            Helpbar::get()->addPlainText('',
                _('Bitte denken Sie daran, vor dem Verlassen der Seite Ihre Lösung zu speichern.'));
        }

        $widget = new ViewsWidget();
        $widget->setTitle(_('Aufgabenblatt'));

        foreach ($assignment->getExerciseRefs($solver_id) as $i => $item) {
            $this->item = $item;
            $this->position = $i + 1;
            $element = new WidgetElement($this->render_template_as_string('vips/sheets/show_exercise_link'));
            $element->active = $item->task_id === $exercise->id;
            $widget->addElement($element, 'exercise-' . $item->task_id);
        }

        Sidebar::get()->addWidget($widget);
    }

    /**
     * Displays all running assignments "work-on ready" for students (view of
     * students when clicking on tab Uebungsblatt), respectively student view
     * for lecturers and tutors.
     */
    public function list_assignments_stud_action()
    {
        $course_id = Context::getId();
        $sort = Request::option('sort', 'start');
        $desc = Request::int('desc');
        VipsModule::requireStatus('autor', $course_id);

        $this->sort      = $sort;
        $this->desc      = $desc;
        $this->assignments = [];

        $assignments = VipsAssignment::findByRangeId($course_id);
        $blocks      = VipsBlock::findBySQL('range_id = ? ORDER BY name', [$course_id]);
        $blocks[]    = VipsBlock::build(['name' => _('Aufgabenblätter')]);
        $ip_address  = $_SERVER['REMOTE_ADDR'];

        usort($assignments, function($a, $b) use ($sort) {
            if ($sort === 'title') {
                return strcoll($a->test->title, $b->test->title);
            } else if ($sort === 'type') {
                return strcmp($a->type, $b->type);
            } else if ($sort === 'start') {
                return strcmp($a->start, $b->start);
            } else {
                return strcmp($a->end ?: '~', $b->end ?: '~');
            }
        });

        if ($desc) {
            $assignments = array_reverse($assignments);
        }

        foreach ($blocks as $block) {
            $this->blocks[$block->id]['title'] = $block->name;
        }

        foreach ($assignments as $assignment) {
            if ($assignment->isRunning() && $assignment->isVisible($GLOBALS['user']->id)) {
                if ($assignment->checkIPAccess($ip_address)) {
                    if (isset($assignment->block->group_id)) {
                        $this->blocks['']['assignments'][] = $assignment;
                    } else {
                        $this->blocks[$assignment->block_id]['assignments'][] = $assignment;
                    }
                }
            }
        }

        // delete empty blocks
        foreach ($blocks as $block) {
            if (empty($this->blocks[$block->id]['assignments'])) {
                unset($this->blocks[$block->id]);
            }
        }

        $this->user_id = $GLOBALS['user']->id;
    }

    /**
     * Display one assignment to the student, including the list of exercises.
     */
    public function show_assignment_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $solver_id = Request::option('solver_id', $GLOBALS['user']->id);
        $ip_address = $_SERVER['REMOTE_ADDR'];

        VipsModule::requireViewPermission($assignment);

        if (!$assignment->checkEditPermission()) {
            $solver_id = $GLOBALS['user']->id;
        }

        $this->solver_id = $solver_id;
        $this->user_end_time = $assignment->getUserEndTime($solver_id);
        $this->remaining_time = $this->user_end_time - time();
        $this->access_code = trim(Request::get('access_code'));
        $this->assignment = $assignment;
        $this->needs_code = false;
        $this->exam_terms = null;
        $this->preview_exam_terms = null;

        $this->contentbar = $this->create_contentbar($assignment, null, 'show', $solver_id);

        if (!$assignment->checkEditPermission()) {
            if (!$assignment->isRunning() || !$assignment->active) {
                PageLayout::postError(_('Das Aufgabenblatt kann zur Zeit nicht bearbeitet werden.'));
                $this->redirect('vips/sheets/list_assignments_stud');
                return;
            }

            if (!$assignment->checkIPAccess($ip_address)) {
                PageLayout::postError(sprintf(_('Sie haben mit Ihrer IP-Adresse &bdquo;%s&ldquo; keinen Zugriff!'), htmlReady($ip_address)));
                $this->redirect('vips/sheets/list_assignments_stud');
                return;
            }

            $this->assignment_attempt = $assignment->getAssignmentAttempt($solver_id);

            if ($assignment->type === 'exam') {
                if (!$assignment->checkAccessCode()) {
                    $this->needs_code = true;
                }

                if (!$this->assignment_attempt) {
                    $this->exam_terms = Config::get()->VIPS_EXAM_TERMS;
                }

                if ($this->exam_terms || $this->needs_code) {
                    $this->contentbar = $this->contentbar->withProps(['toc' => null]);
                }
            }

            $widget = new ActionsWidget();

            if ($assignment->type !== 'exam') {
                $widget->addLink(
                    _('Aufgabenblatt drucken'),
                    $this->url_for('vips/sheets/print_assignments', ['assignment_id' => $assignment_id, 'print_files' => 1]),
                    Icon::create('print'),
                    ['target' => '_blank']
                );
            }
            if ($assignment->isResetAllowed()) {
                $widget->addLink(
                    _('Lösungen dieses Blatts löschen'),
                    $this->url_for('vips/sheets/delete_solutions', ['assignment_id' => $assignment_id]),
                    Icon::create('refresh'),
                    ['data-confirm' => _('Wollen Sie die Lösungen dieses Aufgabenblatts wirklich löschen?')]
                )->asButton();
            }
            if ($assignment->type === 'exam' && $this->assignment_attempt && $this->remaining_time > 0) {
                $widget->addLink(
                    _('Klausur vorzeitig abgeben'),
                    $this->url_for('vips/sheets/finish_assignment', ['assignment_id' => $assignment_id]),
                    Icon::create('lock-locked'),
                    ['data-confirm' => _('Achtung: Wenn Sie die Klausur abgeben, sind keine weiteren Eingaben mehr möglich!')]
                )->asButton();
            }
            if ($assignment->type === 'selftest' && $this->assignment_attempt && $this->assignment_attempt->end === null) {
                $widget->addLink(
                    _('Aufgabenblatt jetzt abgeben'),
                    $this->url_for('vips/sheets/finish_assignment', ['assignment_id' => $assignment_id]),
                    Icon::create('lock-locked'),
                    ['data-confirm' => _('Achtung: Wenn Sie das Aufgabenblatt abgeben, sind keine weiteren Eingaben mehr möglich!')]
                )->asButton();
            }
            Sidebar::get()->addWidget($widget);
        } else {
            if ($assignment->type === 'exam') {
                $this->preview_exam_terms = Config::get()->VIPS_EXAM_TERMS;
            }

            Helpbar::get()->addPlainText('',
                _('Dies ist die Studierendensicht (Vorschau) des Aufgabenblatts.'));

            $widget = new ActionsWidget();

            if ($assignment->type !== 'exam') {
                $widget->addLink(
                    _('Aufgabenblatt drucken'),
                    $this->url_for('vips/sheets/print_assignments', ['assignment_id' => $assignment_id, 'print_files' => 1, 'user_ids[]' => $solver_id]),
                    Icon::create('print'),
                    ['target' => '_blank']
                );
            }
            if ($assignment->isResetAllowed()) {
                $widget->addLink(
                    _('Lösungen dieses Blatts löschen'),
                    $this->url_for('vips/sheets/delete_solutions', ['assignment_id' => $assignment_id, 'solver_id' => $solver_id]),
                    Icon::create('refresh'),
                    ['data-confirm' => _('Wollen Sie die Lösungen dieses Aufgabenblatts wirklich löschen?')]
                )->asButton();
            }
            Sidebar::get()->addWidget($widget);

            $widget = new ViewsWidget();
            $widget->addLink(
                _('Aufgabenblatt bearbeiten'),
                $this->url_for('vips/sheets/edit_assignment', ['assignment_id' => $assignment_id])
            );
            $widget->addLink(
                _('Studierendensicht (Vorschau)'),
                $this->url_for('vips/sheets/show_assignment', ['assignment_id' => $assignment_id])
            )->setActive();
            Sidebar::get()->addWidget($widget);

            if ($assignment->range_type === 'course') {
                $widget = new SelectWidget(_('Anzeigen für'), $this->url_for('vips/sheets/show_assignment', compact('assignment_id')), 'solver_id');
                $widget->class = 'nested-select';
                $element = new SelectElement($GLOBALS['user']->id, ' ', $GLOBALS['user']->id == $solver_id);
                $widget->addElement($element);

                foreach ($assignment->course->members->findBy('status', 'autor')->orderBy('nachname, vorname') as $member) {
                    if ($assignment->isVisible($member->user_id)) {
                        $element = new SelectElement($member->user_id, $member->nachname . ', ' . $member->vorname, $member->user_id == $solver_id);
                        $widget->addElement($element);
                    }
                }
                Sidebar::get()->addWidget($widget);
            }
        }
    }

    #####################################
    #                                   #
    #         Lecturer Methods          #
    #                                   #
    #####################################


    /**
     * Dialog for confirming the end date of a starting assignment.
     */
    public function start_assignment_dialog_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        $this->assignment = $assignment;
    }

    /**
     * EXAMS/SHEETS
     *
     * If an assignment hasn't started yet this function sets the start time to NOW
     * so that it's running
     *
     */
    public function start_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        $end_date = trim(Request::get('end_date'));
        $end_time = trim(Request::get('end_time'));
        $end_datetime = DateTime::createFromFormat('d.m.Y H:i', $end_date.' '.$end_time);

        // unlimited selftest
        if ($assignment->type === 'selftest' && $end_date === '' && $end_time === '') {
            $end = null;
        } else if ($end_datetime) {
            $end = strtotime($end_datetime->format('Y-m-d H:i:s'));
        } else {
            $end = $assignment->end;
            PageLayout::postWarning(_('Ungültiger Endzeitpunkt, der Wert wurde nicht übernommen.'));
        }

        // set new start and end time in database
        $assignment->start = time();
        $assignment->end = $end;
        $assignment->active = 1;
        $assignment->store();

        // delete start time for exam from database
        VipsAssignmentAttempt::deleteBySQL('assignment_id = ?', [$assignment_id]);

        $this->redirect('vips/sheets');
    }


    /**
     * EXAMS/SHEETS
     *
     * Stops/continues an assignment (no change of start/end time but temporary closure)
     *
     */
    public function stopgo_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $db = DBManager::get();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        if ($assignment->type === 'exam') {
            if ($assignment->active) {
                $assignment->options['stopdate'] = date('Y-m-d H:i:s');
            } else if ($assignment->options['stopdate']) {
                // extend exam duration for already active participants
                $interval = time() - strtotime($assignment->options['stopdate']);
                $sql = 'UPDATE etask_assignment_attempts SET end = end + ?
                        WHERE assignment_id = ? AND end > ?';
                $stmt = $db->prepare($sql);
                $stmt->execute([$interval, $assignment_id, $assignment->options['stopdate']]);

                unset($assignment->options['stopdate']);
            }
        }

        $assignment->active = !$assignment->active;
        $assignment->store();

        $this->redirect('vips/sheets');
    }


    /**
     * EXAMS/SHEETS
     *
     * Deletes an assignment from the course (and block if applicable).
     */
    public function delete_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $test_title = $assignment->test->title;

        VipsModule::requireEditPermission($assignment);

        if (!$assignment->isLocked()) {
            $assignment->delete();
            PageLayout::postSuccess(sprintf(_('Das Aufgabenblatt „%s“ wurde gelöscht.'), htmlReady($test_title)));
        }

        $this->redirect('vips/sheets');
    }

    /**
     * Delete a list of assignments from the course (and block if applicable).
     */
    public function delete_assignments_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_ids = Request::intArray('assignment_ids');
        $deleted = 0;

        foreach ($assignment_ids as $assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
            VipsModule::requireEditPermission($assignment);

            if (!$assignment->isLocked()) {
                $assignment->delete();
                ++$deleted;
            }
        }

        if ($deleted > 0) {
            PageLayout::postSuccess(sprintf(_('Es wurden %s Aufgabenblätter gelöscht.'), $deleted));
        }

        if ($deleted < count($assignment_ids)) {
            PageLayout::postError(_('Einige Aufgabenblätter konnten nicht gelöscht werden, da bereits Lösungen abgegeben wurden.'), [
                _('Falls Sie diese wirklich löschen möchten, müssen Sie zuerst die Lösungen aller Teilnehmenden zurücksetzen.')
            ]);
        }

        $this->redirect(Context::getId() ? 'vips/sheets' : 'vips/pool/assignments');
    }

    /**
     * Dialog for selecting a block for a list of assignments.
     */
    public function assign_block_dialog_action()
    {
        $course_id = Context::getId();
        VipsModule::requireStatus('tutor', $course_id);

        $this->assignment_ids = Request::intArray('assignment_ids');
        $this->blocks = VipsBlock::findBySQL('range_id = ? ORDER BY name', [$course_id]);
    }

    /**
     * Assign a list of assignments to the specified block.
     */
    public function assign_block_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_ids = Request::intArray('assignment_ids');
        $block_id = Request::int('block_id');

        if ($block_id) {
            $block = VipsBlock::find($block_id);
        }

        foreach ($assignment_ids as $assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
            VipsModule::requireEditPermission($assignment);

            if (!$block_id || $block->range_id === $assignment->range_id) {
                $assignment->block_id = $block_id ?: null;
                $assignment->store();
            }
        }

        PageLayout::postSuccess(_('Die Blockzuordnung wurde gespeichert.'));

        $this->redirect('vips/sheets');
    }

    /**
     * Dialog for copying a list of assignments into a course.
     */
    public function copy_assignments_dialog_action()
    {
        PageLayout::setTitle(_('Aufgabenblätter kopieren'));

        $this->assignment_ids = Request::intArray('assignment_ids');
        $this->courses = VipsModule::getActiveCourses($GLOBALS['user']->id);
        $this->course_id = Context::getId();
    }

    /**
     * Copy the selected assignments into the selected course.
     */
    public function copy_assignments_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_ids = Request::intArray('assignment_ids');
        $course_id = Request::option('course_id');

        if ($course_id) {
            VipsModule::requireStatus('tutor', $course_id);
        }

        foreach ($assignment_ids as $assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
            VipsModule::requireEditPermission($assignment);

            if ($course_id) {
                $assignment->copyIntoCourse($course_id);
            } else {
                $assignment->copyIntoCourse($GLOBALS['user']->id, 'user');
            }
        }

        PageLayout::postSuccess(ngettext('Das Aufgabenblatt wurde kopiert.', 'Die Aufgabenblätter wurden kopiert.', count($assignment_ids)));

        $this->redirect(Context::getId() ? 'vips/sheets' : 'vips/pool/assignments');
    }

    /**
     * Dialog for moving a list of assignments to another course.
     */
    public function move_assignments_dialog_action()
    {
        PageLayout::setTitle(_('Aufgabenblätter verschieben'));

        $this->assignment_ids = Request::intArray('assignment_ids');
        $this->courses = VipsModule::getActiveCourses($GLOBALS['user']->id);
        $this->course_id = Context::getId();
    }

    /**
     * Move a list of assignments to the specified course.
     */
    public function move_assignments_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_ids = Request::intArray('assignment_ids');
        $course_id = Request::option('course_id');

        if ($course_id) {
            VipsModule::requireStatus('tutor', $course_id);
        }

        foreach ($assignment_ids as $assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
            VipsModule::requireEditPermission($assignment);

            if ($course_id) {
                $assignment->moveIntoCourse($course_id);
            } else {
                $assignment->moveIntoCourse($GLOBALS['user']->id, 'user');
            }
        }

        PageLayout::postSuccess(ngettext('Das Aufgabenblatt wurde verschoben.', 'Die Aufgabenblätter wurden verschoben.', count($assignment_ids)));

        $this->redirect(Context::getId() ? 'vips/sheets' : 'vips/pool/assignments');
    }

    /**
     * Delete the solutions of all students and reset the assignment.
     */
    public function reset_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        if ($assignment->type === 'exam') {
            $assignment->deleteAllSolutions();
            PageLayout::postSuccess(_('Die Klausur wurde zurückgesetzt und alle abgegebenen Lösungen archiviert.'));
        }

        $this->redirect(Context::getId() ? 'vips/sheets' : 'vips/pool/assignments');
    }


    /**
     * SHEETS/EXAMS
     *
     * Takes an exercise off an assignment and deletes it.
     */
    public function delete_exercise_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $exercise_id = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $exercise = Exercise::find($exercise_id);

        VipsModule::requireEditPermission($assignment, $exercise_id);

        if (!$assignment->isLocked()) {
            $assignment->test->removeExercise($exercise_id);
            PageLayout::postSuccess(sprintf(_('Die Aufgabe „%s“ wurde gelöscht.'), htmlReady($exercise->title)));
        }

        $this->redirect($this->url_for('vips/sheets/edit_assignment', compact('assignment_id')));
    }

    /**
     * Deletes a list of exercises from a specific assignment.
     */
    public function delete_exercises_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $exercise_ids = Request::intArray('exercise_ids');
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        if (!$assignment->isLocked()) {
            foreach ($exercise_ids as $exercise_id) {
                VipsModule::requireEditPermission($assignment, $exercise_id);
                $assignment->test->removeExercise($exercise_id);
            }

            PageLayout::postSuccess(sprintf(_('Es wurden %s Aufgaben gelöscht.'), count($exercise_ids)));
        }

        $this->redirect($this->url_for('vips/sheets/edit_assignment', compact('assignment_id')));
    }

    /**
     * Reorder exercise positions within an assignment.
     */
    public function move_exercise_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $list = Request::intArray('item');

        VipsModule::requireEditPermission($assignment);

        /* renumber all exercises in current assignment */
        foreach ($list as $i => $exercise_id) {
            $exercise_ref = VipsExerciseRef::find([$assignment->test_id, $exercise_id]);

            if ($exercise_ref) {
                $exercise_ref->position = $i + 1;
                $exercise_ref->store();
            }
        }

        $this->render_nothing();
    }

    /**
     * SHEETS/EXAMS
     *
     * Displays the form for editing an exercise.
     *
     * Is called when editing an existing exercise or creating a new exercise.
     */
    public function edit_exercise_action()
    {
        PageLayout::setHelpKeyword('Basis.VipsAufgaben');

        $exercise_id = Request::int('exercise_id');  // is not set when creating new exercise
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment, $exercise_id);

        if ($exercise_id) {
            // edit already existing exercise
            $exercise_ref = $assignment->test->getExerciseRef($exercise_id);
            $exercise     = $exercise_ref->exercise;

            $max_points = $exercise_ref->points;
            $exercise_position = $exercise_ref->position;
        } else {
            // create new exercise
            $exercise_type = Request::option('exercise_type');
            $exercise = new $exercise_type();

            $max_points = null;
            $exercise_position = null;
        }

        $this->assignment            = $assignment;
        $this->assignment_id         = $assignment_id;
        $this->exercise              = $exercise;
        $this->exercise_position     = $exercise_position;
        $this->max_points            = $max_points;

        $this->contentbar = $this->create_contentbar($assignment, $exercise_id);

        Helpbar::get()->addPlainText('',
            _('Sie können hier den Aufgabentext und die Antwortoptionen dieser Aufgabe bearbeiten.'));

        $widget = new ActionsWidget();

        if (!$assignment->isLocked()) {
            $widget->addLink(
                _('Neue Aufgabe erstellen'),
                $this->url_for('vips/sheets/add_exercise_dialog', ['assignment_id' => $assignment_id]),
                Icon::create('add')
            )->asDialog('size=auto');
        }

        Sidebar::get()->addWidget($widget);

        if ($exercise->id) {
            $widget = new ViewsWidget();
            $widget->addLink(
                _('Aufgabe bearbeiten'),
                $this->url_for('vips/sheets/edit_exercise', ['assignment_id' => $assignment_id, 'exercise_id' => $exercise->id])
            )->setActive();
            $widget->addLink(
                _('Studierendensicht (Vorschau)'),
                $this->url_for('vips/sheets/show_exercise', ['assignment_id' => $assignment_id, 'exercise_id' => $exercise->id])
            );
            Sidebar::get()->addWidget($widget);
        }

        $widget = new ViewsWidget();
        $widget->setTitle(_('Aufgabenblatt'));

        foreach ($assignment->test->exercise_refs as $item) {
            $widget->addLink(
                sprintf(_('Aufgabe %d'), $item->position),
                $this->url_for('vips/sheets/edit_exercise', ['assignment_id' => $assignment_id, 'exercise_id' => $item->task_id])
            )->setActive($item->task_id === $exercise->id);
        }

        Sidebar::get()->addWidget($widget);
    }


    /**
     * SHEETS/EXAMS
     *
     * Inserts/Updates an exercise into the database
     */
    public function store_exercise_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $exercise_id = Request::int('exercise_id');  // not set when storing new exercise
        $exercise_type = Request::option('exercise_type');
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $test_id = $assignment->test_id;
        $file_ids = Request::optionArray('file_ids');
        $request = Request::getInstance();

        VipsModule::requireEditPermission($assignment, $exercise_id);

        if ($exercise_id) {
            // update existing exercise.
            $exercise = Exercise::find($exercise_id);
            $item_count = $exercise->itemCount();
            $exercise->initFromRequest($request);
            $exercise->store();

            // update maximum points
            if ($exercise->itemCount() != $item_count) {
                $exercise_ref = VipsExerciseRef::find([$test_id, $exercise_id]);
                $exercise_ref->points = $exercise->itemCount();
                $exercise_ref->store();
            }
        } else {
            // store exercise in database.
            $exercise = new $exercise_type();
            $exercise->initFromRequest($request);
            $exercise->user_id = $GLOBALS['user']->id;
            $exercise->store();

            // link new exercise to the assignment.
            $assignment->test->addExercise($exercise);
            $exercise_id = $exercise->id;
        }

        $upload = $_FILES['upload'] ?: ['name' => []];
        $folder = Folder::findTopFolder($exercise->id, 'ExerciseFolder', 'task');

        foreach ($folder->file_refs as $file_ref) {
            if (!in_array($file_ref->id, $file_ids) || in_array($file_ref->name, $upload['name'])) {
                $file_ref->delete();
            }
        }

        FileManager::handleFileUpload($upload, $folder->getTypedFolder());

        PageLayout::postSuccess(_('Die Aufgabe wurde eingetragen.'));

        $this->redirect($this->url_for('vips/sheets/edit_exercise', compact('assignment_id', 'exercise_id')));
    }

    /**
     * Copy the selected exercises into this assignment.
     */
    public function copy_exercise_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $exercise_id = Request::int('exercise_id');
        $exercise_ids = $exercise_id ? [$exercise_id => $assignment_id] : Request::intArray('exercise_ids');

        VipsModule::requireEditPermission($assignment);

        if (!$assignment->isLocked()) {
            foreach ($exercise_ids as $exercise_id => $copy_assignment_id) {
                $copy_assignment = VipsAssignment::find($copy_assignment_id);
                VipsModule::requireEditPermission($copy_assignment);

                $exercise_ref = VipsExerciseRef::find([$copy_assignment->test_id, $exercise_id]);
                $exercise_ref->copyIntoTest($assignment->test_id);
            }

            PageLayout::postSuccess(ngettext('Die Aufgabe wurde kopiert.', 'Die Aufgaben wurden kopiert.', count($exercise_ids)));
        }

        $this->redirect($this->url_for('vips/sheets/edit_assignment', compact('assignment_id')));
    }

    /**
     * Dialog for copying a list of exercises to another assignment.
     */
    public function copy_exercises_dialog_action()
    {
        $this->assignment_id = Request::int('assignment_id');
        $this->exercise_ids = Request::intArray('exercise_ids');
        $this->courses = VipsModule::getActiveCourses($GLOBALS['user']->id);
    }

    /**
     * Copy a list of exercises to the specified assignment.
     */
    public function copy_exercises_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $target_assignment_id = Request::int('target_assignment_id');
        $exercise_ids = Request::intArray('exercise_ids');

        $assignment = VipsAssignment::find($assignment_id);
        $target_assignment = VipsAssignment::find($target_assignment_id);

        VipsModule::requireEditPermission($assignment);
        VipsModule::requireEditPermission($target_assignment);

        if (!$target_assignment->isLocked()) {
            foreach ($exercise_ids as $exercise_id) {
                $exercise_ref = $assignment->test->getExerciseRef($exercise_id);
                $exercise_ref->copyIntoTest($target_assignment->test_id);
            }

            PageLayout::postSuccess(ngettext('Die Aufgabe wurde kopiert.', 'Die Aufgaben wurden kopiert.', count($exercise_ids)));
        }

        $this->redirect($this->url_for('vips/sheets/edit_assignment', compact('assignment_id')));
    }

    /**
     * Dialog for moving a list of exercises to another assignment.
     */
    public function move_exercises_dialog_action()
    {
        $this->assignment_id = Request::int('assignment_id');
        $this->exercise_ids = Request::intArray('exercise_ids');
        $this->courses = VipsModule::getActiveCourses($GLOBALS['user']->id);
    }

    /**
     * Move a list of exercises to the specified assignment.
     */
    public function move_exercises_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $target_assignment_id = Request::int('target_assignment_id');
        $exercise_ids = Request::intArray('exercise_ids');

        $assignment = VipsAssignment::find($assignment_id);
        $target_assignment = VipsAssignment::find($target_assignment_id);

        VipsModule::requireEditPermission($assignment);
        VipsModule::requireEditPermission($target_assignment);

        if (!$assignment->isLocked() && !$target_assignment->isLocked()) {
            foreach ($exercise_ids as $exercise_id) {
                $exercise_ref = VipsExerciseRef::find([$assignment->test_id, $exercise_id]);
                $exercise_ref->moveIntoTest($target_assignment->test_id);
            }

            PageLayout::postSuccess(ngettext('Die Aufgabe wurde verschoben.', 'Die Aufgaben wurden verschoben.', count($exercise_ids)));
        }

        $this->redirect($this->url_for('vips/sheets/edit_assignment', compact('assignment_id')));
    }

    /**
     * SHEETS/EXAMS
     *
     * Stores the specification (Grunddaten) of an assignment
     * OR add new exercise, edit points/Bewertung (basically everything that can be done on
     * page edit_exercise_action())
     */
    public function store_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $db = DBManager::get();

        $assignment_id = Request::int('assignment_id');

        if ($assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
        } else {
            $assignment = new VipsAssignment();
            $assignment->range_id = Context::getId() ?: $GLOBALS['user']->id;
            $assignment->range_type = Context::getId() ? 'course' : 'user';
        }

        VipsModule::requireEditPermission($assignment);

        $assignment_name        = trim(Request::get('assignment_name'));
        $assignment_description = trim(Request::get('assignment_description'));
        $assignment_description = Studip\Markup::purifyHtml($assignment_description);
        $assignment_notes       = trim(Request::get('assignment_notes'));
        $assignment_type        = Request::option('assignment_type', 'practice');
        $assignment_block       = Request::int('assignment_block', 0);
        $assignment_block_name  = trim(Request::get('assignment_block_name'));
        $start_date             = trim(Request::get('start_date'));
        $start_time             = trim(Request::get('start_time'));
        $end_date               = trim(Request::get('end_date'));
        $end_time               = trim(Request::get('end_time'));

        $exam_length            = Request::int('exam_length');
        $access_code            = trim(Request::get('access_code'));
        $ip_range               = trim(Request::get('ip_range'));
        $use_groups             = Request::int('use_groups', 0);
        $shuffle_answers        = Request::int('shuffle_answers', 0);
        $shuffle_exercises      = Request::int('shuffle_exercises', 0);
        $self_assessment        = Request::int('self_assessment', 0);
        $max_tries              = Request::int('max_tries', 0);
        $resets                 = Request::int('resets', 0);
        $evaluation_mode        = Request::int('evaluation_mode', 0);
        $exercise_points        = Request::floatArray('exercise_points');
        $selftest_threshold     = Request::getArray('threshold');
        $selftest_feedback      = Request::getArray('feedback');

        $start_datetime = DateTime::createFromFormat('d.m.Y H:i', $start_date.' '.$start_time);
        $end_datetime   = DateTime::createFromFormat('d.m.Y H:i', $end_date.' '.$end_time);

        if ($assignment_name === '') {
            $assignment_name = _('Aufgabenblatt');
        }

        if ($start_datetime) {
            $start = $start_datetime->format('Y-m-d H:i:s');
        } else {
            $start = date('Y-m-d H:00:00');
            PageLayout::postWarning(_('Ungültiger Startzeitpunkt, der Wert wurde nicht übernommen.'));
        }

        // unlimited selftest
        if ($assignment_type == 'selftest' && $end_date == '' && $end_time == '') {
            $end = null;
        } else if ($end_datetime) {
            $end = $end_datetime->format('Y-m-d H:i:s');
        } else {
            $end = date('Y-m-d H:00:00');
            PageLayout::postWarning(_('Ungültiger Endzeitpunkt, der Wert wurde nicht übernommen.'));
        }

        if ($end && $end <= $start) {  // start is *later* than end!
            $end = $start;
            PageLayout::postWarning(_('Bitte überprüfen Sie den Start- und den Endzeitpunkt!'));
        }

        if ($assignment_block_name != '') {
            $block = VipsBlock::create(['name' => $assignment_block_name, 'range_id' => $assignment->range_id]);
        } else if ($assignment_block) {
            $block = VipsBlock::find($assignment_block);

            if ($block->range_id !== $assignment->range_id) {
                $block = null;
            }
        } else {
            $block = null;
        }

        foreach ($selftest_threshold as $i => $threshold) {
            if ($threshold !== '') {
                $feedback[$threshold] = Studip\Markup::purifyHtml($selftest_feedback[$i]);
            }
        }

        /*** store basic data (Grunddaten) of assignment */
        if ($assignment_id) {
            // check whether the exam's start time has been moved
            if ($assignment->start != strtotime($start) && time() <= strtotime($start)) {
                $assignment->active = 1;
            }

            // extend exam duration for already active participants
            if ($assignment_type === 'exam' && $assignment->options['duration'] != $exam_length) {
                $sql = 'UPDATE etask_assignment_attempts SET end = GREATEST(end + ? * 60, UNIX_TIMESTAMP())
                        WHERE assignment_id = ? AND end > UNIX_TIMESTAMP()';
                $stmt = $db->prepare($sql);
                $stmt->execute([$exam_length - $assignment->options['duration'], $assignment_id]);
            }

            $assignment->test->setData([
                'title'       => $assignment_name,
                'description' => $assignment_description
            ]);
            $assignment->test->store();
        } else {
            $assignment->test = VipsTest::create([
                'title'       => $assignment_name,
                'description' => $assignment_description,
                'user_id'     => $GLOBALS['user']->id
            ]);
        }

        $assignment->setData([
            'type'      => $assignment_type,
            'start'     => strtotime($start),
            'end'       => $end ? strtotime($end) : null,
            'block_id'  => $block ? $block->id : null
        ]);

        // update options array
        $assignment->options['evaluation_mode'] = $evaluation_mode;
        $assignment->options['notes']           = $assignment_notes;

        unset($assignment->options['access_code']);
        unset($assignment->options['ip_range']);
        unset($assignment->options['shuffle_answers']);
        unset($assignment->options['shuffle_exercises']);
        unset($assignment->options['self_assessment']);
        unset($assignment->options['use_groups']);
        unset($assignment->options['max_tries']);
        unset($assignment->options['resets']);
        unset($assignment->options['feedback']);

        if ($assignment_type === 'exam') {
            $assignment->options['duration'] = $exam_length;

            if ($access_code !== '') {
                $assignment->options['access_code'] = $access_code;
            }

            if ($ip_range !== '') {
                $assignment->options['ip_range'] = $ip_range;
            }

            $assignment->options['shuffle_answers'] = $shuffle_answers;

            if ($shuffle_exercises === 1) {
                $assignment->options['shuffle_exercises'] = $shuffle_exercises;
            }

            if ($self_assessment === 1) {
                $assignment->options['self_assessment'] = $self_assessment;
            }
        }

        if ($assignment_type === 'practice') {
            $assignment->options['use_groups'] = $use_groups;
        }

        if ($assignment_type === 'selftest') {
            $assignment->options['max_tries'] = $max_tries;

            if ($resets === 0) {
                $assignment->options['resets'] = $resets;
            }

            if (isset($feedback)) {
                krsort($feedback);
                $assignment->options['feedback'] = $feedback;
            }
        }

        $assignment->store();
        $assignment_id = $assignment->id;

        foreach ($assignment->test->exercise_refs as $exercise_ref) {
            $points = $exercise_points[$exercise_ref->task_id];
            $exercise_ref->points = round($points * 2) / 2;
            $exercise_ref->store();
        }

        PageLayout::postSuccess(_('Das Aufgabenblatt wurde gespeichert.'));
        $this->redirect($this->url_for('vips/sheets/edit_assignment', compact('assignment_id')));
    }

    /**
     * Returns the dialog content to create a new exercise.
     */
    public function add_exercise_dialog_action()
    {
        PageLayout::setHelpKeyword('Basis.VipsAufgaben');

        $assignment_id = Request::int('assignment_id');

        $this->assignment_id  = $assignment_id;
        $this->exercise_types = Exercise::getExerciseTypes();
    }

    /**
     * Returns the dialog content to copy an existing exercise.
     */
    public function copy_exercise_dialog_action()
    {
        $assignment_id = Request::int('assignment_id');
        $search_filter = Request::getArray('search_filter');

        $sort = Request::option('sort', 'start_time');
        $desc = Request::int('desc', $sort === 'start_time');
        $page = Request::int('page', 1);
        $size = 15;

        if (empty($search_filter) || Request::submitted('reset_search')) {
            $search_filter = array_fill_keys(['search_string', 'exercise_type'], '');
            $search_filter['range_type'] = Context::getId() ? 'course' : 'user';
        }

        if ($search_filter['range_type'] === 'course') {
            $course_ids = array_column(VipsModule::getActiveCourses($GLOBALS['user']->id), 'id');
        } else {
            $course_ids = [$GLOBALS['user']->id];
        }

        $exercises = $this->getAllExercises($course_ids, $sort, $desc, $search_filter);

        $this->sort = $sort;
        $this->desc = $desc;
        $this->page = $page;
        $this->size = $size;
        $this->count = count($exercises);
        $this->exercises = array_slice($exercises, $size * ($page - 1), $size);
        $this->exercise_types = Exercise::getExerciseTypes();
        $this->assignment_id = $assignment_id;
        $this->search_filter = $search_filter;
    }

    /**
     * Get all matching exercises from a list of courses in given order.
     * If $search_filter is not empty, search filters are applied.
     *
     * @param course_ids    list of courses to get exercises from
     * @param sort          sort exercise list by this property
     * @param desc          true if sort direction is descending
     * @param search_filter the currently active search filter
     *
     * @return array with data of all matching exercises
     */
    public function getAllExercises($course_ids, $sort, $desc, $search_filter)
    {
        $db = DBManager::get();

        // check if some filters are active
        $search_string = $search_filter['search_string'];
        $exercise_type = $search_filter['exercise_type'];

        $sql = "SELECT etask_tasks.*,
                       etask_assignments.id AS assignment_id,
                       etask_assignments.range_id,
                       etask_assignments.range_type,
                       etask_tests.title AS test_title,
                       seminare.name AS course_name,
                       (SELECT MIN(beginn) FROM semester_data
                        JOIN semester_courses USING(semester_id)
                        WHERE course_id = Seminar_id) AS start_time
                FROM etask_tasks
                JOIN etask_test_tasks ON etask_tasks.id = etask_test_tasks.task_id
                JOIN etask_tests ON etask_tests.id = etask_test_tasks.test_id
                JOIN etask_assignments USING (test_id)
                LEFT JOIN seminare ON etask_assignments.range_id = seminare.seminar_id
                WHERE etask_assignments.range_id IN (:course_ids)
                  AND etask_assignments.type IN ('exam', 'practice', 'selftest') " .
                ($search_string ? 'AND (etask_tasks.title LIKE :input OR
                                        etask_tasks.description LIKE :input OR
                                        etask_tests.title LIKE :input OR
                                        seminare.name LIKE :input) ' : '') .
                ($exercise_type ? 'AND etask_tasks.type = :exercise_type ' : '') .
               "ORDER BY :sort :desc, start_time DESC, seminare.name,
                         etask_tests.mkdate DESC, etask_test_tasks.position";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':course_ids', $course_ids);
        $stmt->bindValue(':input', '%' . $search_string . '%');
        $stmt->bindValue(':exercise_type', $exercise_type);
        $stmt->bindValue(':sort', $sort, StudipPDO::PARAM_COLUMN);
        $stmt->bindValue(':desc', $desc ? 'DESC' : 'ASC', StudipPDO::PARAM_COLUMN);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * SHEETS/EXAMS
     *
     * Displays form to edit an existing assignment
     *
     */
    public function edit_assignment_action()
    {
        PageLayout::setHelpKeyword('Basis.VipsAufgabenblatt');

        $assignment_id = Request::int('assignment_id');

        if ($assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
            $test = $assignment->test;
        } else {
            $test = new VipsTest();
            $test->title = _('Aufgabenblatt');

            $assignment = new VipsAssignment();
            $assignment->range_id = Context::getId() ?: $GLOBALS['user']->id;
            $assignment->range_type = Context::getId() ? 'course' : 'user';
            $assignment->type = 'practice';
            $assignment->start = strtotime(date('Y-m-d H:00:00'));
            $assignment->end = strtotime(date('Y-m-d H:00:00'));
        }

        VipsModule::requireEditPermission($assignment);

        if (!isset($assignment->options['feedback'])) {
            $assignment->options['feedback'] = ['' => ''];
        }

        $blocks = VipsBlock::findBySQL('range_id = ? ORDER BY name', [$assignment->range_id]);

        $this->assignment             = $assignment;
        $this->assignment_id          = $assignment_id;
        $this->test                   = $test;
        $this->blocks                 = $blocks;
        $this->locked                 = $assignment_id && $assignment->isLocked();
        $this->exercises              = $test->exercises;
        $this->assignment_types       = VipsAssignment::getAssignmentTypes();
        $this->exam_rooms             = Config::get()->VIPS_EXAM_ROOMS;

        $this->contentbar = $this->create_contentbar($assignment);

        Helpbar::get()->addPlainText('',
            _('Sie können hier die Grunddaten des Aufgabenblatts verwalten und Aufgaben hinzufügen, bearbeiten oder löschen.') . ' ' .
            _('Alle Daten können später geändert oder ergänzt werden.'));

        $widget = new ActionsWidget();

        if ($assignment_id && !$this->locked) {
            $widget->addLink(
                _('Neue Aufgabe erstellen'),
                $this->url_for('vips/sheets/add_exercise_dialog', compact('assignment_id')),
                Icon::create('add')
            )->asDialog('size=auto');
            $widget->addLink(
                _('Vorhandene Aufgabe kopieren'),
                $this->url_for('vips/sheets/copy_exercise_dialog', compact('assignment_id')),
                Icon::create('copy')
            )->asDialog('size=big');
        }

        if ($assignment_id) {
            if ($assignment->range_type === 'course') {
                $widget->addLink(
                    _('Aufgabenblatt korrigieren'),
                    $this->url_for('vips/solutions/assignment_solutions', ['assignment_id' => $assignment_id]),
                    Icon::create('accept')
                );
            }

            $widget->addLink(
                _('Aufgabenblatt drucken'),
                $this->url_for('vips/sheets/print_assignments', ['assignment_id' => $assignment_id]),
                Icon::create('print'),
                ['target' => '_blank']
            );
            Sidebar::get()->addWidget($widget);

            $widget = new ViewsWidget();
            $widget->addLink(
                _('Aufgabenblatt bearbeiten'),
                $this->url_for('vips/sheets/edit_assignment', ['assignment_id' => $assignment_id])
            )->setActive();
            $widget->addLink(
                _('Studierendensicht (Vorschau)'),
                $this->url_for('vips/sheets/show_assignment', ['assignment_id' => $assignment_id])
            );
            Sidebar::get()->addWidget($widget);

            $widget = new ExportWidget();
            $widget->addLink(
                _('Aufgabenblatt exportieren'),
                $this->url_for('vips/sheets/export_xml', ['assignment_id' => $assignment_id]),
                Icon::create('export')
            );
        }

        Sidebar::get()->addWidget($widget);
    }

    /**
     * Show preview of an existing exercise (using print view for now).
     */
    public function preview_exercise_action()
    {
        $exercise_id   = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment, $exercise_id);

        // fetch exercise info
        $exercise_ref = VipsExerciseRef::find([$assignment->test_id, $exercise_id]);
        $exercise     = $exercise_ref->exercise;

        $this->assignment        = $assignment;
        $this->exercise          = $exercise;
        $this->exercise_position = $exercise_ref->position;
        $this->max_points        = $exercise_ref->points;
        $this->solution          = new VipsSolution();
        $this->show_solution     = false;
        $this->print_correction  = false;
        $this->user_id           = null;

        $this->render_template('vips/exercises/print_exercise');
    }

    /**
     * Copy the selected assignments into the current course.
     */
    public function copy_assignment_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $course_id = Context::getId();

        if ($course_id) {
            VipsModule::requireStatus('tutor', $course_id);
        }

        $assignment_id = Request::int('assignment_id');
        $assignment_ids = $assignment_id ? [$assignment_id] : Request::intArray('assignment_ids');

        foreach ($assignment_ids as $assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
            VipsModule::requireEditPermission($assignment);

            if ($course_id) {
                $assignment->copyIntoCourse($course_id);
            } else {
                $assignment->copyIntoCourse($GLOBALS['user']->id, 'user');
            }
        }

        PageLayout::postSuccess(ngettext('Das Aufgabenblatt wurde kopiert.', 'Die Aufgabenblätter wurden kopiert.', count($assignment_ids)));

        $this->redirect($course_id ? 'vips/sheets' : 'vips/pool/assignments');
    }

    /**
     * Imports a test from a text file.
     */
    public function import_test_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $course_id = Context::getId();
        $user_id = $GLOBALS['user']->id;
        $range_id = $course_id ?: $user_id;
        $range_type = $course_id ? 'course' : 'user';

        if ($course_id) {
            VipsModule::requireStatus('tutor', $course_id);
        }

        if ($_FILES['upload']['name'][0] == '') {
            PageLayout::postError(_('Sie müssen eine Datei zum Importieren auswählen.'));
            $this->redirect($course_id ? 'vips/sheets' : 'vips/pool/assignments');
            return;
        }

        $num_assignments = 0;
        $num_exercises = 0;

        for ($i = 0; $i < count($_FILES['upload']['name']); ++$i) {
            if (!is_uploaded_file($_FILES['upload']['tmp_name'][$i])) {
                $message = sprintf(_('Es trat ein Fehler beim Hochladen der Datei „%s“ auf.'), htmlReady($_FILES['upload']['name'][$i]));
                PageLayout::postError($message);
                continue;
            }

            $text = file_get_contents($_FILES['upload']['tmp_name'][$i]);

            if (str_contains($text, '<?xml')) {
                $assignment = VipsAssignment::importXML($text, $user_id, $range_id, $range_type);
            } else {
                // convert from windows-1252 if legacy text format
                $text = mb_decode_numericentity(mb_convert_encoding($text, 'UTF-8', 'WINDOWS-1252'), [0x100, 0xffff, 0, 0xffff], 'UTF-8');
                $test_title = trim(basename($_FILES['upload']['name'][$i], '.txt'));
                $assignment = VipsAssignment::importText($test_title, $text, $user_id, $range_id, $range_type);
            }

            $num_assignments += 1;
            $num_exercises += count($assignment->test->exercise_refs);
        }

        if ($num_assignments == 1) {
            $message = sprintf(ngettext('Das Aufgabenblatt „%s“ mit %d Aufgabe wurde hinzugefügt.',
                                      'Das Aufgabenblatt „%s“ mit %d Aufgaben wurde hinzugefügt.', $num_exercises),
                               htmlReady($assignment->test->title), $num_exercises);
            PageLayout::postSuccess($message);
        } else if ($num_assignments > 1) {
            $message = sprintf(_('%1$d Aufgabenblätter mit insgesamt %2$d Aufgaben wurden hinzugefügt.'), $num_assignments, $num_exercises);
            PageLayout::postSuccess($message);
        }

        $this->redirect($course_id ? 'vips/sheets' : 'vips/pool/assignments');
    }

    /**
     * Creates html print view of a sheet/exam (new window) specified by id
     */
    public function print_assignments_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireViewPermission($assignment);

        $user_ids              = Request::optionArray('user_ids');
        $print_files           = Request::int('print_files');
        $print_correction      = Request::int('print_correction');
        $print_sample_solution = Request::int('print_sample_solution');
        $print_student_ids     = false;
        $assignment_data       = [];

        if (!$assignment->checkEditPermission()) {
            $user_ids              = [$GLOBALS['user']->id];
            $released              = $assignment->releaseStatus($user_ids[0]);
            $print_correction      = $released >= VipsAssignment::RELEASE_STATUS_CORRECTIONS;
            $print_sample_solution = $released == VipsAssignment::RELEASE_STATUS_SAMPLE_SOLUTIONS;

            if ($assignment->type !== 'exam' && $assignment->checkAccess($user_ids[0])) {
                $assignment->recordAssignmentAttempt($user_ids[0]);
            } else if ($released < VipsAssignment::RELEASE_STATUS_CORRECTIONS) {
                PageLayout::postError(_('Kein Zugriff möglich!'));
                $this->redirect('vips/sheets/list_assignments_stud');
                return;
            }
        }

        if ($assignment->range_type === 'course') {
            foreach ($assignment->course->getMembersWithStatus('dozent') as $member) {
                $lecturers[] = $member->getUserFullname();
            }

            $sem_class = $assignment->course->getSemClass();
            $print_student_ids = !$sem_class['studygroup_mode'];
        }

        if ($user_ids) {
            foreach ($user_ids as $user_id) {
                $group = $assignment->getUserGroup($user_id);
                $students = $stud_ids = [];

                if ($group) {
                    $name = $group->name;
                    $members = $assignment->getGroupMembers($group);

                    usort($members, function($a, $b) {
                        return strcoll($a->user->getFullName('no_title_rev'), $b->user->getFullName('no_title_rev'));
                    });

                    foreach ($members as $member) {
                        $students[] = $member->user->getFullName('no_title');
                        $stud_ids[] = $member->user->matriculation_number ?: _('(keine Matrikelnummer)');
                    }
                } else {
                    $user = User::find($user_id);
                    $name = $user->getFullName('no_title_rev');
                    $students[] = $user->getFullName('no_title');
                    $stud_ids[] = $user->matriculation_number ?: _('(keine Matrikelnummer)');
                }

                $assignment_data[] = [
                    'user_id'  => $user_id,
                    'students' => $students,
                    'stud_ids' => $stud_ids
                ];
            }
        } else {
            $assignment_data[] = [
                'user_id'  => null
            ];
        }

        if (count($user_ids) === 1) {
            Config::get()->UNI_NAME_CLEAN = $name;
        }

        PageLayout::setTitle($assignment->test->title);
        $this->set_layout('vips/sheets/print_layout');

        $this->assignment            = $assignment;
        $this->user_ids              = $user_ids;
        $this->lecturers             = $lecturers;
        $this->print_files           = $print_files;
        $this->print_correction      = $print_correction;
        $this->print_sample_solution = $print_sample_solution;
        $this->print_student_ids     = $print_student_ids;
        $this->assignment_data       = $assignment_data;
    }

    /**
     * SHEETS/EXAMS
     *
     * Main page of sheets/exams.
     * Lists all the assignments (sheets or exams) in the course, grouped by "not yet started",
     * "running" and "finished".
     */
    public function list_assignments_action()
    {
        $course_id = Context::getId();
        VipsModule::requireStatus('tutor', $course_id);

        $sort = Request::option('sort', 'start');
        $desc = Request::int('desc');
        $group = isset($_SESSION['group_assignments']) ? $_SESSION['group_assignments'] : 0;
        $group = Request::int('group', $group);

        $_SESSION['group_assignments'] = $group;
        $running = false;

        ######################################
        #   get assignments in this course   #
        ######################################

        $assignments = VipsAssignment::findByRangeId($course_id);
        $blocks   = VipsBlock::findBySQL('range_id = ? ORDER BY name', [$course_id]);
        $blocks[] = VipsBlock::build(['name' => _('Aufgabenblätter ohne Blockzuordnung')]);

        usort($assignments, function($a, $b) use ($sort) {
            if ($sort === 'title') {
                return strcoll($a->test->title, $b->test->title);
            } else if ($sort === 'type') {
                return strcmp($a->type, $b->type);
            } else if ($sort === 'start') {
                return strcmp($a->start, $b->start);
            } else {
                return strcmp($a->end ?: '~', $b->end ?: '~');
            }
        });

        if ($desc) {
            $assignments = array_reverse($assignments);
        }

        $plugin_manager = PluginManager::getInstance();
        $courseware = $plugin_manager->getPluginInfo('CoursewareModule');
        $courseware_active = $courseware && $plugin_manager->isPluginActivated($courseware['id'], $course_id);

        if ($group == 2 && $courseware_active) {
            $elements = Courseware\StructuralElement::findBySQL('range_id = ?', [$course_id]);
            $unassigned = array_column($assignments, 'id');

            foreach ($elements as $element) {
                $assigned = $this->courseware_assignments($element);
                $unassigned = array_diff($unassigned, $assigned);

                $assignment_data[] = [
                    'title'       => $element->title,
                    'assignments' => array_filter($assignments, function($assignment) use ($assigned) {
                        return in_array($assignment->id, $assigned);
                    })
                ];
            }

            $assignment_data[] = [
                'title'       => _('Aufgabenblätter ohne Courseware-Einbindung'),
                'assignments' => array_filter($assignments, function($assignment) use ($unassigned) {
                    return in_array($assignment->id, $unassigned);
                })
            ];
        } else if ($group == 1) {
            foreach ($blocks as $block) {
                $assignment_data[$block->id] = [
                    'title'       => $block->name,
                    'block'       => $block,
                    'assignments' => []
                ];
            }

            foreach ($assignments as $assignment) {
                $assignment_data[$assignment->block_id]['assignments'][] = $assignment;
            }
        } else {
            $group = 0;
            $assignment_data = [
                [
                    'title'       => _('Noch nicht gestartete Aufgabenblätter'),
                    'assignments' => []
                ], [
                    'title'       => _('Laufende Aufgabenblätter'),
                    'assignments' => []
                ], [
                    'title'       => _('Beendete Aufgabenblätter'),
                    'assignments' => []
                ]
            ];

            foreach ($assignments as $assignment) {
                if ($assignment->isFinished()) {
                    $assignment_data[2]['assignments'][] = $assignment;
                } else if ($assignment->isRunning()) {
                    $assignment_data[1]['assignments'][] = $assignment;
                } else {
                    $assignment_data[0]['assignments'][] = $assignment;
                }
            }
        }

        foreach ($assignments as $assignment) {
            if ($assignment->isRunning()) {
                $running = true;
            }
        }

        $this->assignment_data = $assignment_data;
        $this->num_assignments = count($assignments);
        $this->sort = $sort;
        $this->desc = $desc;
        $this->group = $group;
        $this->blocks = $blocks;

        Helpbar::get()->addPlainText('',
            _('Hier können Übungen, Tests und Klausuren online vorbereitet und durchgeführt werden. Sie erhalten ' .
                  'dabei auch eine Übersicht über die Lösungen bzw. Antworten der Studierenden.') . "\n\n" .
            _('Auf dieser Seite können Sie Aufgabenblätter in Ihrem Kurs anlegen und verwalten.'));

        $widget = new ActionsWidget();
        $widget->addLink(
            _('Aufgabenblatt erstellen'),
            $this->url_for('vips/sheets/edit_assignment'),
            Icon::create('add')
        );
        $widget->addLink(
            _('Aufgabenblatt kopieren'),
            $this->url_for('vips/sheets/copy_assignment_dialog'),
            Icon::create('copy')
        )->asDialog('size=1200x800');
        $widget->addLink(
            _('Aufgabenblatt importieren'),
            $this->url_for('vips/sheets/import_assignment_dialog'),
            Icon::create('import')
        )->asDialog('size=auto');
        $widget->addLink(
            _('Neuen Block erstellen'),
            $this->url_for('vips/admin/edit_block'),
            Icon::create('add')
        )->asDialog('size=auto');
        Sidebar::get()->addWidget($widget);

        $widget = new ViewsWidget();
        $widget->addLink(
            _('Gruppiert nach Status'),
            $this->url_for('vips/sheets', ['group' => 0])
        )->setActive($group == 0);
        $widget->addLink(
            _('Gruppiert nach Blöcken'),
            $this->url_for('vips/sheets', ['group' => 1])
        )->setActive($group == 1);

        if ($courseware_active) {
            $widget->addLink(
                _('Verwendung in Courseware'),
                $this->url_for('vips/sheets', ['group' => 2])
            )->setActive($group == 2);
        }

        Sidebar::get()->addWidget($widget);
    }

    /**
     * Collect all assignment_ids used in the given Courseware element.
     */
    private function courseware_assignments($element)
    {
        $result = [];

        foreach ($element->containers as $container) {
            foreach ($container->blocks as $block) {
                if ($block->block_type === 'test') {
                    $payload = json_decode($block->payload, true);

                    if (!empty($payload['assignment'])) {
                        $result[] = $payload['assignment'];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Returns the dialog content to import an assignment from text file.
     */
    public function import_assignment_dialog_action()
    {
    }

    /**
     * Returns the dialog content to copy available assignments.
     */
    public function copy_assignment_dialog_action()
    {
        $search_filter = Request::getArray('search_filter');

        $sort = Request::option('sort', 'start_time');
        $desc = Request::int('desc', $sort === 'start_time');
        $page = Request::int('page', 1);
        $size = 15;

        if (empty($search_filter) || Request::submitted('reset_search')) {
            $search_filter = array_fill_keys(['search_string', 'assignment_type'], '');
            $search_filter['range_type'] = Context::getId() ? 'course' : 'user';
        }

        if ($search_filter['range_type'] === 'course') {
            $course_ids = array_column(VipsModule::getActiveCourses($GLOBALS['user']->id), 'id');
        } else {
            $course_ids = [$GLOBALS['user']->id];
        }

        $assignments = $this->getAllAssignments($course_ids, $sort, $desc, $search_filter);

        $this->sort = $sort;
        $this->desc = $desc;
        $this->page = $page;
        $this->size = $size;
        $this->count = count($assignments);
        $this->assignments = array_slice($assignments, $size * ($page - 1), $size);
        $this->assignment_types = VipsAssignment::getAssignmentTypes();
        $this->search_filter = $search_filter;
    }

    /**
     * Get all matching assignments from a list of courses in given order.
     * If $search_filter is not empty, search filters are applied.
     *
     * @param array  $course_ids    list of courses to get assignments from
     * @param string $sort          sort assignment list by this property
     * @param bool   $desc          true if sort direction is descending
     * @param array  $search_filter the currently active search filter
     *
     * @return array with data of all matching assignments
     */
    public function getAllAssignments(array $course_ids, string $sort, bool $desc, array $search_filter)
    {
        $db = DBManager::get();

        // check if some filters are active
        $search_string = $search_filter['search_string'];
        $assignment_type = $search_filter['assignment_type'];
        $types = $assignment_type ? [$assignment_type] : ['exam', 'practice', 'selftest'];

        $sql = "SELECT etask_assignments.*,
                       etask_tests.title AS test_title,
                       seminare.name AS course_name,
                       (SELECT MIN(beginn) FROM semester_data
                        JOIN semester_courses USING(semester_id)
                        WHERE course_id = Seminar_id) AS start_time
                FROM etask_tests
                JOIN etask_assignments ON etask_tests.id = etask_assignments.test_id
                LEFT JOIN seminare ON etask_assignments.range_id = seminare.seminar_id
                WHERE etask_assignments.range_id IN (:course_ids)
                  AND etask_assignments.type IN (:types) " .
                ($search_string ? 'AND (etask_tests.title LIKE :input OR
                                        etask_tests.description LIKE :input OR
                                        seminare.name LIKE :input) ' : '') .
               "ORDER BY :sort :desc, start_time DESC, seminare.name,
                         etask_tests.mkdate DESC";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':course_ids', $course_ids);
        $stmt->bindValue(':input', '%' . $search_string . '%');
        $stmt->bindValue(':types', $types);
        $stmt->bindValue(':sort', $sort, StudipPDO::PARAM_COLUMN);
        $stmt->bindValue(':desc', $desc ? 'DESC' : 'ASC', StudipPDO::PARAM_COLUMN);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Exports all exercises in this assignment in Vips XML format.
     */
    public function export_xml_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        $this->set_content_type('text/xml; charset=UTF-8');
        header('Content-Disposition: attachment; ' . encode_header_parameter('filename', $assignment->test->title.'.xml'));

        $this->render_text($assignment->exportXML());
    }

    public function relay_action($action)
    {
        $params = func_get_args();
        $params[0] = $this;
        $exercise_id = Request::int('exercise_id');
        $exercise = Exercise::find($exercise_id);
        $action = $action . '_action';

        $this->exercise = $exercise;

        if (method_exists($exercise, $action)) {
            call_user_func_array([$exercise, $action], $params);
        } else {
            throw new InvalidArgumentException(get_class($exercise) . '::' . $action);
        }
    }

    /**
     * Create a ContentBar for this assignment (if no exercise is specified)
     * or for the given exercise on the assignment.
     */
    public function create_contentbar(
        VipsAssignment $assignment,
        ?int $exercise_id = null,
        string $view = 'edit',
        ?string $solver_id = null
    ) {
        $toc = new TOCItem($assignment->test->title);
        $toc->setURL($this->url_for("vips/sheets/{$view}_assignment", ['assignment_id' => $assignment->id]));
        $toc->setActive($exercise_id === null);

        if (!empty($assignment->test->exercise_refs)) {
            if ($view === 'edit') {
                $exercise_refs = $assignment->test->exercise_refs;
            } else {
                $exercise_refs = $assignment->getExerciseRefs($solver_id);
            }

            foreach ($exercise_refs as $i => $item) {
                $child = new TOCItem(sprintf('%d. %s', $i + 1, $item->exercise->title));
                $child->setURL($this->url_for(
                    "vips/sheets/{$view}_exercise",
                    ['assignment_id' => $assignment->id, 'exercise_id' => $item->task_id, 'solver_id' => $solver_id]
                ));

                $child->setActive($item->task_id == $exercise_id);
                $toc->children[] = $child;
            }
        }

        foreach ($toc->children as $i => $item) {
            if ($item->isActive()) {
                $icons = $this->get_template_factory()->open('vips/sheets/content_bar_icons');

                if ($i > 0) {
                    $icons->prev_exercise_url = $toc->children[$i - 1]->getURL();
                }

                if ($i < count($toc->children) - 1) {
                    $icons->next_exercise_url = $toc->children[$i + 1]->getURL();
                }
            }
        }

        return Studip\VueApp::create('ContentBar')->withProps([
            'isContentBar' => true,
            'toc' => $toc
        ])->withSlot('buttons-left', $icons ?? '');
    }

    /**
     * Return the appropriate CSS class for sortable column (if any).
     *
     * @param boolean $sort sort by this column
     * @param boolean $desc set sort direction
     */
    public function sort_class(bool $sort, ?bool $desc): string
    {
        return $sort ? ($desc ? 'sortdesc' : 'sortasc') : '';
    }

    /**
     * Render a generic page chooser selector. The first occurence of '%d'
     * in the URL is replaced with the selected page number.
     *
     * @param string      $url       URL for one of the pages
     * @param string      $count     total number of entries
     * @param string      $page      current page to display
     * @param string|null $dialog    Optional dialog attribute content
     * @param int|null    $page_size page size (defaults to system default)
     * @return mixed
     */
    public function page_chooser(string $url, string $count, string $page, ?string $dialog = null, ?int $page_size = null)
    {
        $template = $GLOBALS['template_factory']->open('shared/pagechooser');
        $template->dialog = $dialog;
        $template->num_postings = $count;
        $template->page = $page;
        $template->perPage = $page_size ?: Config::get()->ENTRIES_PER_PAGE;
        $template->pagelink = str_replace('%%25d', '%d', str_replace('%', '%%', $url));

        return $template->render();
    }
}
