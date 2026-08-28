<?php
/**
 * ModuleManagementModel.php
 * Parent class of all MVV-Models
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Peter Thienel <thienel@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       3.5
 */

require_once 'config/mvv_config.php';

abstract class ModuleManagementModel extends SimpleORMap implements ModuleManagementInterface
{
    protected static $filter_params = [];
    protected $is_dirty = false;
    private static $language = null;
    protected static $perm_object = null;
    public $object_real_name = '';

    protected static $object_cache = [];

    /**
     * Common configuration for all module management models. This will ensure
     * that the caches are flushed whenever an object changes or is removed.
     *
     * @param array  $config Configuration from derived class
     */
    protected static function configure($config = [])
    {
        $config['registered_callbacks']['after_store'][] = function () {
            self::clearCache();
        };
        $config['registered_callbacks']['after_delete'][] = function () {
            self::clearCache();
        };

        parent::configure($config);
    }

    /**
     * Returns a collection of a MVV object type found by search term optionally
     * filtered.
     *
     * @see ModuleManagementModel::getFilterSql()
     * @param string $search_term The term to search for.
     * @param array $filter Filter parameters as key value pairs.
     * @return SimpleORMapCollection A collection of "self" objects.
     */
    public static function findBySearchTerm($search_term, $filter = null)
    {
        return new SimpleORMapCollection();
    }

    /**
     * Returns an array of all objects of "self" type.
     *
     * @return array An array of "self" objects.
     */
    public static function getAll()
    {
        return parent::findBySQL('1');
    }

    /**
     * Returns an object by given id or a new object.
     *
     * @param string $id The id of the object.
     * @return ModuleManagementModel An object of "self" type.
     */
    public static function get($id = null)
    {
        return static::findCached($id)
            ?? new static($id);
    }

    /**
     * Returns an object by given id with all relations and additional fields.
     *
     * @param string $id The id of the object.
     * @return ModuleManagementModel
     */
    public static function getEnriched($id)
    {
        return parent::find($id);
    }

    /**
     * Verifies whether the given user has sufficient rights to create, modify
     * or delete this object and throws an exception if not.
     *
     * @param string $user_id The user's id.
     * @return boolean True if rights are sufficient
     * @throws Exception if rights are not sufficient.
     */
    public function verifyPermission($user_id = null)
    {
        $user_id = $user_id ?: $GLOBALS['user']->id;
        $perm = MvvPerm::get($this);
        // PERM_CREATE means a permission to store a new one or delete one
        if ($this->isNew() || $this->isDeleted()) {
            if (!$perm->haveObjectPerm(MvvPerm::PERM_CREATE, $user_id)) {
                throw new Exception(sprintf(
                    'Permission denied! The user is not allowed to '
                    . 'create/delete an object of type %s.', static::class));
            }
        } else {
            if (!$perm->haveObjectPerm(MvvPerm::PERM_WRITE, $user_id)) {
                throw new Exception(sprintf(
                    'Permission denied! The user is not allowed to store an '
                    . 'object of type %s', static::class));
            }
        }

        // check the permissions for every single db field except primary keys
        if ($this->isNew()) {
            $fields = array_diff(array_keys($this->db_fields()),
                    array_values($this->pk()));
        } else {
            $fields = array_keys($this->db_fields());
        }
        foreach ($fields as $field) {
            if ($this->isFieldDirty($field)
                    && !$perm->haveFieldPerm($field, MvvPerm::PERM_WRITE, $user_id)) {
                throw new Exception(sprintf(
                        'Permission denied! The user is not allowed to change '
                        . 'value of field %s.%s.', static::class, $field));
            }
        }

        // check the permissions for every single relation
        foreach (array_keys($this->relations) as $relation) {
            $options = $this->getRelationOptions($relation);
            if ((isset($options['on_store']) || isset($options['on_delete'])) &&
            ($options['type'] === 'has_one' ||
            $options['type'] === 'has_many' ||
            $options['type'] === 'has_and_belongs_to_many')) {
                if (isset($this->relations[$relation])) {
                    if ($options['type'] === 'has_one') {
                        $this->checkRelation($relation, $this->{$relation}, $perm, $user_id);
                    } else {
                        // datafields gets special treatment...
                        if ($relation == 'datafields') {
                            foreach ($this->datafields as $entry) {
                                if ($entry->isNew() || $entry->isDirty()) {
                                    if (!$perm->haveDfEntryPerm($entry->datafield_id, MvvPerm::PERM_WRITE)) {
                                        throw new Exception(sprintf(
                                            'Permission denied! The user is not '
                                            . 'allowed to change value of field %s::datafields[%s] ("%s").', static::class, $entry->datafield_id, $entry->datafield->name));
                                    }
                                }
                            }
                        } else {
                            foreach ($this->{$relation} as $r) {
                                $this->checkRelation($relation, $r, $perm, $user_id);
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Checks the rights for a relation.
     *
     * @param string $relation_name Field name of relation.
     * @param ModuleManagementModel $relation_object
     * @param int $perm
     * @param type $user_id
     * @return boolean
     * @throws Exception
     */
    private function checkRelation($relation_name, $relation_object, $perm, $user_id)
    {
        if (($relation_object->isNew() || $relation_object->isDeleted())
                && !$perm->haveFieldPerm($relation_name, MvvPerm::PERM_CREATE, $user_id)) {
            throw new Exception(sprintf(
                'Permission denied! The user is not allowed to create/delete a relation %s::%s.',
                get_class($relation_object), $relation_name));
        } elseif ($relation_object->isDirty()) {
            if ($relation_object instanceof ModuleManagementModel) {
                $relation_object->verifyPermission($user_id);
            } elseif (!$perm->haveFieldPerm($relation_name, MvvPerm::PERM_WRITE, $user_id)) {
                throw new Exception(sprintf(
                    'Permission denied! The user is not allowed to modify a relation %s::%s.',
                    get_class($relation_object), $relation_name));
            }
        }
        return true;
    }

    /**
     * @see SimpleOrMap::store()
     * Optional validation of values. Triggers logging of changes.
     *
     * @param boolean $validate True to validate values.
     */
    public function store(/* $validate = true */)
    {
        $validate = true;
        if (func_num_args() > 0) {
            $validate = func_get_arg(0) !== false;
        }
        if ($validate) {
            $this->validate();
        }
        if ($this->isNew() || $this->isDirty()) {
            $this->editor_id = $GLOBALS['user']->id;
        }

        $stored = false;
        if ($this->isNew()) {
            $this->author_id = $GLOBALS['user']->id;
            $stored = parent::store();
            if ($stored) {
                $this->logChanges('new');
            }
        } else {
            $this->logChanges('update');
            $stored = parent::store();
        }
        return $stored;
    }

    /**
     * Validates the values before store. Throws an InvalidValuesException
     * normally catched by form validation.
     *
     * @throws InvalidValuesException
     */
    public function validate() {

    }

    /**
     * @see SimpleOrMap::delete()
     * Triggers logging.
     */
    public function delete() {
        $this->logChanges('delete');
        return parent::delete();
    }

    /**
     * Logs all changes of this object.
     *
     * @param string $action new, update or delete
     */
    protected function logChanges ($action = null) {

        switch ($this->db_table()) {
            case 'abschluss' :
                $logging = 'MVV_ABSCHLUSS';
                $num_index = 1;
                break;
            case 'mvv_abschl_kategorie' :
                $logging = 'MVV_KATEGORIE';
                $num_index = 1;
                break;
            case 'mvv_abschl_zuord' :
                $logging = 'MVV_ABS_ZUORD';
                $num_index = 2;
                break;
            case 'mvv_dokument' :
                $logging = 'MVV_DOKUMENT';
                $num_index = 1;
                break;
            case 'mvv_dokument_zuord' :
                $logging = 'MVV_DOK_ZUORD';
                $num_index = 3;
                break;
            case 'fach' :
                $logging = 'MVV_FACH';
                $num_index = 1;
                break;
            case 'mvv_fachberater' :
                $logging = 'MVV_FACHBERATER';
                $num_index = 2;
                break;
            case 'mvv_fach_inst' :
                $logging = 'MVV_FACHINST';
                $num_index = 2;
                break;
            case 'mvv_lvgruppe' :
                $logging = 'MVV_LVGRUPPE';
                $num_index = 1;
                break;
            case 'mvv_lvgruppe_modulteil' :
                $logging = 'MVV_LVMODULTEIL';
                $num_index = 2;
                break;
            case 'mvv_lvgruppe_seminar' :
                $logging = 'MVV_LVSEMINAR';
                $num_index = 2;
                break;
            case 'mvv_modul' :
                $logging = 'MVV_MODUL';
                $num_index = 1;
                break;
            case 'mvv_modulteil' :
                $logging = 'MVV_MODULTEIL';
                $num_index = 1;
                break;
            case 'mvv_modulteil_deskriptor' :
                $logging = 'MVV_MODULTEIL_DESK';
                $num_index = 1;
                break;
            case 'mvv_modulteil_language' :
                $logging = 'MVV_MODULTEIL_LANG';
                $num_index = 2;
                break;
            case 'mvv_modulteil_stgteilabschnitt' :
                $logging = 'MVV_MODULTEIL_STGTEILABS';
                $num_index = 3;
                break;
            case 'mvv_modul_deskriptor' :
                $logging = 'MVV_MODUL_DESK';
                $num_index = 1;
                break;
            case 'mvv_modul_inst' :
                $logging = 'MVV_MODULINST';
                $num_index = 2;
                break;
            case 'mvv_modul_language' :
                $logging = 'MVV_MODUL_LANG';
                $num_index = 2;
                break;
            case 'mvv_modul_user' :
                $logging = 'MVV_MODUL_USER';
                $num_index = 3;
                break;
            case 'mvv_stgteil' :
                $logging = 'MVV_STGTEIL';
                $num_index = 1;
                break;
            case 'mvv_stgteilabschnitt' :
                $logging = 'MVV_STGTEILABS';
                $num_index = 1;
                break;
            case 'mvv_stgteilabschnitt_modul' :
                $logging = 'MVV_STGTEILABS_MODUL';
                $num_index = 2;
                break;
            case 'mvv_stgteilversion' :
                $logging = 'MVV_STGTEILVERSION';
                $num_index = 1;
                break;
            case 'mvv_stgteil_bez' :
                $logging = 'MVV_STGTEILBEZ';
                $num_index = 1;
                break;
            case 'mvv_stg_stgteil' :
                $logging = 'MVV_STG_STGTEIL';
                $num_index = 3;
                break;
            case 'mvv_studiengang' :
                $logging = 'MVV_STUDIENGANG';
                $num_index = 1;
                break;
            default:
                return;
        }

        if ($logging) {

            $aff = null;
            $coaff = null;
            $debuginfo =null;

            switch ($action) {
                case 'new':
                    $logging .= '_NEW';
                    $debuginfo = $this->getDisplayName();
                    break;
                case 'update':
                    $logging .= '_UPDATE';
                    break;
                case 'delete':
                    $logging .= '_DEL';
                    $debuginfo = $this->getDisplayName();
                    break;
                default:
                    return;
            }

            $id_array = $this->getId();
            switch ($num_index) {
                case '1':
                    $aff = $id_array;
                    break;
                case '2':
                    $aff = $id_array[0];
                    $coaff = $id_array[1];
                    break;
                case '3':
                    $aff = $id_array[0];
                    $coaff = $id_array[1];
                    $debuginfo = $id_array[2];
                    break;
                default:
                    return;
            }

            if ($action == 'update') {
                foreach ($this->content as $name => $value) {
                    if ($name == 'author_id' || $name == 'editor_id' || $name == 'mkdate' || $name == 'chdate' ) {
                        continue;
                    }
                    if ($this->isFieldDirty($name)) {
                        $info = ($num_index == 3) ? $debuginfo.';'.$value : $value;
                        StudipLog::log($logging, $aff, $coaff, $this->db_table().'.'.$name, $info);
                    }
                }
            } else {
                StudipLog::log($logging, $aff, $coaff, $this->db_table(), $debuginfo);
            }

            return;
        }
        return;
    }

    /**
     * Sets a new id for this object.
     */
    public function setNewId()
    {
        $this->setId($this->getNewId());
    }

    /**
     * Enriches the model with data from other joined tables.
     *
     * @param string $query complete sql with all fields in select statement
     * from main table
     * @param array $params Array with the parameters used in query
     * @param int $row_count Number of rows to return
     * @param int $offset Offset where the result set starts
     * @return SimpleOrMapCollection with all found objects or empty array
     */
    public static function getEnrichedByQuery($query = null, $params = [],
            $row_count = null, $offset = null)
    {
        $enriched = [];
        $params = array_merge($params, self::$filter_params);
        self::$filter_params = [];
        if (!is_null($query)) {
            if (!is_null($row_count)) {
                $limit_sql = ' LIMIT ?';
                $params[] = intval($row_count);
                if (!is_null($offset)) {
                    $limit_sql .= ' OFFSET ?';
                    $params[] = intval($offset);
                }
            } else {
                $limit_sql = '';
            }
            $stmt = DBManager::get()->prepare($query . $limit_sql);
            $stmt->execute($params);
            $model_object = new static();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $data) {
                $pkey = [];
                foreach (static::pk() as $pk) {
                    $pkey[]= $data[static::db_fields()[$pk]['name']];
                }
                $data_object = clone $model_object;
                foreach ($data as $key => $value) {
                    if (isset($data_object->db_fields()[$key])) {
                        $data_object->setValue($key, $value);
                    } else {
                        $data_object->content[mb_strtolower($key)] = $value;
                        $data_object->content_db[mb_strtolower($key)] = $value;
                    }
                }
                $data_object->setId($pkey);
                $data_object->setNew(false);
                $enriched[join('', $pkey)] = $data_object;
            }
        }
        return SimpleORMapCollection::createFromArray($enriched);
    }

    /**
     * Returns the name of the object to display in a specific context. The
     * default is the value from the db fields "name" or "bezeichnung" or an
     * empty string if no such fields exist. This method is overwritten by most
     * of the mvv objects to display more complex names glued together from
     * fields of related objects.
     *
     * @return string The display name for this object
     */
    public function getDisplayName()
    {
        if ($this->isField('name')) {
            return (string) $this->getValue('name');
        }
        if ($this->isField('bezeichnung')) {
            return (string) $this->getValue('bezeichnung');
        }
        return '';
    }

    /**
     * Returns the display name of this class.
     *
     * @return string the display name of this class
     */
    public static function getClassDisplayName($long = false)
    {
        return 'Module Management Model';
    }

    /**
     * Creates a sql clause to set constraints in a query by given filters.
     *
     * The values of the given filters can be either a scalar value or an array.
     *
     * If the value of the given filter is '__undefined__' then it matches
     * against NULL or an empty string.
     *
     * If the column name has
     * a comparison operator at its end (delimited by a blank), this operator
     * is used.
     *
     * To filter for semesters the column name has to be 'start_sem.beginn' for
     * the start semester and 'end_sem.ende' for the end semester according to
     * the joins with the semester_data table and table aliases in the sql
     * statement.
     *
     * @param array $filter An associative array with filters where the key is
     * the column name to filter against the given value.
     * @param bool $where if true returns a complete where statement
     * @param string SQL-where part glued with an "OR" at the end of the
     * filter sql part.
     * @return string The sql clause
     */
    public static function getFilterSql($filter, $where = false, $or_sql = null)
    {
        $sql_parts = [];
        foreach ((array) $filter as $col => $val) {
            $col = trim($col);
            if (is_array($val)) {
                if (sizeof($val)) {
                    $sql_parts[] = $col . ' IN('
                        . join(',', array_map(
                            function ($val) {
                                return DBManager::get()->quote($val);
                            }, $val))
                        . ') ';
                }
            } else if (trim($val)) {
                if ($val == '__undefined__') {
                    $sql_parts[] = '(ISNULL(' . $col . ') OR ' . $col . " = '')";
                } else {
                    if (preg_match('/([\w\.]+)\s+([\<\>\!]\=?)/', $col, $matches)) {
                        $sql_parts[] = trim($matches[1]) . ' ' . $matches[2] . ' '
                                . DBManager::get()->quote($val) . ' ';
                    } else if ($col == 'start_sem.beginn') {
                        if ((int) $val >= 0) {
                            // start semester filter for Module, Studiengaenge, ...
                            $sql_parts[] = '(start_sem.beginn <= '
                                    . DBManager::get()->quote($val)
                                    . ' OR ISNULL(start_sem.beginn))';
                        }
                    } else if ($col == 'end_sem.ende') {
                        if ((int) $val >= 0) {
                            // end semester filter for Module, Studiengaenge, ...
                            $sql_parts[] = '(end_sem.ende >= '
                                    . DBManager::get()->quote($val)
                                    . ' OR ISNULL(end_sem.ende))';
                        }
                    } else {
                        $sql_parts[] = $col . ' = '
                                . DBManager::get()->quote($val) . ' ';
                    }
                }
            }
        }
        $sql = implode(' AND ', $sql_parts);
        if (mb_strlen($sql)) {
            if ($or_sql) {
                $sql = '(' . $sql . ') OR (' . $or_sql . ')';
            }
            $sql = $where ? ' WHERE (' . $sql . ') ' : ' AND (' . $sql . ') ';
        }
        return $sql;
    }

    /**
     * Verifies a field name or an array of field names if they are permitted for
     * sorting the result. If ok returns the given sort fields. If not, returns
     * the given standard_field or null.
     *
     * @param string|array $sort the fields to check
     * @param string $additional_fields additional allowed fields
     * @return string|null the verified sort fields
     */
    protected static function checkSortFields($sort, $standard_field = null,
            $additional_fields = [])
    {
        if (!is_array($sort)) {
            $sort = explode(',', $sort);
        }
        if (sizeof(array_intersect(
                array_merge(array_keys(static::db_fields()), $additional_fields),
                $sort))) {
            return implode(',', $sort);
        }
        return $standard_field;
    }

    /**
     * Checks for valid fields and creates a sort statement for queries.
     *
     * @param string|array $sort The field(s) to sort by.
     * @param string $order The direction (ASC|DESC)
     * @param string $standard_field
     * @param array $additional_fields Calculated columns.
     * @return string The sort query part.
     */
    protected static function createSortStatement($sort, $order = 'ASC',
            $standard_field = null, $additional_fields = [])
    {
        $order = mb_strtoupper(trim($order)) !== 'DESC' ? ' ASC' : ' DESC';
        if (!is_array($sort)) {
            $sort = explode(',', $sort);
        }
        $sort = array_map('trim', $sort);
        $allowed_fields = array_intersect(
            $sort,
            array_merge(
                array_keys(static::db_fields()),
                $additional_fields
            )
        );
        if (count($allowed_fields) > 0) {
            return implode("{$order},", $allowed_fields) . $order;
        }
        return $standard_field;
    }

    /**
     * Returns an SimpleOrMap object as an Array.
     * Its like a static version of SimpleOrMap::toArray but
     * returns all content fields. Usefull as callback function.
     *
     * @param SimpleORMap $sorm The SimpleOrMap object to transform
     * @param bool $to_utf8 If true (default), the data will be utf8 transformed.
     * @return array The array with all content fields from object.
     */
    public static function getContentArray(SimpleORMap $sorm, $to_utf8 = true)
    {
        return $sorm->contentToArray($to_utf8);
    }

    /**
     * Returns the number of objects of this type. Optionally reduced by
     * filter criteria.
     *
     * @param array An array with filter criteria.
     * See ApplicationSimpleORMap::getFilter().
     * @return int The number of rows.
     */
    public static function getCount($filter = null)
    {
        if ($filter) {
            $filter_sql = self::getFilterSql($filter, true);
        } else {
            $filter_sql = '';
        }
        $db = DBManager::get()->query('SELECT COUNT(*) FROM '
                . static::db_table() . $filter_sql);
        return $db->fetchColumn(0);
    }

    /**
     * Returns the number of rows found by the given sql and filters.
     *
     * @param string $sql The sql query part.
     * @param array $filter An array of filters with respect to the query part.
     * @return int The number of rows.
     */
    public static function getCountBySql($sql, $filter = null)
    {
        $stmt = DBManager::get()->prepare($sql . self::getFilterSql($filter,
                true));
        $stmt->execute(self::$filter_params);
        return $stmt->fetchColumn(0);
    }

    /**
     * Sets the language for localized fields and the locale environment
     * globally.
     * See configuration of CONTENT_LANGUAGES.
     *
     * @see mvv_config.php
     * @param string $language The language code.
     */
    public static final function setContentLanguage($language)
    {
        $content_languages = Config::get()->CONTENT_LANGUAGES;
        if (empty($content_languages[$language])) {
            $language = Config::get()->DEFAULT_LANGUAGE;
        }
        setLocaleEnv($language);
        I18NString::setContentLanguage($language);
        self::$language = $language;
        // load config file again
        require $GLOBALS['STUDIP_BASE_PATH'] . '/config/mvv_config.php';
    }

    public function getAvailableTranslations(string $original_language): array
    {
        $translations = [];
        $stmt = DBManager::get()->prepare('SELECT DISTINCT `lang` '
                . 'FROM i18n '
                . 'WHERE `object_id` = ? AND `table` = ?');
        $stmt->execute([$this->id, $this->db_table()]);
        $languages = array_merge([$original_language],
            $stmt->fetchAll(PDO::FETCH_COLUMN));
        $content_languages = $GLOBALS['CONTENT_LANGUAGES'];
        foreach ($languages as $code) {
            if (!empty($content_languages[$code])) {
                $translations[] = $code;
            }
        }
        return $translations;
    }

    /**
     * Returns the currently selected language.
     *
     * @return string The currently selected language.
     */
    final public static function getLanguage()
    {
        return self::$language;
    }

    /**
     * Returns the suffix for ordinal numbers if the selected locale is EN or
     * a simple point if not.
     *
     * @param int $num
     * @return string The ordinal suffix or a point.
     */
    public static function getLocaleOrdinalNumberSuffix($num)
    {
        if (ModuleManagementModel::getLanguage() == 'EN') {
            if ($num % 100 < 11 || $num % 100 > 13) {
                switch ($num % 10) {
                    case 1:  return 'st';
                    case 2:  return 'nd';
                    case 3:  return 'rd';
                }
            }
            return 'th';
        }
        return '.';
    }

    /**
     * Returns an array of all values for given class with status "public"
     * defined by configuration.
     *
     * @return array Array of defined values for status "public".
     */
    public static function getPublicStatus($class_name = null)
    {
        $class_name = $class_name ?: static::class;
        $class_name = 'MVV_' . mb_strtoupper($class_name);
        $public_status = [];
        if (is_array($GLOBALS[$class_name]['STATUS']['values'])) {
            foreach ($GLOBALS[$class_name]['STATUS']['values'] as $key => $status) {
                if ($status['public']) {
                    $public_status[] = $key;
                }
            }
        }
        return $public_status;
    }

    /**
     * Returns the status of this object.
     * Some MVV objects have a status declared in mvv_config.php.
     *
     * @return string|null The status or null if the object has no status.
     */
    public function getStatus()
    {
        if ($this->isField('stat')) {
            return $this->stat;
        }
        return null;
    }

    /**
     * Returns whether this object has a public status. Public status means that
     * this object is public visible. The possible status are defined
     * in mvv_config.php. The set of possible status can be restricted by an
     * optional filter. Only the statis given in filter are checkrd.
     *
     * @param array $filter An array of status keys.
     * @return boolean True if object has an public status.
     */
    public function hasPublicStatus($filter = null)
    {
        $public_status = ModuleManagementModel::getPublicStatus(static::class);
        $filtered_status = $filter
                ? array_intersect(words($filter), $public_status)
                : $public_status;
        $status = $this->getStatus();
        return $status ? in_array($status, $filtered_status) : false;
    }

    public function getResponsibleInstitutes()
    {
        return [];
    }

    /**
     * Returns a string that identify a variant of this object. Returns an empty
     * string if no variant exists for this object.
     *
     * @return string String to identify a variant.
     */
    public function getVariant()
    {
        return '';
    }

    /**
     * Locates and returns an item from cache
     *
     * @param  string $id    Id of the item
     * @param  string $index Optional index for the cache, defaults to class name
     * @return ModuleManagementModel object or null
     */
    public static function findCached($id, $index = null)
    {
        // Prevent warnings
        if ($id === null || $id === false) {
            return null;
        }

        return self::fromCache($index ?? static::class, $id, function () use ($id) {
            return static::find($id);
        });
    }

    /**
     * Clears the cache for a given index or completely. If this method is
     * called on this abstract class, it will always clear the whole cache.
     * Otherwise it will clear the cache for the subclass of this class or
     * the given index.
     *
     * @param  mixed $index Optional index to clear
     */
    public static function clearCache($index = null)
    {
        if (static::class === self::class && $index === null) {
            static::$object_cache = [];
        } else {
            $index = $index ?? static::class;
            unset(static::$object_cache[$index]);
        }
    }

    /**
     * Loads an item from cache and retrieves and stores it if it is not
     * present.
     *
     * @param  string   $index  Index for the cache
     * @param  string   $id     Id of the item
     * @param  Callable $finder Function to actually locate the item.
     * @return ModuleManagementModel object or null
     */
    protected static function fromCache($index, $id, Callable $finder)
    {
        // Leave immeditately if $id cannot be used as array index
        if (!is_string($id) && !is_int($id)) {
            return $finder();
        }

        if (!isset(static::$object_cache[$index])) {
            static::$object_cache[$index] = [];
        }
        if (!array_key_exists($id, static::$object_cache[$index])) {
            static::$object_cache[$index][$id] = $finder();
        }
        return static::$object_cache[$index][$id];
    }

    /**
     * Formats the name of the object by given template and appropriate placeholders and replacements.
     *
     * @param string $template The template.
     * @param array $placeholders All placeholders (words) without regeexp stuff.
     * @param array $values Values to replace the placeholders. Empty strings will be ignored.
     * @return string The formatted name.
     * @throws Exception If the template is not usable.
     */
    protected static function formatDisplayName(
        string $template,
        array $placeholders,
        array $replacements
    ): string {
        if (mb_strlen($template) === 0) {
            return '';
        }
        $placeholders = array_map(
            function ($placeholder) {
                return '/^(.*?)' . $placeholder . '(.*?)$/';
            },
            $placeholders
        );
        $replacements = array_map(
            function ($replacement) {
                return mb_strlen($replacement) ? '${1}' . $replacement . '${2}' : '';
            },
            $replacements
        );
        $markup = new TextFormat();
        $markup->addMarkup(
            'mvv',
            '\{\{', '\}\}',
            function ($markup, $matches, $content) use ($placeholders, $replacements) {
                return preg_replace($placeholders, $replacements, $content);
            }
        );
        return $markup->format($template);
    }
}
