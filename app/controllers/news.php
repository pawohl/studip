<?php
# Lifter007: TODO
# Lifter003: TODO
# Lifter010: TODO

/*
 * news.php - News controller
 *
 * Copyright (C) 2007 - Marcus Lunzenauer <mlunzena@uos.de>, Rasmus Fuhse <fuhse@data-quest.de>, Arne Schr�der <schroeder@data-quest.de>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Rasmus Fuhse <fuhse@data-quest.de>
 * @author      Marcus Lunzenauer <mlunzena@uos.de>
 * @author      Arne Schr�der <schroeder@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     news
 */

require_once 'lib/functions.php';
require_once 'lib/showNews.inc.php';
require_once 'lib/user_visible.inc.php';
require_once 'studip_controller.php';

class NewsController extends StudipController
{
    /**
     * Callback function being called before an action is executed.
     */
    function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        // open session
        page_open(array('sess' => 'Seminar_Session',
                        'auth' => 'Seminar_Default_Auth',
                        'perm' => 'Seminar_Perm',
                        'user' => 'Seminar_User'));

        // set up user session
        include 'lib/seminar_open.php';

        $this->set_content_type('text/html; charset=windows-1252');

        $this->area_structure = array('global' => array('title' => _('Stud.IP (systemweit)'), 'icon' => 'home.png'),
                                      'inst' => array('title' => _('Einrichtungen'), 'icon' => 'institute.png'),
                                      'sem' => array('title' => _('Veranstaltungen'), 'icon' => 'seminar.png'),
                                      'user' => array('title' => _('Profile'), 'icon' => 'person.png'));
    }

    /**
     * Callback function being called after an action is executed.
     */
    function after_filter($action, $args)
    {
        page_close();
    }

    /**
     * Widget controller to produce the formally known show_votes()
     *
     * @param String $range_id range id of the news to get displayed
     * @return array() Array of votes
     */
    function display_action($range_id)
    {
        if (!$range_id) {
            $this->set_status(400);
            return $this->render_nothing();
        }

        if (!StudipNews::haveRangePermission('view', $range_id, $GLOBALS['user']->id)) {
            $this->set_status(401);
            return $this->render_nothing();
        }

        // Check if user wrote a comment
        if (Request::submitted('accept') && trim(Request::get('comment_content')) && Request::isPost()) {
            CSRFProtection::verifySecurityToken();
            StudipComment::create(array(
            'object_id' => Request::get('comsubmit'),
            'user_id' => $GLOBALS['user']->id,
            'content' => trim(Request::get('comment_content'))
            ));
        }

        // Check if user wants to remove a announcement
        if ($news_id = Request::get('remove_news')) {
            $news = new StudipNews($news_id);
            $range = Request::get('news_range');
            if ($news->havePermission('unassign', $range)) {
                if (Request::get('confirm')) {
                    $news->deleteRange($range);
                    $news->store();
                } else {
                    $this->question = createQuestion(_('Ank�ndigung wirklich aus diesem Bereich entfernen?'), array('remove_news' => $news_id, 'news_range' => $range, 'confirm' => true));
                }
            }
        }

        // Check if user wants to delete an announcement
        if ($news_id = Request::get('delete_news')) {
            $news = new StudipNews($news_id);
            if ($news->havePermission('delete')) {
                if (Request::get('confirm')) {
                    $news->delete();
                } else {
                    $this->question = createQuestion(_('Ank�ndigung wirklich l�schen?'), array('delete_news' => $news_id, 'confirm' => true));
                }
            }
        }

        $this->perm = StudipNews::haveRangePermission('edit', $range_id);
        $this->show_all_news = Request::get('nshow_all') && $this->perm;
        $news = StudipNews::GetNewsByRange($range_id, !$this->show_all_news);
        if ($this->show_all_news) {
            URLHelper::addLinkParam('nshow_all', 1);
        }
        $this->news = StudipNews::GetNewsByRange($range_id, !$this->show_all_news, true);
        $this->count_all_news  = $this->show_all_news ? count($this->news) : count(StudipNews::GetNewsByRange($range_id, false));
        $this->rss_id = get_config('NEWS_RSS_EXPORT_ENABLE') ? StudipNews::GetRssIdFromRangeId($range_id) : false;
        $this->range = $range_id;
        $this->nobody = !$GLOBALS['user']->id || $GLOBALS['user']->id == 'nobody';

        $this->visit();
            }

    function visit()
    {
        if ($GLOBALS['user']->id && $GLOBALS['user']->id != 'nobody' && Request::option('contentbox_open') && Request::option('contentbox_type') === 'news') {
            object_add_view(Request::option('contentbox_open'));
            object_set_visit(Request::option('contentbox_open'), 'news'); //and, set a visittime
        }
    }

    function visit_action()
    {
        $this->visit();
        $this->render_nothing();
    }

    /**
     * shows news content
     *
     * @param string $id        news id
     */
    function get_news_action($id)
    {

        if (is_null($id)) {
            $this->set_status(400);
            return $this->render_nothing();
        }

        $news = new StudipNews($id);

        if ($news->isNew()) {
            $this->set_status(404);
            return $this->render_nothing();
        }

        // check for permission for at least one of those ranges
        if (!$news->havePermission('view')) {
            $this->set_status(401);
            return $this->render_nothing();
        }
        $newscontent = $news->toArray();

        // use the same logic here as in show_news_item()
        if ($newscontent['user_id'] != $GLOBALS['auth']->auth['uid']) {
            object_add_view($id);
        }

        object_set_visit($id, "news");
        if ($GLOBALS['user']->id == 'nobody') {
            $newscontent['allow_comments'] = 0;
        }
        $this->news = $newscontent;
        $this->content = show_news_item_content($newscontent,
                                                array(),
                                                Request::get('range_id')
        );
    }

    /**
     * @addtogroup notifications
     *
     * Creating a news triggers a NewsDidCreate notification. The news's ID is
     * transmitted as subject of the notification.
     */

    /**
     * Builds news dialog for editing / adding news
     *
     * @param string $id news           id (in case news already exists; otherwise set to "new")
     * @param string $context_range     range id (only for new news; set to 'template' for copied news)
     * @param string $template_id       template id (source of news template)
     *
     */
    function edit_news_action($id = '', $context_range = '', $template_id = '')
    {
        // initialize
        $this->news_isvisible = array('news_basic' => true, 'news_comments' => false, 'news_areas' => false);
        $ranges = array();
        $this->ranges = array();
        $this->area_options_selectable = array();
        $this->area_options_selected = array();
        $this->may_delete = false;
        $this->route = "news/edit_news/$id";
        if ($context_range) {
            $this->route .= "/$context_range";
            if ($template_id)
                $this->route .= "/$template_id";
        }
        $msg_object = new messaging();

        if ($id == "new") {
            unset($id);
            $this->title = _("Ank�ndigung erstellen");
        } else
            $this->title = _("Ank�ndigung bearbeiten");

        // user has to have autor permission at least
        if (! $GLOBALS['perm']->have_perm(autor)) {
            $this->set_status(401);
            return $this->render_nothing();
        }
        // Output as dialog (Ajax-Request) or as Stud.IP page?
        if (Request::isXhr()) {
            $this->set_layout(null);
            header('X-Title: ' . $this->title);
        } else {
            $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
        }

        // load news and comment data and check if user has permission to edit
        $news = new StudipNews($id);
        if (!$news->isNew())
            $this->comments = StudipComment::GetCommentsForObject($id);
        if ((!$news->havePermission('edit') AND (!$news->isNew()))) {
            $this->set_status(401);
            PageLayout::postMessage(MessageBox::error(_('Keine Berechtigung!')));
            return $this->render_nothing();
        }
        // if form sent, get news data by post vars
        if (Request::get('news_isvisible')) {
            // visible categories, selected areas, topic, and body are utf8 encoded when sent via ajax
            $this->news_isvisible = unserialize((Request::get('news_isvisible')));
            if (Request::isXhr()) {
                $this->area_options_selected = unserialize(studip_utf8decode(Request::get('news_selected_areas')));
                $this->area_options_selectable = unserialize(studip_utf8decode(Request::get('news_selectable_areas')));
                $topic = studip_utf8decode(Request::get('news_topic'));
                $body = transformBeforeSave(studip_utf8decode(Request::get('news_body')));
            } else {
                $this->area_options_selected = unserialize(Request::get('news_selected_areas'));
                $this->area_options_selectable = unserialize(Request::get('news_selectable_areas'));
                $topic = Request::get('news_topic');
                $body = transformBeforeSave(Request::get('news_body'));
            }
            $date = $this->getTimeStamp(Request::get('news_startdate'), 'start');
            $expire = $this->getTimeStamp(Request::get('news_enddate'), 'end') ? $this->getTimeStamp(Request::get('news_enddate'), 'end') - $this->getTimeStamp(Request::get('news_startdate'), 'start') : '';
            $allow_comments = Request::get('news_allow_comments') ? 1 : 0;
            if (Request::submitted('comments_status_deny')) {
                $this->anker = 'news_comments';
                $allow_comments = 0;
            } elseif (Request::submitted('comments_status_allow')) {
                $this->anker = 'news_comments';
                $allow_comments = 1;
            }
            if ($news->getValue('topic') != $topic
                OR $news->getValue('body') != $body
                OR $news->getValue('date') != $date
                OR $news->getValue('allow_comments') != $allow_comments
                OR $news->getValue('expire') != $expire)
                $changed = true;
            $news->setValue('topic', $topic);
            $news->setValue('body', $body);
            $news->setValue('date', $date);
            $news->setValue('expire', $expire);
            $news->setValue('allow_comments', $allow_comments);
        } elseif ($id) {
            // if news id given check for valid id and load ranges
            if ($news->isNew()) {
                PageLayout::postMessage(MessageBox::error(_('Die Ank�ndigung existiert nicht!')));
                return $this->render_nothing();
            }
            $ranges = $news->news_ranges->toArray();
        } elseif ($template_id) {
            // otherwise, load data from template
            $news_template = new StudipNews($template_id);
            if ($news_template->isNew()) {
                PageLayout::postMessage(MessageBox::error(_('Die Ank�ndigung existiert nicht!')));
                return $this->render_nothing();
            }
            // check for permission
            if (!$news_template->havePermission('edit')) {
                $this->set_status(401);
                return $this->render_nothing();
            }
            $ranges = $news_template->news_ranges->toArray();
            // remove those ranges for which user doesn't have permission
            foreach ($ranges as $key => $news_range)
                if (!$news->haveRangePermission('edit', $news_range['range_id'])) {
                    $changed_areas++;
                    $this->news_isvisible['news_areas'] = true;
                    unset($ranges[$key]);
                }
            if ($changed_areas == 1)
                PageLayout::postMessage(MessageBox::info(_('1 zugeordneter Bereich wurde nicht �bernommen, weil Sie dort keine Ank�ndigungen erstellen d�rfen.')));
            elseif ($changed_areas)
                PageLayout::postMessage(MessageBox::info(sprintf(_('%s zugeordnete Bereiche wurden nicht �bernommen, weil Sie dort keine Ank�ndigungen erstellen d�rfen.'), $changed_areas)));
            $news->setValue('topic', $news_template->getValue('topic'));
            $news->setValue('body', $news_template->getValue('body'));
            $news->setValue('date', $news_template->getValue('date'));
            $news->setValue('expire', $news_template->getValue('expire'));
            $news->setValue('allow_comments', $news_template->getValue('allow_comments'));
        } else {
            // for new news, set startdate to today and range to dialog context
            $news->setValue('date', strtotime(date('Y-m-d')));// + 12*60*60;
            $news->setValue('expire', 604800);
            if (($context_range != '') AND ($context_range != 'template')) {
                $add_range = new NewsRange(array('', $context_range));
                $ranges[] = $add_range->toArray();
            }
        }
        // build news var for template
        $this->news = $news->toArray();

        // treat faculties and institutes as one area group (inst)
        foreach ($ranges as $range) {
            switch ($range['type']) {
                case 'fak' : $this->area_options_selected['inst'][$range['range_id']] = $range['name'];
                break;
                default : $this->area_options_selected[$range['type']][$range['range_id']] = $range['name'];
            }
        }

        // define search presets
        $this->search_presets['user'] = _('Meine Profilseite');
        if ($GLOBALS['perm']->have_perm('autor') AND !$GLOBALS['perm']->have_perm('admin')) {
            $my_sem = $this->search_area('__THIS_SEMESTER__');
            if (count($my_sem['sem']))
                $this->search_presets['sem'] = _('Meine Veranstaltungen im aktuellen Semester').' ('.count($my_sem['sem']).')';
        }
        if ($GLOBALS['perm']->have_perm('dozent') AND !$GLOBALS['perm']->have_perm('root')) {
            $my_inst = $this->search_area('__MY_INSTITUTES__');
            if (count($my_inst))
                $this->search_presets['inst'] = _('Meine Einrichtungen').' ('.count($my_inst['inst']).')';
        }
        if ($GLOBALS['perm']->have_perm('root'))
            $this->search_presets['global'] = $this->area_structure['global']['title'];

        // perform search
        if (Request::submitted('area_search') OR Request::submitted('area_search_preset')) {
            $this->anker = 'news_areas';
            $this->search_term = studip_utf8decode(Request::get('area_search_term'));
            if (Request::submitted('area_search'))
                $this->area_options_selectable = $this->search_area($this->search_term);
            else {
                $this->current_search_preset = Request::option('search_preset');
                if ($this->current_search_preset == 'inst')
                    $this->area_options_selectable = $my_inst;
                elseif ($this->current_search_preset == 'sem')
                    $this->area_options_selectable = $my_sem;
                elseif ($this->current_search_preset == 'user')
                    $this->area_options_selectable = array('user' => array($GLOBALS['auth']->auth['uid'] => get_fullname()));
                elseif ($this->current_search_preset == 'global')
                    $this->area_options_selectable = array('global' => array('studip' => _('Stud.IP')));
            }

            if (!count($this->area_options_selectable))
                unset($this->search_term);
            else {
                // already assigned areas won't be selectable
                foreach($this->area_options_selected as $type => $data)
                    foreach ($data as $id => $title)
                        unset($this->area_options_selectable[$type][$id]);
            }
        }
        // delete comment(s)
        if (Request::submitted('delete_marked_comments')) {
            $this->anker = 'news_comments';
            $this->flash['question_text'] = delete_comments(Request::optionArray('mark_comments'));
            $this->flash['question_param'] = array('mark_comments' => Request::optionArray('mark_comments'),
                                                   'delete_marked_comments' => 1);
            // reload comments
            if (!$this->flash['question_text']) {
                $this->comments = StudipComment::GetCommentsForObject($id);
                $changed = true;
            }
        }
        if ($news->havePermission('delete'))
            $this->comments_admin = true;
        if (is_array($this->comments)) {
            foreach ($this->comments as $key => $comment) {
                if (Request::submitted('news_delete_comment_'.$comment['comment_id'])) {
                    $this->anker = 'news_comments';
                    $this->flash['question_text'] = delete_comments($comment['comment_id']);
                    $this->flash['question_param'] = array('mark_comments' => array($comment['comment_id']),
                                                           'delete_marked_comments' => 1);
                }
            }
        }
        // open / close category
        foreach($this->news_isvisible as $category => $value)
            if ((Request::submitted('toggle_'.$category)) OR (Request::get($category.'_js'))) {
                $this->news_isvisible[$category] = $this->news_isvisible[$category] ? false : true;
                $this->anker = $category;
            }
        // add / remove areas
        if (Request::submitted('news_add_areas') AND is_array($this->area_options_selectable)) {
            $this->anker = 'news_areas';
            foreach (Request::optionArray('area_options_selectable') as $range_id) {
                foreach ($this->area_options_selectable as $type => $data) {
                    if (isset($data[$range_id])) {
                        $this->area_options_selected[$type][$range_id] = $data[$range_id];
                        unset($this->area_options_selectable[$type][$range_id]);
                    }
                }
            }
        }
        if (Request::submitted('news_remove_areas') AND is_array($this->area_options_selected)) {
            $this->anker = 'news_areas';
            foreach (Request::optionArray('area_options_selected') as $range_id) {
                foreach ($this->area_options_selected as $type => $data) {
                    if (isset($data[$range_id])) {
                        $this->area_options_selectable[$type][$range_id] = $data[$range_id];
                        unset($this->area_options_selected[$type][$range_id]);
                    }
                }
            }
        }
        // prepare to save news
        if (Request::submitted('save_news') AND Request::isPost()) {
            CSRFProtection::verifySecurityToken();
            //prepare ranges array for already assigned news_ranges
            foreach($news->getRanges() as $range_id)
                $this->ranges[$range_id] = get_object_type($range_id, array('global', 'fak', 'inst', 'sem', 'user'));

            // check if new ranges must be added
            foreach ($this->area_options_selected as $type => $area_group) {
                foreach ($area_group as $range_id => $area_title) {
                    if (!isset($this->ranges[$range_id])) {
                        if ($news->haveRangePermission('edit', $range_id)) {
                            $news->addRange($range_id);
                            $changed = true;
                        } else {
                            PageLayout::postMessage(MessageBox::error(sprintf(_('Sie haben keine Berechtigung zum �ndern der Bereichsverkn�pfung f�r "%s".'), htmlReady($area_title))));
                            $error++;
                        }
                    }
                }
            }

            // check if assigned ranges must be removed
            foreach ($this->ranges as $range_id => $range_type) {
                if (($range_type === 'fak' && !isset($this->area_options_selected['inst'][$range_id])) ||
                   ($range_type !== 'fak' && !isset($this->area_options_selected[$range_type][$range_id]))) {
                    if ($news->havePermission('unassign', $range_id)) {
                        $news->deleteRange($range_id);
                        $changed = true;
                    } else {
                        PageLayout::postMessage(MessageBox::error(_('Sie haben keine Berechtigung zum �ndern der Bereichsverkn�pfung.')));
                        $error++;
                    }
                }
            }

            // save news
            if ($news->validate() AND !$error) {
                if (!$id)
                    NotificationCenter::postNotification('NewsDidCreate', $news->getId());
                elseif (($news->getValue('user_id') != $GLOBALS['auth']->auth['uid'])) {
                    $news->setValue('chdate_uid', $GLOBALS['auth']->auth['uid']);
                    setTempLanguage($news->getValue('user_id'));
                    $msg = sprintf(_('Ihre Ank�ndigung "%s" wurde von %s ver�ndert.'), $news->getValue('topic'), get_fullname() . ' ('.get_username().')'). "\n";
                    $msg_object->insert_message($msg, get_username($news->getValue('user_id')) , "____%system%____", FALSE, FALSE, "1", FALSE, _("Systemnachricht:")." "._("Ank�ndigung ge�ndert"));
                    restoreLanguage();
                } else
                    $news->setValue('chdate_uid', '');
                $news->store();
                PageLayout::postMessage(MessageBox::success(_('Die Ank�ndigung wurde gespeichert.')));
                // in fallback mode redirect to edit page with proper news id
                if (!Request::isXhr() AND !$id)
                    $this->redirect('news/edit_news/'.$news->getValue('news_id'));
                // if in dialog mode send empty result (STUDIP.News closes dialog and initiates reload)
                elseif (Request::isXhr())
                    $this->render_nothing();
            }
        }
        // check if user has full permission on news object
        if ($news->havePermission('delete'))
            $this->may_delete = true;
    }


    /**
     * Show administration page for user's news
     *
     * @param string $area_type         area filter
     */
    function admin_news_action($area_type = '')
    {
        // check permission
        if (!$GLOBALS['auth']->is_authenticated() || $GLOBALS['user']->id === 'nobody') {
            throw new AccessDeniedException();
        }
        $GLOBALS['perm']->check('user');

        // initialize
        $news_result = array();
        $limit = 100;
        if (Request::get('news_filter') == 'set') {
            $this->news_searchterm = Request::option('news_filter_term');
            $this->news_startdate = Request::int('news_filter_start');
            $this->news_enddate = Request::int('news_filter_end');
        } else {
            $this->news_startdate = time();
        }
        if (is_array($this->area_structure[$area_type]))
            $this->area_type = $area_type;
        $this->set_layout($GLOBALS['template_factory']->open('layouts/base'));
        PageLayout::setTitle(_('Meine Ank�ndigungen'));
        PageLayout::setHelpKeyword('Basis.News');
        Navigation::activateItem('/tools/news');
        if (Request::submitted('reset_filter')) {
            $area_type = 'all';
            $this->news_searchterm = '';
            $this->news_startdate = '';
            $this->news_enddate = '';
        }
        // delete news
        if (Request::submitted('remove_marked_news')) {
            $remove_ranges = array();
            foreach (Request::optionArray('mark_news') as $mark_id) {
                list($news_id, $range_id) = explode('_', $mark_id);
                $remove_ranges[$news_id][] = $range_id;
            }
            $this->flash['question_text'] = remove_news($remove_ranges);
            $this->flash['question_param'] = array('mark_news' => Request::optionArray('mark_news'),
                                                   'remove_marked_news' => 1);
        }
        // apply filter
        if (Request::submitted('apply_news_filter')) {
            $this->news_isvisible['basic'] = $this->news_isvisible['basic'] ? false : true;
            if (Request::get('news_searchterm') AND (strlen(trim(Request::get('news_searchterm'))) < 3))
                PageLayout::postMessage(MessageBox::error(_('Der Suchbegriff muss mindestens 3 Zeichen lang sein.')));
            elseif ((Request::get('news_startdate') AND !$this->getTimeStamp(Request::get('news_startdate'), 'start')) OR (Request::get('news_enddate') AND !$this->getTimeStamp(Request::get('news_enddate'), 'end')))
                PageLayout::postMessage(MessageBox::error(_('Ung�ltige Datumsangabe. Bitte geben Sie ein Datum im Format TT.MM.JJJJ ein.')));
            elseif (Request::get('news_enddate') AND Request::get('news_enddate') AND ($this->getTimeStamp(Request::get('news_startdate'), 'start') > $this->getTimeStamp(Request::get('news_enddate'), 'end')))
                PageLayout::postMessage(MessageBox::error(_('Das Startdatum muss vor dem Enddatum liegen.')));

            if (strlen(trim(Request::get('news_searchterm'))) >= 3)
                $this->news_searchterm = Request::get('news_searchterm');
            $this->news_startdate = $this->getTimeStamp(Request::get('news_startdate'), 'start');
            $this->news_enddate = $this->getTimeStamp(Request::get('news_enddate'), 'end');
        }
        // fetch news list
        $this->news_items = StudipNews::getNewsRangesByFilter($GLOBALS["auth"]->auth["uid"], $this->area_type, $this->news_searchterm, $this->news_startdate, $this->news_enddate, true, $limit+1);
        // build area and filter description
        if ($this->news_searchterm AND $this->area_type AND ($this->area_type != 'all')) {
            if ($this->news_startdate AND $this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s" zum Suchbegriff "%s", die zwischen dem %s und dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm, date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            elseif ($this->news_startdate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s" zum Suchbegriff "%s", die ab dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm, date('d.m.Y', $this->news_startdate));
            elseif ($this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s" zum Suchbegriff "%s", die vor dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm, date('d.m.Y', $this->news_enddate));
            else
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s" zum Suchbegriff "%s".'), $this->area_structure[$this->area_type]['title'], $this->news_searchterm);
        } elseif ($this->area_type AND ($this->area_type != 'all')) {
            if ($this->news_startdate AND $this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s", die zwischen dem %s und dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            elseif ($this->news_startdate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s", die ab dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], date('d.m.Y', $this->news_startdate));
            elseif ($this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s", die vor dem %s sichtbar sind.'), $this->area_structure[$this->area_type]['title'], date('d.m.Y', $this->news_enddate));
            else
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen im Bereich "%s".'), $this->area_structure[$this->area_type]['title']);
        } elseif ($this->news_searchterm) {
            if ($this->news_startdate AND $this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen zum Suchbegriff "%s", die zwischen dem %s und dem %s sichtbar sind.'), $this->news_searchterm, date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            elseif ($this->news_startdate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen zum Suchbegriff "%s", die ab dem %s sichtbar sind.'), $this->news_searchterm, date('d.m.Y', $this->news_startdate));
            elseif ($this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen zum Suchbegriff "%s", die vor dem %s sichtbar sind.'), $this->news_searchterm, date('d.m.Y', $this->news_enddate));
            else
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen zum Suchbegriff "%s".'), $this->news_searchterm);
        } elseif ($this->news_startdate OR $this->news_enddate) {
            if ($this->news_startdate AND $this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen, die zwischen dem %s und dem %s sichtbar sind.'), date('d.m.Y', $this->news_startdate), date('d.m.Y', $this->news_enddate));
            elseif ($this->news_startdate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen, die ab dem %s sichtbar sind.'), date('d.m.Y', $this->news_startdate));
            elseif ($this->news_enddate)
                $this->filter_text = sprintf(_('Angezeigt werden Ank�ndigungen, die vor dem %s sichtbar sind.'), date('d.m.Y', $this->news_enddate));
        }

        // check for delete-buttons and news limit
        foreach ($this->area_structure as $type => $area_data) {
            if (is_array($this->news_items[$type])) {
                foreach($this->news_items[$type] as $key => $news) {
                    // has trash icon been clicked?
                    if (Request::submitted('news_remove_'.$news['object']->news_id.'_'.$news['range_id']) AND Request::isPost()) {
                        $this->flash['question_text'] = remove_news(array($news['object']->news_id => $news['range_id']));
                        $this->flash['question_param'] = array('mark_news' => array($news['object']->news_id.'_'.$news['range_id']),
                                                               'remove_marked_news' => 1);
                    }
                    // check if result set too big
                    $counter++;
                    if ($counter == $limit+1) {
                        PageLayout::postMessage(MessageBox::info(sprintf(_('Es werden nur die ersten %s Ank�ndigungen angezeigt.'), $limit)));
                        unset($this->news_items[$type][$key]);
                    }
                }
            }
        }

        // sort grouped list by title
        foreach($this->area_structure as $type => $area_data)
            if (count($this->news_groups[$type]))
                ksort($this->news_groups[$type]);

        $this->sidebar = Sidebar::get();
        $this->sidebar->setImage('sidebar/news-sidebar.png');
        if ($GLOBALS['perm']->have_perm('tutor')) {
            $widget = new ViewsWidget();
            $widget->addLink(_('Alle Ank�ndigungen'), URLHelper::getURL('dispatch.php/news/admin_news/all'))->setActive(!$this->area_type);
            if ($GLOBALS['perm']->have_perm('root')) {
                $widget->addLink(_('System'), URLHelper::getURL('dispatch.php/news/admin_news/global'))->setActive($this->area_type === 'global');
            }
            if ($GLOBALS['perm']->have_perm('dozent')) {
                $widget->addLink(_('Einrichtungen'), URLHelper::getURL('dispatch.php/news/admin_news/inst'))->setActive($this->area_type === 'inst');
            }
            $widget->addLink(_('Veranstaltungen'), URLHelper::getURL('dispatch.php/news/admin_news/sem'))->setActive($this->area_type === 'sem');
            $widget->addLink(_('Profil'), URLHelper::getURL('dispatch.php/news/admin_news/user'))->setActive($this->area_type === 'user');
            $this->sidebar->addWidget($widget);
        }
        $widget = new ActionsWidget();
        $widget->addLink(_('Ank�ndigung erstellen'), URLHelper::getLink('dispatch.php/news/edit_news/new'), 'icons/16/blue/add/news.png', array('rel'=>'get_dialog', 'target'=>'_blank'));
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
        if ($date) {
            $date_array = explode('.', $date);
            if (checkdate($date_array[1], $date_array[0], $date_array[2])) {
                if ($mode == 'end')
                    return mktime(23,59,0,$date_array[1], $date_array[0], $date_array[2]);
                else
                    return mktime(0,0,0,$date_array[1], $date_array[0], $date_array[2]);
            }
        }
        return false;
    }

    /**
     * Searchs for studip areas using given search term
     *
     * @param string $term search term
     * @return array area data
     */
    function search_area($term) {
        global $perm;
        $result = array();
        if (strlen($term) < 3) {
            PageLayout::postMessage(MessageBox::error(_('Der Suchbegriff muss mindestens drei Zeichen lang sein.')));
            return $result;
        } elseif ($term == '__THIS_SEMESTER__') {
            $nr = 0;
            $current_semester = Semester::findCurrent();
            $query = "SELECT seminare.Name AS sem_name, seminare.Seminar_id, seminare.visible
                      FROM seminar_user LEFT JOIN seminare  USING (Seminar_id)
                      WHERE seminar_user.user_id = :user_id AND seminar_user.status IN('tutor', 'dozent')
                      AND seminare.start_time <= :start
                      AND (:start <= (seminare.start_time + seminare.duration_time)
                      OR seminare.duration_time = -1)";
            if (get_config('DEPUTIES_ENABLE')) {
                $query .= " UNION SELECT CONCAT(seminare.Name, ' ["._("Vertretung")."]') AS sem_name, seminare.Seminar_id,
                            seminare.visible
                            FROM deputies JOIN seminare ON (deputies.range_id=seminare.Seminar_id)
                            WHERE deputies.user_id = :user_id
                            AND seminare.start_time <= :start
                            AND (:start <= (seminare.start_time + seminare.duration_time)
                            OR seminare.duration_time = -1)";
            }
            $query .= " ORDER BY sem_name ASC";
            $statement = DBManager::get()->prepare($query);
            $statement->bindValue(':user_id', $GLOBALS['auth']->auth['uid']);
            $statement->bindValue(':start', $current_semester["beginn"]);
            $statement->execute();
            $seminars = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach($seminars as $key => $sem)
                    $tmp_result[$sem['Seminar_id']] = array(
                        'name' => $sem['sem_name'],
                        'type' => 'sem'
                    );
            $term = '';
        } elseif ($term == '__MY_INSTITUTES__') {
            $term = '';
            if ($perm->have_perm('root')) {
                $tmp_result['studip'] = array(
                    'name' => 'Stud.IP',
                    'type' => 'global'
                );
            }
            $inst_list = Institute::getMyInstitutes();
            if (count($inst_list))
                foreach($inst_list as $data) {
                    $tmp_result[$data['Institut_id']] = array(
                        'name' => $data['Name'],
                        'type' => ($data['is_fak'] ? 'fak' : 'inst'));
                }
        } else {
            $tmp_result = search_range($term, true);
            // add users
            if (stripos(get_fullname(), $term) !== false)
                $tmp_result[$GLOBALS['auth']->auth['uid']] = array(
                    'name' => get_fullname(),
                    'type' => 'user');
            if (isDeputyEditAboutActivated()) {
                $query = "SELECT DISTINCT a.user_id "
                       . "FROM deputies d "
                       . "JOIN auth_user_md5 a ON (d.range_id = a.user_id) "
                       . "JOIN user_info u ON (a.user_id=u.user_id) "
                       . "WHERE d.user_id = ? "
                       . "AND CONCAT(u.title_front, ' ', a.Vorname, ' ', a.Nachname, ', ', u.title_rear) LIKE CONCAT('%',?,'%')";
                $statement = DBManager::get()->prepare($query);
                $statement->execute(array($GLOBALS['auth']->auth['uid'], $term));
                while ($data = $statement->fetch(PDO::FETCH_ASSOC)) {
                    $tmp_result[$data['user_id']] = array(
                        'name' => get_fullname($data['user_id']),
                        'type' => 'user');
                }
            }
        }
        // workaround: apply search term (ignored by search_range below admin)
        if ((count($tmp_result)) AND (!$GLOBALS['perm']->have_perm('admin')) AND ($term))
            foreach ($tmp_result as $id => $data) {
                if (stripos($data['name'], $term) === false)
                    unset($tmp_result[$id]);
            }
        // prepare result
        if (count($tmp_result)) {
            foreach ($tmp_result as $id => $data) {
                $result[$data['type'] == 'fak' ? 'inst' : $data['type']][$id] = $data['name'];
            }
        } elseif ($term) {
            PageLayout::postMessage(MessageBox::error(_('Zu diesem Suchbegriff wurden keine Bereiche gefunden.')));
        }
        return $result;
    }

    function rss_config_action($range_id)
    {
        if (!get_config('NEWS_RSS_EXPORT_ENABLE') || !StudipNews::haveRangePermission('edit', $range_id)) {
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

