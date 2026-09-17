<?php
/**
 * SettingsController - Administration of all general user related
 * settings
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       2.4
 */

require_once 'settings.php';

class Settings_GeneralController extends Settings_SettingsController
{
    /**
     * Set up this controller.
     *
     * @param String $action Name of the action to be invoked
     * @param Array  $args   Arguments to be passed to the action method
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        PageLayout::setTitle(_('Allgemeine Einstellungen anpassen'));
        Navigation::activateItem('/profile/settings/general');
        $this->show_room_management_autor_config = Config::get()->RESOURCES_ENABLE
                                                && (
                                                    ResourceManager::userHasGlobalPermission($this->user, 'autor')
                                                    ||
                                                    RoomManager::userHasRooms($this->user, 'autor')
                                                );
    }

    /**
     * Displays the general settings of a user.
     */
    public function index_action()
    {
        $this->user_language = getUserLanguage($this->user->id);
        $this->notifications_placement = User::findCurrent()->getConfiguration()->SYSTEM_NOTIFICATIONS_PLACEMENT;
    }

    /**
     * Stores the general settings of a user.
     */
    public function store_action()
    {
        $this->check_ticket();

        $language = Request::get('forced_language');
        if (array_key_exists($language, $GLOBALS['INSTALLED_LANGUAGES'])) {
            $this->user->preferred_language = $language;
            if ($GLOBALS['user']->id === $this->user->id) {
                $_SESSION['_language'] = $language;
            }
            $this->user->store();
        }

        $this->config->store('PERSONAL_STARTPAGE', Request::int('personal_startpage'));
        $this->config->store('SHOWSEM_ENABLE', Request::int('showsem_enable'));
        $this->config->store('TOUR_AUTOSTART_DISABLE', Request::int('tour_autostart_disable'));
        if ($this->show_room_management_autor_config) {
            $this->config->store('RESOURCES_CONFIRM_PLAN_DRAG_AND_DROP', Request::int('resources_confirm_plan_drag_and_drop'));
        }

        if (Config::get()->getValue('PERSONAL_NOTIFICATIONS_ACTIVATED')) {
            if (Request::int('personal_notifications_activated')) {
                PersonalNotifications::activate($this->user->id);
            } else {
                PersonalNotifications::deactivate($this->user->id);
            }
        }

        $this->config->store('SYSTEM_NOTIFICATIONS_PLACEMENT', Request::get('system_notifications_placement'));

        PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));
        $this->redirect('settings/general');
    }
}
