<?php
/**
 * EventData.class.php - Model class for calendar events.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Peter Thienel <thienel@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       3.2
 */

class EventData extends SimpleORMap
{
    
    protected static function configure($config = array())
    {
        $config['db_table'] = 'event_data';
        
        $config['belongs_to']['author'] = array(
            'class_name' => 'User',
            'foreign_key' => 'author_id',
        );
        $config['belongs_to']['editor'] = array(
            'class_name' => 'User',
            'foreign_key' => 'editor_id',
        );
        $config['has_many']['calendars'] = array(
            'class_name' => 'CalendarEvent',
            'foreign_key' => 'event_id'
        );
        
        $time = time();
        $config['default_values']['start'] = $time;
        $config['default_values']['end'] = $time + 3600;
        $config['default_values']['category_intern'] = 0;
        $config['default_values']['class'] = 'PRIVATE';
        $config['default_values']['rtype'] = 'SINGLE';
        $config['default_values']['linterval'] = 0;
        $config['default_values']['sinterval'] = 0;
        $config['default_values']['ts'] = mktime(12, 0, 0, date('n', $time),
                date('j', $time), date('Y', $time));
        $config['default_values']['uid'] = function($event) {
            return 'Stud.IP-' . $event->event_id . '@' . $_SERVER['SERVER_NAME'];
        };
        
        parent::configure($config);
        
    }
    
    public function delete()
    {
        // do not delete until one calendar is left
        if (sizeof($this->calendars) > 1) {
            return false;
        }
        $calendars = $this->calendars;
        $ret = parent::delete();
        // only one calendar is left
        if ($ret) {
            $calendars->each(function($c) { $c->delete(); });
        }
        return $ret;
    }
    
    public static function garbageCollect()
    {
        DBManager::get()->query('DELETE event_data '
                . 'FROM calendar_event LEFT JOIN event_data USING(event_id)'
                . 'WHERE range_id IS NULL');
    }
    
}
