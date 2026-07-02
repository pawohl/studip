<?php
require_once 'settings.php';

class Settings_AvatarController extends Settings_SettingsController
{
    public function index_action(): void
    {
        PageLayout::setTitle(_('Profilbild anpassen'));
        Navigation::activateItem('/profile/edit/avatar');

        $this->user_id = $this->user->id;
        $avatar = Avatar::getAvatar($this->user->id);
        $this->avatar_url = $avatar->getURL(Avatar::NORMAL);
    }
}
