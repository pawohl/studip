<?php
namespace Forum;

use ActionsWidget;
use Context;
use CoreForum;
use Icon;
use Request;
use SearchWidget;
use Sidebar;
use StudipController;
use User;

abstract class BaseController extends StudipController
{
    protected $with_session = true;
    protected $is_admin = false;
    protected $is_moderator = false;

    public function before_filter(&$action, &$args): void
    {
        object_set_visit_module('forum');

        $this->range_id = Context::getId();
        $this->user_id = User::findCurrent()?->user_id;

        if ($this->user_id) {
            $this->is_admin = CoreForum::isAdmin($this->range_id);
            $this->is_moderator = CoreForum::isModerator($this->range_id);
        }

        $this->buildSidebar();

        \Helpbar::Get()->addPlainText('', _('Neue Diskussionen starten Sie per Plus-Symbol, Antworten und Zitieren '
            . 'sind direkt möglich. Abonnements und Suche helfen, den Überblick zu behalten. Diskussionen lassen '
            . 'sich übersichtlich nach Themen und Kategorien ordnen, neueste Beiträge finden Sie dort in der Spalte '
            . 'Letzte Aktivität.'));
        \PageLayout::setHelpKeyword('Basis.Forum3');

        parent::before_filter($action, $args);
    }

    protected function buildSidebar(): void
    {
        $actions = new ActionsWidget();


        if ($this->user_id) {
            $actions->addLink(
                _('Neue Diskussion starten'),
                $this->url_for('course/forum/discussions/edit', ['topic_id' => Request::option('topic_id')]),
                Icon::create('add', Icon::ROLE_CLICKABLE, ['title' => _('Neue Diskussion starten')])
            )->asDialog('width=900;height=750');
        }

        if ($this->is_admin) {
            $actions->addLink(
                _('Forum verwalten'),
                $this->url_for('course/forum/configs/edit'),
                Icon::create('admin', Icon::ROLE_CLICKABLE, ['title' => _('Forum verwalten')])
            )->asDialog('width=500;height=350');
        }

        Sidebar::Get()->addWidget($actions);

        $search = new SearchWidget($this->url_for('course/forum/search', [
            'begin' => Request::int('begin'),
            'end' => Request::int('end')
        ]));

        $search->addNeedle(
            _('Suche nach Diskussionen oder Beiträge'),
            'q',
            true
        );

        Sidebar::Get()->addWidget($search, 'forum_search');
    }
}
