<?php
/**
 * vips/solutions.php - assignment solutions controller
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Elmar Ludwig
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 */

class Vips_SolutionsController extends AuthenticatedController
{
    /**
     * Return the default action and arguments
     *
     * @return array containing the action, an array of args and the format
     */
    public function default_action_and_args()
    {
        return ['assignments', [], null];
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

        Navigation::activateItem('/course/vips/solutions');
        PageLayout::setHelpKeyword('Basis.VipsErgebnisse');
        PageLayout::setTitle(PageLayout::getTitle() . ' - ' . _('Ergebnisse'));
    }

    /**
     * Displays all exercise sheets.
     * Lecturer can select what sheet to correct.
     */
    public function assignments_action()
    {
        $sort = Request::option('sort', 'start');
        $desc = Request::int('desc');
        $course_id = Context::getId();
        VipsModule::requireStatus('autor', $course_id);

        $this->sort      = $sort;
        $this->desc      = $desc;
        $this->course_id = $course_id;
        $this->user_id   = $GLOBALS['user']->id;
        $this->test_data = $this->get_assignments_data($course_id, $this->user_id, $sort, $desc);
        $this->blocks    = VipsBlock::findBySQL('range_id = ? ORDER BY name', [$course_id]);
        $this->blocks[]  = VipsBlock::build(['name' => _('Aufgabenblätter ohne Blockzuordnung')]);

        foreach ($this->test_data['assignments'] as $assignment) {
            $this->block_assignments[$assignment['assignment']->block_id][] = $assignment;
        }

        $this->use_weighting = false;

        foreach ($this->blocks as $block) {
            if ($block->weight !== null) {
                if ($block->weight) {
                    $this->use_weighting = true;
                }
            } else if (isset($this->block_assignments[$block->id])) {
                foreach ($this->block_assignments[$block->id] as $ass) {
                    if ($ass['assignment']->weight) {
                        $this->use_weighting = true;
                    }
                }
            }
        }

        $settings = CourseConfig::get($course_id);

        // display course results if grades are defined for this course
        if (!VipsModule::hasStatus('tutor', $course_id) && $settings->VIPS_COURSE_GRADES) {
            $assignments = VipsAssignment::findBySQL("range_id = ? AND type IN ('exam', 'practice')", [$course_id]);
            $show_overview = true;

            // find unreleased or unfinished assignments
            foreach ($assignments as $assignment) {
                if (!$this->use_weighting || $assignment->weight || $assignment->block_id && $assignment->block->weight) {
                    if (
                        $assignment->isVisible($this->user_id)
                        && $assignment->releaseStatus($this->user_id) == VipsAssignment::RELEASE_STATUS_NONE
                    ) {
                        $show_overview = false;
                    }
                }
            }

            // if all assignments are finished and released
            if ($show_overview) {
                $this->overview_data = $this->participants_overview_data($course_id, $this->user_id);
            }
        }

        if (VipsModule::hasStatus('tutor', $course_id)) {
            Helpbar::get()->addPlainText('',
                _('Hier finden Sie eine Übersicht über den Korrekturstatus Ihrer Aufgabenblätter und können Aufgaben korrigieren. ' .
                      'Außerdem können Sie hier die Einstellungen für die Notenberechnung in Ihrem Kurs vornehmen.'));

            $widget = new ActionsWidget();
            $widget->addLink(
                _('Notenverteilung festlegen'),
                $this->url_for('vips/admin/edit_grades'),
                Icon::create('graph')
            )->asDialog('size=auto');
            Sidebar::get()->addWidget($widget);

            $widget = new ViewsWidget();
            $widget->addLink(
                _('Ergebnisse'),
                $this->url_for('vips/solutions')
            )->setActive();
            $widget->addLink(
                _('Punkteübersicht'),
                $this->url_for('vips/solutions/participants_overview', ['display' => 'points'])
            );
            $widget->addLink(
                _('Notenübersicht'),
                $this->url_for('vips/solutions/participants_overview', ['display' => 'weighting'])
            );
            $widget->addLink(
                _('Statistik'),
                $this->url_for('vips/solutions/statistics')
            );
            Sidebar::get()->addWidget($widget);
        }
    }

    /**
     * Changes which correction information is released to the student (either
     * nothing or only the points or points and correction).
     */
    public function update_released_dialog_action()
    {
        PageLayout::setTitle(_('Freigabe für Studierende'));

        $this->assignment_ids = Request::intArray('assignment_ids');

        foreach ($this->assignment_ids as $assignment_id) {
            $assignment = VipsAssignment::find($assignment_id);
            VipsModule::requireEditPermission($assignment);

            $released = $assignment->options['released'];
            $default = isset($default) ? ($released === $default ? $default : -1) : $released;

            if ($assignment->type === 'exam') {
                $this->exam_options = true;
            }
        }

        $this->default = $default;
    }

    /**
     * Changes which correction information is released to the student (either
     * nothing or only the points or points and correction).
     */
    public function update_released_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_ids = Request::intArray('assignment_ids');
        $released = Request::int('released');

        if (isset($released)) {
            foreach ($assignment_ids as $assignment_id) {
                $assignment = VipsAssignment::find($assignment_id);
                VipsModule::requireEditPermission($assignment);

                $assignment->options['released'] = $released;
                $assignment->store();
            }

            PageLayout::postSuccess(_('Die Freigabeeinstellungen wurden geändert.'));
        }

        $this->redirect('vips/solutions');
    }

    /**
     * Changes which correction information is released to the student (either
     * nothing or only the points or points and correction).
     */
    public function change_released_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        $assignment->options['released'] = Request::int('released');
        $assignment->store();

        $this->redirect($this->url_for('vips/solutions/assignment_solutions', compact('assignment_id')));
    }

    /**
     * Shows solution points for each student/group with a link to view solution and correct it.
     */
    public function assignment_solutions_action()
    {
        PageLayout::setHelpKeyword('Basis.VipsKorrektur');

        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $format        = Request::option('format');

        VipsModule::requireEditPermission($assignment);

        $view      = Request::option('view');
        $expand    = Request::option('expand');

        // fetch info about assignment
        $end        = $assignment->end;
        $duration   = $assignment->options['duration'];
        $released   = $assignment->options['released'];

        // fetch solvers, exercises and solutions //
        $arrays    = $this->get_solutions($assignment, $view);
        $solvers   = $arrays['solvers'];
        $exercises = $arrays['exercises'];
        $solutions = $arrays['solutions'];

        if ($assignment->type === 'exam') {
            $all_solvers = $solvers;
            $solvers     = [];
            $started     = [];

            // find all user ids which have an entry in etask_assignment_attempts
            foreach ($assignment->assignment_attempts as $attempt) {
                $start = $attempt->start;
                $user_end = $attempt->end ? $attempt->end : $start + $duration * 60;
                $user_end = min($end, $user_end);
                $remaining_time = ceil(($user_end - time()) / 60);

                $started[$attempt->user_id] = [
                    'start'     => $start,
                    'remaining' => $remaining_time,
                    'ip'        => $attempt->ip_address
                ];
            }

            // remove users which are not shown
            foreach ($all_solvers as $solver) {
                $user_id = $solver['id'];

                if (isset($started[$user_id])) {
                    $remaining = $started[$user_id]['remaining'];

                    if ($view === 'working' && $remaining > 0 || $view == '' && $remaining <= 0) {
                        // working or finished
                        $solvers[$user_id] = $all_solvers[$user_id];
                        $solvers[$user_id]['running_info'] = $started[$user_id];
                    }
                } else if ($view === 'pending') {
                    if ($assignment->isVisible($user_id)) {
                        // not yet started
                        $solvers[$user_id] = $all_solvers[$user_id];
                    }
                }
            }
        }

        /* reached points, uncorrected solutions and unanswered exercises */

        $overall_uncorrected_solutions = 0;
        $first_uncorrected_solution    = null;

        foreach ($solvers as $solver_id => $solver) {
            $extra_info = [
                'points'      => 0,
                'progress'    => 0,
                'uncorrected' => 0,
                'unanswered'  => count($exercises),
                'files'       => 0
            ];

            if (isset($solutions[$solver_id])) {
                foreach ($solutions[$solver_id] as $solution) {
                    $extra_info['points']      += $solution['points'];
                    $extra_info['progress']    += $exercises[$solution['exercise_id']]['points'];
                    $extra_info['uncorrected'] += $solution['corrected'] ? 0 : 1;
                    $extra_info['unanswered']  -= 1;
                    $extra_info['files']       += $solution['uploads'];

                    if (!$solution['corrected']) {
                        if (!isset($first_uncorrected_solution)) {
                            $first_uncorrected_solution = [
                                'solver_id'   => $solver['user_id'],
                                'exercise_id' => $solution['exercise_id'],
                            ];
                        }
                    }
                }
            }

            $overall_uncorrected_solutions += $extra_info['uncorrected'];
            $solvers[$solver_id]['extra_info'] = $extra_info;
        }

        $this->assignment                    = $assignment;
        $this->assignment_id                 = $assignment_id;
        $this->view                          = $view;
        $this->expand                        = $expand;
        $this->solutions                     = $solutions;
        $this->solvers                       = $solvers;
        $this->exercises                     = $exercises;
        $this->overall_max_points            = $assignment->test->getTotalPoints();
        $this->overall_uncorrected_solutions = $overall_uncorrected_solutions;
        $this->first_uncorrected_solution    = $first_uncorrected_solution;

        if ($format === 'csv') {
            $columns = [_('Teilnehmende')];

            foreach ($exercises as $exercise) {
                $columns[] = $exercise['position'] . '. ' . $exercise['title'];
            }

            $columns[] = _('Summe');

            $data = [$columns];

            $row = [_('Maximalpunktzahl')];

            foreach ($exercises as $exercise) {
                $row[] = sprintf('%g', $exercise['points']);
            }

            $row[] = sprintf('%g', $this->overall_max_points);

            $data[] = $row;

            foreach ($solvers as $solver) {
                $row = [$solver['name']];

                foreach ($exercises as $exercise) {
                    if (isset($solutions[$solver['id']][$exercise['id']])) {
                        $row[] = sprintf('%g', $solutions[$solver['id']][$exercise['id']]['points']);
                    } else {
                        $row[] = '';
                    }
                }

                $row[] = sprintf('%g', $solver['extra_info']['points']);

                $data[] = $row;
            }

            $this->render_csv($data, $assignment->test->title . '.csv');
        } else {
            Helpbar::get()->addPlainText('',
                _('In dieser Übersicht können Sie sich anzeigen lassen, welche Teilnehmenden Lösungen abgegeben haben, und diese Lösungen korrigieren und freigeben.'));

            $widget = new ActionsWidget();
            $widget->addLink(
                _('Aufgabenblatt bearbeiten'),
                $this->url_for('vips/sheets/edit_assignment', ['assignment_id' => $assignment_id]),
                Icon::create('edit')
            );
            $widget->addLink(
                _('Aufgabenblatt drucken'),
                $this->url_for('vips/sheets/print_assignments', ['assignment_id' => $assignment_id]),
                Icon::create('print'),
                ['target' => '_blank']
            );
            $widget->addLink(
                _('Autokorrektur starten'),
                $this->url_for('vips/solutions/autocorrect_dialog', compact('assignment_id', 'expand', 'view')),
                Icon::create('accept')
            )->asDialog('size=auto');

            if ($assignment->type === 'exam') {
                $widget->addLink(
                    _('Alle Lösungen zurücksetzen'),
                    $this->url_for('vips/solutions/delete_solutions', compact('assignment_id', 'view') + ['solver_id' => 'all']),
                    Icon::create('refresh'),
                    ['data-confirm' => _('Achtung: Wenn Sie die Lösungen zurücksetzen, werden die Lösungen aller Teilnehmenden archiviert!')]
                )->asButton();
            }

            $plugin_manager = PluginManager::getInstance();
            $gradebook = $plugin_manager->getPluginInfo('GradebookModule');

            if ($gradebook && $plugin_manager->isPluginActivated($gradebook['id'], $assignment->range_id)) {
                if ($assignment->options['gradebook_id']) {
                    $widget->addLink(
                        _('Gradebook-Eintrag entfernen'),
                        $this->url_for('vips/solutions/gradebook_unpublish', compact('assignment_id', 'expand', 'view')),
                        Icon::create('assessment'),
                        ['data-confirm' => _('Eintrag aus dem Gradebook löschen?')]
                    )->asButton();
                } else {
                    $widget->addLink(
                        _('Eintrag im Gradebook anlegen'),
                        $this->url_for('vips/solutions/gradebook_dialog', compact('assignment_id', 'expand', 'view')),
                        Icon::create('assessment')
                    )->asDialog('size=auto');
                }
            }

            Sidebar::get()->addWidget($widget);

            $widget = new ExportWidget();
            $widget->addLink(
                _('Punktetabelle exportieren'),
                $this->url_for('vips/solutions/assignment_solutions', ['assignment_id' => $assignment_id, 'format' => 'csv']),
                Icon::create('export')
            );

            if ($assignment->type === 'exam') {
                $widget->addLink(
                    _('Abgabeprotokolle exportieren'),
                    $this->url_for('vips/solutions/assignment_logs', ['assignment_id' => $assignment_id]),
                    Icon::create('export')
                );
            }

            $widget->addLink(
                _('Lösungen der Teilnehmenden exportieren'),
                $this->url_for('vips/solutions/download_responses', ['assignment_id' => $assignment_id]),
                Icon::create('export')
            );
            $widget->addLink(
                _('Abgegebene Dateien herunterladen'),
                $this->url_for('vips/solutions/download_all_uploads', ['assignment_id' => $assignment_id]),
                Icon::create('export')
            );
            Sidebar::get()->addWidget($widget);

            $widget = new OptionsWidget();
            $widget->setTitle(_('Freigabe für Studierende'));
            $widget->addRadioButton(
                _('Nichts'),
                $this->url_for('vips/solutions/change_released', [
                    'assignment_id' => $assignment_id,
                    'released' => VipsAssignment::RELEASE_STATUS_NONE,
                ]),
                $released == VipsAssignment::RELEASE_STATUS_NONE
            );
            $widget->addRadioButton(
                _('Vergebene Punkte'),
                $this->url_for('vips/solutions/change_released', [
                    'assignment_id' => $assignment_id,
                    'released' => VipsAssignment::RELEASE_STATUS_POINTS,
                ]),
                $released == VipsAssignment::RELEASE_STATUS_POINTS
            );
            $widget->addRadioButton(
                _('Punkte und Kommentare'),
                $this->url_for('vips/solutions/change_released', [
                    'assignment_id' => $assignment_id,
                    'released' => VipsAssignment::RELEASE_STATUS_COMMENTS,
                ]),
                $released == VipsAssignment::RELEASE_STATUS_COMMENTS
            );
            $widget->addRadioButton(
                _('… zusätzlich Aufgaben und Korrektur'),
                $this->url_for('vips/solutions/change_released', [
                    'assignment_id' => $assignment_id,
                    'released' => VipsAssignment::RELEASE_STATUS_CORRECTIONS,
                ]),
                $released == VipsAssignment::RELEASE_STATUS_CORRECTIONS
            );
            $widget->addRadioButton(
                _('… zusätzlich Musterlösungen'),
                $this->url_for('vips/solutions/change_released', [
                    'assignment_id' => $assignment_id,
                    'released' => VipsAssignment::RELEASE_STATUS_SAMPLE_SOLUTIONS,
                ]),
                $released == VipsAssignment::RELEASE_STATUS_SAMPLE_SOLUTIONS
            );
            Sidebar::get()->addWidget($widget);
        }
    }

    /**
     * Download responses to all exercises for all users in an assignment.
     */
    public function download_responses_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        $arrays = $this->get_solutions($assignment, null);
        $columns = [_('Teilnehmende')];
        $exercises = [];
        $item_count = [];

        foreach ($arrays['exercises'] as $exercise) {
            $exercises[$exercise['id']] = Exercise::find($exercise['id']);
            $item_count[$exercise['id']] = $exercises[$exercise['id']]->itemCount();

            for ($i = 0; $i < $item_count[$exercise['id']]; ++$i) {
                if ($i === 0) {
                    $columns[] = $exercise['position'] . '. ' . $exercise['title'];
                } else {
                    $columns[] = '';
                }
            }

            if ($exercises[$exercise['id']]->options['comment']) {
                $columns[] = _('Bemerkungen');
            }
        }

        $data = [$columns];

        foreach ($arrays['solvers'] as $solver) {
            $row = [$solver['name']];

            if (isset($arrays['solutions'][$solver['id']])) {
                $solutions = $arrays['solutions'][$solver['id']];

                foreach ($arrays['exercises'] as $exercise) {
                    $vips_solution = null;
                    $export = [];

                    if (isset($solutions[$exercise['id']])) {
                        $vips_solution = VipsSolution::find($solutions[$exercise['id']]['id']);
                        $export = $exercises[$exercise['id']]->exportResponse($vips_solution->response);
                    }

                    for ($i = 0; $i < $item_count[$exercise['id']]; ++$i) {
                        $row[] = isset($export[$i]) ? $export[$i] : '';
                    }

                    if ($exercises[$exercise['id']]->options['comment']) {
                        $row[] = $vips_solution ? $vips_solution->student_comment : '';
                    }
                }

                $data[] = $row;
            }
        }

        $this->render_csv($data, $assignment->test->title . '.csv');
    }

    /**
     * Download uploads to current solutions for all users in an assignment.
     */
    public function download_all_uploads_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        $sem_class = $assignment->course->getSemClass();
        $filename = $assignment->test->title . '.zip';
        $zipfile = tempnam($GLOBALS['TMP_PATH'], 'upload');
        $zip = new ZipArchive();

        if (!$zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new Exception(_('ZIP-Archiv konnte nicht erzeugt werden.'));
        }

        $arrays = $this->get_solutions($assignment, null);

        foreach ($arrays['solvers'] as $solver) {
            foreach ($arrays['exercises'] as $exercise) {
                $solution = $arrays['solutions'][$solver['id']][$exercise['id']];  // may be null
                $folder = $solver['name'];

                if ($solver['type'] === 'single' && !$sem_class['studygroup_mode']) {
                    $folder .= sprintf(' (%s)', $solver['stud_id'] ?: $solver['username']);
                }

                if ($solution && $solution['uploads']) {
                    foreach (VipsSolution::find($solution['id'])->folder->file_refs as $file_ref) {
                        $zip->addFile($file_ref->file->getPath(), sprintf(_('%s/Aufgabe %d/'), $folder, $exercise['position']) . $file_ref->name);
                    }
                }
            }
        }

        $zip->close();

        if (!file_exists($zipfile)) {
            file_put_contents($zipfile, base64_decode('UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA=='));
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; ' . encode_header_parameter('filename', $filename));
        header('Content-Length: ' . filesize($zipfile));

        readfile($zipfile);
        unlink($zipfile);
        die();
    }

    /**
     * Download uploads to current solutions for a user in an assignment.
     */
    public function download_uploads_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $solver_id     = Request::option('solver_id');

        VipsModule::requireEditPermission($assignment);

        $group = $assignment->getUserGroup($solver_id);
        $solver_name = $group ? $group->name : get_username($solver_id);

        $filename = $assignment->test->title . '-' . $solver_name . '.zip';
        $zipfile = tempnam($GLOBALS['TMP_PATH'], 'upload');
        $zip = new ZipArchive();

        if (!$zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new Exception(_('ZIP-Archiv konnte nicht erzeugt werden.'));
        }

        foreach ($assignment->test->exercises as $i => $exercise) {
            $solution = $assignment->getSolution($solver_id, $exercise->id);

            if ($solution) {
                foreach ($solution->folder->file_refs as $file_ref) {
                    $zip->addFile($file_ref->file->getPath(), sprintf(_('Aufgabe %d/'), $i + 1) . $file_ref->name);
                }
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; ' . encode_header_parameter('filename', $filename));
        header('Content-Length: ' . filesize($zipfile));

        readfile($zipfile);
        unlink($zipfile);
        die();
    }

    /**
     * Show dialog for publishing the assignment in the gradebook.
     */
    public function gradebook_dialog_action()
    {
        $this->assignment_id = Request::int('assignment_id');
        $this->assignment    = VipsAssignment::find($this->assignment_id);
        $this->view          = Request::option('view');
        $this->expand        = Request::option('expand');

        VipsModule::requireEditPermission($this->assignment);

        $definitions = Grading\Definition::findByCourse_id($this->assignment->range_id);
        $this->weights = array_sum(array_column($definitions, 'weight'));
    }

    /**
     * Publish this assignment in the gradebook of the course.
     */
    public function gradebook_publish_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $view          = Request::option('view');
        $expand        = Request::option('expand');
        $title         = Request::get('title');
        $weight        = Request::float('weight');

        VipsModule::requireEditPermission($assignment);

        $assignment->insertIntoGradebook($title, $weight);
        $assignment->updateGradebookEntries();

        PageLayout::postSuccess(_('Das Aufgabenblatt wurde in das Gradebook eingetragen.'));
        $this->redirect($this->url_for('vips/solutions/assignment_solutions', compact('assignment_id', 'view', 'expand')));
    }

    /**
     * Remove this assignment from the gradebook of the course.
     */
    public function gradebook_unpublish_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $view          = Request::option('view');
        $expand        = Request::option('expand');

        VipsModule::requireEditPermission($assignment);

        $assignment->removeFromGradebook();

        PageLayout::postSuccess(_('Das Aufgabenblatt wurde aus dem Gradebook gelöscht.'));
        $this->redirect($this->url_for('vips/solutions/assignment_solutions', compact('assignment_id', 'view', 'expand')));
    }

    /**
     * Download a summary of the event logs for an assignment.
     */
    public function assignment_logs_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment);

        $columns = [_('Nachname'), _('Vorname'), _('Kennung'), _('Ereignis'),
                    _('Zeit'), _('IP-Adresse'), _('Rechnername'), _('Sitzungs-ID')];
        $data = [];

        foreach ($assignment->assignment_attempts as $assignment_attempt) {
            foreach ($assignment_attempt->getLogEntries() as $entry) {
                $data[] = [
                    $assignment_attempt->user->nachname,
                    $assignment_attempt->user->vorname,
                    $assignment_attempt->user->username,
                    $entry['label'],
                    date('Y-m-d H:i:s', $entry['time']),
                    $entry['ip_address'],
                    $entry['ip_address'] ? $this->gethostbyaddr($entry['ip_address']) : '',
                    $entry['session_id']
                ];
            }
        }

        usort($data, function($a, $b) {
            return strcoll("{$a[0]},{$a[1]},{$a[2]},{$a[4]}", "{$b[0]},{$b[1]},{$b[2]},{$b[4]}");
        });

        array_unshift($data, $columns);

        $this->render_csv($data, $assignment->test->title . '_log.csv');
    }



    /******************************************************************************/
    /*
    /* A U T O K O R R E K T U R
    /*
    /******************************************************************************/

    /**
     * Select options and run automatic correction of solutions.
     */
    public function autocorrect_dialog_action()
    {
        $this->assignment_id = Request::int('assignment_id');
        $this->view          = Request::option('view');
        $this->expand        = Request::option('expand');
    }

    /**
     * Deletes all solution-points, where the solutions are automatically corrected
     */
    public function autocorrect_solutions_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $view          = Request::option('view');
        $expand        = Request::option('expand');
        $corrected     = Request::int('corrected', 0);

        VipsModule::requireEditPermission($assignment);

        $corrected_solutions = 0;

        // select all solutions not manually corrected
        $solutions = $assignment->solutions->findBy('grader_id', null);

        foreach ($solutions as $solution) {
            $assignment->correctSolution($solution, $corrected);
            $solution->store();

            if ($solution->state) {
                ++$corrected_solutions;
            }
        }

        $message = sprintf(ngettext('Es wurde %d Lösung korrigiert.', 'Es wurden %d Lösungen korrigiert.', $corrected_solutions), $corrected_solutions);
        PageLayout::postSuccess($message);
        $this->redirect($this->url_for('vips/solutions/assignment_solutions', compact('assignment_id', 'view', 'expand')));
    }

    /**
     * Display form that allows lecturer to correct the student's solution.
     */
    public function edit_solution_action()
    {
        PageLayout::setHelpKeyword('Basis.VipsKorrektur');

        $exercise_id   = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);

        VipsModule::requireEditPermission($assignment, $exercise_id);

        $archived_id   = Request::int('solution_id');
        $solver_id     = Request::option('solver_id');
        $view          = Request::option('view');

        $group = $assignment->getUserGroup($solver_id);
        $solver_name = $group ? $group->name : get_fullname($solver_id, 'no_title_rev');
        $solver_or_group_id = $group ? $group->id : $solver_id;

        // fetch solvers, exercises and solutions //

        $arrays = $this->get_solutions($assignment, $view);
        $solvers   = $arrays['solvers'];
        $exercises = $arrays['exercises'];
        $solutions = $arrays['solutions'];

        // previous and next solver, exercise and uncorrected exercise //

        $prev_solver               = null;
        $prev_exercise             = null;
        $next_solver               = null;
        $next_exercise             = null;
        $next_uncorrected_exercise = null;
        $before_current            = true;  // before current solver + current exercise

        foreach ($solvers as $solver) {
            foreach ($exercises as $exercise) {
                // current solver and current exercise
                if ($solver['id'] == $solver_or_group_id && $exercise['id'] == $exercise_id) {
                    $before_current = false;
                    $exercise_position = $exercise['position'];
                    $max_points = $exercise['points'];
                    continue;
                }

                if (isset($solutions[$solver['id']][$exercise['id']])) {
                    // previous/next solver (same exercise)
                    if ($solver['id'] != $solver_or_group_id && $exercise['id'] == $exercise_id) {
                        if ($before_current) {
                            $prev_solver = $solver;
                        } else if (!isset($next_solver)) {
                            $next_solver = $solver;
                        }
                    }

                    // previous/next exercise (same solver)
                    if ($solver['id'] == $solver_or_group_id && $exercise['id'] != $exercise_id) {
                        if ($before_current) {
                            $prev_exercise = $exercise;
                        } else if (!isset($next_exercise)) {
                            $next_exercise = $exercise;
                        }
                    }

                    // previous/next uncorrected exercise
                    if (!$solutions[$solver['id']][$exercise['id']]['corrected']) {
                        if ($before_current) {
                            $prev_uncorrected_exercise = [
                                'id'        => $exercise['id'],
                                'solver_id' => $solver['user_id']
                            ];
                        } else if (!isset($next_uncorrected_exercise)) {
                            $next_uncorrected_exercise = [
                                'id'        => $exercise['id'],
                                'solver_id' => $solver['user_id']
                            ];
                        }
                    }

                    // break condition
                    if (isset($next_uncorrected_exercise) && isset($next_solver)) {
                        break 2;
                    }
                }
            }
        }

        ###################################
        # get user solution if applicable #
        ###################################

        $exercise = Exercise::find($exercise_id);
        $solution = $assignment->getSolution($solver_id, $exercise_id);
        $solution_archive = $assignment->getArchivedSolutions($solver_id, $exercise_id);

        if (!$solution) {
            $solution = new VipsSolution();
            $solution->assignment = $assignment;
            $version = _('Nicht abgegeben');
        } else {
            $version = date('d.m.Y, H:i', $solution->mkdate);
        }

        if ($assignment->type !== 'selftest' && !isset($solution->feedback)) {
            $solution->feedback = $exercise->options['feedback'];
        }

        if ($archived_id) {
            foreach ($solution_archive as $old_solution) {
                if ($old_solution->id == $archived_id) {
                    $solution = $old_solution;
                    break;
                }
            }
        }

        ##############################
        #   set template variables   #
        ##############################

        $this->exercise                  = $exercise;
        $this->exercise_id               = $exercise_id;
        $this->exercise_position         = $exercise_position;
        $this->assignment                = $assignment;
        $this->assignment_id             = $assignment_id;
        $this->solver_id                 = $solver_id;
        $this->solver_name               = $solver_name;
        $this->solver_or_group_id        = $solver_or_group_id;
        $this->solution                  = $solution;
        $this->edit_solution             = !$archived_id;
        $this->show_solution             = true;
        $this->max_points                = $max_points;
        $this->prev_solver               = $prev_solver;
        $this->prev_exercise             = $prev_exercise;
        $this->next_solver               = $next_solver;
        $this->next_exercise             = $next_exercise;
        $this->view                      = $view;

        Helpbar::get()->addPlainText('',
            _('Sie können hier die Ergebnisse der Autokorrektur ansehen und Aufgaben manuell korrigieren.'));

        $widget = new ActionsWidget();
        $widget->addLink(
            _('Aufgabe bearbeiten'),
            $this->url_for('vips/sheets/edit_exercise', compact('assignment_id', 'exercise_id')),
            Icon::create('edit')
        );
        $widget->addLink(
            _('Aufgabenblatt drucken'),
            $this->url_for('vips/sheets/print_assignments', ['assignment_id' => $assignment_id, 'user_ids[]' => $solver_id, 'print_files' => 1, 'print_correction' => !$view]),
            Icon::create('print'),
            ['target' => '_blank']
        );
        Sidebar::get()->addWidget($widget);

        $widget = new LinksWidget();
        $widget->setTitle(_('Links'));
        if (isset($prev_uncorrected_exercise)) {
            $widget->addLink(
                _('Vorige unkorrigierte Aufgabe'),
                $this->url_for('vips/solutions/edit_solution', [
                    'assignment_id' => $assignment_id,
                    'exercise_id' => $prev_uncorrected_exercise['id'],
                    'solver_id' => $prev_uncorrected_exercise['solver_id'],
                    'view' => $view,
                ]),
                Icon::create('arr_1left')
            );
        }
        if (isset($next_uncorrected_exercise)) {
            $widget->addLink(
                _('Nächste unkorrigierte Aufgabe'),
                $this->url_for('vips/solutions/edit_solution', [
                    'assignment_id' => $assignment_id,
                    'exercise_id' => $next_uncorrected_exercise['id'],
                     'solver_id' => $next_uncorrected_exercise['solver_id'],
                    'view' => $view,
                ]),
                Icon::create('arr_1right')
            );
        }
        Sidebar::get()->addWidget($widget);

        $widget = new SelectWidget(_('Aufgabenblatt'), $this->url_for('vips/solutions/edit_solution', compact('assignment_id', 'solver_id', 'view')), 'exercise_id');

        foreach ($exercises as $exercise) {
            $element = new SelectElement($exercise['id'], sprintf(_('Aufgabe %d'), $exercise['position']), $exercise['id'] === $exercise_id);
            $widget->addElement($element);
        }
        Sidebar::get()->addWidget($widget);

        $widget = new SelectWidget(_('Versionen'), $this->url_for('vips/solutions/edit_solution', compact('assignment_id', 'exercise_id', 'solver_id', 'view')), 'solution_id');
        $element = new SelectElement(0, sprintf(_('Aktuelle Version: %s'), $version), !$archived_id);
        $widget->addElement($element);

        if (count($solution_archive) === 0) {
            $widget->attributes = ['disabled' => 'disabled'];
        }

        foreach ($solution_archive as $i => $old_solution) {
            $element = new SelectElement($old_solution->id,
                sprintf(_('Version %s vom %s'), count($solution_archive) - $i, date('d.m.Y, H:i', $old_solution->mkdate)),
                $old_solution->id == $archived_id);
            $widget->addElement($element);
        }
        Sidebar::get()->addWidget($widget);
    }

    /**
     * Display a student's corrected solution for a single exercise.
     */
    public function view_solution_action()
    {
        $exercise_id   = Request::int('exercise_id');
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);

        VipsModule::requireViewPermission($assignment, $exercise_id);

        $solver_id     = $GLOBALS['user']->id;
        $released      = $assignment->releaseStatus($solver_id);

        if ($released < VipsAssignment::RELEASE_STATUS_CORRECTIONS) {
            // the assignment is not finished or not yet released
            PageLayout::postError(_('Die Korrekturen des Aufgabenblatts sind nicht freigegeben.'));
            $this->redirect($this->url_for('vips/solutions/student_assignment_solutions', compact('assignment_id')));
            return;
        }

        $exercise = Exercise::find($exercise_id);
        $solution = $assignment->getSolution($solver_id, $exercise_id);

        if (!$solution) {
            $solution = new VipsSolution();
            $solution->assignment = $assignment;
        }

        // fetch previous and next exercises
        $prev_exercise_id = null;
        $next_exercise_id = null;
        $before_current   = true;

        foreach ($assignment->getExerciseRefs($solver_id) as $i => $item) {
            if ($item->task_id == $exercise_id) {
                $before_current = false;
                $exercise_position = $i + 1;
                $max_points = $item->points;
            } else if ($before_current) {
                $prev_exercise_id = $item->task_id;
            } else {
                $next_exercise_id = $item->task_id;
                break;
            }
        }

        $this->exercise          = $exercise;
        $this->exercise_position = $exercise_position;
        $this->assignment        = $assignment;
        $this->solution          = $solution;
        $this->show_solution     = $released == VipsAssignment::RELEASE_STATUS_SAMPLE_SOLUTIONS;
        $this->max_points        = $max_points;
        $this->prev_exercise_id  = $prev_exercise_id;
        $this->next_exercise_id  = $next_exercise_id;

        $widget = new SelectWidget(_('Aufgabenblatt'), $this->url_for('vips/solutions/view_solution', compact('assignment_id')), 'exercise_id');

        foreach ($assignment->getExerciseRefs($solver_id) as $i => $item) {
            $element = new SelectElement($item->task_id, sprintf(_('Aufgabe %d'), $i + 1), $item->task_id === $exercise->id);
            $widget->addElement($element);
        }
        Sidebar::get()->addWidget($widget);
    }

    /**
     * Restores an archived solution as the current solution.
     */
    public function restore_solution_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $solution_id       = Request::int('solution_id');
        $solver_id         = Request::option('solver_id');
        $view              = Request::option('view');

        $solution = VipsSolution::find($solution_id);

        $exercise_id = $solution->task_id;
        $assignment_id = $solution->assignment_id;
        $assignment = $solution->assignment;

        VipsModule::requireEditPermission($assignment);

        $assignment->restoreSolution($solution);
        PageLayout::postSuccess(_('Die ausgewählte Lösung wurde als aktuelle Version gespeichert.'));

        $this->redirect($this->url_for('vips/solutions/edit_solution', compact('exercise_id', 'assignment_id', 'solver_id', 'view')));
    }

    /**
     * Displays a student's event log for an assignment.
     */
    public function show_assignment_log_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $solver_id     = Request::option('solver_id');

        VipsModule::requireEditPermission($assignment);

        $assignment_attempt = $assignment->getAssignmentAttempt($solver_id);

        $this->user = User::find($solver_id);
        $this->logs = $assignment_attempt->getLogEntries();
    }

    /**
     * Offer to remove users from a group for this assignment.
     */
    public function edit_group_dialog_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $solver_id     = Request::option('solver_id');
        $view          = Request::option('view');

        VipsModule::requireEditPermission($assignment);

        $this->group   = $assignment->getUserGroup($solver_id);
        $this->members = $assignment->getGroupMembers($this->group);

        usort($this->members, function($a, $b) {
            return strcoll($a->user->getFullName('no_title_rev'), $b->user->getFullName('no_title_rev'));
        });

        $this->assignment = $assignment;
        $this->view       = $view;
    }

    /**
     * Update group assignment for a list of users for this assignment.
     */
    public function edit_group_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $group_id      = Request::option('group_id');
        $group         = VipsGroup::find($group_id);
        $user_ids      = Request::optionArray('user_ids');
        $view          = Request::option('view');

        VipsModule::requireEditPermission($assignment);

        if ($assignment->isFinished() && $user_ids) {
            foreach ($assignment->getGroupMembers($group) as $member) {
                if (in_array($member->user_id, $user_ids)) {
                    $clone = $member->build($member);
                    $member->end = $assignment->end;
                    $member->store();
                    $clone->start = $assignment->end;
                    $clone->store();
                }
            }

            PageLayout::postSuccess(_('Die ausgewählten Personen wurden aus der Gruppe entfernt.'));
        }

        $this->redirect($this->url_for('vips/solutions/assignment_solutions', compact('assignment_id', 'view')));
    }

    /**
     * Write a message to selected members for an assignment.
     */
    public function write_message_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $user_ids      = Request::optionArray('user_ids');

        VipsModule::requireEditPermission($assignment);

        foreach ($user_ids as $user_id) {
            $group = $assignment->getUserGroup($user_id);

            if ($group) {
                foreach ($assignment->getGroupMembers($group) as $member) {
                    $users[] = $member->username;
                }
            } else {
                $users[] = get_username($user_id);
            }
        }

        $_SESSION['sms_data']['p_rec'] = $users;
        $this->redirect(URLHelper::getURL('dispatch.php/messages/write'));
    }

    /**
     * Stores the lecturer comment and the corrected points for a solution.
     */
    public function store_correction_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $solution_id       = Request::int('solution_id');
        $solver_id         = Request::option('solver_id');
        $view              = Request::option('view');

        $feedback = trim(Request::get('feedback'));
        $feedback = Studip\Markup::purifyHtml($feedback);
        $file_ids          = Request::optionArray('file_ids');
        $corrected         = Request::int('corrected', 0);
        $reached_points    = Request::float('reached_points');
        $max_points        = Request::float('max_points');

        if ($solution_id) {
            $solution = VipsSolution::find($solution_id);
        } else {
            // create dummy empty solution
            $solution = new VipsSolution();
            $solution->task_id = Request::int('exercise_id');
            $solution->assignment_id = Request::int('assignment_id');
            $solution->user_id = $solver_id;
        }

        $exercise_id = $solution->task_id;
        $assignment_id = $solution->assignment_id;

        VipsModule::requireEditPermission($solution->assignment, $exercise_id);

        // let exercise class handle special controls added to the form
        $exercise = Exercise::find($exercise_id);
        $exercise->correctSolutionAction($this, $solution);

        if (Request::submitted('store_solution')) {
            // process lecturer's input
            $solution->state = $corrected;
            $solution->points = round($reached_points * 2) / 2;
            $solution->feedback = $feedback ?: null;

            if ($solution->points > $max_points) {
                PageLayout::postInfo(sprintf(_('Sie haben Bonuspunkte vergeben: %g von %g.'), $solution->points, $max_points));
            } else if ($solution->points < 0) {
                PageLayout::postWarning(sprintf(_('Sie haben eine negative Punktzahl eingegeben: %g von %g.'), $solution->points, $max_points));
            } else if ($solution->points != $reached_points) {
                PageLayout::postWarning(sprintf(_('Die eingegebene Punktzahl wurde auf halbe Punkte gerundet: %g.'), $solution->points));
            }

            $upload = $_FILES['upload'] ?: ['name' => []];

            if ($solution->isDirty() || count($upload)) {
                $solution->grader_id = $GLOBALS['user']->id;
                $solution->store();

                PageLayout::postSuccess(_('Ihre Korrektur wurde gespeichert.'));
            }

            $folder = Folder::findTopFolder($solution->id, 'FeedbackFolder', 'response');

            foreach ($folder->file_refs as $file_ref) {
                if (!in_array($file_ref->id, $file_ids) || in_array($file_ref->name, $upload['name'])) {
                    $file_ref->delete();
                }
            }

            FileManager::handleFileUpload($upload, $folder->getTypedFolder());
        }

        // show exercise and correction form again
        $this->redirect($this->url_for('vips/solutions/edit_solution', compact('exercise_id', 'assignment_id', 'solver_id', 'view')));
    }

    /**
     * Edit an active assignment attempt (update end time).
     */
    public function edit_assignment_attempt_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $solver_id     = Request::option('solver_id');
        $view          = Request::option('view');

        VipsModule::requireEditPermission($assignment);

        $this->assignment         = $assignment;
        $this->assignment_attempt = $assignment->getAssignmentAttempt($solver_id);
        $this->solver_id          = $solver_id;
        $this->view               = $view;
    }

    /**
     * Update an active assignment attempt (store end time).
     */
    public function store_assignment_attempt_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment    = VipsAssignment::find($assignment_id);
        $end_time      = trim(Request::get('end_time'));
        $solver_id     = Request::option('solver_id');
        $view          = Request::option('view');

        VipsModule::requireEditPermission($assignment);

        $assignment_attempt = $assignment->getAssignmentAttempt($solver_id);

        if ($assignment_attempt) {
            $end_day = date('Y-m-d', $assignment->getUserEndTime($solver_id));
            $end_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $end_day . ' ' . $end_time);

            if ($end_datetime) {
                $assignment_attempt->end = strtotime($end_datetime->format('Y-m-d H:i:s'));
                $assignment_attempt->store();

                if ($assignment_attempt->end > $assignment->end) {
                    PageLayout::postWarning(_('Der Abgabezeitpunkt liegt nach dem Ende der Klausur.'));
                }
            } else {
                PageLayout::postError(_('Der Abgabezeitpunkt ist keine gültige Uhrzeit.'));
            }
        }

        $this->redirect($this->url_for('vips/solutions/assignment_solutions', compact('assignment_id', 'view')));
    }

    /**
     * Deletes the solutions of a student (or all students) and resets the
     * assignment attempt.
     */
    public function delete_solutions_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);
        $solver_id = Request::option('solver_id');
        $view = Request::option('view');

        VipsModule::requireEditPermission($assignment);

        if ($assignment->type === 'exam') {
            if ($solver_id === 'all') {
                $assignment->deleteAllSolutions();
                PageLayout::postSuccess(_('Die Klausur wurde zurückgesetzt und alle abgegebenen Lösungen archiviert.'));
            } else if ($assignment->isRunning()) {
                $assignment->deleteSolutions($solver_id);
                PageLayout::postSuccess(_('Die Teilnahme wurde zurückgesetzt und ggf. abgegebene Lösungen archiviert.'));
            }
        }

        $this->redirect($this->url_for('vips/solutions/assignment_solutions', compact('assignment_id', 'view')));
    }



    /**
     * Shows all corrected exercises of an exercise sheet, if the status
     * of "released" allows that, i.e. is at least 1.
     */
    public function student_assignment_solutions_action()
    {
        $assignment_id = Request::int('assignment_id');
        $assignment = VipsAssignment::find($assignment_id);

        VipsModule::requireViewPermission($assignment);

        $this->assignment = $assignment;
        $this->user_id = $GLOBALS['user']->id;
        $this->released = $assignment->releaseStatus($this->user_id);
        $this->feedback = $assignment->getUserFeedback($this->user_id);

        // Security check -- is assignment really accessible for students?
        if ($this->released == VipsAssignment::RELEASE_STATUS_NONE) {
            PageLayout::postError(_('Die Korrekturen wurden noch nicht freigegeben.'));
            $this->redirect('vips/solutions');
            return;
        }

        Helpbar::get()->addPlainText('',
            _('Sie können hier die Ergebnisse bzw. die Korrekturen ihrer Aufgaben ansehen.'));

        if ($this->released >= VipsAssignment::RELEASE_STATUS_CORRECTIONS) {
            $widget = new ActionsWidget();
            $widget->addLink(
                _('Aufgabenblatt drucken'),
                $this->url_for('vips/sheets/print_assignments', ['assignment_id' => $assignment_id]),
                Icon::create('print'),
                ['target' => '_blank']
            );
            Sidebar::get()->addWidget($widget);
        }
    }



    /**
     * Displays all course participants and all their results (reached points,
     * percent, weighted percent) for all tests, blocks and exams plus their
     * final grade.
     */
    public function participants_overview_action()
    {
        $this->course_id = Context::getId();
        VipsModule::requireStatus('tutor', $this->course_id);

        $display = Request::option('display', 'points');
        $sort    = Request::option('sort', 'name');
        $desc    = Request::int('desc');
        $view    = Request::option('view');
        $format  = Request::option('format');

        $sem_class = Context::get()->getSemClass();
        $attributes = $this->participants_overview_data($this->course_id, null, $display, $sort, $desc, $view);

        $settings = CourseConfig::get($this->course_id);
        $this->has_grades = !empty($settings->VIPS_COURSE_GRADES);

        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }

        if ($format == 'csv') {
            $columns = [_('Nachname'), _('Vorname'), _('Kennung'), _('Matrikelnr.')];

            foreach ($this->items as $category => $list) {
                foreach ($list as $item) {
                    $columns[] = $item['name'];
                }
            }

            $columns[] = _('Summe');

            if ($display != 'points' && $this->has_grades) {
                $columns[] = _('Note');
            }

            $data = [$columns];

            if ($display == 'points' || $this->overall['weighting']) {
                if ($display == 'points') {
                    $row = [_('Maximalpunktzahl'), '', '', ''];
                } else {
                    $row = [_('Gewichtung'), '', '', ''];
                }

                foreach ($this->items as $category => $list) {
                    foreach ($list as $item) {
                        if ($display == 'points') {
                            $row[] = sprintf('%.1f', $item['points']);
                        } else {
                            $row[] = sprintf('%.1f%%', $item['weighting']);
                        }
                    }
                }

                if ($display == 'points') {
                    $row[] = sprintf('%.1f', $this->overall['points']);
                } else {
                    $row[] = sprintf('%.1f%%', 100);

                    if ($this->has_grades) {
                        $row[] = '';
                    }
                }

                $data[] = $row;
            }

            foreach ($this->participants as $p) {
                $row = [$p['surname'], $p['forename'], $p['username']];

                if (!$sem_class['studygroup_mode']) {
                    $row[] = $p['stud_id'];
                } else {
                    $row[] = '';
                }

                foreach ($this->items as $category => $list) {
                    foreach ($list as $item) {
                        if ($display == 'points') {
                            if (isset($p['items'][$category][$item['id']]['points'])) {
                                $row[] = sprintf('%.1f', $p['items'][$category][$item['id']]['points']);
                            } else {
                                $row[] = '';
                            }
                        } else {
                            if (isset($p['items'][$category][$item['id']]['percent'])) {
                                $row[] = sprintf('%.1f%%', $p['items'][$category][$item['id']]['percent']);
                            } else {
                                $row[] = '';
                            }
                        }
                    }
                }

                if ($display == 'points') {
                    if (isset($p['overall']['points'])) {
                        $row[] = sprintf('%.1f', $p['overall']['points']);
                    } else {
                        $row[] = '';
                    }
                } else {
                    if (isset($p['overall']['weighting'])) {
                        $row[] = sprintf('%.1f%%', $p['overall']['weighting']);
                    } else {
                        $row[] = '';
                    }

                    if ($this->has_grades) {
                        $row[] = $p['grade'];
                    }
                }

                $data[] = $row;
            }

            $this->render_csv($data, _('Notenliste.csv'));
        } else {
            Helpbar::get()->addPlainText('',
                _('Diese Seite gibt einen Überblick über die von allen Teilnehmenden erreichten Punkte und ggf. Noten.'));

            $widget = new ViewsWidget();
            $widget->addLink(
                _('Ergebnisse'),
                $this->url_for('vips/solutions')
            );
            $widget->addLink(
                _('Punkteübersicht'),
                $this->url_for('vips/solutions/participants_overview', ['display' => 'points'])
            )->setActive($display == 'points');
            $widget->addLink(
                _('Notenübersicht'),
                $this->url_for('vips/solutions/participants_overview', ['display' => 'weighting'])
            )->setActive($display == 'weighting');
            $widget->addLink(
                _('Statistik'),
                $this->url_for('vips/solutions/statistics')
            );
            Sidebar::get()->addWidget($widget);

            $widget = new ExportWidget();
            $widget->addLink(
                _('Liste im CSV-Format exportieren'),
                $this->url_for('vips/solutions/participants_overview', ['display' => $display, 'view' => $view, 'sort' => $sort, 'format' => 'csv']),
                Icon::create('export')
            );
            Sidebar::get()->addWidget($widget);
        }
    }

    public function statistics_action()
    {
        $course_id = Context::getId();
        VipsModule::requireStatus('tutor', $course_id);

        $db = DBManager::get();

        $format = Request::option('format');
        $assignments = [];

        $_assignments = VipsAssignment::findBySQL("range_id = ? AND type IN ('exam', 'practice') ORDER BY start", [$course_id]);

        foreach ($_assignments as $assignment) {
            $test_points     = 0;
            $test_average    = 0;
            $exercises       = [];

            foreach ($assignment->test->exercise_refs as $exercise_ref) {
                $exercise         = $exercise_ref->exercise;
                $exercise_points  = (float) $exercise_ref->points;
                $exercise_average = 0;
                $exercise_correct = 0;

                $exercise_item_count = $exercise->itemCount();
                $exercise_items      = array_fill(0, $exercise_item_count, 0);
                $exercise_items_c    = array_fill(0, $exercise_item_count, 0);

                $query = "SELECT etask_responses.* FROM etask_responses
                          LEFT JOIN seminar_user USING(user_id)
                          WHERE etask_responses.assignment_id = $assignment->id
                            AND etask_responses.task_id = $exercise->id
                            AND seminar_user.Seminar_id = '$course_id'
                            AND seminar_user.status = 'autor'";

                $solution_result = $db->query($query);
                $num_solutions = $solution_result->rowCount();

                foreach ($solution_result as $solution_row) {
                    $solution        = VipsSolution::buildExisting($solution_row);
                    $solution_points = (float) $solution->points;

                    if ($exercise_item_count > 1) {
                        $items = $exercise->evaluateItems($solution);
                        $item_scale = $exercise_points / max(count($items), 1);

                        foreach ($items as $index => $item) {
                            $exercise_items[$index] += $item['points'] * $item_scale / $num_solutions;

                            if ($item['points'] == 1) {
                                $exercise_items_c[$index] += 1 / $num_solutions;
                            }
                        }
                    }

                    if ($solution_points >= $exercise_points) {
                        ++$exercise_correct;
                    }

                    $exercise_average += $solution_points / $num_solutions;
                }

                $exercises[] = [
                    'id'       => $exercise->id,
                    'name'     => $exercise->title,
                    'position' => $exercise_ref->position,
                    'points'   => $exercise_points,
                    'average'  => $exercise_average,
                    'correct'  => $exercise_correct / max($num_solutions, 1),
                    'items'    => $exercise_items,
                    'items_c'  => $exercise_items_c
                ];

                $test_points += $exercise_points;
                $test_average += $exercise_average;
            }

            $assignments[] = [
                'assignment' => $assignment,
                'points'     => $test_points,
                'average'    => $test_average,
                'exercises'  => $exercises
            ];
        }

        $this->assignments = $assignments;

        if ($format == 'csv') {
            $columns = [
                _('Titel'),
                _('Aufgabe'),
                _('Item'),
                _('Erreichbare Punkte'),
                _('Durchschn. Punkte'),
                _('Korrekte Lösungen')
            ];

            $data = [$columns];

            foreach ($assignments as $assignment) {
                if (count($assignment['exercises'])) {
                    $data[] = [
                        $assignment['assignment']->test->title,
                        '',
                        '',
                        sprintf('%.1f', $assignment['points']),
                        sprintf('%.1f', $assignment['average']),
                        ''
                    ];

                    foreach ($assignment['exercises'] as $exercise) {
                        $data[] = [
                            $assignment['assignment']->test->title,
                            $exercise['position'] . '. ' . $exercise['name'],
                            '',
                            sprintf('%.1f', $exercise['points']),
                            sprintf('%.1f', $exercise['average']),
                            sprintf('%.1f%%', $exercise['correct'] * 100)
                        ];

                        if (count($exercise['items']) > 1) {
                            foreach ($exercise['items'] as $index => $item) {
                                $data[] = [
                                    $assignment['assignment']->test->title,
                                    $exercise['position'] . '. ' . $exercise['name'],
                                    sprintf(_('Item %d'), $index + 1),
                                    sprintf('%.1f', $exercise['points'] / count($exercise['items'])),
                                    sprintf('%.1f', $item),
                                    sprintf('%.1f%%', $exercise['items_c'][$index] * 100)
                                ];
                            }
                        }
                    }
                }
            }

            $this->render_csv($data, _('Statistik.csv'));
        } else {
            Helpbar::get()->addPlainText('',
                _('Diese Seite gibt einen Überblick über die im Durchschnitt von allen Teilnehmenden erreichten Punkte ' .
                      'sowie den Prozentsatz der vollständig korrekten Lösungen.'));

            $widget = new ViewsWidget();
            $widget->addLink(
                _('Ergebnisse'),
                $this->url_for('vips/solutions')
            );
            $widget->addLink(
                _('Punkteübersicht'),
                $this->url_for('vips/solutions/participants_overview', ['display' => 'points'])
            );
            $widget->addLink(
                _('Notenübersicht'),
                $this->url_for('vips/solutions/participants_overview', ['display' => 'weighting'])
            );
            $widget->addLink(
                _('Statistik'),
                $this->url_for('vips/solutions/statistics')
            )->setActive();
            Sidebar::get()->addWidget($widget);

            $widget = new ExportWidget();
            $widget->addLink(
                _('Liste im CSV-Format exportieren'),
                $this->url_for('vips/solutions/statistics', ['format' => 'csv']),
                Icon::create('export')
            );
            Sidebar::get()->addWidget($widget);
        }
    }

    /**
     * Get the internet host name corresponding to a given IP address.
     *
     * @param string $ip_address host IP address
     */
    public function gethostbyaddr(string $ip_address): ?string
    {
        static $hostname = [];

        if (!array_key_exists($ip_address, $hostname)) {
            $hostname[$ip_address] = gethostbyaddr($ip_address);
        }

        if ($hostname[$ip_address] !== $ip_address) {
            return $hostname[$ip_address];
        }

        return null;
    }

    /**
     * Get all exercise sheets belonging to course.
     */
    private function get_assignments_data($course_id, $user_id, $sort, $desc)
    {
        $assignments_array  = [];
        $m_sum_max_points   = 0; // holds the maximum points of all exercise sheets
        $sum_reached_points = 0; // holds the reached points of all assignments

        // find all assignments
        $assignments = VipsAssignment::findByRangeId($course_id);

        usort($assignments, function($a, $b) use ($sort) {
            if ($sort === 'title') {
                return strcoll($a->test->title, $b->test->title);
            } else if ($sort === 'start') {
                return strcmp($a->start, $b->start);
            } else {
                return strcmp($a->end ?: '~', $b->end ?: '~');
            }
        });

        if ($desc) {
            $assignments = array_reverse($assignments);
        }

        foreach ($assignments as $assignment) {
            $max_points = $assignment->test->getTotalPoints();

            // for students, get reached points
            if (!VipsModule::hasStatus('tutor', $course_id)) {
                $released = $assignment->releaseStatus($user_id);

                if ($assignment->isVisible($user_id) && $released > 0) {
                    $reached_points = $assignment->getUserPoints($user_id);
                    $sum_reached_points += $reached_points;
                    $m_sum_max_points += $max_points;
                } else {
                    continue;
                }
            } else {
                $released = $assignment->options['released'];
                $reached_points = null;
                $m_sum_max_points += $max_points;
            }

            // count uncorrected solutions
            $uncorrected_solutions = $this->count_uncorrected_solutions($assignment->id);

            $assignments_array[] = [
                'assignment'            => $assignment,
                'released'              => $released,
                'reached_points'        => $reached_points,
                'max_points'            => $max_points,
                'uncorrected_solutions' => $uncorrected_solutions
            ];
        }

        return [
            'assignments'        => $assignments_array,
            'sum_reached_points' => $sum_reached_points,
            'sum_max_points'     => $m_sum_max_points
        ];
    }

    private function participants_overview_data($course_id, $param_user_id, $display = null, $sort = null, $desc = null, $view = null)
    {
        $db = DBManager::get();

        // fetch all course participants //

        $participants = [];

        $sql = "SELECT user_id
                FROM seminar_user
                WHERE Seminar_id = ?
                  AND status NOT IN ('dozent', 'tutor')";
        $result = $db->prepare($sql);
        $result->execute([$course_id]);

        foreach ($result as $row) {
            $participants[$row['user_id']] = [];
        }

        // fetch all assignments with maximum points, assigned to blocks //
        // (if appropriate), and with weighting (if appropriate)         //

        $types = $view === 'selftest' ? ['selftest'] : ['exam', 'practice'];

        $sql = "SELECT etask_assignments.id,
                       etask_assignments.type,
                       etask_tests.title,
                       etask_assignments.end,
                       etask_assignments.weight,
                       etask_assignments.options,
                       etask_assignments.block_id,
                       SUM(etask_test_tasks.points) AS points,
                       etask_blocks.name AS block_name,
                       etask_blocks.weight AS block_weight
                FROM etask_assignments
                JOIN etask_tests ON etask_tests.id = etask_assignments.test_id
                LEFT JOIN etask_test_tasks
                  ON etask_test_tasks.test_id = etask_tests.id
                LEFT JOIN etask_blocks
                  ON etask_blocks.id = etask_assignments.block_id
                WHERE etask_assignments.range_id = ?
                  AND etask_assignments.type IN (?)
                GROUP BY etask_assignments.id
                ORDER BY etask_assignments.type DESC, etask_blocks.name, etask_assignments.start";
        $result = $db->prepare($sql);
        $result->execute([$course_id, $types]);

        // the result is ordered by
        //  * tests
        //  * blocks
        //  * exams
        // with ascending start points in each category

        $assignments    = [];
        $items          = [
            'tests'     => [],
            'blocks'    => [],
            'exams'     => []
        ];
        $overall_points = 0;
        $overall_weighting = 0;

        // each assignment
        foreach ($result as $row) {
            $assignment_id = (int) $row['id'];
            $test_type     = $row['type'];
            $test_title    = $row['title'];
            $points        = (float) $row['points'];
            $block_id      = $row['block_id'];
            $block_name    = $row['block_name'];
            $weighting     = (float) $row['weight'];

            $assignment = VipsAssignment::find($assignment_id);

            if (isset($block_id) && $row['block_weight'] !== null) {
                $category = 'blocks';

                // store assignment
                $assignments[$assignment_id] = [
                    'assignment' => $assignment,
                    'category'   => $category,
                    'item_id'    => $block_id
                ];

                // store item
                if (!isset($items[$category][$block_id])) {
                    $weighting = (float) $row['block_weight'];

                    // initialise block
                    $items[$category][$block_id] = [
                        'id'        => $block_id,
                        'item'      => VipsBlock::find($block_id),
                        'name'      => $block_name,
                        'tooltip'   => $block_name.': '.$test_title,
                        'points'    => 0,
                        'weighting' => $weighting
                    ];

                    // increase overall weighting (just once for each block!)
                    $overall_weighting += $weighting;
                } else {
                    // extend tooltip for existing block
                    $items[$category][$block_id]['tooltip'] .= ', '.$test_title;
                }

                // increase block's points (for each assignment)
                $items[$category][$block_id]['points'] += $points;

                // increase overall points (for each assignment)
                $overall_points += $points;
            } else {
                $category = $test_type === 'exam' ? 'exams' : 'tests';

                // store assignment
                $assignments[$assignment_id] = [
                    'assignment' => $assignment,
                    'category'   => $category,
                    'item_id'    => $assignment_id
                ];

                // store item
                $items[$category][$assignment_id] = [
                    'id'        => $assignment_id,
                    'item'      => $assignment,
                    'name'      => $test_title,
                    'tooltip'   => $test_title,
                    'points'    => $points,
                    'weighting' => $weighting
                ];

                // increase overall points and weighting
                $overall_points    += $points;
                $overall_weighting += $weighting;
            }
        }

        // overall sum column
        $overall = [
            'points'    => $overall_points,
            'weighting' => $overall_weighting
        ];

        if ($overall['weighting'] == 0 && count($assignments) > 0) {
            // if weighting is not used, all items weigh equally
            $equal_weight = 100 / (count($items['tests']) + count($items['blocks']) + count($items['exams']));

            foreach ($items as &$list) {
                foreach ($list as &$item) {
                    $item['weighting'] = $equal_weight;
                }
            }
        } else {
            foreach ($items as &$list) {
                foreach ($list as &$item) {
                    $item['weighting'] = 100 * $item['weighting'] / $overall['weighting'];
                }
            }
        }

        if (count($assignments) > 0) {

            // fetch all assignments, grouped and summed up by user       //
            // (assignments that are not solved by any user won't appear) //

            $sql = "SELECT etask_responses.assignment_id, etask_responses.user_id
                    FROM etask_responses
                    LEFT JOIN seminar_user
                      ON seminar_user.user_id = etask_responses.user_id
                         AND seminar_user.Seminar_id = ?
                    WHERE etask_responses.assignment_id IN (?)
                      AND (
                          seminar_user.status IS NULL OR
                          seminar_user.status NOT IN ('dozent', 'tutor')
                      )
                    GROUP BY etask_responses.assignment_id, etask_responses.user_id";
            $result = $db->prepare($sql);
            $result->execute([$course_id, array_keys($assignments)]);

            // each assignment
            foreach ($result as $row) {
                $assignment_id  = (int) $row['assignment_id'];
                $assignment     = $assignments[$assignment_id]['assignment'];
                $user_id        = $row['user_id'];
                $reached_points = $assignment->getUserPoints($user_id); // points in the assignment

                $category = $assignments[$assignment_id]['category'];
                $item_id  = $assignments[$assignment_id]['item_id'];

                $max_points  = $items[$category][$item_id]['points'];  // max points for the item
                $weighting   = $items[$category][$item_id]['weighting'];  // item weighting

                // recalc weighting based on item visibility
                $sum_weight = $this->participant_weight_sum($items, $user_id);

                if ($sum_weight && ($assignment->isVisible($user_id) || $assignment->getAssignmentAttempt($user_id))) {
                    $weighting = 100 * $weighting / $sum_weight;
                } else {
                    $weighting = 0;
                }

                // compute percent and weighted percent
                if ($max_points > 0) {
                    $percent          = 100 * $reached_points / $max_points;
                    $weighted_percent = $weighting * $reached_points / $max_points;
                } else {
                    $percent          = 0;
                    $weighted_percent = 0;
                }

                $group = $assignment->getUserGroup($user_id);

                if (isset($group)) {
                    $members = array_column($assignment->getGroupMembers($group), 'user_id');
                } else {
                    $members = [$user_id];
                }

                // tests //

                if ($category == 'tests') {
                    foreach ($members as $member_id) {
                        if (!isset($participants[$member_id]['items']['tests'][$item_id])) {
                            // store reached points, percent and weighted percent for this item, for each group member
                            $participants[$member_id]['items'][$category][$item_id] = [
                                'points'    => $reached_points,
                                'percent'   => $percent
                            ];

                            if (!isset($participants[$member_id]['overall'])) {
                                $participants[$member_id]['overall'] = ['points' => 0, 'weighting' => 0];
                            }

                            // sum up overall points and weighted percent
                            $participants[$member_id]['overall']['points']    += $reached_points;
                            $participants[$member_id]['overall']['weighting'] += $weighted_percent;
                        }
                    }
                }

                // blocks //

                if ($category == 'blocks') {
                    foreach ($members as $member_id) {
                        if (!isset($participants[$member_id]['items']['tests_seen'][$assignment_id])) {
                            $participants[$member_id]['items']['tests_seen'][$assignment_id] = true;

                            if (!isset($participants[$member_id]['items']['blocks'][$item_id])) {
                                $participants[$member_id]['items']['blocks'][$item_id] = ['points' => 0, 'percent' => 0];
                            }

                            // store reached points, percent and weighted percent for this item, for each group member
                            $participants[$member_id]['items']['blocks'][$item_id]['points']    += $reached_points;
                            $participants[$member_id]['items']['blocks'][$item_id]['percent']   += $percent;

                            if (!isset($participants[$member_id]['overall'])) {
                                $participants[$member_id]['overall'] = ['points' => 0, 'weighting' => 0];
                            }

                            // sum up overall points and weighted percent
                            $participants[$member_id]['overall']['points']    += $reached_points;
                            $participants[$member_id]['overall']['weighting'] += $weighted_percent;
                        }
                    }
                }

                // exams //

                if ($category == 'exams') {
                    // store reached points, percent and weighted percent for this item
                    $participants[$user_id]['items'][$category][$item_id] = [
                        'points'    => $reached_points,
                        'percent'   => $percent
                    ];

                    if (!isset($participants[$user_id]['overall'])) {
                        $participants[$user_id]['overall'] = ['points' => 0, 'weighting' => 0];
                    }

                    // sum up overall points and weighted percent
                    $participants[$user_id]['overall']['points']    += $reached_points;
                    $participants[$user_id]['overall']['weighting'] += $weighted_percent;
                }
            }
        }

        // if user_id parameter has been passed, delete all participants but the
        // requested user (this must take place AFTER all that has been done before
        // for to catch all group solutions)
        if (isset($param_user_id)) {
            $participants = [$param_user_id => $participants[$param_user_id]];
        }

        // get information for each participant
        foreach ($participants as $user_id => $rest) {
            $user = User::find($user_id);

            $participants[$user_id]['username'] = $user->username;
            $participants[$user_id]['forename'] = $user->vorname;
            $participants[$user_id]['surname']  = $user->nachname;
            $participants[$user_id]['name']     = $user->nachname . ', ' . $user->vorname;
            $participants[$user_id]['stud_id']  = $user->matriculation_number;
        }


        // sort participant array //

        $sort_by_name = function($a, $b) {  // sort by name
            return strcoll($a['name'], $b['name']);
        };

        $sort_by_points = function($a, $b) use ($sort_by_name) {  // sort by points (or name, if points are equal)
            if ($a['overall']['points'] == $b['overall']['points']) {
                return $sort_by_name($a, $b);
            } else {
                return $a['overall']['points'] < $b['overall']['points'] ? -1 : 1;
            }
        };

        $sort_by_grade = function($a, $b) use ($sort_by_name) {  // sort by grade (or name, if grade is equal)
            if ($a['overall']['weighting'] == $b['overall']['weighting']) {
                return $sort_by_name($a, $b);
            } else {
                return $a['overall']['weighting'] < $b['overall']['weighting'] ? -1 : 1;
            }
        };

        switch ($sort) {
            case 'sum':  // sort by sum row
                if ($display == 'points') {
                    uasort($participants, $sort_by_points);
                } else {
                    uasort($participants, $sort_by_grade);
                }
                break;

            case 'grade':  // sort by grade (or name, if grade is equal)
                uasort($participants, $sort_by_grade);
                break;

            case 'name':  // sort by name
            default:
                uasort($participants, $sort_by_name);
        }

        if ($desc) {
            $participants = array_reverse($participants, true);
        }

        // fetch grades from database
        $settings = CourseConfig::get($course_id);

        // grading is used
        if ($settings->VIPS_COURSE_GRADES) {
            foreach ($participants as $user_id => $participant) {
                $participants[$user_id]['grade'] = '5,0';

                if (isset($participant['overall'])) {
                    foreach ($settings->VIPS_COURSE_GRADES as $g) {
                        $grade     = $g['grade'];
                        $percent   = $g['percent'];
                        $comment   = $g['comment'];

                        if ($participant['overall']['weighting'] >= $percent) {
                            $participants[$user_id]['grade']         = $grade;
                            $participants[$user_id]['grade_comment'] = $comment;
                            break;
                        }
                    }
                }
            }
        }

        return [
            'display'        => $display,
            'sort'           => $sort,
            'desc'           => $desc,
            'view'           => $view,
            'items'          => $items,
            'overall'        => $overall,
            'participants'   => $participants
        ];
    }

    private function participant_weight_sum($items, $user_id)
    {
        static $weight_sum = [];

        if (!array_key_exists($user_id, $weight_sum)) {
            $weight_sum[$user_id] = 0;

            foreach ($items as $list) {
                foreach ($list as $item) {
                    if ($item['item']->isVisible($user_id) || $item['item']->getAssignmentAttempt($user_id)) {
                        $weight_sum[$user_id] += $item['weighting'];
                    }
                }
            }
        }

        return $weight_sum[$user_id];
    }

    /**
     * Get all solutions for an assignment.
     *
     * @param object $assignment The assignment
     * @param string|bool $view If set to the empty string, only users with solutions are
     *                    returned.  If set to string <code>all</code>, virtually
     *                    <i>all</i> course participants (including those who have
     *                    not delivered any solution) are returned.
     * @return Array An array consisting of <i>three</i> arrays, namely 'solvers'
     *               (containing all single solvers and groups), 'exercises'
     *               (containing all exercises in the assignment) and 'solutions'
     *               (containing all solvers and their solved exercises).
     */
    private function get_solutions($assignment, $view)
    {
        // get exercises //

        $exercises = [];

        foreach ($assignment->test->exercise_refs as $exercise_ref) {
            $exercise_id = (int) $exercise_ref->task_id;

            $exercises[$exercise_id] = [
                'id'        => $exercise_id,
                'title'     => $exercise_ref->exercise->title,
                'type'      => $exercise_ref->exercise->type,
                'position'  => (int) $exercise_ref->position,
                'points'    => (float) $exercise_ref->points
            ];
        }

        // get course participants //

        $solvers = [];
        $tutors = [];

        foreach ($assignment->course->members as $member) {
            $user_id = $member->user_id;
            $status  = $member->status;

            // don't include tutors and lecturers
            if ($status == 'tutor' || $status == 'dozent') {
                $tutors[$user_id] = $status;
            } else {
                $solvers[$user_id] = [
                    'type'      => 'single',
                    'id'        => $user_id,
                    'user_id'   => $user_id
                ];
            }
        }

        // get assignment attempts //

        foreach ($assignment->assignment_attempts as $attempt) {
            $user_id = $attempt->user_id;

            $solvers[$user_id] = [
                'type'      => 'single',
                'id'        => $user_id,
                'user_id'   => $user_id
            ];
        }

        // get solutions //

        $solutions = [];

        foreach ($assignment->solutions as $solution) {
            $exercise_id = (int) $solution->task_id;
            $user_id     = $solution->user_id;

            $solutions[$user_id][$exercise_id] = [
                'id'          => (int) $solution->id,
                'exercise_id' => $exercise_id,
                'user_id'     => $user_id,
                'time'        => $solution->mkdate,
                'corrected'   => (bool) $solution->state,
                'points'      => (float) $solution->points,
                'grader_id'   => $solution->grader_id,
                'feedback'    => $solution->feedback,
                'uploads'     => $solution->folder && count($solution->folder->file_refs)
            ];

            // solver may be a non-participant (and must not be a tutor)
            if (!isset($solvers[$user_id]) && !isset($tutors[$user_id])) {
                $solvers[$user_id] = [
                    'type'      => 'single',
                    'id'        => $user_id,
                    'user_id'   => $user_id
                ];
            }
        }

        /// NOTE: $solvers now *additionally* contains all students which have
        /// submitted a solution

        // get groups //

        $groups = [];

        if ($assignment->hasGroupSolutions()) {
            $all_groups = VipsGroup::findBySQL('range_id = ? ORDER BY name', [$assignment->range_id]);

            foreach ($all_groups as $group) {
                $members = $assignment->getGroupMembers($group);

                foreach ($members as $member) {
                    $group_id   = $group->id;
                    $user_id    = $member->user_id;

                    if (!isset($solvers[$user_id])) {
                        // add group member to $solvers
                        $solvers[$user_id] = [
                            'type'      => 'group_member',
                            'id'        => $user_id,
                            'user_id'   => $user_id
                        ];
                    } else {
                        // update type for existing solvers
                        $solvers[$user_id]['type'] = 'group_member';
                    }

                    if (!isset($groups[$group_id])) {
                        $groups[$group_id] = [
                            'type'    => 'group',
                            'id'      => $group_id,
                            'user_id' => $user_id,
                            'name'    => $group->name,
                            'members' => []
                        ];
                    }

                    // store which user is member of which group (user_id => group_id)
                    $map_user_to_group[$user_id] = $group_id;
                }
            }
        }

        /// NOTE: $solvers now *additionally* contains group members (if applicable)

        if (count($solvers)) {
            $result = User::findMany(array_keys($solvers));

            // get user names
            foreach ($result as $user) {
                $solvers[$user->id]['username'] = $user->username;
                $solvers[$user->id]['forename'] = $user->vorname;
                $solvers[$user->id]['surname']  = $user->nachname;
                $solvers[$user->id]['name']     = $user->nachname . ', ' . $user->vorname;
                $solvers[$user->id]['stud_id']  = $user->matriculation_number;
            }

            uasort($solvers, function($a, $b) {
                return strcoll($a['name'] ?? '', $b['name'] ?? '');
            });
        }

        // add groups to $solvers array //

        foreach ($groups as $group_id => $group) {
            $solvers[$group_id] = $group;
        }

        // sort single solvers to groups //

        foreach ($solvers as $solver_id => $solver) {
            if ($solver['type'] == 'group_member') {
                $group_id = $map_user_to_group[$solver_id];

                $solvers[$group_id]['members'][$solver_id] = $solver;  // store solver as group member
                unset($solvers[$solver_id]);  // delete him as single solver
            }
        }

        // change solution user ids to group ids //

        foreach ($solutions as $solver_id => $exercise_solutions) {
            if (isset($map_user_to_group[$solver_id])) {
                $group_id = $map_user_to_group[$solver_id];

                foreach ($exercise_solutions as $exercise_id => $solution) {
                    // always store most recent solution
                    if (!isset($solutions[$group_id][$exercise_id]) || $solution['time'] > $solutions[$group_id][$exercise_id]['time']) {
                        $solutions[$group_id][$exercise_id] = $solution;  // store solution as group solution
                    }
                    unset($solutions[$solver_id][$exercise_id]);  // delete single-solver-solution
                }
            }
        }

        // remove hidden solver entries //

        if ($assignment->type !== 'exam') {
            foreach ($solvers as $solver_id => $solver) {
                if (!isset($solutions[$solver_id])) {  // has no solutions
                    if (!$view || $view == 'todo') {
                        unset($solvers[$solver_id]);
                    }
                } else if ($view == 'todo') {
                    foreach ($solutions[$solver_id] as $solution) {
                        if (!$solution['corrected']) {
                            continue 2;
                        }
                    }

                    unset($solvers[$solver_id]);
                }
            }
        }

        return [
            'solvers'   => $solvers,    // ordered by name
            'exercises' => $exercises,  // ordered by position
            'solutions' => $solutions   // first single solvers then groups, furthermore unordered
        ];
    }

    /**
     * Counts uncorrected solutions for a assignment.
     *
     * @param $assignment_id The assignment id
     * @return <code>null</code> if there does not exist any solution at all, else
     *         the number of uncorrected solutions
     */
    private function count_uncorrected_solutions($assignment_id)
    {
        $db = DBManager::get();

        $assignment = VipsAssignment::find($assignment_id);
        $course_id = $assignment->range_id;

        // get all corrected and uncorrected solutions
        $sql = "SELECT etask_responses.task_id,
                       etask_responses.user_id,
                       etask_responses.state
                FROM etask_responses
                LEFT JOIN seminar_user
                  ON seminar_user.user_id = etask_responses.user_id
                     AND seminar_user.Seminar_id = ?
                WHERE etask_responses.assignment_id = ?
                  AND (
                      seminar_user.status IS NULL OR
                      seminar_user.status NOT IN ('dozent', 'tutor')
                  )
                ORDER BY etask_responses.id DESC";
        $result = $db->prepare($sql);
        $result->execute([$course_id, $assignment_id]);

        // no solutions at all
        if ($result->rowCount() == 0) {
            return null;
        }

        // count uncorrected solutions
        $uncorrected_solutions = 0;
        $solution = [];
        $group = [];

        foreach ($result as $row) {
            $exercise_id = (int) $row['task_id'];
            $user_id     = $row['user_id'];
            $corrected   = (bool) $row['state'];

            if (!array_key_exists($user_id, $group)) {
                $group[$user_id] = $assignment->getUserGroup($user_id);
            }

            if (!array_key_exists($exercise_id . '_' . $user_id, $solution)) {
                if (isset($group[$user_id])) {
                    $members = array_column($assignment->getGroupMembers($group[$user_id]), 'user_id');
                } else {
                    $members = [$user_id];
                }

                foreach ($members as $user_id) {
                    $solution[$exercise_id . '_' . $user_id] = true;
                }

                if (!$corrected) {
                    $uncorrected_solutions++;
                }
            }
        }

        return $uncorrected_solutions;
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
}
