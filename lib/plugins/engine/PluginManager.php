<?php
/**
 * PluginManager.php - plugin manager for Stud.IP
 *
 * @copyright  2009  Elmar Ludwig
 * @license GPL2 or any later version
 *
 * @template P of StudIPPlugin
 */

class PluginManager
{
    /**
     * meta data of installed plugins
     */
    private $plugins;

    /**
     * cache of created plugin instances
     */
    private $plugin_cache;

    /**
     * cache of activated plugins by context
     */
    private $plugins_activated_cache;

    /**
     * Returns the PluginManager singleton instance.
     */
    public static function getInstance ()
    {
        static $instance;

        if (isset($instance)) {
            return $instance;
        }

        return $instance = new PluginManager();
    }

    /**
     * Initialize a new PluginManager instance.
     */
    private function __construct ()
    {
        $this->readPluginInfos();
        $this->plugin_cache = [];

        $this->plugins_activated_cache = new StudipCachedArray('/PluginActivations');
    }

    /**
     * Read meta data for all plugins registered in the data base.
     */
    private function readPluginInfos ()
    {
        $db = DBManager::get();
        $this->plugins = [];

        $result = $db->query('SELECT * FROM plugins ORDER BY pluginname');

        foreach ($result as $plugin) {
            $id = (int) $plugin['pluginid'];

            $this->plugins[$id] = [
                'id'                      => $id,
                'name'                    => $plugin['pluginname'],
                'class'                   => $plugin['pluginclassname'],
                'path'                    => $plugin['pluginpath'],
                'type'                    => explode(',', $plugin['plugintype']),
                'enabled'                 => $plugin['enabled'] === 'yes',
                'position'                => $plugin['navigationpos'],
                'depends'                 => (int) $plugin['dependentonid'],
                'core'                    => $plugin['pluginpath'] === '',
                'automatic_update_url'    => $plugin['automatic_update_url'],
                'automatic_update_secret' => $plugin['automatic_update_secret'],
                'description'             => $plugin['description'] ?? '',
                'description_mode'        => $plugin['description_mode'] ?? null,
                'highlight_until'         => $plugin['highlight_until'] ?? null,
                'highlight_text'          => $plugin['highlight_text'] ?? null,
            ];
        }
    }

    /**
     * @addtogroup notifications
     *
     * Enabling or disabling a plugin triggers a PluginDidEnable or
     * respectively PluginDidDisable notification. The plugin's ID
     * is transmitted as subject of the notification.
     */
    /**
     * Set the enabled/disabled status of the given plugin.
     *
     * If the plugin implements the method "onEnable" or "onDisable", this
     * method will be called accordingly. If the method returns false or
     * throws and exception, the plugin's activation state is not updated.
     *
     * @param int $id        id of the plugin
     * @param bool   $enabled   plugin status (true or false)
     * @param bool   $force     force (de)activation regardless of the result
     *                           of on(en|dis)able
     * @return bool  indicating whether the plugin was updated or null if the
     *               passed state equals the current state or if the plugin is
     *               missing.
     */
    public function setPluginEnabled ($id, $enabled, $force = false)
    {
        $info = $this->getPluginInfoById($id);
        $plugin_class = null;

        // Plugin is not present or no changes
        if (!$info || $info['enabled'] == $enabled) {
            return null;
        }

        if ($info['core'] || !$this->isPluginsDisabled()) {
            $plugin_class = $this->loadPlugin($info['class'], $info['path']);
        }

        if ($plugin_class) {
            $method = $enabled ? 'onEnable' : 'onDisable';
            $result = $plugin_class->getMethod($method)->invoke(null, $id);

            // if callback returns false, don't enable or disable the plugin
            if ($result === false && !$force) {
                return false;
            }
        }

        // Update plugin
        $state = $enabled ? 'yes' : 'no';

        $query = "UPDATE plugins SET enabled = ? WHERE pluginid = ?";
        DBManager::get()->execute($query, [$state, $id]);

        $this->plugins[$id]['enabled'] = (boolean) $enabled;

        NotificationCenter::postNotification(
            $enabled ? 'PluginDidEnable' : 'PluginDidDisable',
            $id
        );

        return true;
    }

    /**
     * Set the navigation position of the given plugin.
     *
     * @param int $id id of the plugin
     * @param int $position plugin navigation position
     * @return bool indicating whether any change occured
     */
    public function setPluginPosition ($id, $position)
    {
        $info = $this->getPluginInfoById($id);
        $position = (int) $position;

        if (!$info || $info['position'] == $position) {
            return false;
        }

        $query = "UPDATE plugins SET navigationpos = ? WHERE pluginid = ?";
        DBManager::get()->execute($query, [$position, $id]);

        $this->plugins[$id]['position'] = $position;
        $this->readPluginInfos();

        return true;
    }

    /**
     * Get the activation status of a plugin in the given context.
     * This also checks the plugin default activations and sem_class-settings.
     *
     * @param int $id  id of the plugin
     * @param string $context range id
     * @returns bool
     */
    public function isPluginActivated ($id, $context)
    {
        if (!$context) {
            return null;
        }
        if (!isset($this->plugins_activated_cache[$context])) {
            $query = "SELECT plugin_id, 1 as state
                      FROM tools_activated
                      WHERE range_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$context]);
            $this->plugins_activated_cache[$context] = $statement->fetchGrouped(PDO::FETCH_COLUMN);
        }
        return isset($this->plugins_activated_cache[$context][$id]);
    }

    /**
     * Get the activation status of a plugin for the given user.
     * This also checks the plugin default activations and sem_class-settings.
     *
     * @param int $pluginId id of the plugin
     * @param string $userId id of the user
     */
    public function isPluginActivatedForUser($pluginId, $userId)
    {
        if (!$userId) {
            $userId = $GLOBALS['user']->id;
        }
        if (!isset($this->plugins_activated_cache[$userId])) {
            $query = "SELECT pluginid, state
                      FROM plugins_activated
                      WHERE range_type = 'user'
                        AND range_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$userId]);
            $this->plugins_activated_cache[$userId] = $statement->fetchGrouped(PDO::FETCH_COLUMN);
        }
        $state = $this->plugins_activated_cache[$userId][$pluginId] ?? null;
        if ($state === null) {
            $activated = (bool) Config::get()->HOMEPAGEPLUGIN_DEFAULT_ACTIVATION;
        } else {
            $activated = (bool) $state;
        }

        return $activated;
    }

    /**
     * Sets the activation status of a plugin in the given context.
     *
     * @param int $id id of the plugin
     * @param string $rangeId context range id
     * @param bool $active plugin status (true or false)
     */
    public function setPluginActivated ($id, $rangeId, $active)
    {
        unset($this->plugins_activated_cache[$rangeId]);
        $activation = ToolActivation::find([$rangeId, $id]);
        if (!$activation) {
            $range = get_object_by_range_id($rangeId);
            $activation = new ToolActivation();
            $activation->range_id = $rangeId;
            $activation->plugin_id = $id;
            $activation->range_type = $range->getRangeType();
        }
        $plugin = $this->getPluginById($id);

        if ($active) {
            call_user_func([get_class($plugin), 'onActivation'], $id, $rangeId);
            StudipLog::log('PLUGIN_ENABLE', $rangeId, $id, User::findCurrent()->id);
            NotificationCenter::postNotification('PluginDidActivate', $rangeId, $id);
            return $activation->store();
        } else {
            call_user_func([get_class($plugin), 'onDeactivation'], $id, $rangeId);
            StudipLog::log('PLUGIN_DISABLE', $rangeId, $id, User::findCurrent()->id);
            NotificationCenter::postNotification('PluginDidDeactivate', $rangeId, $id);
            return $activation->delete();
        }
    }

    /**
     * Sets the activation status of a plugin in the given context.
     *
     * @param int $pluginid id of the plugin
     * @param string $user_id user id
     * @param bool $active plugin status (true or false)
     */
    public function setPluginActivatedForUser($pluginid, $user_id, $active)
    {
        $db = DBManager::get();
        $state = $active ? 1 : 0;
        unset($this->plugins_activated_cache[$user_id]);

        $query = "REPLACE INTO plugins_activated (pluginid, range_type, range_id, state)
                  VALUES (?, 'user', ?, ?)";
        $result = $db->execute($query, [$pluginid, $user_id, $state]);

        if ($result > 0) {
            $plugin = $this->getPluginById($pluginid);
            if ($active) {
                call_user_func([get_class($plugin), 'onActivation'], $pluginid, $user_id);
            } else {
                call_user_func([get_class($plugin), 'onDeactivation'], $pluginid, $user_id);
            }
        }

        return $result;
    }

    /**
     * Deactivate all plugins for the given range.
     *
     * @param  string $range_type Type of range (sem, inst or user)
     * @param  string $range_id   Id of range
     * @return int number of deactivated/removed plugins for range
     */
    public function deactivateAllPluginsForRange($range_type, $range_id)
    {
        unset($this->plugins_activated_cache[$range_id]);

        $query = "DELETE FROM `plugins_activated`
                  WHERE `range_type` = :range_type
                    AND `range_id` = :range_id";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':range_type', $range_type);
        $statement->bindValue(':range_id', $range_id);
        $statement->execute();

        return $statement->rowCount();
    }

    /**
     * Disable loading of all non-core plugins for the current session.
     *
     * @param bool $status true: disable non-core plugins
     */
    public function setPluginsDisabled($status)
    {
        $_SESSION['plugins_disabled'] = (bool) $status;
    }

    /**
     * Check whether loading of non-core plugins is currently disabled.
     */
    public function isPluginsDisabled()
    {
        return $_SESSION['plugins_disabled'] ?? false;
    }

    /**
     * Load a plugin class from the given file system path and
     * return the ReflectionClass instance for the plugin.
     *
     * @param string $class plugin class name
     * @param string $path plugin relative path
     */
    private function loadPlugin ($class, $path)
    {
        if ($path) {
            $basepath = Config::get()->PLUGINS_PATH;
        } else {
            $basepath = $GLOBALS['STUDIP_BASE_PATH'];
            $path = 'lib/modules';
        }

        $pluginfile = $basepath.'/'.$path.'/'.$class.'.php';

        if (!file_exists($pluginfile)) {
            $pluginfile = $basepath.'/'.$path.'/'.$class.'.class.php';

            if (!file_exists($pluginfile)) {
                return null;
            }
        }

        require_once $pluginfile;

        // Restore variable $class that might have been overwritten by the
        // previous require
        $class = func_get_arg(0);

        return new ReflectionClass($class);
    }

    /**
     * Determine the type of a plugin to be installed.
     *
     * @param string $class plugin class name
     * @param string $path plugin relative path
     */
    private function getPluginType ($class, $path)
    {
        $plugin_class = $this->loadPlugin($class, $path);
        $types = [];

        if ($plugin_class) {
            $plugin_base = new ReflectionClass('StudIPPlugin');
            $interfaces = $plugin_class->getInterfaces();

            if ($plugin_class->isSubclassOf($plugin_base)) {
                foreach ($interfaces as $interface) {
                    $types[] = $interface->getName();
                }
            }
        }

        sort($types);

        return $types;
    }

    /**
     * Register a new plugin or update an existing plugin entry in the
     * data base. Returns the id of the new or updated plugin.
     *
     * @param string $name      plugin name
     * @param string $class     plugin class name
     * @param string $path      plugin relative path
     * @param int $depends   id of plugin this plugin depends on
     */
    public function registerPlugin ($name, $class, $path, $depends = null)
    {
        $db = DBManager::get();
        $info = $this->getPluginInfo($class);
        $type = $this->getPluginType($class, $path);
        $position = 1;

        // plugin must implement at least one interface
        if (count($type) == 0) {
            throw new Exception(_("Plugin implementiert kein gültiges Interface."));
        }

        if ($info) {
            $id = $info['id'];
            $sql = 'UPDATE plugins SET pluginname = ?, pluginpath = ?,
                                       plugintype = ? WHERE pluginid = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute([$name, $path, join(',', $type), $id]);

            $this->plugins[$id]['name'] = $name;
            $this->plugins[$id]['path'] = $path;
            $this->plugins[$id]['type'] = $type;

            // Delete tool activations if the plugin is no longer a
            // StandardPlugin
            if (!in_array(StandardPlugin::class, $type)) {
                ToolActivation::findEachBySQL(
                    function (ToolActivation $activation) use ($id) {
                        $this->setPluginActivated($id, $activation->range_id, false);
                    },
                    'plugin_id = ?',
                    [$id]
                );
            }
        } else {
            foreach ($this->plugins as $plugin) {
                $common_types = array_intersect($type, $plugin['type']);

                if (count($common_types) > 0 && $plugin['position'] >= $position) {
                    $position = $plugin['position'] + 1;
                }
            }

            $sql = 'INSERT INTO plugins (
                    pluginname, pluginclassname, pluginpath,
                    plugintype, navigationpos, dependentonid
                ) VALUES (?,?,?,?,?,?)';
            $stmt = $db->prepare($sql);
            $stmt->execute([$name, $class, $path, join(',', $type), $position, $depends]);
            $id = $db->lastInsertId();

            $this->plugins[$id] = [
                'id'          => $id,
                'name'        => $name,
                'class'       => $class,
                'path'        => $path,
                'type'        => $type,
                'enabled'     => false,
                'position'    => $position,
                'depends'     => $depends
            ];

            $this->readPluginInfos();

            $db->exec("INSERT INTO roles_plugins (roleid, pluginid)
                   SELECT roleid, $id FROM roles WHERE `system` = 'y' AND rolename != 'Nobody'");
        }

        return $id;
    }

    /**
     * Remove registration for the given plugin from the data base.
     *
     * @param int $id id of the plugin
     */
    public function unregisterPlugin ($id)
    {
        $info = $this->getPluginInfoById($id);

        if ($info) {
            $db = DBManager::get();

            $db->execute("DELETE FROM plugins WHERE pluginid = ?", [$id]);
            $db->execute("DELETE FROM plugins_activated WHERE pluginid = ?", [$id]);
            $db->execute("DELETE FROM roles_plugins WHERE pluginid = ?", [$id]);

            unset($this->plugins[$id]);

            unset($this->plugins_activated_cache[$id]);
        }
    }

    /**
     * Get meta data for the plugin specified by plugin class name.
     *
     * @param string $class class name of plugin
     */
    public function getPluginInfo ($class)
    {
        foreach ($this->plugins as $plugin) {
            if (strcasecmp($plugin['class'], $class) == 0) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Get meta data for the plugin specified by plugin id.
     *
     * @param int $id id of the plugin
     */
    public function getPluginInfoById ($id)
    {
        if (isset($this->plugins[$id])) {
            return $this->plugins[$id];
        }

        return null;
    }

    /**
     * Get meta data for all plugins of the specified type. A type of null
     * returns meta data for all installed plugins.
     *
     * @param string $type plugin type or null (all types)
     */
    public function getPluginInfos ($type = null)
    {
        $result = [];

        foreach ($this->plugins as $id => $plugin) {
            if ($type === null || in_array($type, $plugin['type'])) {
                $result[$id] = $plugin;
            }
        }

        return $result;
    }

    /**
     * Check user access permission for the given plugin.
     *
     * @param array $plugin plugin meta data
     * @param string $user_id id of user
     * @return bool
     */
    protected function checkUserAccess ($plugin, $user_id)
    {
        if (!$plugin['enabled']) {
            return false;
        }

        $plugin_roles = RolePersistence::getAssignedPluginRoles($plugin['id']);
        $user_roles = RolePersistence::getAssignedRoles($user_id, true);

        foreach ($plugin_roles as $plugin_role) {
            foreach ($user_roles as $user_role) {
                if ($plugin_role->getRoleid() === $user_role->getRoleid()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get instance of the plugin specified by plugin meta data.
     *
     * @param array $plugin_info plugin meta data
     * @return P
     */
    protected function getCachedPlugin ($plugin_info)
    {
        $class = $plugin_info['class'];
        $path  = $plugin_info['path'];
        $plugin_class = '';
        $plugin = null;

        if (isset($this->plugin_cache[$class])) {
            return $this->plugin_cache[$class];
        }

        if ($plugin_info['core'] || !$this->isPluginsDisabled()) {
            $plugin_class = $this->loadPlugin($class, $path);
        }

        if ($plugin_class) {
            $plugin = app()->get($class);
        }

        return $this->plugin_cache[$class] = $plugin;
    }

    /**
     * Get instance of the plugin specified by plugin class name.
     *
     * @param class-string<P> $class class name of plugin
     * @return P|null
     */
    public function getPlugin ($class)
    {
        $user = $GLOBALS['user']->id ?? 'nobody' ;
        $plugin_info = $this->getPluginInfo($class);
        $plugin = null;

        if (isset($plugin_info) && $this->checkUserAccess($plugin_info, $user)) {
            $plugin = $this->getCachedPlugin($plugin_info);
        }

        return $plugin;
    }

    /**
     * Get instance of the plugin specified by plugin id.
     *
     * @param int $id id of the plugin
     * @return P|null $plugin
     */
    public function getPluginById ($id)
    {
        $user = $GLOBALS['user']->id;
        $plugin_info = $this->getPluginInfoById($id);
        $plugin = null;

        if (isset($plugin_info) && $this->checkUserAccess($plugin_info, $user)) {
            $plugin = $this->getCachedPlugin($plugin_info);
        }

        return $plugin;
    }

    /**
     * Get instances of all plugins of the specified type. A type of null
     * returns all enabled plugins. The optional context parameter can be
     * used to get only plugins that are activated in the given context.
     *
     * @param class-string<P>|null $type plugin type or null (all types)
     * @param string $context context range id (optional)
     * @return P[]|StudIPPlugin[]
     */
    public function getPlugins ($type, $context = null)
    {
        $user = isset($GLOBALS['user']) ? $GLOBALS['user']->id : 'nobody';
        $plugin_info = $this->getPluginInfos($type);
        $plugins = [];

        foreach ($plugin_info as $info) {
            $activated = $context == null
                || $this->isPluginActivated($info['id'], $context);

            if ($this->checkUserAccess($info, $user) && $activated) {
                $plugin = $this->getCachedPlugin($info);

                if ($plugin !== null) {
                    $plugins[] = $plugin;
                }
            }
        }

        return $plugins;
    }

    /**
     * Read the manifest of the plugin in the given directory.
     * Returns null if the manifest cannot be found.
     *
     * @return array    containing the manifest information
     */
    public function getPluginManifest($plugindir)
    {
        if (!file_exists($plugindir . '/plugin.manifest')) {
            return null;
        }
        $manifest = file($plugindir . '/plugin.manifest');
        $result = [];

        if ($manifest === false) {
            return null;
        }

        foreach ($manifest as $line) {
            $key_and_value = explode('=', $line);
            $key = trim($key_and_value[0]);
            $value = trim($key_and_value[1] ?? '');

            // skip empty lines and comments
            if ($key === '' || $key[0] === '#') {
                continue;
            }

            $key_array = explode('.',$key,2);
            if(count($key_array) > 1){
                if($key_array[0] === 'screenshots'){
                    $screenshot_data['source'] = $key_array[1];
                    $screenshot_data['title'] = $value;
                    $result['screenshots']['pictures'][] = $screenshot_data;
                }
            } elseif($key === 'screenshots') {
                $result['screenshots']['path'] = $value;
            } elseif ($key === 'pluginclassname' && isset($result[$key])) {
                $result['additionalclasses'][] = $value;
            } elseif ($key === 'screenshot' && isset($result[$key])) {
                $result['additionalscreenshots'][] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
