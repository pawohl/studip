<?php
/*
 * Copyright (c) 2011  Rasmus Fuhse
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

/**
 * Controller called by the main periodical ajax-request. It collects data,
 * converts the textstrings to utf8 and returns it as a json-object to the
 * internal javascript-function "STUDIP.JSUpdater.process(json)".
 */
class JsupdaterController extends AuthenticatedController
{
    // Allow nobody to prevent login screen
    // Refers to http://develop.studip.de/trac/ticket/4771
    protected $allow_nobody = true;

    /**
     * Checks whether we have a valid logged in user,
     * send "Forbidden" otherwise.
     *
     * @param String $action The action to perform
     * @param Array  $args   Potential arguments
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        // Keep all things in flash, this controller show tinker with those values
        $this->flash->keep();

        // Check for a valid logged in user (only when an ajax request occurs)
        if (Request::isXhr() && (!is_object($GLOBALS['user']) || $GLOBALS['user']->id === 'nobody')) {
            $this->response->set_status(403);
            $action = 'nop';
        }
    }

    /**
     * Does and renders absolute nothing.
     */
    public function nop_action()
    {
        $this->render_nothing();
    }

    /**
     * Main action that returns a json-object like
     * {
     *  'js_function.sub_function': data,
     *  'anotherjs_function.sub_function': moredata
     * }
     * This action is called by STUDIP.JSUpdater.poll and the result processed
     * the internal STUDIP.JSUpdater.process method
     */
    public function get_action()
    {
        UpdateInformation::setInformation("server_timestamp", time());
        $data = UpdateInformation::getInformation();
        $data = array_merge($data, $this->coreInformation());

        $this->render_json($data);
    }

    /**
     * Marks a personal notification as read by the user so it won't be displayed
     * in the list in the header.
     * @param string $id : hash-id of the notification
     */
    public function mark_notification_read_action($id)
    {
        if ($id === 'all') {
            PersonalNotifications::markAllAsRead();
        } else {
            PersonalNotifications::markAsRead($id);
        }

        $url = false;
        if ($id === 'all') {
            $url = Request::get('return_to');
        } elseif (!Request::isXhr() || Request::isDialog()) {
            $notification = new PersonalNotifications($id);
            $url = $notification->url;
        }

        if ($url && is_internal_url($url)) {
            $this->redirect(URLHelper::getURL(TransformInternalLinks($url)));
        } else {
            $this->render_nothing();
        }
    }

    /**
     * Sets the background-color of the notification-number to blue, so it does
     * not annoy the user anymore. But he/she is still able to see the notificaion-list.
     * Just sets a unix-timestamp in the user-config NOTIFICATIONS_SEEN_LAST_DATE.
     */
    public function notifications_seen_action()
    {
        UserConfig::get($GLOBALS['user']->id)->store('NOTIFICATIONS_SEEN_LAST_DATE', time());
        $this->render_text(time());
    }

    /**
     * SystemPlugins may call UpdateInformation::setInformation to set information
     * to be sent via ajax to the main request. Core-functionality-data should be
     * collected and set here.
     * @return array: array(array('index' => $data), ...)
     */
    protected function coreInformation()
    {
        $pageInfo = Request::getArray('page_info');
        $data = [
            'coursewareclipboard' => $this->getCoursewareClipboardUpdates($pageInfo['coursewareclipboard'] ?? null),
            'blubber' => $this->getBlubberUpdates($pageInfo['blubber'] ?? null),
            'messages' => $this->getMessagesUpdates($pageInfo['messages'] ?? null),
            'personalnotifications' => $this->getPersonalNotificationUpdates(),
            'questionnaire' => $this->getQuestionnaireUpdates($pageInfo['questionnaire'] ?? null),
            'wiki_editor_status' => $this->getWikiEditorStatus($pageInfo['wiki_editor_status'] ?? null),
        ];

        return array_filter($data);
    }

    private function getBlubberUpdates($pageInfo)
    {
        $data = [];
        if (isset($pageInfo['threads']) && is_array($pageInfo['threads'])) {
            $blubber_data = [];
            foreach ($pageInfo['threads'] as $thread_id) {
                $thread = new BlubberThread($thread_id);
                if ($thread->isReadable()) {
                    $comments = BlubberComment::findBySQL(
                        "thread_id = :thread_id AND chdate >= :time ORDER BY mkdate ASC",
                        ['thread_id' => $thread_id, 'time' => UpdateInformation::getTimestamp()]
                    );
                    foreach ($comments as $comment) {
                        $blubber_data[$thread_id][] = $comment->getJSONdata();
                    }
                }
            }
            if (count($blubber_data)) {
                $data['addNewComments'] = $blubber_data;
            }
            $statement = DBManager::get()->prepare("
                SELECT blubber_events_queue.item_id
                FROM blubber_events_queue
                WHERE blubber_events_queue.event_type = 'delete'
            ");
            $statement->execute([$pageInfo['threads']]);
            $comment_ids = $statement->fetchAll(PDO::FETCH_COLUMN, 0);
            if (count($comment_ids)) {
                $data['removeDeletedComments'] = $comment_ids;
            }
            $statement = DBManager::get()->prepare("
                DELETE FROM blubber_events_queue
                WHERE mkdate <= UNIX_TIMESTAMP() - 60 * 15
            ");
            $statement->execute();
        }
        if (mb_stripos(Request::get("page"), "dispatch.php/blubber") !== false) {
            //collect updated threads for the widget
            $threads = BlubberThread::findMyGlobalThreads(30, UpdateInformation::getTimestamp());
            $thread_widget_data = [];
            foreach ($threads as $thread) {
                $thread_widget_data[] = [
                    'thread_id' => $thread->getId(),
                    'avatar' => $thread->getAvatar(),
                    'name' => $thread->getName(),
                    'timestamp' => (int) $thread->getLatestActivity()
                ];
            }
            if (count($thread_widget_data)) {
                $data['updateThreadWidget'] = $thread_widget_data;
            }
        }

        return $data;
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function getMessagesUpdates($pageInfo)
    {
        $data = [];
        if (mb_stripos(Request::get("page"), "dispatch.php/messages") !== false) {
            $messages = Message::findNew(
                $GLOBALS["user"]->id,
                $pageInfo['received'],
                $pageInfo['since'],
                $pageInfo['tag']
            );
            $templateFactory = $this->get_template_factory();
            foreach ($messages as $message) {
                $attributes = [
                    'message' => $message,
                    'received' => $pageInfo['received'],
                    'controller' => $this,
                ];
                $html = $templateFactory->open("messages/_message_row.php")
                                         ->render($attributes);
                $data['messages'][$message->getId()] = $html;
            }
        }

        return $data;
    }

    /**
     * @SuppressWarnings(UnusedFormalParameter)
     */
    private function getPersonalNotificationUpdates()
    {
        $data = [];

        if (PersonalNotifications::isActivated()) {
            $data['notifications'] = array_map(
                function ($notification) {
                    $info = $notification->toArray();
                    $info['html'] = $notification->getLiElement();
                    return $info;
                },
                PersonalNotifications::getMyNotifications()
            );
        }

        return array_filter($data);
    }

    private function getQuestionnaireUpdates($pageInfo)
    {
        if (
            !isset($pageInfo['questionnaire_ids'])
            || !is_array($pageInfo['questionnaire_ids'])
        ) {
            return [];
        }

        $data = [];
        Questionnaire::findEachMany(
            function (Questionnaire $questionnaire) use ($pageInfo, &$data) {
                if ($questionnaire->latestAnswerTimestamp() > $pageInfo['last_update']) {
                    $template = $this->get_template_factory()->open('questionnaire/evaluate');
                    $template->questionnaire = $questionnaire;
                    $template->filtered = $pageInfo['filtered'] ?? [];
                    $template->set_layout(null);
                    $data[$questionnaire->id] = [
                        'html' => $template->render()
                    ];
                }
            },
            $pageInfo['questionnaire_ids']
        );

        return $data;
    }

    private function getWikiEditorStatus($pageInfo): array
    {
        $data = [];
        if (!empty($pageInfo)) {
            $id = $pageInfo['id'];

            $user = User::findCurrent();
            $page = WikiPage::find($id);
            if ($page) {
                WikiOnlineEditingUser::purge($page);

                if ($page->isEditable()) {
                    $onlineData = [
                        'user_id' => $user->id,
                        'page_id' => $page->id,
                    ];
                    $online = WikiOnlineEditingUser::findOneBySQL(
                        '`user_id` = :user_id AND `page_id` = :page_id',
                        $onlineData
                    );
                    if (!$online) {
                        $online = WikiOnlineEditingUser::build($onlineData);
                    } elseif ($online->editing && isset($pageInfo['content'])) {
                        $page->content = \Studip\Markup::markAsHtml($pageInfo['content']);
                        if ($page->isDirty()) {
                            $page->user_id = $user->id;
                            $page->store();
                        }
                    } else {
                        $editingUsers = WikiOnlineEditingUser::countBySQL(
                            '`page_id` = ? AND `editing` = 1 AND `user_id` != ?',
                            [$page->id, $user->id]
                        );
                        if ($editingUsers > 0) {
                            $online->editing = false;
                        } elseif ($online->editing && $online->editing_request) {
                            // this is the mode that this user requested the editing mode and was granted to get it:
                            $online->editing_request = false;
                        } elseif ($online->editing_request) {
                            $other_requests = WikiOnlineEditingUser::countBySql('`page_id` = ? AND `editing_request` = 1 AND `user_id` != ?', [
                                $page->id,
                                $user->id,
                            ]);
                            if ($other_requests === 0) {
                                $online->editing_request = false;
                                $online->editing = true;
                            }
                        } else {
                            $other_users = WikiOnlineEditingUser::countBySql('`page_id` = ? AND `user_id` != ?', [
                                $page->id,
                                $user->id,
                            ]);
                            // if I'm the only user I don't need to lose the edit mode
                            if ($other_users === 0) {
                                $online->editing = true;
                            }
                        }
                    }

                    $online->chdate = time();
                    $online->store();

                    $data['editing'] = (bool) $online->editing;
                }

                if (
                    $page->isReadable()
                    && $page->chdate >= Request::int('server_timestamp')
                ) {
                    $data['html'] = wikiReady($page->content, true, $page->range_id, $page->id);
                    $data['content'] = Studip\Markup::markupToHtml($page->content);
                    $data['chdate'] = date('c', $page->chdate);
                }

                $data['users'] = $page->getOnlineUsers();
            }
        }
        return $data;
    }

    private function getCoursewareClipboardUpdates($pageInfo)
    {
        if (!isset($pageInfo['counter'])) {
            return null;
        }

        $counter = $pageInfo['counter'] ?? 0;
        return \Courseware\Clipboard::countByUser_id($GLOBALS['user']->id) != $counter;
    }
}
