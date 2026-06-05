<?php

require_once 'lib/messaging.inc.php';
require_once 'lib/user_visible.inc.php';

/**
 * This controller realises the basal functionalities of a studygroup.
 *
 * @license GPL2 or any later version
 */
class Course_StudygroupController extends AuthenticatedController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (Config::Get()->STUDYGROUPS_ENABLE
            || in_array($action, words('globalmodules savemodules deactivate'))
        ) {

            // args at position zero is always the studygroup-id
            if (!empty($args[0]) && $action == 'details') {
                if (SeminarCategories::GetBySeminarId($args[0])->studygroup_mode == false) {
                    throw new Exception(_('Dieses Seminar ist keine Studiengruppe!'));
                }
            }
            PageLayout::setTitle(_("Studiengruppe bearbeiten"));
            PageLayout::setHelpKeyword('Basis.Studiengruppen');
        } else {
            throw new Exception(_("Die von Ihnen gewählte Option ist im System nicht aktiviert."));
        }
        $context_id = Context::getId();
        if ($context_id) {
            $this->view = $this->getView($context_id);
            URLHelper::bindLinkParam('cid', $context_id);
        }
    }

    private function getView($course_id)
    {
        // Obtain user config
        if (isset($GLOBALS['user']->cfg->STUDYGROUP_VIEWS)) {
            $user_cfg = json_decode($GLOBALS['user']->cfg->STUDYGROUP_VIEWS, true);
        } else {
            $user_cfg = [];
        }

        // Obtain default view
        $default_view = $user_cfg[$course_id] ?? 'gallery';
        $view = Request::option('view', $default_view);
        if (!in_array($view, words('gallery list'))) {
            $view = 'gallery';
        }

        // Store default view
        if ($view !== 'gallery') {
            $user_cfg[$course_id] = $view;
        } elseif (isset($user_cfg[$course_id])) {
            unset($user_cfg[$course_id]);
        }
        $GLOBALS['user']->cfg->store('STUDYGROUP_VIEWS', json_encode($user_cfg));

        return $view;
    }

    /**
     * shows details of a studygroup
     *
     * @param string $id of a studygroup
     * @return void
     */
    public function details_action($id = null)
    {
        if (!$id) {
            $id = Context::getId();
        }

        $studygroup = Course::find($id);
        $this->sidebarActions = [];
        if (Request::isXhr()) {
            PageLayout::setTitle(_('Studiengruppendetails'));
        } else {
            PageLayout::setTitle((Context::getHeaderLine() ?: Course::find($id)->getFullName()) . ' - ' . _('Studiengruppendetails'));
            PageLayout::setHelpKeyword('Basis.StudiengruppenAbonnieren');

            $stmt = DBManager::get()->prepare("SELECT * FROM admission_seminar_user"
                                              . " WHERE user_id = ? AND seminar_id = ?");
            $stmt->execute([$GLOBALS['user']->id, $id]);
            $data = $stmt->fetch();

            if (isset($data['status'])) {
                $membership_requested = $data['status'] === 'accepted';
            } else {
                $membership_requested = false;
            }
            $invited = StudygroupModel::isInvited($GLOBALS['user']->id, $id);

            if (!preg_match('/^(' . preg_quote($GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP'], '/') . ')?([a-zA-Z0-9_-]+\.php)([a-zA-Z0-9_?&=-]*)$/', Request::get('send_from_search_page'))) {
                $send_from_search_page = '';
            } else {
                $send_from_search_page = Request::get('send_from_search_page');
            }

            $icon = Icon::create('door-enter');
            if ($GLOBALS['perm']->have_studip_perm('autor', $studygroup->id) || $membership_requested) {
                $action = _('Persönlicher Status:');
                if ($membership_requested) {
                    $icon = $icon->copyWithRole('info');
                    $infotext = _('Mitgliedschaft bereits beantragt!');
                } else {
                    $infolink = URLHelper::getURL('dispatch.php/course/go', ['to' => $studygroup->id]);
                    $infotext = _('Direkt zur Studiengruppe');
                }
            } else if ($GLOBALS['perm']->have_perm('admin')) {
                $action   = _('Hinweis');
                $infotext = _('Sie sind Admin und können sich daher nicht für Studiengruppen anmelden.');
                $icon     = Icon::create('decline', 'attention');
            } else {
                $action           = _('Aktionen');
                $infolink         = $this->url_for("course/enrolment/apply/{$studygroup->id}");
                $infolink_options = ['data-dialog' => ''];
                // customize link text if user is invited or group access is restricted
                if ($invited) {
                    $infotext = _('Einladung akzeptieren');
                } elseif ($studygroup->admission_prelim) {
                    $infotext = _('Mitgliedschaft beantragen');
                } else {
                    $infotext = _('Studiengruppe beitreten');
                }
            }
            $sidebar = Sidebar::get();
            $sidebar->setContextAvatar(StudygroupAvatar::getAvatar($studygroup->id));

            $iwidget = new SidebarWidget();
            $iwidget->setTitle(_('Information'));
            $iwidget->addElement(new WidgetElement(_('Hier sehen Sie weitere Informationen zur Studiengruppe. Außerdem können Sie ihr beitreten/eine Mitgliedschaft beantragen.')));
            $sidebar->addWidget($iwidget);

            $awidget = new LinksWidget();
            $awidget->setTitle($action);
            $awidget->addLink($infotext, $infolink ?? '#', $icon, $infolink_options ?? []);
            if ($send_from_search_page) {
                $awidget->addLink(
                    _('Zurück zur Suche'),
                    URLHelper::getURL($send_from_search_page),
                    Icon::create('link-intern')
                );
            }
            $sidebar->addWidget($awidget);

            $this->sidebarActions = $awidget->getElements();

            $ashare = new ShareWidget();
            $ashare->addCopyableLink(
                _('Link zu dieser Studiengruppe kopieren'),
                $this->url_for("course/studygroup/details/" . $studygroup->id, ['cid' => null, 'again' => 'yes']),
                Icon::create('group')
            );
            $sidebar->addWidget($ashare);
        }
        $this->studygroup = $studygroup;
    }

    /**
     * displays a form for editing studygroups with corresponding data
     *
     * @param string id of a studygroup
     *
     * @return void
     */
    public function edit_action()
    {
        if (!$GLOBALS['perm']->have_studip_perm('dozent', Context::getId())) {
            throw new AccessDeniedException();
        }

        PageLayout::setTitle(Context::getHeaderLine() . ' - ' . _('Studiengruppe bearbeiten'));
        Navigation::activateItem('/course/admin/main');
        PageLayout::setHelpKeyword('Basis.StudiengruppenBearbeiten');

        $course = Context::get();

        $expiration_date = CourseConfig::get($course->id)->STUDYGROUP_EXPIRATION_DATE;
        if (!$expiration_date) {
            $expiration_date = _('Unbegrenzt');
        }

        $zugang_options = [
            'all' => _('Offen für alle'),
            'invite' => _('Auf Anfrage'),
            'connectedcourse' => _('Für Mitglieder der zugehörigen Lehrveranstaltung')
        ];
        if (Config::get()->STUDYGROUPS_INVISIBLE_ALLOWED) {
            $zugang_options['invisible'] = _('Unsichtbar');
        }

        $form = \Studip\Forms\Form::fromSORM(Context::get(), [
            'legend' => _('Grunddaten'),
            'fields' => [
                'name' => [
                    'label' => _('Name'),
                    'required' => true
                ],
                'beschreibung' => _('Beschreibung'),
                'zugang' => [
                    'label' => _('Zugang'),
                    'type' => 'select',
                    'options' => $zugang_options,
                    'value' => function () use ($course) {
                        $courseset = CourseSet::getSetForCourse($course->id);
                        if ($courseset && $courseset->getId() === CourseSet::getConnectedcourseAdmissionSetId()) {
                            return 'connectedcourse';
                        } elseif (!$course->visible) {
                            return 'invisible';
                        } else {
                            return $course->admission_prelim > 0 ? 'invite' : 'all';
                        }
                    },
                    'store' => function ($value, $input) {
                        $course = $input->getContextObject();
                        switch ($value) {
                            case 'connectedcourse':
                                CourseSet::addCourseToSet(CourseSet::getConnectedcourseAdmissionSetId(), $course->id);
                                $course->visible = 1;
                                break;
                            case 'invisible':
                                CourseSet::removeCourseFromSet(CourseSet::getConnectedcourseAdmissionSetId(), $course->id);
                                if (Config::get()->STUDYGROUPS_INVISIBLE_ALLOWED) {
                                    $course->visible = 0;
                                    break;
                                }
                            case 'invite':
                                CourseSet::removeCourseFromSet(CourseSet::getConnectedcourseAdmissionSetId(), $course->id);
                                $course->visible = 1;
                                $course->admission_prelim = 1;
                                $course->admission_prelim_txt = _('Die Moderator:innen der Studiengruppe können Ihren Aufnahmewunsch bestätigen oder ablehnen. Erst nach Bestätigung erhalten Sie vollen Zugriff auf die Gruppe.');
                                break;
                            case 'all':
                                CourseSet::removeCourseFromSet(CourseSet::getConnectedcourseAdmissionSetId(), $course->id);
                                $course->visible = 1;
                                $course->admission_prelim = 0;
                                break;
                        }
                        $course->store();
                    }
                ]
            ]
        ])->addSORM(
            Context::get(), [
                'legend' => _('Erweiterte Einstellungen'),
                'fields' => [
                    'ablaufdatum' => [
                        'label' => _('Ablaufdatum / Löschdatum'),
                        'type' => 'datetimepicker',
                        'value' => $expiration_date,
                        'store' => function ($value) {
                            CourseConfig::get(Context::getId())->store('STUDYGROUP_EXPIRATION_DATE', $value);
                        }
                    ],
                    'tags' => [
                        'label' => _('Schlagwörter'),
                        'type' => 'multiquicksearch',
                        'addlabel' => _('Schlagwort hinzufügen'),
                        'value' => function () {
                            $course = Context::get();
                            $tags = Tag::getByRange($course->id, 'course');
                            return array_map(function ($t) { return $t->name; }, $tags);
                        },
                        'searchtype' => (string) SQLSearch::get('SELECT `name`, `name` FROM `tags` WHERE `active` = 1 AND `name` LIKE :input', _('Schlagwort suchen')),
                        'autocomplete' => true,
                        'mapper' => function ($value, $obj) {
                            $tags = [];
                            foreach ($value as $name) {
                                if ($name) {
                                    if ($tag = Tag::findOneByName($name)) {
                                        if ($tag->active) {
                                            $tags[] = $tag;
                                        }
                                    } else {
                                        $tag = new Tag();
                                        $tag->name = $name;
                                        $tag->store();
                                        $tags[] = $tag;
                                    }
                                }
                            }
                            return $tags;
                        },
                        'store' => function ($tags, $input) {
                            $course = $input->getContextObject();
                            $tag_ids = [];
                            foreach ($tags as $tag) {
                                $tag_ids[] = $tag->id;
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
                                        'tag_id'     => $tag->id,
                                    ]);
                                }
                            }
                            TagRelation::deleteBySQL(
                                "`range_id` = :course_id AND `range_type` = 'course' AND `tag_id` NOT IN (:ids)",
                                [
                                    'ids'       => $tag_ids,
                                    'course_id' => $course->id
                                ]
                            );
                        }
                    ]
                ]
            ]
        )->setURL($this->editURL())
            ->autoStore();

        $actions = new ActionsWidget();

        $actions->addLink(
            _('Neue Studiengruppe anlegen'),
            $this->url_for('course/wizard?studygroup=1'),
            Icon::create('add')
        );
        $actions->addLink(
            _('Diese Studiengruppe löschen'),
            $this->deleteURL(),
            Icon::create('trash'),
            [
                'data-confirm' => ('Sind Sie sicher, dass Sie diese Studiengruppe löschen möchten?'),
                'data-secure-disable' => '',
            ]
        )->asButton();

        Sidebar::get()->addWidget($actions);

        $this->render_form($form);
    }

    /**
     * updates studygroups with respect to the corresponding form data
     *
     * @param string id of a studygroup
     *
     * @return void
     */
    public function update_action()
    {
        global $perm;

        $id = Context::getId();

        // if we are permitted to edit the studygroup get some data...
        if ($perm->have_studip_perm('dozent', $id)) {
            $errors    = [];
            $course    = Course::find($id);

            CSRFProtection::verifyUnsafeRequest();

            if (Request::submitted('replace_founder')) {
                // retrieve old founder
                $old_dozent = current(StudygroupModel::getFounder($id));

                // remove old founder
                StudygroupModel::promote_user($old_dozent['uname'], $id, 'tutor');

                // add new founder
                $new_founder = Request::option('choose_founder');
                StudygroupModel::promote_user(get_username($new_founder), $id, 'dozent');
            } else {
                // test whether we have a group name...
                if (!Request::get('groupname')) {
                    $errors[] = _("Bitte Gruppennamen angeben");
                }
                if (count($errors)) {
                    $this->flash['errors'] = $errors;
                    $this->flash['edit']   = true;
                    // Everything seems fine, let's update the studygroup
                } else {
                    $course->name           = Request::get('groupname');         // seminar-class quotes itself
                    $course->beschreibung   = Request::get('groupdescription');  // seminar-class quotes itself
                    $course->lesezugriff    = 1;
                    $course->schreibzugriff = 1;
                    $course->visible        = 1;

                    $cs_id = CourseSet::getConnectedcourseAdmissionSetId();
                    if (Request::get('groupaccess') === 'all') {
                        $course->admission_prelim = 0;
                        CourseSet::removeCourseFromSet($cs_id, $id);
                    } elseif(Request::get('groupaccess') === 'top-course') {
                        CourseSet::addCourseToSet($cs_id, $id);
                    } else {
                        $course->admission_prelim = 1;
                        if (Config::get()->STUDYGROUPS_INVISIBLE_ALLOWED && Request::get('groupaccess') === 'invisible') {
                            $course->visible = 0;
                        }
                        $course->admission_prelim_txt = _('Die für die Moderation zuständigen Personen der Studiengruppe können Ihren Aufnahmewunsch bestätigen oder ablehnen. Erst nach Bestätigung erhalten Sie vollen Zugriff auf die Gruppe.');

                        CourseSet::removeCourseFromSet($cs_id, $id);
                    }
                    $course->store();
                }
            }
        }

        if (!$this->flash['errors']) {
            // Everything seems fine
            PageLayout::postSuccess(_('Die Änderungen wurden erfolgreich übernommen.'));
        }
        // let's go to the studygroup
        $this->redirect('course/studygroup/edit');
    }


    /**
     * displays a paginated member overview of a studygroup
     *
     * @param string id of a studypgroup
     * @param string page number the current page
     *
     * @return void
     *
     */
    public function members_action()
    {
        $sem = Context::get();
        if (!$sem) {
            throw new CheckObjectException(_('Sie haben kein Objekt gewählt.'));
        }
        $id = $sem->id;

        PageLayout::setTitle(Context::getHeaderLine() . ' - ' . _('Teilnehmende'));
        Navigation::activateItem('/course/members');
        PageLayout::setHelpKeyword('Basis.StudiengruppenBenutzer');

        Request::set('choose_member_parameter', $this->flash['choose_member_parameter']);

        $this->studip_module = checkObjectModule('StudygroupParticipants');
        object_set_visit_module($this->studip_module->getPluginId());


        $this->last_visitdate   = object_get_visit($id,  $this->studip_module->getPluginId());
        $this->anzahl           = StudygroupModel::countMembers($id);
        $this->groupname        = $sem->getFullName();
        $this->sem_id           = $id;
        $this->groupdescription = $sem->beschreibung;
        $this->moderators       = $sem->getMembersWithStatus('dozent');
        $this->tutors           = $sem->getMembersWithStatus('tutor');
        $this->autors           = $sem->getMembersWithStatus('autor');
        $this->accepted         = $sem->admission_applicants->findBy('status', 'accepted');
        $this->sem_class        = $sem->getSemClass();
        $this->invitedMembers   = StudygroupInvitation::findBySQL('sem_id = ? ORDER BY mkdate', [$id]);
        $this->rechte           = $GLOBALS['perm']->have_studip_perm('tutor', $id);
        $this->page = null;
        $this->setupMembersSidebar($sem);
    }

    /**
     *
     */
    private function setupMembersSidebar(Course $course)
    {
        $actions = new ActionsWidget();
        if ($this->rechte) {
            $quoted_id = DBManager::get()->quote($course->id);
            $vis_query = get_vis_query();

            $query = "SELECT auth_user_md5.user_id,
                             {$GLOBALS['_fullname_sql']['full_rev']} AS fullname,
                             username, perms
                      FROM auth_user_md5
                      LEFT JOIN user_info ON (auth_user_md5.user_id = user_info.user_id)
                      LEFT JOIN seminar_user ON (auth_user_md5.user_id = seminar_user.user_id AND seminar_user.Seminar_id = {$quoted_id})
                      WHERE perms NOT IN ('root', 'admin')
                        AND `seminar_user`.`user_id` IS NULL
                        AND {$vis_query}
                        AND (username LIKE :input
                          OR CONCAT(Vorname, ' ', Nachname) LIKE :input
                          OR CONCAT(Nachname, ' ', Vorname) LIKE :input
                          OR {$GLOBALS['_fullname_sql']['full_rev']} LIKE :input)
                      ORDER BY fullname ASC";

            $inviting_search = new SQLSearch($query, _('Nutzer suchen'), 'user_id');

            $mp  = MultiPersonSearch::get('studygroup_invite_' . $course->id)
                                    ->setLinkText(_('Neue Gruppenmitglieder einladen'))
                                    ->setLinkIconPath('')
                                    ->setTitle(_('Neue Gruppenmitglieder einladen'))
                                    ->setExecuteURL($this->url_for('course/studygroup/execute_invite/', ['view' => $this->view]))
                                    ->setSearchObject($inviting_search)
                                    ->addQuickfilter(_('Adressbuch'), User::findCurrent()->contacts->pluck('user_id'))
                                    ->setNavigationItem('/course/members')
                                    ->render();

            $element = LinkElement::fromHTML($mp, Icon::create('add'));
            $actions->addElement($element);
        }

        if ($this->rechte || $course->getSemClass()['studygroup_mode']) {
            $actions->addLink(
                _('Nachricht an alle Gruppenmitglieder verschicken'),
                $this->messageURL(),
                Icon::create('mail')
            )->asDialog();
        }
        if ($actions->hasElements()) {
            Sidebar::get()->addWidget($actions);
        }

        $views = new ViewsWidget();
        $views->addLink(
            _('Galerie'),
            $this->membersURL(['view' => 'gallery'])
        )->setActive($this->view === 'gallery');
        $views->addLink(
            _('Liste'),
            $this->membersURL(['view' => 'list'])
        )->setActive($this->view === 'list');
        Sidebar::get()->addWidget($views);
    }

    /**
     * offers specific member functions wrt perms
     *
     * @param string $action that has to be performed
     * @param string $from_status if applicable (e.g. tutor)
     */
    public function edit_members_action($action, $from_status = '')
    {
        global $perm;

        $id = Context::getId();

        if (!$id) {
            throw new CheckObjectException(_('Sie haben kein Objekt gewählt.'));
        }

        $user = Request::username('user');

        if ($from_status === 'moderator') {
            $from_status = 'dozent';
        }

        if (!$perm->have_studip_perm('tutor', $id)) {
            $this->redirect(URLHelper::getURL('dispatch.php/course/go', ['to' => $id]));
            return;
        }

        if (!$action) {
            PageLayout::postError(_('Es wurde keine korrekte Option gewählt.'));
        } elseif ($action === 'accept') {
            StudygroupModel::accept_user($user, $id);
            PageLayout::postSuccess(sprintf(
                _('%s wurde akzeptiert.'),
                htmlReady(get_fullname_from_uname($user, 'full'))
            ));
        } elseif ($action === 'deny') {
            StudygroupModel::deny_user($user, $id);
            PageLayout::postInfo(sprintf(
                _('%s wurde nicht akzeptiert.'),
                htmLReady(get_fullname_from_uname($user, 'full'))
            ));
        } elseif ($action === 'cancelInvitation') {
            StudygroupModel::cancelInvitation($user, $id);
            PageLayout::postSuccess(sprintf(
                _('Die Einladung von %s wurde gelöscht.'),
                htmlReady(get_fullname_from_uname($user, 'full'))
            ));
        } elseif ($action === 'bulk') {
            $users = Request::usernameArray('members');

            if (Request::submitted('accept')) {
                foreach ($users as $u) {
                    StudygroupModel::accept_user($u, $id);
                }
                PageLayout::postSuccess(sprintf(
                    _('%u Personen wurden akzeptiert.'),
                    count($users)
                ));
            } elseif (Request::submitted('deny')) {
                foreach ($users as $u) {
                    StudygroupModel::deny_user($u, $id);
                }
                PageLayout::postInfo(sprintf(
                    _('%u Personen wurden nicht akzeptiert.'),
                    count($users)
                ));
            } elseif (Request::submitted('cancel-invitations')) {
                foreach ($users as $u) {
                    StudygroupModel::cancelInvitation($u, $id);
                }
                PageLayout::postSuccess(sprintf(
                    _('Die Einladungen von %u Personen wurden gelöscht.'),
                    count($users)
                ));
            } elseif (Request::submitted('mail')) {
                $_SESSION['sms_data']['p_rec'] = $users;
                $this->redirect(URLHelper::getURL('dispatch.php/messages/write'));
                return;
            } elseif (Request::submitted('promote') && $GLOBALS['perm']->have_studip_perm('dozent', $id)) {
                $users = array_filter($users, function ($u) {
                    return $u !== $GLOBALS['user']->username;
                });

                $changed = 0;
                foreach ($users as $u) {
                    if ($from_status === $perm->get_studip_perm($id, get_userid($u))) {
                        $status = $GLOBALS['perm']->have_studip_perm('tutor', $id, get_userid($u)) ? 'dozent' : 'tutor';
                        StudygroupModel::promote_user($u, $id, $status);
                        $changed += 1;
                    }
                }
                if ($changed > 0) {
                    PageLayout::postSuccess(sprintf(
                        _('Der Status von %u Personen wurde geändert.'),
                        $changed
                    ));
                }
            } elseif (Request::submitted('downgrade') && $GLOBALS['perm']->have_studip_perm('dozent', $id)) {
                $users = array_filter($users, function ($u) {
                    return $u !== $GLOBALS['user']->username;
                });

                $changed = 0;
                foreach ($users as $u) {
                    if ($from_status === $perm->get_studip_perm($id, get_userid($u))) {
                        $status = $GLOBALS['perm']->have_studip_perm('dozent', $id, get_userid($u)) ? 'tutor' : 'autor';
                        StudygroupModel::promote_user($u, $id, $status);
                        $changed += 1;
                    }
                }
                if ($changed > 0) {
                    PageLayout::postSuccess(sprintf(
                        _('Der Status von %u Personen wurde geändert.'),
                        $changed
                    ));
                }
            } elseif (Request::submitted('remove')) {
                $users = array_filter($users, function ($u) {
                    return $u !== $GLOBALS['user']->username;
                });

                foreach ($users as $u) {
                    StudygroupModel::remove_user($u, $id);
                }
                PageLayout::postSuccess(sprintf(
                    _('%u Personen wurden aus der Studiengruppe entfernt.'),
                    count($users)
                ));
            }
        } elseif (!$perm->have_studip_perm('dozent', $id, get_userid($user)) || count(Course::find($id)->getMembersWithStatus('dozent')) > 1) {
            if ($action === 'promote' && $perm->have_studip_perm('dozent', $id)) {
                if ($from_status === $perm->get_studip_perm($id, get_userid($user))) {
                    $status = $perm->have_studip_perm('tutor', $id, get_userid($user)) ? "dozent" : "tutor";
                    StudygroupModel::promote_user($user, $id, $status);
                    PageLayout::postSuccess(sprintf(
                        _('Der Status von %s wurde geändert.'),
                        htmlReady(get_fullname_from_uname($user, 'full'))
                    ));
                }
            } elseif ($action === "downgrade" && $perm->have_studip_perm('dozent', $id)) {
                if ($from_status === $perm->get_studip_perm($id, get_userid($user))) {
                    $status = $perm->have_studip_perm('dozent', $id, get_userid($user)) ? "tutor" : "autor";
                    StudygroupModel::promote_user($user, $id, $status);
                    PageLayout::postSuccess(sprintf(
                        _('Der Status von %s wurde geändert.'),
                        htmlReady(get_fullname_from_uname($user, 'full'))
                    ));
                }
            } elseif ($action === 'remove') {
                PageLayout::postQuestion(
                    sprintf(
                        _('Möchten Sie %s wirklich aus der Studiengruppe entfernen?'),
                        htmlReady(get_fullname_from_uname($user, 'full'))
                    ),
                    $this->edit_membersURL('remove_approved', compact('user', 'id'))
                )->includeTicket();
            } elseif ($action === 'remove_approved' && check_ticket(Request::get('studip_ticket'))) {
                StudygroupModel::remove_user($user, $id);
                PageLayout::postSuccess(sprintf(
                    _('%s wurde aus der Studiengruppe entfernt.'),
                    htmlReady(get_fullname_from_uname($user, 'full'))
                ));
            }
        } else {
            PageLayout::postError(_('Jede Studiengruppe muss mindestens einen Gruppengründer haben!'));
        }

        //Für die QuickSearch-Suche:
        if (Request::get('choose_member_parameter') && Request::get('choose_member_parameter') !== _('Nutzer suchen')) {
            $this->flash['choose_member_parameter'] = Request::get('choose_member_parameter');
        }
        $this->redirect($this->url_for('course/studygroup/members', ['view' => $this->view]));
    }

    /**
     * invites members to a studygroup.
     */
    public function execute_invite_action()
    {
        // Security Check
        global $perm;

        $id = Context::getId();

        if (!$perm->have_studip_perm('tutor', $id)) {
            $this->redirect(URLHelper::getURL('dispatch.php/course/go', ['to' => $id]));
            exit;
        }

        // load MultiPersonSearch object
        $mp         = MultiPersonSearch::load("studygroup_invite_{$id}");
        $fail       = false;
        $count      = 0;
        $addedUsers = "";
        foreach ($mp->getAddedUsers() as $receiver) {
            // save invite in database
            StudygroupModel::inviteMember($receiver, $id);
            // send invite message to user
            $msg        = new messaging();
            $sem = Course::find($id);
            $message = sprintf(_("%s möchte Sie auf die Studiengruppe %s aufmerksam machen. Klicken Sie auf den folgenden Link, um direkt zur Studiengruppe zu gelangen.\n\n %s"),
                get_fullname(), $sem->name, URLHelper::getlink("dispatch.php/course/studygroup/details/" . $id, ['cid' => null]));
            $subject = _("Sie wurden in eine Studiengruppe eingeladen");
            $msg->insert_message($message, get_username($receiver), '', '', '', '', '', $subject);

            if ($count > 0) {
                $addedUsers .= ", ";
            }
            $addedUsers .= get_fullname($receiver, 'full', true);
            $count++;

        }
        if ($count == 1) {
            PageLayout::postSuccess(sprintf(
                _('%s wurde in die Studiengruppe eingeladen.'),
                $addedUsers
            ));
        } else if ($count >= 1) {
            PageLayout::postSuccess(sprintf(
                _('%s wurden in die Studiengruppe eingeladen.'),
                $addedUsers
            ));
        }

        $this->redirect($this->url_for('course/studygroup/members/', ['view' => $this->view]));
    }

    /**
     * deletes a studygroup
     */
    public function delete_action(): void
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$GLOBALS['perm']->have_studip_perm('dozent', Context::getId())) {
            throw new AccessDeniedException();
        }

        $course = Context::get();

        if (!($course instanceof Course) || !$course->isStudygroup()) {
            throw new InvalidArgumentException(_('Studiengruppe nicht vorhanden oder Veranstaltung ist keine Studiengruppe'));
        }

        if (!Context::get()->delete()) {
            PageLayout::postError(_('Die Studiengruppe konnte nicht gelöscht werden.'));
        } else {
            PageLayout::postSuccess(_('Die Studiengruppe wurde gelöscht.'));
        }

        $this->redirect(URLHelper::getURL('dispatch.php/studygroup/browse', [], true));
    }

    /**
     * Displays admin settings concerning the studygroups
     *
     * @return void
     */
    public function globalmodules_action()
    {
        global $perm;
        $perm->check("root");
        PageLayout::setHelpKeyword('Admin.Studiengruppen');

        // get institutes
        $institutes   = StudygroupModel::getInstitutes();
        $default_inst = $this->flash['institute'] ?? Config::Get()->STUDYGROUP_DEFAULT_INST;

        // Nutzungsbedingungen
        $terms = $this->flash['terms'] ?? Config::Get()->STUDYGROUP_TERMS;

        PageLayout::setTitle(_('Verwaltung studentischer Arbeitsgruppen'));
        Navigation::activateItem('/admin/config/studygroup');

        $query     = "SELECT COUNT(*) FROM seminare WHERE status IN (?)";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([studygroup_sem_types()]);

        // set variables for view
        $this->can_deactivate = $statement->fetchColumn() == 0;
        $this->current_page   = _("Verwaltung erlaubter Inhaltselemente und Plugins für Studiengruppen");
        $this->configured     = count(studygroup_sem_types()) > 0;
        $this->institutes     = $institutes;
        $this->default_inst   = $default_inst;
        $this->terms          = $terms;
    }

    /**
     * sets the global module and plugin settings for studygroups
     *
     * @return void
     */
    public function savemodules_action()

    {
        $GLOBALS['perm']->check('root');
        PageLayout::setHelpKeyword('Admin.Studiengruppen');
        $errors = [];
        if (!Request::get('institute')) {
            $errors[] = _('Bitte wählen Sie eine Einrichtung aus, der die Studiengruppen zugeordnet werden sollen!');
        }

        if (!trim(Request::get('terms'))) {
            $errors[] = _('Bitte tragen Sie Nutzungsbedingungen ein!');
        }

        if ($errors) {
            $this->flash['messages']  = ['error' => ['title' => 'Die Studiengruppen konnten nicht aktiviert werden!', 'details' => $errors]];
            $this->flash['institute'] = Request::get('institute');
            $this->flash['terms']     = Request::i18n('terms');
        }

        if (!$errors) {
            $cfg = Config::get();
            if ($cfg->STUDYGROUPS_ENABLE == false && count(studygroup_sem_types()) > 0) {
                $cfg->store("STUDYGROUPS_ENABLE", true);
                PageLayout::postSuccess(_('Die Studiengruppen wurden aktiviert.'));
            }

            if (Request::get('institute')) {
                $cfg->store('STUDYGROUP_DEFAULT_INST', Request::get('institute'));
                $cfg->store('STUDYGROUP_TERMS', Request::i18n('terms'));
                PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));
            } else {
                PageLayout::postError(_('Fehler beim Speichern der Einstellung!'));
            }
        }
        $this->redirect('course/studygroup/globalmodules');
    }
    /**
     * globally deactivates the studygroups
     *
     * @return void
     */
    public function deactivate_action()
    {
        global $perm;
        $perm->check("root");
        PageLayout::setHelpKeyword('Admin.Studiengruppen');

        $query     = "SELECT COUNT(*) FROM seminare WHERE status IN (?)";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([studygroup_sem_types()]);

        if (($count = $statement->fetchColumn()) != 0) {
            PageLayout::postError(sprintf(
                _('Sie können die Studiengruppen nicht deaktivieren, da noch %s Studiengruppen vorhanden sind!'),
                $count
            ));
        } else {
            Config::get()->store("STUDYGROUPS_ENABLE", false);
            PageLayout::postSuccess(_('Die Studiengruppen wurden deaktiviert.'));
        }
        $this->redirect('course/studygroup/globalmodules');
    }

    /**
     * sends a message to all members of a studygroup
     *
     * @param string id of a studygroup
     *
     * @return void
     */

    public function message_action()
    {
        $id = Context::getId();
        $sem = Context::get();

        if (mb_strlen($sem->name) > 32) {//cut subject if to long
            $subject = sprintf(_("[Studiengruppe: %s...]"), mb_substr($sem->name, 0, 30));
        } else {
            $subject = sprintf(_("[Studiengruppe: %s]"), $sem->name);
        }

        $this->redirect($this->url_for('messages/write', ['course_id' => $id, 'default_subject' => $subject, 'filter' => 'all', 'emailrequest' => 1]));
    }
    public function avatar_action()
    {
        Navigation::activateItem('/course/admin/avatar');
        $this->studygroup_id = Context::getId();
        $avatar = StudygroupAvatar::getAvatar($this->studygroup_id);
        $this->avatar_url = $avatar->getURL(Avatar::NORMAL);
    }

    public function widget_action($range_id)
    {
        if (get_class($this->parent_controller) === __CLASS__) {
            throw new RuntimeException('widget_action must be relayed');
        }
        $this->course = Course::find($range_id);

        if ($this->course->isStudygroup()) {
            $sql = "INNER JOIN `seminare` ON (`seminare`.`Seminar_id` = `studygroup_courses`.`course_id`) WHERE `studygroup_id` = ? ORDER BY `seminare`.`name` ASC";
        } else {
            $sql = "INNER JOIN `seminare` ON (`seminare`.`Seminar_id` = `studygroup_courses`.`studygroup_id`) WHERE `course_id` = ? ORDER BY `seminare`.`name` ASC ";
        }
        $this->connections = StudygroupCourse::findBySQL($sql, [$range_id]);
    }


}
