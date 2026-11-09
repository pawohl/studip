<?php
# Lifter007: TODO
# Lifter003: TODO
# Lifter010: TODO
/*
 * ilias_interface.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Arne Schroeder <schroeder@data-quest.de>
 * @copyright   2018 Suchi & Berg GmbH <info@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
*/
class Admin_IliasInterfaceController extends AuthenticatedController
{
    /**
     * Before filter, set up the page by initializing the session and checking
     * all conditions.
     *
     * @param String $action Name of the action to be invoked
     * @param Array  $args   Arguments to be passed to the action method
     */
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (!$GLOBALS['perm']->have_perm('root')) {
            throw new AccessDeniedException();
        }

        // check if interface is active
        if (!Config::Get()->ILIAS_INTERFACE_ENABLE ) {
            throw new AccessDeniedException(_('Ilias-Interface ist nicht aktiviert.'));
        } else {
            $this->ilias_active = true;
        }

        // get basic settings
        $this->ilias_interface_config = Config::get()->getValue('ILIAS_INTERFACE_BASIC_SETTINGS');
        if (!is_array($this->ilias_interface_config)) {
            throw new AccessDeniedException(_('ILIAS-Grundeinstellungen nicht gefunden.'));
        }
        $this->ilias_interface_moduletitle = Config::get()->getValue('ILIAS_INTERFACE_MODULETITLE');

        // get ILIAS installation settings
        $this->ilias_configs = Config::get()->getValue('ILIAS_INTERFACE_SETTINGS');
        PageLayout::setHelpKeyword('Basis.Ilias');

        $this->modules_available = ConnectedIlias::getSupportedModuleTypes();
        $this->studip_roles = ['autor', 'tutor', 'dozent', 'admin', 'root'];

        $this->sidebar = Sidebar::get();
    }

    /**
     * Displays connected ILIAS installations
     */
    public function index_action()
    {
        Navigation::activateItem('admin/config/ilias_interface');

        PageLayout::setTitle(_('Verwaltung der ILIAS-Schnittstelle'));

        $widget = new ActionsWidget();

        $widget->addLink(
                _('Schnittstelle konfigurieren'),
                $this->url_for('admin/ilias_interface/edit_interface_settings'),
                Icon::create('admin'),
                ['data-dialog' => 'size=auto']
                );
        $widget->addLink(
                _('ILIAS-Installation hinzufügen'),
                $this->url_for('admin/ilias_interface/edit_server/new'),
                Icon::create('add'),
                ['data-dialog' => 'size=auto']
                );
        $this->sidebar->addWidget($widget);
    }

    /**
     * edit ILIAS interface basic settings
     */
    public function edit_interface_settings_action()
    {
    }

    /**
     * save ILIAS interface basic settings
     */
    public function save_interface_settings_action()
    {
        if (Request::submitted('submit')) {
            $this->ilias_interface_config['edit_moduletitle'] = Request::bool('ilias_interface_edit_moduletitle', false);
            $this->ilias_interface_config['show_offline'] = Request::bool('ilias_interface_show_offline', false);
            $this->ilias_interface_config['search_active'] = Request::bool('ilias_interface_search_active', false);
            $this->ilias_interface_config['show_course_paths'] = Request::bool('ilias_interface_show_course_paths', false);
            $this->ilias_interface_config['show_tools_page'] = Request::bool('ilias_interface_show_tools_page', false);
            $this->ilias_interface_config['add_statusgroups'] = Request::bool('ilias_interface_add_statusgroups', false);
            $this->ilias_interface_config['cache'] = Request::bool('ilias_interface_cache', false);
            $this->ilias_interface_config['allow_change_course'] = Request::get('ilias_interface_allow_change_course');
            $this->ilias_interface_config['allow_add_own_course'] = Request::get('ilias_interface_allow_add_own_course');
            $this->ilias_interface_config['create_category'] = Request::bool('ilias_interface_create_category', false);
            $this->ilias_interface_config['create_objects'] = Request::bool('ilias_interface_create_objects', false);

            //store config entry
            Config::get()->store('ILIAS_INTERFACE_BASIC_SETTINGS', $this->ilias_interface_config);
            Config::get()->store('ILIAS_INTERFACE_MODULETITLE', Request::get('ilias_interface_moduletitle'));
            PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));
        }
        $this->redirect($this->url_for('admin/ilias_interface'));
    }

    /**
     * add/edit connected ILIAS server settings
     * @param $index Index of ILIAS settings
     */
    public function edit_server_action($index)
    {
        $this->valid_url = false;
        $this->ilias_version = '';
        $this->ilias_version_date = '';
        $this->ilias_clients = [];

        // default values
        $ilias = new ConnectedIlias($index);
        $this->ilias_config = $ilias->ilias_config;

        if ($index === 'new') {

            // fetch existing indicies from previously connected ILIAS installations
            $this->existing_indices = ConnectedIlias::getExistingIndices();

            foreach ($this->ilias_configs as $ilias_index => $ilias_config) {
                unset($this->existing_indices[$ilias_index]);
            }

            // get ILIAS server info
            if (Request::get('ilias_url')) {
                $info = ConnectedIlias::getIliasInfo(Request::get('ilias_url'));
                if (is_array($info) && count($info)) {
                    $this->valid_url = true;
                    $this->ilias_config['url'] = rtrim(Request::get('ilias_url'), '/');
                    $this->ilias_config['http_connection_timeout'] = (int) Request::get('ilias_http_connection_timeout');
                    $this->ilias_config['http_request_timeout'] = (int) Request::get('ilias_http_request_timeout');
                    if ($info['version']) {
                        $this->ilias_version = $info['version'];
                        $this->ilias_version_date = $info['version_date'];
                        $this->ilias_clients = $info['clients'];
                    } else {
                        $this->ilias_version = '';
                    }
                    if (Request::get('ilias_index') !== 'new') {
                        // use data from previously connected ILIAS
                        $index = Request::get('ilias_index');
                    } else {
                        // read existing indices
                        $this->existing_indices = ConnectedIlias::getExistingIndices();
                        foreach ($this->ilias_configs as $ilias_index => $ilias_config) {
                            unset($this->existing_indices[$ilias_index]);
                        }
                        // find new unique index
                        $index = 'ilias'.ConnectedIlias::getIntVersion($this->ilias_version);
                        if (!empty($this->ilias_configs[$index]) || !empty($this->existing_indices[$index])) {
                            $i = 1;
                            while (!empty($this->ilias_configs[$index.'-'.$i]) || !empty($this->existing_indices[$index.'-'.$i])) {
                                $i++;
                            }
                            $index = $index.'-'.$i;
                        }
                    }
                } else {
                    PageLayout::postError(sprintf(_('Die URL "%s" ist nicht erreichbar.'), htmlReady(Request::get('ilias_url'))));
                }
                if (Request::get('ilias_name') || ! $this->valid_url) {
                    $this->ilias_config['name'] = Request::get('ilias_name');
                } else {
                    $this->valid_url = false;
                    PageLayout::postError(_('Name der Installation darf nicht leer sein.'));
                }
            }
        } else {
            $this->valid_url = true;
            $this->ilias_config = $this->ilias_configs[$index];
            $ldap_options = [];
            foreach (StudipAuthAbstract::GetInstance() as $plugin) {
                if (!($plugin instanceof StudipAuthStandard)) {
                    $ldap_options[] = '<option '.($plugin->plugin_name == $this->ilias_config['ldap_enable'] ? 'selected' : '').'>' . $plugin->plugin_name . '</option>';
                }
            }
            $this->ldap_options = count($ldap_options) ? join("\n", array_merge(['<option></option>'], $ldap_options)) : '';
            if (Request::get('ilias_name')) {
                $this->ilias_config['name'] = Request::get('ilias_name');
                $this->ilias_config['url'] = Request::get('ilias_url');
                $this->ilias_config['http_connection_timeout'] = (int) Request::get('ilias_http_connection_timeout');
                $this->ilias_config['http_request_timeout'] = (int) Request::get('ilias_http_request_timeout');
            }
            $info = ConnectedIlias::getIliasInfo($this->ilias_config['url']);
            if (count($info)) {
                if ($info['version']) {
                    $this->ilias_version = $info['version'];
                    $this->ilias_version_date = $info['version_date'];
                    $this->ilias_clients = $info['clients'] ?? [];
                } else {
                    PageLayout::postInfo(_('ILIAS-Version und Clients konnten nicht automatisch erkannt werden.'));
                }
            }
        }
        $this->ilias_index = $index;
    }

    /**
     * edit connected ILIAS content settings
     * @param $index Index of ILIAS settings
     */
    public function edit_content_action($index)
    {
        $this->ilias_index = $index;
        $this->ilias_datafields = [];

        $connected_ilias = new ConnectedIlias($index);
        $this->ilias_config = $connected_ilias->ilias_config;

        if ($admin_id = $connected_ilias->soap_client->lookupUser($this->ilias_config['admin'])) {
            $user = $connected_ilias->soap_client->getUser($admin_id);
            if (!empty($user) && array_key_exists('udfs', $user)) {
                $this->ilias_datafields = $user['udfs'];
            }
        }
    }

    /**
     * edit connected ILIAS permissions settings
     * @param string $index Index of ILIAS settings
     */
    public function edit_permissions_action($index)
    {
        $this->ilias_index = $index;

        $connected_ilias = new ConnectedIlias($index);
        $this->ilias_config = $connected_ilias->ilias_config;
        $this->global_roles = $connected_ilias->soap_client->getRoles('global', -1);
    }

    /**
     * Deletes given ILIAS settings from configuration
     * @param string $index Index of ILIAS settings
     */
    public function delete_action($index)
    {
        CSRFProtection::verifyUnsafeRequest();

        unset($this->ilias_configs[$index]);
        Config::get()->store('ILIAS_INTERFACE_SETTINGS', $this->ilias_configs);
        PageLayout::postSuccess(_('ILIAS-Konfiguration wurde entfernt.'));

        $this->redirect($this->url_for('admin/ilias_interface'));
    }

    /**
     * Save connected ILIAS installation settings
     * @param string $index Index of ILIAS settings
     */
    public function save_action($index)
    {
        CSRFProtection::verifyUnsafeRequest();

        if (Request::submittedSome('submit', 'add_additional_role', 'remove_additional_role')) {
            $connected_ilias = new ConnectedIlias($index);
            $this->ilias_configs[$index] = $connected_ilias->ilias_config;
            // set basic server settings
            if (Request::getInstance()->offsetExists('ilias_name')) {
                $this->ilias_configs[$index]['name'] = Request::get('ilias_name');
                if (Request::getInstance()->offsetExists('ilias_version')) {
                    $this->ilias_configs[$index]['version'] = Request::get('ilias_version');
                }
                $this->ilias_configs[$index]['url'] = rtrim(Request::get('ilias_url'), '/');
                $this->ilias_configs[$index]['http_connection_timeout'] = (int) Request::get('ilias_http_connection_timeout');
                $this->ilias_configs[$index]['http_request_timeout'] = (int) Request::get('ilias_http_request_timeout');
                if (Request::getInstance()->offsetExists('ilias_client')) {
                    $this->ilias_configs[$index]['client'] = Request::get('ilias_client');
                }
                if (Request::getInstance()->offsetExists('ilias_ldap_enable')) {
                    $this->ilias_configs[$index]['ldap_enable'] = Request::get('ilias_ldap_enable');
                }
                $this->ilias_configs[$index]['no_account_updates'] = Request::get('ilias_no_account_updates');
                $this->ilias_configs[$index]['admin'] = Request::get('ilias_admin');
                $this->ilias_configs[$index]['admin_pw'] = Request::get('ilias_admin_pw');

                //store config entry
                Config::get()->store('ILIAS_INTERFACE_SETTINGS', $this->ilias_configs);
                PageLayout::postSuccess(_('ILIAS-Servereinstellungen wurden gespeichert.'));
            }

            // check stored configuration
            $connected_ilias = new ConnectedIlias($index);
            if ($connected_ilias->getConnectionSettingsStatus()) {
                // set content settings
                if (Request::getInstance()->offsetExists('ilias_content_settings')) {
                    if (Request::get('ilias_root_category_name')) {
                        $this->ilias_configs[$index]['root_category_name'] = Request::get('ilias_root_category_name');
                    }
                    if (Request::getInstance()->offsetExists('ilias_user_prefix')) {
                        $this->ilias_configs[$index]['user_prefix'] = Request::get('ilias_user_prefix');
                    }
                    if (Request::getInstance()->offsetExists('ilias_matriculation')) {
                        $this->ilias_configs[$index]['matriculation'] = Request::get('ilias_matriculation');
                    }
                    if (Request::getInstance()->offsetExists('ilias_discipline_1') && Request::getInstance()->offsetExists('ilias_discipline_2')) {
                        if ($admin_id = $connected_ilias->soap_client->lookupUser($this->ilias_configs[$index]['admin'])) {
                            $user = $connected_ilias->soap_client->getUser($admin_id);
                            if (array_key_exists('udfs', $user)) {
                                $this->ilias_datafields = $user['udfs'];
                                foreach ($this->ilias_datafields as $field) {
                                    if (Request::option('ilias_discipline_1') == $field['id']) {
                                        $this->ilias_configs[$index]['discipline_1'] = ['id' => $field['id'], 'name' => $field['name']];
                                    }
                                    if (Request::option('ilias_discipline_2') == $field['id']) {
                                        $this->ilias_configs[$index]['discipline_2'] = ['id' => $field['id'], 'name' => $field['name']];
                                    }
                                }
                                if (!Request::option('ilias_discipline_1')) {
                                    unset($this->ilias_configs[$index]['discipline_1']);
                                }
                                if (!Request::option('ilias_discipline_2')) {
                                    unset($this->ilias_configs[$index]['discipline_2']);
                                }
                            }
                        }
                    }
                    if (Request::getInstance()->offsetExists('ilias_cat_semester')) {
                        $this->ilias_configs[$index]['cat_semester'] = Request::get('ilias_cat_semester');
                    }
                    $this->ilias_configs[$index]['cat_faculty'] = Request::get('ilias_cat_faculty');
                    if (Request::getInstance()->offsetExists('ilias_course_semester')) {
                        $this->ilias_configs[$index]['course_semester'] = Request::get('ilias_course_semester');
                    }
                    if (Request::getInstance()->offsetExists('ilias_course_veranstaltungsnummer')) {
                        $this->ilias_configs[$index]['course_veranstaltungsnummer'] = Request::get('ilias_course_veranstaltungsnummer');
                    }
                    $this->ilias_configs[$index]['delete_ilias_users'] = Request::get('ilias_delete_ilias_users');
                    $this->ilias_configs[$index]['reconnect_accounts'] = Request::bool('ilias_reconnect_accounts', false);
                    $this->ilias_configs[$index]['delete_ilias_courses'] = Request::get('ilias_delete_ilias_courses');
                    $this->ilias_configs[$index]['category_create_on_add_module'] = Request::get('ilias_category_create_on_add_module');
                    $this->ilias_configs[$index]['category_to_desktop'] = Request::get('ilias_category_to_desktop');
                    $this->ilias_configs[$index]['workgroup_category_name'] = Request::get('ilias_workgroup_category_name');

                    if (!empty($this->ilias_interface_config['create_objects'])) {
                        foreach ($this->modules_available as $module_index => $module_name) {
                            if (Request::get('ilias_modules_'.$module_index)) {
                                $this->ilias_configs[$index]['modules'][$module_index] = $module_name;
                            } else {
                                unset($this->ilias_configs[$index]['modules'][$module_index]);
                            }
                        }
                    }

                    //store config entry
                    Config::get()->store('ILIAS_INTERFACE_SETTINGS', $this->ilias_configs);
                    PageLayout::postSuccess(_('ILIAS-Inhaltseinstellungen wurden gespeichert.'));

                    //check stored configuration
                    $connected_ilias->loadSettings();
                    $connected_ilias->getContentSettingsStatus();
                }

                // set permissions settings
                if (Request::getInstance()->offsetExists('ilias_author_role_name')) {
                    $this->global_roles = $connected_ilias->soap_client->getRoles('global', -1);
                    $this->ilias_configs[$index]['author_role_name'] = Request::get('ilias_author_role_name');
                    $this->ilias_configs[$index]['author_perm'] = Request::get('ilias_author_perm');
                    $this->ilias_configs[$index]['allow_change_account'] = Request::get('ilias_allow_change_account');
                    $this->ilias_configs[$index]['workgroup_role_name'] = Request::get('ilias_workgroup_role_name');
                    $this->ilias_configs[$index]['workgroup_perm'] = Request::get('ilias_workgroup_perm');

                    // remove ilias role assignment
                    if (
                        Request::submitted('remove_additional_role')
                        && Request::option('studip_role')
                        && array_key_exists('additional_roles', $this->ilias_configs[$index])
                    ) {
                        $studip_role = Request::option('studip_role');
                        $ilias_role = Request::option('remove_additional_role');
                        if (
                            in_array($studip_role, $this->studip_roles)
                            && array_key_exists($studip_role, $this->ilias_configs[$index]['additional_roles'])
                            && array_key_exists($ilias_role, $this->ilias_configs[$index]['additional_roles'][$studip_role])
                        ) {
                            unset($this->ilias_configs[$index]['additional_roles'][$studip_role][$ilias_role]);
                            PageLayout::postSuccess(sprintf(_('ILIAS-Rollenzuweisung der Stud.IP-Rechtestufe %s wurde entfernt.'), $studip_role));
                        }
                    }

                    // add ilias role assignment
                    if (
                        Request::submitted('add_additional_role')
                        && Request::option('add_studip_role')
                        && Request::option('add_ilias_role')
                    ) {
                        $studip_role = Request::option('add_studip_role');
                        $ilias_role = Request::option('add_ilias_role');
                        $role_already_assigned = false;
                        if (!array_key_exists('additional_roles', $this->ilias_configs[$index])) {
                            $this->ilias_configs[$index]['additional_roles'] = [];
                        }
                        if (
                            in_array($studip_role, $this->studip_roles)
                            && (array_key_exists($ilias_role, $this->global_roles))
                        ) {
                            if (!array_key_exists($studip_role, $this->ilias_configs[$index]['additional_roles'])) {
                                $this->ilias_configs[$index]['additional_roles'][$studip_role] = [];
                            }
                            if (array_key_exists($ilias_role, $this->global_roles)) {
                                $this->ilias_configs[$index]['additional_roles'][$studip_role][$ilias_role] = [
                                    'id' => $this->global_roles[$ilias_role]['id'],
                                    'name' => $this->global_roles[$ilias_role]['name']];
                                PageLayout::postSuccess(sprintf(_('ILIAS-Rolle %s wird Stud.IP-Rechtestufe %s zugewiesen.'), $this->global_roles[$ilias_role]['name'], $studip_role));
                            } else {
                                PageLayout::postError(_('ILIAS-Rolle nicht gefunden.'));
                            }
                        }
                    }

                    //store config entry
                    Config::get()->store('ILIAS_INTERFACE_SETTINGS', $this->ilias_configs);
                    PageLayout::postSuccess(_('ILIAS-Berechtigungseinstellungen wurden gespeichert.'));

                    //check stored configuration
                    $connected_ilias->loadSettings();
                    $connected_ilias->getPermissionsSettingsStatus();
                }
            }
        }
        if (isset($connected_ilias)) { // show error messages
            foreach ($connected_ilias->getError() as $error) {
                PageLayout::postError($error);
            }
        }
        $this->redirect($this->url_for('admin/ilias_interface'));
    }

    /**
     * Activate connected ILIAS installation
     * @param $index Index of ILIAS settings
     */
    public function activate_action($index)
    {
        $this->ilias_configs[$index]['is_active'] = true;

        //check stored configuration
        $connected_ilias = new ConnectedIlias($index);
        if ($connected_ilias->getConnectionSettingsStatus() && $connected_ilias->getContentSettingsStatus() && $connected_ilias->getPermissionsSettingsStatus()) {
            //store config entry
            Config::get()->store('ILIAS_INTERFACE_SETTINGS', $this->ilias_configs);
            PageLayout::postSuccess(_('ILIAS-Installation aktiviert.'));
        }

        // show error messages
        foreach ($connected_ilias->getError() as $error) {
            PageLayout::postError($error);
        }

        $this->redirect($this->url_for('admin/ilias_interface'));
    }

    /**
     * Deactivate connected ILIAS installation
     * @param $index Index of ILIAS settings
     */
    public function deactivate_action($index)
    {
        $this->ilias_configs[$index]['is_active'] = false;

        //store config entry
        Config::get()->store('ILIAS_INTERFACE_SETTINGS', $this->ilias_configs);
        PageLayout::postSuccess(_('ILIAS-Installation deaktiviert.'));

        $this->redirect($this->url_for('admin/ilias_interface'));
    }

    /**
     * test soap methods
     * @param $index Index of ILIAS settings
     */
    public function soap_methods_action($index)
    {
        $ilias = new ConnectedIlias($index);

        $this->soap_methods = $ilias->getSoapMethods();
        ksort($this->soap_methods);
        $this->ilias_index = $index;
        if (Request::get('ilias_soap_method')) {
            $this->ilias_soap_method = Request::get('ilias_soap_method');
            foreach ($this->soap_methods[Request::get('ilias_soap_method')] as $param) {
                switch ($param) {
                    case "sid" : $this->params[$param] = $ilias->soap_client->getSID();
                    break;
                    case "user_id" : $this->params[$param] = is_object($ilias->user) ? $ilias->user->getId() : '';
                    break;
                }
            }
        } elseif (Request::get('ilias_call')) {
            $params = [];
            foreach ($this->soap_methods[Request::get('ilias_call')] as $param) {
                if ($param === 'user_ids') {
                    $params[$param] = [Request::get('ilias_soap_param_'.$param)];
                } else {
                    $params[$param] = Request::get('ilias_soap_param_'.$param);
                }
            }
            $this->result = $ilias->soap_client->call(Request::get('ilias_call'), $params);
        }
    }
}
