<?php
# Lifter010: TODO
/*
 * studygroup.php - Contains the StudygroupModel class
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author     André Klaßen <andre.klassen@elan-ev.de>
 * @copyright  2009 ELAN e.V.
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category   Stud.IP
 *
 */

require_once 'lib/messaging.inc.php';

class StudygroupModel
{
    /**
     * retrieves all institues suitbable for an admin wrt global studygroup settings
     *
     * @return array institutes
     */
    public static function getInstitutes()
    {
        $institutes = [];

        // Prepare institutes statement
        $query = "SELECT Institut_id, Name
                  FROM Institute
                  WHERE fakultaets_id = ? AND fakultaets_id != Institut_id
                  ORDER BY Name";
        $institute_statement = DBManager::get()->prepare($query);

        // get faculties
        $query = "SELECT Name, Institut_id, 1 AS is_fak, 'admin' AS inst_perms
                  FROM Institute
                  WHERE Institut_id = fakultaets_id
                  ORDER BY Name";
        $stmt = DBManager::get()->query($query);
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $institutes[$data['Institut_id']] = [
                'name'   => $data['Name'],
                'childs' => [],
            ];

            // institutes for faculties
            $institute_statement->execute([$data['Institut_id']]);
            while ($data2 = $institute_statement->fetch(PDO::FETCH_ASSOC)) {
                $institutes[$data['Institut_id']]['childs'][$data2['Institut_id']] = $data2['Name'];
            }
            $institute_statement->closeCursor();
        }

        return $institutes;
    }

    /**
     * allows an user to access a "closed" studygroup
     *
     * @param string username
     * @param string id of a studygroup
     */
    public static function accept_user($username, $sem_id)
    {
        $query = "SELECT user_id
                  FROM admission_seminar_user AS asu
                  JOIN auth_user_md5 AS au USING (user_id)
                  WHERE au.username = ? AND asu.seminar_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$username, $sem_id]);
        if ($accept_user_id = $statement->fetchColumn()) {
            CourseMember::create([
                'seminar_id' => $sem_id,
                'user_id'    => $accept_user_id,
                'status'     => 'autor',
                'gruppe'     => 8,
                'visible'    => 'yes',
            ]);

            AdmissionApplication::deleteBySQL(
                'user_id = ? AND seminar_id = ?',
                [$accept_user_id, $sem_id]
            );

            // Post equivalent notifications to a regular course
            $seminar = Course::find($sem_id);
            NotificationCenter::postNotification(
                'CourseDidChangeMember', $seminar, $accept_user_id
            );
            NotificationCenter::postNotification(
                'UserDidEnterCourse', $sem_id, $accept_user_id
            );
        }
    }

    /**
     * denies access to a "closed" studygroup for an user
     *
     * @param string username
     * @param string id of a studygroup
     *
     * @return void
     */
    public static function deny_user($username, $sem_id)
    {
        $user = User::findByUsername($username);

        AdmissionApplication::deleteBySQL(
            'user_id = ? AND seminar_id = ?',
            [$user->id, $sem_id]
        );
    }

    /**
     * promotes an user in a studygroup wrt to a given perm
     *
     * @param string username
     * @param string id of a studygroup
     * @param string perm
     *
     * @return void
     */
    public static function promote_user($username, $sem_id, $perm)
    {
        $user = User::findByUsername($username);

        $member = CourseMember::find([$sem_id, $user->id]);
        $member->status = $perm;
        $member->store();
    }

    /**
     * removes a user of a studygroup
     *
     * @param string username
     * @param string id of a studygroup
     *
     * @return void
     */
    public static function remove_user($username, $sem_id)
    {
        $user = User::findByUsername($username);

        CourseMember::deleteBySQL(
            'Seminar_id = ? AND user_id = ?',
            [$sem_id, $user->id]
        );

        // Post equivalent notifications to a regular course
        $seminar = Course::find($sem_id);
        NotificationCenter::postNotification('CourseDidChangeMember', $seminar, $user->id);
        NotificationCenter::postNotification('UserDidLeaveCourse', $sem_id, $user->id);
    }

    /**
     * retrieves the count of all studygroups
     *
     * @param string $search        Search term
     * @param mixed  $closed_groups Display closed groups
     * @return int count
     */
    public static function countGroups($search = null, $closed_groups = null)
    {
        $conditions = ['status IN (:studygroup_sem_types)'];
        $parameters['studygroup_sem_types'] = studygroup_sem_types();
        $joins = '';

        // Only root may see hidden studygroups
        if (!$GLOBALS['perm']->have_perm('root')) {
            $conditions[] = 'visible = 1';
        }

        // Search by name?
        if (isset($search)) {
            $joins = "LEFT JOIN `tags_relations` ON (`tags_relations`.`range_id` = seminare.Seminar_id AND `tags_relations`.`range_type` = 'course')
                    LEFT JOIN `tags` ON (`tags`.`id` = `tags_relations`.`tag_id` AND `tags`.`active` = 1) ";
            $conditions[] = "(seminare.`Name` LIKE :search OR `tags`.`name` LIKE :search) ";
            $parameters['search'] = '%' . $search . '%';
        }

        // Show closed groups
        if (isset($closed_groups) && !$closed_groups) {
            $conditions[] = 'admission_prelim = 0';
        }

        return Course::countBySQL(
            ($joins ? $joins.' WHERE ' : '') .
            implode(' AND ', $conditions),
            $parameters
        );
    }

    /**
     * get all studygroups in a paged manner wrt a stort criteria and a search term
     *
     * @param string $sort              Sort criteria
     * @param int    $lower_bound       Lower bound of the resultset
     * @param int    $elements_per_page Elements per page, if null get the global configuration value
     * @param string $search            Search term
     * @param mixed  $closed_groups     Display closed groups
     * @return array studygroups
     */
    public static function getAllGroups($sort = '', $lower_bound = 1, $elements_per_page = null, $search = null, $closed_groups = null)
    {
        if (!$elements_per_page) {
            $elements_per_page = Config::get()->ENTRIES_PER_PAGE;
        }

        $sql = "SELECT s.*
                FROM seminare AS s
                    LEFT JOIN `tags_relations` ON (`tags_relations`.`range_id` = s.Seminar_id AND `tags_relations`.`range_type` = 'course')
                    LEFT JOIN `tags` ON (`tags`.`id` = `tags_relations`.`tag_id` AND `tags`.`active` = 1) ";
        $sql_additional = '';
        $conditions = [];
        $parameters = [];

        $conditions[] = 's.status IN (:studygroup_sem_types)';
        $parameters['studygroup_sem_types'] = studygroup_sem_types();

        if (!$GLOBALS['perm']->have_perm('root')) {
            $conditions[] = 's.visible = 1';
        }

        if (isset($search)) {
            $conditions[] = "(s.`Name` LIKE :search OR `tags`.`name` LIKE :search) ";
            $parameters['search'] = '%' . $search . '%';
        }
        if (isset($closed_groups) && !$closed_groups) {
            $conditions[] = 's.admission_prelim = 0';
        }

        list($sort_by, $sort_order) = explode('_', $sort);
        $sort_order = $sort_order === 'asc' ? 'ASC' : 'DESC';

        // add here the sortings
        if ($sort_by === 'name') {
            $sort_by = 's.Name';
        } elseif ($sort_by === 'founded') {
            $sort_by = 's.mkdate';
        } elseif ($sort_by === 'member') {
            $sort_by = 'members';

            $sql = "SELECT s.*, COUNT(su.user_id) AS members
                    FROM seminare AS s
                        LEFT JOIN `tags_relations` ON (`tags_relations`.`range_id` = s.Seminar_id AND `tags_relations`.`range_type` = 'course')
                        LEFT JOIN `tags` ON (`tags`.`id` = `tags_relations`.`tag_id` AND `tags`.`active` = 1)
                        LEFT JOIN seminar_user AS su USING (Seminar_id)";

        } elseif ($sort_by === 'founder') {
            $sort_by = "GROUP_CONCAT(aum.Nachname ORDER BY su.status, su.position, aum.Nachname, aum.Vorname SEPARATOR ',')";

            $sql = "SELECT s.*
                    FROM seminare AS s
                        LEFT JOIN `tags_relations` ON (`tags_relations`.`range_id` = s.Seminar_id AND `tags_relations`.`range_type` = 'course')
                        LEFT JOIN `tags` ON (`tags`.`id` = `tags_relations`.`tag_id` AND `tags`.`active` = 1) LEFT JOIN seminar_user AS su ON (s.Seminar_id = su.Seminar_id AND su.status = 'dozent')
                        LEFT JOIN auth_user_md5 AS aum ON (su.user_id = aum.user_id)";

        } elseif ($sort_by === 'ismember') {
            $sort_by = 'is_member';

            $sql = "SELECT s.*, COUNT(su.user_id) AS is_member
                    FROM seminare AS s
                        LEFT JOIN `tags_relations` ON (`tags_relations`.`range_id` = s.Seminar_id AND `tags_relations`.`range_type` = 'course')
                        LEFT JOIN `tags` ON (`tags`.`id` = `tags_relations`.`tag_id` AND `tags`.`active` = 1)
                        LEFT JOIN seminar_user AS su ON s.Seminar_id = su.Seminar_id AND su.user_id = :user_id";
            $parameters['user_id'] = $GLOBALS['user']->id;

        } elseif ($sort_by == 'access') {
            $sort_by = 'admission_prelim';
        } else {
            throw new Exception('Invalid sort parameter');
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ' . $sql_additional;
        $sql .= ' GROUP BY s.Seminar_id ';
        $sql .= " ORDER BY {$sort_by} {$sort_order}";
        $sql .= ", s.`name` {$sort_order} LIMIT " . (int) $lower_bound . ',' . (int) $elements_per_page;

        $statement = DBManager::get()->prepare($sql);
        $statement->execute($parameters);
        $groups = $statement->fetchAll();

        foreach ($groups as $key => $studygroup)
        {
            $visit_data = get_objects_visits([$studygroup['Seminar_id']], 0);
            $studygroup['last_visit_date'] = $visit_data[$studygroup['Seminar_id']];
            $groups[$key]['last_visit_date'] = $studygroup['last_visit_date'];
            $groups[$key]['course'] = Course::buildExisting($studygroup);
        }

        return $groups;
    }

    /**
     * returns the count of members for a given studygroup
     *
     * @param string id of a studygroup
     * @return int count
     */
    public static function countMembers($semid)
    {
        return (int) CourseMember::countBySeminar_id($semid);
    }

    /**
     * get founder for a given studgroup
     *
     * @param string id of a studygroup
     * @return array founder
     */
    public static function getFounder($semid)
    {
        $founder = [];
        foreach (CourseMember::findByCourseAndStatus($semid, 'dozent') as $user) {
            $founder[] = [
                'user_id'  => $user->user_id,
                'fullname' => $user->getUserFullname(),
                'uname'    => $user->username,
            ];
        }
        return $founder;
    }

    /**
     * checks whether a user is a member of a studygroup
     *
     * @param string id of a user
     * @param string id of a studygroup
     * @return boolean membership
     */
    public static function isMember($userid, $semid)
    {
        return CourseMember::exists([$semid, $userid]);
    }

    /**
     * adds a founder to a given studygroup
     *
     * @param string username
     * @param string id of a studygroup
     */
    public static function addFounder($username, $sem_id)
    {
        $user = User::findByUsername($username);

        $member = new CourseMember([$sem_id, $user->id]);
        if ($member->isNew()) {
            $member->status = 'dozent';
            $member->store();
        }
    }

    /**
     * removes a founder from a given studygroup
     *
     * @param string username
     * @param string id of a studygroup
     */
    public static function removeFounder($username, $sem_id)
    {
        $user = User::findByUsername($username);

        CourseMember::deleteBySQL(
            'Seminar_id = ? AND user_id = ?',
            [$sem_id, $user->id]
        );
    }

    /**
     * get founders of a given studygroup
     *
     * @param string id of a studygroup
     * @return array founders
     */
    public static function getFounders($sem_id)
    {
        $query = "SELECT username, perms, {$GLOBALS['_fullname_sql']['full_rev']} AS fullname
                  FROM seminar_user
                  LEFT JOIN auth_user_md5 USING (user_id)
                  LEFT JOIN user_info USING (user_id)
                  WHERE Seminar_id = ? AND status = 'dozent'";

        $stmt = DBManager::get()->prepare($query);
        $stmt->execute([$sem_id]);

        return $stmt->fetchAll();
    }

    /**
     * retrieves all members of a given studygroup in a paged manner
     *
     * @param string id of a studygroup
     * @param int lower bound of the resultset
     * @param int elements per page, if null get the global configuration value
     *
     * @return array members
     */
    public static function getMembers($sem_id, $lower_bound = 1, $elements_per_page = null)
    {
        if (!$elements_per_page) {
            $elements_per_page = Config::get()->ENTRIES_PER_PAGE;
        }

        $query = "SELECT user_id ,username ,perms, seminar_user.status,
                         {$GLOBALS['_fullname_sql']['full_rev']} AS fullname,
                         seminar_user.mkdate
                  FROM seminar_user
                  LEFT JOIN auth_user_md5 USING (user_id)
                  LEFT JOIN user_info USING (user_id)
                  WHERE Seminar_id = ?
                  ORDER BY seminar_user.mkdate ASC, seminar_user.status ASC";

        if ($elements_per_page !== 'all') {
            $query .= " LIMIT {$lower_bound}, {$elements_per_page}";
        }

        return DBManager::get()->fetchGrouped($query, [$sem_id]);
    }

    /**
     * invites a member to a given studygroup.
     *
     * @param string $user_id
     * @param string $sem_id id of a studygroup
     */
    public static function inviteMember($user_id, $sem_id)
    {
        $invitation = new StudygroupInvitation([$sem_id, $user_id]);
        $invitation->mkdate = time();
        $invitation->store();
    }

    /**
     * cancels invitation.
     *
     * @param string $username
     * @param string $sem_id id of a studygroup
     */
    public static function cancelInvitation($username, $sem_id)
    {
        $user = User::findOneByUsername($username);
        if (!$user) {
            return;
        }

        StudygroupInvitation::deleteBySQL(
            'sem_id = ? AND user_id = ?',
            [$sem_id, $user->id]
        );
    }

    /**
     * returns invited member of a given studygroup.
     *
     * @param string id of a studygroup
     * @return array invited members
     * @deprecated Will be removed in Stud.IP 6.2
     */
    public static function getInvitations($sem_id)
    {
        $query = "SELECT username, user_id,
                         {$GLOBALS['_fullname_sql']['full_rev']} AS fullname,
                         studygroup_invitations.mkdate
                  FROM studygroup_invitations
                  LEFT JOIN auth_user_md5 USING (user_id)
                  LEFT JOIN user_info USING (user_id)
                  WHERE studygroup_invitations.sem_id = ?
                  ORDER BY studygroup_invitations.mkdate";

        $stmt = DBManager::get()->prepare($query);
        $stmt->execute([$sem_id]);

        return $stmt->fetchAll();
    }

    /**
     * checks if a user is already invited.
     *
     * @param string $user_id
     * @param string $sem_id id of a studygroup
     * @return bool
     */
    public static function isInvited($user_id, $sem_id)
    {
        return (bool) StudygroupInvitation::countBySql(
            'sem_id = ? AND user_id = ?',
            [$sem_id, $user_id]
        );
    }

    /**
     * Checks for a given seminar_id whether a course is a studygroup
     *
     * @param   string id of a seminar
     *
     * @return  array studygroup
     */
    public static function isStudygroup($sem_id)
    {
        $sql = "SELECT *
                FROM seminare
                WHERE Seminar_id = ? AND status IN (?)";
        $stmt = DBManager::get()->prepare($sql);
        $stmt->execute([
            $sem_id,
            studygroup_sem_types()
        ]);

        return $stmt->fetch();
    }

    /**
     * If a new user applies, an application note to all moderators and founders
     * of a studygroup will be automatically sent while calling this function.
     * The note contains the user's name and a direct link to the member page of the studygroup.
     *
     * @param string $sem_id id of a seminar / studygroup
     * @param string $user_id id of the applicant
     * @return int number of recipients
     */
    public static function applicationNotice($sem_id, $user_id)
    {
        $course = Course::find($sem_id);
        $msging = new messaging();

        //Get all those with tutor and dozent status to inform them
        //about the application:
        $stmt = DBManager::get()->prepare(
            "SELECT `username`
             FROM `auth_user_md5`
             JOIN `seminar_user` USING (`user_id`)
             WHERE `seminar_user`.`seminar_id` = :course_id
               AND `seminar_user`.`status` IN ('dozent', 'tutor')"
        );
        $stmt->execute(['course_id' => $course->id]);
        $recipients = $stmt->fetchAll();

        //Limit the subject prefix size in case of a long course name:
        if (mb_strlen($course->name) > 32) {
            $subject = sprintf(_('[Studiengruppe: %s...]'), mb_substr($course->name, 0, 30));
        } else {
            $subject = sprintf(_('[Studiengruppe: %s]'), $course->name);
        }

        if (StudygroupModel::isInvited($user_id, $sem_id)) {
            $subject .= ' ' . _('Einladung akzeptiert');
            $message = sprintf(
                _("%s hat die Einladung zur Studiengruppe %s akzeptiert. Klicken Sie auf den folgenden Link, um direkt zur Studiengruppe zu gelangen.\n\n [Direkt zur Studiengruppe]%s"),
                get_fullname($user_id),
                $course->name,
                URLHelper::getlink(
                    "{$GLOBALS['ABSOLUTE_URI_STUDIP']}dispatch.php/course/studygroup/members",
                    ['cid' => $course->id]
                )
            );
        } else {
            $subject .= ' ' . _('Neuer Mitgliedsantrag');
            $message = sprintf(
                _("%s möchte der Studiengruppe %s beitreten. Klicken Sie auf den folgenden Link, um direkt zur Studiengruppe zu gelangen.\n\n [Direkt zur Studiengruppe]%s"),
                get_fullname($user_id),
                $course->name,
                URLHelper::getlink(
                    "{$GLOBALS['ABSOLUTE_URI_STUDIP']}dispatch.php/course/studygroup/members",
                    ['cid' => $course->id]
                )
            );
        }

        return $msging->insert_message($message, $recipients, '', '', '', '1', '', $subject);
    }

    /**
     * @param Course $studygroup
     * @param $course_id
     * @return false|string
     */
    public static function proposeAsStudygroupTo(Course $studygroup, $course_id)
    {
        if (!$GLOBALS['perm']->have_studip_perm('tutor', $studygroup->id) && !$GLOBALS['perm']->have_studip_perm('tutor')) {
            return false;
        }
        $proposal = StudygroupCourseProposal::findOneBySQL('course_id = ? AND studygroup_id = ?', [
            $course_id,
            $studygroup->id
        ]);
        if ($GLOBALS['perm']->have_studip_perm('tutor', $course_id) || $proposal['proposed_from'] === 'course') {
            $connection = StudygroupCourse::findOneBySQL('course_id = ? AND studygroup_id = ?', [
                $course_id,
                $studygroup->id
            ]);
            if (!$connection) {
                $connection = StudygroupCourse::create([
                    'course_id' => $course_id,
                    'studygroup_id' => $studygroup->id
                ]);
            }
            if ($proposal) {
                if ($proposal['proposed_from'] === 'course') {
                    $statement = DBManager::get()->prepare("
                            SELECT `username`, `user_id`
                            FROM `auth_user_md5`
                                INNER JOIN `seminar_user` USING (`user_id`)
                            WHERE `seminar_user`.`Seminar_id` = ? AND `seminar_user`.`status` IN ('tutor', 'dozent')
                        ");
                    $statement->execute([$course_id]);
                    $messaging = new messaging();

                    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $user_data) {
                        setTempLanguage($user_data['user_id']);
                        $messaging->insert_message(
                            sprintf(
                                _('Ihr Vorschlag, die Studiengruppe „%1$s“ mit der Veranstaltung „%2$s“ zu verknüpfen, wurde angenommen.'),
                                $studygroup->getFullname(),
                                Course::find($course_id)->getFullname()
                            ),
                            $user_data['username'],
                            '____%system%____',
                            '',
                            '',
                            '',
                            '',
                            _('Verknüpfungsvorschlag angenommen'),
                            '',
                            'normal',
                            ['Studiengruppe']
                        );
                        restoreLanguage();
                    }
                }
                $proposal->delete();
            }
            PageLayout::postSuccess(_('Veranstaltung wurde verknüpft.'));
            return 'connected';
        } else {
            if (!$proposal) {
                $proposal = StudygroupCourseProposal::create([
                    'course_id' => $course_id,
                    'studygroup_id' => $studygroup->id,
                    'proposed_from' => 'studygroup',
                    'user_id' => User::findCurrent()->id
                ]);
                //send message:
                $statement = DBManager::get()->prepare("
                        SELECT `username`, `user_id`
                        FROM `auth_user_md5`
                            INNER JOIN `seminar_user` USING (`user_id`)
                        WHERE `seminar_user`.`Seminar_id` = ? AND `seminar_user`.`status` IN ('tutor', 'dozent')
                    ");
                $statement->execute([$course_id]);
                $messaging = new messaging();
                $oldbase = URLHelper::setBaseURL($GLOBALS['ABSOLUTE_URI_STUDIP']);

                foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $user_data) {
                    setTempLanguage($user_data['user_id']);
                    $messaging->insert_message(
                        sprintf(
                            _('Es wurde vorgeschlagen, die Studiengruppe „%1$s“ mit Ihrer Veranstaltung „%2$s“ zu verknüpfen. Sie können den Vorschlag unter folgendem Link annehmen oder ablehnen:'),
                            $studygroup->getFullname(),
                            Course::find($course_id)->getFullname()
                        )."\n\n".URLHelper::getURL('dispatch.php/course/connectedstudygroups/index', ['cid' => $course_id]),
                        $user_data['username'],
                        '____%system%____',
                        '',
                        '',
                        '',
                        '',
                        _('Verknüpfung Ihrer Veranstaltung zu einer Studiengruppe'),
                        '',
                        'normal',
                        ['Studiengruppe']
                    );
                    restoreLanguage();
                }
                URLHelper::setBaseURL($oldbase);
                return 'proposed';
            }
        }
        return false;
    }

    /**
     * Retrieves all study groups for the current user.
     *
     * @returns array A two-dimensional array. The second dimension contains
     *     data for each study group. Most fields of the Course model are
     *     present in the second dimension and there are additional fields
     *     like the colour (gruppe) or the start and end semester.
     */
    public static function getStudygroups()
    {
        $studygroup_sem_types = array_filter(
            array_keys($GLOBALS['SEM_TYPE']),
            function ($sem_type_id) {
                return (bool) $GLOBALS['SEM_CLASS'][$GLOBALS['SEM_TYPE'][$sem_type_id]['class']]['studygroup_mode'];
            }
        );
        $studygroup_memberships = CourseMember::findBySQL(
            'INNER JOIN `seminare` USING (`seminar_id`)
            WHERE `seminar_user`.`user_id` = :me
            AND `seminare`.`status` IN (:studygroup_semtypes)
            GROUP BY `seminar_id`
            ORDER BY `seminar_user`.`gruppe` ASC, `seminare`.`name` ASC',
            [
                'me' => User::findCurrent()->id,
                'studygroup_semtypes' => $studygroup_sem_types
            ]
        );
        $studygroups = [];
        Course::findEachMany(
            function ($studygroup) use (&$studygroups) {
                $studygroups[$studygroup->id] = $studygroup;
            },
            array_map(
                function ($membership) {
                    return $membership->seminar_id;
                },
                $studygroup_memberships
            )
        );

        $data_fields = 'name seminar_id visible veranstaltungsnummer duration_time status visible '
            . 'chdate admission_binding admission_prelim';
        $studygroup_data = [];
        foreach ($studygroup_memberships as $membership) {
            if (!isset($studygroups[$membership->seminar_id])) {
                continue;
            }
            $studygroup = $studygroups[$membership->seminar_id];
            $visit_data = get_objects_visits([$studygroup->id], 0, null, null, $studygroup->tools->pluck('plugin_id'));
            $data = $studygroup->toArray($data_fields);
            $data['tools'] = $studygroup->tools;
            $data['sem_class'] = $studygroup->getSemClass();
            $data['start_semester'] = $studygroup->start_semester->name;
            $data['end_semester'] = $studygroup->end_semester->name ?? '';
            $data['obj_type'] = 'sem';
            $data['user_status'] = $membership->status;
            $data['gruppe'] = $membership->gruppe;
            $data['mkdate'] = $membership->mkdate;
            $data['visitdate'] = $visit_data[$studygroup->id][0]['visitdate'];
            $data['last_visitdate'] = $visit_data[$studygroup->id][0]['last_visitdate'];
            $data['navigation'] = MyRealmModel::getAdditionalNavigations(
                $studygroup->id,
                $data,
                $data['sem_class'],
                $GLOBALS['user']->id,
                $visit_data[$studygroup->id]
            );
            $studygroup_data[$studygroup->id] = $data;
        }

        return $studygroup_data;
    }
}
