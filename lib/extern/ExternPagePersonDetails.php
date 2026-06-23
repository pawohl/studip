<?php
/**
 * ExternPagePersonDetails.php - Class to provide detailed data of an
 * institute member.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Peter Thienel <thienel@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       5.4
 */

class ExternPagePersonDetails extends ExternPage
{

    public $institute;

    public function __construct($config)
    {
        parent::__construct($config);
        $this->institute = Institute::find($this->page_config->range_id);
    }

    /**
     * @return array
     */
    public function getSortFields() : array
    {
        return [];
    }

    public function getDataFields($object_classes = []) : array
    {
        return parent::getDataFields(
            [
                'user',
                'userinstrole'
            ]
        );
    }

    /**
     * @param false $as_array
     * @return array|mixed|string
     */
    public function getConfigFields($as_array = false)
    {
        $args = '
            language      option,
            startsem      option,
            semcount      int,
            semswitch     int,
            semclass      optionArray,
            escaping      option,
            defaultaddr   int
        ';
        return $as_array ? self::argsToArray($args) : $args;
    }

    public function getAllowedRequestParams($as_array = false)
    {
        $params = [
            'language',
            'startsem',
            'semcount',
            'semswitch',
            'user_id',
            'user_name',
        ];
        return $as_array ? $params : implode(',', $params);
    }

    public function getMarkersContents(): array
    {
        return $this->getContent();
    }

    protected function getContent()
    {
        if ($this->user_name) {
            $user = User::findByUsername($this->user_name);
        } else {
            $user = User::find($this->user_id);
        }

        if (!$user) {
            return [];
        }

        $memberships = $this->getMemberships($user);
        if (count($memberships) === 0) {
            return [];
        }

        $content = $this->getContentUser($user);
        $content += $this->getContentInstituteMembers($memberships);
        $content += $this->getContentSemesterCourses($user);
        $content += $this->getContentHomepagePlugins($user);
        $content += $this->getContentNews($user);
        $content += $this->getContentAppointments($user);
        $content += $this->getContentOwnCategories($user);
        return $content;
    }

    /**
     * Retrieves all institute memberships of the current user dependent on
     * home institute of this page configuration.
     *
     * @param User $user The current user.
     * @return InstituteMember[] The memberships of the user.
     */
    private function getMemberships(User $user)
    {
        return InstituteMember::findBySQL(
            '`user_id` = ? AND `inst_perms` = ?',
            [
                $user->id,
                'dozent'
            ]
        );
    }

    /**
     * Retrieves all courses of given user filtered by config values and grouped by semester.
     *
     * @param User $user
     * @return array
     * @throws Exception
     */
    private function getUserCourses(User $user)
    {
        $grouped_courses = [];
        $semesters = $this->getSemesters();
        $query = "SELECT IFNULL(`semester_courses`.`semester_id`, '-1') AS `group_id`,
                         `seminare`.*
                  FROM `seminare`
                  LEFT JOIN `semester_courses`
                    ON `semester_courses`.`course_id` = `seminare`.`Seminar_id`
                  JOIN `seminar_user` USING(`seminar_id`)
                  JOIN `sem_types` ON `sem_types`.`id` = `seminare`.`status`
                  WHERE `seminare`.`visible` = 1
                    AND (`semester_courses`.`semester_id` IN (:semester_ids) OR ISNULL(`semester_id`))
                    AND `seminar_user`.`user_id` = :user_id
                    AND `seminar_user`.`status` = 'dozent'
                    AND `sem_types`.`class` IN (:semclasses)";

        $grouped_results = DBManager::get()->fetchGrouped($query, [
            'semester_ids' => $semesters,
            'semclasses'   => (array) $this->semclass,
            'user_id'      => $user->id
        ]);

        // handle unlimited courses
        if (isset($grouped_results['-1'])) {
            foreach ($semesters as $semester_id) {
                if (isset($grouped_results[$semester_id])) {
                    $grouped_results[$semester_id] = array_merge($grouped_results[$semester_id], $grouped_results['-1']);
                } else {
                    $grouped_results[$semester_id] = $grouped_results['-1'];
                }
            }
            unset($grouped_results['-1']);
        }
        foreach ($grouped_results as $group_id => $group_result) {
            $grouped_courses[$group_id] = Course::findMany($group_result);
        }
        return $grouped_courses;
    }

    protected function getAllChildren($group)
    {
        $all_groups[] = $group;
        foreach ($group->getChildren() as $child) {
            $all_groups = array_merge($all_groups, $this->getAllChildren($child));
        }
        return $all_groups;
    }

    protected function getContentUser(User $user)
    {
        if (Visibility::verify('picture', $user->id)) {
            $avatar = Avatar::getAvatar($user->id);
        } else {
            $avatar = Avatar::getNobody();
        }
        $content = [
            'FULLNAME'           => $user->getFullName(),
            'LASTNAME'           => $user->Nachname,
            'FIRSTNAME'          => $user->Vorname,
            'TITLEFRONT'         => $user->title_front,
            'TITLEREAR'          => $user->title_rear,
            'USERNAME'           => $user->username,
            'USERID'             => $user->id,
            'IMAGE_URL_SMALL'    => $avatar->getURL(Avatar::SMALL),
            'IMAGE_URL_MEDIUM'   => $avatar->getURL(Avatar::MEDIUM),
            'IMAGE_URL_NORMAL'   => $avatar->getURL(Avatar::NORMAL),
            'EMAIL'              => get_visible_email($user->id),
            'HOMEPAGE_URL'       => Visibility::verify('homepage', $user->id) ? $user->home : '',
            'CV'                 => Visibility::verify('lebenslauf', $user->id) ? $user->lebenslauf : '',
            'RESEARCH_INTERESTS' => Visibility::verify('schwerp', $user->id) ? $user->schwerp : '',
            'PUBLICATIONS'       => Visibility::verify('publi', $user->id) ? $user->publi : '',
       ];
        $content += $this->getDatafieldMarkers($user);
        return $content;
    }

    protected function getContentInstituteMembers($members)
    {
        $content = [];
        foreach ($members as $member) {
            if (!$member->visible) {
                continue;
            }
            $content[] = array_merge(
                [
                    'ID'                 => $member->institut_id,
                    'NAME'               => $member->institute->name,
                    'HOMEPAGE'           => $member->institute->url,
                    'STREET'             => $member->institute->strasse,
                    'ZIPCODE'            => $member->institute->plz,
                    'EMAIL'              => $member->institute->email,
                    'PHONE'              => $member->institute->telefon,
                    'FAX'                => $member->institute->fax,
                    'TYPE'               => $GLOBALS['INST_TYPE'][$member->institute->type]['name'],
                    'MEMBER_ROOM'        => $member->raum,
                    'MEMBER_OFFICEHOURS' => $member->sprechzeiten,
                    'MEMBER_PHONE'       => $member->telefon,
                    'MEMBER_FAX'         => $member->fax,
                    'MEMBER_ISSTANDARD'  => $member->externdefault,
                    'MEMBER_GROUPPATHS' => Statusgruppen::getUserRoles($member->institute->id, $member->user_id)
                ],
                $this->getDatafieldMarkers($member));
        }
        return ['INSTITUTES' => $content];
    }

    protected function getUserContent($member)
    {
        if (Visibility::verify('picture', $member->user_id)) {
            $avatar = Avatar::getAvatar($member->user_id);
        } else {
            $avatar = Avatar::getNobody();
        }
        $inst_member = $this->institute->members->findOneBy('user_id', $member->user_id);
        $content = [
            'FULLNAME'         => $member->user->getFullName(),
            'LASTNAME'         => $member->user->Nachname,
            'FIRSTNAME'        => $member->user->Vorname,
            'TITLEFRONT'       => $member->user->title_front,
            'TITLEREAR'        => $member->user->title_rear,
            'USERNAME'         => $member->user->username,
            'IMAGE_URL_SMALL'  => $avatar->getURL(Avatar::SMALL),
            'IMAGE_URL_MEDIUM' => $avatar->getURL(Avatar::MEDIUM),
            'IMAGE_URL_NORMAL' => $avatar->getURL(Avatar::NORMAL),
            'PHONE'            => $inst_member->telefon,
            'ROOM'             => $inst_member->raum,
            'EMAIL'            => get_visible_email($member->user_id),
            'HOMEPAGE_URL'     => Visibility::verify('homepage', $member->user_id) ? $member->user->home : '',
            'OFFICEHOURS'      => $inst_member->sprechzeiten,

        ];
        if (Visibility::verify('lebenslauf', $member->user_id)) {
            $content['CV'] = $member->user->lebenslauf;
        }
        if (Visibility::verify('schwerp', $member->user_id)) {
            $content['RESEARCH_INTERESTS'] = $member->user->schwerp;
        }
        if (Visibility::verify('publi', $member->user_id)) {
            $content['PUBLICATIONS'] = $member->user->publi;
        }
        $content = array_merge($content, $this->getContentSemesterCourses($member->user));
        $content = array_merge($content, $this->getContentHomepagePlugins($member->user));
        if (Visibility::verify('news', $member->user_id)) {
            $content['NEWS'] = $this->getContentNews($member->user);
        }
        if (Visibility::verify('dates', $member->user_id)) {
            $content['APPOINTMENTS'] = $this->getContentAppointments($member->user);
        }
        $content['OWNCATEGORIES'] = $this->getContentOwnCategories($member->user);
        return $content;
    }

    /**
     * Returns content array with courses grouped by semesters and markers as keys.
     *
     * @param User $user The current user object.
     * @return array[] Array with semester and course data.
     */
    private function getContentSemesterCourses(User $user)
    {
        $content =  [];
        $grouped_courses = $this->getUserCourses($user);
        foreach ($grouped_courses as $semester_id => $courses) {
            $content[] = array_merge([
                'COURSES' => $this->getContentCourses($courses)
            ], $this->getContentSemester($semester_id));
        }
        return ['SEMESTERS' => $content];
    }

    /**
     * Returns data of given semester as content array.
     *
     * @param string $semester_id The id of the semester.
     * @return array Array with semester data.
     */
    private function getContentSemester($semester_id)
    {
        $content = [];
        $semester = Semester::find($semester_id);
        if ($semester) {
            $content = [
                'NAME'        => $semester->name,
                'SHORTNAME'   => $semester->semester_token,
            ];
        }
        return $content;
    }

    private function getContentCourses($courses)
    {
        foreach ($courses as $course) {
            $content[] = [
                'TITLE'    => $course->name,
                'SUBTITLE' => $course->untertitel,
                'NUMBER'   => $course->VeranstaltungsNummer,
                'ID'       => $course->id,
            ];
        }
        return $content;
    }

    private function getContentNews(User $user)
    {
        $news = StudipNews::GetNewsByRange($user->id, true, true);
        $content = [];
        foreach ($news as $news_detail) {
            $content[] = [
                'BODY'  => $news_detail->body,
                'DATE'  => $news_detail->date,
                'TOPIC' => $news_detail->topic,
            ];
        }
        return ['NEWS' => $content];
    }

    private function getContentAppointments (User $user)
    {
        if (!Config::get()->CALENDAR_ENABLE) {
            return [];
        }

        $list_start = new DateTime();
        $list_end = clone $list_start;
        $list_end = $list_end->add(new DateInterval('P7D'));

        $assigned_events = CalendarDateAssignment::getEvents(
            $list_start,
            $list_end,
            $user->id,
            ['PUBLIC']
        );

        $content['APPOINTMENTS_START'] = $list_start->getTimestamp();
        $content['APPOINTMENTS_END']   = $list_end->getTimestamp();
        $content_events = [];
        if (!empty($assigned_events)) {
            foreach ($assigned_events as $assigned_event) {
                $event = $assigned_event->calendar_date;
                if (!$event) {
                    continue;
                }
                if ($event->isWholeDay()) {
                    $date = date('d.m.Y', $event->begin) . ' (' . _('ganztägig') . ')';
                } else {
                    $date = date('d.m.Y G:i:s', $event->begin);
                    if (date('dmY', $event->begin) === date('dmY', $event->end)) {
                        $date .= date('d.m.Y G:i:s', $event->end);
                    } else {
                        $date .= ' - ' . date('d.m.Y G:i:s', $event->end);
                    }
                }
                $content_events[] = [
                    'DATE'            => $date,
                    'TITLE'           => $event->title,
                    'DESCRIPTION'     => $event->description,
                    'LOCATION'        => $event->location,
                    'RECURRENCE'      => $event->getRepetitionAsString(),
                    'CATEGORY'        => $event->getCategoryAsString(),
                    'PRIORITY'        => _('Keine Angabe'),
                    'START'           => date('d.m.Y G:i:s', $event->begin),
                    'END'             => date('d.m.Y G:i:s', $event->end),
                    'TIMESTAMP_START' => $event->begin,
                    'TIMESTAMP_END'   => $event->end,
                ];
            }
        }
        return array_merge($content, [
            'APPOINTMENTS' => $content_events
        ]);
    }

    /**
     * Retrieves all visible homepage categories of the current user.
     *
     * @param User $user The current user.
     * @return array[] The content with categories.
     */
    private function getContentOwnCategories(User $user)
    {
        $content = [];
        $categories = Kategorie::findBySQL('`range_id` = ? ORDER BY `priority`', [$user->id]);
        foreach ($categories as $category) {
            if (Visibility::verify('kat_' . $category->id, $user->id)) {
                $content[] = [
                    'TITLE' => $category->name,
                    'CONTENT' => $category->content
                ];
            }
        }
        return ['OWNCATEGORIES' => $content];
    }

    /**
     * Returns content from Homepage Plugins.
     *
     * @param User $user
     */
    private function getContentHomepagePlugins(User $user)
    {
        $content = [];
        $plugins = PluginEngine::getPlugins(HomepagePlugin::class);
        foreach ($plugins as $plugin) {
            $template = $plugin->getHomepageTemplate($user->id);
            if ($template) {
                $key_name = 'PLUGIN-' . mb_strtoupper($plugin->getPluginName());
                $content[$key_name] = $template->render();
            }
        }
        return $content;
    }

    public function getFullGroupNames()
    {
        $groups = [];
        foreach ($this->institute->status_groups as $status_group) {
            $groups[$status_group->id] = $status_group->name;
            $groups = array_merge($groups, $this->getGroupPaths($status_group));
        }
        return $groups;
    }

    public function getGroupPaths($group, $seperator = ' > ', $pre = '')
    {
        $name = $pre
            ? $pre . $seperator . $group->getName()
            : $group->getName();
        $result[$group->id] = $name;
        if ($group->children) {
            foreach ($group->children as $child) {
                $result = array_merge($result, $this->getGroupPaths($child, $seperator, $name));
            }
        }
        return $result;
    }
}
