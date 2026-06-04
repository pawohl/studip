<?php
/**
 * User.php
 * model class for combined auth_user_md5/user_info record
 * this class represents one user, the attributes from tables
 * auth_user_md5 and user_info were merged.
 *
 * @code
 * $a_user = User::find($id);
 * $another_users_email = User::findByUsername($username)->email;
 * $a_user->email = $another_users_email;
 * $a_user->store();
 * @endcode
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @copyright   2011 Stud.IP Core-Group
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 *
 * @property string $id alias column for user_id
 * @property string $user_id database column
 * @property string $username database column
 * @property string $password database column
 * @property string $perms database column
 * @property string $vorname database column
 * @property string $nachname database column
 * @property string $email database column
 * @property string $validation_key database column
 * @property string|null $auth_plugin database column
 * @property int $locked database column
 * @property string|null $lock_comment database column
 * @property string|null $locked_by database column
 * @property string $visible database column
 * @property string|null $matriculation_number database column
 * @property SimpleORMapCollection<CourseMember> $course_memberships has_many CourseMember
 * @property SimpleORMapCollection<InstituteMember> $institute_memberships has_many InstituteMember
 * @property SimpleORMapCollection<AdmissionApplication> $admission_applications has_many AdmissionApplication
 * @property SimpleORMapCollection<ArchivedCourseMember> $archived_course_memberships has_many ArchivedCourseMember
 * @property SimpleORMapCollection<DatafieldEntryModel> $datafields has_many DatafieldEntryModel
 * @property SimpleORMapCollection<UserStudyCourse> $studycourses has_many UserStudyCourse
 * @property SimpleORMapCollection<Statusgruppen> $contactgroups has_many Statusgruppen
 * @property SimpleORMapCollection<ResourcePermission> $resource_permissions has_many ResourcePermission
 * @property SimpleORMapCollection<ResourceTemporaryPermission> $resource_temporary_permissions has_many ResourceTemporaryPermission
 * @property SimpleORMapCollection<ConsultationBlock> $consultation_blocks has_many ConsultationBlock
 * @property SimpleORMapCollection<ConsultationBooking> $consultation_bookings has_many ConsultationBooking
 * @property SimpleORMapCollection<ConsultationResponsibility> $consultation_responsibilities has_many ConsultationResponsibility
 * @property SimpleORMapCollection<Kategorie> $profile_categories has_many Kategorie
 * @property SimpleORMapCollection<MvvContact> $mvv_assignments has_many MvvContact
 * @property SimpleORMapCollection<CourseMemberNotification> $course_notifications has_many CourseMemberNotification
 * @property SimpleORMapCollection<ExternPageConfig> $extern_pages_configs has_many ExternPageConfig
 * @property SimpleORMapCollection<BlubberThread> $blubber_threads has_many BlubberThread
 * @property SimpleORMapCollection<BlubberComment> $blubber_comments has_many BlubberComment
 * @property SimpleORMapCollection<BlubberMention> $blubber_mentions has_many BlubberMention
 * @property SimpleORMapCollection<StudygroupInvitation> $studygroup_invitations has_many StudygroupInvitation
 * @property UserInfo $info has_one UserInfo
 * @property UserOnline $online has_one UserOnline
 * @property SimpleORMapCollection<User> $contacts has_and_belongs_to_many User
 * @property SimpleORMapCollection<UserDomain> $domains has_and_belongs_to_many UserDomain
 * @property-read mixed $config additional field
 * @property-read mixed $gender additional field
 * @property-read mixed $salutation additional field
 * @property mixed $hobby additional field
 * @property mixed $lebenslauf additional field
 * @property mixed $publi additional field
 * @property mixed $schwerp additional field
 * @property mixed $home additional field
 * @property mixed $privatnr additional field
 * @property mixed $privatcell additional field
 * @property mixed $privadr additional field
 * @property mixed $score additional field
 * @property mixed $geschlecht additional field
 * @property mixed $mkdate additional field
 * @property mixed $chdate additional field
 * @property mixed $title_front additional field
 * @property mixed $title_rear additional field
 * @property mixed $preferred_language additional field
 * @property mixed $smsforward_copy additional field
 * @property mixed $smsforward_rec additional field
 * @property mixed $email_forward additional field
 * @property mixed $motto additional field
 * @property mixed $lock_rule additional field
 * @property mixed $oercampus_description additional field
 */
class User extends AuthUserMd5 implements Range, PrivacyObject, Studip\Calendar\Owner
{
    /**
     *
     */
    protected static function configure($config = [])
    {
        $config['has_many']['course_memberships'] = [
            'class_name' => CourseMember::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['institute_memberships'] = [
            'class_name' => InstituteMember::class,
            'order_by'   => 'ORDER BY priority ASC',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['admission_applications'] = [
            'class_name' => AdmissionApplication::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['archived_course_memberships'] = [
            'class_name' => ArchivedCourseMember::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['datafields'] = [
            'class_name'  => DatafieldEntryModel::class,
            'foreign_key' => function ($user) {
                return [$user];
            },
            'assoc_foreign_key' => function ($model, $params) {
                $model->setValue('range_id', $params[0]->id);
            },
            'assoc_func' => 'findByModel',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['studycourses'] = [
            'class_name' => UserStudyCourse::class,
            'assoc_func' => 'findByUser',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_and_belongs_to_many']['contacts'] = [
            'class_name'     => User::class,
            'thru_table'     => 'contact',
            'thru_key'       => 'owner_id',
            'thru_assoc_key' => 'user_id',
            'order_by'       => 'ORDER BY Nachname, Vorname',
            'on_delete'      => 'delete',
            'on_store'       => 'store',
        ];
        $config['has_many']['contactgroups'] = [
            'class_name'        => Statusgruppen::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
            'on_store'          => 'store',
        ];
        $config['has_one']['info'] = [
            'class_name' => UserInfo::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_one']['online'] = [
            'class_name' => UserOnline::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['resource_permissions'] = [
            'class_name' => ResourcePermission::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store'
        ];
        $config['has_many']['resource_temporary_permissions'] = [
            'class_name' => ResourceTemporaryPermission::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store'
        ];
        $config['has_many']['consultation_blocks'] = [
            'class_name'        => ConsultationBlock::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['consultation_bookings'] = [
            'class_name' => ConsultationBooking::class,
            'on_delete'  => 'delete',
        ];
        $config['has_many']['consultation_responsibilities'] = [
            'class_name'        => ConsultationResponsibility::class,
            'assoc_func'        => 'findByUserId',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['profile_categories'] = [
            'class_name'        => Kategorie::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
        ];


        $config['has_many']['mvv_assignments'] = [
            'class_name'        => MvvContact::class,
            'assoc_foreign_key' => 'contact_id',
            'on_delete'         => 'delete'
        ];

        $config['has_and_belongs_to_many']['domains'] = [
            'class_name'        => UserDomain::class,
            'thru_table'        => 'user_userdomains',
            'on_delete'         => 'delete',
            'on_store'          => 'store',
            'order_by'          => 'ORDER BY name',
        ];

        $config['has_many']['course_notifications'] = [
            'class_name'        => CourseMemberNotification::class,
            'on_delete'         => 'delete',
        ];

        $config['has_many']['extern_pages_configs'] = [
            'class_name' => ExternPageConfig::class,
            'assoc_foreign_key' => 'author_id'
        ];

        // Blubber relations
        $config['has_many']['blubber_threads'] = [
            'class_name' => BlubberThread::class,
            'on_delete'  => 'delete',
        ];
        $config['has_many']['blubber_comments'] = [
            'class_name' => BlubberComment::class,
            'on_delete'  => 'delete',
        ];
        $config['has_many']['blubber_mentions'] = [
            'class_name' => BlubberMention::class,
            'on_delete'  => 'delete',
        ];

        $config['has_many']['studygroup_invitations'] = [
            'class_name' => StudygroupInvitation::class,
            'assoc_foreign_key' => 'user_id',
            'on_delete'  => 'delete',
        ];

        // Forum
        $config['has_many']['forum_posting_reads'] = [
            'class_name' => Forum\PostingRead::class,
            'assoc_foreign_key' => 'user_id',
            'on_delete'  => 'delete',
        ];
        $config['has_many']['forum_posting_reactions'] = [
            'class_name' => Forum\PostingReaction::class,
            'assoc_foreign_key' => 'user_id',
            'on_delete'  => 'delete',
        ];
        $config['has_many']['forum_subscriptions'] = [
            'class_name' => Forum\Subscription::class,
            'assoc_foreign_key' => 'user_id',
            'on_delete'  => 'delete',
        ];

        $config['additional_fields']['config']['get'] = function ($user) {
            return UserConfig::get($user->id);
        };
        $config['additional_fields']['gender']['get'] = 'getGender';
        $config['additional_fields']['salutation']['get'] = 'getSalutation';

        $config['registered_callbacks']['after_delete'][] = 'cbRemoveFeedback';
        $config['registered_callbacks']['before_store'][] = 'cbClearCaches';
        $config['registered_callbacks']['before_store'][] = 'cbStudipLog';

        // Ensure matriculation number is always set to null if empty
        $config['registered_callbacks']['before_store'][] = function (User $user) {
            if (
                isset($user->matriculation_number)
                && trim($user->matriculation_number) === ''
            ) {
                $user->matriculation_number = null;
            }
        };

        $info = new UserInfo();
        $info_meta = $info->getTableMetadata();
        foreach ($info_meta ['fields'] as $field => $meta) {
            if ($field !== $info_meta['pk'][0]) {
                $config['additional_fields'][$field] = [
                    'get'            => '_getAdditionalValueFromRelation',
                    'set'            => '_setAdditionalValueFromRelation',
                    'relation'       => 'info',
                    'relation_field' => $field,
                ];
            }
        }

        parent::configure($config);
    }

    /**
     * @param $type string type of callback
     */
    protected function cbStudipLog($type)
    {
        if ($type == 'before_store' && !$this->isNew()) {
            if ($this->isFieldDirty('locked') && $this->isFieldDirty('lock_comment')) {
                if ((int)$this->locked === 1) {
                    StudipLog::log('USER_LOCK',
                        $this->user_id,
                        null,
                        sprintf(
                            'Kommentar: %s',
                            $this->lock_comment
                        )
                    );
                } else {
                    StudipLog::log('USER_UNLOCK',
                        $this->user_id
                    );
                }
            }
        }
    }

    /**
     * Returns the currently authenticated user.
     *
     * @return ?User User
     */
    public static function findCurrent()
    {
        if (isset($GLOBALS['user']) && is_object($GLOBALS['user'])) {
            return $GLOBALS['user']->getAuthenticatedUser();
        }

        return null;
    }

    /**
     * build new object with given data
     *
     * @param $data array assoc array of record
     * @return User
     */
    public static function build($data, $is_new = true)
    {
        // Note: This should be used instead of `new static()` since PHPStan
        $class = get_called_class();
        $user = new $class();

        $user->info = new UserInfo();
        $user->setData($data);
        $user->setNew($is_new);
        foreach (array_keys($user->db_fields()) as $field) {
            $user->content_db[$field] = $user->content[$field];
        }
        $user->info = UserInfo::build($data, $is_new);
        return $user;
    }

    /**
     * Returns user object including user_info
     *
     * @param string $id
     * @return ?User User
     */
    public static function findFull($id)
    {
        $sql = "SELECT *
                FROM auth_user_md5
                LEFT JOIN user_info USING (user_id)
                WHERE user_id = ?";
        $data = DBManager::get()->fetchOne($sql, [$id]);
        if ($data) {
            return static::buildExisting($data);
        }

        return null;
    }

    /**
     * Returns user objects including user_info
     *
     * @param array $ids
     * @param string $order_by
     * @return User[] User
     */
    public static function findFullMany($ids, $order_by = '')
    {
        $sql = "SELECT *
                FROM auth_user_md5
                LEFT JOIN user_info USING (user_id)
                WHERE user_id IN (?) " . $order_by;
        $data = DBManager::get()->fetchAll($sql, [$ids], [static::class, 'buildExisting']);
        return $data;
    }

    /**
     * return user object for given username
     *
     * @param string $username a username
     * @return User
     */
    public static function findByUsername($username)
    {
        return parent::findOneByUsername($username);
    }

    /**
     * returns an array of User-objects that have the given value in the
     * given datafield.
     * @param string $datafield_id
     * @param array of User
     */
    public static function findByDatafield($datafield_id, $value)
    {
        return static::findMany(
            array_column(
                DatafieldEntryModel::findBySQL(
                    'datafield_id = :datafield_id AND content = :value',
                    compact('datafield_id', 'value')
                ),
                'range_id'
            )
        );
    }

    /**
     * Wraps a search parameter in %..% if the parameter itself does not
     * contain % or _.
     *
     * @param String $needle Search parameter
     * @return String containing the wrapped needle if neccessary
     */
    private static function searchParam($needle)
    {
        if (preg_match('/[%_]/S', $needle)) {
            return $needle;
        }

        return '%' . $needle . '%';
    }

    /**
     * Temporary migrate to User.php
     *
     * @param $attributes
     * @return array
     */
    public static function search($attributes)
    {
        $params = [];
        $joins  = [];
        $where  = [];

        $query = "SELECT ui.*, au.*
                  FROM `auth_user_md5` au
                  LEFT JOIN `user_online` uo ON (au.`user_id` = uo.`user_id`)
                  LEFT JOIN `user_info` ui ON (au.`user_id` = ui.`user_id`)";

        if (!empty($attributes['username'])) {
            $where[] =  "au.`username` like :username";
            $params[':username'] = self::searchParam($attributes['username']);
        }

        if (!empty($attributes['vorname'])) {
            $where[] = "au.`Vorname` LIKE :vorname";
            $params[':vorname'] = self::searchParam($attributes['vorname']);
        }

        if (!empty($attributes['nachname'])) {
            $where[] = "au.`Nachname` LIKE :nachname";
            $params[':nachname'] = self::searchParam($attributes['nachname']);
        }

        if (!empty($attributes['email'])) {
            $where[] = "au.`Email` LIKE :email";
            $params[':email'] = self::searchParam($attributes['email']);
        }

        if (!empty($attributes['matriculation_number'])) {
            $where[] = "au.`matriculation_number` LIKE :matriculation_number";
            $params[':matriculation_number'] = self::searchParam($attributes['matriculation_number']);
        }

        //permissions
        if (!empty($attributes['perm']) && $attributes['perm'] !== 'alle') {
            $where[] = "au.`perms` = :perms";
            $params[':perms'] = $attributes['perm'];
        }

        //locked user
        if (!empty($attributes['locked'])) {
            $where[] = "au.`locked` = 1";
        }

        // show only users who are not lecturers
        if (!empty($attributes['show_only_not_lectures'])) {
            $where[] = "au.`user_id` NOT IN (SELECT `user_id` FROM `seminar_user` WHERE `status` = 'dozent') ";
        }

        if (!empty($attributes['auth_plugins'])) {
            $where[] = "IFNULL(`auth_plugin`, 'preliminary') = :auth_plugins ";
            $params[':auth_plugins'] = $attributes['auth_plugins'];
        }

        //inactivity
        if (!is_null($attributes['inaktiv']) && $attributes['inaktiv'][0] != 'nie') {
            $comp = in_array(trim($attributes['inaktiv'][0]), ['=', '>', '<=']) ? $attributes['inaktiv'][0] : '=';
            $days = (int)$attributes['inaktiv'][1];
            $where[] = "uo.`last_lifesign` {$comp} UNIX_TIMESTAMP(TIMESTAMPADD(DAY, -{$days}, NOW())) ";
        } elseif (!is_null($attributes['inaktiv'])) {
            $where[] = "uo.`last_lifesign` IS NULL";
        }

        //datafields
        if (
            !empty($attributes['datafields'])
            && is_array($attributes['datafields'])
            && count($attributes['datafields']) > 0
        ) {
            $joins[] = "LEFT JOIN `datafields_entries` de ON (de.`range_id` = au.`user_id`)";
            foreach ($attributes['datafields'] as $id => $entry) {
                $where[] = "de.`datafield_id` = :df_id_". $id;
                $where[] = "de.`content` LIKE :df_content_". $id;
                $params[':df_id_' . $id] = $id;
                $params[':df_content_' . $id] = $entry;
            }
        }

        // roles
        if (!empty($attributes['roles'])) {
            $joins[] = "LEFT JOIN `roles_user` ON roles_user.`userid` = au.`user_id`";
            $where[] = "roles_user.`roleid` IN (:roles)";
            $params[':roles'] = $attributes['roles'];
        }

        // userdomains
        if (!empty($attributes['userdomains'])) {
            $joins[] = "LEFT JOIN `user_userdomains` uud ON (au.`user_id` = uud.`user_id`)";
            $joins[] = "LEFT JOIN `userdomains` uds USING (`userdomain_id`)";
            if ($attributes['userdomains'] === 'null-domain') {
                $where[] = "`userdomain_id` IS NULL ";
            } else {
                $where[] = "userdomain_id = :userdomains";
                $params[':userdomains'] = $attributes['userdomains'];
            }
        }

        // degree or studycourse
        if (!empty($attributes['degree']) || !empty($attributes['studycourse']) || !empty($attributes['fachsem'])) {
            $joins[] = "LEFT JOIN `user_studiengang` us ON (us.`user_id` = au.`user_id`)";
            if (!empty($attributes['degree'])) {
                $where[] = "us.`abschluss_id` IN (:degree)";
                $params[':degree'] = $attributes['degree'];
            }

            if (!empty($attributes['studycourse'])) {
                $where[] = "us.`fach_id` IN (:studycourse)";
                $params[':studycourse'] = $attributes['studycourse'];
            }

            if(!empty($attributes['fachsem'])) {
                $where[] = 'us.`semester` = :fachsem';
                $params[':fachsem'] = $attributes['fachsem'];
            }
        }

        if (!empty($attributes['institute'])) {
            $joins[] = "LEFT JOIN `user_inst` uis ON uis.`user_id` = au.`user_id`";
            $where[] = "uis.`Institut_id` = :institute";
            $params[':institute'] = $attributes['institute'];
        }

        $query .= implode(' ', $joins);
        $query .= " WHERE 1 AND ";
        $query .= implode(' AND ', $where);
        $query .= " GROUP BY au.`user_id` ";

        if (!empty($attributes['sortby'])) {
            //sortieren
            switch ($attributes['sortby']) {
                case "perms":
                    $query .= "ORDER BY au.`perms` {$attributes['order']}, au.`username`";
                    break;
                case "Vorname":
                    $query .= "ORDER BY au.`Vorname` {$attributes['order']}, au.`Nachname`";
                    break;
                case "Nachname":
                    $query .= "ORDER BY au.`Nachname` {$attributes['order']}, au.`Vorname`";
                    break;
                case "Email":
                    $query .= "ORDER BY au.`Email` {$attributes['order']}, au.`username`";
                    break;
                case 'matriculation_number':
                    $query .= "ORDER BY au.`matriculation_number` {$attributes['order']}, au.`username`";
                    break;
                case "changed":
                    $query .= "ORDER BY uo.`last_lifesign` {$attributes['order']}, au.`username`";
                    break;
                case "mkdate":
                    $query .= "ORDER BY ui.`mkdate` {$attributes['order']}, au.`username`";
                    break;
                case "auth_plugin":
                    $query .= "ORDER BY `auth_plugin` {$attributes['order']}, au.`username`";
                    break;
                default:
                    $query .= " ORDER BY au.`username` {$attributes['order']}";
            }
        }

        return DBManager::get()->fetchAll($query, $params, [static::class, 'buildExisting']);
    }


    /**
     * @see SimpleORMap::store()
     */
    public function store()
    {
        if ($this->isDirty() && !$this->info->isFieldDirty('chdate')) {
            $this->info->setValue('chdate', time());
        }
        return parent::store();
    }

    /**
     * @see SimpleORMap::triggerChdate()
     */
    public function triggerChdate()
    {
       return $this->info->triggerChdate();
    }

    /**
     * returns the name in specified format
     * (formats defined in $GLOBALS['_fullname_sql'])
     *
     * @param string one of full,full_rev,no_title,no_title_rev,no_title_short,no_title_motto,full_rev_username
     * @return string guess what - the fullname
     */
    public function getFullName($format = 'default')
    {
        static $concat,$left,$if,$quote;

        if ($format === 'default') {
            $format = 'full';
        }

        $sql = $GLOBALS['_fullname_sql'][$format] ?? null;
        if (!$sql || $format == 'no_title') {
            return $this->vorname . ' ' . $this->nachname;
        }
        if ($format == 'no_title_rev') {
            return $this->nachname . ', ' . $this->vorname;
        }
        if ($concat === null) {
            $concat = function() {return join('', func_get_args());};
            $left = function($str, $c = 0) {return mb_substr($str,0,$c);};
            $if = function($ok,$yes,$no) {return $ok ? $yes : $no;};
            $quote = function($str) {return "'" . addcslashes($str, "\\'\0") . "'";};
        }

        $data = array_map($quote, $this->toArray('vorname nachname username title_front title_rear motto perms'));
        $replace_func['CONCAT'] = '$concat';
        $replace_func['LEFT'] = '$left';
        $replace_func['UCASE'] = 'mb_strtoupper';
        $replace_func['IF'] = '$if';
        $eval = strtr($sql, $replace_func);
        $eval = strtr(mb_strtolower($eval), $data);
        return eval('return ' . $eval . ';');
    }

    public function toArrayRecursive($only_these_fields = null)
    {
        $ret = parent::toArrayRecursive($only_these_fields);
        unset($ret['info']);
        return  $ret;
    }

    /**
     * Returns whether the user was assigned a certain role.
     *
     * @param string $role         The role to check
     * @param string $institute_id An optional institute_id
     * @return bool True if the user was assigned this role, false otherwise
     */
    public function hasRole($role, $institute_id = '')
    {
        return RolePersistence::isAssignedRole($this->user_id, $role, $institute_id);
    }

    /**
     * Returns the roles that were assigned to the user.
     *
     * @param boolean $with_implicit
     * @return array
     */
    public function getRoles($with_implicit = false)
    {
        return RolePersistence::getAssignedRoles($this->user_id, $with_implicit);
    }

    /**
     * Returns whether the given user is stored in contacts.
     *
     * @param User $another_user
     * @return bool
     */
    public function isFriendOf($another_user)
    {
        return (bool) DBManager::get()->fetchColumn("SELECT 1 FROM contact WHERE owner_id=? AND user_id=?", [$this->user_id, $another_user->user_id]);
    }

    /**
     * checks if at least one field was modified since last restore
     *
     * @return boolean
     */
    public function isDirty()
    {
        return parent::isDirty() || $this->info->isDirty();
    }

    /**
     * checks if given field was modified since last restore
     *
     * @param string $field
     * @return boolean
     */
    public function isFieldDirty($field)
    {
        $field = mb_strtolower($field);
        return (array_key_exists($field, $this->content_db) ? parent::isFieldDirty($field) : $this->info->isFieldDirty($field));
    }

    /**
     * reverts value of given field to last restored value
     *
     * @param string $field
     * @return mixed the restored value
     */
    public function revertValue($field)
    {
        $field = mb_strtolower($field);
        return (array_key_exists($field, $this->content_db) ? parent::revertValue($field) : $this->info->revertValue($field));
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
        $field = mb_strtolower($field);
        return (array_key_exists($field, $this->content_db) ? parent::getPristineValue($field) : $this->info->getPristineValue($field));
    }

    /**
     * Returns data of table row as assoc array with raw contents like
     * they are in the database.
     * Pass array of fieldnames or ws separated string to limit
     * fields.
     *
     * @param mixed $only_these_fields
     * @return array
     */
    public function toRawArray($only_these_fields = null)
    {
        return array_merge($this->info->toRawArray($only_these_fields), parent::toRawArray($only_these_fields));
    }

    /**
     * @param string $relation
     */
    public function initRelation($relation)
    {
        parent::initRelation($relation);
        if ($relation == 'info' && is_null($this->relations['info'])) {
            $options = $this->getRelationOptions($relation);
            $result = new $options['class_name'];
            $foreign_key_value = call_user_func($options['assoc_func_params_func'], $this);
            call_user_func($options['assoc_foreign_key_setter'], $result, $foreign_key_value);
            $this->relations[$relation] = $result;
        }
    }

    /**
     * This function returns the perms allowed for an institute for the current user
     *
     * @return array list of perms
     */
    public function getInstitutePerms()
    {
        if($this->perms === 'admin') {
            return ['admin'];
        }
        $allowed_status = [];
        $possible_status = ['autor', 'tutor', 'dozent'];

        $pos = array_search($this->perms, $possible_status);

        if ($pos !== false) {
            $allowed_status = array_slice($possible_status, 0, $pos + 1);
        }
        return $allowed_status;
    }

    /**
     * Get the decorated StudIP-Kings information
     * @return String
     */
    public function getStudipKingIcon()
    {
        $is_king = StudipKing::is_king($this->user_id, TRUE);

        $result = '';
        foreach ($is_king as $type => $text) {
            $type = str_replace('_', '-', $type);
            $result .= Assets::img('crowns/crown-' . $type . '.png', ['alt' => $text, 'title' => $text]);
        }

        return $result ?: null;
    }

    /**
     * Builds an array containing all available elements that are part of a
     * user's homepage together with their visibility. It isn't sufficient to
     * just load the visibility settings from database, because if the user
     * has added some data (e.g. CV) but not yet assigned a special visibility
     * to that field, it wouldn't show up.
     *
     * @return array An array containing all available homepage elements
     * together with their visibility settings in the form
     * $name => $visibility.
     */
    public function getHomepageElements()
    {
        $homepage_visibility = get_local_visibility_by_id($this->id, 'homepage');
        if (is_array(json_decode($homepage_visibility, true))) {
            $homepage_visibility = json_decode($homepage_visibility, true);
        } else {
            $homepage_visibility = [];
        }

        // News
        $news = StudipNews::GetNewsByRange($this->id, true);

        // Non-private dates.
        if (Config::get()->CALENDAR_ENABLE) {
            $dates = CalendarDateAssignment::countBySql('range_id = ?', [$this->id]);
        } else {
            $dates = [];
        }

        // Votes
        if (Config::get()->VOTE_ENABLE) {
            $activeVotes  = Questionnaire::countBySQL("user_id = ? AND visible = '1'", [$this->id]);
            $stoppedVotes = Questionnaire::countBySQL("user_id = ? AND visible = '0'", [$this->id]);
        } else {
            $activeVotes = [];
            $stoppedVotes = [];
        }

        // Free datafields
        $data_fields = DataFieldEntry::getDataFieldEntries($this->id, 'user');

        // Now join all available elements with visibility settings.
        $homepage_elements = [];

        if (Avatar::getAvatar($this->id)->is_customized() && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['picture'])) {
            $homepage_elements['picture'] = [
                'name'        => _('Eigenes Bild'),
                'visibility'  => $homepage_visibility['picture'] ?? get_default_homepage_visibility($this->id),
                'extern'      => true,
                'identifier'  => 'commondata'
            ];
        }

        if ($this->info->motto && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['motto'])) {
            $homepage_elements['motto'] = [
                'name'       => _('Motto'),
                'visibility' => $homepage_visibility['motto'] ?? get_default_homepage_visibility($this->id),
                'identifier' => 'privatedata'
            ];
        }
        if ($this->info->privatnr && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['Private Daten_phone'])) {
            $homepage_elements['private_phone'] = [
                'name'       => _('Private Telefonnummer'),
                'visibility' => $homepage_visibility['private_phone'] ?? get_default_homepage_visibility($this->id),
                'identifier' => 'privatedata'
            ];
        }
        if ($this->info->privatcell && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['private_cell'])) {
            $homepage_elements['private_cell'] = [
                'name'       => _('Private Handynummer'),
                'visibility' => $homepage_visibility['private_cell'] ?? get_default_homepage_visibility($this->id),
                'identifier' => 'privatedata'
            ];
        }
        if ($this->info->privadr && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['privadr'])) {
            $homepage_elements['privadr'] = [
                'name'         => _('Private Adresse'),
                'visibility'   => $homepage_visibility['privadr'] ?? get_default_homepage_visibility($this->id),
                'identifier'   => 'privatedata'
            ];
        }
        if ($this->info->home && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['homepage'])) {
            $homepage_elements['homepage'] = [
                'name'        => _('Homepage-Adresse'),
                'visibility'  => $homepage_visibility['homepage'] ?? get_default_homepage_visibility($this->id),
                'extern'      => true,
                'identifier'  => 'privatedata'
            ];
        }
        if ($news && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['news'])) {
            $homepage_elements['news'] = [
                'name'       => _('Ankündigungen'),
                'visibility' => $homepage_visibility['news'] ?? get_default_homepage_visibility($this->id),
                'extern'     => true,
                'identifier' => 'commondata'
            ];
        }
        if (Config::get()->CALENDAR_ENABLE && $dates && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['dates'])) {
            $homepage_elements['termine'] = [
                'name'       => _('Termine'),
                'visibility' => $homepage_visibility['termine'] ?? get_default_homepage_visibility($this->id),
                'extern'     => true,
                'identifier' => 'commondata'
            ];
        }
        if (Config::get()->VOTE_ENABLE && ($activeVotes || $stoppedVotes) && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['votes'])) {
            $homepage_elements['votes'] = [
                'name'       => _('Fragebögen'),
                'visibility' => $homepage_visibility['votes'] ?? get_default_homepage_visibility($this->id),
                'identifier' => 'commondata'
            ];
        }

        $query = "SELECT 1
                  FROM user_inst
                  LEFT JOIN Institute USING (Institut_id)
                  WHERE user_id = ? AND inst_perms = 'user'";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$this->id]);
        if ($statement->fetchColumn() && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['studying'])) {
            $homepage_elements['studying'] = [
                'name'       => _('Wo ich studiere'),
                'visibility' => $homepage_visibility['studying'] ?? get_default_homepage_visibility($this->id),
                'identifier' => 'studdata'
            ];
        }
        if ($this->info->lebenslauf && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['lebenslauf'])) {
            $homepage_elements['lebenslauf'] = [
                'name'       => _('Lebenslauf'),
                'visibility' => $homepage_visibility['lebenslauf'] ?? get_default_homepage_visibility($this->id),
                'extern'     => true,
                'identifier' => 'privatedata'
            ];
        }
        if ($this->info->hobby && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['hobby'])) {
            $homepage_elements['hobby'] = [
                'name'       => _('Hobbys'),
                'visibility' => $homepage_visibility['hobby'] ?? get_default_homepage_visibility($this->id),
                'identifier' => 'privatedata'
            ];
        }
        if ($this->info->publi && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['publi'])) {
            $homepage_elements['publi'] = [
                'name'       => _('Publikationen'),
                'visibility' => $homepage_visibility['publi'] ?? get_default_homepage_visibility($this->id),
                'extern'     => true,
                'identifier' => 'privatedata'
            ];
        }
        if ($this->info->schwerp && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms]['schwerp'])) {
            $homepage_elements['schwerp'] = [
                'name'       => _('Schwerpunkte'),
                'visibility' => $homepage_visibility['schwerp'] ?? get_default_homepage_visibility($this->id),
                'extern'     => true,
                'identifier' => 'privatedata'
            ];
        }

        if ($data_fields) {
            foreach ($data_fields as $key => $field) {
                if ($field->getValue() && $field->isEditable($this->perms) && empty($GLOBALS['NOT_HIDEABLE_FIELDS'][$this->perms][$key])) {
                    $homepage_elements[$key] = [
                        'name'       => $field->getName(),
                        'visibility' => $homepage_visibility[$key] ?? get_default_homepage_visibility($this->id),
                        'extern'     => true,
                        'identifier' => 'additionaldata'
                    ];
                }
            }
        }

        foreach ($this->profile_categories as $category) {
            $homepage_elements['kat_' . $category->id] = [
                'name'       => $category->name,
                'visibility' => $homepage_visibility['kat_' . $category->id] ?? get_default_homepage_visibility($this->id),
                'extern'     => true,
                'identifier' => 'owncategory'
            ];
        }

        return $homepage_elements;
    }

    /**
     * Changes a user's email adress.
     *
     * @param string $email New email
     * @param bool   $force Force update (even if nothing actually changed)
     * @return bool
     */
    public function changeEmail($email, $force = false)
    {
        // Email did not actually change and update is not forced
        if ($this->email === $email && !$force) {
            return true;
        }

        // Is changing of email globally allowed?
        if (!Config::get()->ALLOW_CHANGE_EMAIL) {
            return false;
        }

        // Is changing of email allowed by auth plugin?
        if (StudipAuthAbstract::CheckField('auth_user_md5.Email', $this->auth_plugin) || LockRules::check($this->user_id, 'email')) {
            return false;
        }

        $validator          = new email_validation_class; ## Klasse zum Ueberpruefen der Eingaben
        $validator->timeout = 10;
        $REMOTE_ADDR        = $_SERVER['REMOTE_ADDR'];
        $Zeit               = date('H:i:s, d.m.Y');

        // accept only registered domains if set
        $email_restriction = trim(Config::get()->EMAIL_DOMAIN_RESTRICTION);
        if (!$validator->ValidateEmailAddress($email, $email_restriction)) {
            if ($email_restriction) {
                $email_restriction_msg_part = '';
                $email_restriction_parts    = explode(',', $email_restriction);
                for ($email_restriction_count = 0; $email_restriction_count < count($email_restriction_parts); $email_restriction_count++) {
                    if ($email_restriction_count == count($email_restriction_parts) - 1) {
                        $email_restriction_msg_part .= '@' . trim($email_restriction_parts[$email_restriction_count]) . '<br>';
                    } else if (($email_restriction_count + 1) % 3) {
                        $email_restriction_msg_part .= '@' . trim($email_restriction_parts[$email_restriction_count]) . ', ';
                    } else {
                        $email_restriction_msg_part .= '@' . trim($email_restriction_parts[$email_restriction_count]) . ',<br>';
                    }
                }
                PageLayout::postError(sprintf(_('Die E-Mail-Adresse fehlt, ist falsch geschrieben oder gehört nicht zu folgenden Domains:%s'),
                    '<br>' . htmlReady($email_restriction_msg_part)));
            } else {
                PageLayout::postError(_('Die E-Mail-Adresse fehlt oder ist falsch geschrieben!'));
            }
            return false;
        }

        if (!$validator->ValidateEmailHost($email)) {     // Mailserver nicht erreichbar, ablehnen
            PageLayout::postError(_('Der Mailserver ist nicht erreichbar. Bitte überprüfen Sie, ob Sie E-Mails mit der angegebenen Adresse verschicken können!'));
            return false;
        } else {       // Server ereichbar
            if (!$validator->ValidateEmailBox($email)) {    // aber user unbekannt. Mail an abuse!
                StudipMail::sendAbuseMessage("edit_about", "Emailbox unbekannt\n\nUser: " . $this->username . "\nEmail: ".$email ."\n\nIP: " . $REMOTE_ADDR ." \nZeit: " . $Zeit . "\n");
                PageLayout::postError(_('Die angegebene E-Mail-Adresse ist nicht erreichbar. Bitte überprüfen Sie Ihre Angaben!'));
                return false;
            }
        }

        if (self::countBySql('email = ? AND user_id != ?', [$email, $this->user_id])) {
            PageLayout::postError(sprintf(_('Die angegebene E-Mail-Adresse wird bereits von einem anderen Benutzer (%s) verwendet. Bitte geben Sie eine andere E-Mail-Adresse an.'),
                htmlReady($this->getFullName())));
            return false;
        }

        if (StudipAuthAbstract::CheckField('auth_user_md5.validation_key', $this->auth_plugin)) {
            PageLayout::postSuccess(_('Ihre E-Mail-Adresse wurde geändert!'));
        } else {
            // auth_plugin does not map validation_key (what if...?)

            // generate 10 char activation key
            $key = '';
            mt_srand((double)microtime() * 1000000);
            for ($i = 1; $i <= 10; $i++) {
                $temp = mt_rand() % 36;
                if ($temp < 10)
                    $temp += 48;   // 0 = chr(48), 9 = chr(57)
                else
                    $temp += 87;   // a = chr(97), z = chr(122)
                $key .= chr($temp);
            }
            $this->validation_key = $key;

            $activatation_url = $GLOBALS['ABSOLUTE_URI_STUDIP'] . 'dispatch.php/login/activate_email?uid=' . $this->user_id . '&key=' . $this->validation_key;
            // include language-specific subject and mailbody with fallback to german
            $lang = getUserLanguagePath($this->id);
            if($lang == '') {
                $lang = 'de';
            }

            // TODO: This should be refactored so that the included file returns an array
            include "locale/$lang/LC_MAILS/change_self_mail.inc.php"; // Defines $subject and $mailbody

            $mail = StudipMail::sendMessage($email, $subject ?? '', $mailbody ?? '');

            if (!$mail) {
                return true;
            }

            $this->store();

            PageLayout::postInfo(sprintf(_('An Ihre neue E-Mail-Adresse <b>%s</b> wurde ein Aktivierungslink geschickt, dem Sie folgen müssen bevor Sie sich das nächste mal einloggen können.'), htmlReady($email)));
            StudipLog::log('USER_CHANGE_EMAIL', $this->user_id, null, "{$this->email} -> {$email}");
        }
        return true;
    }

    /**
     * Merge an user ($old_id) to another user ($new_id).  This is a part of the
     * old numit-plugin.
     *
     * @param string $old_user
     * @param string $new_user
     * @param boolean $identity merge identity (if true)
     *
     * @return array() messages to display after migration
     * @deprecated
     */
    public static function convert($old_id, $new_id, $identity = false)
    {
        NotificationCenter::postNotification('UserWillMigrate', $old_id, $new_id);

        $messages = [];

        //Identitätsrelevante Daten migrieren
        if ($identity) {
            // Veranstaltungseintragungen
            self::removeDoubles('seminar_user', 'Seminar_id', $new_id, $old_id);
            $query = "UPDATE IGNORE seminar_user SET user_id = ? WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            self::removeDoubles('admission_seminar_user', 'seminar_id', $new_id, $old_id);
            $query = "UPDATE IGNORE admission_seminar_user SET user_id = ? WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            self::removeDoubles('termin_related_persons', 'range_id', $new_id, $old_id);
            $query = "UPDATE IGNORE `termin_related_persons` SET `user_id` = ? WHERE `user_id` = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            // Persönliche Infos
            $query = "DELETE FROM user_info WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id]);

            $query = "UPDATE IGNORE user_info SET user_id = ? WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            // Migrate registration timestamp by creating a new empty user info
            // entry
            $query = "INSERT INTO `user_info` (`user_id`, `mkdate`, `chdate`)
                      SELECT ?, `mkdate`, `chdate`
                      FROM `user_info`
                      WHERE `user_id` = ?";
            DBManager::get()->execute($query, [$old_id, $new_id]);

            // Studiengänge
            self::removeDoubles('user_studiengang', 'fach_id', $new_id, $old_id);
            $query = "UPDATE IGNORE user_studiengang SET user_id = ? WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            // Eigene Kategorien
            $query = "UPDATE IGNORE kategorien SET range_id = ? WHERE range_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            // Institute
            self::removeDoubles('user_inst', 'Institut_id', $new_id, $old_id);
            $query = "UPDATE IGNORE user_inst SET user_id = ? WHERE user_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            // Generische Datenfelder zusammenführen (bestehende Einträge des
            // "neuen" Nutzers werden dabei nicht überschrieben)
            $old_user = User::find($old_id);
            $old_user->datafields->each(function ($field) use ($new_id) {
                if (!$field->isNew() && $field->content !== null) {
                    $entry = new DatafieldEntryModel([$field->datafield_id, $new_id, $field->sec_range_id, $field->lang]);

                    if ($entry->content === null || $entry->content === '' || $entry->content === 'default_value') {
                        $entry->content = $field->content;
                        $entry->store();
                    }
                }
            });

            # Datenfelder des alten Nutzers leeren
            $old_user->datafields = [];
            $old_user->store();

            //

            //Buddys
            $query = "UPDATE IGNORE contact SET owner_id = ? WHERE owner_id = ?";
            $statement = DBManager::get()->prepare($query);
            $statement->execute([$new_id, $old_id]);

            // Avatar
            $old_avatar = Avatar::getAvatar($old_id);
            $new_avatar = Avatar::getAvatar($new_id);
            if ($old_avatar->is_customized()) {
                if (!$new_avatar->is_customized()) {
                    $avatar_file = $old_avatar->getFilename(Avatar::NORMAL);
                    $new_avatar->createFrom($avatar_file);
                }
                $old_avatar->reset();
            }

            $messages[] = _('Identitätsrelevante Daten wurden migriert.');
        }

        // Restliche Daten übertragen

        // Dateieintragungen und Ordner
        // TODO (mlunzena) should post a notification
        $query = "UPDATE IGNORE file_refs SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE files SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE folders SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        //Kalender
        $query = "UPDATE IGNORE `calendar_date_assignments` SET `range_id` = ? WHERE `range_id` = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE `calendar_dates` SET `author_id` = ? WHERE `author_id` = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE `calendar_dates` SET `editor_id` = ? WHERE `editor_id` = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        //Archiv
        self::removeDoubles('archiv_user', 'seminar_id', $new_id, $old_id);
        $query = "UPDATE IGNORE archiv_user SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        // Kategorien
        $query = "UPDATE IGNORE kategorien SET range_id = ? WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        // Nachrichten (Interne)
        $query = "UPDATE IGNORE message SET autor_id = ? WHERE autor_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        self::removeDoubles('message_user', 'message_id', $new_id, $old_id);
        $query = "UPDATE IGNORE message_user SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        // News
        $query = "UPDATE IGNORE news SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE news_range SET range_id = ? WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        // Informationsseiten
        $query = "UPDATE IGNORE scm SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        // Statusgruppeneinträge
        self::removeDoubles('statusgruppe_user', 'statusgruppe_id', $new_id, $old_id);
        $query = "UPDATE IGNORE statusgruppe_user SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        // Termine
        $query = "UPDATE IGNORE termine SET autor_id = ? WHERE autor_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        //Votings
        $query = "UPDATE IGNORE questionnaires SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE questionnaire_assignments SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE questionnaire_assignments SET range_id = ? WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        self::removeDoubles('questionnaire_anonymous_answers', 'questionnaire_id', $new_id, $old_id);
        $query = "UPDATE IGNORE questionnaire_anonymous_answers SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        self::removeDoubles('questionnaire_answers', 'question_id', $new_id, $old_id);
        $query = "UPDATE IGNORE questionnaire_answers SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        //Wiki
        $query = "UPDATE IGNORE wiki_pages SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE wiki_versions SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        //Adressbucheinträge
        $query = "UPDATE IGNORE contact SET owner_id = ? WHERE owner_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        //Blubber
        $query = "UPDATE IGNORE blubber_comments SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE blubber_mentions SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE blubber_threads SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE blubber_threads_followstates SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        // Consultations
        $query = "UPDATE IGNORE consultation_blocks SET range_id = ? WHERE range_id = ? AND range_type = 'user'";
        DBManager::get()->execute($query, [$new_id, $old_id]);
        $query = "UPDATE IGNORE consultation_bookings SET user_id = ? WHERE user_id = ?";
        DBManager::get()->execute($query, [$new_id, $old_id]);
        $query = "UPDATE IGNORE consultation_events SET user_id = ? WHERE user_id = ?";
        DBManager::get()->execute($query, [$new_id, $old_id]);
        $query = "UPDATE IGNORE consultation_responsibilities SET range_id = ? WHERE range_id = ?
                                                               AND range_type = 'user'";
        DBManager::get()->execute($query, [$new_id, $old_id]);

        // Courseware
        $query = "UPDATE IGNORE cw_blocks SET owner_id = ? WHERE owner_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_blocks SET editor_id = ? WHERE editor_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_blocks SET edit_blocker_id = ? WHERE edit_blocker_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_block_comments SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_block_feedbacks SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_bookmarks SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_certificates SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_clipboards SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_containers SET owner_id = ? WHERE owner_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_containers SET editor_id = ? WHERE editor_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_containers SET edit_blocker_id = ? WHERE edit_blocker_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_public_links SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_structural_elements SET range_id = ? WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_structural_elements SET owner_id = ? WHERE owner_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_structural_elements SET editor_id = ? WHERE editor_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_structural_elements SET edit_blocker_id = ? WHERE edit_blocker_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_structural_element_comments SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_structural_element_feedbacks SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);

        $query = "UPDATE IGNORE cw_tasks SET solver_id = ? WHERE solver_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_task_feedbacks SET lecturer_id = ? WHERE lecturer_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_task_groups SET lecturer_id = ? WHERE lecturer_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_units SET range_id = ? WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $query = "UPDATE IGNORE cw_units SET creator_id = ? WHERE creator_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_user_data_fields SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);

        $query = "UPDATE IGNORE cw_user_progresses SET user_id = ? WHERE user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);


        NotificationCenter::postNotification('UserDidMigrate', $old_id, $new_id);

        $messages[] = _('Dateien, Termine, Adressbuch, Nachrichten und weitere Daten wurden migriert.');
        return $messages;
    }

    /**
     * Delete double entries of the old and new user. This is a part of the old
     * numit-plugin.
     *
     * @param string $table
     * @param string $field
     * @param md5 $new_id
     * @param md5 $old_id
     * @deprecated
     */
    private static function removeDoubles($table, $field, $new_id, $old_id)
    {
        $items = [];

        $query = "SELECT a.{$field} AS field_item
                  FROM {$table} AS a, {$table} AS b
                  WHERE a.user_id = ? AND b.user_id = ? AND a.{$field} = b.{$field}";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$new_id, $old_id]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $value) {
            array_push($items, $value['field_item']);
        }

        if (!empty($items)) {
            $query = "DELETE FROM `{$table}`
                      WHERE user_id = :user_id AND `{$field}` IN (:items)";

            $statement = DBManager::get()->prepare($query);
            $statement->bindValue(':user_id', $new_id);
            $statement->bindValue(':items', $items, StudipPDO::PARAM_ARRAY);
            $statement->execute();
        }
    }

    /**
     * Returns a descriptive text for the range type.
     *
     * @return string
     */
    public function describeRange()
    {
        return _('NutzerIn');
    }

    /**
     * Returns a unique identificator for the range type.
     *
     * @return string
     */
    public function getRangeType()
    {
        return 'user';
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
        return UserConfig::get($this);
    }

    /**
     * Decides whether the user may access the range.
     *
     * @param string|null $user_id Optional id of a user, defaults to current user
     * @return bool
     */
    public function isAccessibleToUser($user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = self::findCurrent()->id;
        }

        return $user_id === $this->user_id
            || static::find($user_id)->perms === 'root'
            || !in_array($this->visible, ['no', 'never'])
            || (Config::get()->getValue('USER_VISIBILITY_UNKNOWN') && $this->visible === 'unknown')
            || ($this->perms === 'dozent' && Config::get()->getValue('DOZENT_ALWAYS_VISIBLE'));
    }

    /**
     * Decides whether the user may edit/alter the range.
     *
     * @param string|null $user_id Optional id of a user, defaults to current user
     */
    public function isEditableByUser($user_id = null): bool
    {
        return $GLOBALS['perm']->have_profile_perm('user', $this->id);
    }

    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $storage object to store data into
     */
    public static function exportUserData(StoredUserData $storage)
    {
        $sorm = User::findBySQL("user_id = ?", [$storage->user_id]);

        if ($sorm) {
            $limit ='user_id username password perms vorname nachname email validation_key auth_plugin locked lock_comment locked_by visible';
            $field_data = [];
            foreach ($sorm as $row) {
                $field_data[] = $row->toRawArray($limit);
            }
            if ($field_data) {
                $storage->addTabularData(_('Kerndaten'), 'auth_user_md5', $field_data);
            }

            $limit = 'user_id hobby lebenslauf publi schwerp home privatnr privatcell privadr score geschlecht mkdate chdate title_front title_rear preferred_language smsforward_copy smsforward_rec email_forward motto lock_rule';
            $field_data = [];
            foreach ($sorm as $row) {
                $field_data[] = $row->toRawArray($limit);
            }
            if ($field_data) {
                $storage->addTabularData(_('Benutzer Informationen'), 'user_info', $field_data);
            }
        }

        $data = DBManager::get()->fetchAll('SELECT * FROM object_user_visits WHERE user_id = ?', [$storage->user_id]);
        $storage->addTabularData(_('Objekt Aufrufe'), 'object_user_visits', $data);
    }

    /**
     * This callback is called after deleting a User.
     * It removes feedback entries that are associated with the User.
     */
    public function cbRemoveFeedback()
    {
        FeedbackElement::deleteBySQL('user_id = ?', [$this->id]);
        FeedbackEntry::deleteBySQL('user_id = ?', [$this->id]);
    }

    public function cbClearCaches()
    {
        if ($this->isFieldDirty('perms')) {
            RolePersistence::expireUserCache($this->user_id);
        }
    }


    /**
     * @see Range::__toString()
     */
    public function __toString() : string
    {
        return $this->getFullName();
    }

    /**
     * Returns whether a user is blocked either explicitely due to the "locked"
     * property or by a set expiration date.
     *
     * @return bool
     * @since Stud.IP 5.4
     */
    public function isBlocked(): bool
    {
        return $this->locked || $this->isExpired();
    }

    /**
     * Returns whether a user account is expired.
     *
     * @return bool
     * @since Stud.IP 5.4
     */
    public function isExpired(): bool
    {
        return $this->config->EXPIRATION_DATE > 0
            && $this->config->EXPIRATION_DATE < time();
    }

    /**
     * @inheritDoc
     */
    public static function getCalendarOwner(string $owner_id): ?\Studip\Calendar\Owner
    {
        return self::find($owner_id);
    }

    /**
     * @inheritDoc
     */
    public function isCalendarReadable(?string $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = self::findCurrent()->id;
        }

        if ($this->id === $user_id) {
            //The owner can always read their own calendar.
            return true;
        }
        return Contact::countBySql(
            "`owner_id` = :this_user_id AND `user_id` = :other_user_id
            AND `calendar_permissions` <> ''",
            ['this_user_id' => $this->id, 'other_user_id' => $user_id]
        ) > 0;
    }

    /**
     * @inheritDoc
     */
    public function isCalendarWritable(?string $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = self::findCurrent()->id;
        }

        if ($this->id === $user_id) {
            //The owner can always write their own calendar.
            return true;
        }
        if (Config::get()->CALENDAR_GRANT_ALL_INSERT) {
            //All users can write in all users calendars.
            return true;
        }
        return Contact::countBySql(
                "`owner_id` = :this_user_id AND `user_id` = :other_user_id
            AND `calendar_permissions` = 'WRITE'",
            ['this_user_id' => $this->id, 'other_user_id' => $user_id]
        ) > 0;
    }

    /**
     * Delivers the gender as text
     * @return string
     */
    public function getGender(): string
    {
        switch ($this->geschlecht) {
            case 1:
                return _('männlich');
            case 2:
                return _('weiblich');
            case 3:
                return _('divers');
            default:
                return _('unbekannt');
        }
    }

    /**
     * Delivers the gender as text
     * @return string
     */
    public function getSalutation(): string
    {
        switch ($this->geschlecht) {
            case 1:
                return _('Herr');
            case 2:
                return _('Frau');
            default:
                return '';
        }
    }

    /*
     * Returns whether the user has the given permission (for the given range).
     *
     * @param string     $permission
     * @param Range|null $for_range
     *
     * @return bool
     */
    public function hasPermissionLevel(string $permission, ?Range $for_range = null): bool
    {
        if (func_num_args() === 1) {
            return $GLOBALS['perm']->have_perm($permission, $this->id);
        }

        if ($for_range === null) {
            throw new Exception('No valid range given');
        }

        if ($for_range instanceof User) {
            return $GLOBALS['perm']->have_profile_perm($permission, $for_range->id, $this->id);
        }

        return $GLOBALS['perm']->have_studip_perm($permission, $for_range->id, $this->id);
    }
}
