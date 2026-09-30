<?php
# Lifter010: TODO
/*
 * studygroup.php - contains Course_BasicdataController
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Rasmus Fuhse <fuhse@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       2.0
 */


class Course_BasicdataController extends AuthenticatedController
{
    public $msg = [];

    /**
     * Set up the list of input fields. Some fields may be locked for
     * some reason (lock rules, insufficient permissions etc.). This
     * method does not return anything, it just sets up $this->attributes
     * and $this->descriptions.
     *
     * @param Course $course
     */
    protected function setupInputFields(Course $course)
    {
        $this->attributes = [];
        $this->attributes[] = [
            'title' => _("Name der Veranstaltung"),
            'name' => "course_name",
            'must' => true,
            'type' => 'text',
            'i18n' => true,
            'value' => $course->name,
            'locked' => LockRules::Check($course->id, 'Name')
        ];
        $this->attributes[] = [
            'title' => _('Untertitel der Veranstaltung'),
            'name' => 'course_untertitel',
            'type' => 'text',
            'i18n' => true,
            'value' => $course->untertitel,
            'locked' => LockRules::Check($course->id, 'Untertitel')
        ];
        $changable = true;
        $this->attributes[] = [
            'title'     => _('Typ der Veranstaltung'),
            'name'      => 'course_status',
            'must'      => true,
            'type'      => 'select',
            'value'     => $course->status,
            'locked'    => LockRules::Check($course->id, 'status'),
            'choices'   => $this->_getTypes($course, $changable),
            'changable' => $changable,
        ];

        $this->attributes[] = [
            'title' => _("Art der Veranstaltung"),
            'name' => 'course_art',
            'type' => 'text',
            'i18n' => true,
            'value' => $course->art,
            'locked' => LockRules::Check($course->id, 'art')
        ];
        $course_number_format_config = Config::get()->getMetadata('COURSE_NUMBER_FORMAT');
        $this->attributes[] = [
            'title' => _('Veranstaltungsnummer'),
            'name' => 'course_veranstaltungsnummer',
            'type' => 'text',
            'value' => $course->veranstaltungsnummer,
            'locked' => LockRules::Check($course->id, 'VeranstaltungsNummer'),
            'description' => $course_number_format_config['comment'],
            'pattern' => Config::get()->COURSE_NUMBER_FORMAT
        ];
        $this->attributes[] = [
            'title' => _('ECTS-Punkte'),
            'name' => 'course_ects',
            'type' => 'text',
            'value' => $course->ects,
            'locked' => LockRules::Check($course->id, 'ects')
        ];
        $this->attributes[] = [
            'title' => _('max. Teilnehmendenzahl'),
            'name' => 'course_admission_turnout',
            'must' => false,
            'type' => 'number',
            'value' => $course->admission_turnout,
            'locked' => LockRules::Check($course->id, 'admission_turnout'),
            'min' => '0'
        ];
        $this->attributes[] = [
            'title' => _('Beschreibung'),
            'name' => 'course_beschreibung',
            'type' => 'textarea',
            'i18n' => true,
            'value' => $course->beschreibung,
            'locked' => LockRules::Check($course->id, 'Beschreibung')
        ];

        $this->institutional = [];
        $my_institutes = Institute::getMyInstitutes();
        $institutes = Institute::getInstitutes();
        foreach ($institutes as $institute) {
            if ($institute['Institut_id'] === $course->institut_id) {
                $found = false;
                foreach ($my_institutes as $inst) {
                    if ($inst['Institut_id'] === $institute['Institut_id']) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
	                $my_institutes[] = $institute;
                }
                break;
            }
        }
        $this->institutional[] = [
            'title'   => _('Heimateinrichtung'),
            'name'    => 'course_institut_id',
            'must'    => true,
            'type'    => 'nested-select',
            'value'   => $course->institut_id,
            'choices' => $this->instituteChoices($my_institutes),
            'locked'  => LockRules::Check($course->id, 'Institut_id')
        ];

        $this->institutional[] = [
            'title'    => _('Beteiligte Einrichtungen'),
            'name'     => 'related_institutes[]',
            'type'     => 'nested-select',
            'value'    => array_diff($course->institutes->pluck('id'), [$course->institut_id]),
            'choices'  => $this->instituteChoices($institutes),
            'locked'   => LockRules::Check($course->id, 'seminar_inst'),
            'multiple' => true,
        ];

        $this->descriptions = [];
        $this->descriptions[] = [
            'title' => _('Teilnehmende'),
            'name' => 'course_teilnehmer',
            'type' => 'textarea',
            'i18n' => true,
            'value' => $course->teilnehmer,
            'locked' => LockRules::Check($course->id, 'teilnehmer')
        ];
        $this->descriptions[] = [
            'title' => _('Voraussetzungen'),
            'name' => 'course_vorrausetzungen',
            'type' => 'textarea',
            'i18n' => true,
            'value' => $course->vorrausetzungen,
            'locked' => LockRules::Check($course->id, 'voraussetzungen')
        ];
        $this->descriptions[] = [
            'title' => _('Lernorganisation'),
            'name' => 'course_lernorga',
            'type' => 'textarea',
            'i18n' => true,
            'value' => $course->lernorga,
            'locked' => LockRules::Check($course->id, 'lernorga')
        ];
        $this->descriptions[] = [
            'title' => _('Leistungsnachweis'),
            'name' => 'course_leistungsnachweis',
            'type' => 'textarea',
            'i18n' => true,
            'value' => $course->leistungsnachweis,
            'locked' => LockRules::Check($course->id, 'leistungsnachweis')
        ];
        $this->descriptions[] = [
            'title' => _("Ort") .
                "<br><span style=\"font-size: 0.8em\"><b>" .
                _("Achtung:") .
                "&nbsp;</b>" .
                _("Diese Ortsangabe wird nur angezeigt, wenn keine " .
                  "Angaben aus Zeiten oder Sitzungsterminen gemacht werden können.") .
                "</span>",
            'i18n' => true,
            'name' => 'course_ort',
            'type' => 'textarea',
            'value' => $course->ort,
            'locked' => LockRules::Check($course->id, 'Ort')
        ];

        $datenfelder = DataFieldEntry::getDataFieldEntries($course->id, 'sem', $course->status);
        if ($datenfelder) {
            foreach($datenfelder as $datenfeld) {
                if ($datenfeld->isVisible()) {
                    $locked = !$datenfeld->isEditable()
                              || LockRules::Check($course->id, $datenfeld->getID());
                    $desc = $locked ? _('Diese Felder werden zentral durch die zuständigen Administratoren erfasst.') : $datenfeld->getDescription();
                    $this->descriptions[] = [
                        'title' => $datenfeld->getName(),
                        'must' =>  $datenfeld->isRequired(),
                        'name' => "datafield_".$datenfeld->getID(),
                        'type' => "datafield",
                        'html_value' => $datenfeld->getHTML("datafields", [
                            'tooltip' => $desc
                        ]),
                        'display_value' => $datenfeld->getDisplayValue(),
                        'locked' => $locked,
                        'description' => $desc
                    ];
                }
            }
        }
        $this->descriptions[] = [
            'title' => _('Sonstiges'),
            'name' => 'course_sonstiges',
            'type' => 'textarea',
            'value' => $course->sonstiges,
            'locked' => LockRules::Check($course->id, 'Sonstiges')
        ];
    }

    /**
     * Helper function to populate the list of institute choices.
     *
     * @param array $institutes
     */
    private function instituteChoices($institutes)
    {
        $faculty_id = null;

        $result = [];
        foreach ($institutes as $inst) {
            $key = $inst['fakultaets_id'] ?? $faculty_id;
            if ($inst['is_fak']) {
                $result[$inst['Institut_id']] = [
                    'label'    => $inst['Name'],
                    'children' => [],
                ];
                $faculty_id = $inst['Institut_id'];
            } elseif (!isset($result[$key])) {
                $result[] = [
                    'label'    => false,
                    'children' => [$inst['Institut_id'] => $inst['Name']],
                ];
            } else {
                $result[$key]['children'][$inst['Institut_id']] = $inst['Name'];
            }
        }

        return $result;
    }

    /**
     * Zeigt die Grunddaten an. Man beachte, dass eventuell zuvor eine andere
     * Action wie Set ausgeführt wurde, von der hierher weitergeleitet worden ist.
     * Wichtige Daten dazu wurden dann über $this->flash übertragen.
     *
     * @param string $course_id
     */
    public function view_action($course_id = null)
    {
        $deputies_enabled = Config::get()->DEPUTIES_ENABLE;

        //damit QuickSearch funktioniert:
        Request::set('new_doz_parameter', $this->flash['new_doz_parameter']);
        if ($deputies_enabled) {
            Request::set('new_dep_parameter', $this->flash['new_dep_parameter']);
        }
        Request::set('new_tut_parameter', $this->flash['new_tut_parameter']);

        $this->course_id = Request::option('cid', $course_id);

        Navigation::activateItem('/course/admin/details');

        //Berechtigungscheck:
        if (!$GLOBALS['perm']->have_studip_perm('tutor', $this->course_id)) {
            throw new AccessDeniedException(_("Sie haben keine Berechtigung diese " .
                    "Veranstaltung zu verändern."));
        }

        //Kopf initialisieren:
        PageLayout::setHelpKeyword("Basis.VeranstaltungenVerwaltenGrunddaten");
        PageLayout::setTitle(_("Verwaltung der Grunddaten"));
        if ($this->course_id) {
            PageLayout::setTitle(Course::find($this->course_id)->getFullName()." - ".PageLayout::getTitle());
        }

        //Daten sammeln:
        $course = Course::find($this->course_id);
        $data = $course->toRawArray();

        //Erster, zweiter und vierter Reiter des Akkordions: Grundeinstellungen
        $this->setupInputFields($course);

        $sem_institutes = $course->institutes->pluck('id');
        $this->dozent_is_locked = LockRules::Check($this->course_id, 'dozent');
        $this->tutor_is_locked = LockRules::Check($this->course_id, 'tutor');

        //Dritter Reiter: Personal
        $this->dozenten = $course->getMembersWithStatus('dozent', true);
        $instUsers = new SimpleCollection(InstituteMember::findByInstituteAndStatus($course->institut_id, 'dozent'));
        $this->lecturersOfInstitute = $instUsers->pluck('user_id');

        if (SeminarCategories::getByTypeId($course->status)->only_inst_user) {
            $search_template = "user_inst_not_already_in_sem";
        } else {
            $search_template = "user_not_already_in_sem";
        }

        $this->dozentUserSearch = new PermissionSearch(
            $search_template,
            sprintf(_('%s suchen'), get_title_for_status('dozent', 1, $course->status)),
            "user_id",
            [
                'permission' => 'dozent',
                'seminar_id' => $this->course_id,
                'sem_perm' => 'dozent',
                'institute' => $sem_institutes
            ]
        );
        $this->dozenten_title = get_title_for_status('dozent', 1, $course->status);
        $this->deputies_enabled = $deputies_enabled;

        if ($this->deputies_enabled) {
            $this->deputies = Deputy::findDeputies($this->course_id);
            $this->deputySearch = new PermissionSearch(
                "user_not_already_in_sem_or_deputy",
                sprintf(_("%s suchen"), get_title_for_status('deputy', 1, $course->status)),
                "user_id",
                ['permission' => Deputy::getValidPerms(), 'seminar_id' => $this->course_id]
            );

            $this->deputy_title = get_title_for_status('deputy', 1, $course->status);
        }
        $this->tutoren = $course->getMembersWithStatus('tutor', true);

        $this->tutorUserSearch = new PermissionSearch(
            $search_template,
            sprintf(_('%s suchen'), get_title_for_status('tutor', 1, $course->status)),
            "user_id",
            ['permission' => ['dozent','tutor'],
                  'seminar_id' => $this->course_id,
                  'sem_perm' => ['dozent','tutor'],
                  'institute' => $sem_institutes
                 ]
        );
        $this->tutor_title = get_title_for_status('tutor', 1, $course->status);
        $instUsers = new SimpleCollection(InstituteMember::findByInstituteAndStatus($course->institut_id, 'tutor'));
        $this->tutorsOfInstitute = $instUsers->pluck('user_id');
        unset($instUsers);

        $this->perm_dozent = $GLOBALS['perm']->have_studip_perm("dozent", $this->course_id);
        $this->mkstring = $data['mkdate'] ? date("d.m.Y, H:i", $data['mkdate']) : _("unbekannt");
        $this->chstring = $data['chdate'] ? date("d.m.Y, H:i", $data['chdate']) : _("unbekannt");
        $lockdata = LockRules::getObjectRule($this->course_id);
        if (!empty($lockdata['description']) && LockRules::CheckLockRulePermission($this->course_id, $lockdata['permission'])){
            $this->flash['msg'] = array_merge((array)$this->flash['msg'], [["info", formatLinks($lockdata['description'])]]);
        }
        $this->flash->discard(); //schmeißt ab jetzt unnötige Variablen aus der Session.
        $sidebar = Sidebar::get();

        $widget = new ActionsWidget();

        $sem_create_perm = in_array(Config::get()->SEM_CREATE_PERM, ['root','admin','dozent']) ? Config::get()->SEM_CREATE_PERM : 'dozent';
        if ($GLOBALS['perm']->have_perm($sem_create_perm)) {
            if (!LockRules::check(Context::getId(), 'seminar_copy')) {
                $widget->addLink(
                    _('Veranstaltung kopieren'),
                    $this->url_for(
                         'course/wizard/copy/' . $this->course_id,
                         ['studip_ticket' => get_ticket()]
                    ),
                    Icon::create('seminar')
                );
            }
        }

        if ($GLOBALS['perm']->have_perm('admin')) {
            $is_locked = $course->lock_rule;
            $widget->addLink(
                _('Sperrebene ändern') . ' (' . ($is_locked ? _('gesperrt') : _('nicht gesperrt')) . ')',
                $this->url_for(
                    'course/management/lock',
                    ['studip_ticket' => get_ticket()]
                ),
                Icon::create('lock-' . ($is_locked ? 'locked' : 'unlocked'))
            )->asDialog('size=auto');
        }

        if (
            (Config::get()->ALLOW_DOZENT_VISIBILITY || $GLOBALS['perm']->have_perm('admin'))
            && !LockRules::Check($this->course_id, 'seminar_visibility')
        ) {
            $is_visible = $course->visible;
            if ($course->isOpenEnded() || $course->end_semester->visible) {
                $widget->addLink(
                    $is_visible ? _('Veranstaltung verstecken') : _('Veranstaltung sichtbar schalten'),
                    $this->url_for(
                        'course/management/change_visibility',
                        ['studip_ticket' => get_ticket()]
                    ),
                    Icon::create('visibility-' . ($is_visible ? 'visible' : 'invisible'))
                );
            }
        }

        if ($this->deputies_enabled) {
            if (Deputy::isDeputy($GLOBALS['user']->id, $this->course_id)) {
                $newstatus = 'dozent';
                $text = _('Lehrende werden');
            } else if (
                $this->dozenten->findOneBy('user_id', $GLOBALS['user']->id)
                && count($this->dozenten) > 1
            ) {
                $newstatus = 'deputy';
                $text = _('Vertretung werden');
            } else {
                $newstatus = '';
                $text = '';
            }
            if ($newstatus !== '' && $text !== '') {
                $widget->addLink(
                    $text,
                    $this->url_for('course/basicdata/switchdeputy', $this->course_id, $newstatus),
                    Icon::create('persons')
                )->asButton();
            }
        }
        if (Config::get()->ALLOW_DOZENT_DELETE || $GLOBALS['perm']->have_perm('admin')) {
            $widget->addLink(
                _('Veranstaltung löschen'),
                $this->url_for(
                    'course/archive/confirm',
                    ['studip_ticket' => get_ticket()]
                ),
                Icon::create('trash')
            )->asDialog('size=auto');
        }
        $sidebar->addWidget($widget);
        if ($GLOBALS['perm']->have_studip_perm('admin', $this->course_id)) {
            $widget = new CourseManagementSelectWidget();
            $sidebar->addWidget($widget);
        }

        foreach ($this->flash['msg'] ?? [] as $msg) {
            match ($msg[0]) {
                'msg'   => PageLayout::postSuccess($msg[1]),
                'error' => PageLayout::postError($msg[1]),
                'info'  => PageLayout::postInfo($msg[1]),
            };
        }
    }

    /**
     * Ändert alle Grunddaten der Veranstaltung (bis auf Personal) und leitet
     * danach weiter auf View.
     */
    public function set_action($course_id)
    {
        global $perm;

        CSRFProtection::verifyUnsafeRequest();

        $course_number_format = Config::get()->COURSE_NUMBER_FORMAT;
        $course = Course::find($course_id);
        $this->msg = [
            'success' => '',
            'errors'  => []
        ];
        $old_settings = $course->toRawArray();
        unset($old_settings['config']);
        //Seminar-Daten:
        if ($perm->have_studip_perm('tutor', $course_id)) {
            $this->setupInputFields($course);
            $changemade = false;
            $invalid_datafields = [];
            $all_fields_types = DataFieldEntry::getDataFieldEntries($course->id, 'sem', $course->status);
            $datafield_values = Request::getArray('datafields');

            foreach (array_merge($this->attributes, $this->institutional, $this->descriptions) as $field) {
                if (!$field['locked']) {
                    if ($field['type'] == 'datafield') {
                        $datafield_id = mb_substr($field['name'], 10);
                        $datafield = $all_fields_types[$datafield_id];
                        $datafield->setValueFromSubmit($datafield_values[$datafield_id]);
                        if ($datafield->isValid()) {
                            if ($datafield->store()) {
                                $changemade = true;
                            }
                        } else {
                            $invalid_datafields[] = $datafield->getName();
                        }
                    } else if ($field['name'] === 'related_institutes[]') {
                        // only related_institutes supported for now
                        $related_institute_ids = Request::optionArray('related_institutes');
                        $current_institute_ids = $course->institutes->pluck('id');
                        $current_institute_ids = array_diff($current_institute_ids, [$course->institut_id]);

                        $institutes_changed = count($related_institute_ids) !== count($current_institute_ids)
                                           || count(array_diff($current_institute_ids, $related_institute_ids)) > 0;

                        if ($institutes_changed) {
                            $course->institutes = Institute::findMany($related_institute_ids);
                            $changemade = true;
                        }
                    } else {
                        // format of input element name is "course_xxx"
                        $varname = mb_substr($field['name'], 7);
                        if (!empty($field['i18n'])) {
                            $req_value = Request::i18n($field['name']);
                        } else {
                            $req_value = Request::get($field['name']);
                        }

                        if ($varname === "name" && !$req_value) {
                            $this->msg['error'][] = _('Name der Veranstaltung darf nicht leer sein.');
                        } elseif ($varname === "seminar_number" && $req_value && $course_number_format &&
                                  !preg_match('/^' . $course_number_format . '$/', $req_value)) {
                            $this->msg['error'][] = _('Die Veranstaltungsnummer hat ein ungültiges Format.');
                        } else if ($field['type'] == 'select' && !in_array($req_value, array_flatten(array_values(array_map('array_keys', $field['choices']))))) {
                            // illegal value - just ignore this
                        } else if ($course->getValue($varname) != $req_value) {
                            $course->setValue($varname, $req_value);
                            $changemade = true;
                        }
                    }
                }
            }
            //Datenfelder:
            if (count($invalid_datafields)) {
                $message = ngettext(
                    'Das folgende Datenfeld der Veranstaltung wurde falsch angegeben, bitte korrigieren Sie dies unter "Beschreibungen": %s',
                    'Die folgenden Datenfelder der Veranstaltung wurden falsch angegeben, bitte korrigieren Sie dies unter "Beschreibungen": %s',
                    count($invalid_datafields)
                );
                $message = sprintf($message, join(', ', array_map('htmlReady', $invalid_datafields)));
                $this->msg['error'][] = $message;
            }

            $course->store();

            // Logging
            $current_settings = $course->toRawArray();
            unset($current_settings['config']);
            $before = array_diff_assoc($old_settings, $current_settings);
            $after  = array_diff_assoc($current_settings, $old_settings);

            //update admission, if turnout was raised
            if (
                !empty($after['admission_turnout'])
                && !empty($before['admission_turnout'])
                && $after['admission_turnout'] > $before['admission_turnout']
                && $course->isAdmissionEnabled()
            ) {
                AdmissionApplication::addMembers($course_id);
            }

            if (sizeof($before) && sizeof($after)) {
                $log_message = '';
                foreach ($before as $k => $v) {
                    $log_message .= "$k: $v => " . $after[$k] . " \n";
                }
                StudipLog::log('CHANGE_BASIC_DATA', $course_id, ' ', $log_message);
                NotificationCenter::postNotification('SeminarBasicDataDidUpdate', $course->id , $GLOBALS['user']->id);
            }
            // end of logging

            if ($changemade) {
                $this->msg['success'] = _('Die Grunddaten der Veranstaltung wurden verändert.');
            }

        } else {
            $this->msg['error'][] = _('Sie haben keine Berechtigung diese Veranstaltung zu verändern.');
        }

        //Labels/Funktionen für Dozenten und Tutoren
        if ($perm->have_studip_perm('dozent', $course_id)) {
            foreach (Request::getArray('label') as $user_id => $label) {
                if ($GLOBALS['perm']->have_studip_perm('tutor', $course_id, $user_id)) {
                    $mb = CourseMember::findOneBySQL('user_id = ? AND Seminar_id = ?', [$user_id, $course_id]);
                    if ($mb) {
                        $mb->label = $label;
                        if ($mb->store()) {
                            NotificationCenter::postNotification(
                                'CourseDidChangeMemberLabel',
                                $course,
                                $mb
                            );
                        }
                    }
                }
            }
        }

        if (!empty($this->msg['error'])) {
            PageLayout::postError(
                _('Die folgenden Fehler traten auf:'),
                $this->msg['error']
            );
        } elseif ($this->msg['success']) {
            PageLayout::postSuccess($this->msg['success']);
        }

        $this->flash['open'] = Request::get("open");
        if (Request::isDialog()) {
            $this->response->add_header('X-Dialog-Close', 1);
            $this->response->add_header('X-Dialog-Execute', 'STUDIP.AdminCourses.App.loadCourse');
            $this->render_text($course_id);
        } else {
            $this->redirect($this->url_for('course/basicdata/view/' . $course_id));
        }
    }

    public function add_member_action($course_id, $status = 'dozent')
    {
        if (!$GLOBALS['perm']->have_studip_perm('dozent', $course_id)) {
            throw new AccessDeniedException();
        }

        // We don't need to check the csrf protection at this point since it
        // is already checked by the multiperson search endpoint

        // load MultiPersonSearch object
        $mp = MultiPersonSearch::load("add_member_{$status}{$course_id}");

        $course = Course::find($course_id);

        $succeeded = [];
        $failed    = [];
        foreach ($mp->getAddedUsers() as $a) {
            $user   = User::find($a);
            $result = false;
            if ($status === 'deputy') {
                $result = Deputy::addDeputy($user->id, $course->id) > 0;
            } else {
                try {
                    $course->addMember(
                        $user,
                        $status === 'tutor' ? 'tutor' : 'dozent'
                    );
                    $result = true;
                } catch (\Studip\EnrolmentException $e) {
                    $result = $e->getMessage();
                }
            }
            if ($result === true) {
                $succeeded[] = $user->getFullName('no_title_rev');
            } elseif (is_string($result)) {
                $failed[] = [$user->getFullName('no_title_rev'), $result];
            } else {
                $failed[] = [$user->getFullName('no_title_rev')];
            }
        }
        // Only show the success messagebox once
        if ($succeeded) {
            $status_title = get_title_for_status($status, count($succeeded), $course->status);
            if (count($succeeded) > 1) {
                $messagetext = sprintf(
                    _("%u %s wurden hinzugefügt."),
                    count($succeeded),
                    $status_title
                );
            } else {
                $messagetext = sprintf(
                    _('%s wurde hinzugefügt.'),
                    $status_title
                );
            }
            PageLayout::postSuccess(
                htmlReady($messagetext),
                array_map('htmlReady', $succeeded),
                true
            );
        }

        // only show an error messagebox once with list of errors!
        if ($failed) {
            $messages = [];
            foreach ($failed as $fail) {
                if (is_array($fail)) {
                    $messages[] = sprintf('%s: %s', $fail[0], $fail[1]);
                } else {
                    $messages[] = $fail;
                }
            }
            PageLayout::postError(
                _('Bei den folgenden Nutzer/-innen ist ein Fehler aufgetreten') ,
                array_map('htmlReady', $messages)
            );
        }
        $this->flash['open'] = 'bd_personal';

        $redirect = Request::get('from', "course/basicdata/view/{$course_id}");
        $this->redirect($this->url_for($redirect));
    }

    /**
     * A helper method since the steps for removing someone are all the same in this controller.
     * Only the actions differ.
     *
     * @param Course $course The course from which to remove a user.
     * @param User $user The user to be removed.
     * @return void
     */
    protected function deleteUserFromCourse(Course $course, User $user)
    {
        try {
            $course->deleteMember($user);
        } catch (\Studip\Exception $e) {
            PageLayout::postError(_('Ein Fehler ist aufgetreten.'), $e->getMessage());
            return;
        }
        PageLayout::postSuccess(
            studip_interpolate(
                _('%{name} wurde aus der Veranstaltung ausgetragen.'),
                ['name' => $user->getFullName()]
            )
        );
    }

    /**
     * Löscht einen Lehrenden (bis auf den letzten Lehrenden)
     * Leitet danach weiter auf View und öffnet den Reiter Personal.
     *
     * @param string $course_id
     * @param string $teacher_id
     */
    public function deletedozent_action($course_id, $teacher_id)
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$GLOBALS['perm']->have_studip_perm('dozent', $course_id)) {
            PageLayout::postError(_('Sie haben keine Berechtigung diese Veranstaltung zu verändern.'));
        } elseif ($teacher_id === $GLOBALS['user']->id) {
            PageLayout::postError(_('Sie dürfen sich nicht selbst aus der Veranstaltung austragen.'));
        } else {
            $this->deleteUserFromCourse(
                Course::find($course_id),
                User::find($teacher_id)
            );
        }

        $this->flash['open'] = 'bd_personal';
        $this->redirect("course/basicdata/view/{$course_id}");
    }

    /**
     * Löscht einen Stellvertreter.
     * Leitet danach weiter auf View und öffnet den Reiter Personal.
     *
     * @param string $course_id
     * @param string $deputy_id
     */
    public function deletedeputy_action($course_id, $deputy_id)
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$GLOBALS['perm']->have_studip_perm('dozent', $course_id)) {
            PageLayout::postError(_('Sie haben keine Berechtigung diese Veranstaltung zu verändern.'));
        } elseif ($deputy_id === $GLOBALS['user']->id) {
            PageLayout::postError(_('Sie dürfen sich nicht selbst aus der Veranstaltung austragen.'));
        } else {
            $course = Course::find($course_id);
            $deputy = Deputy::find([$course_id, $deputy_id]);
            if ($deputy && $deputy->delete()) {
                // Remove user from subcourses as well.
                if (count($course->children) > 0) {
                    $children_ids = $course->children->pluck('seminar_id');
                    Deputy::deleteBySQL('user_id = ? AND range_id IN (?)', [$deputy_id, $children_ids]);
                }

                PageLayout::postSuccess(sprintf(
                    _('%s wurde entfernt.'),
                    htmlReady(get_title_for_status('deputy', 1, $course->status))
                ));
            } else {
                PageLayout::postError(sprintf(
                    _('%s konnte nicht entfernt werden.'),
                    htmlReady(get_title_for_status('deputy', 1, $course->status))
                ));
            }
        }

        $this->flash['open'] = 'bd_personal';
        $this->redirect("course/basicdata/view/{$course_id}");
    }

    /**
     * Löscht einen Tutor
     * Leitet danach weiter auf View und öffnet den Reiter Personal.
     *
     * @param string $course_id
     * @param string $tutor_id
     */
    public function deletetutor_action($course_id, $tutor_id)
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$GLOBALS['perm']->have_studip_perm('dozent', $course_id)) {
            PageLayout::postError( _('Sie haben keine Berechtigung diese Veranstaltung zu verändern.'));
        } else {
            $this->deleteUserFromCourse(
                Course::find($course_id),
                User::find($tutor_id)
            );
        }

        $this->flash['open'] = 'bd_personal';
        $this->redirect("course/basicdata/view/{$course_id}");
    }

    /**
     * Moves a course member up one position in the position list for the
     * corresponding permission level in the course.
     *
     * @param string $course_id The course where to increase the priority.
     *
     * @param string $user_id The user for whom to increase the priority.
     *
     * @param string $status The permission level. This is an unused parameter that is only kept
     *     for compatibility reasons.
     */
    public function priorityupfor_action(string $course_id, string $user_id, string $status = 'dozent')
    {
        CSRFProtection::verifyUnsafeRequest();

        $course = Course::find($course_id);
        $user = User::find($user_id);
        $this->msg = [];
        if ($GLOBALS['perm']->have_studip_perm('dozent', $course->id)) {
            if ($course->moveMemberUp($user) < 0) {
                $this->msg[] = ['error', _('Die Person konnte nicht nach oben verschoben werden.')];
            }
        } else {
            $this->msg[] = ["error", _("Sie haben keine Berechtigung diese Veranstaltung zu verändern.")];
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $course->id));
    }

    /**
     * Moves a course member down one position in the position list for the
     * corresponding permission level in the course.
     *
     * @param string $course_id The course where to decrease the priority.
     *
     * @param string $user_id The user for whom to decrease the priority.
     *
     * @param string $status The permission level. This is an unused parameter that is only kept
     *     for compatibility reasons.
     */
    public function prioritydownfor_action($course_id, $user_id, $status = 'dozent')
    {
        CSRFProtection::verifyUnsafeRequest();

        $course = Course::find($course_id);
        $user = User::find($user_id);
        $this->msg = [];
        if ($GLOBALS['perm']->have_studip_perm('dozent', $course->id)) {
            if ($course->moveMemberDown($user) < 0) {
                $this->msg[] = ['error', _('Die Person konnte nicht nach unten verschoben werden.')];
            }
        } else {
            $this->msg[] = ['error', _('Sie haben keine Berechtigung diese Veranstaltung zu verändern.')];
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $course->id));
    }

    public function switchdeputy_action($course_id, $newstatus)
    {
        CSRFProtection::verifyUnsafeRequest();

        switch($newstatus) {
            case 'dozent':
                $dozent = new CourseMember();
                $dozent->seminar_id = $course_id;
                $dozent->user_id = $GLOBALS['user']->id;
                $dozent->status = 'dozent';
                $dozent->comment = '';
                if ($dozent->store()) {
                    $deputy = Deputy::find([$course_id, $GLOBALS['user']->id]);
                    if ($deputy) {
                        $deputy->delete();
                    }
                    PageLayout::postSuccess(sprintf(_('Sie wurden als %s eingetragen.'),
                        htmlReady(get_title_for_status('dozent', 1))));
                } else {
                    PageLayout::postError(sprintf(_('Sie konnten nicht als %s eingetragen werden.'),
                        htmlReady(get_title_for_status('dozent', 1))));
                }
                break;
            case 'deputy':
                $dozent = Course::find($course_id)->members->findOneBy('user_id', $GLOBALS['user']->id);
                if (Deputy::addDeputy($GLOBALS['user']->id, $course_id)) {
                    $dozent->delete();
                    PageLayout::postSuccess(_('Sie wurden als Vertretung eingetragen.'));
                } else {
                    PageLayout::postError(_('Sie konnten nicht als Vertretung eingetragen werden.'));
                }
                break;
        }
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/'.$course_id));
    }

    private function _getTypes(Course $course, &$changable = true)
    {
        $sem_types = [];

        $sem_classes = [];
        if ($GLOBALS['perm']->have_perm("admin")) {
            foreach (SemClass::getClasses() as $sc) {
                if (!$sc['course_creation_forbidden']) {
                    $sem_classes[] = $sc;
                }
            }
        } else {
            $sem_classes[] = $course->getSemClass();
        }

        if (!$course->isStudyGroup()) {
            $sem_classes = array_filter($sem_classes, function (SemClass $sc) {
                return !$sc['studygroup_mode'];
            });
        }

        foreach ($sem_classes as $sc) {
            $sem_types[$sc['name']] = array_map(function ($st) {
                return $st['name'];
            }, $sc->getSemTypes());
        }
        if (!in_array($course->status, array_flatten(array_values(array_map('array_keys', $sem_types))))) {
            $class_name = $course->getSemClass()->offsetGet('name');
            if (!isset($sem_types[$class_name])) {
                $sem_types[$class_name] = [];
            }
            $sem_types[$class_name][] = $course->getSemType()->offsetGet('name');

            $changable = false;
        }
        return $sem_types;
    }
}
