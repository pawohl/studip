<?php
/**
 * admin/overlapping.php - controller to check for overlapping
 * courses in Stud.IP
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Peter Thienel <thienel@data-quest.de>
 * @copyright   2018 Stud.IP Core-Group
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       4.4
 */

class Admin_OverlappingController extends AuthenticatedController
{
    private ?string $view = null;

    /**
     * Common before filter for all actions.
     *
     * @param String $action Called actions
     * @param Array  $args   Passed arguments
     */
    public function before_filter(&$action, &$args)
    {
        if (!$GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }
        parent::before_filter($action, $args);

        Navigation::activateItem('/browse/my_courses/overlapping');
        URLHelper::bindLinkParam('view', $this->view);
        if (Request::option('sem_select')) {
            $GLOBALS['user']->cfg->store('MY_COURSE_SELECTED_CYCLE', Request::option('sem_select'));
        }
        $this->selected_semester = Semester::find($GLOBALS['user']->cfg->MY_COURSE_SELECTED_CYCLE);
        if (!$this->selected_semester) {
            $this->selected_semester = Semester::findCurrent();
        }
        PageLayout::setTitle(_('Überschneidung von Veranstaltungen'));
    }

    /**
     * Main view: Shows selection form and result.
     *
     * @return void
     */
    public function index_action()
    {
        $this->view = 'index';
        $this->setSidebar();
        $selection_id = Request::option('selection', $_SESSION['MVV_OVL_SELECTION_ID'] ?? null);
        $selections = SimpleORMapCollection::createFromArray(
            MvvOverlappingSelection::findBySQL('`selection_id` = ? AND `user_id` = ?', [
                $selection_id,
                $GLOBALS['user']->id
            ])
        );

        $_SESSION['MVV_OVL_SELECTION_ID'] = $selection_id;
        $this->selection_id = '';
        if (count($selections)) {
            $this->base_version = StgteilVersion::find($selections->first()->base_version_id);
            $this->fachsems = $selections->first()->fachsems === '' ? [] : explode(',', $selections->first()->fachsems);
            $this->semtypes = $selections->first()->semtypes === '' ? [] : explode(',', $selections->first()->semtypes);
            $this->comp_versions = StgteilVersion::findMany($selections->pluck('comp_version_id'));
            $this->selection_id = $selections->first()->selection_id;
            if (Request::int('show_hidden') !== null) {
                $_SESSION['MVV_OVL_HIDDEN'] = Request::int('show_hidden');
            }
        } else {
            $this->base_version = StgteilVersion::find(Request::option('base_version'));
            $this->comp_versions = StgteilVersion::findMany(Request::optionArray('comp_versions'));
            $this->fachsems = Request::intArray('fachsems');
            $this->semtypes = Request::intArray('semtypes');
        }
        $this->base_version_id = $this->base_version->id ?? '';
        $this->comp_versions_ids = SimpleCollection::createFromArray($this->comp_versions)->pluck('id');
        $this->stgteil_versions = $this->getStgteilVersions();
        $this->conflicts = MvvOverlappingSelection::getConflictsBySelection(
            $this->selection_id,
            empty($_SESSION['MVV_OVL_HIDDEN'])
        );

        $version_options = [];
        foreach ($this->getStgteilVersions() as $base_version) {
            $version_options[$base_version->id] = $base_version->getDisplayName();
        }
        $this->form = \Studip\Forms\Form::create();
        $this->fieldset = new \Studip\Forms\Fieldset(_('Auswahl'));
        $this->fieldset->addInput(
            new \Studip\Forms\SelectInput(
                'base_version',
                _('Studiengangteil'),
                $this->base_version_id,
                [
                    'options' => $version_options
                ]
            )
        )->setRequired();
        $this->fieldset->addInput(
            new \Studip\Forms\MultiselectInput(
                'comp_versions',
                _('Vergleichs-Studiengangteile'),
                $this->comp_versions_ids,
                [
                    'options' => $version_options
                ]
            )
        );
        $fsem_options = [];
        for ($fsem = 1; $fsem < 7; $fsem++) {
            $fsem_options[$fsem] = sprintf(_('%s Fachsemester'),
                $fsem . ModuleManagementModel::getLocaleOrdinalNumberSuffix($fsem));
        }
        $this->fieldset->addInput(
            new \Studip\Forms\MultiselectInput(
                'fachsems',
                _('Fachsemester'),
                $this->fachsems,
                [
                    'options' => $fsem_options
                ]
            )
        );
        $sem_class_options = [];
        foreach ($GLOBALS['SEM_CLASS'] as $class_id => $class) {
            if ($class['studygroup_mode']) continue;
            foreach ($class->getSemTypes() as $id => $type) {
                $sem_class_options[$id] = sprintf('%s (%s)', $type['name'], $class['name']);
            }
        }
        $this->fieldset->addInput(
            new \Studip\Forms\MultiselectInput(
                'semtypes',
                _('Veranstaltungstypen'),
                $this->semtypes,
                [
                    'options' => $sem_class_options
                ]
            )
        );
        $this->fieldset->addInput(
            new \Studip\Forms\CheckboxInput(
                'show_hidden',
                _('Ausgeblendete Veranstaltungen anzeigen'),
                $_SESSION['MVV_OVL_HIDDEN'] ?? '0'
            )
        );
        $this->form->addPart($this->fieldset);
        $this->form->setURL($this->indexURL())
            ->setCollapsable(true)
            ->setDataSecure(false)
            ->setSaveButtonText(_('Vergleichen'))
            ->setCancelButtonText(_('Zurücksetzen'))
            ->addStoreCallback(function (\Studip\Forms\Form $form, array $values) {
                return $this->check($values);
            })
            ->autoStore();
    }

    /**
     * Resets form and shows index view.
     *
     * @return void
     */
    public function reset_action()
    {
        $this->setSidebar('index');
        $_SESSION['MVV_OVL_HIDDEN'] = 0;
        $_SESSION['MVV_OVL_SELECTION_ID'] = '';
        $this->conflicts = [];
        $this->redirect('admin/overlapping/index');
    }

    /**
     * Calculates the conflicts for the submitted selection (store callback
     * of the selection form).
     *
     * @param array $values The submitted form values.
     * @return int Number of stored selections.
     * @throws Exception
     */

    private function check(array $values): int
    {
        $stored = 0;
        $this->base_version = StgteilVersion::find($values['base_version'] ?? '');
        if ($this->base_version) {
            $this->comp_versions = [];
            foreach ((array) ($values['comp_versions'] ?? []) as $comp_version_id) {
                $this->comp_versions[] = StgteilVersion::find($comp_version_id);
            }
            // if no comparison version, check base version for internal conflicts
            if (count($this->comp_versions) == 0) {
                $this->comp_versions[$this->base_version->id] = $this->base_version;
            }
            $this->fachsems = array_map('intval', (array) ($values['fachsems'] ?? []));
            $this->semtypes = array_map('intval', (array) ($values['semtypes'] ?? []));

            $selection_id = MvvOverlappingSelection::createSelectionId(
                $this->base_version,
                $this->comp_versions,
                $this->fachsems,
                $this->semtypes,
                $this->selected_semester->id
            );

            // refresh conflicts
            MvvOverlappingConflict::deleteBySelection($selection_id);

            foreach ($this->comp_versions as $comp_version) {
                $selection[$comp_version->id] = MvvOverlappingSelection::findOneBySQL(
                    '`selection_id` = ? AND `comp_version_id` = ?', [
                    $selection_id,
                    $comp_version->id
                ]);
                if (!$selection[$comp_version->id]) {
                    $selection[$comp_version->id] = new MvvOverlappingSelection();
                    $selection[$comp_version->id]->semester_id = $this->selected_semester->id;
                    $selection[$comp_version->id]->selection_id = $selection_id;
                    $selection[$comp_version->id]->base_version_id = $this->base_version->id;
                    $selection[$comp_version->id]->comp_version_id = $comp_version->id;
                    $selection[$comp_version->id]->setFachsemester($this->fachsems);
                    $selection[$comp_version->id]->setCourseTypes($this->semtypes);
                    $selection[$comp_version->id]->user_id = $GLOBALS['user']->id;
                    $selection[$comp_version->id]->store();
                }
                $selection[$comp_version->id]->storeConflicts();
                $stored++;
            }
            $conflicts = MvvOverlappingSelection::getConflictsBySelection($selection_id);
            $visible_conflicts = MvvOverlappingSelection::getConflictsBySelection($selection_id, true);
            if (count($conflicts)) {
                if (count($conflicts) !== count($visible_conflicts)) {
                    PageLayout::postSuccess(
                        sprintf(
                            ngettext('1 Konflikt gefunden (1 ausgeblendet)',
                                '%s Konflikte gefunden (%s ausgeblendet).', count($conflicts)),
                            count($conflicts),
                            count($conflicts) - count($visible_conflicts)
                        )
                    );
                } else {
                    PageLayout::postSuccess(
                        sprintf(
                            ngettext('1 Konflikt gefunden.',
                                '%s Konflikte gefunden.', count($conflicts)),
                            count($conflicts)
                        )
                    );
                }
            } else {
                PageLayout::postSuccess(_('Keine Konflikte gefunden.'));
            }

            $_SESSION['MVV_OVL_SELECTION_ID'] = $selection_id;
        } else {
            PageLayout::postError(_('Die Basis-Version muss angegeben werden!'));
        }
        $_SESSION['MVV_OVL_HIDDEN'] = (int) ($values['show_hidden'] ?? 0);

        return $stored;
    }

    /**
     * Shows the responsible admin of the course.
     *
     * @param string $conflict_id The id of the conflict.
     * @return void
     */
    public function admin_info_action(string $conflict_id)
    {
        $this->conflict = MvvOverlappingConflict::find($conflict_id);
        $this->version = $this->conflict->comp_abschnitt->version;
        $this->course = $this->conflict->comp_course;
        if ($this->course && $this->version) {
            $this->admins = InstituteMember::findByInstituteAndStatus($this->course->institut_id, 'admin');
        } else {
            PageLayout::postMessage(MessageBox::error(_('Unbekannte Veranstaltung.')));
        }
        $this->selected_view = 'admin_info';
    }

    /**
     * Shows the course details.
     *
     * @param string $conflict_id The id of the conflict.
     * @return void
     */
    public function course_info_action(string $conflict_id)
    {
        $this->conflict = MvvOverlappingConflict::find($conflict_id);
        $this->course = $this->conflict->comp_course;
        $this->version = $this->conflict->comp_abschnitt->version;
        if ($this->course && $this->version) {
            $response = $this->relay('course/details');
            $this->content = $response->body;
        } else {
            PageLayout::postMessage(MessageBox::error(_('Unbekannte Veranstaltung oder Version.')));
        }
        $this->selected_view = 'course_info';
        $this->render_template('admin/overlapping/info_dialog');
    }

    /**
     * Sets a course as hidden.
     *
     * @param int $conflict_id The id of the conflict.
     * @return void
     */
    public function exclude_action(int $conflict_id)
    {
        $conflict = MvvOverlappingConflict::find($conflict_id);
        if ($conflict->selection->user_id === $GLOBALS['user']->id) {
            $exclude = new MvvOverlappingExclude(
                [
                    $conflict->selection->selection_id,
                    $conflict->comp_course_id
                ]
            );
            if ($exclude->isNew()) {
                $success = $exclude->store();
            } else {
                $success = $exclude->delete();
            }
            $this->set_status($success ? 204 : 400);
        } else {
            $this->set_status(403);
        }
        $this->relocate('admin/overlapping/' . $this->view);
    }

    /**
     * Shows information of the study course version.
     *
     * @param string $conflict_id The id of the conflict.
     * @return void
     * @throws \Flexi\TemplateNotFoundException
     * @throws \Trails\Exceptions\DoubleRenderError
     */
    public function version_info_action(string $conflict_id)
    {
        $this->conflict = MvvOverlappingConflict::find($conflict_id);
        if (empty($this->conflict)) {
            throw new InvalidArgumentException();
        }
        $this->version = $this->conflict->comp_abschnitt->version;
        $this->course = $this->conflict->comp_course;
        if ($this->version && $this->course) {
            $response = $this->relay('search/studiengaenge/verlauf/' . $this->version->stgteil_id
                    . "/?semester={$this->selected_semester->id}&version={$this->version->id}");
            $this->content = $response->body;
        } else {
            PageLayout::postError(_('Unbekannte Studiengangteil-Version.'));
        }
        $this->selected_view = 'info';
        $this->render_template('admin/overlapping/info_dialog');
    }

    /**
     * Shows the planer view of conflicts.
     *
     * @param string $selection_id The id of the selection.
     * @return void
     */
    public function planer_action(string $selection_id = '')
    {
        $this->view = 'planer';
        $this->setSidebar();

        $selection_id = $selection_id ?: $_SESSION['MVV_OVL_SELECTION_ID'] ?? null;

        $this->fullcalendar = \Studip\Fullcalendar::create(
            _('Kalender'),
            [
                'editable'    => false,
                'selectable'  => false,
                'studip_urls' => '',
                'dialog_size' => 'auto',
                'minTime'     => sprintf('%02u:00', 8),
                'maxTime'     => sprintf('%02u:00', 21),
                'defaultDate' => date('Y-m-d', $this->selected_semester->vorles_beginn),
                'allDaySlot'  => false,
                'allDayText'  => '',
                'headerToolbar' => [
                    'start'  => false,
                    'center' => $this->selected_semester->name,
                    'end'    => false,
                ],
                'weekNumbers' => false,
                'views' => [
                    \Studip\Fullcalendar::VIEW_WEEK => [
                        'dayHeaderFormat' => ['weekday' => 'short', 'omitCommas' => true],
                        'weekends'           => true,
                        'slotDuration'       => '00:30:00'
                    ],
                ],
                'initialView' => \Studip\Fullcalendar::VIEW_WEEK,
                'timeGridEventMinHeight' => 20,
                'eventSources' => [
                    [
                        'url' => $this->conflictsURL($selection_id),
                        'method' => 'GET',
                        'extraParams' => []
                    ]
                ],
                'nowIndicator' => false
            ],
            ['class' => 'resource-plan semester-plan']
        );

        // get selected StgteilVersions colors
        $this->selections = MvvOverlappingSelection::findBySQL(
            '`selection_id` = ? ORDER BY `comp_version_id`',
            [$selection_id]
        );
    }

    /**
     * Retrieves all conflicts for the given selection.
     *
     * @param $selection_id The id of the selection.
     * @return void
     */
    public function conflicts_action($selection_id)
    {
        $selections = MvvOverlappingSelection::findBySQL(
            '`selection_id` = ? ORDER BY `comp_version_id`',
            [$selection_id]
        );
        $conflicting_metadates = [];
        foreach ($selections as $selection) {
            foreach ($selection->conflicts as $conflict) {
                $event_data = $this->createEventFromConflict($conflict, true);
                $base_index = $conflict->base_course->id . $event_data->begin->getTimestamp();
                $conflicting_metadates[$base_index] = $event_data->toFullcalendarEvent();
                $event_data = $this->createEventFromConflict($conflict);
                $comp_index = $conflict->comp_course->id . $event_data->begin->getTimestamp();
                $conflicting_metadates[$comp_index] = $event_data->toFullcalendarEvent();
            }
        }
        $this->render_json(array_values($conflicting_metadates));
    }

    /**
     * Shows a serialized view of the conflict.
     *
     * @param string $conflict_id The id of the conflict.
     * @return void
     */
    public function course_conflict_action(string $conflict_id)
    {
        $this->conflict = MvvOverlappingConflict::find($conflict_id);
        if (empty($this->conflict)) {
            throw new InvalidArgumentException();
        }
        $this->conflicts = SimpleORMapCollection::createFromArray([$this->conflict]);
        $this->base_version = $this->conflict->base_abschnitt->version;
        $this->version = $this->conflict->comp_abschnitt->version;
        $this->course = $this->conflict->comp_course;
        $this->selected_view = 'conflict';
    }

    /**
     * Shows the conflict in a dialog.
     *
     * @param string $conflict_id The id of the conflict.
     * @return void
     * @throws \Flexi\TemplateNotFoundException
     * @throws \Trails\Exceptions\DoubleRenderError
     */
    public function conflict_action(string $conflict_id)
    {
        $this->conflict = MvvOverlappingConflict::find($conflict_id);
        if (empty($this->conflict)) {
            throw new InvalidArgumentException();
        }
        $this->version = $this->conflict->comp_abschnitt->version;
        $this->course = $this->conflict->comp_course;
        PageLayout::setTitle($this->course->getFullName(
            Config::get()->IMPORTANT_SEMNUMBER
                ? 'number-type-name'
                : 'type-name'
            )
        );
        $this->content = '';
        if (empty($this->conflict->comp_course)) {
            PageLayout::postError(_('Unbekannte Veranstaltung.'));
        } else {
            Request::set('sem_id', $this->conflict->comp_course_id);
            $this->course = $this->conflict->comp_course;
            $this->version = $this->conflict->comp_abschnitt->version;
            $response = $this->relayWithRedirect('course/details/index');
            $this->content = $response->body;
        }
        $this->selected_view = 'conflict';
        $this->render_template('admin/overlapping/info_dialog');
    }

    /**
     * Creates EventData from conflicts.
     *
     * @param MvvOverlappingConflict $conflict The conflict object.
     * @return \Studip\Calendar\EventData The event data.
     */
    private function createEventFromConflict(MvvOverlappingConflict $conflict, $base = false): \Studip\Calendar\EventData
    {
        static $color_mapping = [];

        $weekday_mapping =[
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
        ];
        if ($base) {
            $version = $conflict->selection->comp_version;
            $col_version_id = $conflict->selection->base_version->id;
            $cycle = $conflict->base_cycle;
            $course = $conflict->base_course;
        } else {
            $version = $conflict->selection->base_version;
            $col_version_id = $conflict->selection->comp_version->id;
            $cycle = $conflict->comp_cycle;
            $course = $conflict->comp_course;
        }
        if (empty($color_mapping[$col_version_id])) {
            $color_mapping[$col_version_id] = count($color_mapping) + 1;
        }
        $color_pos = $color_mapping[$col_version_id];
        $text_color = Config::get()->PERS_TERMIN_KAT[$color_pos]['fgcolor'];
        $background_color = Config::get()->PERS_TERMIN_KAT[$color_pos]['bgcolor'];
        $border_color = Config::get()->PERS_TERMIN_KAT[$color_pos]['border_color'];
        $begin = new DateTime();
        $begin->setTimestamp($this->selected_semester->vorles_beginn);
        $begin->modify(
            $weekday_mapping[$cycle->weekday]
            . ' this week '
            . $cycle->start_time
        );
        $end = clone $begin;
        $end->modify('today ' . $cycle->end_time);
        return new \Studip\Calendar\EventData(
            $begin,
            $end,
            Config::get()->IMPORTANT_SEMNUMBER
                ? $course->getFullName('number-type-name')
                : $course->getFullName('type-name'),
            ['user-date', 'user-date-category1'],
            $text_color ?? '#ffffff',
            $background_color ?? '#000000',
            false,
            'MvvOverlappingConflict',
            $conflict->id,
            'MvvOverlappingSelection',
            $conflict->selection->id,
            'user',
            $conflict->selection->user_id,
            [
                'show'   => $this->course_conflictURL($conflict->id)
            ],
            [],
            '',
            $border_color ?? '#ffffff'
        );
    }

    /**
     * Init the sidebar content.
     *
     * @return void
     */
    private function setSidebar()
    {
        $sidebar = Sidebar::Get();

        $views = new ViewsWidget();
        $views->addLink(
            _('Listenansicht'),
            $this->indexURL()
        )->setActive($this->view === 'index');
        $views->addLink(
            _('Planeransicht'),
            $this->planerURL()
        )->setActive($this->view === 'planer');
        $sidebar->addWidget($views);

        $semester_selector = new SelectWidget(
            _('Semesterauswahl'),
            $this->url_for('admin/overlapping/reset'),
            'sem_select'
        );
        foreach (array_reverse(Semester::getAll()) as $semester) {
            $semester_selector->addElement(new SelectElement(
                    $semester->id,
                    $semester->name,
                    $semester->id === $this->selected_semester->id
                ), 'sem_select-' . $semester->id
            );
        }
        $sidebar->addWidget($semester_selector);
    }

    /**
     * Search for base version by given search term.
     */
    public function base_version_action()
    {
        $sword = Request::get('term');
        $this->render_text(json_encode($this->getResult($sword)));
    }

    /**
     * Get Studiengangteilversionen for selection, filtered by start and end semester and status (only public).
     *
     * @return StgteilVersion[]
     */
    private function getStgteilVersions(): array
    {
        // get public status from config
        $public_status = array_keys(array_filter(
            $GLOBALS['MVV_STGTEILVERSION']['STATUS']['values'],
            function ($v) {
                return $v['public'];
            }
        ));

        return StgteilVersion::findBySQL(
            "JOIN `mvv_stgteil` USING(`stgteil_id`)
             JOIN `fach` USING(`fach_id`)
             JOIN `semester_data` AS `start_sem`
               ON `mvv_stgteilversion`.`start_sem` = `start_sem`.`semester_id`
             LEFT JOIN `semester_data` AS `end_sem`
               ON `mvv_stgteilversion`.`end_sem` = `end_sem`.`semester_id`
             WHERE (`start_sem`.`beginn` <= :sem_end)
               AND (`end_sem`.`ende` >= :sem_start OR ISNULL(`end_sem`.`ende`))
               AND `mvv_stgteilversion`.`stat` IN (:status)
             ORDER BY `fach`.`name`, `mvv_stgteil`.`kp`",
            [
                ':sem_start' => $this->selected_semester->beginn,
                ':sem_end'   => $this->selected_semester->ende,
                ':status'    => $public_status
            ]
        );
    }

}
