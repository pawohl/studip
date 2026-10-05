<?php
/**
 * admission.php - administration of admission restrictions
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     admin
 */

class Course_AdmissionController extends AuthenticatedController
{
    /**
     * common tasks for all actions
     */
    public function before_filter (&$action, &$args)
    {
        parent::before_filter($action, $args);

        $course_id = $args[0] ?? '';

        $this->course_id = Request::option('cid', $course_id);

        Navigation::activateItem('/course/admin/admission');

        if (!get_object_type($this->course_id, ['sem']) ||
            SeminarCategories::GetBySeminarId($this->course_id)->studygroup_mode ||
            !$GLOBALS['perm']->have_studip_perm('tutor', $this->course_id)) {
            throw new Trails\Exception(403);
        }

        $this->course = Course::find($this->course_id);
        $this->user_id = $GLOBALS['user']->id;
        PageLayout::setHelpKeyword("Basis.VeranstaltungenVerwaltenZugangsberechtigungen");
        PageLayout::setTitle($this->course->getFullName()." - " ._("Verwaltung von Zugangsberechtigungen"));

        $lockrules = words('admission_turnout admission_type admission_endtime admission_binding passwort read_level write_level admission_prelim admission_prelim_txt admission_starttime admission_endtime_sem admission_disable_waitlist user_domain admission_binding admission_studiengang');
        $this->is_locked = [];
        foreach ($lockrules as $rule) {
            $this->is_locked[$rule] = LockRules::Check($this->course_id, $rule) ? 'disabled readonly' : '';
        }
        $this->is_locked['write_level'] = 'disabled readonly';
        AdmissionApplication::addMembers($this->course->id);
        URLHelper::addLinkParam('return_to_dialog', Request::get('return_to_dialog'));
    }

    /**
     * Shows the current restrictions for course participation.
     */
    public function index_action()
    {
        URLHelper::addLinkParam('return_to_dialog', Request::isDialog());
        $this->sidebar = Sidebar::get();

        if ($GLOBALS['perm']->have_studip_perm('admin', $this->course_id)) {
            $widget = new CourseManagementSelectWidget();
            $this->sidebar->addWidget($widget);
        }

        $this->all_domains = UserDomain::getUserDomains();
        $this->seminar_domains = array_map(function($d) { return $d->id; }, UserDomain::getUserDomainsForSeminar($this->course_id));
        $this->current_courseset = CourseSet::getSetForCourse($this->course_id);
        $this->activated_admission_rules = AdmissionRule::getAvailableAdmissionRules();
        if (!$this->current_courseset) {
            $available_coursesets = new SimpleCollection();
            $filter = ['course_set_chdate' => strtotime('-1 year')];
            foreach (CourseSet::getCoursesetsByInstituteId($this->course->institut_id, $filter) as $cs) {
                $cs = new CourseSet($cs['set_id']);
                if ($cs->isUserAllowedToAssignCourse($this->user_id, $this->course_id)) {
                    $available_coursesets[] = [
                        'id' => $cs->getId(),
                        'name' => $cs->getName(),
                        'chdate' => $cs->getChdate(),
                        'my_own' => $cs->getUserId() === $GLOBALS['user']->id
                    ];
                }
            }
            foreach (CourseSet::getglobalCoursesets($filter) as $cs) {
                $cs = new CourseSet($cs['set_id']);
                if ($cs->isUserAllowedToAssignCourse($this->user_id, $this->course_id)) {
                    $available_coursesets[] = [
                        'id' => $cs->getId(),
                        'name' => $cs->getName(),
                        'chdate' => $cs->getChdate(),
                        'my_own' => $cs->getUserId() === $GLOBALS['user']->id
                    ];
                }
            }
            $available_coursesets->orderBy('name');
            $this->available_coursesets = $available_coursesets;

            PageLayout::postInfo(
                _('Für diese Veranstaltung sind keine Anmelderegeln festgelegt. Die Veranstaltung ist damit für alle Nutzer zugänglich.')
            );
        } else {
            if ($this->current_courseset->isSeatDistributionEnabled() && !$this->course->admission_turnout) {
                PageLayout::postInfo(
                    _('Diese Veranstaltung ist teilnahmebeschränkt, aber die maximale Teilnehmendenzahl ist nicht gesetzt.')
                );
            }
        }
        $lockdata = LockRules::getObjectRule($this->course_id);
        if (!empty($lockdata['description']) && LockRules::CheckLockRulePermission($this->course_id)) {
            PageLayout::postInfo(formatLinks($lockdata['description']));
        }
    }

    /**
     * Change preliminary admission settings.
     */
    public function change_admission_prelim_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        PageLayout::setTitle(_('Anmeldemodus ändern'));
        $request = null;
        $question = null;
        if (Request::submitted('change_admission_prelim')) {
            $request = Request::extract('admission_prelim int, admission_binding submitted, admission_prelim_txt');
            $request = array_diff_key($request, array_filter($this->is_locked));
            $request['change_admission_prelim'] = 1;
            $this->course->setData($request);
            if ($this->course->isFieldDirty('admission_prelim')) {
                if ($this->course->admission_prelim == 1 && $this->course->getNumParticipants()) {
                    $question = _('Sie beabsichtigen, den Anmeldemodus auf "Vorläufiger Eintrag" zu ändern. Sollen die bereits in der Veranstaltung eingetragenen Teilnehmenden in vorläufige Teilnehmende umgewandelt werden?');
                }
                if ($this->course->admission_prelim == 0 && $this->course->getNumPrelimParticipants()) {
                    $question = _('Sie beabsichtigen, den Anmeldemodus auf "Direkter Eintrag" zu ändern. Sollen die vorläufigen Teilnehmenden in die Veranstaltung übernommen werden (ansonsten werden die vorläufigen Teilnehmenden aus der Veranstaltung entfernt) ?');
                }
            }
            if (Request::submittedSome('change_admission_prelim_no', 'change_admission_prelim_yes') || !$question) {
                if ($this->course->admission_prelim == 1 && $this->course->getNumParticipants() && Request::submitted('change_admission_prelim_yes')) {
                    $num_moved = 0;
                    foreach ($this->course->members->findBy('status', ['user','autor'])->pluck('user') as $user) {
                        $this->course->addPreliminaryMember($user);
                        try {
                            $this->course->deleteMember($user);
                        } catch (\Studip\Exception $e) {
                            continue;
                        }
                        $num_moved++;
                        setTempLanguage($user->id);
                        $message_body = sprintf(_('Sie wurden in der Veranstaltung **%s** in den Status **vorläufig akzeptiert** befördert, da das Anmeldeverfahren geändert wurde.'), $this->course->name);
                        $message_title = sprintf(_("Statusänderung %s"), $this->course->name);
                        messaging::sendSystemMessage($user->id, $message_title, $message_body);
                        restoreLanguage();
                    }
                    if ($num_moved) {
                        PageLayout::postSuccess(sprintf(
                            _('%s Teilnehmende wurden auf vorläufigen Eintrag gesetzt.'),
                            $num_moved
                        ));
                    }
                }
                if ($this->course->admission_prelim == 0 && $this->course->getNumPrelimParticipants()) {
                    if (Request::submitted('change_admission_prelim_yes')) {
                        $num_moved = 0;
                        foreach ($this->course->admission_applicants->findBy('status', 'accepted')->pluck('user') as $user) {
                            try {
                                $this->course->addMember($user, 'autor');
                            } catch (\Studip\Exception $e) {
                                continue;
                            }
                            $num_moved++;
                            setTempLanguage($user->id);
                            $message_body = sprintf(_('Sie wurden in der Veranstaltung **%s** in den Status **Autor** versetzt, da das Anmeldeverfahren geändert wurde.'), $this->course->name);
                            $message_title = sprintf(_("Statusänderung %s"), $this->course->name);
                            messaging::sendSystemMessage($user, $message_title, $message_body);
                            restoreLanguage();
                        }
                        if ($num_moved) {
                            PageLayout::postSuccess(sprintf(
                                _('%s Teilnehmende wurden in die Veranstaltung übernommen.'),
                                $num_moved
                            ));
                        }
                    }
                    if (Request::submitted('change_admission_prelim_no')) {
                        $num_moved = 0;
                        foreach ($this->course->admission_applicants->findBy('status', 'accepted') as $applicant) {
                            setTempLanguage($applicant->user_id);
                            $message_body = sprintf(_('Sie wurden aus der Veranstaltung **%s** entfernt, da das Anmeldeverfahren geändert wurde.'), $this->course->name);
                            $message_title = sprintf(_("Statusänderung %s"), $this->course->name);
                            messaging::sendSystemMessage($applicant->user_id, $message_title, $message_body);
                            restoreLanguage();
                            $num_moved += $applicant->delete();
                        }
                        if ($num_moved) {
                            PageLayout::postSuccess(sprintf(
                                _('%s vorläufige Teilnehmende wurden entfernt.'),
                                $num_moved
                            ));
                            $this->course->resetRelation('admission_applicants');
                        }
                    }
                }
                if ($this->course->store()) {
                    PageLayout::postSuccess(_("Der Anmeldemodus wurde geändert."));
                }
                unset($question);
            }
        }
        if (empty($question)) {
            $this->relocate($this->action_url('index'));
        } else {
            $this->button_yes = 'change_admission_prelim_yes';
            $this->button_no = 'change_admission_prelim_no';
            $this->request = $request;
            PageLayout::postMessage(MessageBox::info($question));
            $this->render_template('course/admission/_change_admission.php');
        }
    }

    /**
     * Change free access settings.
     */
    public function change_free_access_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        if (Request::submitted('change_free_access')) {
            $request = Request::extract('read_level submitted, write_level submitted');
            $request = array_diff_key($request, array_filter($this->is_locked));
            if (isset($request['write_level'])) {
                if ($request['write_level'] === true) {
                    $this->course->schreibzugriff = 0;
                    $request['read_level'] = true;
                } else {
                    $this->course->schreibzugriff = 1;
                }
            }
            if (isset($request['read_level'])) {
                if ($request['read_level'] === true) {
                    $this->course->lesezugriff = 0;
                } else {
                    $this->course->lesezugriff = 1;
                    $this->course->schreibzugriff = 1;
                }
            }
            if ($this->course->store()) {
                $message = sprintf('read access = %d, write access = %d', $request['read_level'], $request['write_level']);
                StudipLog::log('SEM_CHANGED_ACCESS', $this->course->id, null, 'Zugriff für externe Nutzer wurde geändert', $message);
                PageLayout::postSuccess(_("Zugriff für externe Nutzer wurde geändert."));
            }
        }
        $this->relocate($this->action_url('index'));
    }

    function change_admission_turnout_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        PageLayout::setTitle(_('Teilnehmendenzahl ändern'));
        $request = null;
        if (Request::submitted('change_admission_turnout')) {
            $request = Request::extract('admission_turnout int, admission_disable_waitlist submitted, admission_disable_waitlist_move submitted, admission_waitlist_max int');
            $request = array_diff_key($request, array_filter($this->is_locked));
            $request['change_admission_turnout'] = 1;
            if (isset($request['admission_turnout'])) {
                $this->course->admission_turnout = abs($request['admission_turnout']);
            }
            if (isset($request['admission_disable_waitlist'])) {
                $this->course->admission_disable_waitlist = $request['admission_disable_waitlist'] ? 0 : 1;
                if ($this->course->admission_disable_waitlist && $this->course->getNumWaiting()) {
                    $question = sprintf(_("Sie beabsichtigen die Warteliste zu deaktivieren. Die bestehende Warteliste mit %s Einträgen wird gelöscht. Sind Sie sicher?"), $this->course->getNumWaiting());
                }
            }
            if (isset($request['admission_disable_waitlist_move'])) {
                $this->course->admission_disable_waitlist_move = $request['admission_disable_waitlist_move'] ? 0 : 1;
            }
            if (isset($request['admission_waitlist_max'])) {
                $this->course->admission_waitlist_max = abs($request['admission_waitlist_max']);
                if ($this->course->admission_waitlist_max > 0 && !$this->admission_disable_waitlist && $this->course->getNumWaiting() > $this->course->admission_waitlist_max) {
                    $question = sprintf(_("Sie beabsichtigen die Anzahl der Wartenden zu begrenzen. Die letzten %s Einträge der Warteliste werden gelöscht. Sind Sie sicher?"), $this->course->getNumWaiting()-$this->course->admission_waitlist_max);
                }
            }
            if (Request::submitted('change_admission_turnout_yes') || empty($question)) {
                if ($this->course->admission_disable_waitlist && $this->course->getNumWaiting()) {
                    $removed_applicants = $this->course->admission_applicants->findBy('status', 'awaiting');
                }
                if ($this->course->admission_waitlist_max > 0 && !$this->admission_disable_waitlist && $this->course->getNumWaiting() > $this->course->admission_waitlist_max) {
                    $limit = $this->course->getNumWaiting() - $this->course->admission_waitlist_max;
                    $removed_applicants = $this->course->admission_applicants->findBy('status', 'awaiting')->orderBy('position desc', SORT_NUMERIC)->limit($limit);
                }
                if (!empty($removed_applicants)) {
                    $num_moved = 0;
                    foreach ($removed_applicants as $applicant) {
                        setTempLanguage($applicant->user_id);
                        $message_body = sprintf(_('Die Warteliste der Veranstaltung **%s** wurde deaktiviert, Sie sind damit __nicht__ zugelassen worden.'),  $this->course->name);
                        $message_title = sprintf(_("Statusänderung %s"), $this->course->name);
                        messaging::sendSystemMessage($applicant->user_id, $message_title, $message_body);
                        restoreLanguage();
                        $num_moved += $applicant->delete();
                    }
                    if ($num_moved) {
                        $this->course->resetRelation('admission_applicants');
                        PageLayout::postSuccess(sprintf(
                            _('%s Wartende wurden entfernt.'),
                            $num_moved
                        ));
                    }
                }

                if ($this->course->store()) {
                    PageLayout::postSuccess(_('Die Teilnehmendenzahl wurde geändert.'));
                }
                unset($question);
            }
        }
        if (empty($question)) {
            $this->relocate($this->action_url('index'));
        } else {
            $this->request = $request;
            $this->button_yes = 'change_admission_turnout_yes';
            $this->button_no = 'change_admission_turnout_no';
            PageLayout::postInfo($question);
            $this->render_template('course/admission/_change_admission.php');
        }
    }

    public function change_domains_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        if (Request::submitted('change_domains') && !LockRules::Check($this->course_id, 'user_domain')) {
            $old_domains = array_map(function($d) { return $d->id; }, UserDomain::getUserDomainsForSeminar($this->course_id));
            $new_domains = Request::getArray('user_domain');
            $changes = count(array_diff($old_domains, $new_domains)) + count(array_diff($new_domains, $old_domains));
            if ($changes) {
                UserDomain::removeUserDomainsForSeminar($this->course_id);
                foreach ($new_domains as $d) {
                    UserDomain::find($d)->addSeminar($this->course_id);
                }
                PageLayout::postSuccess(_('Die zugelassenen Nutzerdomänen wurden geändert.'));
            }
        }
        $this->relocate($this->action_url('index'));
    }

    function change_course_set_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        if (Request::submitted('change_course_set_assign') && Request::get('course_set_assign') && !LockRules::Check($this->course_id, 'admission_type')) {
            $cs = new CourseSet(Request::option('course_set_assign'));
            if ($cs->isUserAllowedToAssignCourse($this->user_id, $this->course_id)) {
                CourseSet::addCourseToSet($cs->getId(), $this->course_id);
                $cs->load();
                if (in_array($this->course_id, $cs->getCourses())) {
                    PageLayout::postSuccess(sprintf(
                        _('Die Zuordnung zum Anmeldeset %s wurde durchgeführt.'),
                        htmlReady($cs->getName())
                    ));
                }
            }
        }
        if (Request::submitted('change_course_set_unassign') && !LockRules::Check($this->course_id, 'admission_type')) {
            PageLayout::setTitle(_('Anmelderegeln aufheben'));

            if ($this->course->getNumWaiting() && !Request::submitted('change_course_set_unassign_yes')) {
                $question = sprintf(_("In dieser Veranstaltung existiert eine Warteliste. Die bestehende Warteliste mit %s Einträgen wird gelöscht. Sind Sie sicher?"), $this->course->getNumWaiting());
            }
            $cs = CourseSet::getSetForCourse($this->course_id);
            if ($cs) {
                $priorities = AdmissionPriority::getPrioritiesByCourse($cs->getId(), $this->course_id);
                if (count($priorities) && !Request::submitted('change_course_set_unassign_yes')) {
                    $question = sprintf(_("In dieser Veranstaltung existiert eine Anmeldeliste (Platzverteilung am %s). Die bestehende Anmeldeliste mit %s Einträgen wird gelöscht. Sind Sie sicher?"), strftime('%x %R', $cs->getSeatDistributionTime()), count($priorities));
                }
            }
            if (Request::submitted('change_course_set_unassign_yes') && $cs) {
                CourseSet::removeCourseFromSet($cs->getId(), $this->course_id);
                $cs->load();
                if (!in_array($this->course_id, $cs->getCourses())) {
                    PageLayout::postSuccess(sprintf(
                        _('Die Zuordnung zum Anmeldeset %s wurde aufgehoben.'),
                        htmlReady($cs->getName())
                    ));
                }
                if (!count($cs->getCourses()) && $cs->isGlobal() && $cs->getUserid() != '') {
                    $cs->delete();
                }
                if ($this->course->getNumWaiting()) {
                    $num_moved = 0;
                    foreach ($this->course->admission_applicants->findBy('status', 'awaiting') as $applicant) {
                        setTempLanguage($applicant->user_id);
                        $message_body = sprintf(_('Die Warteliste der Veranstaltung **%s** wurde deaktiviert, Sie sind damit __nicht__ zugelassen worden.'),  $this->course->name);
                        $message_title = sprintf(_("Statusänderung %s"), $this->course->name);
                        messaging::sendSystemMessage($applicant->user_id, $message_title, $message_body);
                        restoreLanguage();
                        $num_moved += $applicant->delete();
                    }
                    if ($num_moved) {
                        $this->course->resetRelation('admission_applicants');
                        PageLayout::postSuccess(sprintf(
                            _('%s Wartende wurden entfernt.'), $num_moved
                        ));
                    }
                }
            }
        }
        if (empty($question)) {
            $this->relocate($this->action_url('index'));
        } else {
            $this->request = ['change_course_set_unassign' => 1];
            $this->button_yes = 'change_course_set_unassign_yes';
            $this->button_no = 'change_course_set_unassign_no';
            PageLayout::postInfo($question);
            $this->render_template('course/admission/_change_admission.php');
        }
    }

    public function explain_course_set_action()
    {
        $cs = new CourseSet(Request::option('set_id'));
        if ($cs->getId()) {
            $this->render_text($cs->toString(true));
        } else {
            $this->render_nothing();
        }
    }

    function instant_course_set_action()
    {
        PageLayout::setTitle(_('Neue Anmelderegel'));

        $types = explode('_', Request::option('type'));
        $type = $types[0] ?? '';
        $another_type = $types[1] ?? null;

        $rule_ids = explode('_', Request::option('rule_id'));
        $rule_id = $rule_ids[0] ?? '';
        $another_rule_id = $rule_ids[1] ?? null;

        $rule_types = AdmissionRule::getAvailableAdmissionRules(true);
        if (isset($rule_types[$type])) {
            $rule = new $type($rule_id);

            if ($type === 'LockedAdmission') {

                $courseset = CourseSet::getGlobalLockedAdmissionSetId();
                CourseSet::addCourseToSet($courseset, $this->course_id);

                PageLayout::postSuccess(_('Die Veranstaltung wurde gesperrt.'));
                $this->relocate('course/admission');

            } else {

                $another_rule = null;
                if (isset($rule_types[$another_type])) {
                    $another_rule = new $another_type($another_rule_id);
                }
                $course_set = CourseSet::getSetForRule($rule_id) ?: new CourseSet();
                $types = [$type];
                if ($another_rule) {
                    $types[] = $another_type;
                }
                $course_set_name = $rule->getName() . ': ' . $this->course->name;

                $props = [
                    'rule-types' => $types,
                    'course-set-name' => $course_set_name,
                    'course-id' => $this->course_id
                ];

                $this->render_vue_app(
                    Studip\VueApp::create('admission/InstantCourseSet')
                        ->withProps($props)
                );

            }
        } else {
            throw new Trails\Exception(400);
        }
    }

    public function edit_courseset_action($cs_id)
    {
        $cs = new CourseSet($cs_id);
        if ($cs->isUserAllowedToEdit($this->user_id)) {
            $this->instant_course_set_view = true;
            $response = $this->relay('admission/courseset/configure/' . $cs->getId());
            $this->body = $response->body;
            if (!empty($response->headers['Location'])) {
                $this->relocate($response->headers['Location']);
            }
        } else {
            throw new Trails\Exception(403);
        }
    }

    public function save_courseset_action($cs_id)
    {
        $cs = new CourseSet($cs_id);
        if ($cs->isUserAllowedToEdit($this->user_id)) {
            $this->instant_course_set_view = true;
            $response = $this->relay('admission/courseset/save/' . $cs->getId());
            $this->body = $response->body;
            if ($response->headers['Location']) {
                $this->relocate($response->headers['Location']);
            }
        } else {
            throw new Trails\Exception(403);
        }
    }

    public function after_filter($action, $args)
    {
        if (Request::isXhr() && !Request::get('return_to_dialog')) {
            foreach ($this->response->headers as $k => $v) {
                if ($k === 'Location') {
                    $this->response->headers['X-Location'] = $v;
                    unset($this->response->headers['Location']);
                    $this->response->set_status(200);
                    $this->response->body = '';
                }
            }
        }
        parent::after_filter($action, $args);
    }
}
