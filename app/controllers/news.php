<?php
# Lifter007: TODO
# Lifter003: TODO
# Lifter010: TODO

/*
 * news.php - News controller
 *
 * Copyright (C) 2007 - Marcus Lunzenauer <mlunzena@uos.de>, Rasmus Fuhse <fuhse@data-quest.de>, Arne Schröder <schroeder@data-quest.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Rasmus Fuhse <fuhse@data-quest.de>
 * @author      Marcus Lunzenauer <mlunzena@uos.de>
 * @author      Arne Schröder <schroeder@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     news
 */

require_once 'lib/showNews.inc.php';
require_once 'lib/user_visible.inc.php';

class NewsController extends StudipController
{
    protected $with_session = true;

    /**
     * Callback function being called before an action is executed.
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->area_structure = [
            'global' => [
                'title' => _('Stud.IP (systemweit)'),
                'icon'  => 'home',
            ],
            'login' => [
                'title' => _('Stud.IP (login)'),
                'icon' => 'door-enter',
            ],
            'inst' => [
                'title' => _('Einrichtungen'),
                'icon'  => 'institute',
            ],
            'sem' => [
                'title' => _('Veranstaltungen'),
                'icon'  => 'seminar',
            ],
            'user' => [
                'title' => _('Profil'),
                'icon'  => 'person',
            ],
        ];

        $this->priorities = [
            0 => '0 (' . _('normal') . ')',
            1 => '1 (' . _('sehr niedrig') . ')',
            2 => '2',
            3 => '3',
            4 => '4',
            5 => '5',
            6 => '6',
            7 => '7',
            8 => '8',
            9 => '9',
           10 => '10 (' . _('sehr hoch') . ')',
       ];

       $this->roles = NewsRoles::getAvailableRoles();
       $this->rolesStats = RolePersistence::getStatistics();
    }

    /**
     * Widget controller to produce the formally known show_votes()
     *
     * @param String $range_id range id of the news to get displayed
     */
    public function display_action($range_id)
    {
        if (!$range_id) {
            $this->set_status(400);
            $this->render_nothing();
            return;
        }

        if (!StudipNews::haveRangePermission('view', $range_id, $GLOBALS['user']->id)) {
            $this->set_status(401);
            $this->render_nothing();
            return;
        }

        // Store visit if user opened comments
        if (Request::bool('comments') && Request::submitted('contentbox_open')) {
            object_set_visit(Request::option('contentbox_open'), 'news');
        }

        // Check if user wrote a comment
        if (Request::submitted('accept') && trim(Request::get('comment_content'))) {
            CSRFProtection::verifyUnsafeRequest();

            $news_id = Request::get('comsubmit');
            $comment = StudipComment::create([
                'object_id' => $news_id,
                'user_id' => $GLOBALS['user']->id,
                'content' => trim(Request::get('comment_content'))
            ]);

            $url = URLHelper::getURL(Request::url() . "#newscomment-{$comment->id}", [
                'contentbox_open' => $news_id,
                'comments'        => true,
            ]);

            $this->redirect($url);
            return;
        }

        // Check if user wants to remove a announcement
        if ($news_id = Request::get('remove_news')) {
            $news = new StudipNews($news_id);
            $range = Request::get('news_range');
            if ($news->havePermission('unassign', $range)) {
                if (Request::get('confirm')) {
                    CSRFProtection::verifyUnsafeRequest();
                    $news->deleteRange($range);
                    $news->store();
                } else {
                    PageLayout::postQuestion(
                        _('Ankündigung wirklich aus diesem Bereich entfernen?'),
                        URLHelper::getURL('', ['remove_news' => $news_id, 'news_range' => $range, 'confirm' => true])
                    );
                }
            }
        }

        // Check if user wants to delete an announcement
        if ($news_id = Request::get('delete_news')) {
            $news = new StudipNews($news_id);
            if ($news->havePermission('delete')) {
                if (Request::get('confirm')) {
                    CSRFProtection::verifyUnsafeRequest();
                    $news->delete();
                } else {
                    PageLayout::postQuestion(
                        _('Ankündigung wirklich löschen?'),
                        URLHelper::getURL('', ['delete_news' => $news_id, 'confirm' => true])
                    );
                }
            }
        }

        $this->perm = StudipNews::haveRangePermission('edit', $range_id);
        $this->show_all_news = Request::get('nshow_all') && $this->perm;
        if ($this->show_all_news) {
            URLHelper::addLinkParam('nshow_all', 1);
        }
        $this->news           = StudipNews::GetNewsByRange($range_id, !$this->show_all_news, true);
        $this->count_all_news = $this->show_all_news ? count($this->news) : count(StudipNews::GetNewsByRange($range_id));
        $this->rss_id         = Config::get()->NEWS_RSS_EXPORT_ENABLE ? StudipNews::GetRssIdFromRangeId($range_id) : false;
        $this->range          = $range_id;
        $this->nobody         = !$GLOBALS['user']->id || $GLOBALS['user']->id === 'nobody';

        $this->visit();
    }

    private function visit()
    {
        if ($GLOBALS['user']->id && $GLOBALS['user']->id !== 'nobody' && Request::option('contentbox_open') && Request::option('contentbox_type') === 'news') {
            object_add_view(Request::option('contentbox_open'));
            object_set_visit(Request::option('contentbox_open'), 'news'); //and, set a visittime
        }
    }

    public function visit_action()
    {
        $this->visit();
        $this->render_nothing();
    }

    public function visit_all_action()
    {
        $global_news = StudipNews::GetNewsByRange('studip', true);

        if ($GLOBALS['user']->id && $GLOBALS['user']->id !== 'nobody') {
            foreach ($global_news as $news) {
                object_add_view($news['news_id']);
                object_set_visit($news['news_id'], 'news');
            }
        }

        $this->render_json(true);
    }

    /**
     * Builds news dialog for editing / adding news
     *
     * @param string $id news           id (in case news already exists; otherwise set to "new")
     * @param string $context_range     range id (only for new news; set to 'template' for copied news)
     * @param string $template_id       template id (source of news template)
     *
     */
    public function edit_news_action($id = '', $context_range = '', $template_id = '')
    {
        // initialize
        $ranges = [];

        $this->ranges                  = [];
        $this->area_options_selectable = [];
        $this->area_options_selected   = [];
        $this->may_delete              = false;
        $this->route                   = "news/edit_news/{$id}";

        $this->news_isvisible = [
            'news_basic' => true,
            'news_comments' => false,
            'news_areas' => false,
            'news_visibility' => false,
        ];

        if ($context_range) {
            $this->route .= "/{$context_range}";
            if ($template_id)
                $this->route .= "/{$template_id}";
        }

        if ($id === 'new') {
            $id = null;
            PageLayout::setTitle(_('Ankündigung erstellen'));
        } else
            PageLayout::setTitle(_('Ankündigung bearbeiten'));

        // user has to have autor permission at least
        if (!$GLOBALS['perm']->have_perm('autor')) {
            $this->set_status(401);
            $this->render_nothing();
            return;
        }

        // load news and comment data and check if user has permission to edit
        $news = new StudipNews($id);

        if (!$news->havePermission('edit') && !$news->isNew()) {
            throw new AccessDeniedException();
        }

        if (!$news->isNew()) {
            $this->comments = StudipComment::GetCommentsForObject($id);

            $this->assigned = NewsRoles::getRoles($id);
            if ($this->assigned){
                $this->news_isvisible['news_visibility'] = true;
            }
            $this->roles = NewsRoles::getAvailableRoles($id);
        }

        // if form sent, get news data by post vars
        if ($id) {
            // if news id given check for valid id and load ranges
            if ($news->isNew()) {
                PageLayout::postError(_('Die Ankündigung existiert nicht!'));
                $this->render_nothing();
                return;
            }
        } elseif ($template_id) {
            // otherwise, load data from template
            $news_template = new StudipNews($template_id);
            if ($news_template->isNew()) {
                PageLayout::postError(_('Die Ankündigung existiert nicht!'));
                $this->render_nothing();
                return;
            }
            // check for permission
            if (!$news_template->havePermission('edit')) {
                $this->set_status(401);
                $this->render_nothing();
                return;
            }
            $ranges = $news_template->news_ranges->toArray();
            $changed_areas = 0;
            // remove those ranges for which user doesn't have permission
            foreach ($ranges as $key => $news_range)
                if (!$news->haveRangePermission('edit', $news_range['range_id'])) {
                    $changed_areas++;
                    $this->news_isvisible['news_areas'] = true;
                    unset($ranges[$key]);
                }
            if ($changed_areas == 1) {
                PageLayout::postInfo(_('1 zugeordneter Bereich wurde nicht übernommen, weil Sie dort keine Ankündigungen erstellen dürfen.'));
            } elseif ($changed_areas) {
                PageLayout::postInfo(sprintf(_('%s zugeordnete Bereiche wurden nicht übernommen, weil Sie dort keine Ankündigungen erstellen dürfen.'), $changed_areas));
            }
            $news->topic          = $news_template->topic;
            $news->body           = $news_template->body;
            $news->date           = $news_template->date;
            $news->expire         = $news_template->expire;
            $news->allow_comments = $news_template->allow_comments;
            $news->user_id        = User::findCurrent()->id;
        } else {
            // for new news, set startdate to today and range to dialog context
            $news->date   = strtotime('today');
            $news->expire = strtotime('23:59 +1 week') - (int)$news->date;
            if ($context_range && $context_range !== 'template') {
                $add_range = new NewsRange(['', $context_range]);
                $ranges[] = $add_range->toArray();
            }
        }


        foreach ($ranges as $range_array) {
            $range = new NewsRange();
            $range['range_id'] = $range_array['range_id'];
            $news['news_ranges'][] = $range;
        }

        $url_params = [];
        if (Request::submitted('username')) {
            $url_params['username'] = Request::username('username');
        }

        $this->form = \Studip\Forms\Form::fromSORM(
            $news,
            [
                'legend' => _('Grunddaten'),
                'fields' => [
                    'topic' => [
                        'label' => _('Titel'),
                        'required' => true
                    ],
                    'body' => [
                        'label' => _('Ankündigungstext'),
                        'required' => true,
                        'type' => 'i18n_formatted'
                    ],
                    'hgroup1' => new \Studip\Forms\InputRow(
                        [
                            'name' => 'date',
                            'label' => _('Beginn'),
                            'type' => 'datetimepicker',
                            'required' => true
                        ],
                        [
                            'name' => 'expire',
                            'label' => _('Ende'),
                            'type' => 'datetimepicker',
                            'value' => (int)$news['date'] + (int)$news['expire'],
                            'mindate' => 'date',
                            'mapper' => function ($value, $obj) { //hier müssen wir vom UnixTimestamp noch den Beginn abziehen:
                                return (int)$value - (int)$obj['date'];
                            },
                            'required' => true
                        ],
                        [
                            'name' => 'days',
                            'label' => _('Laufzeit in Tagen'),
                            'type' => 'calculator',
                            'value' => "Math.floor((expire - date) / 86400)"
                        ]
                    ),
                    'allow_comments' => [
                        'label' => _('Kommentare zulassen'),
                        'type' => 'checkbox'
                    ],
                    'user_id' => [
                        'type' => 'hidden',
                        'mapper' => function () use ($news) {
                            return $news->user_id ?: User::findCurrent()->id;
                        }
                    ],
                    'chdate_uid' => [
                        'type' => 'no',
                        'mapper' => function () {
                            return User::findCurrent()->id;
                        }
                    ],
                    'author' => [
                        'type' => 'no',
                        'mapper' => function () use ($news) {
                            $author = $news->user_id ? User::find($news->user_id): User::findCurrent();
                            return $author ? $author->getFullName() : '';
                        }
                    ]
                ]
            ],
            URLHelper::getURL('?', $url_params)
        )->addSORM(
            $news,
            [
                'legend' => _('In weiteren Bereichen anzeigen'),
                'fields' => [
                    'news_ranges' => [
                        'label' => _('In weiteren Bereichen anzeigen'),
                        'type' => 'NewsRanges',
                        'required' => true
                    ]
                ]
            ]
        )->addSORM(
            $news,
            [
                'legend' => _('Sichtbarkeitseinstellungen'),
                'fields' => [
                    'prio' => [
                        'label' => _('Priorität (1: niedrigste, 10: höchste)'),
                        'type' => 'range'
                    ],
                    'newsroles' => [
                        'permission' => $GLOBALS['perm']->have_perm('admin'),
                        'label' => _('Sichtbarkeit'),
                        'value' => $news->news_roles->map(function (NewsRoles $n) {
                                       return (string) $n->roleid;
                                   }), //vue-select needs these as strings
                        'type' => 'multiselect',
                        'options' => array_map(function ($r) { return $r->getRolename(); }, RolePersistence::getAllRoles()),
                        'store' => function ($value, $input) {
                            $news = $input->getContextObject();
                            NewsRoles::update($news->id, $value);
                        }
                    ]
                ]
            ]
        )->setCollapsable()
            ->autoStore();

        // check if user has full permission on news object
        if ($news->havePermission('delete')) {
            $this->may_delete = true;
        }
    }

    /**
     * Show administration page for user's news
     *
     * @param string $area_type         area filter
     */
    public function admin_news_action($area_type = '')
    {
        // check permission
        $GLOBALS['perm']->check('user');

        // initialize
        $news_result = [];
        $limit = 100;
        if (Request::get('news_filter') === 'set') {
            $this->news_searchterm = Request::option('news_filter_term');
            $this->news_startdate  = Request::int('news_filter_start');
            $this->news_enddate    = Request::int('news_filter_end');
        } else {
            $this->news_startdate = time();
        }
        if (isset($this->area_structure[$area_type]) && is_array($this->area_structure[$area_type])) {
            $this->area_type = $area_type;
        }
        PageLayout::setTitle(_('Meine Ankündigungen'));
        PageLayout::setHelpKeyword('Basis.News');
        Navigation::activateItem('/contents/news');

        if (Request::submitted('reset_filter')) {
            $area_type = 'all';
            $this->news_searchterm = '';
            $this->news_startdate  = time();
            $this->news_enddate    = time();
        }

        // delete news
        if (Request::submitted('remove_marked_news')) {
            $remove_ranges = [];
            foreach (Request::optionArray('mark_news') as $mark_id) {
                list($news_id, $range_id) = explode('_', $mark_id);
                $remove_ranges[$news_id][] = $range_id;
            }
            $this->flash['question_text'] = remove_news($remove_ranges);
            $this->flash['question_param'] = [
                'mark_news' => Request::optionArray('mark_news'),
                'remove_marked_news' => 1
            ];
        }
        // apply filter
        if (Request::submitted('apply_news_filter')) {
            $this->news_isvisible['basic'] = empty($this->news_isvisible['basic']);
            if (Request::get('news_searchterm') && mb_strlen(trim(Request::get('news_searchterm'))) < 3) {
                PageLayout::postError(_('Der Suchbegriff muss mindestens 3 Zeichen lang sein.'));
            } elseif ((Request::get('news_startdate') && !$this->getTimeStamp(Request::get('news_startdate'))) || (Request::get('news_enddate') && !$this->getTimeStamp(Request::get('news_enddate'), 'end'))) {
                PageLayout::postError(_('Ungültige Datumsangabe. Bitte geben Sie ein Datum im Format TT.MM.JJJJ ein.'));
            } elseif (Request::get('news_startdate') && Request::get('news_enddate') && ($this->getTimeStamp(Request::get('news_startdate')) > $this->getTimeStamp(Request::get('news_enddate'), 'end'))) {
                PageLayout::postError(_('Das Startdatum muss vor dem Enddatum liegen.'));
            }

            if (mb_strlen(trim(Request::get('news_searchterm'))) >= 3) {
                $this->news_searchterm = Request::get('news_searchterm');
            }

            $this->news_startdate = $this->getTimeStamp(Request::get('news_startdate'));
            $this->news_enddate   = $this->getTimeStamp(Request::get('news_enddate'), 'end') ?: time();
        }
        // fetch news list
        $this->news_items = StudipNews::GetNewsRangesByFilter($GLOBALS['user']->id, $this->area_type, $this->news_searchterm, $this->news_startdate, $this->news_enddate, true, $limit+1);
        // build area and filter description
        if ($this->news_searchterm && $this->area_type && $this->area_type !== 'all') {
            if ($this->news_startdate && $this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s" zum Suchbegriff "%s", die zwischen dem %s und dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm, date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            } elseif ($this->news_startdate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s" zum Suchbegriff "%s", die ab dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm, date('d.m.Y', $this->news_startdate));
            } elseif ($this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s" zum Suchbegriff "%s", die vor dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm, date('d.m.Y', $this->news_enddate));
            } else {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s" zum Suchbegriff "%s".'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm);
            }
        } elseif ($this->area_type && $this->area_type !== 'all') {
            if ($this->news_startdate && $this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s", die zwischen dem %s und dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            } elseif ($this->news_startdate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s", die ab dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], date('d.m.Y', $this->news_startdate));
            } elseif ($this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s", die vor dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], date('d.m.Y', $this->news_enddate));
            } else {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen im Bereich "%s".'), $this->area_structure[$this->area_type]['title']);
            }
        } elseif ($this->news_searchterm) {
            if ($this->news_startdate && $this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen zum Suchbegriff "%s", die zwischen dem %s und dem %s sichtbar sind.'), $this->news_searchterm, date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            } elseif ($this->news_startdate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen zum Suchbegriff "%s", die ab dem %s sichtbar sind.'), $this->news_searchterm, date('d.m.Y', $this->news_startdate));
            } elseif ($this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen zum Suchbegriff "%s", die vor dem %s sichtbar sind.'), $this->news_searchterm, date('d.m.Y', $this->news_enddate));
            } else {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen zum Suchbegriff "%s".'), $this->news_searchterm);
            }
        } elseif ($this->news_startdate || $this->news_enddate) {
            if ($this->news_startdate && $this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen, die zwischen dem %s und dem %s sichtbar sind.'), date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            } elseif ($this->news_startdate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen, die ab dem %s sichtbar sind.'), date('d.m.Y', $this->news_startdate));
            } elseif ($this->news_enddate) {
                $this->filter_text = sprintf(_('Angezeigt werden Ankündigungen, die vor dem %s sichtbar sind.'), date('d.m.Y', $this->news_enddate));
            }
        }

        $counter = 0;
        // check for delete-buttons and news limit
        foreach ($this->area_structure as $type => $area_data) {
            if (isset($this->news_items[$type]) && is_array($this->news_items[$type])) {
                foreach($this->news_items[$type] as $key => $news) {
                    // check if result set too big
                    $counter++;
                    if ($counter > $limit) {
                        PageLayout::postInfo(sprintf(_('Es werden nur die ersten %s Ankündigungen angezeigt.'), $limit));
                        unset($this->news_items[$type][$key]);
                    }
                }
            }
        }

        // sort grouped list by title
        foreach($this->area_structure as $type => $area_data) {
            if (isset($this->news_groups[$type]) && count($this->news_groups[$type])) {
                ksort($this->news_groups[$type]);
            }
        }

        $this->sidebar = Sidebar::get();
        if ($GLOBALS['perm']->have_perm('tutor')) {
            $widget = new ViewsWidget();
            $widget->addLink(
                _('Alle Ankündigungen'),
                $this->url_for('news/admin_news/all')
            )->setActive(!$this->area_type);
            if ($GLOBALS['perm']->have_perm('root')) {
                $widget->addLink(
                    _('System'),
                    $this->url_for('news/admin_news/global')
                )->setActive($this->area_type === 'global');
            }
            if ($GLOBALS['perm']->have_perm('root')) {
                $widget->addLink(
                    _('Loginseite'),
                    $this->url_for('news/admin_news/login')
                )->setActive($this->area_type === 'login');
            }
            if ($GLOBALS['perm']->have_perm('dozent')) {
                $widget->addLink(
                    _('Einrichtungen'),
                    $this->url_for('news/admin_news/inst')
                )->setActive($this->area_type === 'inst');
            }
            $widget->addLink(
                _('Veranstaltungen'),
                $this->url_for('news/admin_news/sem')
            )->setActive($this->area_type === 'sem');
            $widget->addLink(
                _('Profil'),
                $this->url_for('news/admin_news/user')
            )->setActive($this->area_type === 'user');
            $this->sidebar->addWidget($widget);
        }

        $widget = new ActionsWidget();
        $widget->addLink(
            _('Ankündigung erstellen'),
            $this->url_for('news/edit_news/new'),
            Icon::create('add'),
            ['target' => '_blank']
        )->asDialog();
        $this->sidebar->addWidget($widget);
    }

    /**
     * checks string for valid date
     *
     * @param string $date date-string to be checked
     * @param string $mode 'start' for startddate, 'end' for enddate (i.e. 23:59)
     * @return mixed result
     */
    private function getTimeStamp($date, $mode = 'start')
    {
        // No date given
        if (!$date) {
            return false;
        }

        return strtotime($date);
    }

    public function unassign_action(StudipNews $news, string $range_id, string $from = 'all'): void
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$news->havePermission('unassign', $range_id)) {
            throw new AccessDeniedException(sprintf(
                _('Keine Berechtigung zum Entfernen der Ankündigung "%s" aus diesem Bereich.'),
                $news->topic
            ));
        }

        if ($news->deleteRange($range_id)) {
            $news->store();
            PageLayout::postSuccess(sprintf(
                _('Ankündigung "%s" wurde aus dem Bereich entfernt.'),
                htmlReady($news->topic)
            ));
        }

        $this->redirect($this->admin_newsURL($from));
    }

    public function delete_action(StudipNews $news, string $from = 'all'): void
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$news->havePermission('delete')) {
            throw new AccessDeniedException(sprintf(
                _('Keine Berechtigung zum Löschen der Ankündigung "%s".'),
                $news->topic
            ));
        }

        $topic = $news->topic;

        if ($news->delete()) {
            PageLayout::postSuccess(sprintf(
                _('Ankündigung "%s" wurde gelöscht.'),
                htmlReady($topic)
            ));
        }

        $this->redirect($this->admin_newsURL($from));
    }

    public function rss_config_action($range_id)
    {
        if (!Config::get()->NEWS_RSS_EXPORT_ENABLE || !StudipNews::haveRangePermission('edit', $range_id)) {
            throw new AccessDeniedException();
        }
        if (Request::isPost()) {
            if (Request::submitted('rss_on')) {
                StudipNews::SetRssId($range_id);
            } else {
                StudipNews::UnsetRssId($range_id);
            }
        }
        $this->range_id = $range_id;
        $this->rss_id = StudipNews::GetRssIdFromRangeId($range_id);
    }
}
