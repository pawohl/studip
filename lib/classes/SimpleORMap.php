<?php
/**
 * SimpleORMap.php
 * simple object-relational mapping
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @copyright   2010 Stud.IP Core-Group
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
*/

class SimpleORMap implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * Defines `_` as character used when joining composite primary keys.
     */
    const ID_SEPARATOR = '_';

    /**
     * table row data
     * @var array $content
     */
    protected $content = [];

    /**
     * table row data
     * @var array $content_db
     */
    protected $content_db = [];

    /**
     * new state of entry
     * @var boolean $is_new
     */
    protected $is_new = true;

    /**
     * deleted state of entry
     * @var boolean $is_deleted
     */
    protected $is_deleted = false;

     /**
     * db table metadata
     * @var ?array $schemes;
     */
    public static $schemes = null;

    /**
     * configuration data for subclasses
     * @see self::configure()
     * @var array $config;
     */
    protected static $config = [];

    /**
     * stores instantiated related objects
     * @var array $relations
     */
    protected $relations = [];

    /**
     * assoc array for storing values for additional fields
     *
     * @var array $additional_data
     */
    protected $additional_data = [];

    /**
     * reserved indentifiers, fields with those names must not have an explicit getXXX() method
     * @var array $reserved_slots
     */
    protected static $reserved_slots = ['value','newid','iterator','tablemetadata', 'relationvalue','wherequery','relationoptions','data','new','id'];

    /**
     * indicator for batch operations in findEachBySQL
     *
     * @var bool $performs_batch_operation
     */
    protected static $performs_batch_operation = false;

    /**
     * Defines which variant of the I18NString class should be used
     */
    protected string $i18n_class = I18NString::class;

    /**
     * Defines whether we need to fix the default values for a column
     *
     * @var bool $mariadb_column_default_fix
     */
    protected static $mariadb_column_default_fix = false;

    /**
     * name of db table
     * @return string
     */
    protected static function db_table()
    {
        return static::config('db_table');
    }

    /**
     * table columns
     * @return array
     */
    protected static function db_fields()
    {
        return static::config('db_fields');
    }

    /**
     * primary key columns
     * @return array
     */
    protected static function pk()
    {
        return static::config('pk');
    }

    /**
     * default values for columns
     * @return array
     */
    protected static function default_values()
    {
        return static::config('default_values');
    }

    /**
     * list of columns to deserialize
     * @return array key is name of column, value is name of ArrayObject class
     */
    protected static function serialized_fields()
    {
        return static::config('serialized_fields');
    }

    /**
     * aliases for columns
     * alias => column
     * @return array
     */
    protected static function alias_fields()
    {
        return static::config('alias_fields');
    }

    /**
     * multi-language fields
     * name => boolean
     * @return array
     */
    protected static function i18n_fields()
    {
        return static::config('i18n_fields');
    }

    /**
     * additional computed fields
     * name => callable
     * @return array
     */
    protected static function additional_fields()
    {
        return static::config('additional_fields');
    }

    /**
     * 1:n relation
     * @return array
     */
    protected static function has_many()
    {
        return static::config('has_many');
    }

    /**
     * 1:1 relation
     * @return array
     */
    protected static function has_one()
    {
        return static::config('has_one');
    }

    /**
     * n:1 relations
     * @return array
     */
    protected static function belongs_to()
    {
        return static::config('belongs_to');
    }

    /**
     * n:m relations
     * @return array
     */
    protected static function has_and_belongs_to_many()
    {
        return static::config('has_and_belongs_to_many');
    }

    /**
     * callbacks
     * @return array<string, array<string|Closure>>
     */
    protected static function registered_callbacks()
    {
        return static::config('registered_callbacks');
    }

    /**
     * contains an array of all used identifiers for fields
     * (db columns + aliased columns + additional columns + relations)
     * @return array
     */
    protected static function known_slots()
    {
        return static::config('known_slots');
    }

    /**
     * assoc array used to map SORM callback to NotificationCenter
     * keys are SORM callbacks, values notifications
     * eg. 'after_create' => 'FooDidCreate'
     *
     * @return array
     */
    protected static function notification_map()
    {
        return static::config('notification_map');
    }

    /**
     * assoc array for mapping get/set Methods
     *
     * @return array
     */
    protected static function getter_setter_map()
    {
        return static::config('getter_setter_map');
    }

    //////////////////////////////////////////////////

    /**
     * set configuration data from subclass
     *
     * @param ?array $config configuration data
     * @return void
     */
    protected static function configure($config = [])
    {
        $class = static::class;

        if (empty($config['db_table'])) {
            $config['db_table'] = strtolower($class);
        }

        if (!isset($config['db_fields'])) {
            if (static::tableScheme($config['db_table'])) {
                $config['db_fields'] = self::$schemes[$config['db_table']]['db_fields'];
                $config['pk'] = self::$schemes[$config['db_table']]['pk'];
            }
        }

        if (isset($config['pk'])
            && !isset($config['db_fields']['id'])
            && !isset($config['alias_fields']['id'])
            && !isset($config['additional_fields']['id'])
        ) {
            if (count($config['pk']) === 1) {
                $config['alias_fields']['id'] = $config['pk'][0];
            } else {
                $config['additional_fields']['id'] = ['get' => '_getId',
                                                           'set' => '_setId'];
            }
        }
        if (isset($config['additional_fields'])) {
            foreach ($config['additional_fields'] as $a_field => $a_config) {
                if (is_array($a_config) && !(isset($a_config['get']) || isset($a_config['set']))) {
                    $relation = $a_config[0] ?? '';
                    $relation_field = $a_config[1] ?? '';
                    if (!$relation) {
                        [$relation, $relation_field] = explode('_', $a_field);
                    }
                    if (!$relation_field || !$relation) {
                        throw new UnexpectedValueException('no relation found for autoget/set additional field: ' . $a_field);
                    }
                    $config['additional_fields'][$a_field] = ['get'            => '_getAdditionalValueFromRelation',
                                                                   'set'            => '_setAdditionalValue',
                                                                   'relation'       => $relation,
                                                                   'relation_field' => $relation_field];
                }
            }
        }
        if (isset($config['serialized_fields'])) {
            foreach ($config['serialized_fields'] as $a_field => $object_type) {
                if (!(is_subclass_of($object_type, 'StudipArrayObject'))) {
                    throw new UnexpectedValueException(sprintf('serialized field %s must use subclass of StudipArrayObject', $a_field));
                }
            }
        }

        foreach (['default_values', 'serialized_fields', 'alias_fields', 'i18n_fields', 'additional_fields'] as $fields) {
            if (!isset($config[$fields])) {
                $config[$fields] = [];
            }
        }

        foreach (['has_many', 'belongs_to', 'has_one', 'has_and_belongs_to_many'] as $type) {
            if (isset($config[$type]) && is_array($config[$type])) {
                foreach (array_keys($config[$type]) as $one) {
                    $config['relations'][$one] = null;
                }
            } else {
                $config[$type] = [];
            }
        }

        $callbacks = ['before_create',
                      'before_update',
                      'before_store',
                      'before_delete',
                      'before_initialize',
                      'after_create',
                      'after_update',
                      'after_store',
                      'after_delete',
                      'after_initialize'];

        foreach ($callbacks as $callback) {
            if (!isset($config['registered_callbacks'][$callback])) {
                $config['registered_callbacks'][$callback] = [];
            }
        }

        $auto_notification_map['after_create'] = $class . 'DidCreate';
        $auto_notification_map['after_store'] = $class . 'DidStore';
        $auto_notification_map['after_delete'] = $class . 'DidDelete';
        $auto_notification_map['after_update'] = $class . 'DidUpdate';
        $auto_notification_map['before_create'] = $class . 'WillCreate';
        $auto_notification_map['before_store'] = $class . 'WillStore';
        $auto_notification_map['before_delete'] = $class . 'WillDelete';
        $auto_notification_map['before_update'] = $class . 'WillUpdate';

        foreach ($auto_notification_map as $cb => $notification) {
            if (isset($config['notification_map'][$cb])) {
                if (strpos($config['notification_map'][$cb], $notification) !== false) {
                    $config['notification_map'][$cb] .= ' ' . $notification;
                }
            } else {
                $config['notification_map'][$cb] = $notification;
            }
        }

        if (is_array($config['notification_map'])) {
            foreach (array_keys($config['notification_map']) as $cb) {
                $config['registered_callbacks'][$cb][] = 'cbNotificationMapper';
            }
        }

        if (!I18N::isEnabled() || empty($config['i18n_fields'])) {
            $config['i18n_fields'] = [];
        } elseif (is_string($config['i18n_fields'])) {
            $i18n_fields = words($config['i18n_fields']);
            $config['i18n_fields'] = array_combine(
                $i18n_fields,
                array_fill(0, count($i18n_fields), true)
            );
        } elseif (array_is_list($config['i18n_fields'])) {
            $config['i18n_fields'] = array_combine(
                $config['i18n_fields'],
                array_fill(0, count($config['i18n_fields']), true)
            );
        }

        array_unshift($config['registered_callbacks']['after_initialize'], 'cbAfterInitialize');

        $config['known_slots'] = array_merge(
            array_keys($config['db_fields']),
            array_keys($config['alias_fields'] ?? []),
            array_keys($config['additional_fields'] ?? []),
            array_keys($config['relations'] ?? [])
        );

        foreach (array_map('strtolower', get_class_methods($class)) as $method) {
            if (in_array(substr($method, 0, 3), ['get', 'set'])) {
                $verb = substr($method, 0, 3);
                $name = substr($method, 3);
                if (in_array($name, $config['known_slots']) && !in_array($name, static::$reserved_slots) && !isset($config['additional_fields'][$name][$verb])) {
                    $config['getter_setter_map'][$name][$verb] = $method;
                }
            }
        }
        self::$config[$class] = $config;
    }

    /**
     * fetch config data for the called class
     *
     * @param string $key config key
     * @return mixed value of config key (null if not set)
     */
    protected static function config($key)
    {
        if (!array_key_exists(static::class, self::$config)) {
            static::configure();
        }

        return self::$config[static::class][$key] ?? null;
    }

    /**
     * fetch table metadata from db or from local cache
     *
     * @param string $db_table
     * @return bool true if metadata could be fetched
     */
    public static function tableScheme($db_table)
    {
        if (self::$schemes === null) {
            $cache = \Studip\Cache\Factory::getCache();
            self::$schemes = unserialize($cache->read('DB_TABLE_SCHEMES')) ?: [];
        }
        if (!isset(self::$schemes[$db_table])) {
            $pk = [];
            $db_fields = [];

            $db = DBManager::get()->query("SHOW COLUMNS FROM $db_table");
            while ($rs = $db->fetch(PDO::FETCH_ASSOC)){
                $db_fields[strtolower($rs['Field'])] = [
                    'name' => $rs['Field'],
                    'null' => $rs['Null'],
                    'default' => $rs['Default'],
                    'type' => $rs['Type'],
                    'extra' => $rs['Extra']
                ];
                if ($rs['Key'] === 'PRI'){
                    $pk[] = strtolower($rs['Field']);
                }
            }
            self::$schemes[$db_table]['db_fields'] = $db_fields;
            self::$schemes[$db_table]['pk'] = $pk;
            $cache = \Studip\Cache\Factory::getCache();
            $cache->write('DB_TABLE_SCHEMES', serialize(self::$schemes));
        }
        return isset(self::$schemes[$db_table]);
    }

    /**
     * force reload of cached table metadata
     * @return void
     */
    public static function expireTableScheme()
    {
        \Studip\Cache\Factory::getCache()->expire('DB_TABLE_SCHEMES');
        self::$schemes = null;
        self::$config = [];
    }

    /**
     * Returns new instance for given key when found in the database, else null.
     *
     * @param string|array $id primary key
     * @return static|null
     */
    public static function find($id)
    {
        $ref = new ReflectionClass(static::class);
        /** @var static $record */
        $record = $ref->newInstanceArgs(func_get_args());
        if (!$record->isNew()) {
            return $record;
        } else {
            return null;
        }
    }

    /**
     * Returns true if given key exists in the database.
     *
     * @param string|array $id primary key
     * @return boolean
     */
    public static function exists($id)
    {
        $ret = false;
        $db_table = static::db_table();
        $record = new static();
        $record->setId(...func_get_args());
        $where_query = $record->getWhereQuery();
        if ($where_query) {
            $query = "SELECT 1 FROM `$db_table` WHERE "
                    . join(" AND ", $where_query);
            $ret = (bool)DBManager::get()->query($query)->fetchColumn();
        }
        return $ret;
    }

    /**
     * returns number of records
     *
     * @param ?string $sql sql clause to use on the right side of WHERE
     * @param ?array $params params for query
     * @return int
     */
    public static function countBySql($sql = '1', $params = [])
    {
        $db_table = static::db_table();
        $db = DBManager::get();
        $has_join = stripos($sql, 'JOIN ');
        if ($has_join === false || $has_join > 10) {
            $sql = 'WHERE ' . $sql;
        }
        $sql = "SELECT count(*) FROM `" .  $db_table . "` " . $sql;
        $st = $db->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    /**
     * creates new record with given data in db
     * returns the new object or null
     * @param array $data assoc array of record
     * @return ?static
     */
    public static function create($data)
    {
        $record = new static();
        $record->setData($data, false);
        if ($record->store()) {
            return $record;
        } else {
            return null;
        }
    }

    /**
     * build object with given data
     *
     * @param iterable $data assoc array of record
     * @param ?bool $is_new set object to new state
     * @return static
     */
    public static function build($data, $is_new = true)
    {
        $record = new static();
        $record->setData($data, !$is_new);
        $record->setNew($is_new);
        return $record;
    }

    /**
     * build object with given data and mark it as existing
     *
     * @param iterable $data assoc array of record
     * @return static
     */
    public static function buildExisting($data)
    {
        return static::build($data, false);
    }

    /**
     * generate SimpleORMap object structure from assoc array
     * if given array contains data of related objects in sub-arrays
     * they are also generated. Existing records are updated, new records are created
     * (but changes are not yet stored)
     *
     * @param array $data
     * @return static
     */
    public static function import($data)
    {
        $record_data = [];
        $relation_data = [];
        foreach ($data as $key => $value) {
            $temp = static::alias_fields()[$key] ?? $key;
            if (isset(static::db_fields()[$temp])) {
                $record_data[$key] = $value;
            } else {
                $relation_data[$key] = $value;
            }
        }
        $record = static::toObject($record_data);
        if (!$record instanceof static) {
            $record = new static();
            $record->setData($record_data, true);
        } else {
            $record->setData($record_data);
        }
        foreach ($relation_data as $relation => $data) {
            if (!$record->isRelation($relation)) {
                continue;
            }

            $options = $record->getRelationOptions($relation);
            if ($options['type'] == 'has_one') {
                $record->{$relation} = call_user_func([$options['class_name'], 'import'], $data);
            }
            if ($options['type'] == 'has_many' || $options['type'] == 'has_and_belongs_to_many') {
                foreach ($data as $one) {
                    $current = call_user_func([$options['class_name'], 'import'], $one);
                    if ($options['type'] == 'has_many') {
                        $foreign_key_value = call_user_func($options['assoc_func_params_func'], $record);
                        call_user_func($options['assoc_foreign_key_setter'], $current, $foreign_key_value);
                    }
                    if ($current->id !== null) {
                        $existing = $record->{$relation}->find($current->id);
                        if ($existing) {
                            $existing->mergeRelatedRecord($current);
                        } else {
                            $record->{$relation}->append($current);
                        }
                    } else {
                        $record->{$relation}->append($current);
                    }
                }
            }
        }
        return $record;
    }

    /**
     * returns array of instances of given class filtered by given sql
     * @param string $sql sql clause to use on the right side of WHERE
     * @param ?array $params parameters for query
     * @return static[] array of "self" objects
     */
    public static function findBySQL($sql, $params = [])
    {
        $db_table = static::db_table();

        $has_join = stripos($sql, 'JOIN ');
        if ($has_join === false || $has_join > 10) {
            $sql = 'WHERE ' . $sql;
        }
        $sql = "SELECT `" . $db_table . "`.* FROM `" . $db_table . "` " . $sql;
        $stmt = DBManager::get()->prepare($sql);
        $stmt->execute($params);

        $record = static::build([], false);

        $ret = [];
        do  {
            $clone = clone $record;
            $clone->setNew(false);
            $stmt->setFetchMode(PDO::FETCH_INTO, $clone);

            if ($clone = $stmt->fetch()) {
                $clone->applyCallbacks('after_initialize');
                $ret[] = $clone;
            }
        } while ($clone);
        return $ret;
    }

    /**
     * returns one instance of given class filtered by given sql
     * only first row of query is used
     * @param string $where sql clause to use on the right side of WHERE
     * @param ?array $params parameters for query
     * @return ?static
     */
    public static function findOneBySQL($where, $params = [])
    {
        if (stripos($where, 'LIMIT') === false) {
            $where .= " LIMIT 1";
        }
        $found = static::findBySQL($where, $params);
        return isset($found[0]) ? $found[0] : null;
    }

    /**
     * find related records for a n:m relation (has_many_and_belongs_to_many)
     * using a combination table holding the keys
     *
     * @param string $foreign_key_value value of foreign key to find related records
     * @param array $options relation options from other side of relation
     * @return static[] array of "self" objects
     */
    public static function findThru($foreign_key_value, $options)
    {
        $thru_table = $options['thru_table'];
        $thru_key = $options['thru_key'];
        $thru_assoc_key = $options['thru_assoc_key'];
        $assoc_foreign_key = $options['assoc_foreign_key'];

        $db_table = static::db_table();

        $sql = "SELECT `$db_table`.* FROM `$thru_table`
                JOIN `$db_table` ON `$thru_table`.`$thru_assoc_key` = `$db_table`.`$assoc_foreign_key`
                WHERE `$thru_table`.`$thru_key` = ? ";
        if (empty($options['order_by_php']) && !empty($options['order_by'])) {
            $sql .= $options['order_by'];
        }

        $st = DBManager::get()->prepare($sql);
        $st->execute([$foreign_key_value]);

        $record = static::build([], false);

        $ret = [];
        do {
            $clone = clone $record;
            $clone->setNew(false);
            $st->setFetchMode(PDO::FETCH_INTO, $clone);

            if ($clone = $st->fetch()) {
                $clone->applyCallbacks('after_initialize');
                $ret[] = $clone;
            }
        } while ($clone);
        return $ret;
    }

    /**
     * passes objects for given sql through given callback
     *
     * @param callable $callable callback which gets the current record as param
     * @param string $sql where clause of sql
     * @param ?array $params sql statement parameters
     * @return integer number of found records
     */
    public static function findEachBySQL($callable, $sql, $params = [])
    {
        $has_join = stripos($sql, 'JOIN ');
        if ($has_join === false || $has_join > 10) {
            $sql = "WHERE {$sql}";
        }

        $db_table = static::db_table();
        $st = DBManager::get()->prepare("SELECT `{$db_table}`.* FROM `{$db_table}` {$sql}");
        $st->execute($params);

        // Indicate that we are performing a batch operation
        static::$performs_batch_operation = true;

        $record = static::build([], false);

        $ret = 0;
        do {
            $clone = clone $record;
            $clone->setNew(false);
            $st->setFetchMode(PDO::FETCH_INTO, $clone);

            if ($clone = $st->fetch()) {
                $clone->applyCallbacks('after_initialize');
                $callable($clone, $ret++);
            }
        } while ($clone);

        // Reset batch operation indicator
        static::$performs_batch_operation = false;

        return $ret;
    }

    /**
     * returns array of instances of given class for by given pks
     * @param ?array $pks array of primary keys
     * @param ?string $order order by clause
     * @param ?array $order_params
     * @return static[]
     */
    public static function findMany($pks = [], $order = '', $order_params = [])
    {
        $db_table = static::db_table();
        $pk = static::pk();
        $db = DBManager::get();
        if (count($pk) > 1) {
            throw new Exception('not implemented yet');
        }
        $where = "`$db_table`.`{$pk[0]}` IN ("  . $db->quote($pks) . ") ";
        return static::findBySQL($where . $order, $order_params);
    }

    /**
     * passes objects for by given pks through given callback
     *
     * @param callable $callable callback which gets the current record as param
     * @param ?array $pks array of primary keys of called class
     * @param ?string $order order by sql
     * @param ?array $order_params
     * @return integer number of found records
     */
    public static function findEachMany($callable, $pks = [], $order = '', $order_params = [])
    {
        $db_table = static::db_table();
        $pk = static::pk();
        $db = DBManager::get();
        if (count($pk) > 1) {
            throw new Exception('not implemented yet');
        }
        $where = "`$db_table`.`{$pk[0]}` IN ("  . $db->quote($pks) . ") ";
        return static::findEachBySQL($callable, $where . $order, $order_params);
    }

    /**
     * passes objects for given sql through given callback
     * and returns an array of callback return values
     *
     * @param callable $callable callback which gets the current record as param
     * @param string $where where clause of sql
     * @param array $params sql statement parameters
     * @return array return values of callback
     */
    public static function findAndMapBySQL($callable, $where, $params = [])
    {
        $ret = [];
        $calleach = function($m) use (&$ret, $callable) {
            $ret[] = $callable($m);
        };
        static::findEachBySQL($calleach, $where, $params);
        return $ret;
    }

    /**
     * passes objects for by given pks through given callback
     * and returns an array of callback return values
     *
     * @param callable $callable callback which gets the current record as param
     * @param ?array $pks array of primary keys of called class
     * @param ?string $order order by sql
     * @param ?array $order_params
     * @return array return values of callback
     */
    public static function findAndMapMany($callable, $pks = [], $order = '', $order_params = [])
    {
        $ret = [];
        $calleach = function($m) use (&$ret, $callable) {
            $ret[] = $callable($m);
        };
        $db_table = static::db_table();
        $pk = static::pk();
        $db = DBManager::get();
        if (count($pk) > 1) {
            throw new Exception('not implemented yet');
        }
        $where = "`$db_table`.`{$pk[0]}` IN ("  . $db->quote($pks) . ") ";
        static::findEachBySQL($calleach, $where . $order, $order_params);
        return $ret;
    }

    /**
     * deletes objects specified by sql clause
     * @param string $where sql clause to use on the right side of WHERE
     * @param ?array $params parameters for query
     * @return integer number of deleted records
     */
    public static function deleteBySQL($where, $params = [])
    {
        $killeach = function($record) {$record->delete();};
        return static::findEachBySQL($killeach, $where, $params);
    }

    /**
     * returns object of given class for given id or null
     * the param could be a string, an assoc array containing primary key field
     * or an already matching object. In all these cases an object is returned
     *
     * @param string|static|array $id_or_object id as string, object or assoc array
     * @return static
     */
    public static function toObject($id_or_object)
    {
        if ($id_or_object instanceof static) {
            return $id_or_object;
        }
        if (is_array($id_or_object)) {
            $pk = static::pk();
            $key_values = [];
            foreach ($pk as $key) {
                if (array_key_exists($key, $id_or_object)) {
                    $key_values[] = $id_or_object[$key];
                }
            }
            if (count($pk) === count($key_values)) {
                if (count($pk) === 1) {
                    $id = $key_values[0];
                } else {
                    $id = $key_values;
                }
            } else {
                $id = null;
            }
        } else {
            $id = $id_or_object;
        }
        return static::find($id);
    }

    /**
     * interceptor for static findByColumn / findEachByColumn / countByColumn /
     * deleteByColumn magic
     *
     * @param string $name
     * @param array $arguments
     * @throws BadMethodCallException
     * @return int|static|static[]
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $db_table = static::db_table();
        $alias_fields = static::alias_fields();
        $db_fields = static::db_fields();
        $name = strtolower($name);
        $order = '';
        $param_arr = [];
        $where = '';
        $where_param = is_array($arguments[0]) ? $arguments[0] : [$arguments[0]];
        $action = strstr($name, 'by', true);
        $field = substr($name, strlen($action) + 2);
        switch ($action) {
            case 'findone':
                $order = $arguments[1] ?? '';
                $param_arr[0] =& $where;
                $param_arr[1] = [$where_param];
                $method = 'findonebysql';
                break;
            case 'find':
            case 'findmany':
                $order = $arguments[1] ?? '';
                $param_arr[0] =& $where;
                $param_arr[1] = [$where_param];
                $method = 'findbysql';
                break;
            case 'findeach':
            case 'findeachmany':
                $order = $arguments[2] ?? '';
                $param_arr[0] = $arguments[0];
                $param_arr[1] =& $where;
                $param_arr[2] = [$arguments[1]];
                $method = 'findeachbysql';
                break;
            case 'count':
            case 'delete':
                $param_arr[0] =& $where;
                $param_arr[1] = [$where_param];
                $method = "{$action}bysql";
                break;
            default:
                throw new BadMethodCallException("Method " . static::class . "::$name not found");
        }
        if (isset($alias_fields[$field])) {
            $field = $alias_fields[$field];
        }
        if (isset($db_fields[$field])) {
            $where = "`$db_table`.`$field` IN(?) " . $order;
            return call_user_func_array([static::class, $method], $param_arr);
        }
        throw new BadMethodCallException("Method " . static::class . "::$name not found");
    }

    /**
     * constructor, give primary key of record as param to fetch
     * corresponding record from db if available, if not preset primary key
     * with given value. Give null to create new record
     *
     * @param null|int|string|array $id primary key of table
     */
    function __construct($id = null)
    {
        foreach(['has_many', 'belongs_to', 'has_one', 'has_and_belongs_to_many'] as $type) {
            foreach (array_keys($this->$type()) as $one) {
                $this->relations[$one] = null;
            }
        }

        if ($id) {
            $this->setId($id);
        }
        $this->restore();
    }

    /**
     * returns internal used id value (multiple keys concatenated with _)
     * @param mixed $field unused parameter
     * @return ?string
     */
    protected function _getId($field)
    {
        return is_null($this->getId())
             ? null
             : implode(self::ID_SEPARATOR, $this->getId());
    }

    /**
     * sets internal used id value (multiple keys concatenated with _)
     * @param string $field Field to set (unused since it's always the id)
     * @param string $value Value for id field
     * @return bool
     */
    protected function _setId($field, $value)
    {
        return $this->setId(explode(self::ID_SEPARATOR, $value));
    }

    /**
     * retrieves an additional field value from relation
     *
     * @param string $field
     * @return mixed
     */
    protected function _getAdditionalValueFromRelation($field)
    {
        [$relation, $relation_field] = [$this->additional_fields()[$field]['relation'],
                                                $this->additional_fields()[$field]['relation_field']];
        if (!array_key_exists($field, $this->additional_data)) {
            $this->_setAdditionalValue($field, $this->getRelationValue($relation, $relation_field));
        }
        return $this->additional_data[$field];
    }

    /**
     * sets additional value in field imported from relation
     *
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    protected function _setAdditionalValueFromRelation($field, $value)
    {
        [$relation, $relation_field] = [$this->additional_fields()[$field]['relation'],
                $this->additional_fields()[$field]['relation_field']];
        $this->$relation->$field = $value;
        unset($this->additional_data[$field]);
        return $this->_getAdditionalValueFromRelation($field);
    }

    /**
     * @param string $field
     * @return mixed
     */
    protected function _getAdditionalValue($field)
    {
        return $this->additional_data[$field];
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return mixed
     */
    protected function _setAdditionalValue($field, $value)
    {
        return $this->additional_data[$field] = $value;
    }

    /**
     * clean up references after cloning
     * @return void
     */
    function __clone()
    {
        $this->setNew(true);
        //all references link still to old object => reset all aliases
        foreach ($this->alias_fields() as $alias => $field) {
            if (isset($this->db_fields()[$field])) {
                $content_value = $this->content[$field];
                $content_db_value = $this->content_db[$field];
                unset($this->content[$alias]);
                unset($this->content_db[$alias]);
                unset($this->content[$field]);
                unset($this->content_db[$field]);
                if (is_object($content_value)) {
                    $this->content[$field] = clone $content_value;
                } else {
                    $this->content[$field] = $content_value;
                }
                if (is_object($content_db_value)) {
                    $this->content_db[$field] = clone $content_db_value;
                } else {
                    $this->content_db[$field] = $content_db_value;
                }
            }
        }
        foreach ($this->alias_fields() as $alias => $field) {
            if (isset($this->db_fields()[$field])) {
                $this->content[$alias] =& $this->content[$field];
                $this->content_db[$alias] =& $this->content_db[$field];
            }
        }
        //unset all relations for now
        //TODO: maybe a deep copy of all belonging objects is more appropriate
        foreach(['has_many', 'belongs_to', 'has_one', 'has_and_belongs_to_many'] as $type) {
            foreach (array_keys($this->$type()) as $one) {
                $this->relations[$one] = null;
            }
        }
        //begun the clone war has... hmpf
    }

    /**
     * try to determine all needed options for a relationship from
     * configured options
     *
     * @param string $type
     * @param string $name
     * @param array $options
     * @throws Exception if options for thru_table could not be determined
     * @return array
     */
    protected function parseRelationOptions($type, $name, $options)
    {
        if (empty($options['class_name'])) {
            throw new Exception('Option class_name not set for relation ' . $name);
        }
        if (empty($options['assoc_foreign_key'])) {
            if ($type === 'has_many' || $type === 'has_one') {
                $options['assoc_foreign_key'] = $this->pk()[0];
            } else if ($type === 'belongs_to') {
                $options['assoc_foreign_key'] = 'id';
            }
        }
        if ($type === 'has_and_belongs_to_many') {
            $thru_table = $options['thru_table'];
            if (empty($options['thru_key'])) {
                $options['thru_key'] = $this->pk()[0];
            }
            if (empty($options['thru_assoc_key']) || empty($options['assoc_foreign_key'])) {
                $class = $options['class_name'];
                $record = new $class();
                $meta = $record->getTableMetadata();
                if (empty($options['thru_assoc_key'])) {
                    $options['thru_assoc_key'] = $meta['pk'][0];
                }
                if (empty($options['assoc_foreign_key'])) {
                    $options['assoc_foreign_key']= $meta['pk'][0];
                }
            }
            static::tableScheme($thru_table);
            if (is_array(self::$schemes[$thru_table])) {
                $thru_key_ok = isset(self::$schemes[$thru_table]['db_fields'][$options['thru_key']]);
                $thru_assoc_key_ok = isset(self::$schemes[$thru_table]['db_fields'][$options['thru_assoc_key']]);
            }
            if (!$thru_assoc_key_ok || !$thru_key_ok) {
                throw new Exception("Could not determine keys for relation " . $name . " through table " . $thru_table);
            }
            if ($options['assoc_foreign_key'] instanceof Closure) {
                throw new Exception("For relation " . $name . " assoc_foreign_key must be a name of a column");
            }
        }
        if (empty($options['assoc_func'])) {
            if ($type !== 'has_and_belongs_to_many') {
                $options['assoc_func'] = $options['assoc_foreign_key'] === 'id' ? 'find' : 'findBy' . $options['assoc_foreign_key'];
            } else {
                $options['assoc_func'] = 'findThru';
            }
        }
        if (empty($options['foreign_key'])) {
            $options['foreign_key'] = 'id';
        }
        if (isset($options['foreign_key']) && $options['foreign_key'] instanceof Closure) {
            $options['assoc_func_params_func'] = function($record) use ($name, $options) { return call_user_func($options['foreign_key'], $record, $name, $options);};
        } else {
            $options['assoc_func_params_func'] = function($record) use ($name, $options) { return $options['foreign_key'] === 'id' ? $record->getId() : $record->getValue($options['foreign_key']);};
        }
        if (isset($options['assoc_foreign_key']) && $options['assoc_foreign_key'] instanceof Closure) {
            if ($type === 'belongs_to') {
                $options['assoc_foreign_key_getter'] = function($record, $that) use ($name, $options) { return call_user_func($options['assoc_foreign_key'], $record, $name, $options, $that);};
            } else {
                $options['assoc_foreign_key_setter'] = function($record, $params) use ($name, $options) { return call_user_func($options['assoc_foreign_key'], $record, $params, $name, $options);};
            }
        } elseif (!empty($options['assoc_foreign_key'])) {
            if ($type === 'belongs_to') {
                $options['assoc_foreign_key_getter'] = function($record, $that) use ($name, $options) { return $record->getValue($options['assoc_foreign_key']);};
            } else {
                $options['assoc_foreign_key_setter'] = function($record, $value) use ($name, $options) { return $record->setValue($options['assoc_foreign_key'], $value);};
            }
        } else {
            throw new Exception("Could not determine assoc_foreign_key for relation " . $name);
        }
        return $options;
    }

    /**
     * returns array with option for given relation
     * available options:
     * 'type':                   relation type, on of 'has_many', 'belongs_to', 'has_one', 'has_and_belongs_to_many'
     * 'class_name':             name of class for related records
     * 'foreign_key':            name of column with foreign key
     *                           or callback to retrieve foreign key value
     * 'assoc_foreign_key':      name of foreign key column in related class
     * 'assoc_func':             name of static method to call on related class to find related records
     * 'assoc_func_params_func': callback to retrieve params for assoc_func
     * 'thru_table':             name of relation table for n:m relation
     * 'thru_key':               name of column holding foreign key in relation table
     * 'thru_assoc_key':         name of column holding foreign key from related class in relation table
     * 'on_delete':              contains simply 'delete' to indicate that related records should be deleted
     *                           or callback to invoke before record gets deleted
     * 'on_store':               contains simply 'store' to indicate that related records should be stored
     *                           or callback to invoke after record gets stored
     * 'order_by':               optional ordering of relation items
     * 'order_by_php':           optional order performed on the collection and not in sql
     *                           this may either be a string containing the columns and directions (e.g. 'foo desc, bar')
     *                           or an array with the described string and a sort option flag (e.g. ['foo', SORT_NUMERIC])
     *
     * Note: Defining order_by_php will disable any set order_by option.
     *
     * @param string $relation name of relation
     * @return array assoc array containing options
     */
    public function getRelationOptions($relation)
    {
        $options = [];
        foreach(['has_many', 'belongs_to', 'has_one', 'has_and_belongs_to_many'] as $type) {
            if (isset($this->$type()[$relation])) {
                $options = self::$config[get_class($this)][$type][$relation];
                if (!isset($options['type'])) {
                    $options = $this->parseRelationOptions($type, $relation, $options, $this->db_table());
                    $options['type'] = $type;
                    self::$config[get_class($this)][$type][$relation] = $options;
                }
                break;
            }
        }
        return $options;
    }

    /**
     * returns table and columns metadata
     *
     * @return array assoc array with columns, primary keys and name of table
     */
    function getTableMetadata()
    {
        return ['fields' => $this->db_fields(),
                     'pk' => $this->pk(),
                     'table' => $this->db_table(),
                     'additional_fields' => $this->additional_fields(),
                     'alias_fields' => $this->alias_fields(),
                     'relations' => array_keys($this->relations)];
    }

    /**
     * returns true, if table has an auto_increment column
     *
     * @return boolean
     */
    function hasAutoIncrementColumn()
    {
        return $this->db_fields()[$this->pk()[0]]['extra'] == 'auto_increment';
    }

    /**
     * set primary key for entry, combined keys must be passed as array
     * @param int|string|array $id primary key
     * @throws InvalidArgumentException if given key is not complete
     * @return boolean
     */
    public function setId($id)
    {
        if (!is_array($id)){
            $id = [$id];
        }
        if (count($this->pk()) != count($id)){
            throw new InvalidArgumentException("Invalid ID, Primary Key {$this->db_table()} is " .join(",",$this->pk()));
        } else {
            foreach ($this->pk() as $count => $key){
                $this->content[$key] = $id[$count];
            }
            return true;
        }
    }

    /**
     * returns primary key, multiple keys as array
     * @return null|string|array current primary key, null if not set
     */
    function getId()
    {
        if (count($this->pk()) == 1) {
            return $this->content[$this->pk()[0]] ?? null;
        } else {
            $id = [];
            foreach ($this->pk() as $key) {
                if (array_key_exists($key, $this->content)) {
                    $id[] = $this->content[$key];
                }
            }
            return (count($this->pk()) == count($id) ? $id : null);
        }
    }

    /**
     * Create new unique pk as md5 hash
     *
     * This will only work for said md5 hashes columns. An exception is thrown
     * otherwise.
     *
     * @return string
     */
    public function getNewId()
    {
        if ($this->hasAutoIncrementColumn()) {
            throw new Exception('You cannot retrieve the new id for an autoincrement column');
        }

        if (count($this->pk()) !== 1) {
            throw new Exception('You cannot retrieve a new id for a composite primary key');
        }

        do {
            $id = md5(uniqid($this->db_table(), true));
            $db = DBManager::get()->query("SELECT `{$this->pk()[0]}` FROM `{$this->db_table()}` "
            . "WHERE `{$this->pk()[0]}` = '$id'");
        } while ($db->fetch());

        return $id;
    }

    /**
     * returns data of table row as assoc array
     * pass array of fieldnames or ws separated string to limit
     * fields
     *
     * @param null|array|string $only_these_fields limit returned fields
     * @return array
     */
    function toArray($only_these_fields = null)
    {
        $ret = [];
        if (is_string($only_these_fields)) {
            $only_these_fields = words($only_these_fields);
        }
        $fields = array_diff($this->known_slots(), array_keys($this->relations));
        if (is_array($only_these_fields)) {
            $only_these_fields = array_filter(array_map(function($s) {
                return is_string($s) ? strtolower($s) : null;
            }, $only_these_fields));
            $fields = array_intersect($only_these_fields, $fields);
        }
        foreach ($fields as $field) {
            $ret[$field] = $this->getValue($field);
            if ($ret[$field] instanceof StudipArrayObject) {
                $ret[$field] = $ret[$field]->getArrayCopy();
            }
        }
        return $ret;
    }

    /**
     * Returns data of table row as assoc array with raw contents like
     * they are in the database.
     * Pass array of fieldnames or ws separated string to limit
     * fields.
     *
     * @param null|array|string $only_these_fields
     * @return array
     */
    function toRawArray($only_these_fields = null)
    {
        $ret = [];
        if (is_string($only_these_fields)) {
            $only_these_fields = words($only_these_fields);
        }
        $fields = array_keys($this->db_fields());
        if (is_array($only_these_fields)) {
            $only_these_fields = array_filter(array_map(function ($s) {
                return is_string($s) ? strtolower($s) : null;
            }, $only_these_fields));
            $fields = array_intersect($only_these_fields, $fields);
        }
        foreach ($fields as $field) {
            if ($this->content[$field] instanceof I18NString) {
                $ret[$field] = $this->content[$field]->original();
            } elseif ($this->content[$field] === null) {
                $ret[$field] = null;
            } else {
                $ret[$field] = (string)$this->content[$field];
            }
        }
        return $ret;
    }

    /**
     * returns data of table row as assoc array
     * including related records with a 'has*' relationship
     * recurses one level without param
     *
     * $only_these_fields limits output for relationships in this way:
     * $only_these_fields = array('field_1',
     *                            'field_2',
     *                            'relation1',
     *                            'relation2' => array('rel2_f1',
     *                                                 'rel2_f2',
     *                                                 'rel2_rel11' => array(
     *                                                           rel2_rel1_f1)
     *                                                )
     *                           )
     * Here all fields of relation1 will be returned.
     *
     * @param null|array|string $only_these_fields limit returned fields
     * @return array
     */
    function toArrayRecursive($only_these_fields = null)
    {
        if (is_string($only_these_fields)) {
            $only_these_fields = words($only_these_fields);
        }
        if (is_null($only_these_fields)) {
            $only_these_fields = $this->known_slots();
        }
        $ret = $this->toArray($only_these_fields);
        $relations = [];
        if (is_array($only_these_fields)) {
            foreach ($only_these_fields as $key => $value) {
                if (!is_array($value) &&
                    array_key_exists(strtolower($value), $this->relations)
                ) {
                    $relations[strtolower($value)] = 0; //not null|array|string to stop recursion
                }
                if (array_key_exists(strtolower($key), $this->relations)) {
                    $relations[strtolower($key)] = $value;
                }
            }
        }
        if (count($relations)) {
            foreach ($relations as $relation_name => $relation_only_these_fields) {
                $options = $this->getRelationOptions($relation_name);
                if ($options['type'] === 'has_one' ||
                        $options['type'] === 'belongs_to') {
                    $ret[$relation_name] =
                            $this->{$relation_name}->
                                            toArrayRecursive($relation_only_these_fields);
                }
                if ($options['type'] === 'has_many' ||
                    $options['type'] === 'has_and_belongs_to_many') {
                    $ret[$relation_name] =
                            $this->{$relation_name}->
                                            sendMessage('toArrayRecursive',
                                            [$relation_only_these_fields]);
                }
            }
        }
        return $ret;
    }

    /**
     * returns value of a column
     *
     * @throws InvalidArgumentException if column could not be found
     * @throws BadMethodCallException if getter for additional field could not be found
     * @param string $field
     * @return null|string|SimpleORMapCollection
     */
    public function getValue($field)
    {
        $field = strtolower($field);

        // No value defined, throw exception
        if (!in_array($field, $this->known_slots())) {
            throw new InvalidArgumentException(static::class . '::'.$field . ' not found.');
        }

        // Get value by getter
        if (isset($this->getter_setter_map()[$field]['get'])) {
            return call_user_func([$this, $this->getter_setter_map()[$field]['get']]);
        }

        // Get value from content
        if (array_key_exists($field, $this->content)) {
            return $this->content[$field];
        }

        // Get value from relation
        if (array_key_exists($field, $this->relations)) {
            $this->initRelation($field);
            return $this->relations[$field];
        }

        // Get value from additional_field
        if (isset($this->additional_fields()[$field]['get'])) {
            // Getter is defined as a closure
            if ($this->additional_fields()[$field]['get'] instanceof Closure) {
                return call_user_func_array($this->additional_fields()[$field]['get'], [$this, $field]);
            }

            // Getter is defined as a method of this object
            return call_user_func([$this, $this->additional_fields()[$field]['get']], $field);
        }

        // No value found, throw exception
        throw new RuntimeException('No value could be found for ' . static::class . '::' . $field);
    }

    /**
     * gets a value from a related object
     * only possible, if the relation has cardinality 1
     * e.g. 'has_one' or 'belongs_to'
     *
     * @param string $relation name of relation
     * @param string $field name of column
     * @throws InvalidArgumentException if no relation with given name is found
     * @return mixed the value from the related object
     */
    function getRelationValue($relation, $field)
    {
        $field = strtolower($field);
        $options = $this->getRelationOptions($relation);
        if ($options['type'] === 'has_one' || $options['type'] === 'belongs_to') {
            return $this->{$relation}->{$field} ?? null;
        } else {
            throw new InvalidArgumentException('Relation ' . $relation . ' not found or not applicable.');
        }
    }

    /**
     * returns default value for column
     *
     * @param string $field name of column
     * @return mixed the default value
     */
     function getDefaultValue($field)
     {
         $default_value = null;
         if (!isset($this->default_values()[$field])) {
             if (!in_array($field, $this->pk())) {
                 $meta = $this->db_fields()[$field];
                 if (isset($meta['default'])) {
                     $default_value = $meta['default'];
                 } elseif ($meta['null'] == 'NO') {
                     if (strpos($meta['type'], 'text') !== false || strpos($meta['type'], 'char') !== false) {
                         $default_value = '';
                     }
                     if (strpos($meta['type'], 'int') !== false) {
                         $default_value = '0';
                     }
                 }

                 /* @see https://gitlab.studip.de/studip/studip/-/issues/4462 */
                 if (self::$mariadb_column_default_fix) {
                    if ($default_value === 'NULL') {
                        $default_value = null;
                    } elseif (
                        is_string($default_value)
                        && strlen($default_value) >= 2
                        && $default_value[0] === "'"
                    ) {
                        $default_value = str_replace("''", "'", $default_value);
                        $default_value = stripslashes($default_value);
                        $default_value = substr($default_value, 1, -1);
                    }
                 }
             }
         } else {
             $default_value = $this->default_values()[$field];
         }
         return $default_value;
     }

    /**
     * sets value of a column
     *
     * @throws InvalidArgumentException if column could not be found
     * @throws BadMethodCallException if setter for additional field could not be found
     * @param string $field
     * @param mixed $value
     * @return string
     */
     function setValue($field, $value)
     {
         $field = strtolower($field);
         $ret = false;
         if (in_array($field, $this->known_slots())) {
             if (isset($this->getter_setter_map()[$field]['set'])) {
                 return call_user_func([$this, $this->getter_setter_map()[$field]['set']], $value);
             }
             if (array_key_exists($field, $this->content)) {
                 if (array_key_exists($field, $this->serialized_fields())) {
                     $ret = $this->setSerializedValue($field, $value);
                 } elseif ($this->isI18nField($field)) {
                         $ret = $this->setI18nValue($field, $value);
                 } else {
                     $ret = ($this->content[$field] = $value);
                 }
             } elseif (!empty($this->additional_fields()[$field]['set'])) {
                 if ($this->additional_fields()[$field]['set'] instanceof Closure) {
                     return call_user_func_array($this->additional_fields()[$field]['set'], [$this, $field, $value]);
                 } else {
                     return call_user_func([$this, $this->additional_fields()[$field]['set']], $field, $value);
                 }
             } elseif (array_key_exists($field, $this->relations)) {
                 $options = $this->getRelationOptions($field);
                 if ($options['type'] === 'has_one' || $options['type'] === 'belongs_to') {
                     if (is_a($value, $options['class_name'])) {
                         $this->relations[$field] = $value;
                         if ($options['type'] == 'has_one') {
                             $foreign_key_value = call_user_func($options['assoc_func_params_func'], $this);
                             call_user_func($options['assoc_foreign_key_setter'], $value, $foreign_key_value);
                         } else {
                             $assoc_foreign_key_value = call_user_func($options['assoc_foreign_key_getter'], $value, $this);
                             if ($assoc_foreign_key_value === null) {
                                 throw new InvalidArgumentException(sprintf('trying to set belongs_to object of type: %s, but assoc_foreign_key: %s is null', get_class($value), $options['assoc_foreign_key']));
                             }
                             $this->setValue($options['foreign_key'], $assoc_foreign_key_value);
                         }
                     } elseif (
                         $value === null
                         && $this->db_fields()[$options['foreign_key']]['null'] === 'YES'
                     ) {
                         $this->resetRelation($field);
                         $this->setValue($options['foreign_key'], null);

                     } else {
                         throw new InvalidArgumentException(sprintf('relation %s expects object of type: %s', $field, $options['class_name']));
                     }
                 }
                 if ($options['type'] == 'has_many' || $options['type'] == 'has_and_belongs_to_many') {
                     if (is_array($value) || $value instanceof Traversable) {
                         $new_ids = [];
                         $old_ids = $this->{$field}->pluck('id');
                         foreach ($value as $current) {
                             if (!is_a($current, $options['class_name'])) {
                                 throw new InvalidArgumentException(sprintf('relation %s expects object of type: %s', $field, $options['class_name']));
                             }
                             if ($options['type'] == 'has_many') {
                                 $foreign_key_value = call_user_func($options['assoc_func_params_func'], $this);
                                 call_user_func($options['assoc_foreign_key_setter'], $current, $foreign_key_value);
                             }
                             if ($current->id !== null) {
                                 $new_ids[] = $current->id;
                                 $existing = $this->{$field}->find($current->id);
                                 if ($existing) {
                                     $existing->mergeRelatedRecord($current);
                                 } else {
                                     $this->{$field}->append($current);
                                 }
                             } else {
                                 $this->{$field}->append($current);
                             }
                         }
                         foreach (array_diff($old_ids, $new_ids) as $to_delete) {
                             $this->{$field}->unsetByPK($to_delete);
                         }
                     } else {
                         throw new InvalidArgumentException(sprintf('relation %s expects collection or array of objects of type: %s', $field, $options['class_name']));
                     }
                 }
             }
         } else {
             throw new InvalidArgumentException(get_class($this) . '::'. $field . ' not found.');
         }
         return $ret;
     }

    /**
     * Merge another instance of a related record, including initialized relations.
     * Uninitialized relations must not discard pending changes on this instance.
     */
    protected function mergeRelatedRecord(SimpleORMap $record): void
    {
        $this->setData($record);
        if ($this === $record) {
            return;
        }

        foreach ($record->relations as $name => $relation) {
            if ($relation instanceof SimpleORMapCollection) {
                // Copy both active and deleted records
                $this->initRelation($name);
                $collection = $this->relations[$name];
                $collection->exchangeArray($relation->getArrayCopy());
                $collection->getDeleted()->exchangeArray($relation->getDeleted()->getArrayCopy());
            } elseif ($relation !== null) {
                $this->setValue($name, $relation);
            }
        }
    }

    /**
     * magic method for dynamic properties
     *
     * @throws InvalidArgumentException if column could not be found
     * @throws BadMethodCallException if getter for additional field could not be found
     * @param string $field the column or additional field
     * @return null|string|SimpleORMapCollection
     */
    function __get($field)
    {
        return $this->getValue($field);
    }
    /**
     * magic method for dynamic properties
     *
     * @throws InvalidArgumentException if column could not be found
     * @throws BadMethodCallException if setter for additional field could not be found
     * @param string $field
     * @param string $value
     * @return string
     */
    function __set($field, $value)
    {
        return $this->setValue($field, $value);
    }
    /**
     * magic method for dynamic properties
     *
     * @param string $field
     * @return bool
     */
    function __isset($field)
    {
        $field = strtolower($field);
        if (in_array($field, $this->known_slots())) {
            $value = $this->getValue($field);
            return $value instanceOf SimpleORMapCollection ? (bool)count($value) : !is_null($value);
        } else {
            return false;
        }
    }

    /**
     * ArrayAccess: Check whether the given offset exists.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * ArrayAccess: Get the value at the given offset.
     *
     * @throws InvalidArgumentException if column could not be found
     * @throws BadMethodCallException if getter for additional field could not be found
     * @param string $offset the column or additional field
     * @return null|string|SimpleORMapCollection
     */
    public function offsetGet($offset): mixed
    {
        return $this->getValue($offset);
    }

    /**
     * ArrayAccess: Set the value at the given offset.
     *
     * @throws InvalidArgumentException if column could not be found
     * @throws BadMethodCallException if setter for additional field could not be found
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->setValue($offset, $value);
    }

    /**
     * ArrayAccess: unset the value at the given offset (not applicable)
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {

    }

    /**
     * IteratorAggregate
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * Countable
     */
    public function count(): int
    {
        return count($this->known_slots()) - count($this->relations);
    }

    /**
     * check if given column exists in table
     * @param string $field
     * @return boolean
     */
    function isField($field)
    {
        $field = strtolower($field);
        return isset($this->db_fields()[$field]);
    }

    /**
     * check if given relation exists in this class
     * @param string $field
     * @return boolean
     */
    function isRelation($field)
    {
        $field = strtolower($field);
        return array_key_exists($field, $this->relations);
    }

    /**
     * check if given column is additional
     * @param string $field
     * @return boolean
     */
    function isAdditionalField($field)
    {
        $field = strtolower($field);
        return isset($this->additional_fields()[$field]);
    }

    /**
     * check if given column is an alias
     * @param string $field
     * @return boolean
     */
    function isAliasField($field)
    {
        $field = strtolower($field);
        return isset($this->alias_fields()[$field]);
    }

    /**
     * check if given column is a multi-language field
     * @param string $field
     * @return boolean
     */
    function isI18nField($field)
    {
        $field = strtolower($field);
        return isset($this->i18n_fields()[$field]);
    }

    /**
     * set multiple column values
     * if second param is set, existing data in object will be
     * discarded and dirty state is cleared,
     * else new data overrides old data
     *
     * @param ?iterable $data assoc array
     * @param ?boolean $reset existing data in object will be discarded
     * @return int|bool number of columns changed
     */
    function setData($data, $reset = false)
    {
        $count = 0;
        if ($reset) {
            if ($this->applyCallbacks('before_initialize') === false) {
                return false;
            }
            $this->initializeContent();
        }
        if ($data instanceof self) {
            $data = $data->toRawArray();
        }
        if (is_iterable($data)) {
            foreach ($data as $key => $value) {
                $key = strtolower($key);
                if (
                    isset($this->db_fields()[$key])
                    || isset($this->alias_fields()[$key])
                    || isset($this->additional_fields()[$key]['set'])
                ) {
                    $this->setValue($key, $value);
                    ++$count;
                }
            }
        }
        if ($reset) {
            $this->applyCallbacks('after_initialize');
        }
        return $count;
    }

    /**
     * check if object exists in database
     * @return boolean
     */
    function isNew()
    {
        return $this->is_new;
    }

    /**
     * check if object was deleted
     *
     * @return boolean
     */
    function isDeleted()
    {
        return $this->is_deleted;
    }

    /**
     * set object to new state
     * @param boolean $is_new
     * @return boolean
     */
    function setNew($is_new)
    {
        return $this->is_new = $is_new;
    }

    /**
     * returns sql clause with current table and pk
     * @throws UnexpectedValueException if the primary key is incomplete
     * @return boolean|array<string>
     */
    function getWhereQuery()
    {
        $where_query = null;
        $pk_not_set = [];
        foreach ($this->pk() as $key) {
            $pk = $this->content_db[$key] ?? $this->content[$key] ?? null;
            if (isset($pk)) {
                $where_query[] = "`{$this->db_table()}`.`{$key}` = "  . DBManager::get()->quote($pk);
            } else {
                $pk_not_set[] = $key;
            }
        }
        if (!$where_query || count($pk_not_set)){
            if ($this->isNew()) {
                return false;
            } else {
                throw new UnexpectedValueException(sprintf("primary key incomplete: %s must not be null", join(',',$pk_not_set)));
            }
        }
        return $where_query;
    }

    /**
     * restore entry from database
     * @return boolean
     */
    function restore()
    {
        $where_query = $this->getWhereQuery();
        $id = $this->getId();
        if ($where_query) {
            if ($this->applyCallbacks('before_initialize') === false) {
                return false;
            }
            $this->initializeContent();
            $query = "SELECT * FROM `{$this->db_table()}` WHERE "
                    . join(" AND ", $where_query);
            $st = DBManager::get()->prepare($query);
            $st->execute();
            $st->setFetchMode(PDO::FETCH_INTO , $this);
            if ($st->fetch()) {
                $this->setNew(false);
                $this->applyCallbacks('after_initialize');
                return true;
            }
        }
        $this->setData([], true);
        $this->setNew(true);
        if (isset($id)) {
            $this->setId($id);
        }
        return false;
    }

    /**
     * store entry in database
     *
     * @throws UnexpectedValueException if there are forbidden NULL values
     * @return number|boolean
     */
    function store()
    {
        // Set id or prepare setting of id
        if ($this->isNew() && $this->getId() === null) {
            // Explicitly set id to 0 if auto increment pk is null
            if ($this->hasAutoIncrementColumn()) {
                $this->setId(0);
            } else {
                $this->setId($this->getNewId());
            }
        }

        if ($this->applyCallbacks('before_store') === false) {
            return false;
        }

        $ret = 0;

        if (!$this->isDeleted() && ($this->isDirty() || $this->isNew())) {
            $callback = $this->isNew() ? 'before_create' : 'before_update';
            if ($this->applyCallbacks($callback) === false) {
                return false;
            }

            // Collect i18n contents
            $i18ncontent = [];
            foreach (array_keys($this->i18n_fields()) as $field) {
                if ($this->content[$field] instanceof I18NString) {
                    $i18ncontent[$field] = $this->content[$field];
                    $this->content[$field] = $this->content[$field]->original();
                    $this->content_db[$field] = $this->content_db[$field]->original();
                }
            }

            // Create sql data assignment chunks
            foreach ($this->db_fields() as $field => $meta) {
                $value = $this->content[$field];
                if ($field == 'chdate' && !$this->isFieldDirty($field) && $this->isDirty()) {
                    $value = time();
                }
                if ($field == 'mkdate') {
                    if ($this->isNew()) {
                        if (!$this->isFieldDirty($field)) {
                            $value = time();
                        }
                    } else {
                        continue;
                    }
                }
                if ($value === null && $meta['null'] == 'NO') {
                    throw new UnexpectedValueException($this->db_table() . '.' . $field . ' must not be null.');
                }
                if (is_float($value)) {
                    $value = str_replace(',', '.', $value);
                }
                $this->content[$field] = $value;
                $query_part[] = "`$field` = " . DBManager::get()->quote($value) . " ";
            }

            // Create store query
            if (!$this->isNew()) {
                $where_query = $this->getWhereQuery();
                $query = "UPDATE `{$this->db_table()}` SET "
                    . implode(',', $query_part);
                $query .= " WHERE " . join(" AND ", $where_query);
            } else {
                $query = "INSERT INTO `{$this->db_table()}` SET "
                    . implode(',', $query_part);
            }
            $ret = DBManager::get()->exec($query);

            // Retrieve generated id from database if pk is an auto increment
            // column
            if ($this->isNew()) {
                if ($this->hasAutoIncrementColumn() && !$this->getId()) {
                    $this->setId(DBManager::get()->lastInsertId());
                }
            }

            // Store i18n contents
            foreach ($i18ncontent as $field => $one) {
                $meta = [
                    'object_id' => $this->getId(),
                    'table'     => $this->db_table(),
                    'field'     => $field
                ];
                $one->setMetadata($meta);
                $one->storeTranslations();
                if (!$this->content[$field] instanceof I18NString) {
                    $this->content[$field] = $one;
                    $this->content_db[$field] = clone $one;
                }
            }

            // Apply callbacks
            $this->applyCallbacks($this->isNew() ? 'after_create' : 'after_update');
        }
        $rel_ret = $this->storeRelations();

        $this->applyCallbacks('after_store');

        if ($ret || $rel_ret) {
            $this->restore();
        }
        return $ret + $rel_ret;
    }

    /**
     * sends a store message to all initialized related objects
     * if a relation has a callback for 'on_store' configured, the callback
     * is instead invoked
     *
     * @param null|array|string $only_these
     * @return int|false number addition of all return values, false if none was called
     */
    protected function storeRelations($only_these = null)
    {
        $ret = false;
        if (is_string($only_these)) {
            $only_these = words($only_these);
        }
        $relations = array_keys($this->relations);
        if (is_array($only_these)) {
            $only_these = array_filter(array_map(function ($s) {
                return is_string($s) ? strtolower($s) : null;
            }, $only_these));
            $relations = array_intersect($only_these, $relations);
        }
        foreach ($relations as $relation) {
            $options = $this->getRelationOptions($relation);
            if (isset($options['on_store']) &&
            ($options['type'] === 'has_one' ||
            $options['type'] === 'has_many' ||
            $options['type'] === 'has_and_belongs_to_many')) {
                if ($options['on_store'] instanceof Closure) {
                    $ret += call_user_func($options['on_store'], $this, $relation);
                } elseif (isset($this->relations[$relation])) {
                    $foreign_key_value = call_user_func($options['assoc_func_params_func'], $this);
                    if ($options['type'] === 'has_one') {
                        call_user_func($options['assoc_foreign_key_setter'], $this->{$relation}, $foreign_key_value);
                        $ret = call_user_func([$this->{$relation}, 'store']);
                    } elseif ($options['type'] === 'has_many') {
                        foreach ($this->{$relation} as $r) {
                            call_user_func($options['assoc_foreign_key_setter'], $r, $foreign_key_value);
                        }
                        $ret += array_sum(call_user_func([$this->{$relation}, 'sendMessage'], 'store'));
                        $ret += array_sum(call_user_func([$this->{$relation}->getDeleted(), 'sendMessage'], 'delete'));
                    } else {
                        call_user_func([$this->{$relation}, 'sendMessage'], 'store');
                        $to_delete = array_filter($this->{$relation}->getDeleted()->pluck($options['assoc_foreign_key']));
                        $to_insert = array_filter($this->{$relation}->pluck($options['assoc_foreign_key']));
                        $sql = "DELETE FROM `" . $options['thru_table'] ."` WHERE `" . $options['thru_key'] ."` = ? AND `" . $options['thru_assoc_key'] . "` = ?";
                        $st = DBManager::get()->prepare($sql);
                        foreach ($to_delete as $one_value) {
                            $st->execute([$foreign_key_value, $one_value]);
                            $ret += $st->rowCount();
                        }
                        $sql = "INSERT IGNORE INTO `" . $options['thru_table'] ."` SET `" . $options['thru_key'] ."` = ?, `" . $options['thru_assoc_key'] . "` = ?";
                        $st = DBManager::get()->prepare($sql);
                        foreach ($to_insert as $one_value) {
                            $st->execute([$foreign_key_value, $one_value]);
                            $ret += $st->rowCount();
                        }
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * set chdate column to current timestamp
     * @return boolean
     */
    function triggerChdate()
    {
        if ($this->db_fields()['chdate']) {
            $this->content['chdate'] = time();
            if ($where_query = $this->getWhereQuery()) {
                DBManager::get()->exec("UPDATE `{$this->db_table()}` SET chdate={$this->content['chdate']}
                            WHERE ". join(" AND ", $where_query));
                return true;
            }
        }

        return false;
    }

    /**
     * delete entry from database
     * the object is cleared, but is not(!) turned to new state
     * @return bool|int number of deleted rows
     */
    function delete()
    {
        $ret = false;
        if (!$this->isDeleted() && !$this->isNew()) {
            if ($this->applyCallbacks('before_delete') === false) {
                return false;
            }
            $ret = $this->deleteRelations();
            $where_query = $this->getWhereQuery();
            if ($where_query) {
                $query = "DELETE FROM `{$this->db_table()}` WHERE "
                        . join(" AND ", $where_query);
                $ret += DBManager::get()->exec($query);
            }
            $this->is_deleted = true;
            $this->applyCallbacks('after_delete');

            // Remove i18n translations
            if (I18N::isEnabled()) {
                foreach (array_keys($this->i18n_fields()) as $field) {
                    if ($this->content[$field] instanceof I18NString) {
                        $this->content[$field]->removeTranslations();
                    }
                }
            }
        }
        $this->setData([], true);
        return $ret;
    }

    /**
     * sends a delete message to all related objects
     * if a relation has a callback for 'on_delete' configured, the callback
     * is invoked instead
     *
     * @return bool|int addition of all return values, false if none was called
     */
    protected function deleteRelations()
    {
        $ret = false;
        foreach (array_keys($this->relations) as $relation) {
            $options = $this->getRelationOptions($relation);
            if (isset($options['on_delete']) &&
                ($options['type'] === 'has_one' ||
                $options['type'] === 'has_many' ||
                $options['type'] === 'has_and_belongs_to_many')) {
                if ($options['on_delete'] instanceof Closure) {
                    $ret += call_user_func($options['on_delete'], $this, $relation);
                } else {
                    if ($options['type'] === 'has_one' || $options['type'] === 'has_many') {
                        $this->initRelation($relation);
                        if (isset($this->relations[$relation])) {
                            if ($options['type'] === 'has_one') {
                                $ret += call_user_func([$this->{$relation}, 'delete']);
                            } elseif ($options['type'] === 'has_many') {
                                $ret += array_sum(call_user_func([$this->{$relation}, 'sendMessage'], 'delete'));
                            }
                        }
                    } else {
                        $foreign_key_value = call_user_func($options['assoc_func_params_func'], $this);
                        $sql = "DELETE FROM `" . $options['thru_table'] ."` WHERE `" . $options['thru_key'] ."` = ?";
                        $st = DBManager::get()->prepare($sql);
                        $st->execute([$foreign_key_value]);
                        $ret += $st->rowCount();
                    }
                }
                $this->relations[$relation] = null;
            }
        }
        return $ret;
    }

    /**
     * init internal content arrays with nulls or defaults
     *
     * @throws UnexpectedValueException if there is an unmatched alias
     * @return void
     */
    protected function initializeContent()
    {
        $this->content = [];
        foreach (array_keys($this->db_fields()) as $field) {
            $this->content[$field] = null;
            $this->content_db[$field] = null;
            $this->setValue($field, $this->getDefaultValue($field));
        }
        foreach ($this->alias_fields() as $alias => $field) {
            if (isset($this->db_fields()[$field])) {
                $this->content[$alias] =& $this->content[$field];
                $this->content_db[$alias] =& $this->content_db[$field];
            } else {
                throw new UnexpectedValueException(sprintf('Column %s not found for alias %s', $field, $alias));
            }
        }
        foreach (array_keys($this->relations) as $one) {
            $this->relations[$one] = null;
        }
        $this->additional_data = [];
    }

    /**
     * checks if at least one field was modified since last restore
     *
     * @return boolean
     */
    public function isDirty()
    {
        foreach (array_keys($this->db_fields()) as $field) {
            if ($this->isFieldDirty($field)) {
                return true;
            }
        }
        return false;
    }

    /**
     * checks if given field was modified since last restore
     *
     * @param string $field
     * @return boolean
     */
    public function isFieldDirty($field)
    {
        $field = strtolower($field);
        if ($this->content[$field] === null || $this->content_db[$field] === null) {
            return $this->content[$field] !== $this->content_db[$field];
        } else if (
            $this->content[$field] instanceof I18NString
            || $this->content_db[$field] instanceof I18NString
        ) {
            // Trigger loading of translations
            if ($this->content[$field] instanceof I18NString) {
                $this->content[$field]->toArray();
            }
            if ($this->content_db[$field] instanceof I18NString) {
                $this->content_db[$field]->toArray();
            }

            return $this->content[$field] != $this->content_db[$field];
        } else {
            return (string)$this->content[$field] !== (string)$this->content_db[$field];
        }
    }

    /**
     * reverts value of given field to last restored value
     *
     * @param string $field
     * @return mixed the restored value
     */
    public function revertValue($field)
    {
        $field = strtolower($field);
        return ($this->content[$field] = $this->content_db[$field]);
    }

    /**
     * returns unmodified value of given field
     *
     * @param string $field
     * @throws InvalidArgumentException
     * @return mixed
     */
    public function getPristineValue($field)
    {
        $field = strtolower($field);
        if (array_key_exists($field, $this->content_db)) {
            return $this->content_db[$field];
        } else {
            throw new InvalidArgumentException(get_class($this) . '::'. $field . ' not found.');
        }
    }

    /**
     * intitalize a relationship and get related record(s)
     *
     * @param string $relation name of relation
     * @throws InvalidArgumentException if the relation does not exists
     * @return void
     */
    public function initRelation($relation)
    {
        if (!array_key_exists($relation, $this->relations)) {
            throw new InvalidArgumentException('Unknown relation: ' . $relation);
        }
        if ($this->relations[$relation] === null) {
            $options = $this->getRelationOptions($relation);

            $to_call = [$options['class_name'], $options['assoc_func']];
            if (!is_callable($to_call)) {
                throw new RuntimeException('assoc_func: ' . join('::', $to_call) . ' is not callable.' );
            }

            $params = $options['assoc_func_params_func'];
            if ($options['type'] === 'has_many') {
                $records = function ($record) use ($to_call, $params, $options) {
                    $p = (array) $params($record);
                    return call_user_func_array(
                        $to_call,
                        array_merge(
                            count($p) ? $p : [null],
                            [empty($options['order_by_php']) ? $options['order_by'] ?? null : null]
                        )
                    );
                };
                $this->relations[$relation] = new SimpleORMapCollection($records, $options, $this);
            } elseif ($options['type'] === 'has_and_belongs_to_many') {
                $records = function ($record) use ($to_call, $params, $options) {
                    $p = (array) $params($record);
                    return call_user_func_array(
                        $to_call,
                        array_merge(
                            count($p) ? $p : [null],
                            [$options]
                        )
                    );
                };
                $this->relations[$relation] = new SimpleORMapCollection($records, $options, $this);
            } else {
                $p = (array) $params($this);
                $records = call_user_func_array($to_call, count($p) ? $p : [null]);
                $result = is_array($records) ? ($records[0] ?? null) : $records;
                $this->relations[$relation] = $result;
            }

            if (
                !empty($options['order_by_php'])
                && $this->relations[$relation] instanceof SimpleORMapCollection
            ) {
                $order_options = (array) $options['order_by_php'];
                $this->relations[$relation]->orderBy(
                    $options['order_by_php'][0],
                    $options['order_by_php'][1] ?? SORT_LOCALE_STRING,
                );

            }
        }
    }

    /**
     * clear data for a relationship
     *
     * @param string $relation name of relation
     * @throws InvalidArgumentException if teh relation does not exists
     * @return void
     */
    public function resetRelation($relation)
    {
        if (!array_key_exists($relation, $this->relations)) {
            throw new InvalidArgumentException('Unknown relation: ' . $relation);
        }
        $this->relations[$relation] = null;
    }

    /**
     * invoke registered callbacks for given type
     * if one callback returns false the following will not
     * be invoked
     *
     * @param string $type type of callback
     * @return bool return value from last callback
     */
    protected function applyCallbacks($type)
    {
        $ok = true;
        foreach ($this->registered_callbacks()[$type] as $cb) {
            if ($cb instanceof Closure) {
                $function =  $cb;
                $params = [$this, $type, $cb];
            } else {
                $function = [$this, $cb];
                $params = [$type];
            };
            $ok = call_user_func_array($function, $params);
            if ($ok === false) {
                break;
            }
        }
        return $ok;
    }

    /**
     * register given callback for one or many possible callback types
     * callback param could be a closure or method name of current class
     *
     * @param string|array $types types to register callback for
     * @param callable $cb callback
     * @throws InvalidArgumentException if the callback type is not known
     * @return number of registered callbacks
     */
    protected static function registerCallback($types, $cb)
    {
        trigger_error(__METHOD__ . ' is deprecated. Please use the configuration in configure().', E_USER_DEPRECATED);

        $types = is_array($types) ? $types : words($types);
        $reg = 0;
        foreach ($types as $type) {
            if (isset(static::registered_callbacks()[$type])) {
                $found = array_search($cb, self::$config[static::class]['registered_callbacks'][$type], true);
                if ($found === false) {
                    self::$config[static::class]['registered_callbacks'][$type][] = $cb;
                    $reg++;
                }
            } else {
                throw new InvalidArgumentException('Unknown callback type: ' . $type);
            }
        }
        return $reg;
    }

    /**
     * unregister given callback for one or many possible callback types
     *
     * @param string|array $types types to unregister callback for
     * @param mixed $cb
     * @throws InvalidArgumentException if the callback type is not known
     * @return number of unregistered callbacks
     */
    protected static function unregisterCallback($types, $cb)
    {
        trigger_error(__METHOD__ . ' is deprecated. Please use the configuration in configure().', E_USER_DEPRECATED);

        $types = is_array($types) ? $types : words($types);
        foreach ($types as $type) {
            if (isset(static::registered_callbacks()[$type])) {
                $found = array_search($cb, self::$config[static::class]['registered_callbacks'][$type], true);
                if ($found !== false) {
                    $unreg++;
                    unset(self::$config[static::class]['registered_callbacks'][$type][$found]);
                }
            } else {
                throw new InvalidArgumentException('Unknown callback type: ' . $type);
            }
        }
        return $unreg;
    }

    /**
     * default callback used to map specific callbacks to NotificationCenter
     *
     * @param string $cb_type callback type
     * @return void|boolean
     */
    protected function cbNotificationMapper($cb_type)
    {
        if (isset($this->notification_map()[$cb_type])) {
            try {
                foreach(words($this->notification_map()[$cb_type]) as $notification) {
                    NotificationCenter::postNotification($notification, $this);
                }
            } catch (NotificationVetoException $e) {
                return false;
            }
        }
    }

    /**
     * default callback used to map specific callbacks to NotificationCenter
     *
     * @param string $cb_type callback type
     * @return void|boolean
     */
    protected function cbAfterInitialize($cb_type)
    {
        foreach (array_keys($this->db_fields()) as $field) {
            if (is_object($this->content[$field])) {
                $this->content_db[$field] = clone $this->content[$field];
            } else {
                $this->content_db[$field] = $this->content[$field];
            }
        }
    }

    /**
     * default setter used to proxy serialized fields with
     * ArrayObjects
     *
     * @param string $field column name
     * @param mixed $value value
     * @return mixed
     */
     protected function setSerializedValue($field, $value)
     {
         $object_type = $this->serialized_fields()[$field];
         if (is_null($value) || $value instanceof $object_type) {
             $this->content[$field] = $value;
         } else {
             $this->content[$field] = new $object_type($value);
         }
         return $this->content[$field];
     }

    /**
     * default setter used to proxy I18N fields with
     * I18NString
     *
     * @param string $field column name
     * @param mixed $value value
     * @return mixed
     */
    protected function setI18nValue($field, $value)
    {
        $meta = ['object_id' => $this->getId(),
                 'table'     => $this->db_table(),
                 'field'     => $field];
        if ($value instanceof I18NString) {
            $value->setMetadata($meta);
            $this->content[$field] = $value;
        } else {
            $this->content[$field] = new $this->i18n_class($value, null, $meta);
        }
        return $this->content[$field];
    }

     /**
      * Cleans up this object. This essentially reset all relations of
      * this object and marks them as unused so that the garbage collector may
      * remove the objects.
      *
      * Use this function when you ran into memory problems and need to free
      * some memory;
      *
      * @return void
      */
     public function cleanup()
     {
         foreach ($this->relations as $relation => $object) {
             if ($object instanceof SimpleORMap || $object instanceof SimpleORMapCollection) {
                 $this->relations[$relation]->cleanup();
             }
             $this->resetRelation($relation);
         }
     }

    /**
     * Enable/disable the fix for mariadb >= 10.2.7 default column values.
     */
     public static function setMariadbDefaultColumnFix(bool $state = true): void
     {
         self::$mariadb_column_default_fix = $state;
     }
}
