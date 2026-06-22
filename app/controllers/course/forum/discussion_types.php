<?php
use Forum\DiscussionType;

class Course_Forum_DiscussionTypesController extends AuthenticatedController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        PageLayout::setTitle(_('Forum Diskussions-Typ'));

        $GLOBALS['perm']->check('root');

        Navigation::activateItem('/admin/locations/forum_discussion_types');

        $actions = new ActionsWidget();

        $actions->addLink(
            _('Neuen Diskussionstyp anlegen'),
            $this->url_for('course/forum/discussion_types/edit'),
            Icon::create('add')
        )->asDialog('width=700;height=650');

        Sidebar::Get()->addWidget($actions);
    }

    public function index_action(): void
    {
        $this->render_vue_app(
            Studip\VueApp::create('forum/discussions_types/Index')
        );
    }

    public function edit_action(?DiscussionType $discussionType = null): void
    {
        if ($discussionType->isNew()) {
            PageLayout::setTitle(_('Neuen Diskussionstyp anlegen'));
        } else {
            PageLayout::setTitle(_('Diskussionstyp bearbeiten'));
        }

        $icons = [];

        foreach (scandir($GLOBALS['STUDIP_BASE_PATH'] . '/public/assets/images/icons/blue') as $icon_path) {
            if (!is_dir(
                    $GLOBALS['STUDIP_BASE_PATH'] . '/public/assets/images/icons/blue/'
                ) . $icon_path && $icon_path[0] !== '.') {
                if (stripos($icon_path, '.svg')) {
                    $icon_path = substr($icon_path, 0, stripos($icon_path, '.svg'));
                }
                $icons[] = $icon_path;
            }
        }

        $this->render_vue_app(
            Studip\VueApp::create('forum/discussions_types/Edit')
                ->withProps([
                    'icons' => array_unique($icons),
                    'discussionType' => $discussionType->transformData()
                ])
        );
    }

    public function save_action(?DiscussionType $discussionType = null): void
    {
        CSRFProtection::verifyUnsafeRequest();

        $discussionType->name = Request::get('name');
        $discussionType->icon = Request::get('icon');

        $discussionType->store();

        PageLayout::postSuccess(sprintf(_('Der Diskussionstyp „%s“ wurde gespeichert.'), $discussionType->name));

        $this->relocate('course/forum/discussion_types/index');
    }
}
