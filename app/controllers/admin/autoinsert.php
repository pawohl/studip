<?php
/**
 * autu_insert.php - controller class for the auto insert seminars
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Nico Müller <nico.mueller@uni-oldenburg.de>
 * @author      Michael Riehemann <michael.riehemann@uni-oldenburg.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     admin
 * @since       2.1
 */
class Admin_AutoinsertController extends AuthenticatedController
{
    /**
     * Common tasks for all actions.
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        // user must have root permission
        $GLOBALS['perm']->check('root');
        Navigation::activateItem('/admin/user/auto_insert');
        PageLayout::setTitle(_('Automatisiertes Eintragen verwalten'));
        PageLayout::setHelpKeyword('Admins.AutomatisiertesEintragen');
    }

    /**
     * Maintenance view for the auto insert parameters
     *
     */
    public function index_action()
    {
        $this->sem_search = '';
        $this->sem_select = '';
        $this->seminar_search = [];

        // search seminars
        if (Request::submitted('suchen')) {

            if (Request::get('sem_search')) {
                $this->sem_search = Request::get('sem_search');
                $this->sem_select = Request::option('sem_select') ?: null;
                $search = new SeminarSearch();
                $this->seminar_search = $search->getResults(
                    $this->sem_search,
                    [
                        'search_sem_sem' => $this->sem_select,
                        'search_sem_qs_choose' => 'title_number',
                    ]
                );
                if (count($this->seminar_search) == 0) {
                    PageLayout::postInfo(_('Es wurden keine Veranstaltungen gefunden.'));
                }
            } else {
                PageLayout::postError(_('Bitte geben Sie einen Suchparameter ein.'));
            }
        }
        $seminare = AutoInsert::getAllSeminars();
        $this->auto_sems = $seminare;

        $domains = [];
        $domains[] = [
            'id'   => 'keine',
            'name' => _('Ohne Domäne'),
        ];
        foreach (UserDomain::getUserDomains() as $domain) {
            $domains[] = $domain;
        }

        $this->userdomains = $domains;

        $links = new ActionsWidget();
        $links->addLink(
            _('Benutzergruppen manuell eintragen'),
            $this->manualURL(),
            Icon::create('visibility-visible')
        );
        Sidebar::Get()->addWidget($links);
    }

    /**
     * Create a new seminar for auto insert
     */
    public function new_action()
    {
        if (Request::submitted('anlegen')) {
            $sem_id = Request::option('sem_id');
            $domains = Request::getArray('rechte');
            if (empty($domains)) {
                PageLayout::postError(_('Mindestens ein Status sollte selektiert werden!'));
            } else {
                foreach ($domains as $id => $rechte) {
                    if ($id === 'keine')
                        $id = '';
                    if (!AutoInsert::checkSeminar($sem_id, $id)) {
                        AutoInsert::saveSeminar($sem_id, $rechte, $id);
                        PageLayout::postSuccess(_('Die Zuordnung wurde erfolgreich gespeichert!'));
                    } else {
                        PageLayout::postError(_('Das Seminar wird bereits zu diesem Zweck verwendet!'));
                    }
                }
            }
        }
        $this->redirect('admin/autoinsert');
    }

    /**
     * Edit a rule
     *
     * @param string $seminar_id
     */
    public function edit_action($seminar_id)
    {
        $domain = Request::get('domain_id');
        $status = Request::get('status');
        $remove = Request::get('remove');
        if ($domain === 'keine')
            $domain = '';
        AutoInsert::updateSeminar($seminar_id, $domain, $status, $remove);
        PageLayout::postSuccess(_('Die Statusgruppenanpassung wurde erfolgreich übernommen!'));
        $this->redirect('admin/autoinsert');
    }

    /**
     * Removes a seminar from the auto-insert list, with modal dialog
     *
     * @param string $seminar_id
     */
    public function delete_action($seminar_id)
    {
        if (Request::int('delete') === 1) {
            if (AutoInsert::deleteSeminar($seminar_id)) {
                PageLayout::postSuccess(_('Die Zuordnung der Veranstaltung wurde gelöscht!'));
            }
        } elseif (!Request::get('back')) {
            $this->flash['delete'] = $seminar_id;
        }
        $this->redirect('admin/autoinsert');
    }

    /**
     * Maintenance view for the manual insert parameters
     *
     */
    public function manual_action()
    {
        $this->seminar_search = [];

        PageLayout::setTitle(_('Manuelles Eintragen von Nutzergruppen in Veranstaltungen'));
        if (Request::submittedSome('submit', 'force')) {
            $filters = array_filter(Request::getArray('filter'));
            $force = Request::bool('force', false);
            $seminar_id = Request::option('sem_id');

            if (!$seminar_id || $seminar_id === 'false') {
                PageLayout::postError(_('Ungültiger Aufruf'));
            } elseif (!count($filters)) {
                PageLayout::postError(_('Keine Filterkriterien gewählt'));
            } else {
                $course = Course::find($seminar_id);

                $userlookup = new UserLookup();
                foreach ($filters as $type => $values) {
                    $userlookup->setFilter($type, $values);
                }
                $user_ids = $userlookup->execute();
                $real_users = 0;

                foreach ($user_ids as $user_id) {
                    if ($force || !AutoInsert::checkAutoInsertUser($seminar_id, $user_id)) {
                        $user = User::find($user_id);
                        if ($user) {
                            $real_users += $course->addMember($user, regard_contingent: false) ? 1 : 0;
                            AutoInsert::saveAutoInsertUser($seminar_id, $user_id);
                        }
                    }
                }

                //messagebox
                $text = sprintf(
                    _('Es wurden %u von %u möglichen Personen in die Veranstaltung %s eingetragen.'),
                    $real_users,
                    count($user_ids),
                    sprintf(
                        '<a href="%s">%s</a>',
                        URLHelper::getLink('dispatch.php/course/details/', ['cid' => $course->id]),
                        htmlReady($course->name)
                    )
                );
                $details = [_('Etwaige Abweichungen der Personenzahlen enstehen durch bereits vorhandene bzw. wieder ausgetragene Personen.')];
                if ($real_users > 0) {
                    PageLayout::postSuccess($text, $details);
                } else {
                    PageLayout::postInfo($text, $details);
                }

                $this->redirect('admin/autoinsert/manual');
                return;
            }
        }

        $this->sem_id = Request::option('sem_id');
        $this->sem_search = trim(Request::get('sem_search'));
        $this->sem_select = Request::option('sem_select')?: null;
        $this->filtertype = Request::getArray('filtertype');
        $this->filter = Request::getArray('filter');

        $doSearch = Request::submitted('suchen');

        if (count(Request::getArray('remove_filter')) > 0) {
            $this->filtertype = array_diff($this->filtertype, array_keys(Request::getArray('remove_filter')));
            $doSearch = true;
        } elseif (Request::submitted('add_filter')) {
            $this->filtertype[] = Request::get('add_filtertype');
            $doSearch = true;
        }

        if ($doSearch) {
            if ($this->sem_search) {
                $search = new SeminarSearch();
                $this->seminar_search = $search->getResults(
                    $this->sem_search,
                    [
                        'search_sem_sem' => $this->sem_select,
                        'search_sem_qs_choose' => 'title_number',
                    ]
                );
                if (count($this->seminar_search) == 0) {
                    PageLayout::postInfo(_('Es wurden keine Veranstaltungen gefunden.'));
                }
            } else {
                PageLayout::postError(_('Im Suchfeld wurde nichts eingetragen!'));
            }
        }

        $this->values = [];
        foreach ($this->filtertype as $type) {
            $this->values[$type] = UserLookup::getValuesForType($type);
        }

        $this->available_filtertypes = [
            'fach'         => _('Studienfach'),
            'abschluss'    => _('Studienabschluss'),
            'fachsemester' => _('Studienfachsemester'),
            'institut'     => _('Einrichtung'),
            'status'       => _('Statusgruppe'),
            'domain'       => _('Domäne'),
            'role'         => _('Rolle'),
        ];

        $links = new ActionsWidget();
        $links->addLink(_('Übersicht'), $this->indexURL(), Icon::create('edit'));
        Sidebar::Get()->addWidget($links);

    }

    /**
     * Count how many user a insert
     */
    public function manual_count_action()
    {
        $filters = array_filter(Request::getArray('filter'));
        if (empty($filters)) {
            $data = ['error' => _('Keine Filterkriterien gewählt')];
        } else {
            $userlookup = new UserLookup();
            foreach ($filters as $type => $values) {
                $userlookup->setFilter($type, $values);
            }
            $data = ['users' => count($userlookup->execute())];
        }
        $this->render_json($data);
    }
}
