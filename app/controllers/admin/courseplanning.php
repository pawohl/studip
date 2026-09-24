<?php
/**
 * Lehrveranstaltungsplanungskomponente
 *
 * @author  Timo Hartge <hartge@data-quest.de>
 * @license GPL2 or any version
 * @since   Stud.IP 4.5
 */
class Admin_CourseplanningController extends AuthenticatedController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (!$GLOBALS['perm']->have_perm('admin')) {
            throw new AccessDeniedException();
        }

        Navigation::activateItem('/browse/my_courses/schedule');

        $this->insts = Institute::getMyInstitutes($GLOBALS['user']->id);

        if (empty($this->insts) && !$GLOBALS['perm']->have_perm('root')) {
            PageLayout::postError(_('Sie wurden noch keiner Einrichtung zugeordnet'));
        }

        // Semester selection
        if ($GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE) {
            $this->semester = Semester::find($GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE);
        }

        if (Request::submitted('teacher_filter')) {
            $GLOBALS['user']->cfg->store('ADMIN_COURSES_TEACHERFILTER', Request::option('teacher_filter'));
        }

        if (in_array($action, ['index', 'weekday'])) {
            PageLayout::allowFullscreenMode();
        }
        $this->selected_weekday = '';
    }

    private function getPlanTitle()
    {
        $plan_title = _('Veranstaltungs-Stundenplan');
        if ($GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT && $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT != 'all') {
            $inst = Institute::find($GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT);
            $plan_title .= ' - ' . $inst['Name'];
        }
        if ($GLOBALS['user']->cfg->MY_COURSES_SELECTED_STGTEIL && $GLOBALS['user']->cfg->MY_COURSES_SELECTED_STGTEIL != 'all') {
            $stgteil = StudiengangTeil::find($GLOBALS['user']->cfg->MY_COURSES_SELECTED_STGTEIL);
            $plan_title .= ' - ' . $stgteil->getDisplayName();
        }
        if (isset($this->semester) && $GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE
        ) {
            $plan_title .= ' - ' . $this->semester->name;
        }
        return $plan_title;
    }

    public function index_action()
    {
        PageLayout::setHelpKeyword('Basis.TerminkalenderStundenplan');
        PageLayout::setTitle(_('Veranstaltungs-Stundenplan'));

        // build the sidebar:
        $this->buildSidebar($GLOBALS['user']->cfg->MY_COURSES_TYPE_FILTER);
        $sidebar = Sidebar::get();
        $actions = $sidebar->addWidget(new ActionsWidget());
        $actions->addLink(
            _('Drucken'),
            'javascript:void(window.print());',
            Icon::create('print')
        );

        Sidebar::get()->getWidget('actions')->addLink(
            _('Ansichtsoptionen'),
            $this->tilefilterURL('overview'),
            Icon::create('admin')
        )->asDialog('size=400x350');

        $this->courses = $this->getFilteredCourses();
        $this->events = InstituteCalendarHelper::getEvents(
            $this->courses,
            $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT,
            $this->semester
        );
        $this->eventless_courses = InstituteCalendarHelper::getEventlessCourses($this->courses, $this->semester);
        $this->plan_title = $this->getPlanTitle();

        foreach ($this->events as $event) {
            $start_date_time = explode('T', $event['start']);
            $time_elements = explode(':', $start_date_time[1]);
            if (empty($event['conform']) || $time_elements[0] % 2) {
                Sidebar::get()->getWidget('actions')->addLink(
                    _('Veranstaltungen außerhalb des Rasters'),
                    $this->nonconformURL(),
                    Icon::create('date-cycle')
                )->asDialog();

                break;
            }
        }
    }

    public function weekday_action($day_of_week)
    {
        PageLayout::setHelpKeyword('Basis.TerminkalenderStundenplan');
        PageLayout::setTitle(_('Veranstaltungs-Stundenplan'));

        $this->selected_weekday = $day_of_week;

        $fixed_day_of_week = ($day_of_week == 0) ? 7 : $day_of_week;
        $sub_days = date('w') - $fixed_day_of_week;
        $cal_date = new DateTime();
        if ($sub_days > 0) {
            $cal_date->sub(new DateInterval('P' . $sub_days . 'D'));
        } else {
            $cal_date->add(new DateInterval('P' . -$sub_days . 'D'));
        }

        //build the sidebar:
        $this->buildSidebar($GLOBALS['user']->cfg->MY_COURSES_TYPE_FILTER);
        $sidebar = Sidebar::get();
        $actions = $sidebar->addWidget(new ActionsWidget());
        $actions->addLink(
            _('Veranstaltungs-Stundenplan PDF'),
            'javascript:STUDIP.Fullcalendar.downloadPDF("landscape", true);',
            Icon::create('file-pdf')
        );

        Sidebar::get()->getWidget('actions')->addLink(
            _('Spalten Anzeige'),
            $this->viewcolumnsURL($cal_date->format('w')),
            Icon::create('admin')
        )->asDialog('size=auto');

        Sidebar::get()->getWidget('actions')->addLink(
            _('Ansichtsoptionen'),
            $this->tilefilterURL('weekday', $day_of_week),
            Icon::create('admin')
        )->asDialog('size=400x350');

        $this->cal_date = $cal_date;
        $this->courses = $this->getFilteredCourses();
        $this->events = InstituteCalendarHelper::getEvents($this->courses, $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT, $this->semester, $day_of_week);
        $this->eventless_courses = InstituteCalendarHelper::getEventlessCourses($this->courses, $this->semester);
        $this->columns = InstituteCalendarHelper::getResourceColumns($GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT, true);
        $this->plan_title = $this->getPlanTitle();

        foreach ($this->events as $event) {
            $start_date_time = explode('T', $event['start']);
            $time_elements = explode(':', $start_date_time[1]);
            if (!$event['conform'] || $time_elements[0] % 2) {
                Sidebar::get()->getWidget('actions')->addLink(
                    _('Veranstaltungen außerhalb des Rasters'),
                    $this->nonconformURL($day_of_week),
                    Icon::create('date-cycle')
                )->asDialog('size=medium');

                break;
            }
        }
    }

    public function remove_column_action($institute_id, $column_id, $week_day)
    {
        $inst_column = InstitutePlanColumn::find([$institute_id, $column_id]);
        if ($inst_column) {
            $col_name = $inst_column->name;

            if ($institute_id !== 'all') {

                $datafield_entries = DatafieldEntryModel::findBySQL(
                    "`datafield_id` = :df_id AND `content` LIKE :institut_id",
                    [
                        'df_id'       => InstituteCalendarHelper::COLUMN_DATAFIELD_ID,
                        'institut_id' => "%{$institute_id}%",
                    ]
                );
                foreach ($datafield_entries as $df) {
                    $df_content = unserialize($df->content);
                    foreach ($df_content as $cdate => $inst_col) {
                        if (!is_array($inst_col)) {
                            continue;
                        }
                        foreach ($inst_col as $inst => $col) {
                            if ($inst == $institute_id && $col == $column_id) {
                                $df_content[$cdate][$inst] = 0;
                            }
                        }
                    }
                    $df->content = serialize($df_content);
                    $df->store();
                }

                if ($inst_column->delete()) {
                    PageLayout::postSuccess(sprintf(
                        _('Die Spalte %s wurde gelöscht.'),
                        htmlReady($col_name)
                    ));
                }
            }
        }

        $redirect_url = $this->url_for('admin/courseplanning/weekday/' . $week_day);
        $this->relocate($redirect_url);
    }

    public function rename_column_action($column_id, $week_day)
    {
        PageLayout::setTitle(_('Spalte umbenennen'));

        $redirect_url = $this->url_for('admin/courseplanning/weekday/' . $week_day);
        $institute_id = $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT;

        if ($institute_id) {
            if (Request::submitted('save')) {
                $column_name = Request::get('column_name');
                if ($column_name) {
                    $instplan_col = InstitutePlanColumn::find([$institute_id, $column_id]);
                    if (!$instplan_col) {
                        $stored = InstituteCalendarHelper::addResourceColumn($institute_id, $column_name, $column_id);
                    } else {
                        $instplan_col->name = $column_name;
                        $stored = $instplan_col->store();
                    }
                    if ($stored) {
                        PageLayout::postSuccess(_('Die Spalte wurde umbenannt.'));
                        $this->relocate($redirect_url);
                        return false;
                    }
                }
            }

            $inst_columns = InstituteCalendarHelper::getResourceColumns($institute_id);
            foreach ($inst_columns as $inst_column) {
                if ($inst_column['id'] == $column_id) {
                    $column_name = $inst_column['title'];
                }
            }

            $this->column_id = $column_id;
            $this->column_name = $column_name;
            $this->week_day = $week_day;
        } else {
            PageLayout::postError(_('Bei der Ermittlung der gewählten Einrichtung ist ein Fehler aufgetreten.'));
            $this->relocate($redirect_url);
        }
    }

    public function viewcolumns_action($week_day)
    {
        PageLayout::setTitle(_('Spalten Anzeige'));

        $redirect_url = $this->url_for('admin/courseplanning/weekday/' . $week_day);
        $institute_id = $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT;

        $this->columns =  InstituteCalendarHelper::getResourceColumns($institute_id);
        $this->week_day = $week_day;

        if (Request::submitted('save')) {
            $vis_cols = [];
            $invis_cols = [];
            foreach ($this->columns as $col) {
                if ($col['id'] == 0) continue;
                if (in_array($col['id'], Request::getArray('column_view')) && $col['visible'] != '1' ) {
                    $vis_cols[] = $col['id'];
                } else if (!in_array($col['id'], Request::getArray('column_view')) && $col['visible'] != '0') {
                    $invis_cols[] = $col['id'];
                }
            }

            if (!empty($vis_cols)) {
                $changes = InstitutePlanColumn::setVisbilities($institute_id, $vis_cols, 1);
                if ($changes) PageLayout::postSuccess(sprintf(_('%s Spalte(n) sichtbar geschaltet.'), $changes));
            }
            if (!empty($invis_cols)) {
                $changes = InstitutePlanColumn::setVisbilities($institute_id, $invis_cols, 0);
                if ($changes) PageLayout::postSuccess(sprintf(_('%s Spalte(n) ausgeblendet.'), $changes));
            }

            if (Request::get('column_name')) {
                InstituteCalendarHelper::addResourceColumn($institute_id, Request::get('column_name'));
                PageLayout::postSuccess(_('Spalte hinzugefügt.'));
            }

            $this->relocate($redirect_url);
        }

        $this->institute_id = $institute_id;

    }

    public function nonconform_action($day_of_week = null)
    {
        $courses = $this->getFilteredCourses();
        $non_rasters = [];

        $min_time = explode(':',Config::get()->INSTITUTE_COURSE_PLAN_START_HOUR);
        $minbigtime = (int) $min_time[0];
        $max_time = explode(':',Config::get()->INSTITUTE_COURSE_PLAN_END_HOUR);
        $maxbigtime = (int) $max_time[0];

        foreach ($courses as $cid => $course_data) {
            $course = Course::find($cid);

            foreach (SeminarCycleDate::findBySeminar($cid) as $cycle_date) {
                if (is_numeric($day_of_week) && $day_of_week != $cycle_date['weekday']) {
                    continue;
                }

                $conform = true;

                $start_time = explode(':', $cycle_date['start_time']);
                $bigtime = (int) $start_time[0];
                if ($bigtime > $maxbigtime || $bigtime < $minbigtime) {
                    $conform = false;
                } elseif ($bigtime % 2) {
                    $bigtime--;
                }
                $start_time = "{$bigtime}:00:00";

                $end_time = explode(':', $start_time);
                $end_time[0] += 2;
                $end_time = implode(':', $end_time);

                if (!$conform
                    || ($start_time != $cycle_date['start_time'] && $end_time != $cycle_date['end_time'])
                ) {
                    $non_rasters[] = [
                        'cid'   => $cid,
                        'nr'    => $course->veranstaltungsnummer,
                        'name'  => $course->name,
                        'start' => $cycle_date['start_time'],
                        'end'   => $cycle_date['end_time'],
                    ];
                }
            }
        }

        $this->non_conform_dates = $non_rasters;
    }

    public function add_event_action($course_id)
    {
        $start       = Request::getDateTime('start', DateTimeInterface::RFC3339_EXTENDED);
        $end         = Request::getDateTime('end', DateTimeInterface::RFC3339_EXTENDED);
        $now         = new DateTime();
        $success     = false;
        $cycle_start = null;
        $cycle_end   = null;
        $course      = Course::find($course_id);

        if ($course && $this->semester && $start && $end) {
            //$start and $end are in UTC/Zulu timezone. Their values have to be converted
            //to the timezone of the server to get correct results.
            $start->setTimezone($now->getTimezone());
            $end->setTimezone($now->getTimezone());
            if (count($course->semesters) > 1) {
                //The course spans over more than one semester.
                $start_weeks = $course->start_semester->getStartWeeks($course->end_semester);

                $sem_weeks       = $this->semester->getStartWeeks();
                $sem_weeks_start = explode(' Semesterwoche ', $sem_weeks[0]);
                $sem_weeks_end   = explode(' Semesterwoche ', end($sem_weeks));

                foreach ($start_weeks as $week_num => $week_text) {
                    if ($cycle_start && $cycle_end) break;
                    if (strstr($week_text, $sem_weeks_start[1]) !== false) {
                        $cycle_start = $week_num;
                    }
                    if (strstr($week_text, $sem_weeks_end[1]) !== false) {
                        $cycle_end = $week_num;
                    }
                }
            }

            $cycle              = new SeminarCycleDate();
            $cycle->seminar_id  = $course_id;
            $cycle->weekday     = $start->format('w');
            $cycle->description = '';
            $cycle->sws         = floatVal(0.0);
            $cycle->cycle       = 0;
            $cycle->week_offset = $cycle_start ? $cycle_start : 0;
            $cycle->end_offset  = $cycle_end ? $cycle_end : intval(count($this->semester->getStartWeeks()) -1);
            $cycle->start_time  = $start->format('H:i:s');
            $cycle->end_time    = $end->format('H:i:s');
            $success = $cycle->store();
        }

        if ($success) {
            if (Request::submitted('resource_id')) {
                InstituteCalendarHelper::setCourseEventcolumn(
                    Course::find($cycle->seminar_id),
                    $cycle->metadate_id,
                    $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT,
                    Request::get('resource_id', '0')
                );
            }
            echo json_encode(InstituteCalendarHelper::getCycleEvent($cycle, $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT));
        }

        $this->render_nothing();
    }

    public function pick_color_action($metadate_id, $from_action, $weekday = null)
    {
        PageLayout::setTitle(_('Farbwähler'));

        $this->available_colours = [
            'yellow'      => '#ffbd33',
            'orange'      => '#f26e00',
            'red'         => '#d60000',
            'violet'      => '#b02e7c',
            'dark-violet' => '#682c8b',
            'green'       => '#6ead10',
            'dark-green'  => '#008512',
            'petrol'      => '#0E817B',
            'brown'       => '#a85d45'
        ];

        $cdate = SeminarCycleDate::find($metadate_id);
        if ($cdate) {
            $course = Course::find($cdate->Seminar_id);
            $semtype = $course->getSemType();

            if (Request::submitted('save') && Request::submitted('event_colour')) {
                $selected_colour_index = Request::get('event_colour');
                if (Request::get('event_colour_semtype')) {
                    if (InstituteCalendarHelper::setSemtypeEventcolor(
                        Course::find($cdate->seminar_id),
                        $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT,
                        $this->available_colours[$selected_colour_index] ?? '',
                    )) {
                        PageLayout::postSuccess(sprintf(
                            _('Die Farbe wurde allen VA des Typs %s zugewiesen.'),
                            htmlReady($semtype['name'])
                        ));
                    } else {
                        PageLayout::postError(_('Bei der Farbzuweisung ist ein Fehler aufgetreten.'));
                    }
                } else {
                    if (InstituteCalendarHelper::setCourseEventcolor(
                        Course::find($cdate->seminar_id),
                        $metadate_id,
                        $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT,
                        $this->available_colours[$selected_colour_index] ?? ''
                    )) {
                        PageLayout::postSuccess(_('Die Farbe wurde der VA zugewiesen.'));
                    } else {
                        PageLayout::postError(_('Bei der Farbzuweisung ist ein Fehler aufgetreten.'));
                    }
                }

                $this->relocate($this->action_url($from_action, $weekday));
                return;
            }

            $course_colors = InstituteCalendarHelper::getCourseEventcolors($course);
            if (array_key_exists($metadate_id, $course_colors)) {
                $this->color = $course_colors[$metadate_id][$GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT] ?? '';
            } else {
                $this->color = '#28497c';
            }

            $this->semtype = $semtype['name'];
        }
        $this->metadate_id = $metadate_id;
        $this->from_action = $from_action;
        $this->weekday     = $weekday;
    }

    public function move_event_action($cycle_date_id)
    {
        $start = Request::getDateTime('start', DateTimeInterface::RFC3339_EXTENDED);
        $end   = Request::getDateTime('end', DateTimeInterface::RFC3339_EXTENDED);
        $success = false;

        $cdate = SeminarCycleDate::find($cycle_date_id);
        if ($cdate) {
            if (Request::submitted('resource_id')) {
                InstituteCalendarHelper::setCourseEventcolumn(
                    Course::find($cdate->seminar_id),
                    $cycle_date_id,
                    $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT,
                    Request::get('resource_id', '0')
                );
            }

            $weekday           = $start->format('w');
            $cdate->start_time = $start->format('H:i:s');
            $cdate->end_time = $end->format('H:i:s');
            $cdate->weekday = $weekday;
            $success = $cdate->store();
        }

        $this->render_text($success);
    }

    private function getFilteredCourses()
    {
        if (Request::get('sortFlag')) {
            $GLOBALS['user']->cfg->store('MEINE_SEMINARE_SORT_FLAG', Request::get('sortFlag') == 'asc' ? 'DESC' : 'ASC');
        }
        if (Request::option('sortby')) {
            $GLOBALS['user']->cfg->store('MEINE_SEMINARE_SORT', Request::option('sortby'));
        }

        $this->sortby = $GLOBALS['user']->cfg->MEINE_SEMINARE_SORT;
        $this->sortFlag = $GLOBALS['user']->cfg->MEINE_SEMINARE_SORT_FLAG ?: 'ASC';

        return $this->getCourses([
            'sortby'      => $this->sortby,
            'sortFlag'    => $this->sortFlag,
            'view_filter' => [],//$this->view_filter,
            'typeFilter'  => $GLOBALS['user']->cfg->MY_COURSES_TYPE_FILTER,
            'datafields' => []//$this->getDatafieldFilters()
        ], Request::get('display') === 'all');
    }

    /**
     * This method is responsible for building the sidebar.
     *
     * Depending on the sidebar elements the user has selected some of those
     * elements are shown or not. To find out what elements
     * the user has selected the user configuration is accessed.
     *
     * @param string courseTypeFilterConfig The selected value for the course type filter field, defaults to null.
     * @return null This method does not return any value.
     */
    private function buildSidebar($courseTypeFilterConfig = null)
    {
        $this->setInstSelector();
        $this->setSemesterSelector();
        $this->setStgteilSelector();
        $this->setCourseTypeWidget($courseTypeFilterConfig);
        $this->setTeacherWidget();
    }


    /**
     * Returns the default element configuration.
     *
     * @return array containing the default element configuration
     */
    private function getActiveElementsDefault()
    {
        return [
            'search'     => false,
            'institute'  => true,
            'semester'   => true,
            'courseType' => true,
            'teacher'    => true,
            'viewFilter' => false,
        ];
    }

    /**
     * Returns the active element configuration of the current user.
     *
     * @return array containing the active element configuration
     */
    private function getActiveElements()
    {
/*         $active_elements = $GLOBALS['user']->cfg->ADMIN_COURSES_SIDEBAR_ACTIVE_ELEMENTS;

        if ($active_elements) {
            return json_decode($active_elements, true);
        } else { */
            return $this->getActiveElementsDefault();
        //}
    }


    /**
     * Adds the institutes selector to the sidebar
     */
    private function setInstSelector()
    {
        $sidebar = Sidebar::Get();
        $list = new SelectWidget(
            _('Einrichtung'),
            $this->url_for('admin/courseplanning/set_selection/' . $this->selected_weekday),
            'institute'
        );
        $list->class = 'institute-list';

        foreach ($this->insts as $institut) {
            if (!$GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT ||$GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT == 'all') {
                $GLOBALS['user']->cfg->store('MY_INSTITUTES_DEFAULT', $institut['Institut_id']);
            }

            $list->addElement(
                new SelectElement(
                    $institut['Institut_id'],
                    (!$institut['is_fak'] ? ' ' : '') . $institut['Name'],
                    $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT === $institut['Institut_id']
                ),
                "select-{$institut['Institut_id']}"
            );

            //check if the institute is a faculty.
            //If true, then add another option to display all courses
            //from that faculty and all its institutes.

            //$institut is an array, we can't use the method isFaculty() here!
            if ($institut['fakultaets_id'] == $institut['Institut_id']) {
                $list->addElement(
                    new SelectElement(
                        $institut['Institut_id'] . '_withinst', //_withinst = with institutes
                        ' ' . $institut['Name'] . ' +' . _('Institute'),
                        ($GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT === $institut['Institut_id'] && $GLOBALS['user']->cfg->MY_INSTITUTES_INCLUDE_CHILDREN)
                    ),
                    'select-' . $institut['Name'] . '-with_institutes'
                );
            }
        }

        $sidebar->addWidget($list, 'filter_institute');
    }

    /**
     * Adds the semester selector to the sidebar
     */
    private function setSemesterSelector()
    {
        $semesters = array_reverse(Semester::getAll());
        $sidebar = Sidebar::Get();
        $list = new SelectWidget(_('Semester'), $this->url_for('admin/courseplanning/set_selection/' . $this->selected_weekday), 'sem_select');
        foreach ($semesters as $semester) {
            if (!$GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE) {
                $GLOBALS['user']->cfg->store('MY_COURSES_SELECTED_CYCLE', $semester->id);
            }
            $list->addElement(new SelectElement(
                $semester->id,
                $semester->name,
                $semester->id === $GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE
            ), "sem_select-{$semester->id}");
        }

        $sidebar->addWidget($list, 'filter_semester');
    }

        /**
     * Adds the studiengangteil selector to the sidebar
     */
    private function setStgteilSelector()
    {
        $stgteile = StudiengangTeil::getAllEnriched('fach_name','ASC', ['mvv_fach_inst.institut_id' => $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT]);
        $sidebar = Sidebar::Get();
        $list = new SelectWidget(_('Studiengangteil'), $this->url_for('admin/courseplanning/set_selection/' . $this->selected_weekday), 'stgteil_select');
        $list->addElement(new SelectElement('all', _('Alle')), 'stgteil_select-all');
        foreach ($stgteile as $stgteil) {
            $list->addElement(new SelectElement(
                $stgteil->id,
                $stgteil->getDisplayName(),
                $stgteil->id === $GLOBALS['user']->cfg->MY_COURSES_SELECTED_STGTEIL
            ), 'stgteil_select-' . $stgteil->id);
        }

        $sidebar->addWidget($list, 'filter_stgteil');
    }

    /**
     * Returns a course type widthet depending on all available courses and theirs types
     * @param string $selected
     * @param array $params
     */
    private function setCourseTypeWidget($selected = 'all')
    {
        $sidebar = Sidebar::get();
        $this->url = $this->url_for('admin/courseplanning/set_course_type');
        $this->types = [];
        $this->selected = $selected;

        $list = new SelectWidget(
            _('Veranstaltungstypfilter'),
            $this->url_for('admin/courseplanning/set_course_type'),
            'course_type'
        );
        $list->addElement(new SelectElement(
            'all', _('Alle'), $selected === 'all'
        ), 'course-type-all');
        foreach ($GLOBALS['SEM_CLASS'] as $class_id => $class) {
            if ($class['studygroup_mode']) {
                continue;
            }

            $element = new SelectElement(
                $class_id,
                $class['name'],
                $selected === (string)$class_id
            );
            $list->addElement(
                $element->setAsHeader(),
                'course-type-' . $class_id
            );

            foreach ($class->getSemTypes() as $id => $result) {
                $element = new SelectElement(
                    $class_id . '_' . $id,
                    $result['name'],
                    $selected === $class_id . '_' . $id
                );
                $list->addElement(
                    $element->setIndentLevel(1),
                    'course-type-' . $class_id . '_' . $id
                );
            }
        }
        $sidebar->addWidget($list, 'filter-course-type');
    }

    /**
     * Returns a widget to selected a specific teacher
     * @param array $teachers
     */
    private function setTeacherWidget()
    {
        if (!$GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT || $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT === "all") {
            return;
        }
        $teachers = DBManager::get()->fetchAll("
            SELECT auth_user_md5.*, user_info.*
            FROM auth_user_md5
                LEFT JOIN user_info ON (auth_user_md5.user_id = user_info.user_id)
                INNER JOIN user_inst ON (user_inst.user_id = auth_user_md5.user_id)
                INNER JOIN Institute ON (Institute.Institut_id = user_inst.Institut_id)
            WHERE (Institute.Institut_id = :institut_id OR Institute.fakultaets_id = :institut_id)
                AND auth_user_md5.perms = 'dozent'
            ORDER BY auth_user_md5.Nachname ASC, auth_user_md5.Vorname ASC
        ", [
            'institut_id' => $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT
        ],
        function ($data) {
            $ret['user_id'] = $data['user_id'];
            unset($data['user_id']);
            $ret['fullname'] = User::build($data)->getFullName("full_rev");
            return $ret;
        }
        );


        $sidebar = Sidebar::Get();
        $list = new SelectWidget(_('Lehrendenfilter'), $this->url_for('admin/courseplanning/index'), 'teacher_filter');
        $list->addElement(new SelectElement('', _('alle'), Request::get('teacher_filter') === ''), 'teacher_filter-all');

        foreach ($teachers as $teacher) {
            $list->addElement(new SelectElement(
                $teacher['user_id'],
                $teacher['fullname'],
                $GLOBALS['user']->cfg->ADMIN_COURSES_TEACHERFILTER === $teacher['user_id']
            ), 'teacher_filter-' . $teacher['user_id']);
        }

        $sidebar->addWidget($list, 'filter_teacher');
    }

    /**
     * Returns the teacher for a given cours
     *
     * @param String $course_id Id of the course
     * @return array of user infos [user_id, username, Nachname, fullname]
     */
    private function getTeacher($course_id): array
    {
        $teachers   = CourseMember::findByCourseAndStatus($course_id, 'dozent');
        $collection = SimpleCollection::createFromArray($teachers);
        return $collection->map(function (CourseMember $teacher) {
            return [
                'user_id'  => $teacher->user_id,
                'username' => $teacher->username,
                'Nachname' => $teacher->nachname,
                'fullname' => $teacher->getUserFullname('no_title_rev'),
            ];
        });
    }

    /**
     * Returns all courses matching set criteria.
     *
     * @param Array $params Additional parameters
     * @param bool $display_all should we show all courses or check for a limit of 500 courses?
     * @return Array of courses
     */
    private function getCourses($params = [], $display_all = false): array
    {
        $filter = AdminCourseFilter::get(true);
        $this->count_courses = $filter->countCourses();
        if ($this->count_courses && ($this->count_courses <= $filter->max_show_courses || $display_all)) {
            $courses = $filter->getCourses();
        } else {
            return [];
        }

        if (in_array('contents', $params['view_filter'])) {
            $sem_types = SemType::getTypes();
        }

        $seminars = [];
        foreach ($courses as $course) {
            $seminars[$course->id] = $course->toArray();

            $seminars[$course->id]['seminar_id'] = $course->id;
            $seminars[$course->id]['obj_type'] = 'sem';
            $dozenten = $this->getTeacher($course->id);
            $seminars[$course->id]['dozenten'] = $dozenten;

            if (in_array('contents', $params['view_filter'])) {
                $tools = new SimpleCollection(ToolActivation::findbyRange_id($course->id, "ORDER BY position"));
                $visit_data = get_objects_visits([$course->id], 0, null, null, $tools->pluck('plugin_id'));
                $seminars[$course->id]['tools'] = $tools;
                $seminars[$course->id]['visitdate'] = $visit_data[$course->id][0]['visitdate'];
                $seminars[$course->id]['last_visitdate'] = $visit_data[$course->id][0]['last_visitdate'];
                $seminars[$course->id]['sem_class'] = $sem_types[$course->status]->getClass();
                $seminars[$course->id]['navigation'] = MyRealmModel::getAdditionalNavigations(
                    $course->id,
                    $seminars[$course->id],
                    $seminars[$course->id]['sem_class'],
                    $GLOBALS['user']->id,
                    $visit_data[$course->id]
                );
            }
            //add last activity column:
            if (in_array('last_activity', $params['view_filter'])) {
                $seminars[$course->id]['last_activity'] = lastActivity($course->id);
            }
            if ($this->selected_action == 17) {
                $seminars[$course->id]['admission_locked'] = false;
                if ($course->course_set) {
                    $set = new CourseSet($course->course_set);
                    if (!is_null($set) && $set->hasAdmissionRule('LockedAdmission')) {
                        $seminars[$course->id]['admission_locked'] = 'locked';
                    } else {
                        $seminars[$course->id]['admission_locked'] = 'disable';
                    }
                    unset($set);
                }
            }
        }

        return $seminars;
    }


    /**
     * Set the selected institute or semester
     */
    public function set_selection_action($week_day = NULL)
    {
        if (Request::option('institute')) {
            $GLOBALS['user']->cfg->store('ADMIN_COURSES_TEACHERFILTER', null);
            $GLOBALS['user']->cfg->store('MY_COURSES_SELECTED_STGTEIL', null);
            $inst = explode('_', Request::option('institute'));
            $GLOBALS['user']->cfg->store('MY_INSTITUTES_DEFAULT', $inst[0]);

            if ($inst[1] === 'withinst') {
                $GLOBALS['user']->cfg->store('MY_INSTITUTES_INCLUDE_CHILDREN', 1);
            } else {
                $GLOBALS['user']->cfg->store('MY_INSTITUTES_INCLUDE_CHILDREN', 0);
            }

            PageLayout::postSuccess(_('Die gewünschte Einrichtung wurde ausgewählt!'));
        }

        if (Request::option('sem_select')) {
            $GLOBALS['user']->cfg->store('MY_COURSES_SELECTED_CYCLE', Request::option('sem_select'));
            if (Request::option('sem_select') !== '') {
                PageLayout::postSuccess(sprintf(
                    _('Das %s wurde ausgewählt'),
                    htmlReady(Semester::find(Request::option('sem_select'))->name)
                ));
            } else {
                PageLayout::postSuccess(_('Semesterfilter abgewählt'));
            }
        }

        if (Request::option('stgteil_select')) {
            $GLOBALS['user']->cfg->store('MY_COURSES_SELECTED_STGTEIL', Request::option('stgteil_select'));
            if (Request::option('stgteil_select') !== 'all') {
                PageLayout::postSuccess(sprintf(
                    _('Der Studiengangteil %s wurde ausgewählt'),
                    htmlReady(StudiengangTeil::find(Request::option('stgteil_select'))->getDisplayName())
                ));
            } else {
                PageLayout::postSuccess(_('Studiengangteilfilter abgewählt'));
            }
        }

        if ($week_day) {
            $this->redirect('admin/courseplanning/weekday/' . $week_day);
        } else {
            $this->redirect('admin/courseplanning/index');
        }
    }

    /**
     * Set the selected course type filter and store the selection in configuration
     */
    public function set_course_type_action()
    {
        if (Request::option('course_type')) {
            $GLOBALS['user']->cfg->store('MY_COURSES_TYPE_FILTER', Request::option('course_type'));
            PageLayout::postSuccess(_('Der gewünschte Veranstaltungstyp wurde übernommen!'));
        }
        $this->redirect('admin/courseplanning/index');
    }

    /**
     * Sets filters for the title on the date tile
     */
    public function tilefilter_action($view = null, $weekday = null)
    {
        PageLayout::setTitle(_('Angezeigte Veranstaltungsdaten'));

        $config = User::findCurrent()->getConfiguration();

        $this->render_vue_app(
            Studip\VueApp::create('CoursePlanningTileFilter')
                ->withProps([
                    'view' => $view,
                    'weekday' => $weekday,
                    'config' => [
                        'course_number' => (bool) $config->getValue('TIMETABLE_COURSE_NUMBER_VISIBLE'),
                        'course_name'   => (bool) $config->getValue('TIMETABLE_COURSE_NAME_VISIBLE'),
                        'lecturers'     => (bool) $config->getValue('TIMETABLE_LECTURERS_VISIBLE'),
                        'rooms'         => (bool) $config->getValue('TIMETABLE_ROOMS_VISIBLE'),
                    ]
                ])
        );

        $this->view = $view;
        $this->weekday = $weekday;
        $this->config = UserConfig::get($GLOBALS['user']->id);
    }

    public function store_tilefilter_action($view = null, $weekday = null)
    {
        CSRFProtection::verifyUnsafeRequest();

        $GLOBALS['user']->cfg->store('TIMETABLE_COURSE_NUMBER_VISIBLE', Request::bool('course_number'));
        $GLOBALS['user']->cfg->store('TIMETABLE_COURSE_NAME_VISIBLE', Request::bool('course_name'));
        $GLOBALS['user']->cfg->store('TIMETABLE_LECTURERS_VISIBLE', Request::bool('lecturers'));
        $GLOBALS['user']->cfg->store('TIMETABLE_ROOMS_VISIBLE', Request::bool('rooms'));

        if ($view === 'overview') {
            $this->redirect('admin/courseplanning/index');
        } elseif ($view === 'weekday') {
            $this->redirect('admin/courseplanning/weekday/' . $weekday);
        }

        PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));
    }
}
