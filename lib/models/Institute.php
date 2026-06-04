<?php
/**
 * Institute.php - model class for table Institute
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Rasmus Fuhse <fuhse@data-quest>
 * @author      Suchi & Berg GmbH <info@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       2.0
 *
 * @property string $id alias column for institut_id
 * @property string $institut_id database column
 * @property I18NString $name database column
 * @property string $fakultaets_id database column
 * @property string $strasse database column
 * @property string $plz database column
 * @property I18NString $url database column
 * @property string $telefon database column
 * @property string $email database column
 * @property string $fax database column
 * @property int $type database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property string|null $lit_plugin_name database column
 * @property int $srienabled database column
 * @property string $lock_rule database column
 * @property SimpleORMapCollection<InstituteMember> $members has_many InstituteMember
 * @property SimpleORMapCollection<Course> $home_courses has_many Course
 * @property SimpleORMapCollection<Institute> $sub_institutes has_many Institute
 * @property SimpleORMapCollection<DatafieldEntryModel> $datafields has_many DatafieldEntryModel
 * @property SimpleORMapCollection<StudipScmEntry> $scm has_many StudipScmEntry
 * @property SimpleORMapCollection<Statusgruppen> $status_groups has_many Statusgruppen
 * @property SimpleORMapCollection<BlubberThread> $blubberthreads has_many BlubberThread
 * @property SimpleORMapCollection<ConsultationBlock> $consultation_blocks has_many ConsultationBlock
 * @property SimpleORMapCollection<ConsultationResponsibility> $consultation_responsibilities has_many ConsultationResponsibility
 * @property SimpleORMapCollection<ToolActivation> $tools has_many ToolActivation
 * @property SimpleORMapCollection<Studiengang> $courses_of_study has_many Studiengang
 * @property Institute $faculty belongs_to Institute
 * @property SimpleORMapCollection<Course> $courses has_and_belongs_to_many Course
 * @property-read mixed $is_fak additional field
 * @property-read mixed $all_status_groups additional field
 */

class Institute extends SimpleORMap implements Range
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'Institute';
        $config['additional_fields']['is_fak']['get'] = 'isFaculty';

        $config['has_many']['members'] = [
            'class_name' => InstituteMember::class,
            'assoc_func' => 'findByInstitute',
            'on_delete' => 'delete',
            'on_store' => 'store',
        ];
        $config['has_many']['home_courses'] = [
            'class_name' => Course::class,
            'on_delete' => 'delete',
            'on_store' => 'store',
        ];
        $config['has_many']['sub_institutes'] = [
            'class_name' => Institute::class,
            'assoc_foreign_key' => 'fakultaets_id',
            'assoc_func' => 'findByFaculty',
            'on_delete' => 'delete',
            'on_store' => 'store',
        ];
        $config['has_many']['datafields'] = [
            'class_name' => DatafieldEntryModel::class,
            'assoc_foreign_key' =>
                function($model,$params) {
                    $model->setValue('range_id', $params[0]->id);
                },
            'assoc_func' => 'findByModel',
            'on_delete' => 'delete',
            'on_store' => 'store',
            'foreign_key' =>
                function($i) {
                    return [$i];
                }
        ];
        $config['belongs_to']['faculty'] = [
            'class_name' => Institute::class,
            'foreign_key' => 'fakultaets_id',
        ];
        $config['has_and_belongs_to_many']['courses'] = [
            'class_name' => Course::class,
            'thru_table' => 'seminar_inst',
            'on_delete' => 'delete',
            'on_store' => 'store',
        ];
        $config['has_many']['scm'] = [
            'class_name'        => StudipScmEntry::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
            'on_store'          => 'store',
        ];
        $config['has_many']['status_groups'] = [
            'class_name'        => Statusgruppen::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
            'on_store'          => 'store',
            'order_by'          => 'ORDER BY position ASC',
        ];
        $config['has_many']['blubberthreads'] = [
            'class_name' => BlubberThread::class,
            'assoc_func' => 'findByInstitut',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['consultation_blocks'] = [
            'class_name'        => ConsultationBlock::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['consultation_responsibilities'] = [
            'class_name'        => ConsultationResponsibility::class,
            'assoc_func'        => 'findByInstituteId',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['tools'] = [
            'class_name'        => ToolActivation::class,
            'assoc_foreign_key' => 'range_id',
            'order_by'          => 'ORDER BY position',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['courses_of_study'] = [
            'class_name'        => Studiengang::class,
            'assoc_foreign_key' => 'institut_id',
            'order_by'          => 'ORDER BY name ASC',
        ];
        $config['additional_fields']['all_status_groups']['get'] = function ($institute) {
            return Statusgruppen::findAllByRangeId($institute->id, true);
        };

        $config['i18n_fields'] = ['name', 'url'];
        $config['registered_callbacks']['after_create'][] = 'setDefaultTools';

        parent::configure($config);
    }

    /**
    * Returns the currently active course or false if none is active.
    *
    * @return Institute object of currently active institute
    * @since 3.0
    */
    public static function findCurrent()
    {
        if (Context::isInstitute()) {
            return Context::get();
        }
        return null;
    }

    /**
     * returns array of instances of Institutes belonging to given faculty
     * @param string $fakultaets_id
     * @return array
     */
    public static function findByFaculty($fakultaets_id)
    {
        return self::findBySQL("fakultaets_id=? AND fakultaets_id <> institut_id ORDER BY Name ASC", [$fakultaets_id]);
    }

    /**
     * returns an array of all institutes ordered by faculties and name
     * @return array
     */
    public static function getInstitutes()
    {
        $db = DBManager::get();
        $result = $db->query("SELECT Institute.Institut_id, Institute.Name, IF(Institute.Institut_id=Institute.fakultaets_id,1,0) AS is_fak " .
                "FROM Institute " .
                    "LEFT JOIN Institute as fakultaet ON (Institute.fakultaets_id = fakultaet.Institut_id) " .
                "ORDER BY fakultaet.Name ASC, is_fak DESC, Institute.Name ASC")->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    /**
     * returns an array of all institutes to which the given user belongs,
     * ordered by faculties and name. The user role for each institute is included
     *
     * @param string $user_id if omitted, the current user is used
     *
     * @return array
     */
    public static function getMyInstitutes($user_id = NULL)
    {
        global $perm, $user;
        if (!$user_id) {
            $user_id = $user->id;
        }
        $db = DBManager::get();
        if (!$perm->have_perm("admin", $user_id)) {
            $result = $db->query("SELECT user_inst.Institut_id, Institute.Name, Institute.fakultaets_id, IF(user_inst.Institut_id=Institute.fakultaets_id,1,0) AS is_fak, user_inst.inst_perms " .
                "FROM user_inst " .
                    "LEFT JOIN Institute USING (institut_id) " .
                "WHERE (user_id = ".$db->quote($user_id)." " .
                    "AND (inst_perms = 'dozent' OR inst_perms = 'tutor')) " .
                "ORDER BY Institute.Name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } else if (!$perm->have_perm("root", $user_id)) {
            $result = $db->query("SELECT user_inst.Institut_id, Institute.Name, Institute.fakultaets_id, IF(user_inst.Institut_id=Institute.fakultaets_id,1,0) AS is_fak, user_inst.inst_perms " .
                "FROM user_inst " .
                    "LEFT JOIN Institute USING (institut_id) " .
                "WHERE (user_id = ".$db->quote($user_id)." AND inst_perms = 'admin') " .
                "ORDER BY Institute.Name ASC")->fetchAll(PDO::FETCH_ASSOC);
            if ($perm->is_fak_admin($user_id)) {
                foreach($result as $fak) {
                    $combined_result[] = $fak;
                    $institutes = $db->query("SELECT Institut_id, Name, fakultaets_id, 0 as is_fak, 'admin' as inst_perms
                                              FROM Institute WHERE Institut_id <> fakultaets_id AND fakultaets_id = " . $db->quote($fak['Institut_id'])
                                             . " ORDER BY Institute.Name ASC")->fetchAll(PDO::FETCH_ASSOC);
                    $combined_result = array_merge($combined_result, $institutes);
                }
                $result = $combined_result;
            }

        } else {
            $result = $db->query("SELECT Institute.Institut_id, Institute.Name, Institute.fakultaets_id, IF(Institute.Institut_id=Institute.fakultaets_id,1,0) AS is_fak, 'admin' AS inst_perms " .
                "FROM Institute " .
                    "LEFT JOIN Institute as fakultaet ON (Institute.fakultaets_id = fakultaet.Institut_id) " .
                "ORDER BY fakultaet.Name ASC, is_fak DESC, Institute.Name ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        return $result;
    }

    public function isFaculty()
    {
        return $this->fakultaets_id === $this->institut_id;
    }

    /**
     * Returns the full name of an institute.
     *
     * @param string formatting template name
     * @return string Fullname
     */
    public function getFullName($format = 'default'): string
    {
        $template['type-name'] = '%2$s: %1$s';
        if ($format === 'default' || !isset($template[$format])) {
           $format = 'type-name';
        }
        $type = $GLOBALS['INST_TYPE'][$this['type']]['name'];
        if (!$type) {
            $type = _('Einrichtung');
        }
        $data[0] = $this['name'];
        $data[1] = $type;
        return trim(vsprintf($template[$format], array_map('trim', $data)));
    }

    /**
     * Returns a descriptive text for the range type.
     *
     * @return string
     */
    public function describeRange(): string
    {
        return _('Einrichtung');
    }

    /**
     * Returns a unique identificator for the range type.
     *
     * @return string
     */
    public function getRangeType(): string
    {
        return 'institute';
    }

    /**
     * Returns the id of the current range
     *
     * @return mixed (string|int)
     */
    public function getRangeId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return InstituteConfig::get($this);
    }

    /**
     * Decides whether the user may access the range.
     *
     * @param string|null $user_id Optional id of a user, defaults to current user
     * @return bool
     */
    public function isAccessibleToUser($user_id = null): bool
    {
        if (Config::get()->ENABLE_FREE_ACCESS === '1') {
            return true;
        }

        return $GLOBALS['perm']->have_perm('user');
    }

    /**
     * Decides whether the user may edit/alter the range.
     *
     * @param string|null $user_id Optional id of a user, defaults to current user
     */
    public function isEditableByUser($user_id = null): bool
    {
        return $GLOBALS['perm']->have_studip_perm('admin', $this->id);
    }

    /**
     * @return SemClass
     */
    public function getSemClass()
    {
        return SemClass::getDefaultInstituteClass($this->type);
    }

    /**
     *
     */
    public function setDefaultTools()
    {
       $this->tools = [];
       foreach (array_values($this->getSemClass()->getActivatedModuleObjects()) as $module) {
           PluginManager::getInstance()->setPluginActivated($module->getPluginId(), $this->id, true);
           $this->tools[] = ToolActivation::find([$this->id, $module->getPluginId()]);
       }
    }

    /**
     * @param $name string name of tool / plugin
     * @return bool
     */
    public function isToolActive($name): bool
    {
        $plugin = PluginEngine::getPlugin($name);
        return $plugin && $this->tools->findOneby('plugin_id', $plugin->getPluginId());
    }

    /**
     * returns all activated plugins/modules for this course
     * @return StudipModule[]
     */
    public function getActivatedTools()
    {
        return array_filter($this->tools->getStudipModule());
    }


    /**
     * @see Range::__toString()
     */
    public function __toString() : string
    {
        return $this->getFullName();
    }
}
