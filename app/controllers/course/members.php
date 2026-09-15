<?php

/*
 * MembersController
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      David Siegfried <david.siegfried@uni-oldenburg.de>
 * @author      Sebastian Hobert <sebastian.hobert@uni-goettingen.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       2.5
 */

use PhpOffice\PhpWord\PhpWord;
use Services\Export\CourseMemberService;

require_once 'lib/messaging.inc.php'; //Funktionen des Nachrichtensystems

class Course_MembersController extends AuthenticatedController
{

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        global $perm;

        checkObject();
        checkObjectModule("participants");

        $this->course_id    = Context::getId();
        $this->course_title = Context::get()->Name;
        $this->user_id      = $GLOBALS['user']->id;
        $this->config       = CourseConfig::get($this->course_id);

        // Check perms
        $this->is_dozent = $perm->have_studip_perm('dozent', $this->course_id);
        $this->is_tutor  = $perm->have_studip_perm('tutor', $this->course_id);
        $this->is_autor  = $perm->have_studip_perm('autor', $this->course_id);

        if ($this->is_tutor) {
            PageLayout::setHelpKeyword("Basis.VeranstaltungenVerwaltenTeilnehmer");
        } else {
            PageLayout::setHelpKeyword("Basis.InVeranstaltungTeilnehmer");
        }

        // Check lock rules
        $this->dozent_is_locked = LockRules::Check($this->course_id, 'dozent');
        $this->tutor_is_locked  = LockRules::Check($this->course_id, 'tutor');
        $this->is_locked        = LockRules::Check($this->course_id, 'participants');

        // Layoutsettings
        PageLayout::setTitle(sprintf('%s - %s', Course::findCurrent()->getFullName(), _("Teilnehmende")));

        $this->studip_module = checkObjectModule('participants');
        object_set_visit_module( $this->studip_module->getPluginId());
        $this->last_visitdate = object_get_visit($this->course_id, $this->studip_module->getPluginId());

        // Check perms and set the last visit date
        if (!$this->is_tutor) {
            $this->last_visitdate = time() + 10;
        }

        // Get the max-page-value for the pagination
        $this->max_per_page = Config::get()->ENTRIES_PER_PAGE;
        $this->status_groups = [
            'dozent' => get_title_for_status('dozent', 2),
            'tutor' => get_title_for_status('tutor', 2),
            'autor' => get_title_for_status('autor', 2),
            'user' => get_title_for_status('user', 2),
            'accepted' => get_title_for_status('accepted', 2),
            'awaiting' => _("Wartende Personen"),
            'claiming' => _("Wartende Personen")
        ];

        // StatusGroups for the view
        $this->decoratedStatusGroups = [
            'dozent' => get_title_for_status('dozent', 1),
            'autor' => get_title_for_status('autor', 1),
            'tutor' => get_title_for_status('tutor', 1),
            'user' => get_title_for_status('user', 1)
        ];

        //check for admission / waiting list
        AdmissionApplication::addMembers($this->course_id);
        $this->checkUserVisibility();
    }

    public function index_action()
    {
        $course             = Course::find($this->course_id);
        $this->sort_by      = Request::option('sortby', 'nachname');
        $this->order        = Request::option('order', 'asc');
        $this->sort_status  = Request::get('sort_status', '');

        Navigation::activateItem('/course/members/view');
        if (Request::int('toggle')) {
            $this->order = $this->order == 'desc' ? 'asc' : 'desc';
        }

        $filtered_members = CourseMember::getMembers(
            $this->course_id,
            $this->sort_status,
            $this->sort_by . ' ' . $this->order
        );

        if ($this->is_tutor) {
            $filtered_members = array_merge(
                $filtered_members,
                AdmissionApplication::getAdmissionMembers(
                    $this->course_id,
                    $this->sort_status,
                    $this->sort_by . ' ' . $this->order
                )
            );
            $this->awaiting = $filtered_members['awaiting']->toArray();
            $this->accepted = $filtered_members['accepted']->toArray();
            $this->claiming = $filtered_members['claiming']->toArray();
        }

        // Check autor-perms
        if (!$this->is_tutor) {
            // filter invisible user
            $user_count = count($filtered_members['autor']) + count($filtered_members['user']);
            $current_user_id = $this->user_id;
            $exclude_invisibles =
                    function ($user) use ($current_user_id) {
                        return ($user['visible'] != 'no' || $user['user_id'] == $current_user_id);
                    };
            $filtered_members['autor'] = $filtered_members['autor']->filter($exclude_invisibles);
            $filtered_members['user'] = $filtered_members['user']->filter($exclude_invisibles);
            $this->invisibles = $user_count - count($filtered_members['autor']) - count($filtered_members['user']);
            $this->my_visibility = $this->getUserVisibility();
        }

        // get member informations
        $this->dozenten = $filtered_members['dozent']->toArray();
        $this->tutoren = $filtered_members['tutor']->toArray();
        $this->autoren = $filtered_members['autor']->toArray();
        $this->users = $filtered_members['user']->toArray();
        $this->studipticket = get_ticket();
        $this->subject = $this->getSubject();
        $this->groups = $this->status_groups;
        $this->semAdmissionEnabled = false;
        // Check Seminar
        $this->waitingTitle = _('Warteliste (nicht aktiv)');
        $this->waiting_type = 'awaiting';
        if ($this->is_tutor && $course->isAdmissionEnabled()) {
            $this->course = $course;
            $distribution_time = $course->getCourseSet()->getSeatDistributionTime();
            if ($course->getCourseSet()->hasAlgorithmRun()) {
                $this->waitingTitle = _("Warteliste");
                if (!$course->admission_disable_waitlist_move) {
                    $this->waitingTitle .= ' (' . _("automatisches Nachrücken ist eingeschaltet") . ')';
                } else {
                    $this->waitingTitle .= ' (' . _("automatisches Nachrücken ist ausgeschaltet") . ')';
                }
                $this->semAdmissionEnabled = 2;
                $this->waiting_type = 'awaiting';
            } else {
                $this->waitingTitle = sprintf(_("Anmeldeliste (Platzverteilung am %s)"), strftime('%x %R', $distribution_time));
                $this->semAdmissionEnabled = 1;
                $this->awaiting = $this->claiming;
                $this->waiting_type = 'claiming';
            }
        }
        // Set the infobox
        $this->createSidebar($filtered_members);

        if ($this->is_locked && $this->is_tutor) {
            $lockdata = LockRules::getObjectRule($this->course_id);
            if ($lockdata['description']) {
                PageLayout::postMessage(MessageBox::info(formatLinks($lockdata['description'])));
            }
        }
        $this->to_waitlist_actions = false;
        // Check for waitlist availability (influences available actions)
        // People can be moved to waitlist if waitlist available and no automatic moving up.
        if (
            !$course->admission_disable_waitlist
            && $course->admission_disable_waitlist_move
            && $course->isAdmissionEnabled()
            && $course->getCourseSet()->hasAlgorithmRun()
        ) {
            $this->to_waitlist_actions = true;
        }
    }

    /*
     * Returns an array with emails of members
     */
    public function getEmailLinkByStatus($status, $members)
    {
        if (!Config::get()->ENABLE_EMAIL_TO_STATUSGROUP) {
            return;
        }

        if (in_array($status, words('accepted awaiting claiming'))) {
            $textStatus = _('Wartenden');
        } else {
            $textStatus = $this->status_groups[$status];
        }

        $results = SimpleCollection::createFromArray($members)->pluck('email');

        if (!empty($results)) {
            return sprintf('<a href="mailto:%s">%s</a>', htmlReady(join(',', $results)), Icon::create('mail', attributes: ['title' => sprintf('E-Mail an alle %s versenden',$textStatus)])->asImg());
        } else {
            return null;
        }
    }

    /**
     * Show dialog to enter a comment for this user
     * @param String $user_id
     * @throws AccessDeniedException
     */
    public function add_comment_action($user_id = null)
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        $course_member = CourseMember::find([$this->course_id, $user_id]);
        if (!$course_member) {
            $course_member = AdmissionApplication::find([$user_id, $this->course_id]);
        }
        if (is_null($course_member)) {
            throw new Trails\Exception(400);
        }
        $this->comment = $course_member->comment;
        $this->user = User::find($user_id);
        PageLayout::setTitle(sprintf(_('Bemerkung für %s'), htmlReady($this->user->getFullName())));

        // Output as dialog (Ajax-Request) or as Stud.IP page?
        $this->xhr = Request::isXhr();
        if ($this->xhr) {
            $this->set_layout(null);
        } else {
            Navigation::activateItem('/course/members/view');
        }
    }

    /**
     * Store a comment for this user
     * @param String $user_id
     * @throws AccessDeniedException
     */
    public function set_comment_action($user_id = null)
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        CSRFProtection::verifyUnsafeRequest();
        $course_member = CourseMember::find([$this->course_id, $user_id]);
        if (!$course_member) {
            $course_member = AdmissionApplication::find([$user_id, $this->course_id]);
        }
        if (!Request::submitted('save') || is_null($course_member)) {
            throw new Trails\Exception(400);
        }
        $course_member->comment = Request::get('comment');

        if ($course_member->store() !== false) {
            PageLayout::postSuccess(_('Bemerkung wurde erfolgreich gespeichert.'));
        } else {
            PageLayout::postError(_('Bemerkung konnte nicht erfolgreich gespeichert werden.'));
        }
        $this->redirect($this->indexURL());
    }

    /**
     * Add members to a seminar.
     * @throws AccessDeniedException
     */
    public function execute_multipersonsearch_autor_action()
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        // load MultiPersonSearch object
        $mp = MultiPersonSearch::load("add_autor" . $this->course_id);
        $course = Course::find($this->course_id);
        if (!$course) {
            PageLayout::postError(_('Die ausgewählte Veranstaltung wurde nicht gefunden!'));
            $this->redirect($this->indexURL());
            return;
        }

        $added_c = 0;
        $errors = [];
        User::findEachMany(
            function (User $user) use ($course, &$added_c, &$errors) {
                try {
                    $course->addMember($user, regard_contingent: false);
                    $added_c++;
                } catch (\Studip\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            },
            $mp->getAddedUsers()
        );
        if ($added_c > 0) {
            PageLayout::postSuccess(
                sprintf(
                    ngettext(
                        'Es wurde eine neue Person hinzugefügt.',
                        'Es wurden %u neue Personen hinzugefügt.',
                        $added_c
                    ),
                    $added_c
                )
            );
        }
        if ($errors) {
            PageLayout::postError(
                _('Die folgenden Fehler traten beim Eintragen von Personen auf:'),
                array_map(htmlReady(...), $errors)
            );
        }
        $this->redirect($this->indexURL());
    }

     /**
     * Add dozents to a seminar.
     * @throws AccessDeniedException
     */
    public function execute_multipersonsearch_dozent_action()
    {
        // Security Check
        if (!$this->is_dozent) {
            throw new AccessDeniedException('Sie sind nicht bereichtig, auf diesen Bereich von Stud.IP zuzugreifen.');
        }

        // load MultiPersonSearch object
        $mp = MultiPersonSearch::load("add_dozent" . $this->course_id);
        $course = Course::find($this->course_id);
        $added_c = 0;
        User::findEachMany(
            function (User $user) use ($course, &$added_c) {
                try {
                    $course->addMember($user, 'dozent');
                    $added_c++;
                } catch (\Studip\Exception $e) {
                    //Nothing here.
                }
            },
            $mp->getAddedUsers()
        );
        if ($added_c > 0) {
            $status = get_title_for_status('dozent', $added_c, $course->status);
            PageLayout::postSuccess(
                sprintf(
                    ngettext(
                        '%1$u %2$s wurde hinzugefügt.',
                        '%1$u %2$s wurden hinzugefügt.',
                        $added_c
                    ),
                    $added_c,
                    htmlReady($status)
                )
            );
        }
        $this->redirect('course/members/index');
    }

    /**
     * Add people to a course waitlist.
     * @throws AccessDeniedException
     */
    public function execute_multipersonsearch_waitlist_action()
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        // load MultiPersonSearch object
        $mp = MultiPersonSearch::load('add_waitlist' . $this->course_id);
        $course = Course::find($this->course_id);
        $added_c = 0;
        $errors = [];
        User::findEachMany(
            function (User $user) use ($course, &$added_c, &$errors) {
                try {
                    $course->addMemberTowaitlist($user);
                    $added_c++;
                } catch (\Studip\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            },
            $mp->getAddedUsers()
        );

        if ($added_c) {
            PageLayout::postSuccess(
                sprintf(
                    ngettext(
                        'Eine Person wurde zur Warteliste hinzugefügt.',
                        '%u Personen wurden zur Warteliste hinzugefügt.',
                        $added_c
                    ),
                    $added_c
                )
            );
        }
        if ($errors) {
            PageLayout::postError(
                sprintf(
                    ngettext(
                        'Eine Person konnte nicht zur Warteliste hinzugefügt werden:',
                        '%u Personen konnten nicht zur Warteliste hinzugefügt werden:',
                        count($errors)
                    ),
                    count($errors)
                ),
                array_map(htmlReady(...), $errors)
            );
        }
        $this->redirect('course/members/index');
    }

    public function execute_multipersonsearch_accepted_action(): void
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        $mp = MultiPersonSearch::load('add_accepted' . $this->course_id);
        $this->addAccepted($mp->getAddedUsers(), Course::find($this->course_id));

        $this->redirect('course/members/index');
    }

    /**
     * Add tutors to a seminar.
     * @throws AccessDeniedException
     */
    public function execute_multipersonsearch_tutor_action()
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        // load MultiPersonSearch object
        $mp = MultiPersonSearch::load("add_tutor" . $this->course_id);
        $course = Course::find($this->course_id);
        $added_c = 0;
        User::findEachMany(
            function (User $user) use ($course, &$added_c) {
                try {
                    $course->addMember($user, 'tutor');
                    $added_c++;
                } catch (\Studip\Exception $e) {
                    //Nothing here.
                }
            },
            $mp->getAddedUsers()
        );
        if($added_c > 0) {
            $status = get_title_for_status('tutor', $added_c, $course->status);
            PageLayout::postSuccess(
                sprintf(
                    ngettext(
                        '%1$u %2$s wurde hinzugefügt.',
                        '%1$u %2$s wurden hinzugefügt.',
                        $added_c
                    ),
                    $added_c,
                    $status
                )
            );
        }
        $this->redirect('course/members/index');
    }

    /**
     * Provides a dialog to move or copy selected users to another course.
     */
    public function select_course_action()
    {
        if (Request::submitted('submit')) {
            CSRFProtection::verifyUnsafeRequest();
            $this->flash['users_to_send'] = Request::getArray('users');
            $this->flash['target_course'] = Request::option('course_id');
            $this->flash['move'] = Request::int('move');
            $this->redirect('course/members/send_to_course');
        } else {
            if ($GLOBALS['perm']->have_perm('root')) {
                $parameters = [
                    'semtypes' => studygroup_sem_types() ?: null,
                    'exclude' => [Context::getId()],
                ];
            } else if ($GLOBALS['perm']->have_perm('admin')) {
                $parameters = [
                    'semtypes' => studygroup_sem_types() ?: null,
                    'institutes' => array_map(function ($i) {
                        return $i['Institut_id'];
                    }, Institute::getMyInstitutes()),
                    'exclude' => [Context::getId()],
                ];

            } else {
                $parameters = [
                    'userid' => $GLOBALS['user']->id,
                    'semtypes' => studygroup_sem_types() ?: null,
                    'exclude' => [Context::getId()],
                ];
            }
            $coursesearch = MyCoursesSearch::get('Seminar_id', $GLOBALS['perm']->get_perm(), $parameters);
            $this->search = QuickSearch::get('course_id', $coursesearch)
                ->setInputStyle('width:100%')
                ->withButton()
                ->render();
            $this->course_id = Request::option('course_id');
            $this->course_id_parameter = Request::get('course_id_parameter');
            if (!empty($this->flash['users']) || Request::getArray('users')) {
                $users = $this->flash['users'] ?: Request::getArray('users');
                // create a usable array
                $this->users = [];
                foreach ($users as $user => $val) {
                    if ($val) {
                        $this->users[] = $user;
                    }
                }

                PageLayout::setTitle( _('Zielveranstaltung auswählen'));
            } elseif (Request::isXhr()) {
                $this->response->add_header('X-Dialog-Close', '1');
                $this->render_nothing();
            } else {
                $this->redirect('course/members/index');
            }
        }
    }

    /**
     * Copies or moves selected users to the selected target course.
     */
    public function send_to_course_action()
    {
        if ($target = $this->flash['target_course']) {
            $msg = $this->sendToCourse(
                (array)$this->flash['users_to_send'],
                $target,
                $this->flash['move']
            );
            if ($msg['success']) {
                if (count($msg['success']) === 1) {
                    $text = _('Eine Person wurde in die Zielveranstaltung eingetragen.');
                } else {
                    $text = sprintf(
                        _('%s Person(en) wurde(n) in die Zielveranstaltung eingetragen.'),
                        count($msg['success'])
                    );
                }
                PageLayout::postSuccess($text);
            }
            if ($msg['existing']) {
                if (count($msg['existing']) === 1) {
                    $text = _('Eine Person ist bereits in die Zielveranstaltung eingetragen ' .
                                'und kann daher nicht verschoben/kopiert werden.');
                } else {
                    $text = sprintf(_('%s Person(en) sind bereits in die Zielveranstaltung eingetragen ' .
                        'und konnten daher nicht verschoben/kopiert werden.'),
                        sizeof($msg['existing']));
                }
                PageLayout::postInfo($text);
            }
            if ($msg['failed']) {
                if (count($msg['failed']) === 1) {
                    $text = _('Eine Person kann nicht in die Zielveranstaltung eingetragen werden.');
                } else {
                    $text = sprintf(_('%s Person(en) konnten nicht in die Zielveranstaltung eingetragen werden.'),
                            sizeof($msg['failed']));
                }
                PageLayout::postError($text);
            }
        } else {
            PageLayout::postError(_('Bitte wählen Sie eine Zielveranstaltung.'));
        }
        $this->redirect($this->indexURL());
    }

    /**
     * Send Stud.IP-Message to selected users
     */
    public function send_message_action()
    {
        if (!empty($this->flash['users'])) {
            $users = [];
            // create a usable array
            foreach ($this->flash['users'] as $user => $val) {
                if ($val) {
                    $users[] = User::find($user)->username;
                }
            }
            $_SESSION['sms_data'] = [];
            $_SESSION['sms_data']['p_rec'] = array_filter($users);
            $this->redirect(URLHelper::getURL('dispatch.php/messages/write', [
                'default_subject' => $this->getSubject(),
                'tmpsavesnd' => 1,
                'emailrequest' => 1
            ]));
        } else {
            if (Request::isXhr()) {
                $this->response->add_header('X-Dialog-Close', '1');
                $this->render_nothing();
            } else {
            $this->redirect('course/members/index');
        }
    }
    }

    public function import_autorlist_action()
    {
        if (!Request::isXhr()) {
            Navigation::activateItem('/course/members/view');
        }
        $datafields = DataField::getDataFields('user', 1 | 2 | 4 | 8, true);
        $accessible_df = [];
        foreach ($datafields as $df) {
            if ($df->accessAllowed() && in_array($df->getId(), $GLOBALS['TEILNEHMER_IMPORT_DATAFIELDS'])) {
                $accessible_df[] = $df;
            }
        }
        $this->accessible_df = $accessible_df;

    }

    /**
     * Old version of CSV import (copy and paste from teilnehmer.php
     *
     * @throws AccessDeniedException
     */
    public function set_autor_csv_action()
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }
        CSRFProtection::verifyUnsafeRequest();

        // prepare CSV-Lines
        $messaging = new messaging();
        $csv_request = preg_split('/(\n\r|\r\n|\n|\r)/', trim(Request::get('csv_import')));
        $csv_mult_founds = [];
        $csv_count_insert = 0;
        $csv_count_multiple = 0;
        $csv_count_double = 0;
        $datafield_id = null;

        if (Request::get('csv_import_format') && !in_array(Request::get('csv_import_format'), words('realname username email'))) {
            foreach (DataField::getDataFields('user', 1 | 2 | 4 | 8, true) as $df) {
                if (
                    $df->accessAllowed()
                    && in_array($df->getId(), $GLOBALS['TEILNEHMER_IMPORT_DATAFIELDS'])
                    && $df->getId() == Request::get('csv_import_format')
                ) {
                    $datafield_id = $df->getId();
                    break;
                }
            }
        }
        $csv_count_present = 0;
        $csv_not_found = [];
        if (Request::get('csv_import')) {
            // remove duplicate users from csv-import
            $csv_lines = array_unique($csv_request);
            $course = Course::find($this->course_id);
            foreach ($csv_lines as $csv_line) {
                $csv_name = preg_split('/[,\t]/', mb_substr($csv_line, 0, 100), -1, PREG_SPLIT_NO_EMPTY);
                $csv_nachname = trim($csv_name[0]);
                $csv_vorname = trim($csv_name[1] ?? '');

                if (!$csv_nachname) {
                    continue;
                }

                if (Request::option('csv_import_format') === 'realname') {
                    $csv_users = CourseMember::getMemberByIdentification($this->course_id, $csv_nachname, $csv_vorname);
                } elseif (Request::option('csv_import_format') === 'username') {
                    $csv_users = CourseMember::getMemberByUsername($this->course_id, $csv_nachname);
                } elseif (Request::option('csv_import_format') === 'email') {
                    $csv_users = CourseMember::getMemberByEmail($this->course_id, $csv_nachname);
                } else {
                    $csv_users = CourseMember::getMemberByDatafield($this->course_id, $csv_nachname, $datafield_id);
                }

                // if found more then one result to given name
                if (count($csv_users) > 1) {
                    // if user have two accounts
                    foreach ($csv_users as $row) {
                        if ($row['is_present']) {
                            $csv_count_double++;
                        } else {
                            $csv_mult_founds[$csv_line][] = $row;
                        }
                    }

                    if (is_array($csv_mult_founds[$csv_line])) {
                        $csv_count_multiple++;
                    }
                } elseif (count($csv_users) > 0) {
                    $row = reset($csv_users);
                    if (!$row['is_present']) {
                        $user = User::find($row['user_id']);
                        if ($user) {
                            try {
                                $course->addMember($user, regard_contingent: false);
                                $csv_count_insert++;
                            } catch (\Studip\Exception $e) {
                            }
                        }
                    } else {
                        $csv_count_present++;
                    }
                } else {
                    // not found
                    $csv_not_found[] = stripslashes($csv_nachname) . ($csv_vorname ? ', ' . stripslashes($csv_vorname) : '');
                }
            }
        }
        $selected_users = Request::getArray('selected_users');

        if (!empty($selected_users) && count($selected_users) > 0) {
            $course = Course::find($this->course_id);
            foreach ($selected_users as $selected_user) {
                $user = User::findByUsername($selected_user);
                if ($user) {
                    try {
                        $course->addMember($user, regard_contingent: false);
                        $csv_count_insert++;
                    } catch (\Studip\Exception $e) {
                    }
                }
            }
        }

        // no results
        if (empty($csv_lines) && empty($selected_users)) {
            PageLayout::postError(_('Niemanden gefunden!'));
        }

        if ($csv_count_insert) {
            PageLayout::postSuccess(sprintf(_('%s Personen in die Veranstaltung eingetragen!'), $csv_count_insert));
        }

        if ($csv_count_present) {
            PageLayout::postInfo(sprintf(_('%s Personen waren bereits in der Veranstaltung eingetragen!'), $csv_count_double + $csv_count_present));
        }

        // redirect to manual assignment
        if ($csv_mult_founds) {
            PageLayout::postInfo(sprintf(_('%s Personen konnten <b>nicht eindeutig</b>
                zugeordnet werden! Nehmen Sie die Zuordnung bitte manuell vor.'), $csv_count_multiple));
            $this->flash['csv_mult_founds'] = $csv_mult_founds;
            $this->redirect('course/members/csv_manual_assignment');
            return;
        }
        if (is_array($csv_not_found) && count($csv_not_found) > 0) {
            PageLayout::postError(
                _('Folgende Einträge konnten <b>nicht</b> zugeordnet werden:'),
                array_map('htmlReady', $csv_not_found)
            );
        }

        $this->relocate('course/members/index');
    }

    /**
     * Select manual the assignment of a given user or of a group of users
     * @global Object $perm
     * @throws AccessDeniedException
     */
    public function csv_manual_assignment_action()
    {
        // Security. If user not autor, then redirect to index
        if (!$GLOBALS['perm']->have_studip_perm('tutor', $this->course_id)) {
            throw new AccessDeniedException();
        }

        if (empty($this->flash['csv_mult_founds'])) {
            $this->redirect('course/members/index');
        }
    }

    /**
     * Change the visibilty of an autor
     * @return void
     */
    public function change_visibility_action($cmd, $mode)
    {
        // Security. If user not autor, then redirect to index
        if ($GLOBALS['perm']->have_studip_perm('tutor', $this->course_id)) {
            throw new AccessDeniedException();
        }

        // Check for visibile mode
        if ($cmd === 'make_visible') {
            $command = 'yes';
        } else {
            $command = 'no';
        }

        if ($mode === 'awaiting') {
            $model = AdmissionApplication::findOneBySQL(
                'user_id = ? AND seminar_id = ?',
                [$this->user_id, $this->course_id]
            );
        } else {
            $model = CourseMember::findOneBySQL(
                'user_id = ? AND Seminar_id = ?',
                [$this->user_id, $this->course_id]
            );
        }
        $model->visible = $command;
        $result = $model->store();

        if ($result > 0) {
            PageLayout::postSuccess(_('Ihre Sichtbarkeit wurde erfolgreich geändert.'));
        } else {
            PageLayout::postError(_('Leider ist beim Ändern der Sichtbarkeit ein Fehler aufgetreten. Die Einstellung konnte nicht vorgenommen werden.'));
        }
        $this->redirect('course/members/index');
    }

    /**
     * Helper function to select the action
     * @throws AccessDeniedException
     */
    public function edit_tutor_action()
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }
        CSRFProtection::verifyUnsafeRequest();

        $this->flash['users'] = Request::getArray('tutor');

        // select the additional method
        switch (Request::get('action_tutor')) {
            case 'downgrade':
                $target = 'course/members/downgrade_user/tutor/autor';
                break;
            case 'remove':
                $target = 'course/members/cancel_subscription/collection/tutor';
                break;
            case 'message':
                $this->redirect('course/members/send_message');
                return;
            default:
                $target = 'course/members/index';
                break;
        }
        $this->relocate($target);
    }

    /**
     * Helper function to select the action
     * @throws AccessDeniedException
     */
    public function edit_autor_action()
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }
        CSRFProtection::verifyUnsafeRequest();

        $this->flash['users'] = Request::getArray('autor');

        switch (Request::get('action_autor')) {
            case 'upgrade':
                $target = 'course/members/upgrade_user/autor/tutor';
                break;
            case 'downgrade':
                $target = 'course/members/downgrade_user/autor/user';
                break;
            case 'to_admission_first':
                $target = 'course/members/to_waitlist/first';
                break;
            case 'to_admission_last':
                $target = 'course/members/to_waitlist/last';
                break;
            case 'remove':
                $target = 'course/members/cancel_subscription/collection/autor';
                break;
            case 'to_accepted_list':
                $target = 'course/members/to_accepted_list';
            break;
            case 'to_course':
                $this->redirect('course/members/select_course');
                return;
            case 'message':
                $this->redirect('course/members/send_message');
                return;
            default:
                $target = 'course/members/index';
                break;
        }
        $this->relocate($target);
    }

    /**
     * Helper function to select the action
     * @throws AccessDeniedException
     */
    public function edit_user_action()
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }
        CSRFProtection::verifyUnsafeRequest();

        $this->flash['users'] = Request::getArray('user');

        // select the additional method
        switch (Request::get('action_user')) {
            case 'upgrade':
                $target = 'course/members/upgrade_user/user/autor';
                break;
            case 'to_admission_first':
                $target = 'course/members/to_waitlist/first';
                break;
            case 'to_admission_last':
                $target = 'course/members/to_waitlist/last';
                break;
            case 'remove':
                $target = 'course/members/cancel_subscription/collection/user';
                break;
            case 'to_course':
                $this->redirect('course/members/select_course');
                return;
            case 'message':
                $this->redirect('course/members/send_message');
                return;
            default:
                $target = 'course/members/index';
                break;
        }
        $this->relocate($target);
    }

    /**
     * Helper function to select the action
     * @throws AccessDeniedException
     */
    public function edit_awaiting_action()
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }
        CSRFProtection::verifyUnsafeRequest();

        $this->flash['users'] = Request::getArray('awaiting');
        $waiting_type = Request::option('waiting_type');
        // select the additional method
        switch (Request::get('action_awaiting')) {
            case 'upgrade_autor':
                $target = 'course/members/insert_admission/awaiting/collection';
                break;
            case 'upgrade_user':
                $target = 'course/members/insert_admission/awaiting/collection/user';
                break;
            case 'remove':
                $target = 'course/members/cancel_subscription/collection/' . $waiting_type;
                break;
            case 'message':
                $this->redirect('course/members/send_message');
                return;
            default:
                $target = 'course/members/index';
                break;
        }
        $this->relocate($target);
    }

    /**
     * Helper function to select the action
     * @throws AccessDeniedException
     */
    public function edit_accepted_action()
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }
        CSRFProtection::verifyUnsafeRequest();

        $this->flash['users'] = Request::getArray('accepted');

        // select the additional method
        switch (Request::get('action_accepted')) {
            case 'upgrade':
                $target = 'course/members/insert_admission/accepted/collection';
                break;
            case 'remove':
                $target = 'course/members/cancel_subscription/collection/accepted';
                break;
            case 'message':
                $this->redirect('course/members/send_message');
                return;
            default:
                $target = 'course/members/index';
                break;
        }
        $this->relocate($target);
    }

    /**
     * Insert a user to a given seminar or a group of users
     * @param String $status
     * @param String $cmd
     * @param String $target_status
     * @throws AccessDeniedException
     */
    public function insert_admission_action($status, $cmd, $target_status = 'autor')
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        if (!$this->is_dozent && in_array($target_status, ['tutor', 'dozent'])) {
            throw new AccessDeniedException(_('Sie dürfen keine lehrenden Personen oder Hilfspersonen in diese Veranstaltung eintragen.'));
        }

        $users = [];
        // create a usable array
        if($this->flash['users']) {
            $users = array_filter($this->flash['users'], function ($user) {
                return $user;
            });
        }

        if ($users) {
            $enrolled_user_names = [];
            $errors = [];
            $course = Course::find($this->course_id);
            foreach ($users as $user_id => $value) {
                if ($value) {
                    $user = User::find($user_id);
                    if ($user) {
                        try {
                            //Add the user but do not renumber the admission list. This is done manually
                            //to avoid a mass of mails being sent.
                            $course->addMember(
                                $user,
                                $target_status,
                                false,
                                true,
                                false
                            );
                            $enrolled_user_names[] = $user->getFullName();
                        } catch (\Studip\Exception $e) {
                            $errors[] = sprintf(
                                '%1$s: %2$s',
                                $user->getFullName(),
                                $e->getMessage()
                            );
                        }
                    }
                }
            }

            //Renumber the admission list:
            AdmissionApplication::renumberAdmission($this->course_id);

            if ($enrolled_user_names) {
                $message = sprintf(
                    _('%1$s wurde in die Veranstaltung mit dem Status „%2$s“ eingetragen.'),
                    htmlReady(join(',', $enrolled_user_names)),
                    $this->decoratedStatusGroups['autor']
                );
            } else {
                if ($status === 'awaiting') {
                    $message = sprintf(
                        _('%1$s wurde aus der Anmelde- bzw. Warteliste mit dem Status "%2$s" in die Veranstaltung eingetragen.'),
                        htmlReady(implode(', ', $enrolled_user_names)),
                        $this->decoratedStatusGroups[$target_status]
                    );
                } else {
                    $message = sprintf(_('%1$s wurde mit dem Status "%2$s" endgültig akzeptiert und damit in die Veranstaltung eingetragen.'),
                        htmlReady(implode(', ', $enrolled_user_names)),
                        $this->decoratedStatusGroups[$target_status]
                    );
                }
                PageLayout::postSuccess($message);
            }
            if ($errors) {
                PageLayout::postError(
                    _('Es traten Fehler beim Hochstufen von Personen auf:'),
                    array_map(htmlReady(...), $errors)
                );
            }
        } else {
            PageLayout::postError(_('Sie haben niemanden zum Hochstufen ausgewählt.'));
        }

        $this->redirect('course/members/index');
    }

    /**
     * Cancel the subscription of a selected user or group of users
     * @throws AccessDeniedException
     */
    public function cancel_subscription_action(string $cmd, string $status, ?string $user_id = null)
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        $course = Course::find($this->course_id);
        if (!Request::submitted('no')) {
            if (Request::submitted('yes')) {
                CSRFProtection::verifyUnsafeRequest();

                $user_ids = Request::optionArray('users');

                if (!$this->is_dozent) {
                    $this->validateTutorPermission($user_ids, $this->course_id);
                }

                $users = User::findMany($user_ids);

                if (!empty($users)) {
                    $removed_users = [];
                    $errors = [];
                    if (in_array($status, words('accepted awaiting claiming'))) {
                        foreach ($users as $user) {
                            try {
                                $course->removePreliminaryMember($user);
                                $removed_users[] = $user->getFullName();
                            } catch (Exception $e) {
                                $errors[] = $e->getMessage();
                            }
                        }
                    } else {
                        foreach ($users as $user) {
                            try {
                                $course->deleteMember($user);
                                $removed_users[] = $user->getFullName();
                            } catch (Exception $e) {
                                $errors[] = $e->getMessage();
                            }
                        }
                    }

                    if (!empty($errors)) {
                        PageLayout::postError(
                            _('Die folgenden Fehler traten beim Austragen von Personen auf:'),
                            array_map(htmlReady(...), $errors)
                        );
                    }
                    if (count($removed_users) > 5) {
                        PageLayout::postSuccess(sprintf(
                            _('%u Personen wurden ausgetragen.'),
                            count($removed_users)
                        ));
                    } elseif (count($removed_users) > 0) {
                        PageLayout::postSuccess(
                            _('Die folgenden Personen wurden ausgetragen:'),
                            array_map(htmlReady(...), $removed_users)
                        );
                    }
                } else {
                    PageLayout::postWarning(sprintf(
                        _('Sie haben keine %s zum Austragen ausgewählt'),
                        htmlReady($this->status_groups[$status])
                    ));
                }
            } else {
                $users = [];
                if ($cmd === 'singleuser') {
                    $users[] = $user_id;
                } elseif (isset($this->flash['users']) && is_array($this->flash['users'])) {
                    // create a usable array
                    foreach ($this->flash['users'] as $user => $val) {
                        if ($val) {
                            $users[] = $user;
                        }
                    }
                }

                PageLayout::postQuestion(
                    sprintf(
                        _('Wollen Sie die/den "%s" wirklich austragen?'),
                        htmlReady($this->status_groups[$status])
                    )
                )->setAcceptURL(
                    $this->cancel_subscriptionURL('collection', $status),
                    compact('users')
                );
                $this->flash['checked'] = $users;
            }
        }
        $this->redirect($this->indexURL());
    }

    /**
     * Upgrade a user to a selected status
     * @param string $status
     * @param string $next_status
     * @throws AccessDeniedException
     */
    public function upgrade_user_action($status, $next_status)
    {
         if ($GLOBALS['perm']->have_studip_perm('tutor', $this->course_id)
            && $next_status !== 'autor'
            && !$GLOBALS['perm']->have_studip_perm('dozent', $this->course_id)) {
            throw new AccessDeniedException();
        }

        $users = [];
        // create a usable array
        if(!empty($this->flash['users'])) {
            foreach ($this->flash['users'] as $user => $val) {
                if ($val) {
                    $users[] = $user;
                }
            }
        }

        if (!empty($users)) {
            // insert admission user to autorlist
            $msgs = $this->setMemberStatus($users, $status, $next_status, 'upgrade');

            if (!empty($msgs['success'])) {
                PageLayout::postSuccess(sprintf(
                    _('Das Hochstufen auf den Status  %s von %s wurde erfolgreich durchgeführt'),
                    htmlReady($this->decoratedStatusGroups[$next_status]),
                    htmlReady(join(', ', $msgs['success']))
                ));
            }

            if (!empty($msgs['no_tutor'])) {
                PageLayout::postError(sprintf(
                    _('Das Hochstufen auf den Status %s von %s konnte nicht durchgeführt werden, weil die globale Rechtestufe "tutor" fehlt.') . ' ' . _('Bitte wenden Sie sich an den Support.'),
                    htmlReady($this->decoratedStatusGroups[$next_status]),
                    htmlReady(join(', ', $msgs['no_tutor']))
                ));
            }
        } else {
            PageLayout::postError(sprintf(
                _('Sie haben keine %s zum Hochstufen ausgewählt'),
                htmlReady($this->status_groups[$status])
            ));
        }

        $this->redirect('course/members/index');
    }

    /**
     * Downgrade a user to a selected status
     * @param string $status
     * @param string $next_status
     * @throws AccessDeniedException
     */
    public function downgrade_user_action($status, $next_status)
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        if ($next_status !== 'user' && !$this->is_dozent) {
            throw new AccessDeniedException();
        }
        $users = [];
        if (!empty($this->flash['users'])) {
            foreach ($this->flash['users'] as $user => $val) {
                if ($val) {
                    $users[] = $user;
                }
            }
        }

        if (!empty($users)) {
            $msgs = $this->setMemberStatus($users, $status, $next_status, 'downgrade');

            if (!empty($msgs['success'])) {
                PageLayout::postSuccess(sprintf(
                    _('Der/die %s %s wurde auf den Status %s heruntergestuft.'),
                    htmlReady($this->decoratedStatusGroups[$status]),
                    htmlReady(join(', ', $msgs['success'])),
                    $this->decoratedStatusGroups[$next_status]));
            }
        } else {
            PageLayout::postError(sprintf(
                _('Sie haben keine %s zum Herunterstufen ausgewählt'),
                htmlReady($this->status_groups[$status])
            ));
        }

        $this->redirect('course/members/index');
    }

    public function to_accepted_list_action(): void
    {
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        if (!empty($this->flash['users'])) {
            $user_ids = array_keys(array_filter($this->flash['users']));

            $this->addAccepted($user_ids, Course::find($this->course_id));
        }

        $this->redirect('course/members/index');
    }
    /**
     * Moves selected users to waitlist, either at the top or at the end.
     * @param $which_end 'first' or 'last': append to top or to end of waitlist?
     */
    public function to_waitlist_action($which_end)
    {
        // Security Check
        if (!$this->is_tutor) {
            throw new AccessDeniedException();
        }

        $user_ids = [];
        if (!empty($this->flash['users'])) {
            $user_ids = array_keys(array_filter($this->flash['users']));
        }

        if (!empty($user_ids)) {
            $course    = Course::find($this->course_id);
            $success_c = 0;
            $errors    = [];
            User::findEachMany(
                function (User $user) use ($course, &$success_c, &$errors) {
                    try {
                        $course->moveMemberToWaitlist($user, true);
                        $success_c++;
                    } catch (\Studip\Exception $e) {
                        $errors[] = $e->getMessage();
                    }
                },
                $user_ids
            );
            if ($success_c > 0) {
                PageLayout::postSuccess(
                    studip_interpolate(
                        ngettext(
                            'Eine Person wurde auf die Warteliste verschoben.',
                            '%{number} Personen wurden auf die Warteliste verschoben.',
                            $success_c
                        ),
                    ['number' => $success_c]
                    )
                );
            }
            if (count($errors)) {
                PageLayout::postError(
                    studip_interpolate(
                        ngettext(
                            'Eine Person konnte nicht auf die Warteliste verschoben werden:',
                            '%{number} Personen konnten nicht auf die Warteliste verschoben werden:',
                            count($errors)
                        ),
                        ['number' => count($errors)]
                    ),
                    array_map(htmlReady(...), $errors)
                );
            }
        } else {
            PageLayout::postError(_('Sie haben keine Personen zum Verschieben auf die Warteliste ausgewählt'));
        }

        $this->redirect('course/members/index');
    }

    /**
     * Displays all members of the course and their aux data
     */
    public function additional_action($format = null)
    {
        // Users get forwarded to aux_input
        if (!($this->is_dozent || $this->is_tutor)) {
            $this->redirect('course/members/additional_input');
            return;
        }

        Navigation::activateItem('/course/main/additional');

        // fetch course and aux data
        $course    = Course::findCurrent();
        $this->aux = $course->aux->getCourseData($course);
    }

    /**
     * Stora all members of the course and their aux data
     */
    public function store_additional_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $course = Course::findCurrent();

        foreach ($course->members->findBy('status', 'autor') as $member) {
            $course->aux->updateMember($member, Request::getArray($member->user_id));
        }

        $this->redirect($this->additionalURL());
    }

    /**
     * Aux input for users
     */
    public function additional_input_action()
    {
        Navigation::activateItem('/course/main/additional');

        // Fetch datafields for the user
        $course = Course::findCurrent();
        $member = $course->members->findOneBy('user_id', $GLOBALS['user']->id);
        $this->datafields = $member ? $course->aux->getMemberData($member) : [];

        $this->editable = false;

        // We need aux data in the view
        $this->aux = $course->aux;

        // Update em if they got submittet
        if (Request::submitted('save')) {
            $success = 0;

            $datafields = SimpleCollection::createFromArray($this->datafields);
            foreach (Request::getArray('aux') as $aux => $value) {
                $datafield = $datafields->findOneBy('datafield_id', $aux);
                if ($datafield) {
                    $typed = $datafield->getTypedDatafield();
                    if ($typed->isEditable()) {
                        $typed->setValueFromSubmit($value);
                        // Track success across each store process.
                        $success = $success + $typed->store();
                    }
                }
            }

            // Show success or error message.
            if ($success > 0) {
                PageLayout::postSuccess(_('Die Daten wurden gespeichert.'));
            } else {
                PageLayout::postWarning(_('Keine Veränderungen vorgenommen.'));
            }
        } else if ($course->aux_lock_rule_forced) {
            if (empty(array_column($this->datafields, 'content'))) {
                PageLayout::postWarning(_('Um die Anmeldung zur Veranstaltung abzuschließen, müssen Sie zusätzliche Angaben auf dieser Seite machen.'));
            }
        }
    }

    /**
     * Get the visibility of a user in a seminar
     * @return Array
     */
    private function getUserVisibility()
    {
        $member = CourseMember::find([$this->course_id, $this->user_id]);
        if (!$member) {
            return ['iam_visible' => false, 'visible_mode' => false];
        }

        $visibility = $member->visible;
        $status = $member->status;
        $result['visible_mode'] = false;

        if ($visibility) {
            $result['iam_visible'] = ($visibility === 'yes' || $visibility === 'unknown');

            if ($status === 'user' || $status === 'autor') {
                $result['visible_mode'] = 'participant';
            } else {
                $result['iam_visible'] = true;
                $result['visible_mode'] = false;
            }
        }

        return $result;
    }

    /**
     * Returns the Subject for the Messaging
     * @return String
     */
    private function getSubject()
    {
        $result = Course::find($this->course_id)->veranstaltungsnummer;

        return ($result == '') ? sprintf('[%s]', $this->course_title) :
                sprintf(_('[%s: %s]'), $result, $this->course_title);
    }

    private function createSidebar($filtered_members)
    {
        $course = Course::find($this->course_id);

        $sidebar = Sidebar::get();
        $widget  = $sidebar->addWidget(new ActionsWidget());

        if ($this->is_tutor || $this->config->COURSE_STUDENT_MAILING) {
            $widget->addLink(
                _('Rundmail schreiben'),
                URLHelper::getURL('dispatch.php/course/members/circular_mail', [
                    'course_id' => $this->course_id,
                    'default_subject' => $this->subject
                ]),
                Icon::create('mail')
            )->asDialog('size=auto');
        }
        if ($this->is_tutor) {
            //Calculate the course institutes here since they are needed
            //in three different parts of the followint source code.
            //The course institutes are the main institute and the
            //participating institutes.
            $course_institute_ids = [
                $course->home_institut->id
            ];
            foreach ($course->institutes as $inst) {
                if ($inst->id != $course->home_institut->id) {
                    $course_institute_ids[] = $inst->id;
                }
            }
            if ($this->is_dozent) {
                if (!$this->dozent_is_locked) {
                    $institute_ids = $course->institutes->pluck('id');

                    if (SeminarCategories::getByTypeId($course->status)->only_inst_user) {
                        $search_template = 'user_inst_not_already_in_sem';
                    } else {
                        $search_template = 'user_not_already_in_sem';
                    }

                    // create new search for dozent
                    $searchtype = new PermissionSearch(
                        $search_template,
                        sprintf(
                            _('%s suchen'),
                            get_title_for_status('dozent', 1, $course->status)
                        ),
                        'user_id',
                        [
                            'permission' => 'dozent',
                            'institute'  => $institute_ids,
                            'seminar_id' => $course->id,
                        ]
                    );

                    // quickfilter: dozents of institut
                    $sql = "SELECT `user_id`
                            FROM `user_inst`
                            WHERE `Institut_id` IN (:institute_ids)
                              AND `inst_perms` = 'dozent'";
                    $db = DBManager::get();
                    $statement = $db->prepare($sql);
                    $statement->execute(['institute_ids' => $course_institute_ids]);
                    $membersOfInstitute = $statement->fetchAll(PDO::FETCH_COLUMN);

                    // add "add dozent" to infobox
                    $mp = MultiPersonSearch::get("add_dozent{$this->course_id}")
                        ->setLinkText(sprintf(_('%s eintragen'), get_title_for_status('dozent', 1, $course->status)))
                        ->setDefaultSelectedUser($filtered_members['dozent']->pluck('user_id'))
                        ->setLinkIconPath("")
                        ->setTitle(sprintf(_('%s eintragen'), get_title_for_status('dozent', 1, $course->status)))
                        ->setExecuteURL($this->url_for('course/members/execute_multipersonsearch_dozent'))
                        ->setSearchObject($searchtype)
                        ->addQuickfilter(
                            sprintf(
                                ngettext(
                                    '%s der Einrichtung',
                                    '%s der Einrichtungen',
                                    count($course_institute_ids)
                                ),
                                $this->status_groups['dozent']
                            ),
                            $membersOfInstitute)
                        ->setNavigationItem('/course/members/view')
                        ->render();
                    $element = LinkElement::fromHTML($mp, Icon::create('add'));
                    $widget->addElement($element);
                }
                if (!$this->tutor_is_locked) {
                    $institute_ids = $course->institutes->pluck('id');

                    if (SeminarCategories::getByTypeId($course->status)->only_inst_user) {
                        $search_template = 'user_inst_not_already_in_sem';
                    } else {
                        $search_template = 'user_not_already_in_sem';
                    }

                    // create new search for tutor
                    $searchType = new PermissionSearch(
                        $search_template,
                        sprintf(
                            _('%s suchen'),
                            get_title_for_status('tutor', 1, $course->status)
                        ),
                        'user_id',
                        [
                            'permission' => ['dozent', 'tutor'],
                            'institute'  => $institute_ids,
                            'seminar_id' => $course->id,
                        ]
                    );

                    // quickfilter: tutors of institut
                    $sql = "SELECT `user_id`
                            FROM `user_inst`
                            WHERE `Institut_id` IN (:institute_ids)
                              AND `inst_perms` = 'tutor'";
                    $db = DBManager::get();
                    $statement = $db->prepare($sql);
                    $statement->execute(['institute_ids' => $course_institute_ids]);
                    $membersOfInstitute = $statement->fetchAll(PDO::FETCH_COLUMN);

                    // add "add tutor" to infobox
                    $mp = MultiPersonSearch::get("add_tutor{$this->course_id}")
                        ->setLinkText(sprintf(_('%s eintragen'), get_title_for_status('tutor', 1, $course->status)))
                        ->setDefaultSelectedUser($filtered_members['tutor']->pluck('user_id'))
                        ->setLinkIconPath('')
                        ->setTitle(sprintf(_('%s eintragen'), get_title_for_status('tutor', 1, $course->status)))
                        ->setExecuteURL($this->url_for('course/members/execute_multipersonsearch_tutor'))
                        ->setSearchObject($searchType)
                        ->addQuickfilter(
                            sprintf(
                                ngettext(
                                    '%s der Einrichtung',
                                    '%s der Einrichtungen',
                                    count($course_institute_ids)
                                ),
                                $this->status_groups['tutor']),
                            $membersOfInstitute)
                        ->setNavigationItem('/course/members/view')
                        ->render();
                    $element = LinkElement::fromHTML($mp, Icon::create('add'));
                    $widget->addElement($element);
                }
            }
            if (!$this->is_locked) {
                // create new search for members

                // The course institutes are the main institute and the
                // participating institutes.
                $course_institute_ids = [$course->home_institut->id];
                foreach ($course->institutes as $inst) {
                    if ($inst->id !== $course->home_institut->id) {
                        $course_institute_ids[] = $inst->id;
                    }
                }

                // create new search for autor
                $searchType = new PermissionSearch(
                    'user_not_already_in_sem',
                    sprintf(
                        _('%s suchen'),
                        get_title_for_status('autor', 1, $course->status)
                    ),
                    'user_id',
                    [
                        'permission' => ['autor', 'tutor', 'dozent'],
                        'institute'  => $sem_institutes ?? [],
                        'seminar_id' => $course->id,
                    ]
                );

                // quickfilter: autors of institut
                $sql = "SELECT `user_id`
                        FROM `user_inst`
                        WHERE `Institut_id` IN (:institute_ids)
                          AND `inst_perms` = 'autor'";
                $db = DBManager::get();
                $statement = $db->prepare($sql);
                $statement->execute(['institute_ids' => $course_institute_ids]);
                $membersOfInstitute = $statement->fetchAll(PDO::FETCH_COLUMN);

                // add "add autor" to infobox
                $mp = MultiPersonSearch::get("add_autor{$this->course_id}")
                    ->setLinkText(sprintf(_('%s eintragen'), get_title_for_status('autor', 1, $course->status)))
                    ->setDefaultSelectedUser($filtered_members['autor']->pluck('user_id'))
                    ->setLinkIconPath('')
                    ->setTitle(sprintf(_('%s eintragen'), get_title_for_status('autor', 1, $course->status)))
                    ->setExecuteURL($this->url_for('course/members/execute_multipersonsearch_autor'))
                    ->setSearchObject($searchType)
                    ->addQuickfilter(
                        sprintf(
                            ngettext(
                                '%s der Einrichtung',
                                '%s der Einrichtungen',
                                count($course_institute_ids)
                            ),
                            $this->status_groups['autor']
                        ),
                        $membersOfInstitute
                    )
                    ->setNavigationItem('/course/members/view')
                    ->render();
                $widget->addElement(LinkElement::fromHTML(
                    $mp,
                    Icon::create('add')
                ));

                $ignore = array_merge(
                    $filtered_members['dozent']->pluck('user_id'),
                    $filtered_members['tutor']->pluck('user_id'),
                    $filtered_members['autor']->pluck('user_id'),
                    $filtered_members['user']->pluck('user_id'),
                    $filtered_members['awaiting']->pluck('user_id')
                );

                // add "add person to waitlist" to sidebar
                if (
                    $course->isAdmissionEnabled()
                    && $course->getCourseSet()->hasAlgorithmRun()
                    && !$course->admission_disable_waitlist
                    && (!$course->getFreeSeats() || $course->admission_disable_waitlist_move)
                ) {

                    $mp = MultiPersonSearch::get("add_waitlist{$this->course_id}")
                        ->setLinkText(_('Person(en) auf Warteliste eintragen'))
                        ->setDefaultSelectedUser($ignore)
                        ->setLinkIconPath('')
                        ->setTitle(_('Person(en) auf Warteliste eintragen'))
                        ->setExecuteURL($this->url_for('course/members/execute_multipersonsearch_waitlist'))
                        ->setSearchObject($searchType)
                        ->addQuickfilter(_('Mitglieder der Einrichtung'), $membersOfInstitute)
                        ->setNavigationItem('/course/members/view')
                        ->render();
                    $element = LinkElement::fromHTML($mp, Icon::create('add'));
                    $widget->addElement($element);
                }

                if ($course->admission_prelim) {
                    $mp = MultiPersonSearch::get("add_accepted{$course->id}")
                        ->setLinkText(_('Vorläufig akzeptierte Teilnehmende eintragen'))
                        ->setDefaultSelectedUser($ignore)
                        ->setLinkIconPath('')
                        ->setTitle(_('Vorläufig akzeptierte Teilnehmende eintragen'))
                        ->setExecuteURL($this->execute_multipersonsearch_acceptedURL())
                        ->setSearchObject($searchType)
                        ->addQuickfilter(_('Mitglieder der Einrichtung'), $membersOfInstitute)
                        ->setNavigationItem('/course/members/view')
                        ->render();
                    $element = LinkElement::fromHTML($mp, Icon::create('add'));
                    $widget->addElement($element);
                }
                $widget->addLink(
                    _('Teilnehmendenliste importieren'),
                    $this->import_autorlistURL(),
                    Icon::create('persons'),
                    ['data-dialog' => 1]
                );

            }

            if (Config::get()->EXPORT_ENABLE) {
                $widget = $sidebar->addWidget(new ExportWidget());

                $widget->addLink(
                    _('Als Excel-Datei exportieren'),
                    $this->exportURL([
                        'course_id' => $this->course_id,
                        'format' => 'xlsx',
                    ]),
                    Icon::create('export')
                );

                $widget->addLink(
                    _('Als CSV-Datei exportieren'),
                    $this->exportURL([
                        'course_id' => $this->course_id,
                        'format' => 'csv',
                    ]),
                    Icon::create('export')
                );

                $widget->addLink(
                    _('Als Word-Datei exportieren'),
                    $this->export_wordURL([
                        'course_id' => $this->course_id
                    ]),
                    Icon::create('export')
                );

                if (count($this->awaiting) > 0) {
                    $widget->addLink(
                        _('Warteliste als Excel-Datei exportieren'),
                        $this->exportURL([
                            'course_id' => $this->course_id,
                            'format'    => 'xlsx',
                            'status'    => $this->waiting_type,
                        ]),
                        Icon::create('export')
                    );
                    $widget->addLink(
                        _('Warteliste als CSV-Datei exportieren'),
                        $this->exportURL([
                            'course_id' => $this->course_id,
                            'format'    => 'csv',
                            'status'    => $this->waiting_type,
                        ]),
                        Icon::create('export')
                    );

                    $widget->addLink(
                        _('Als Word-Datei exportieren'),
                        $this->export_wordURL([
                            'course_id' => $this->course_id,
                            'status'    => $this->waiting_type
                        ]),
                        Icon::create('export')
                    );
                }
            }

            $options = new OptionsWidget();

            if ($this->is_dozent) {
                $options->addCheckbox(
                    _('Rundmails von Studierenden erlauben'),
                    $this->config->COURSE_STUDENT_MAILING,
                    $this->url_for('course/members/toggle_student_mailing/1'),
                    $this->url_for('course/members/toggle_student_mailing/0'),
                    ['title' => _('Über diese Option können Sie Studierenden das Schreiben von Nachrichten an alle anderen Teilnehmenden der Veranstaltung erlauben')]
                );
            }
            $sidebar->addWidget($options);
        } else if ($this->is_autor || $this->is_user) {
            // Visibility preferences
            if ($this->my_visibility['iam_visible']) {
                $text = _('Sie sind für andere Teilnehmenden auf der Teilnehmendenliste sichtbar.');
                $icon = Icon::create('visibility-invisible');
                $modus = 'make_invisible';
                $link_text = _('Klicken Sie hier, um unsichtbar zu werden.');
            } else {
                $text = _('Sie sind für andere Teilnehmenden auf der Teilnehmendenliste nicht sichtbar.');
                $icon = Icon::create('visibility-visible');
                $modus = 'make_visible';
                $link_text = _('Klicken Sie hier, um sichtbar zu werden.');
            }

            $actions = $sidebar->addWidget(new ActionsWidget());
            $actions->addLink(
                $link_text,
                $this->change_visibilityURL($modus, $this->my_visibility['visible_mode']),
                $icon,
                ['title' => $text]
            );
        }
    }

    /**
     * Handles the export of the course member list as a Word document.
     *
     * @return void
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function export_word_action(): void
    {
        $status  = Request::get('status', '');
        $course  = Course::findCurrent();

        $file = new CourseMemberService($course, $status);
        $file->save();

        $this->response->add_header('Cache-Control', 'cache, must-revalidate');
        $this->render_temporary_file(
            $file->getFilePath(),
            $file->getFilename(),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }

    public function export_action()
    {
        $export_format = Request::get('format');
        $status = Request::get('status');

        if (!in_array($export_format, ['csv', 'xlsx'])) {
            throw new Exception('Wrong format');
        }
        $header = [
            _('Status'),
            _('Anrede'),
            _('Titel'),
            _('Vorname'),
            _('Nachname'),
            _('Titel nachgestellt'),
            _('Benutzername'),
            _('Adresse'),
            _('Telefonnr.'),
            _('E-Mail'),
            _('Anmeldedatum'),
            _('Matrikelnummer'),
            _('Studiengänge'),
            _('Position'),
        ];

        $course = Course::findCurrent();
        $aux = $course->aux ? $course->aux->getCourseData($course, true) : false;

        $members = $course->getMembersData($status);

        $datafields = DataField::getDataFields('user');
        $datafields = array_filter(
            $datafields,
            fn(DataField $datafield) => $datafield->accessAllowed()
        );

        if (count($datafields) > 0) {
            foreach ($datafields as $datafield) {
                $header[] = (string) $datafield->name;
            }
        }

        // Zusatzangaben
        if ($aux) {
            foreach ($aux['head'] as $key => $value) {
                if ($key === 'name') {
                    continue;
                }
                $header[] = $value;
            }
        }

        if (in_array($status, ['awaiting', 'claiming'])) {
            $filename = _('Wartelistenexport');
        } else {
            $filename = _('Teilnehmendenexport');
        }

        $filename = $filename . ' ' . $this->course_title . '.' . $export_format;

        $this->render_spreadsheet($header, $members, $export_format, $filename);
    }

    public function toggle_student_mailing_action($state)
    {
        if (!$this->is_dozent) {
            throw new AccessDeniedException();
        }

        $this->config->store('COURSE_STUDENT_MAILING', $state);

        $this->redirect($this->indexURL());
    }

    public function circular_mail_action()
    {
        if (!$this->is_tutor && !$this->config->COURSE_STUDENT_MAILING) {
            throw new AccessDeniedException();
        }

        //Calculate the amount of recipients for each group:
        $visibility_constraint = !$this->is_tutor ? " AND visible <> 'no'" : "";
        $this->user_count = CourseMember::countBySql("seminar_id=? AND status=?" . $visibility_constraint, [$this->course_id, 'user']);
        $this->autor_count = CourseMember::countBySql("seminar_id=? AND status=?" . $visibility_constraint, [$this->course_id, 'autor']);
        $this->tutor_count = CourseMember::countBySql("seminar_id=? AND status=?" . $visibility_constraint, [$this->course_id, 'tutor']);
        $this->dozent_count = CourseMember::countBySql("seminar_id=? AND status=?" . $visibility_constraint, [$this->course_id, 'dozent']);

        //Use the correct names for thte four status groups:
        $course = Course::find($this->course_id);
        $this->user_name   = get_title_for_status('user', 0, $course->status);
        $this->autor_name  = get_title_for_status('autor', 0, $course->status);
        $this->tutor_name  = get_title_for_status('tutor', 0, $course->status);
        $this->dozent_name = get_title_for_status('dozent', 0, $course->status);

        $this->default_subject = Request::get('default_subject');

        if ($this->is_tutor) {
            $this->awaiting_count = AdmissionApplication::countBySql(
                "seminar_id = :course_id AND status = 'awaiting'",
                [
                    'course_id' => $this->course_id
                ]
            );
            $this->accepted_count = AdmissionApplication::countBySql(
                "seminar_id = :course_id AND status = 'accepted'",
                [
                    'course_id' => $this->course_id
                ]
            );
            $cs = CourseSet::getSetForCourse($this->course_id);
            if (is_object($cs) && !$cs->hasAlgorithmRun()) {
                $this->claiming_count = count(AdmissionPriority::getPrioritiesByCourse($cs->getId(), $this->course_id));
            }
        }
        $this->default_selected_groups = ['dozent', 'tutor', 'autor', 'user'];
        $this->all_available_groups = $this->default_selected_groups;
        if ($this->is_tutor) {
            //The user has at least tutor permissions:
            if ($this->accepted_count) {
                $this->all_available_groups[] = 'accepted';
            }
            if ($this->awaiting_count) {
                $this->all_available_groups[] = 'awaiting';
            }
            if ($this->claiming_count) {
                $this->all_available_groups[] = 'claiming';
            }
        }
        if (Request::submitted('write')) {
            CSRFProtection::verifyUnsafeRequest();

            $this->selected_groups = Request::getArray('selected_groups');
            //Filter all selected groups by the list of all available groups:
            $filtered_groups = [];
            foreach ($this->selected_groups as $group) {
                if (in_array($group, $this->all_available_groups)) {
                    $filtered_groups[] = $group;
                }
            }
            //Do custom filtering.
            $filters = [];
            $who_param = [];

            foreach ($filtered_groups as $group) {
                if ($group === 'awaiting') {
                    $filters[] = 'awaiting';
                } elseif ($group === 'accepted') {
                    $filters[] = 'prelim';
                } elseif ($group === 'claiming') {
                    $filters[] = 'claiming';
                } elseif ($group === 'user') {
                    $filters[] = 'all';
                    $who_param[] = 'user';
                } elseif ($group === 'autor') {
                    $filters[] = 'all';
                    $who_param[] = 'autor';
                } elseif ($group === 'tutor') {
                    $filters[] = 'all';
                    $who_param[] = 'tutor';
                } elseif ($group === 'dozent') {
                    $filters[] = 'all';
                    $who_param[] = 'dozent';
                }
            }
            $filters = array_unique($filters);
            if (!$filters) {
                PageLayout::postError(
                    _('Es wurde keine Gruppe ausgewählt!')
                );
                return;
            }

            $url_params = [
                'course_id' => $this->course_id,
                'default_subject' => $this->default_subject,
                'filter' => implode(',', array_unique($filters)),
                'emailrequest' => 1
            ];
            if ($who_param) {
                $url_params['who'] = implode(',', $who_param);
            }

            $this->redirect(URLHelper::getURL(
                'dispatch.php/messages/write',
                $url_params
            ));

        }
    }
    public function checkUserVisibility()
    {
        $membership = CourseMember::findOneBySQL("visible = 'unknown' AND Seminar_id = ?", [$this->course_id]);
        if ($membership) {
            CourseMember::findEachBySQL(
                function(CourseMember $membership) {
                    $membership->visible = 'yes';
                    $membership->store();
                },
                "status IN ('tutor', 'dozent') AND Seminar_id = ?",
                [$this->course_id]
            );

            CourseMember::findEachBySQL(
                function(CourseMember $membership) {
                    $user = $membership->user;
                    if (in_array($user->visible, ['no','never'])
                        || ($user->visible === 'unknown') && (int)!Config::get()->USER_VISIBILITY_UNKNOWN
                    ) {
                        $mode = 'no';
                    } else {
                        $mode = 'yes';
                    }
                    $membership->visible = $mode;
                    $membership->store();
                },
                "Seminar_id = ? AND visible='unknown'",
                [$this->course_id]
            );
        }
    }

    private function setMemberStatus($members, $status, $next_status, $direction): array
    {
        $msgs = [
            'success'   => [],
            'no_tutor'  => []
        ];
        foreach ($members as $user_id) {
            $temp_user = User::find($user_id);
            if ($next_status === 'tutor' && !$GLOBALS['perm']->have_perm('tutor', $user_id)) {
                $msgs['no_tutor'][$user_id] = $temp_user->getFullName();
            } else {
                if ($temp_user) {
                    $next_pos = 0;
                    // get the next position of the user
                    switch ($next_status) {
                        case 'autor':
                        case 'user':
                            // get the current position of the user
                            $next_pos = $this->getPosition($user_id);
                            break;
                        // set the status to tutor
                        case 'tutor':
                            // get the next position of the user
                            $next_pos = CourseMember::getNextPosition($next_status, $this->course_id);
                            // resort the tutors
                            CourseMember::resortMembership($this->course_id, $this->getPosition($user_id));
                            break;
                    }

                    $membership = CourseMember::findOneBySQL(
                        'Seminar_id = ? AND user_id = ? AND status = ?',
                        [$this->course_id, $user_id, $status]
                    );
                    $membership->status = $next_status;
                    $membership->position = $next_pos;

                    if ($membership->store()) {
                        StudipLog::log('SEM_CHANGED_RIGHTS', $this->course_id, $user_id, $next_status,
                            $this->getLogLevel($direction, $next_status));
                        NotificationCenter::postNotification('CourseMemberStatusDidUpdate', $this->course_id, $user_id);
                        if ($next_status === 'autor') {
                            CourseMember::resortMembership($this->course_id, $next_pos);
                        }
                        $msgs['success'][$user_id] = $temp_user->getFullName();
                    }
                }
            }
        }

        return $msgs;
    }

    /**
     * Adds the given users to the accepted list of the current course
     */
    private function addAccepted(array $user_ids, Course $course): void
    {
        $added = [];
        $failed = [];

        User::findEachMany(
            function (User $user) use ($course, &$added, &$failed): void {
                if (AdmissionApplication::addAcceptedMember($user, $course)) {
                    $added[] = $user->getFullName();
                } else {
                    $failed[] = $user->getFullName();
                }
            },
            $user_ids
        );

        if (count($added) > 0) {
            PageLayout::postSuccess(
                sprintf(
                    ngettext(
                        'Es wurde %u neue Person auf die vorläufig akzeptierten Liste hinzugefügt.',
                        'Es wurden %u neue Personen auf die vorläufig akzeptierten Liste eingetragen.',
                        count($added)
                    ),
                    count($added)
                ),
                array_map(htmlReady(...), $added)
            );
        }
        if (count($failed) > 0) {
            PageLayout::postError(
                sprintf(
                    ngettext(
                        '%u Person konnte nicht auf die vorläufig akzeptierten Liste eingetragen werden.',
                        '%u neue Personen konnten nicht auf die vorläufig akzeptierten Liste eingetragen werden.',
                        count($failed)
                    ),
                    count($failed),
                ),
                array_map(htmlReady(...), $failed)
            );
        }
    }

    /**
     * Adds the given users to the target course.
     * @param array $users users to add
     * @param string $target_course_id which course to add users to
     * @param bool $move move users (=delete in source course) or just add to target course?
     * @return array success and failure statuses
     */
    private function sendToCourse(array $users, string $target_course_id, bool $move = false): array
    {
        $msg = [
            'succes' => [],
            'failed' => [],
            'existing' => [],
        ];
        foreach ($users as $user_id) {
            if (!CourseMember::exists([$target_course_id, $user_id])) {
                $user = User::find($user_id);
                if (!$user) {
                    continue;
                }
                $target_course = Course::find($target_course_id);
                if ($target_course->addMember($user, regard_contingent: false)) {
                    if ($move) {
                        $remove_from = Course::find($this->course_id);
                        $remove_from->deleteMember($user);
                    }
                    $msg['success'][] = $user_id;
                } else {
                    $msg['failed'][] = $user_id;
                }
            } else {
                $msg['existing'][] = $user_id;
            }
        }
        return $msg;
    }

    /**
     * Get the position out of the database
     */
    private function getPosition(string $user_id): int
    {
        $membership = CourseMember::find([$this->course_id, $user_id]);
        return $membership->position ?? 0;
    }

    private function getLogLevel($direction, $status)
    {
        if ($direction === 'upgrade') {
            $directionString = 'hochgestuft';
        } else {
            $directionString = 'runtergestuft';
        }
        $log_level = '';
        switch ($status) {
            case 'tutor': $log_level = 'zum Tutor';
                break;
            case 'autor': $log_level = 'zum Autor';
                break;
            case 'dozent': $log_level = 'zum Dozenten';
                break;
        }

        return sprintf('%s %s', $directionString, $log_level);
    }

    /**
     * Checks whether a tutor is attempting to add or remove tutors or
     * instructors.
     *
     * @param array $users Selected users
     * @param string $course_id ID of the course
     */
    private function validateTutorPermission(array $users, string $course_id): void
    {
        $invalid_user_ids = array_filter($users, function ($user_id) use ($course_id): bool {
            return $GLOBALS['perm']->have_studip_perm('tutor', $course_id, $user_id);
        });

        if (count($invalid_user_ids) > 0) {
            throw new AccessDeniedException(_('Sie dürfen keine Lehrenden oder Tutor/-innen aus dieser Veranstaltungen austragen.'));
        }
    }
}
