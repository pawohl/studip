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

require_once 'app/controllers/authenticated_controller.php';
require_once 'lib/classes/Seminar.class.php';

class Course_BasicdataController extends AuthenticatedController
{
    public $msg = array();

    /**
     * Zeigt die Grunddaten an. Man beachte, dass eventuell zuvor eine andere
     * Action wie Set ausgef�hrt wurde, von der hierher weitergeleitet worden ist.
     * Wichtige Daten dazu wurden dann �ber $this->flash �bertragen.
     *
     * @param md5 $course_id
     */
    public function view_action($course_id = null)
    {
        global $user, $perm, $_fullname_sql;

        $deputies_enabled = get_config('DEPUTIES_ENABLE');

        //damit QuickSearch funktioniert:
        Request::set('new_doz_parameter', $this->flash['new_doz_parameter']);
        if ($deputies_enabled) {
            Request::set('new_dep_parameter', $this->flash['new_dep_parameter']);
        }
        Request::set('new_tut_parameter', $this->flash['new_tut_parameter']);

        $this->course_id = Request::option('cid', $course_id);

        if ($perm->have_perm('admin')) {
            //Navigation im Admin-Bereich:
            Navigation::activateItem('/admin/course/details');
        } else {
            //Navigation in der Veranstaltung:
            Navigation::activateItem('/course/admin/details');
        }

        //Ausw�hler f�r Admin-Bereich:
        if (!$this->course_id) {
            PageLayout::setTitle(_("Verwaltung der Grunddaten"));
            $GLOBALS['view_mode'] = "sem";

            require_once 'lib/admin_search.inc.php';

            include 'lib/include/admin_search_form.inc.php';  // will not return
            die(); //must not return
        }

        //Berechtigungscheck:
        if (!$perm->have_studip_perm("tutor",$this->course_id)) {
            throw new AccessDeniedException(_("Sie haben keine Berechtigung diese " .
                    "Veranstaltung zu ver�ndern."));
        }

        //Kopf initialisieren:
        PageLayout::setHelpKeyword("Basis.VeranstaltungenVerwaltenGrunddaten");
        PageLayout::setTitle(_("Verwaltung der Grunddaten"));
        if ($this->course_id) {
            PageLayout::setTitle(Course::find($this->course_id)->getFullname()." - ".PageLayout::getTitle());
        }

        //Daten sammeln:
        $sem = Seminar::getInstance($this->course_id);
        $data = $sem->getData();

        //Erster Reiter des Akkordions: Grundeinstellungen
        $this->attributes = array();
        $this->attributes[] = array(
            'title' => _("Name der Veranstaltung"),
            'name' => "course_name",
            'must' => true,
            'type' => 'text',
            'value' => $data['name'],
            'locked' => LockRules::Check($this->course_id, 'Name')
        );
        $this->attributes[] = array(
            'title' => _("Untertitel der Veranstaltung"),
            'name' => "course_subtitle",
            'type' => 'text',
            'value' => $data['subtitle'],
            'locked' => LockRules::Check($this->course_id, 'Untertitel')
        );
        $sem_types = array();
        if ($perm->have_perm("admin")) {
            foreach (SemClass::getClasses() as $sc) {
                foreach ($sc->getSemTypes() as $st) {
                    if (!$sc['course_creation_forbidden']) {
                        $sem_types[$st['id']] = $st['name'] . ' (' . $sc['name'] . ')';
                    }
                }
            }
        } else {
            $sc = $sem->getSemClass();
            foreach($sc->getSemTypes() as $st) {
                $sem_types[$st['id']] = $st['name'] . ' (' . $sc['name'] . ')';
            }
        }
        if (!isset($sem_types[$data['status']])) {
            $sem_types[$data['status']] = $sem->getSemType()->offsetGet('name');
        }
        $this->attributes[] = array(
            'title' => _("Typ der Veranstaltung"),
            'name' => "course_status",
            'must' => true,
            'type' => 'select',
            'value' => $data['status'],
            'locked' => LockRules::Check($this->course_id, 'status'),
            'choices' => array_map('htmlReady', $sem_types)
        );
        $this->attributes[] = array(
            'title' => _("Art der Veranstaltung"),
            'name' => "course_form",
            'type' => 'text',
            'value' => $data['form'],
            'locked' => LockRules::Check($this->course_id, 'art')
        );
        $this->attributes[] = array(
            'title' => _("Veranstaltungs-Nummer"),
            'name' => "course_seminar_number",
            'type' => 'text',
            'value' => $data['seminar_number'],
            'locked' => LockRules::Check($this->course_id, 'VeranstaltungsNummer')
        );
        $this->attributes[] = array(
            'title' => _("ECTS-Punkte"),
            'name' => "course_ects",
            'type' => 'text',
            'value' => $data['ects'],
            'locked' => LockRules::Check($this->course_id, 'ects')
        );
        $this->attributes[] = array(
            'title' => _("max. Teilnehmerzahl"),
            'name' => "course_admission_turnout",
            'must' => false,
            'type' => 'number',
            'value' => $data['admission_turnout'],
            'locked' => LockRules::Check($this->course_id, 'admission_turnout'),
            'min' => '0'
        );
        $this->attributes[] = array(
            'title' => _("Beschreibung"),
            'name' => "course_description",
            'type' => 'textarea',
            'value' => $data['description'],
            'locked' => LockRules::Check($this->course_id, 'Beschreibung')
        );


        //Zweiter Reiter: Institute
        $this->institutional = array();
        $institute = Institute::getMyInstitutes();
        $choices = array();
        foreach ($institute as $inst) {
            //$choices[$inst['Institut_id']] = $inst['Name'];
            $choices[$inst['Institut_id']] =
                ($inst['is_fak'] ? "<span style=\"font-weight: bold\">" : "&nbsp;&nbsp;&nbsp;&nbsp;") .
                htmlReady($inst['Name']) .
                ($inst['is_fak'] ? "</span>" : "");
        }
        $this->institutional[] = array(
            'title' => _("Heimat-Einrichtung"),
            'name' => "course_institut_id",
            'must' => true,
            'type' => 'select',
            'value' => $data['institut_id'],
            'choices' => $choices,
            'locked' => LockRules::Check($this->course_id, 'Institut_id')
        );
        $institute = Institute::getInstitutes();
        $choices = array();
        foreach ($institute as $inst) {
            $choices[$inst['Institut_id']] =
                ($inst['is_fak'] ? "<span style=\"font-weight: bold\">" : "&nbsp;&nbsp;&nbsp;&nbsp;") .
                htmlReady($inst['Name']) .
                ($inst['is_fak'] ? "</span>" : "");
        }
        $sem_institutes = $sem->getInstitutes();
        $inst = array_flip($sem_institutes);
        unset($inst[$sem->institut_id]);
        $inst = array_flip($inst);
        $this->institutional[] = array(
            'title' => _("beteiligte Einrichtungen"),
            'name' => "related_institutes[]",
            'type' => 'multiselect',
            'value' => $inst,
            'choices' => $choices,
            'locked' => LockRules::Check($this->course_id, 'seminar_inst')
        );

        $this->dozent_is_locked = LockRules::Check($this->course_id, 'dozent');
        $this->tutor_is_locked = LockRules::Check($this->course_id, 'tutor');

        //Dritter Reiter: Personal
        $this->dozenten = $sem->getMembers('dozent');

        if (SeminarCategories::getByTypeId($sem->status)->only_inst_user) {
            $search_template = "user_inst_not_already_in_sem";
        } else {
            $search_template = "user_not_already_in_sem";
        }

        $this->dozentUserSearch = new PermissionSearch(
                            $search_template,
                            sprintf(_("%s suchen"), get_title_for_status('dozent', 1, $sem->status)),
                            "user_id",
                            array('permission' => 'dozent',
                                  'seminar_id' => $this->course_id,
                                  'sem_perm' => 'dozent',
                                  'institute' => $sem_institutes
                                 )
                            );
        $this->dozenten_title = get_title_for_status('dozent', 1, $sem->status);
        $this->deputies_enabled = $deputies_enabled;

        if ($this->deputies_enabled) {
            $this->deputies = getDeputies($this->course_id);
            $this->deputySearch = new PermissionSearch(
                    "user_not_already_in_sem_or_deputy",
                    sprintf(_("%s suchen"), get_title_for_status('deputy', 1, $sem->status)),
                    "user_id",
                    array('permission' => getValidDeputyPerms(), 'seminar_id' => $this->course_id)
                );

            $this->deputy_title = get_title_for_status('deputy', 1, $sem->status);
        }
        $this->tutoren = $sem->getMembers('tutor');

        $this->tutorUserSearch = new PermissionSearch(
                            $search_template,
                            sprintf(_("%s suchen"), get_title_for_status('tutor', 1, $sem->status)),
                            "user_id",
                            array('permission' => array('dozent','tutor'),
                                  'seminar_id' => $this->course_id,
                                  'sem_perm' => array('dozent','tutor'),
                                  'institute' => $sem_institutes
                                 )
                            );
        $this->tutor_title = get_title_for_status('tutor', 1, $sem->status);


        //Vierter Reiter: Beschreibungen (darunter Datenfelder)
        $this->descriptions[] = array(
            'title' => _("Teilnehmer/-innen"),
            'name' => "course_participants",
            'type' => 'textarea',
            'value' => $data['participants'],
            'locked' => LockRules::Check($this->course_id, 'teilnehmer')
        );
        $this->descriptions[] = array(
            'title' => _("Voraussetzungen"),
            'name' => "course_requirements",
            'type' => 'textarea',
            'value' => $data['requirements'],
            'locked' => LockRules::Check($this->course_id, 'voraussetzungen')
        );
        $this->descriptions[] = array(
            'title' => _("Lernorganisation"),
            'name' => "course_orga",
            'type' => 'textarea',
            'value' => $data['orga'],
            'locked' => LockRules::Check($this->course_id, 'lernorga')
        );
        $this->descriptions[] = array(
            'title' => _("Leistungsnachweis"),
            'name' => "course_leistungsnachweis",
            'type' => 'textarea',
            'value' => $data['leistungsnachweis'],
            'locked' => LockRules::Check($this->course_id, 'leistungsnachweis')
        );
        $this->descriptions[] = array(
            'title' => _("Ort") .
                "<br><span style=\"font-size: 0.8em\"><b>" .
                _("Achtung:") .
                "&nbsp;</b>" .
                _("Diese Ortsangabe wird nur angezeigt, wenn keine " .
                  "Angaben aus Zeiten oder Sitzungsterminen gemacht werden k�nnen.") .
                "</span>",
            'name' => "course_location",
            'type' => 'textarea',
            'value' => $data['location'],
            'locked' => LockRules::Check($this->course_id, 'Ort')
        );

        $datenfelder = DataFieldEntry::getDataFieldEntries($this->course_id, 'sem', $data["status"]);
        if ($datenfelder) {
            foreach($datenfelder as $datenfeld) {
                if ($datenfeld->isVisible()) {
                    $locked = !$datenfeld->isEditable()
                              || LockRules::Check($this->course_id, $datenfeld->getID());
                    $this->descriptions[] = array(
                        'title' => $datenfeld->getName(),
                        'must' =>  $datenfeld->structure->getIsRequired(),
                        'name' => "datafield_".$datenfeld->getID(),
                        'type' => "datafield",
                        'html_value' => $datenfeld->getHTML("datafields"),
                        'display_value' => $datenfeld->getDisplayValue(),
                        'locked' => $locked,
                        'description' => (!$datenfeld->isEditable()?("Diese Felder werden zentral durch die zust�ndigen Administratoren erfasst."):$datenfeld->getDescription() )
                    );
                }
            }
        }
        $this->descriptions[] = array(
            'title' => _("Sonstiges"),
            'name' => "course_misc",
            'type' => 'textarea',
            'value' => $data['misc'],
            'locked' => LockRules::Check($this->course_id, 'Sonstiges')
        );

        $this->perm_dozent = $perm->have_studip_perm("dozent", $this->course_id);
        $this->mkstring = $data['mkdate'] ? date("d.m.Y, G:i", $data['mkdate']) : _("unbekannt");
        $this->chstring = $data['chdate'] ? date("d.m.Y, G:i", $data['chdate']) : _("unbekannt");
        $lockdata = LockRules::getObjectRule($this->course_id);
        if ($lockdata['description'] && LockRules::CheckLockRulePermission($this->course_id, $lockdata['permission'])){
            $this->flash['msg'] = array_merge((array)$this->flash['msg'], array(array("info", formatLinks($lockdata['description']))));
        }
        $this->flash->discard(); //schmei�t ab jetzt unn�tige Variablen aus der Session.
        $sidebar = Sidebar::get();
        $sidebar->setImage("sidebar/admin-sidebar.png");

        $widget = new ActionsWidget();
        $widget->addLink(_('Bild �ndern'),
            $this->url_for('course/avatar/update', $course_id),
            'icons/16/blue/edit.png');
        if ($this->deputies_enabled) {
            if (isDeputy($user->id, $this->course_id)) {
                $newstatus = 'dozent';
                $text = _('Dozent/-in werden');
            } else if (in_array($user->id, array_keys($this->dozenten)) && sizeof($this->dozenten) > 1) {
                $newstatus = 'deputy';
                $text = _('Vertretung werden');
            }
            $widget->addLink($text,
                $this->url_for('course/basicdata/switchdeputy', $this->course_id, $newstatus),
                'icons/blue/persons.svg');
        }
        $sidebar->addWidget($widget);
        // Entry list for admin upwards.
        if ($perm->have_studip_perm("admin",$this->course_id)) {
            $adminList = AdminList::getInstance()->getSelectTemplate($this->course_id);
            if ($adminList) {
                $list = new SelectorWidget();
                $list->setUrl("?#admin_top_links");
                $list->setSelectParameterName("cid");
                foreach ($adminList->adminList as $seminar) {
                    $list->addElement(new SelectElement($seminar['Seminar_id'], $seminar['Name']), 'select-' . $seminar['Seminar_id']);
                }
                $list->setSelection($adminList->course_id);
                $sidebar->addWidget($list);
            }
        }
    }

    /**
     * �ndert alle Grunddaten der Veranstaltung (bis auf Personal) und leitet
     * danach weiter auf View.
     */
    public function set_action($course_id)
    {
        global $perm;

        $sem = Seminar::getInstance($course_id);
        $this->msg = array();
        $old_settings = $sem->getSettings();
        //Seminar-Daten:
        if ($perm->have_studip_perm("tutor", $sem->getId())) {
            $changemade = false;
            foreach (Request::getInstance() as $req_name => $req_value) {
                if (substr($req_name, 0, 7) === "course_") {
                    $varname = substr($req_name, 7);
                    if ($varname === "name" && !$req_value) {
                        $this->msg[] = array("error", _("Name der Veranstaltung darf nicht leer sein."));
                    } elseif ($sem->{$varname} != $req_value) {
                        $sem->{$varname} = $req_value;
                        $changemade = true;
                    }
                }
            }
            //seminar_inst:
            if (!LockRules::Check($course_id, 'seminar_inst') &&
                $sem->setInstitutes(Request::optionArray('related_institutes'))) {
                $changemade = true;
            }
            //Datenfelder:
            $invalid_datafields = array();
            $all_fields_types = DataFieldEntry::getDataFieldEntries($sem->id, 'sem', $sem->status);
            foreach (Request::getArray('datafields') as $datafield_id => $datafield_value) {
                $datafield = $all_fields_types[$datafield_id];
                $valueBefore = $datafield->getValue();
                $datafield->setValueFromSubmit($datafield_value);
                if ($valueBefore != $datafield->getValue()) {
                    if ($datafield->isValid()) {
                        $datafield->store();
                        $changemade = true;
                    } else {
                        $invalid_datafields[] = $datafield->getName();
                    }
                }
            }

            if (count($invalid_datafields)) {
                $message = ngettext(_('%s der Veranstaltung wurde falsch angegeben'),
                                    _('%s der Veranstaltung wurden falsch angegeben'),
                                    count($invalid_datafields));
                $message .= ', ' . _('bitte korrigieren Sie dies unter "Beschreibungen"') . '.';
                $message = sprintf($message, join(', ', array_map('htmlReady', $invalid_datafields)));
                $this->msg[] = array('error',  $message);
            }

            $sem->store();

            // Logging
            $before = array_diff_assoc($old_settings, $sem->getSettings());
            $after  = array_diff_assoc($sem->getSettings(), $old_settings);

            //update admission, if turnout was raised
            if($after['admission_turnout'] > $before['admission_turnout'] && $sem->isAdmissionEnabled()) {
                update_admission($sem->getId());
            }

            if (sizeof($before) && sizeof($after)) {
                foreach($before as $k => $v) $log_message .= "$k: $v => " . $after[$k] . " \n";
                log_event('CHANGE_BASIC_DATA', $sem->getId(), " ", $log_message);
            }
            // end of logging

            if ($changemade) {
                $this->msg[] = array("msg", _("Die Grunddaten der Veranstaltung wurden ver�ndert."));
            }

        } else {
            $this->msg[] = array("error", _("Sie haben keine Berechtigung diese Veranstaltung zu ver�ndern."));
        }

        //Labels/Funktionen f�r Dozenten und Tutoren
        if ($perm->have_studip_perm("dozent", $sem->getId())) {
            foreach (Request::getArray("label") as $user_id => $label) {
                $sem->setLabel($user_id, $label);
            }
        }

        foreach($sem->getStackedMessages() as $key => $messages) {
            foreach($messages['details'] as $message) {
                $this->msg[] = array(($key !== "success" ? $key : "msg"), $message);
            }
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = Request::get("open");
        $this->redirect($this->url_for('course/basicdata/view/' . $sem->getId()));
    }

    public function add_member_action($course_id, $status = 'dozent') {
        // load MultiPersonSearch object
        $mp = MultiPersonSearch::load("add_member_" . $status . $course_id);
        $fail = false;

        switch($status) {
            case 'tutor' : $func = 'addTutor'; break;
            case 'deputy': $func = 'addDeputy'; break;
            default: $func = 'addTeacher'; break;
        }

        foreach ($mp->getAddedUsers() as $a) {
            $result = $this->$func($a, $course_id);
            if ($result !== false) {
                PageLayout::postMessage($result);
            } else {
                $fail = true;
            }
        }
        // only show an error messagebox once.
        if ($fail === true) {
            PageLayout::postMessage(MessageBox::error(_('Die gew�nschte Operation konnte nicht ausgef�hrt werden.')));
        }
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $course_id));
    }

    private function addTutor($tutor, $course_id) {
        //Tutoren hinzuf�gen:
        if ($GLOBALS['perm']->have_studip_perm("tutor", $course_id)) {
            $sem = Seminar::GetInstance($course_id);
            if ($sem->addMember($tutor, "tutor")) {
                return MessageBox::success(sprintf(_("%s wurde hinzugef�gt."),get_title_for_status('tutor', 1, $sem->status)));
            }
        }
        return false;
    }

    private function addDeputy($deputy, $course_id) {
        //Vertretung hinzuf�gen:
        if ($GLOBALS['perm']->have_studip_perm("dozent", $course_id)) {
            $sem = Seminar::GetInstance($course_id);
            if (addDeputy($deputy, $sem->getId())) {
                return MessageBox::success(sprintf(_("%s wurde hinzugef�gt."), get_title_for_status('deputy', 1, $sem->status)));
            }
        }
        return false;
    }

    private function addTeacher($dozent, $course_id) {
        $deputies_enabled = get_config('DEPUTIES_ENABLE');
        $sem = Seminar::GetInstance($course_id);
        if($GLOBALS['perm']->have_studip_perm('dozent', $course_id)) {
            if ($sem->addMember($dozent, "dozent")) {
                // Only applicable when globally enabled and user deputies enabled too
                if ($deputies_enabled) {
                    // Check whether chosen person is set as deputy
                    // -> delete deputy entry.
                    if (isDeputy($dozent, $course_id)) {
                        deleteDeputy($dozent, $course_id);
                    }
                    // Add default deputies of the chosen lecturer...
                    if (get_config('DEPUTIES_DEFAULTENTRY_ENABLE')) {
                        $deputies  = getDeputies($dozent);
                        $lecturers = $sem->getMembers('dozent');
                        foreach ($deputies as $deputy) {
                            // ..but only if not already set as lecturer or deputy.
                            if (!isset($lecturers[$deputy['user_id']]) &&
                                !isDeputy($deputy['user_id'], $course_id)
                            ) {
                                addDeputy($deputy['user_id'], $course_id);
                            }
                        }
                    }
                }

                return MessageBox::success(sprintf(_('%s wurde hinzugef�gt.'), get_title_for_status('dozent', 1)));
            }
        }
        return false;
    }

    /**
     * L�scht einen Dozenten (bis auf den letzten Dozenten)
     * Leitet danach weiter auf View und �ffnet den Reiter Personal.
     *
     * @param md5 $dozent
     */
    public function deletedozent_action($course_id, $dozent)
    {
        global $user, $perm;

        $sem = Seminar::getInstance($course_id);
        $this->msg = array();
        if ($perm->have_studip_perm("dozent", $sem->getId())) {
            if ($dozent !== $user->id) {
                $sem->deleteMember($dozent);
                foreach($sem->getStackedMessages() as $key => $messages) {
                    foreach($messages['details'] as $message) {
                        $this->msg[] = array(($key !== "success" ? $key : "msg"), $message);
                    }
                }
            } else {
                $this->msg[] = array("error", _("Sie d�rfen sich nicht selbst aus der Veranstaltung austragen."));
            }
        } else {
            $this->msg[] = array("error", _("Sie haben keine Berechtigung diese Veranstaltung zu ver�ndern."));
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $sem->getId()));
    }

    /**
     * L�scht einen Stellvertreter.
     * Leitet danach weiter auf View und �ffnet den Reiter Personal.
     *
     * @param md5 $deputy
     */
    public function deletedeputy_action($course_id, $deputy)
    {
        global $user, $perm;

        $sem = Seminar::getInstance($course_id);
        $this->msg = array();
        if ($perm->have_studip_perm("dozent", $sem->getId())) {
            if ($deputy !== $user->id) {
                if (deleteDeputy($deputy, $sem->getId())) {
                    $this->msg[] = array("msg", sprintf(_("%s wurde entfernt."),
                        get_title_for_status('deputy', 1, $sem->status)));
                } else {
                    $this->msg[] = array("error", sprintf(_("%s konnte nicht entfernt werden."),
                       get_title_for_status('deputy', 1, $sem->status)));
                }
            } else {
                $this->msg[] = array("error", _("Sie d�rfen sich nicht selbst aus der Veranstaltung austragen."));
            }
        } else {
            $this->msg[] = array("error", _("Sie haben keine Berechtigung diese Veranstaltung zu ver�ndern."));
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $sem->getId()));
    }

    /**
     * L�scht einen Tutor
     * Leitet danach weiter auf View und �ffnet den Reiter Personal.
     *
     * @param md5 $tutor
     */
    public function deletetutor_action($course_id, $tutor)
    {
        global $user, $perm;

        $sem = Seminar::getInstance($course_id);
        $this->msg = array();
        if ($perm->have_studip_perm("dozent", $sem->getId())) {
            $sem->deleteMember($tutor);
            foreach($sem->getStackedMessages() as $key => $messages) {
                foreach($messages['details'] as $message) {
                    $this->msg[] = array(($key !== "success" ? $key : "msg"), $message);
                }
            }
        } else {
            $this->msg[] = array("error", _("Sie haben keine Berechtigung diese Veranstaltung zu ver�ndern."));
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $sem->getId()));
    }

    /**
     * Falls eine Person in der >>Reihenfolge<< hochgestuft werden soll.
     * Leitet danach weiter auf View und �ffnet den Reiter Personal.
     *
     * @param md5 $user_id
     * @param string $status
     */
    public function priorityupfor_action($course_id, $user_id, $status = "dozent")
    {
        global $user, $perm;

        $sem = Seminar::getInstance($course_id);
        $this->msg = array();
        if ($perm->have_studip_perm("dozent", $sem->getId())) {
            $teilnehmer = $sem->getMembers($status);
            $members = array();
            foreach($teilnehmer as $key => $member) {
                $members[] = $member["user_id"];
            }
            foreach($members as $key => $member) {
                if ($key > 0 && $member == $user_id) {
                    $temp_member = $members[$key-1];
                    $members[$key-1] = $member;
                    $members[$key] = $temp_member;
                }
            }
            $sem->setMemberPriority($members, $status);
        } else {
            $this->msg[] = array("error", _("Sie haben keine Berechtigung diese Veranstaltung zu ver�ndern."));
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $sem->getId()));
    }

    /**
     * Falls eine Person in der >>Reihenfolge<< runtergestuft werden soll.
     * Leitet danach weiter auf View und �ffnet den Reiter Personal.
     *
     * @param md5 $user_id
     * @param string $status
     */
    public function prioritydownfor_action($course_id, $user_id, $status = "dozent")
    {
        global $user, $perm;

        $sem = Seminar::getInstance($course_id);
        $this->msg = array();
        if ($perm->have_studip_perm("dozent", $sem->getId())) {
            $teilnehmer = $sem->getMembers($status);
            $members = array();
            foreach($teilnehmer as $key => $member) {
                $members[] = $member["user_id"];
            }
            foreach($members as $key => $member) {
                if ($key < count($members)-1 && $member == $user_id) {
                    $temp_member = $members[$key+1];
                    $members[$key+1] = $member;
                    $members[$key] = $temp_member;
                }
            }
            $sem->setMemberPriority($members, $status);
        } else {
            $this->msg[] = array("error", _("Sie haben keine Berechtigung diese Veranstaltung zu ver�ndern."));
        }
        $this->flash['msg'] = $this->msg;
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/' . $sem->getId()));
    }

    public function switchdeputy_action($course_id, $newstatus) {
        $course = Seminar::getInstance($course_id);
        switch($newstatus) {
            case 'dozent':
                if ($course->addMember($GLOBALS['user']->id, 'dozent')) {
                    deleteDeputy($GLOBALS['user']->id, $course_id);
                }
                break;
            case 'deputy':
                if (addDeputy($GLOBALS['user']->id, $course_id)) {
                    $course->deleteMember($GLOBALS['user']->id);
                }
                break;
        }
        $this->flash['open'] = "bd_personal";
        $this->redirect($this->url_for('course/basicdata/view/'.$course_id));
    }

}
