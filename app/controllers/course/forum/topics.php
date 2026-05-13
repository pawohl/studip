<?php

use Forum\Category;
use Forum\Subscription;
use Forum\Topic;

class Course_Forum_TopicsController extends Forum\BaseController
{
    public function before_filter(&$action, &$args): void
    {
        if ($action === 'show') {
            Request::set('topic_id', $args[0] ?? null);
        }
        
        parent::before_filter($action, $args);

        unset($_SESSION['forum'][$this->range_id]['search_filter']);

        Navigation::activateItem('course/forum/topics');
    }

    public function index_action(): void
    {
        $this->render_vue_app(
            Studip\VueApp::create('forum/topics/Index')
        );
    }

    public function show_action(Topic $topic): void
    {
        if (!$topic) {
            throw new NotFoundException();
        }

        PageLayout::setTitle($topic->name);

        $user_subscription = null;
        if ($this->user_id) {
            $user_subscription = Subscription::findOneBySQL(
                "subject = :subject AND subject_id = :subject_id AND user_id = :user_id",
                [
                    'subject' => 'topic',
                    'subject_id' => $topic->getId(),
                    'user_id' => $this->user_id
                ]
            );
        }

        $this->render_vue_app(
            Studip\VueApp::create('forum/topics/Show')
                ->withProps([
                    'topic' => $topic->transformData(),
                    'category' => $topic->category ? $topic->category->transformData() : [],
                    'userSubscription' => $user_subscription ? $user_subscription->toRawArray() : [],
                    'metadata' => [
                        'postings_count' => (int) $topic->metadata['postings_count'],
                        'users_count' => (int) $topic->metadata['users_count'],
                        'recent_activity' => date('c', $topic->metadata['recent_activity'])
                    ]
                ])
        );
    }

    public function edit_action($topic_id = null): void
    {
        if (!$this->is_moderator) {
            throw new AccessDeniedException();
        }

        if ($topic_id) {
            PageLayout::setTitle(_('Thema bearbeiten'));
            $topic = Topic::getCourseTopic($this->range_id, $topic_id);

            if (!$topic) {
                throw new AccessDeniedException();
            }
        } else {
            PageLayout::setTitle(_('Neues Thema anlegen'));
            $topic = new Topic();
            $topic['category_id'] = Request::get('category_id');
        }

        $categories = DBManager::get()->fetchAll(
            "SELECT * FROM `forum_categories` WHERE `range_id` = ? ORDER BY `position` ASC, `mkdate` DESC",
            [$this->range_id]
        );

        $this->render_vue_app(
            Studip\VueApp::create('forum/topics/Edit')
                ->withProps([
                    'topic' => $topic->transformData(),
                    'categories' => $categories
                ])
        );
    }

    public function save_action($topic_id = null): void
    {
        if (!$this->is_moderator) {
            throw new AccessDeniedException();
        }

        CSRFProtection::verifyUnsafeRequest();

        if ($topic_id) {
            $topic = Topic::getCourseTopic($this->range_id, $topic_id);
            if (!$topic) {
                throw new AccessDeniedException();
            }
        } else {
            $topic = new Topic();
            $topic->range_id = $this->range_id;
        }

        $category = json_decode(Request::get('category'), true);

        if (empty($category['category_id']) && !empty($category['name'])) {
            $newCategory = Category::create([
                'range_id' => $this->range_id,
                'color' => '#28497C',
                'name' => $category['name']
            ]);

            $category['category_id'] = $newCategory->category_id;
        } else {
            $topic->category_id = null;
        }

        if (!empty($category['category_id'])) {
            $topic->category_id = $category['category_id'];
        }

        $topic->name = Request::get('name');
        $topic->description = Request::get('description');

        $topic->store();

        PageLayout::postSuccess(_('Das Thema wurde gespeichert.'));

        $this->relocate('course/forum/topics/show/' . $topic->topic_id);
    }

    public function delete_action(Topic $topic): void
    {
        CSRFProtection::verifyUnsafeRequest();

        if (!$topic) {
            throw new NotFoundException();
        }

        if (!$this->is_moderator) {
            throw new AccessDeniedException();
        }

        $topic->delete();

        PageLayout::postSuccess(_('Das Thema wurde gelöscht.'));

        $this->relocate('course/forum/topics');
    }
}
