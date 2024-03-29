<?php
// +---------------------------------------------------------------------------+
// This file is part of Stud.IP
// CronjobSchedule.class.php
//
// Copyright (C) 2013 Jan-Hendrik Willms <tleilax+studip@gmail.com>
// +---------------------------------------------------------------------------+
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or any later version.
// +---------------------------------------------------------------------------+
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
// +---------------------------------------------------------------------------+

/**
 * CronjobTask - Model for the database table "cronjobs_tasks"
 *
 * @author      Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       2.4
 *
 * @property string task_id database column
 * @property string id alias column for task_id
 * @property string filename database column
 * @property string class database column
 * @property string active database column
 * @property string execution_count database column
 * @property string assigned_count database column
 * @property SimpleORMapCollection schedules has_many CronjobSchedule
 */
class CronjobTask extends SimpleORMap
{
    public $valid = false;

    /**
     * Configures the model.
     *
     * @param Array $config Optional configuration passed from derived class
     */
    protected static function configure($config = array())
    {
        $config['db_table'] = 'cronjobs_tasks';
        $config['has_many']['schedules'] = array(
            'class_name' => 'CronjobSchedule',
            'on_delete'  => 'delete',
            'on_store'   => 'store'
        );
        parent::configure($config);
    }

    /**
     * The constructor sets the after_initialize callback which will load
     * the associated php class.
     */
    public function __construct($id = null)
    {
        $this->registerCallback('after_initialize', 'loadClass');

        parent::__construct($id);
    }

    /**
     * Tries to load the underlying php class. This also determines the valid
     * state of the task. If the class does not exists, the task is marked
     * as not valid.
     */
    protected function loadClass()
    {
        if (empty($this->class)) {
            return;
        }

        if (class_exists($this->class)) {
            $this->valid = true;
            return;
        }

        $filename = $GLOBALS['STUDIP_BASE_PATH'] . '/' . $this->filename;

        if (file_exists($filename)) {
            $this->valid = true;
            require_once $filename;
        }
    }

    /**
     * Returns whether the task is defined in the core system or via a plugin.
     *
     * @return bool True if task is defined in core system
     */
    public function isCore()
    {
        return strpos($this->filename, 'plugins_packages') === false;
    }

    /**
     * Executes the task.
     *
     * @param String $last_result Result of last executions
     * @param Array  $parameters  Parameters to pass to the task
     */
    public function engage($last_result, $parameters = array())
    {
        if ($this->valid) {
            $task = new $this->class;

            $task->setUp();
            $result = $task->execute($last_result, $parameters);
            $task->tearDown();
        } else {
            $result = $last_result;
        }

        return $result;
    }

    /**
     * Proxy the static methods "getDescription", "getName" and
     * "getParameters" from the task class.
     *
     * @param  String $field Field which should be accessed.
     * @return String Value of the method call
     */
    public function getValue($field)
    {
        if (in_array($field, words('description name parameters'))) {
            if ($this->valid) {
                $method = 'get' . ucfirst($field);
                return call_user_func("{$this->class}::{$method}");
            } elseif ($field === 'description') {
                return _('Unbekannt');
            } elseif ($field === 'name') {
                return preg_replace('/(_Cronjob)?(\.class)?\.php$/', '', basename($this->filename))
                     . ' (' . _('fehlerhaft') . ')';
            } elseif ($field === 'parameters') {
                return array();
            }
        }
        return parent::getValue($field);
    }
}
