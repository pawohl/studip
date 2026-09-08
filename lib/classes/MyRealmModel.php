<?php
/**
 * my_courses.php - Model for user and seminar related
 * pages under "Meine Veranstaltungen"
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 * @author      David Siegfried <david@ds-labs.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2 or later
 * @category    Stud.IP
 * @since       3.1
 */

require_once 'lib/object.inc.php';

class MyRealmModel
{

    /**
     * Check the voting system
     * @param string $my_obj
     * @param string $user_id
     * @param  $object_id
     */
    public static function checkVote(&$my_obj, $user_id, $object_id)
    {
        $count = 0;
        $neue  = 0;
        $type  = get_object_type($object_id, ['sem']) === 'sem' ? 'course' : 'institute';

        $statusgruppen = Statusgruppen::findByRange_id($object_id);
        if (!$GLOBALS['perm']->have_studip_perm("tutor", $object_id)) {
            $statusgruppen = array_filter($statusgruppen, function ($sg) use ($user_id) {
                return $sg->isMember($user_id);
            });
        }

        $threshold = object_get_visit_threshold();
        $statement = DBManager::get()->prepare("
            SELECT COUNT(DISTINCT questionnaires.questionnaire_id) AS count,
                COUNT(IF((questionnaires.chdate > IFNULL(object_user_visits.visitdate, :threshold) AND questionnaires.user_id !=:user_id), questionnaires.questionnaire_id, NULL)) AS new,
                MAX(IF((questionnaires.chdate > IFNULL(object_user_visits.visitdate, :threshold) AND questionnaires.user_id !=:user_id), questionnaires.chdate, 0)) AS last_modified
            FROM questionnaire_assignments
                INNER JOIN questionnaires ON (questionnaires.questionnaire_id = questionnaire_assignments.questionnaire_id)
                LEFT JOIN object_user_visits ON(object_user_visits.object_id = questionnaires.questionnaire_id AND object_user_visits.user_id = :user_id AND object_user_visits.plugin_id = -1)
            WHERE (
                    (
                        questionnaire_assignments.range_id = :course_id
                        AND questionnaire_assignments.range_type IN ('course', 'institute')
                    ) OR (
                        questionnaire_assignments.range_id IN (:statusgruppe_ids)
                        AND questionnaire_assignments.range_type = 'statusgruppe'
                    )
                )
                AND questionnaires.startdate IS NOT NULL
                AND questionnaires.startdate <= UNIX_TIMESTAMP()
                AND (
                    questionnaires.stopdate IS NULL
                    OR questionnaires.stopdate > UNIX_TIMESTAMP()
               )
            GROUP BY questionnaire_assignments.range_id
        ");
        $statement->execute([
            'threshold'        => $threshold,
            'user_id'          => $user_id,
            'course_id'        => $object_id,
            'plugin_id'        => -1,
            'statusgruppe_ids' => array_map(function ($g) { return $g->getId(); }, $statusgruppen)
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (!empty($result)) {
            $count = $result['count'];
            $neue  = $result['new'];

            if (!is_null($result['last_modified']) && (int)$result['last_modified'] != 0) {
                if (!isset($my_obj['last_modified']) || $my_obj['last_modified'] < $result['last_modified']) {
                    $my_obj['last_modified'] = $result['last_modified'];
                }
            }
        }

        if ($neue || $count > 0) {
            $nav = new Navigation(
                'vote',
                "dispatch.php/{$type}/overview#vote"
            );
            if ($neue) {
                $nav->setImage(Icon::create('vote', Icon::ROLE_ATTENTION, [
                    'title' => sprintf(
                        ngettext(
                            '%1$u Fragebogen, %2$u neuer',
                            '%1$u Fragebögen, %2$u neue',
                            $count
                        ),
                        $count,
                        $neue
                    )
                ]));
                $nav->setBadgeNumber($neue);
            } else if ($count) {
                $nav->setImage(Icon::create('vote'));
                $nav->setLinkAttributes([
                    'title' => sprintf(
                        ngettext(
                            '%u Fragebogen',
                            '%u Fragebögen',
                            $count
                        ),
                        $count
                    )
                ]);
            }
            return $nav;
        }

        return null;
    }



    /**
     * Get all courses vor given user in selected semesters
     *
     * @return Course[]
     */
    public static function getCourses($min_sem_key, $max_sem_key, $params = [])
    {
        // init
        $order_by          = $params['order_by'] ?? null;
        $order             = $params['order'] ?? null;
        $deputies_enabled  = !empty($params['deputies_enabled']);
        $studygroup_filter = !empty($params['studygroups_enabled']);
        $studygroups_only  = !empty($params['studygroups_only']);

        $sem_data = Semester::getAllAsArray();

        $semester_ids = [];
        if (is_numeric($min_sem_key) && is_numeric($max_sem_key)) {
            foreach ($sem_data as $index => $data) {
                if (
                    $index >= $min_sem_key && $index <= $max_sem_key
                    && (
                        !isset($params['exactly'])
                        || in_array($index, $params['exactly'])
                    )
                ) {
                    $semester_ids[] = $data['semester_id'] ?? '';
                }
            }
        }

        $semesters = Semester::findMany($semester_ids);
        $ordering          = '';
        // create ordering
        if (!$order_by) {
            if (Config::get()->IMPORTANT_SEMNUMBER) {
                $ordering = 'veranstaltungsnummer asc, name asc';
            } else {
                $ordering .= 'name asc';
            }
        } else {
            $ordering .= $order_by . ' ' . $order;
        }

        // search for your own courses
        // Filtering by Semester
        $courses = Course::findThru($GLOBALS['user']->id, [
            'thru_table'        => 'seminar_user',
            'thru_key'          => 'user_id',
            'thru_assoc_key'    => 'seminar_id',
            'assoc_foreign_key' => 'seminar_id'
        ]);

        if ($deputies_enabled) {
            $deputy_courses = Deputy::findDeputyCourses($GLOBALS['user']->id)->pluck('course');
            if (!empty($deputy_courses)) {
                $courses = array_merge($courses, $deputy_courses);
            }
        }
        // create a new collection for more functionality
        $courses = new SimpleCollection($courses);
        if ($studygroup_filter && $studygroups_only) {
            $courses = $courses->filter(fn($a) => $a->isStudygroup());
        } else {
            $courses = $courses->filter(function (Course $a) use ($studygroup_filter, $semesters) {
                if ($studygroup_filter && $a->isStudygroup()) {
                    return false;
                }

                foreach ($semesters as $semester) {
                    if ($a->isInSemester($semester)) {
                        return true;
                    }
                }
                return false;
            });
        }
        return self::sortCourses($courses, $ordering);
    }

    public static function getSelectedSemesters($sem = '')
    {
        $sem_data = Semester::getAllAsArray();
        $semesters = [];
        $current_sem = null;
        foreach ($sem_data as $sem_key => $one_sem) {
            $current_sem = $sem_key;
            if (!$one_sem['past']) {
                break;
            }
        }

        if (isset($sem_data[$current_sem + 1])) {
            $max_sem = $current_sem + 1;
        } else {
            $max_sem = $current_sem;
        }

        // Get the needed semester
        if (!in_array($sem, ['', 'current', 'future', 'last', 'lastandnext','lastbutone'])) {
            $semesters[] = Semester::getIndexById($sem);
        } else {
            switch ($sem) {
                case 'current':
                    $semesters[] = $current_sem;
                    break;
                case 'future':
                    $semesters[] = $current_sem;
                    $semesters[] = $max_sem;
                    break;
                case 'last':
                    $semesters[] = $current_sem - 1;
                    $semesters[] = $current_sem;
                    break;
                case 'lastandnext':
                    $semesters[] = $current_sem - 1;
                    $semesters[] = $current_sem;
                    $semesters[] = $max_sem;
                    break;
                case 'lastbutone':
                    $semesters[] = $current_sem - 2;
                    $semesters[] = $current_sem;
                    break;
                default:
                    $semesters = array_keys($sem_data);
                    break;
            }
        }

        return $semesters;
    }

    /**
     * Returns a list of all matching courses prepared for further treatment.
     *
     * @param string $sem Semester index
     * @param array $params Additional parameters
     * @return array
     */
    public static function getPreparedCourses($sem = '', $params = [])
    {
        $studygroups_only = !empty($params['studygroups_only']);

        $semesters   = self::getSelectedSemesters($sem);
        $current_semester_nr = Semester::getIndexById(Semester::findCurrent()->id ?? null);
        $min_sem_key = min($semesters);
        $max_sem_key = max($semesters);
        $group_field = $params['group_field'];
        $courses     = self::getCourses($min_sem_key, $max_sem_key, $params + ['exactly' => $semesters]);
        $show_semester_name = UserConfig::get($GLOBALS['user']->id)->SHOWSEM_ENABLE;
        $sem_courses = [];

        $param_array = 'name seminar_id visible veranstaltungsnummer status visible ';
        $param_array .= 'chdate admission_binding modules admission_prelim';

        // filtering courses
        $member_ships = User::findCurrent()->course_memberships->toGroupedArray('seminar_id', 'status gruppe');
        $children = [];
        $semester_assign = [];

        $courses2 = $courses;
        foreach ($courses2 as $course) {
            foreach ($course->studygroups as $studygroup) {
                $courses[] = $studygroup;
            }
        }
        $courses = $courses2;

        foreach ($courses as $course) {
            // export object to array for simple handling
            $_course = $course->toArray($param_array);
            $_course['start_semester'] = $course->start_semester ? $course->start_semester->name : null;
            $_course['end_semester']   = $course->end_semester ? $course->end_semester->name : null;
            $_course['sem_class']      = $course->getSemClass();
            $_course['obj_type']       = 'sem';

            if ($group_field === 'sem_tree_id') {
                $_course['sem_tree'] = $course->study_areas->toArray();
            }
            $deputy = null;
            $user_status = $member_ships[$course->id]['status'] ?? null;
            if (!$user_status && Config::get()->DEPUTIES_ENABLE && Deputy::isDeputy($GLOBALS['user']->id, $course->id)) {
                $user_status = 'dozent';
                $deputy = Deputy::findOneBySQL('range_id = ? AND user_id = ?', [$course->id, $GLOBALS['user']->id]);
                $is_deputy = true;
            } else {
                $is_deputy = false;
            }

            // get teachers only if grouping selected (for better performance)
            if ($group_field === 'dozent_id') {
                $teachers = new SimpleCollection($course->getMembersWithStatus('dozent'));
                $_course['teachers'] = $teachers->map(function (CourseMember $a) {
                    return $a->user->getFullName('no_title_rev');
                });
            }

            $_course['user_status']    = $user_status;
            $_course['gruppe']         = !$is_deputy ? $member_ships[$course->id]['gruppe'] ?? null : ($deputy ? $deputy->gruppe : null);
            $_course['sem_number_end'] = $course->isOpenEnded() ? $max_sem_key : Semester::getIndexById($course->end_semester->id);
            $_course['sem_number']     = Semester::getIndexById($course->start_semester->id ?? null);
            $_course['tools']          = $course->tools;
            $_course['name']           = $course->name;
            $_course['temp_name']      = $course->name;
            $_course['number']         = $course->veranstaltungsnummer;
            $_course['is_deputy']      = $is_deputy;
            if ($show_semester_name && count($course->semesters) !== 1 && !$course->getSemClass()['studygroup_mode']) {
                $_course['name'] .= ' (' . $course->getTextualSemester() . ')';
            }
            $_course['parent_course'] = $course->parent_course ?? null;
            $_course['is_group'] = $course->getSemClass()->isGroup();

            // add the course to the correct semester

            if (empty($_course['parent_course'])) {
                if ($course->isOpenEnded()) {
                    if ($current_semester_nr >= $min_sem_key && $current_semester_nr <= $max_sem_key) {
                        $sem_courses[$current_semester_nr][$course->id] = $_course;
                        $semester_assign[$course->id] = $current_semester_nr;
                    } else {
                        $sem_courses[$max_sem_key][$course->id] = $_course;
                        $semester_assign[$course->id] = $max_sem_key;
                    }
                } else {
                    for ($i = $min_sem_key; $i <= $max_sem_key; $i += 1) {
                        if ($i >= $_course['sem_number'] && $i <= $_course['sem_number_end']) {
                            $sem_courses[$i][$course->id] = $_course;
                            $semester_assign[$course->id] = $i;
                        }
                    }
                }
            } elseif (!empty($_course['parent_course'])) {
                $children[$_course['parent_course']][] = $_course;
            }
            if ($course->isStudygroup()) {
                foreach ($course->connectedcourses as $connectedcourse) {
                    if ($studygroups_only && !$connectedcourse->isStudygroup()) {
                        continue;
                    }

                    if ($GLOBALS['perm']->have_studip_perm('user', $course->id)) {
                        $children[$connectedcourse->id][] = $_course;
                    }
                }
            }
        }

        // Now sort children directly under their parent.
        foreach ($children as $parent => $kids) {
            $sem_courses[$semester_assign[$parent]][$parent]['children'] = $kids;
        }

        $navs = self::getManyAdditionalNavigations(
            $sem_courses,
            User::findCurrent()->id
        );

        foreach ($sem_courses as $index => $courses) {
            foreach ($courses as $c_id => $course) {
                if (!empty($navs[$c_id])) {
                    $sem_courses[$index][$c_id] = array_merge($course, $navs[$c_id]);
                }
            }
        }

        if (!empty($params['main_navigation'])) {
            return $sem_courses;
        }

        krsort($sem_courses);

        // grouping
        if ($group_field === 'sem_number' && !$params['order_by']) {
            foreach ($sem_courses as $index => $courses) {
                uasort($courses, function ($a, $b) {
                    $extra_condition = 0;
                    if (Config::get()->IMPORTANT_SEMNUMBER) {
                        $extra_condition = strcmp($a['number'], $b['number']);
                    }

                    return ($a['gruppe'] - $b['gruppe'])
                        ?: $extra_condition
                        ?: strcmp($a['temp_name'], $b['temp_name']);
                });
                $sem_courses[$index] = $courses;
            }
        }
        // Group by teacher
        if ($group_field === 'dozent_id') {
            self::groupByTeacher($sem_courses);
        }

        // Group by Sem Status
        if ($group_field === 'sem_status') {
            self::groupBySemStatus($sem_courses);
        }

        // Group by colors
        if ($group_field === 'gruppe') {
            self::groupByGruppe($sem_courses);
        }

        // Group by sem_tree
        if ($group_field === 'sem_tree_id') {
            self::groupBySemTree($sem_courses);
        }

        // Group by mvv module
        if ($group_field === 'mvv') {
            self::groupByMVVModule($sem_courses);
        }

        return $sem_courses;
    }

    /**
     * Get the whole icon-navigation for a given course
     * @param $object_id
     * @param $my_obj_values
     * @param null $sem_class
     * @param $user_id
     * @return array
     */
    public static function getAdditionalNavigations($object_id, &$my_obj_values, $sem_class, $user_id, $visit_data = [])
    {
        $navigation = [];
        foreach (self::getDefaultModules() as $plugin_id => $plugin) {

            // Go to next module if current module is not available and not voting-module
            if ($plugin !== 'vote' && !$my_obj_values['tools']->findOneBy('plugin_id', $plugin_id)) {
                $navigation[$plugin_id] = null;
                continue;
            }

            if ($plugin === 'vote') {
                $navigation[$plugin_id] = self::checkVote($my_obj_values, $user_id, $object_id);
            } else if ($tool = $my_obj_values['tools']->findOneBy('plugin_id', $plugin_id)) {
                if (Seminar_Perm::get()->have_studip_perm($tool->getVisibilityPermission(), $object_id, $user_id)) {
                    $navigation[$plugin_id] = $plugin->getIconNavigation($object_id, $visit_data[$plugin_id]['visitdate'], $user_id);
                } else {
                    $navigation[$plugin_id] = null;
                }
            }
        }
        foreach ($my_obj_values['tools'] as $tool) {
            if (array_key_exists($tool->plugin_id, $navigation)) {
                continue;
            }
            $module = $tool->getStudipModule();
            if (!$module || get_class($module)  === 'CoreAdmin' || get_class($module)  === 'CoreStudygroupAdmin') {
                continue;
            }
            if (Seminar_Perm::get()->have_studip_perm($tool->getVisibilityPermission(), $object_id, $user_id)) {
                $navigation[$tool->plugin_id] = $module->getIconNavigation($object_id, $visit_data[$tool->plugin_id]['visitdate'], $user_id);
            } else {
                $navigation[$tool->plugin_id] = null;
            }
        }
        return $navigation;
    }

    private static function getManyAdditionalNavigations(array $sem_courses, $user_id): array
    {
        // -- 0. Extract all courses: semester is irrelevant and flatten for children
        $all_courses = [];
        foreach ($sem_courses as $courses) {
            foreach ($courses as $c_id => $course) {
                $all_courses[$c_id] = $course;
                if (!empty($course['children'])) {
                    foreach ($course['children'] as $child_course) {
                        $all_courses[$child_course['seminar_id']] = $child_course;
                    }
                }
            }
        }

        // -- 1. Calculate all the relevant courses for each StudipModule
        $navigation = [];
        $activated_tools = [];
        $default_modules = self::getDefaultModules();
        foreach ($all_courses as $course_id => $course) {
            // add every default module with null, so there will be blank spaces in the nav
            $navigation[$course_id] = array_fill_keys(
                array_keys($default_modules),
                null
            );

            foreach ($course['tools'] as $tool) {
                $studip_module = $tool->getStudipModule();
                if (
                    !$studip_module
                    || $studip_module instanceof CoreAdmin
                    || $studip_module instanceof CoreStudygroupAdmin
                ) {
                    continue;
                }
                if (Seminar_Perm::get()->have_studip_perm($tool->getVisibilityPermission(), $course_id, $user_id)) {
                    $activated_tools[$tool['plugin_id']]['studip_module'] = $studip_module;
                    $activated_tools[$tool['plugin_id']]['courses'][$course_id] = $course_id;
                }
            }
        }

        // -- 2. Fetch the Navigation per StudipModule
        $all_course_ids = array_keys($all_courses);
        $visits = get_objects_visits($all_course_ids, 0, null, null, array_keys($activated_tools));
        foreach ($activated_tools as $plugin_id => $plugin_data) {
            $c_ids = $plugin_data['courses'];
            if ($c_ids) {
                if ($plugin_data['studip_module'] instanceof StudipModuleExtended) {
                    $fetched_navs = $plugin_data['studip_module']->getManyIconNavigation($c_ids, $user_id);
                    foreach ($fetched_navs as $fetched_c_id => $fetched_nav) {
                        $navigation[$fetched_c_id][$plugin_id] = $fetched_nav;
                    }
                } else {
                    foreach ($c_ids as $c_id) {
                        $navigation[$c_id][$plugin_id] = $plugin_data['studip_module']->getIconNavigation(
                            $c_id,
                            $visits[$c_id][$plugin_id]['visitdate'],
                            $user_id
                        );
                    }
                }

                if ($plugin_data['studip_module'] instanceof CoreOverview) {
                    foreach ($c_ids as $c_id) {
                        // TODO testing vote need to be done
                        $voteNavigation = self::checkVote($all_courses[$c_id], $user_id, $c_id);
                        if ($voteNavigation) {
                            $navigation[$c_id][] = $voteNavigation;
                        }
                    }
                }
            }
        }

        // -- 3. Set each nav and visitdate by course
        $result = [];
        foreach ($navigation as $cid => $nav) {
            $result[$cid] = [
                'navigation' => $nav,
                'visitdate' => $visits[$cid][0]['visitdate'] ?? null,
                'last_visitdate' => $visits[$cid][0]['last_visitdate'] ?? null,
            ];
        }
        return $result;
    }


    /**
     * This function reset all visits on every available modules
     * @param $object
     * @param $user_id
     * @return bool
     */
    public static function setObjectVisits($object, $user_id, $timestamp = null)
    {
        // load plugins, so they have a chance to register themselves as observers
        PluginEngine::getPlugins(StandardPlugin::class);

        // Update news and votes
        $query = "INSERT INTO object_user_visits
                    (object_id, user_id, plugin_id, visitdate, last_visitdate)
                  (
                    SELECT questionnaire_id, :user_id, '-1', :timestamp, 0
                    FROM questionnaire_assignments
                    WHERE range_id = :id
                  ) UNION (
                    SELECT `news_id`, :user_id, `pluginid`, :timestamp, 0
                    FROM `news_range`
                    JOIN `plugins` ON (`pluginclassname` = 'CoreOverview')
                    WHERE `range_id` = :id
                  )
                  ON DUPLICATE KEY UPDATE last_visitdate = IFNULL(visitdate, 0), visitdate = :timestamp";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':user_id', $user_id);
        $statement->bindValue(':timestamp', $timestamp ?: time());
        $statement->bindValue('id', $object->id);
        $statement->execute();

        // Update all activated modules
        foreach ($object->tools as $module) {
            object_set_visit($object->id, $module->plugin_id);
        }

        // Update object itself
        object_set_visit($object->id, 0);

        return true;
    }

    /**
     * Returns a list of all the courses the user is in the waiting list for.
     *
     * @param string $user_id Id of the user
     * @return array
     */
    public static function getWaitingList($user_id)
    {
        $sql = "SELECT set_id, priorities.seminar_id,'claiming' as status, seminare.Name, seminare.Ort,
                priorities.priority, coursesets.name AS cname, seminare.admission_binding
            FROM priorities
            INNER JOIN seminare USING(seminar_id)
            INNER JOIN coursesets USING (set_id)
            WHERE priorities.user_id = ?
            ORDER BY coursesets.name, priorities.priority";
        $claiming = DBManager::get()->fetchAll($sql, [$user_id]);
        $csets    = [];
        foreach ($claiming as $k => $claim) {
            if (!isset($csets[$claim['set_id']])) {
                $csets[$claim['set_id']] = new CourseSet($claim['set_id']);
            }
            $cs = $csets[$claim['set_id']];
            if (!$cs->hasAlgorithmRun()) {
                $claiming[$k]['admission_endtime'] = $cs->getSeatDistributionTime();
            } else {
                unset($claiming[$k]);
            }
        }

        $stmt = DBManager::get()->prepare(
            "SELECT admission_seminar_user.*, seminare.status as sem_status, " .
            "seminare.Name, seminare.Ort, seminare.admission_binding " .
            "FROM admission_seminar_user " .
            "INNER JOIN seminare USING(seminar_id) " .
            "WHERE user_id = ? " .
            "ORDER BY admission_seminar_user.status, name");
        $stmt->execute([$user_id]);

        return array_merge($claiming, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }


    /**
     * Get all user assigned institutes based on simple or map
     * @return array
     */
    public static function getMyInstitutes(): array
    {
        $memberShips = InstituteMember::findByUser($GLOBALS['user']->id);

        if (empty($memberShips)) {
            return [];
        }
        $institutes = [];
        $insts = new SimpleCollection($memberShips);
        foreach ($insts as $inst) {
            $index = $inst->institute->id;
            $visits = get_objects_visits([$index], 0, null, null, $inst->institute->tools->pluck('plugin_id'));
            $institutes[$index] = $inst->institute->toArray();
            $institutes[$index]['tools'] = $inst->institute->tools;
            $institutes[$index]['perms'] = $inst->inst_perms;
            $institutes[$index]['visitdate'] = $visits[$index][0]['visitdate'];
            $institutes[$index]['last_visitdate'] = $visits[$index][0]['last_visitdate'];
            $institutes[$index]['obj_type']   = 'inst';
            $institutes[$index]['navigation'] = self::getAdditionalNavigations(
                $index,
                $institutes[$index],
                SemClass::getDefaultInstituteClass($inst->institute->type),
                $GLOBALS['user']->id,
                $visits[$index]
            );
        }


        return $institutes;
    }

    /**
     * Groups the list of courses by sem tree criteria.
     *
     * @param array $sem_courses List of courses
     */
    public static function groupBySemTree(&$sem_courses)
    {
        $_tmp_courses = [];
        foreach ($sem_courses as $sem_key => $collection) {
            if (!isset($_tmp_courses[$sem_key])) {
                $_tmp_courses[$sem_key] = [];
            }

            //We have to store the sem_tree names separately
            //since we first need the sem_tree IDs as array keys.
            //This makes it more easy to get the ordering
            //of the sem_tree objects.
            $sem_tree_names = [];
            foreach ($collection as $course) {
                if (!empty($course['sem_tree'])) {
                    foreach ($course['sem_tree'] as $tree) {
                        $sem_tree_names[$tree['sem_tree_id']] = $tree['name'];

                        if (!isset($_tmp_courses[$sem_key][(string)$tree['sem_tree_id']])) {
                            $_tmp_courses[$sem_key][(string)$tree['sem_tree_id']] = [];
                        }
                        $_tmp_courses[$sem_key][(string)$tree['sem_tree_id']][$course['seminar_id']] = $course;
                    }
                } else {
                    if (!isset($_tmp_courses[$sem_key][''])) {
                        $_tmp_courses[$sem_key][''] = [];
                    }

                    $_tmp_courses[$sem_key][''][$course['seminar_id']] = $course;
                }
            }

            // Create sort order for assigned sem_tree entries.
            $entries = StudipStudyArea::findMany(array_keys($sem_tree_names));
            if (count($entries) > 0) {
                $order = [];
                foreach ($entries as $entry) {
                    $order[$entry->getId()] = $entry->getIndex();
                }
                $max = max(array_map('strlen', $order));

                // Now sort courses by sem_tree entry order.
                uksort($_tmp_courses[$sem_key], function ($a, $b) use ($order, $max) {
                    return str_pad($order[$a] ?? '', $max, '0') - str_pad($order[$b] ?? '', $max, '0');
                });
            }

            //At this point the $_tmp_courses array is sorted by the ordering
            //of the sem_tree.
            //Now we have to replace the sem_tree IDs in the second layer
            //of the $_tmp_courses array with the sem_tree names:
            foreach ($_tmp_courses[$sem_key] as $sem_tree_id => $courses) {
                foreach ($courses as $course) {
                    $index = (string)($sem_tree_names[$sem_tree_id] ?? '');
                    if (!isset($_tmp_courses[$sem_key][$index])) {
                        $_tmp_courses[$sem_key][$index] = [];
                    }
                    $_tmp_courses[$sem_key][$index][$course['seminar_id']] = $course;
                }
                if (isset($sem_tree_names[$sem_tree_id])) {
                    unset($_tmp_courses[$sem_key][$sem_tree_id]);
                }
            }
        }

        //After the $_tmp_courses array has been built we must sort the
        //third layer (course collection) by group (color),
        //by number (at your option) and by name:
        foreach ($_tmp_courses as $sem_key => $sem_tree) {
            foreach ($sem_tree as $sem_tree_name => $collection) {
                //We must sort all courses by their group and their name:
                uasort($collection, function ($a, $b) {
                    if (Config::get()->IMPORTANT_SEMNUMBER) {
                        return strnatcasecmp($a['gruppe'], $b['gruppe'])
                            ?: strnatcasecmp($a['number'], $b['number'])
                            ?: strnatcasecmp($a['temp_name'], $b['temp_name']);
                    } else {
                        return strnatcasecmp($a['gruppe'], $b['gruppe'])
                            ?: strnatcasecmp($a['temp_name'], $b['temp_name']);
                    }
                });
                $_tmp_courses[$sem_key][$sem_tree_name] = $collection;
            }
        }

        $sem_courses = $_tmp_courses;
    }

    /**
     * Groups the list of courses by defined group.
     *
     * @param array $sem_courses
     */
    public static function groupByGruppe(&$sem_courses)
    {
        $_tmp_courses = [];
        foreach ($sem_courses as $sem_key => $collection) {
            foreach ($collection as $course) {
                $_tmp_courses[$sem_key][$course['gruppe']][$course['seminar_id']] = $course;
                ksort($_tmp_courses[$sem_key]);
            }
        }
        $sem_courses = $_tmp_courses;
    }

    /**
     * Groups the list of courses by the corresponding course's status.
     *
     * @param array $sem_courses
     */
    public static function groupBySemStatus(&$sem_courses)
    {
        $_tmp_courses = [];
        foreach ($sem_courses as $sem_key => $collection) {
            foreach ($collection as $course) {

                $sem_status = $GLOBALS['SEM_TYPE'][$course['status']]["name"]
                    . " (" . $GLOBALS['SEM_CLASS'][$GLOBALS['SEM_TYPE'][$course['status']]["class"]]["name"] . ")";

                $_tmp_courses[$sem_key][$sem_status][$course['seminar_id']] = $course;
            }
            // reorder array
            uksort($_tmp_courses[$sem_key], function ($a, $b) {
                if (ucfirst($a) == ucfirst($b)) return 0;
                return ucfirst($a) < ucfirst($b) ? -1 : 1;
            });
        }
        $sem_courses = $_tmp_courses;
    }

    /**
     * Groups the list of courses by the teacher(s) of the course.
     *
     * @param array $sem_courses
     */
    public static function groupByTeacher(&$sem_courses)
    {
        $_tmp_courses = [];
        foreach ($sem_courses as $sem_key => $collection) {
            foreach ($collection as $course) {
                if (!empty($course['teachers'])) {
                    foreach ($course['teachers'] as $fullname) {
                        $_tmp_courses[$sem_key][$fullname][$course['seminar_id']] = $course;
                    }
                } else {
                    $_tmp_courses[$sem_key][""][$course['seminar_id']] = $course;
                }
                ksort($_tmp_courses[$sem_key]);
            }
        }

        $sem_courses = $_tmp_courses;
    }

    /**
     * Groups the list of courses by associated mvv module of the course..
     *
     * @param array $sem_courses
     */
    public static function groupByMVVModule(array &$sem_courses): void
    {
        $_tmp_courses = [];
        foreach ($sem_courses as $sem_key => $collection) {
            $_tmp_courses[$sem_key] = [];
            foreach ($collection as $course) {
                $modules = Course::getMVVModulesForCourseId($course['seminar_id'], ['genehmigt']);
                if ($modules) {
                    $modules = array_map(function (Modul $module) {
                        return $module->getDisplayName();
                    }, $modules);
                } else {
                    $modules = [_('Keinem Modul zugeordnet')];
                }

                foreach ($modules as $module) {
                    if (!isset($_tmp_courses[$sem_key][$module])) {
                        $_tmp_courses[$sem_key][$module] = [];
                    }
                    $_tmp_courses[$sem_key][$module][$course['seminar_id']] = $course;
                }
            }
            ksort($_tmp_courses[$sem_key]);
        }
        $sem_courses = $_tmp_courses;
    }


    /**
     * Calc nav elements to get the table-column-width
     * @param $my_obj
     * @param string $group_field
     * @return int
     */
    public static function calc_nav_elements($my_obj, $group_field = 'sem_number')
    {
        $nav_elements = 0;
        if (empty($my_obj)) {
            return $nav_elements;
        }

        foreach ($my_obj as $courses) {
            if(!empty($courses)) {
                if ($group_field !== 'sem_number') {
                    // tlx: If array is 2-dimensional, merge it into a 1-dimensional
                    $courses = call_user_func_array('array_merge', $courses);
                }

                foreach ($courses as $course) {
                    $nav_elements = max($nav_elements, count(self::array_rtrim($course['navigation'])));
                }
            }
        }

        return $nav_elements;
    }

    /**
     * Calculates and returns the maximum length of the given course's navigations.
     *
     * @param array $collection
     * @return int
     */
    public static function calc_single_navigation($collection)
    {
        $nav_elements = 0;
        if (!empty($collection)) {
            foreach ($collection as $course) {
                $nav_elements = max($nav_elements, count(self::array_rtrim($course['navigation'])));
            }
        }
        return $nav_elements;
    }

    /**
     * Trims an array from it's null value from the right.
     *
     * @param array $array The array to trim
     * @return array The trimmed array
     * @author tlx
     */
    public static function array_rtrim($array)
    {
        $temp  = array_reverse($array, true);
        $empty = true;

        while ($empty && !empty($temp)) {
            $item = reset($temp);
            if ($empty = ($item === null)) {
                $temp = array_slice($temp, 1, null, true);
            }
        }
        return array_reverse($temp, true);
    }

    /**
     * Sorts the list of courses by given order and parent/child relation.
     *
     * @param array $courses List of courses
     * @param string $order Order to sort by
     * @return SimpleCollection
     */
    private static function sortCourses($courses, $order)
    {
        $sorted = $courses->orderBy($order);

        // First get all courses that can act as parent and have child courses.
        $parents = $courses->filter(function ($c) {
            return $c->getSemClass()->isGroup()
                && count($c->children) > 0;
        });

        // Sort children directly after parents. Only necessary if parents exist.
        if (count($parents) > 0) {
            $withChildren = new SimpleCollection();

            foreach ($sorted as $c) {
                if ($c->parent_course === null) {
                    $withChildren->append($c);
                    if (count($c->children) > 0) {
                        foreach ($sorted->findBy('parent_course', $c->id) as $child) {
                            $withChildren->append($child);
                        }
                    }
                }
            }

            $sorted = $withChildren;
        }

        return $sorted;
    }

    public static function getDefaultModules($range_type = 'course')
    {
        $default_modules = [];
        if ($range_type === 'course') {
            $sem_class = array_values(SemClass::getClasses())[0] ?? SemClass::getDefaultSemClass();
        } else {
            $sem_class = SemClass::getDefaultInstituteClass(1);
        }
        foreach ($sem_class->getActivatedModuleObjects() as $id => $plugin) {
            if (get_class($plugin) === 'CoreAdmin' || get_class($plugin) === 'CoreStudygroupAdmin') {
                continue;
            }
            $default_modules[$id] = $plugin;
        }
        if (Config::get()->VOTE_ENABLE) {
            $default_modules[-1] = 'vote';
        }
        return $default_modules;
    }
}
