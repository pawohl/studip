<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Nico Müller <nico.mueller@uni-oldenburg.de>
 * @author      Michael Riehemann <michael.riehemann@uni-oldenburg.de>
 * @author      Jan Hendrik Willms <jan.hendrik.willms@uni-oldenburg.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       2.1
 */

/**
 * AutoInsert.php
 * Provides functions required by StEP00216:
 * - Assign seminars for automatic registration of certain user types
 * - Maintenance of registration rules
 *
 * Example of use:
 * @code
 *
 *   # show all auto insert seminars
 *   $auto_sems = AutoInsert::getAllSeminars();
 *
 *   # Save a new auto insert seminar with the user status
 *   AutoInsert::saveSeminar($sem_id, $rechte);
 *
 * @endcode
 */
class AutoInsert
{
    private static $instance = null;
    protected static $seminar_cache = null;

    private $settings = [];

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        $this->loadSettings();
    }

    private function loadSettings()
    {
        $query = "SELECT a.seminar_id, GROUP_CONCAT(a.status,IF(LENGTH(a.domain_id)=0,':keine',CONCAT(':',a.domain_id))) AS domain_status, s.Name, s.Schreibzugriff ";
        $query .= "FROM auto_insert_sem a ";
        $query .= "JOIN seminare AS s USING (Seminar_id) ";
        $query .= "GROUP BY s.seminar_id ";
        $query .= "ORDER BY s.Name";
        $statement = DBManager::get()->query($query);
        $results   = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $result) {
            if ($result['Schreibzugriff'] < 3) {
                $domains = explode(',', $result['domain_status']);

                foreach ($domains as $domain) {
                    $array                                       = explode(':', $domain);
                    $key                                         = $array[1] . '.' . $array[0];
                    $this->settings[$key][$result['seminar_id']] = ['Seminar_id'     => $result['seminar_id'],
                                                                    'name'           => $result['Name'],
                                                                    'Schreibzugriff' => $result['Schreibzugriff']];
                }
            }
        }
    }


    private function getUserSeminars($user_id, $seminare)
    {
        $statement = DBManager::get()->prepare("SELECT Seminar_id, s.name, s.Schreibzugriff, su.status
            FROM seminar_user su
            INNER JOIN seminare s USING(Seminar_id)
            WHERE user_id = ? AND Seminar_id IN(?)");
        $statement->execute([$user_id, $seminare]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Trägt den Benutzer in den Eingestellten veranstaltungen automatisch ein.
     * @param string $user_id
     * @param string|bool $status Wenn Status nicht angegeben wird, wird der Status des Users aus user_id genommen
     * @return array 'added' Namen der Seminare in die der User eingetragen wurde
     *                     array 'removed' Namen der Seminare aus denen der User ausgetragen wurde
     */
    public function saveUser($user_id, $status = false)
    {
        $user = User::find($user_id);
        if (!$user) {
            throw new Exception("Unknown user (id: {$user_id})");
        }

        $domains = [];
        if (!$status) {
            $status = $GLOBALS['perm']->get_perm($user_id);
        }
        foreach (UserDomain::getUserDomainsForUser($user_id) as $d) {
            $domains [] = $d->id; //Domains des Users
        }

        if (count($domains) === 0) {
            $domains [] = 'keine';
        }
        $settings     = [];
        $all_seminare = [];
        foreach ($domains as $domain) {

            $key = $domain . '.' . $status;
            if (isset($this->settings[$key]) && is_array($this->settings[$key])) {
                foreach ($this->settings[$key] as $id => $value) {
                    $settings[$id] = $value;
                }
            }
            foreach ($this->settings as $key) {
                foreach ($key as $id => $sem) {
                    $all_seminare[$id] = $sem;
                }
            }
        }

        $seminare              = [];
        $seminare_tutor_dozent = [];
        foreach ($this->getUserSeminars($user_id, array_keys($all_seminare)) as $sem) {
            $seminare[$sem['Seminar_id']] = $sem;
            if (in_array($sem['status'], ['tutor', 'dozent'])) {
                $seminare_tutor_dozent[$sem['Seminar_id']] = $sem;
            }
        }
        $toAdd    = array_diff_key($settings, $seminare);
        $toRemove = array_diff_key($all_seminare, $toAdd, $settings, $seminare_tutor_dozent);

        $added   = [];
        $removed = [];

        Course::findEachMany(
            function (Course $course) use ($user, &$added) {
                if ($this->addUser($user, $course)) {
                    $added[] = $course->name;
                }
            },
            array_column($toAdd, 'Seminar_id')
        );

        Course::findEachMany(
            function (Course $course) use ($user, &$removed) {
                if ($this->removeUser($user, $course)) {
                    $removed[] = $course->name;
                }
            },
            array_column($toRemove, 'Seminar_id')
        );

        return ['added' => $added, 'removed' => $removed];
    }

    private function addUser(User $user, Course $course): bool
    {
        try {
            $course->addMember($user, regard_contingent: false);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function removeUser(User $user, Course $course): bool
    {
        try {
            $course->deleteMember($user);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Tests if a seminar already has an autoinsert record
     * @param  string $seminar_id Id of the seminar
     * @return bool   Indicating whether the seminar already has an autoinsert record
     */
    public static function checkSeminar($seminar_id, $domain_id = false)
    {
        $cached = self::getSeminarCache();

        if (!isset($cached[$seminar_id])) {
            $query = "SELECT domain_id, 1
                      FROM auto_insert_sem
                      WHERE seminar_id = ?";
            $cached[$seminar_id] = DBManager::get()->fetchGroupedPairs(
                $query,
                [$seminar_id],
                function ($value) {
                    return (bool) $value;
                }
            );
        }

        return array_key_exists($domain_id ?: '', $cached[$seminar_id])
             ? $cached[$seminar_id][$domain_id ?: '']
             : false;
    }

    /**
     * Enables a seminar for autoinsertion of users with the given status(ses)
     * @param string $seminar_id Id of the seminar
     * @param mixed $status      Either a single string or an array of strings
     *                           containing the status(ses) to enable for
     *                           autoinsertion
     */
    public static function saveSeminar($seminar_id, $status, $domain_id)
    {
        $query     = "INSERT INTO auto_insert_sem (seminar_id, status,domain_id) VALUES (?, ?,?)";
        $statement = DBManager::get()->prepare($query);

        foreach ((array)$status as $s) {
            $statement->execute([$seminar_id, $s, $domain_id]);
        }
    }

    /**
     * Updates an autoinsert record for a given seminar, dependent on the
     * parameter $remove it either inserts or removes the record for the given
     * parameters
     *
     * @param string $seminar_id Id of the seminar
     * @param string $status     Status for autoinsertion
     * @param bool $remove       Whether the record should be added or removed
     */
    public static function updateSeminar($seminar_id, $domain, $status, $remove = false)
    {
        $query     = $remove ? "DELETE FROM auto_insert_sem WHERE seminar_id = ? AND status= ? AND domain_id = ?" : "INSERT IGNORE INTO auto_insert_sem (seminar_id, status,domain_id) VALUES (?, ?, ?)";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$seminar_id, $status, $domain]);

        if ($remove) {
            unset(self::getSeminarCache()[$seminar_id]);
        }
    }

    /**
     * Removes a seminar from the autoinsertion process.
     * @param string $seminar_id Id of the seminar
     */
    public static function deleteSeminar($seminar_id): bool
    {
        $query     = "DELETE FROM auto_insert_sem WHERE seminar_id = ?";
        $statement = DBManager::get()->prepare($query);
        $result = $statement->execute([$seminar_id]);

        unset(self::getSeminarCache()[$seminar_id]);

        return $result > 0;
    }

    /**
     * Returns a list of all seminars enabled for autoinsertion
     * @param  bool  Indicates whether only the seminar ids (true) or the full
     *               dataset shall be returned (false)
     * @return array The list of all enabled seminars (format according to $only_sem_id)
     */
    public static function getAllSeminars($only_sem_id = false)
    {
        if ($only_sem_id) {
            $query     = "SELECT DISTINCT seminar_id FROM auto_insert_sem";
            $statement = DBManager::get()->query($query);
            $results   = $statement->fetchAll(PDO::FETCH_COLUMN);
        } else {
            $query = "SELECT a.seminar_id, GROUP_CONCAT(a.status,IF(LENGTH(a.domain_id)=0,':keine',CONCAT(':',a.domain_id))) AS domain_status, s.Name, s.Schreibzugriff ";
            $query .= "FROM auto_insert_sem a ";
            $query .= "JOIN seminare AS s USING (Seminar_id) ";

            $query .= "GROUP BY s.seminar_id ";
            $query .= "ORDER BY s.Name";
            $statement = DBManager::get()->query($query);
            $results   = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $index => $result) {
                $domains = explode(',', $result['domain_status']);
                foreach ($domains as $domain) {
                    $array                                  = explode(':', $domain);
                    $results[$index]['status'][$array[1]][] = $array[0];
                }
            }
        }

        return $results;
    }

    /**
     * Returns a seminar's info for autoinsertion
     * @param  string $seminar_id Id of the seminar
     * @return array  The seminar's data as an associative array
     */
    public static function getSeminar($seminar_id)
    {
        $query = "SELECT a.seminar_id, GROUP_CONCAT(a.status) AS status, s.Name ";
        $query .= "FROM auto_insert_sem a ";
        $query .= "JOIN seminare AS s USING (Seminar_id) ";
        $query .= "WHERE a.seminar_id = ? ";
        $query .= "GROUP BY s.seminar_id";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$seminar_id]);

        $result           = $statement->fetch(PDO::FETCH_ASSOC);
        $result['status'] = explode(',', $result['status']);
        return $result;
    }

    /**
     * Store the user's automatic registration in a seminar redundantly to
     * avoid an annoying reregistration although the user explicitely left the
     * according seminar
     * @param string $user_id    Id of the user
     * @param string $seminar_id Id of the seminar
     */
    public static function saveAutoInsertUser($seminar_id, $user_id)
    {
        $query = "INSERT IGNORE INTO auto_insert_user (Seminar_id, user_id, mkdate)
                  SELECT ?, user_id, UNIX_TIMESTAMP()
                  FROM auth_user_md5
                  WHERE user_id = ? AND perms NOT IN ('root','admin')";
        return DBManager::get()->execute($query, [$seminar_id, $user_id]);
    }

    /**
     * Tests whether a user was already automatically registered for a certain
     * seminar.
     * @param  string $seminar_id Id of the seminar
     * @param  string $user_id    If of the user
     * @return bool   Indicates whether the user was already registered
     */
    public static function checkAutoInsertUser($seminar_id, $user_id)
    {
        $query     = "SELECT 1 FROM auto_insert_user WHERE seminar_id = ? AND user_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute([$seminar_id, $user_id]);
        $result = $statement->fetchColumn();

        return $result > 0;
    }

    /**
     * Returns the cache for seminars.
     */
    protected static function getSeminarCache(): StudipCachedArray
    {
        if (self::$seminar_cache === null) {
            self::$seminar_cache = new StudipCachedArray('AutoInsertSeminars');
        }
        return self::$seminar_cache;
    }
}
