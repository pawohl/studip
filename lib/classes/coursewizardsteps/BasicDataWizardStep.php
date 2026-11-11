<?php
/**
 * BasicDataWizardStep.php
 * Course wizard step for getting the basic course data.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Thomas Hackl <thomas.hackl@uni-passau.de>
 * @copyright   2015 Stud.IP Core-Group
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */

class BasicDataWizardStep implements CourseWizardStep
{
    /**
     * Returns the Flexi template for entering the necessary values
     * for this step.
     *
     * @param Array $values Pre-set values
     * @param int $stepnumber which number has the current step in the wizard?
     * @param String $temp_id temporary ID for wizard workflow
     * @return String a Flexi template for getting needed data.
     */
    public function getStepTemplate($values, $stepnumber, $temp_id)
    {
        // Load template from step template directory.
        $factory = new Flexi\Factory($GLOBALS['STUDIP_BASE_PATH'] . '/app/views/course/wizard/steps');
        if (!empty($values[__CLASS__]['studygroup'])) {
            $tpl = $factory->open('studygroups/index');
            $values[__CLASS__]['lecturers'][$GLOBALS['user']->id] = 1;
        } else {
            $tpl = $factory->open('basicdata/index');
        }
        if ($this->setupTemplateAttributes($tpl, $values, $stepnumber, $temp_id)) {
            return $tpl->render();
        }

        return '';
    }

    protected function setupTemplateAttributes($tpl, $values, $stepnumber, $temp_id)
    {
        // We only need our own stored values here.
        $values = $values[__CLASS__] ?? [];
        // Get all available course types and their categories.
        $typestruct = [];
        $admission_turnout_mandatory_types = [];
        foreach (SemType::getTypes() as $type) {
            $class = $type->getClass();
            // Creates a studygroup.
            if (!empty($values['studygroup'])) {
                // Get all studygroup types.
                if ($class['studygroup_mode']) {
                    $typestruct[$class['name']][] = $type;
                }
                // Pre-set institute for studygroup assignment.
                $values['institute'] = Config::get()->STUDYGROUP_DEFAULT_INST;
            // Normal course.
            } else {
                if (!$class['course_creation_forbidden'] && !$class['studygroup_mode']) {
                    $typestruct[$class['name']][] = $type;
                    if ($class['admission_turnout_mandatory']) {
                        $admission_turnout_mandatory_types[] = $type['id'];
                    }
                }
            }
        }
        $tpl->set_attribute('types', $typestruct);
        $tpl->set_attribute('admission_turnout_mandatory_types', $admission_turnout_mandatory_types);
        // Select a default type if none is given.
        if (empty($values['coursetype'])) {
            if (
                $GLOBALS['user']->cfg->MY_COURSES_TYPE_FILTER
                && $GLOBALS['user']->cfg->MY_COURSES_TYPE_FILTER !== 'all'
                && Request::isXhr()
            ) {
                $values['coursetype'] = $GLOBALS['user']->cfg->MY_COURSES_TYPE_FILTER;
            } else {
                $values['coursetype'] = 1;
            }
        }

        // Semester selection.
        $semesters = [];
        $now = time();
        // Allow only current or future semesters for selection.
        foreach (Semester::getAll() as $s) {
            if ($s->ende >= $now) {
                if ($GLOBALS['perm']->have_perm("admin")) {
                    if (
                        $s->id == $GLOBALS['user']->cfg->MY_COURSES_SELECTED_CYCLE
                        && empty($values['start_semester'])
                        && Request::isXhr()
                    ) {
                        $values['start_semester'] = $s->id;
                    }
                }
                $semesters[] = $s;
            }
        }
        if (empty($values['start_semester'])) {
            $values['start_semester'] = Semester::findDefault()->id;
        }
        if (!empty($values['studygroup']) && (!count($typestruct) || empty($values['institute'])) ) {
            $message = sprintf(_('Die Konfiguration der Studiengruppen ist unvollständig. ' .
                'Bitte wenden Sie sich an [die Stud.IP-Administration]%s .'),
                URLHelper::getLink('dispatch.php/siteinfo/show')
            );
            PageLayout::postError(formatReady($message));
            return false;
        }
        if (count($semesters) > 0) {
            $tpl->set_attribute('semesters', array_reverse($semesters));
            // If no semester is set, use current as selected default.
            if (empty($values['start_semester'])) {
                $values['start_semester'] = Semester::findCurrent()->id;
            }
        } else {
            $message = sprintf(_('Veranstaltungen können nur ' .
                'im aktuellen oder in zukünftigen Semestern angelegt werden. ' .
                'Leider wurde kein passendes Semester gefunden. Bitte wenden ' .
                'Sie sich an [die Stud.IP-Administration]%s .'),
                URLHelper::getLink('dispatch.php/siteinfo/show')
            );
            PageLayout::postError(formatReady($message));
            return false;
        }

        // Create a I18NString for course name and description.
        $values = $this->makeI18N($values, ['name', 'description']);

        // Get all allowed home institutes (my own).
        $institutes = Institute::getMyInstitutes();
        if (!empty($values['studygroup']) || count($institutes) > 0) {
            $tpl->set_attribute('institutes', $institutes);
            if (empty($values['institute'])) {
                if ($GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT !== 'all' && Request::isXhr()) {
                    $values['institute'] = $GLOBALS['user']->cfg->MY_INSTITUTES_DEFAULT;
                } else {
                    $values['institute'] = InstituteMember::getDefaultInstituteIdForUser($GLOBALS['user']->id);

                    // if for some reason no default institute is set, use the first one listed
                    if (!$values['institute']) {
                        $values['institute'] = $institutes[0]['Institut_id'];
                    }
                }
            }
        } else {
            $message = sprintf(_('Um Veranstaltungen ' .
                'anlegen zu können, muss Ihr Account der Einrichtung, ' .
                'für die Sie eine Veranstaltung anlegen möchten, zugeordnet ' .
                'werden. Bitte wenden Sie sich an [die ' .
                'Stud.IP-Administration]%s .'),
                URLHelper::getLink('dispatch.php/siteinfo/show')
            );
            PageLayout::postError(formatReady($message));
            return false;
        }

        // QuickSearch for participating institutes.
        // No JS: Keep search value and results for displaying in search select box.
        if (!empty($values['part_inst_id'])) {
            Request::getInstance()->offsetSet('part_inst_id', $values['part_inst_id']);
        }
        if (!empty($values['part_inst_id_parameter'])) {
            Request::getInstance()->offsetSet('part_inst_id_parameter', $values['part_inst_id_parameter']);
        }
        $instsearch = new StandardSearch('Institut_id',
            _('Beteiligte Einrichtung hinzufügen'),
            'part_inst_id'
        );
        $tpl->set_attribute('instsearch', QuickSearch::get('part_inst_id', $instsearch)
            ->withButton(['search_button_name' => 'search_part_inst', 'reset_button_name' => 'reset_instsearch'])
            ->fireJSFunctionOnSelect('STUDIP.CourseWizard.addParticipatingInst')
            ->render());
        if (empty($values['participating'])) {
            $values['participating'] = [];
        }

        // Quicksearch for lecturers.
        // No JS: Keep search value and results for displaying in search select box.
        if (!empty($values['lecturer_id'])) {
            Request::getInstance()->offsetSet('lecturer_id', $values['lecturer_id']);
        }
        if (!empty($values['lecturer_id_parameter'])) {
            Request::getInstance()->offsetSet('lecturer_id_parameter', $values['lecturer_id_parameter']);
        }

        // Check for deputies.
        $deputies = Config::get()->DEPUTIES_ENABLE;
        /*
         * No lecturers set, add yourself so that at least one lecturer is
         * present. But this can only be done if your own permission level
         * is 'dozent'.
         */
        if (
            empty($values['lecturers'])
            && $GLOBALS['perm']->have_perm('dozent')
            && !$GLOBALS['perm']->have_perm('admin')
        ) {
            $values['lecturers'] = [$GLOBALS['user']->id => true];
            // Remove from deputies if set.
            if ($deputies && isset($values['deputies'][$GLOBALS['user']->id])) {
                unset($values['deputies'][$GLOBALS['user']->id]);
            }
            // Add your own default deputies if applicable.
            if ($deputies && Config::get()->DEPUTIES_DEFAULTENTRY_ENABLE) {
                $values['deputies'] = array_merge(
                    $values['deputies'] ?? [],
                    array_flip(Deputy::findDeputies($GLOBALS['user']->id)->pluck('user_id'))
                );
            }
        }
        // Add lecturer from my courses filter.
        if ($GLOBALS['user']->cfg->ADMIN_COURSES_TEACHERFILTER && empty($values['lecturers']) && Request::isXhr()) {
            $values['lecturers'] = [$GLOBALS['user']->cfg->ADMIN_COURSES_TEACHERFILTER => true];
            // Add this lecturer's default deputies if applicable.
            if ($deputies && Config::get()->DEPUTIES_DEFAULTENTRY_ENABLE) {
                $values['deputies'] = array_merge(
                    $values['deputies'] ?? [],
                    array_flip(Deputy::findDeputies($GLOBALS['user']->cfg->ADMIN_COURSES_TEACHERFILTER)->pluck('user_id'))
                );
            }
        }
        if (empty($values['lecturers'])) {
            $values['lecturers'] = [];
        }
        if ($deputies && empty($values['deputies'])) {
            $values['deputies'] = [];
        }

        // Quicksearch for deputies if applicable.
        if ($deputies) {
            // No JS: Keep search value and results for displaying in search select box.
            if (!empty($values['deputy_id'])) {
                Request::getInstance()->offsetSet('deputy_id', $values['deputy_id']);
            }
            if (!empty($values['deputy_id_parameter'])) {
                Request::getInstance()->offsetSet('deputy_id_parameter', $values['deputy_id_parameter']);
            }
            $deputysearch = new PermissionSearch('user',
                _('Vertretung hinzufügen'),
                'user_id',
                ['permission' => 'dozent',
                    'exclude_user' => array_keys($values['deputies'])]
            );
            $tpl->set_attribute('dsearch', QuickSearch::get('deputy_id', $deputysearch)
                ->withButton(['search_button_name' => 'search_deputy', 'reset_button_name' => 'reset_dsearch'])
                ->fireJSFunctionOnSelect('STUDIP.CourseWizard.addDeputy')
                ->render());
        }

        if (empty($values['tutors'])) {
            $values['tutors'] = [];
        }

        [$lsearch, $tsearch]  = array_values($this->getSearch($values['coursetype'],
            array_merge([$values['institute']], array_keys($values['participating'])),
            array_keys($values['lecturers']), array_keys($values['tutors'])));
        // Quicksearch for lecturers.
        $tpl->set_attribute('lsearch', $lsearch);
        $tpl->set_attribute('tsearch', $tsearch);
        $tpl->set_attribute('values', $values);
        // AJAX URL needed for default deputy checking.
        $tpl->set_attribute('ajax_url', $values['ajax_url'] ?? URLHelper::getLink('dispatch.php/course/wizard/ajax'));
        $tpl->set_attribute('default_deputies_enabled',
            ($deputies && Config::get()->DEPUTIES_DEFAULTENTRY_ENABLE) ? 1 : 0);

        return $tpl;
    }

    /**
     * The function only needs to handle person adding and removing
     * as other actions are handled by normal request processing.
     * @param Array $values currently set values for the wizard.
     * @return bool
     */
    public function alterValues($values)
    {
        // We only need our own stored values here.
        $values = $values[__CLASS__];

        // Add a participating institute.
        if (Request::submitted('add_part_inst') && Request::option('part_inst_id')) {
            $values['participating'][Request::option('part_inst_id')] = true;
            unset($values['part_inst_id']);
            unset($values['part_inst_id_parameter']);
        }
        // Remove a participating institute.
        if ($remove = array_keys(Request::getArray('remove_participating'))) {
            $remove = $remove[0];
            unset($values['participating'][$remove]);
        }
        // Add a lecturer.
        if (Request::submitted('add_lecturer') && Request::option('lecturer_id')) {
            $values['lecturers'][Request::option('lecturer_id')] = true;
            unset($values['lecturer_id']);
            unset($values['lecturer_id_parameter']);
            // Add default deputies if applicable.
            if (Config::get()->DEPUTIES_ENABLE && Config::get()->DEPUTIES_DEFAULTENTRY_ENABLE) {
                $values['deputies'] = array_merge($values['deputies'] ?: [],
                    array_flip(array_keys(Request::optionArray('lecturer_id'))));
            }
        }
        // Remove a lecturer.
        if ($remove = array_keys(Request::getArray('remove_lecturer'))) {
            $remove = $remove[0];
            unset($values['lecturers'][$remove]);
        }
        // Add a deputy.
        if (Request::submitted('add_deputy')) {
            $values['deputies'][Request::option('deputy_id')] = true;
            unset($values['deputy_id']);
            unset($values['deputy_id_parameter']);
        }
        // Remove a deputy.
        if ($remove = array_keys(Request::getArray('remove_deputy'))) {
            $remove = $remove[0];
            unset($values['deputies'][$remove]);
        }
        // Add a tutor.
        if (Request::submitted('add_tutor') && Request::option('tutor_id')) {
            $values['tutors'][Request::option('tutor_id')] = true;
            unset($values['tutor_id']);
            unset($values['tutor_id_parameter']);
        }
        // Remove a tutor.
        if ($remove = array_keys(Request::getArray('remove_tutor'))) {
            $remove = $remove[0];
            unset($values['tutors'][$remove]);
        }
        return $values;
    }

    /**
     * Validates if given values are sufficient for completing the current
     * course wizard step and switch to another one. If not, all errors are
     * collected and shown via PageLayout::postMessage.
     *
     * @param mixed $values Array of stored values
     * @return bool Everything ok?
     */
    public function validate($values)
    {
        // We only need our own stored values here.
        $values = $values[__CLASS__];
        $ok = true;
        $errors = [];
        if (empty($values['name'])) {
            $errors[] = _('Bitte geben Sie den Namen der Veranstaltung an.');
        }
        if (isset($values['number']) && $values['number'] != '') {
            $course_number_format = Config::get()->COURSE_NUMBER_FORMAT;
            if ($course_number_format && !preg_match('/^' . $course_number_format . '$/', $values['number'])) {
                $errors[] = _('Die Veranstaltungsnummer hat ein ungültiges Format.');
            }
        }
        if (empty($values['lecturers'])) {
            $errors[] = sprintf(
                _('Bitte tragen Sie mindestens eine Person als %s ein.'),
                htmlReady(get_title_for_status('dozent', 1, $values['coursetype'] ?? null))
            );
        }
        if (empty($values['lecturers'][$GLOBALS['user']->id]) && !$GLOBALS['perm']->have_perm('admin')) {
            if (Config::get()->DEPUTIES_ENABLE) {
                if (empty($values['deputies'][$GLOBALS['user']->id])) {
                    $errors[] = sprintf(
                        _('Sie selbst müssen entweder als %s oder als Vertretung eingetragen sein.'),
                        htmlReady(get_title_for_status('dozent', 1, $values['coursetype'] ?? null))
                    );
                }
            } else {
                $errors[] = sprintf(
                    _('Sie müssen selbst als %s eingetragen sein.'),
                    htmlReady(get_title_for_status('dozent', 1, $values['coursetype'] ?? null))
                );
            }
        }
        if (in_array($values['coursetype'], studygroup_sem_types())) {
            if (!$values['accept']) {
                $errors[] = _('Sie müssen die Nutzungsbedingungen akzeptieren.');
            }
        }
        $sem_type = new SemType((int)$values['coursetype']);
        $sem_class = $sem_type->getClass();
        if ($sem_class['admission_turnout_mandatory'] && !$values['maxmembers']) {
            $errors[] = _('Sie müssen die maximale Teilnehmendenzahl angeben');
        }
        if ($errors) {
            $ok = false;
            PageLayout::postError(_('Bitte beheben Sie erst folgende Fehler, bevor Sie fortfahren:'), $errors);
        }
        return $ok;
    }

    /**
     * Stores the given values to the given course.
     *
     * @param Course $course the course to store values for
     * @param array  $values values to set
     *
     * @return Course|false The course object with updated values.
     */
    public function storeValues($course, $values)
    {
        // Fetch settings from $values before it is overwritten
        $source_id = $values['source_id'] ?? null;
        $copy_basic_data = !empty($values['copy_basic_data']);
        $copy_participants = !empty($values['copy_participants']);
        $copy_groups = !empty($values['copy_groups']);
        $copy_members = !empty($values['copy_members']);

        // We only need our own stored values here.
        $values = $values[__CLASS__];

        if ($source_id && $copy_basic_data) {
            $this->copyBasicData(
                $course,
                $source_id
            );
        }

        $course->status = $values['coursetype'];
        $course->name = new I18NString($values['name'], $values['name_i18n'] ?? []);
        $course->veranstaltungsnummer = $values['number'] ?? null;
        $course->beschreibung = new I18NString($values['description'], $values['description_i18n'] ?? []);
        $course->start_semester = isset($values['start_semester']) ? Semester::find($values['start_semester']) : Semester::findCurrent();
        $course->institut_id = $values['institute'];

        $semclass = $course->getSemClass();
        $course->visible = $semclass['visible'];
        $course->admission_prelim = $semclass['admission_prelim_default'];
        $course->lesezugriff = $semclass['default_read_level'] ?: 1;
        $course->schreibzugriff = $semclass['default_write_level'] ?: 1;
        $course->admission_turnout = $values['maxmembers'] ?? null;

        // Studygroups: access and description.
        if (in_array($values['coursetype'], studygroup_sem_types())) {
            $course->visible = 1;
            switch ($values['access']) {
                case 'all':
                    $course->admission_prelim = 0;
                    break;
                case 'invisible':
                    if (!Config::get()->STUDYGROUPS_INVISIBLE_ALLOWED) {
                        $course->visible = 0;
                    }
                case 'invite':
                    $course->admission_prelim = 1;
                    $course->admission_prelim_txt = Config::get()->STUDYGROUP_ACCEPTANCE_TEXT;
                    break;
            }
        }

        if (!$course->store()) {
            return false;
        }

        StudipLog::log('SEM_CREATE', $course->id, null, 'Veranstaltung mit Assistent angelegt');
        $institute_ids = [$values['institute']];
        if (isset($values['participating']) && is_array($values['participating'])) {
            $institute_ids = array_merge($institute_ids, array_keys($values['participating']));
        }
        $institutes = Institute::findMany($institute_ids);
        $course->institutes = $institutes;
        $course->store();

        if (isset($values['lecturers']) && is_array($values['lecturers'])) {
            User::findEachMany(
                function (User $user) use ($course) {
                    try {
                        $course->addMember(
                            $user,
                            'dozent',
                            send_mail: $user->id !== User::findCurrent()->id
                        );
                    } catch (\Studip\Exception $e) {
                        //Nothing here.
                    }
                },
                array_keys($values['lecturers'])
            );
        }
        if (isset($values['tutors']) && is_array($values['tutors'])) {
            User::findEachMany(
                function (User $user) use ($course) {
                    try {
                        $course->addMember($user, 'tutor');
                    } catch (\Studip\Exception $e) {
                        //Nothing here.
                    }
                },
                array_keys($values['tutors'])
            );
        }
        if (Config::get()->DEPUTIES_ENABLE && isset($values['deputies']) && is_array($values['deputies'])) {
            foreach ($values['deputies'] as $d => $assigned) {
                Deputy::addDeputy($d, $course->id);
            }
        }
        if ($semclass['admission_type_default'] == 3) {
            $course_set_id = CourseSet::getGlobalLockedAdmissionSetId();
            CourseSet::addCourseToSet($course_set_id, $course->id);
        }

        if ($source_id  && ($copy_participants || $copy_groups || $copy_members)) {
            self::copyParticipantsAndGroups($course, $source_id, $copy_participants, $copy_groups, $copy_members);
        }

        if (in_array($values['coursetype'], studygroup_sem_types())) {
            if (!empty($values['lv_course_id'])) {
                StudygroupModel::proposeAsStudygroupTo($course, $values['lv_course_id']);
            }
        }

        if (!empty($values['exp_date'])) {
            $exp_date = strtotime($values['exp_date']);
            if ($exp_date) {
                CourseConfig::get($course->id)->store('STUDYGROUP_EXPIRATION_DATE', $exp_date);
            }
        }
        if (!empty($values['tags'])) {
            foreach ($values['tags'] as $name) {
                if ($tag = Tag::findOneByName($name)) {
                    if (!$tag->active) {
                        continue;
                    }
                } else {
                    $tag = Tag::create(['name' => $name]);
                }

                $relation = TagRelation::findOneBySQL(
                    "`range_id` = :course_id AND `range_type` = 'course' AND `tag_id` = :tag_id",
                    [
                        'tag_id'    => $tag->id,
                        'course_id' => $course->id
                    ]
                );
                if (!$relation) {
                    $relation = TagRelation::create([
                        'range_id'   => $course->id,
                        'range_type' => 'course',
                        'tag_id'     => $tag->id
                    ]);
                }
            }
        }


        if (!empty($values['stgteil_id'])) {
            $studiengangteil = StudiengangTeil::find($values['stgteil_id']);
            $studiengangteil->addStudygroup($course);
        }

        return $course;
    }

    /**
     * Checks if the current step needs to be executed according
     * to already given values. A good example are study areas which
     * are only needed for certain sem_classes.
     *
     * @param array $values values specified from previous steps
     * @return bool Is the current step required for a new course?
     */
    public function isRequired($values)
    {
        return true;
    }

    /**
     * Copy values for basic data wizard step from given course.
     * @param Course $course
     * @param array $values
     */
    public function copy($course, $values)
    {
        $data = [
            'coursetype' => $course->status,
            'start_semester' => $course->start_semester->id ?? '',
            'name' => $course->name,
            'name_i18n' => is_object($course->name) ? $course->name->toArray() : $course->name,
            'number' => $course->veranstaltungsnummer,
            'institute' => $course->institut_id,
            'description' => $course->beschreibung,
            'description_i18n' => is_object($course->beschreibung) ?
                $course->beschreibung->toArray() : $course->beschreibung,
            'maxmembers' => $course->admission_turnout,
        ];
        $lecturers = $course->members->findBy('status', 'dozent')->pluck('user_id');
        $data['lecturers'] = array_flip($lecturers);
        $tutors = $course->members->findBy('status', 'tutor')->pluck('user_id');
        $data['tutors'] = array_flip($tutors);
        $participating = $course->institutes->pluck('institut_id');
        $data['participating'] = array_flip($participating);
        unset($data['participating'][$course->institut_id]);
        if (Config::get()->DEPUTIES_ENABLE) {
            $data['deputies'] = array_flip(Deputy::findDeputies($course->id)->pluck('user_id'));
        }
        $values[__CLASS__] = $data;
        return $values;
    }

    /**
     * Fetches the default deputies for a given person if the necessary
     * config options are set.
     * @param $user_id user whose default deputies to get
     * @return array Default deputy user_ids.
     */
    public function getDefaultDeputies($user_id)
    {
        if (Config::get()->DEPUTIES_ENABLE && Config::get()->DEPUTIES_DEFAULTENTRY_ENABLE) {
            return Deputy::findDeputies($user_id)->map(function (Deputy $deputy) {
                return ['id' => $deputy->user_id, 'name' => $deputy->getDeputyFullname()];
            });
        }

        return [];
    }

    public function getSearch($course_type)
    {
        $category = SeminarCategories::getByTypeId($course_type);
        if ($category && $category->only_inst_user) {
            $search = 'user_inst';
        } else {
            $search = 'user';
        }
        $psearch = new PermissionSearch($search,
            sprintf(_("%s hinzufügen"), get_title_for_status('dozent', 1, $course_type)),
            'user_id',
            __CLASS__ . '::lsearchHelper'
        );
        $lsearch = QuickSearch::get('lecturer_id', $psearch)
            ->withButton(['search_button_name' => 'search_lecturer', 'reset_button_name' => 'reset_lsearch'])
            ->fireJSFunctionOnSelect('STUDIP.CourseWizard.addLecturer')
            ->render();

        $tutor_psearch = new PermissionSearch($search,
            sprintf(_("%s hinzufügen"), get_title_for_status('tutor', 1, $course_type)),
            'user_id',
            __CLASS__ . '::tsearchHelper'
        );
        $tsearch = QuickSearch::get('tutor_id', $tutor_psearch)
            ->withButton(['search_button_name' => 'search_tutor', 'reset_button_name' => 'reset_tsearch'])
            ->fireJSFunctionOnSelect('STUDIP.CourseWizard.addTutor')
            ->render();

        return compact('lsearch', 'tsearch');
    }

    public static function tsearchHelper($psearch, $context)
    {
        $ret['permission'] = ['tutor', 'dozent'];
        $ret['exclude_user'] = array_keys((array) ($context['tutors'] ?? []));
        $ret['institute'] = array_merge(
            [$context['institute']],
            array_keys((array) ($context['participating'] ?? []))
        );
        return $ret;
    }

    public static function lsearchHelper($psearch, $context)
    {
        $ret['permission'] = 'dozent';
        $ret['exclude_user'] = array_keys((array) ($context['lecturers'] ?? []));
        $ret['institute'] = array_merge(
            [$context['institute']],
            array_keys((array) ($context['participating'] ?? []))
        );
        return $ret;
    }

    /**
     * Creates I18N strings from the given values at the given indices.
     *
     * @param array $values this step's set values
     * @param array $indices the values to convert to I18NStrings
     *
     * @return array modified values
     */
    protected function makeI18N($values, $indices)
    {
        // We only need to do something if there are several content languages.
        if (count($GLOBALS['CONTENT_LANGUAGES']) > 1) {

            /**
             * Create array for configured content languages
             */
            $translations = array_combine(
                array_keys($GLOBALS['CONTENT_LANGUAGES']),
                array_fill(0, count($GLOBALS['CONTENT_LANGUAGES']), '')
            );

            foreach ($indices as $index) {
                // There are values given => create an I18NString
                if (!empty($values[$index])) {

                    $values[$index] = new I18NString($values[$index], $values[$index . '_i18n'] ?? []);

                // Current index is not set (yet), create an empty I18NString
                } else {

                    $values[$index] = new I18NString('', $translations);

                }
            }
        } else {
            foreach ($indices as $index) {
                $values[$index] = $values[$index] ?? '';
            }
        }

        return $values;
    }

    private function copyBasicData(
        Course $course,
        string $source_id
    ): void {
        $source = Course::find($source_id);
        $course->setData($source->toArray('untertitel ort sonstiges art teilnehmer vorrausetzungen lernorga leistungsnachweis ects modules'));
        foreach ($source->datafields as $one) {
            $df = $one->getTypedDatafield();
            if ($df->isEditable()) {
                $course->datafields->findOneBy('datafield_id', $one->datafield_id)->content = $one->content;
            }
        }
    }

    /**
     * Copies participants and/or groups from one course to another.
     */
    public static function copyParticipantsAndGroups(
        Course $course,
        string $source_id,
        bool $with_participants = true,
        bool $with_groups = true,
        bool $with_members = true,
        bool|array $group_ids = false
    ): void {
        $source = Course::find($source_id);

        if (!$with_participants && !$with_groups) {
            return;
        }

        if ($with_participants || ($with_groups && $with_members)) {
            $member_ids = false;
            if (!$with_participants && $with_members) {
                $member_ids = [];
                $source->statusgruppen->filter(function (Statusgruppen $group) use ($group_ids): bool {
                    return $group_ids === false
                        || in_array($group->id, $group_ids);
                })->each(function (Statusgruppen $group) use (&$member_ids): void {
                    $group->members->each(function (StatusgruppeUser $member) use (&$member_ids): void {
                        if (!in_array($member->user_id, $member_ids)) {
                            $member_ids[] = $member->user_id;
                        }
                    });
                });
            }

            $source->getMembersWithStatus(['user', 'autor', 'tutor'], true)
                ->filter(function (CourseMember $member) use ($course, $member_ids): bool {
                    return ($member_ids === false || in_array($member->user_id, $member_ids))
                        && !CourseMember::exists([$course->id, $member->user_id]);
                })->each(function (CourseMember $member) use ($course): void {
                    CourseMember::insertCourseMember(
                        $course->id,
                        $member->user_id,
                        $member->status,
                    );
                });
        }

        if (!$with_groups) {
            return;
        }

        $source->statusgruppen->filter(function (Statusgruppen $group) use ($group_ids): bool {
            return $group_ids === false
                || in_array($group->id, $group_ids);
        })->each(function (Statusgruppen $group) use ($course, $with_members, $group_ids): void {
            $g = Statusgruppen::findOneBySQL(
                'range_id = ? AND name = ?',
                [$course->id, $group->name]
            );

            if (!$g) {
                $g = Statusgruppen::createOrUpdate(
                    null,
                    $group->name,
                    $group->position,
                    $course->id,
                    $group->size,
                    $group->selfassign,
                    $group->selfassign_start,
                    $group->selfassign_end,
                    $group->hasFolder(),
                    null,
                    $group->hasBlubber()
                );
            }

            if (!$with_members) {
                return;
            }

            $group->members->filter(function (StatusgruppeUser $member) use ($g): bool {
                return !StatusgruppeUser::exists([$g->id, $member->user_id]);
            })->each(function (StatusgruppeUser $member) use ($g): void {
                StatusgruppeUser::create([
                    'statusgruppe_id' => $g->id,
                    ...$member->toArray([
                        'user_id',
                        'position',
                        'visible',
                        'inherit',
                    ])
                ]);
            });
        });
    }
}
