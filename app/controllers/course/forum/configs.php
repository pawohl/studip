<?php

class Course_Forum_ConfigsController extends Forum\BaseController
{
    public function before_filter(&$action, &$args): void
    {
        parent::before_filter($action, $args);

        if (!$this->user_id) {
            throw new LoginException();
        }

        if (! $this->is_admin) {
            throw new AccessDeniedException();
        }
    }

    public function edit_action(): void
    {
        $config = Context::get()->getConfiguration();

        $this->render_vue_app(
            Studip\VueApp::create('forum/configs/Edit')
                ->withProps([
                    'config' => [
                        'moderator' => $config->FORUM_MODERATION_PERMISSION,
                        'isCategoriesHidden' => $config->FORUM_HIDE_CATEGORIES
                    ]
                ])
        );
    }

    public function save_action(): void
    {
        CSRFProtection::verifyUnsafeRequest();

        $config = Context::get()->getConfiguration();

        $config->store('FORUM_MODERATION_PERMISSION', trim(Request::option('moderator')));

        $config->store('FORUM_HIDE_CATEGORIES', Request::bool('is_categories_hidden'));

        PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));

        $this->relocate('course/forum/topics');
    }
}
