<?php

/**
 * UserFilterField.php
 *
 * A specification of a Stud.IP condition that must be fulfilled. One
 * or more instances of the UserFilterField subclasses make up a
 * UserFilter.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Thomas Hackl <thomas.hackl@uni-passau.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */
class UserFilterField
{
    // --- ATTRIBUTES ---

    /**
     * Which of the valid compare operators is currently chosen?
     */
    public $compareOperator = '';

    /**
     * ID of the UserFilter this field belongs to.
     */
    public $conditionId = '';

    /**
     * Unique ID for this condition field.
     */
    public $id = '';

    /**
     * The set of valid compare operators.
     */
    public $validCompareOperators = [];

    /**
     * All valid values for this field.
     */
    public $validValues = [];

    /**
     * Which of the valid values is currently chosen?
     */
    public $value = null;

    /*
     * Provide some kind of sort order for filter fields. By default,
     * all subclasses without an explicitly given order will be sorted at the end.
     */
    public static $sortOrder = 99;

    public static $isParameterized = false;

    public static $validContexts = [
        '',
        'MassMail'
    ];

    protected static $cached_valid_values;
    protected static $available_filter_fields;

    /**
     * Database tables and fields to get valid values and concrete user values
     * from.
     */
    public $valuesDbTable = '';
    public $valuesDbIdField = '';
    public $valuesDbNameField = '';
    public $userDataDbTable = '';
    public $userDataDbField = '';
    public $relations = [];

    // --- OPERATIONS ---

    public static function getParameterizedTypes()
    {

    }

    /**
     * Which targets are allowed for this filter field?
     * An empty array means: no restrictions
     * @return array
     */
    public static function getTargets()
    {
        return [];
    }

    /**
     * Indicates whether this filter field is active.
     * @return true
     */
    public static function isActive()
    {
        return true;
    }

    /**
     * Standard constructor.
     *
     * @param String $fieldId If a fieldId is given, the corresponding data is
     *                        loaded from database.
     *
     */
    public function __construct($fieldId = '')
    {
        $this->validCompareOperators = [
            '=' => _('ist'),
            '!=' => _('ist nicht')
        ];
        if ($this->valuesDbNameField) {
            if (isset(self::$cached_valid_values[static::class])) {
                $this->validValues = self::$cached_valid_values[static::class];
            } else {
                // Get all available values from database.
                $stmt = DBManager::get()->query(
                    "SELECT DISTINCT `" . $this->valuesDbIdField . "`, `" . $this->valuesDbNameField . "` " .
                    "FROM `" . $this->valuesDbTable . "` ORDER BY `" . $this->valuesDbNameField . "` ASC");
                while ($current = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $this->validValues[$current[$this->valuesDbIdField]] = $current[$this->valuesDbNameField];
                }
                self::$cached_valid_values[static::class] = $this->validValues;
            }
        }
        if ($fieldId) {
            $this->id = $fieldId;
            $this->load();
        } else {
            $this->id = $this->generateId();
        }
    }

    /**
     * Checks whether the given value fits the configured condition. The
     * value is compared to the currently selected value by using the
     * currently selected compare operator.
     *
     * @param Array values
     * @return Boolean
     */
    public function checkValue($values)
    {
        // Validate compare operator
        if (!isset($this->validCompareOperators[$this->compareOperator])) {
            throw new Exception('Invalid compare operator');
        }

        $result = false;
        foreach ($values as $value) {
            switch ($this->compareOperator) {
                case '=':
                    $result = $value == $this->value;
                    break;
                case '!=':
                    $result = $value != $this->value;
                    break;
                case '<':
                    $result = $value < $this->value;
                    break;
                case '<=':
                    $result = $value <= $this->value;
                    break;
                case '>=':
                    $result = $value >= $this->value;
                    break;
                case '>':
                    $result = $value > $this->value;
                    break;
                default:
                    throw new Exception('Unknown compare operator.');
            }

            if ($result) {
                break;
            }
        }
        return $result;
    }

    /**
     * Deletes the stored data for this condition field from DB.
     */
    public function delete()
    {
        // Delete condition data.
        $stmt = DBManager::get()->prepare("DELETE FROM `userfilter_fields`
            WHERE `field_id`=?");
        $stmt->execute([$this->id]);
    }

    /**
     * Generate a new unique ID.
     *
     * @param String tableName
     */
    public function generateId()
    {
        do {
            $newid = md5(uniqid(get_class($this) . microtime(), true));
            $id = DBManager::get()->fetchColumn("SELECT `field_id`
                FROM `userfilter_fields` WHERE `field_id`=?", [$newid]);
        } while ($id);
        return $newid;
    }

    /**
     * Reads all available UserFilterField subclasses and loads their definitions.
     */
    public static function getAvailableFilterFields(string $context = '', string $target = '')
    {
        if (self::$available_filter_fields === null) {
            $fields = [];

            // Check if given context is allowed.
            if (in_array($context, static::$validContexts)) {

                $path = $GLOBALS['STUDIP_BASE_PATH'] . '/lib/classes/UserFilterFields';

                if ($context !== '') {
                    $path .= DIRECTORY_SEPARATOR . $context;
                }

                try {
                    $i = new FileSystemIterator(
                        $path,
                        FileSystemIterator::SKIP_DOTS
                    );

                    foreach ($i as $class) {
                        if ($class->isFile()) {
                            require_once $class;
                        }
                    }
                } catch (UnexpectedValueException $e) {
                    Log::error($e->getMessage());
                }

            }

            // Get all classes in given context.
            $classes = array_filter(
                get_declared_classes(),
                function ($c) use ($context) {
                    $reflection_class = new \ReflectionClass($c);
                    $namespace = $reflection_class->getNamespaceName();
                    return is_subclass_of($c, UserFilterField::class)
                        && $namespace === 'UserFilterFields' . ($context !== '' ? '\\' . $context : '')
                        && $c::isActive();
                }
            );

            usort($classes, fn ($a, $b) => $a::$sortOrder - $b::$sortOrder);

            // If a target is given, return only matching classes
            if ($target !== '') {
                $classes = array_filter(
                    $classes,
                    function ($c) use ($target) {
                        $targets = $c::getTargets();
                        return count($targets) === 0 || in_array($target, $targets);
                    }
                );
            }

            foreach ($classes as $class) {
                if ($class::$isParameterized) {
                    $fields = array_merge($fields, $class::getParameterizedTypes());
                } else {
                    $filter = new $class();
                    $fields[$class] = $filter->getName();
                }
            }
            self::$available_filter_fields = $fields;
        }
        return self::$available_filter_fields;
    }


    /**
     * Which compare operator is set?
     *
     * @return String
     */
    public function getCompareOperator()
    {
        return $this->compareOperator;
    }

    /**
     * Which compare operator is set?
     *
     * @return String
     */
    public function getCompareOperatorAsText()
    {
        return $this->getValidCompareOperators()[$this->compareOperator] ?? '';
    }

    /**
     * Field ID.
     *
     * @return String
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get this field's display name.
     *
     * @return String
     */
    public function getName()
    {
        return _("Nutzerfilterfeld");
    }

    /**
     * Compares all the users' values by using the specified compare operator
     * and returns all users that fulfill the condition. This can be
     * an important information when checking on validity of a combination
     * of conditions.
     *
     * @param Array $restrictions values from other fields that restrict the valid
     *                            values for a user (e.g. a semester of study in
     *                            a given subject)
     * @return Array All users that are affected by the current condition
     *               field.
     */
    public function getUsers($restrictions = [])
    {
        $db = DBManager::get();
        $users = [];
        // Standard query getting the values without respecting other values.
        $select = "SELECT DISTINCT `" . $this->userDataDbTable . "`.`user_id` ";
        $from = "FROM `" . $this->userDataDbTable . "` ";
        $where = "WHERE `" . $this->userDataDbTable . "`.`" . $this->userDataDbField .
            "`" . $this->compareOperator . "?";
        $parameters = [$this->value];
        $joinedTables = [
            $this->userDataDbTable => true
        ];
        // Check if there are restrictions given.
        foreach ($restrictions as $otherField => $restriction) {
            // We only take the value into consideration if it represents a valid restriction.
            if ($this->relations[$otherField]) {
                // Do we need to join in another table?
                if (!$joinedTables[$restriction['table']]) {
                    $joinedTables[$restriction['table']] = true;
                    $from .= " INNER JOIN `" . $restriction['table'] . "` ON (`" .
                        $this->userDataDbTable . "`.`" .
                        $this->relations[$otherField]['local_field'] . "`=`" .
                        $restriction['table'] . "`.`" .
                        $this->relations[$otherField]['foreign_field'] . "`)";
                }
                // Expand WHERE statement with the value from restriction.
                $where .= " AND `" . $restriction['table'] . "`.`" .
                    $restriction['field'] . "`" . $restriction['compare'] . "?";
                $parameters[] = $restriction['value'];
            }
        }
        // Get all the users that fulfill the condition.
        $stmt = $db->prepare($select . $from . $where);
        $stmt->execute($parameters);
        while ($current = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $current['user_id'];
        }
        return $users;
    }

    /**
     * Gets the value for the given user that is relevant for this
     * condition field. Here, this method looks up the study degree(s)
     * for the user. These can then be compared with the required degrees
     * whether they fit.
     *
     * @param String $userId User to check.
     * @param array $additional conditions that are required for check.
     * @return array The value(s) for this user.
     */
    public function getUserValues($userId, $additional = null)
    {
        $result = [];
        $query = "SELECT DISTINCT `" . $this->userDataDbField . "` " .
            "FROM `" . $this->userDataDbTable . "` " .
            "WHERE `user_id`=?";
        $parameters = [$userId];
        // Additional requirements given...
        if (is_array($additional)) {

            // Don't use the same database field twice as this can only get ugly.
            $usedFields = [$this->userDataDbField];

            foreach ($additional as $a_condition) {
                if ($a_condition->id != $this->id && $this->userDataDbTable == $a_condition->userDataDbTable &&
                    !in_array($a_condition->userDataDbField, $usedFields)) {
                    $query .= " AND `" . $a_condition->userDataDbField . "` " . $a_condition->compareOperator . "?";
                    $parameters[] = $a_condition->value;
                }
            }
        }
        // Get semester of study for user.
        $stmt = DBManager::get()->prepare($query);
        $stmt->execute($parameters);
        while ($current = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = $current[$this->userDataDbField];
        }
        return $result;
    }

    /**
     * Returns all valid compare operators.
     *
     * @return Array Array of valid compare operators.
     */
    public function getValidCompareOperators()
    {
        return $this->validCompareOperators;
    }

    /**
     * Returns all valid values. Values can be loaded dynamically from
     * database or be returned as static array.
     *
     * @return Array Valid values in the form $value => $displayname.
     */
    public function getValidValues()
    {
        return $this->validValues;
    }

    /**
     * Which value is set?
     *
     * @return String
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Helper function for loading data from DB.
     */
    public function load()
    {
        $stmt = DBManager::get()->prepare(
            "SELECT * FROM `userfilter_fields` WHERE `field_id`=? LIMIT 1");
        $stmt->execute([$this->id]);
        if ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->conditionId = $data['filter_id'];
            $this->value = $data['value'];
            $this->compareOperator = $data['compare_op'];
        }
    }

    /**
     * Sets a new selected compare operator
     *
     * @param String newOperator
     * @return UserFilterField
     */
    public function setCompareOperator($newOperator)
    {
        if (in_array($newOperator, array_keys($this->validCompareOperators))) {
            $this->compareOperator = $newOperator;
            return $this;
        } else {
            return false;
        }
    }

    /**
     * Connects the current field to a UserFilter.
     *
     * @param String $id ID of a UserFilter object.
     * @return UserFilterField
     */
    public function setConditionId($id)
    {
        $this->conditionId = $id;
        return $this;
    }

    /**
     * Sets a new selected value.
     *
     * @param String newValue
     * @return UserFilterField
     */
    public function setValue($newValue)
    {
        if ($this->validValues[$newValue]) {
            $this->value = $newValue;
            return $this;
        } else {
            return false;
        }
    }

    /**
     * Stores data to DB.
     *
     * @param String conditionId The condition this field belongs to.
     */
    public function store()
    {
        // Generate new ID if field entry doesn't exist in DB yet.
        if (!$this->id) {
            $this->id = $this->generateId();
        }
        // Store field data.
        $stmt = DBManager::get()->prepare("INSERT INTO `userfilter_fields`
            (`field_id`, `filter_id`, `type`, `value`, `compare_op`,
            `mkdate`, `chdate`)  VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `filter_id`=VALUES(`filter_id`),
            `type`=VALUES(`type`),`value`=VALUES(`value`),
            `compare_op`=VALUES(`compare_op`), `chdate`=VALUES(`chdate`)");
        $stmt->execute([$this->id, $this->conditionId, get_class($this),
            $this->value, $this->compareOperator, time(), time()]);
    }

    public function __clone()
    {
        $this->id = md5(uniqid(get_class($this)));
        $this->conditionId = null;
    }

} /* end of class UserFilterField */
