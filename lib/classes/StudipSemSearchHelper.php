<?php
# Lifter002: TODO
# Lifter007: TODO
# Lifter003: TODO
# Lifter010: TODO
// +---------------------------------------------------------------------------+
// This file is part of Stud.IP
// StudipSemSearchHelper.php
//
//
// Copyright (c) 2003 André Noack <noack@data-quest.de>
// +---------------------------------------------------------------------------+
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or any later version.
// +---------------------------------------------------------------------------+
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
// +---------------------------------------------------------------------------+

class StudipSemSearchHelper {

    public static function GetQuickSearchFields(){
        return [   'all' =>_("alles"),
                        'title_lecturer_number' => _("Titel") . ', ' . _("Lehrende") . ', ' . _("Nummer"),
                        'title' => _("Titel"),
                        'sub_title' => _("Untertitel"),
                        'lecturer' => _("Lehrende"),
                        'number' => _("Nummer"),
                        'comment' => _("Kommentar"),
                        'scope' => _("Bereich")];
    }

    private $found_rows = false;
    private $params = [];
    private $visible_only;

    function __construct($form = null, $visible_only = null){
        $params = [];
        if($form instanceof StudipForm){
            foreach($form->getFormFieldsByName(true) as $name){
                $params[$name] = $form->getFormFieldValue($name);
            }
        }
        $this->setParams($params, $visible_only);
    }

    public function setParams($params, $visible_only = null)
    {
        if (isset($params['quick_search'], $params['qs_choose'])) {
            if ($params['qs_choose'] === 'all'){
                foreach (self::GetQuickSearchFields() as $key => $value) {
                    $params[$key] = $this->trim($params['quick_search']);
                }
                $params['combination'] = 'OR';
            } elseif (
                $params['qs_choose'] === 'title_lecturer_number'
                || $params['qs_choose'] === 'title_number'
            ) {
                foreach (explode('_', $params['qs_choose']) as $key) {
                    $params[$key] = $this->trim($params['quick_search']);
                }
                $params['combination'] = 'OR';
            } else {
                $params[$params['qs_choose']] = $this->trim($params['quick_search']);
            }
        }

        if (!isset($params['combination'])) {
            $params['combination'] = 'AND';
        }

        $this->params = $params;
        $this->visible_only = $visible_only;
    }

    public function doSearch()
    {
        if (count($this->params) === 0) {
            return false;
        }

        $db = DBManager::get();
        $join_sql   = [];
        $where_sql  = [];
        $sql_params = [];

        if (isset($this->params['sem']) && $this->params['sem'] !== 'all') {
            $all_semesters = Semester::getAll();
            if (array_key_exists($this->params['sem'], $all_semesters)) {
                $semester = $all_semesters[$this->params['sem']];
                //Use that semester for filtering courses:
                $join_sql[]  = "LEFT JOIN `semester_courses` ON `seminare`.`seminar_id` = `semester_courses`.`course_id`";
                $where_sql[] = "(`semester_courses`.`semester_id` IS NULL OR `semester_courses`.`semester_id` = :semester_id)";
                $sql_params['semester_id'] = $semester->id;
            } else {
                //Nothing can be found when the semester is unknown:
                return [];
            }
        }

        $sem_types = [];
        if (isset($this->params['category']) && $this->params['category'] !== 'all') {
            foreach ($GLOBALS['SEM_TYPE'] as $type_key => $type_value) {
                if ($type_value['class'] == $this->params['category']) {
                    $sem_types[] = $type_key;
                }
            }
        }

        if (isset($this->params['type']) && $this->params['type'] !== 'all') {
            $sem_types = [$this->params['type']];
        }
        if ($sem_types) {
            $where_sql[] = "`seminare`.`status` IN ( :course_types )";
            $sql_params['course_types'] = $sem_types;
        }

        if ($this->visible_only) {
            //Visible courses only:
            $where_sql[] = "`seminare`.`visible` = 1";
        }

        if (!empty($this->params['scope_choose']) && $this->params['scope_choose'] !== 'root') {
            //Filter by study areas:
            $study_area_ids = [];
            $study_area = StudipStudyArea::find($this->params['scope_choose']);
            if ($study_area) {
                $children = $study_area->getChildren();
                foreach ($children as $child) {
                    $study_area_ids[] = $child->id;
                    $grand_children = $child->getChildren();
                    foreach ($grand_children as $grand_child) {
                        $study_area_ids[] = $grand_child->id;
                    }
                }
            }

            if (!empty($study_area_ids)) {
                $join_sql[]  = "JOIN `seminar_sem_tree` USING (`seminar_id`)";
                $where_sql[] = "`seminar_sem_tree`.`sem_tree_id` IN ( :study_area_ids )";
                $sql_params['study_area_ids'] = $study_area_ids;
            }
        }

        if (!empty($this->params['range_choose']) && $this->params['range_choose'] !== 'root') {
            //Filter by institutes:
            $institute = Institute::find($this->params['range_choose']);
            $institute_ids = [];
            if ($institute) {
                $institute_ids[] = $institute->id;
                if ($institute->isFaculty()) {
                    $institute_ids[] = array_merge(
                        $institute_ids,
                        $institute->sub_institutes->pluck('id')
                    );
                }
            }
            if (empty($institute_ids)) {
                //We cannot search for courses if the institutes they shall belong to cannot be found:
                return [];
            }
            $join_sql[]  = "JOIN `seminar_inst` USING (`seminar_id`)";
            $where_sql[] = "`seminar_inst`.`institut_id` IN (:institute_ids)";
            $sql_params['institute_ids'] = $institute_ids;
        }

        $this->getSearchCondition($where_sql, $join_sql, $sql_params);

        $query = sprintf(
            'SELECT DISTINCT `seminar_id` FROM `seminare` %s WHERE %s',
            implode(' ', $join_sql),
            implode(' AND ', $where_sql)
        );

        $stmt = $db->prepare($query);
        $stmt->execute($sql_params);
        return $stmt->fetchAll();
    }


    private function trim($what)
    {
        $what = trim($what);
        $what = preg_replace("/^\x{00A0}+|\x{00A0}+$/Su", '', $what);
        return $what;
    }

    private function hasSearchParam(string $key): bool
    {
        return isset($this->params[$key])
            && mb_strlen($this->params[$key]) > 2;
    }

    private function getSearchCondition(
        array &$where_sql,
        array &$join_sql,
        array &$sql_params
    ): void {
        $conditions = [];

        if ($this->hasSearchParam('lecturer')) {
            //Search for lecturers:
            $join_sql[] = "JOIN `seminar_user` USING (`seminar_id`)";
            $join_sql[] = "JOIN `auth_user_md5` USING (`user_id`)";

            $conditions[] = "((
                CONCAT(`auth_user_md5`.`Nachname`, ', ', `auth_user_md5`.`Vorname`, ' ', `auth_user_md5`.`Nachname`) LIKE CONCAT('%', :lecturer_name, '%')
                OR `auth_user_md5`.`username` LIKE CONCAT('%', :lecturer_name, '%')
                ) AND `seminar_user`.`status` = 'dozent')";
            $sql_params['lecturer_name'] = $this->params['lecturer'];
        }

        if ($this->hasSearchParam('number')) {
            $conditions[] = "`seminare`.`VeranstaltungsNummer` LIKE CONCAT('%', :number, '%')";
            $sql_params['number'] = $this->params['number'];
        }

        if ($this->hasSearchParam('title')) {
            $terms = explode(' ', $this->params['title']);
            $terms = array_map('trim', $terms);
            $terms = array_filter($terms);
            $terms = array_unique($terms);

            $chunks = [];
            foreach ($terms as $index => $term) {
                $chunks[] = "`seminare`.`Name` LIKE CONCAT('%', :title{$index}, '%')";
                $sql_params['title' . $index] = $term;
            }

            $conditions[] = '(' . implode(' AND ', $chunks) . ')';
        }

        if ($this->hasSearchParam('sub_title')) {
            $conditions[] = "`seminare`.`Untertitel` LIKE CONCAT('%', :sub_title, '%')";
            $sql_params['sub_title'] = $this->params['sub_title'];
        }

        if ($this->hasSearchParam('comment')) {
            $conditions[] = "`seminare`.`Beschreibung` LIKE CONCAT('%', :description, '%')";
            $sql_params['description'] = $this->params['comment'];
        }

        if (count($conditions) > 0) {
            $where_sql[] = '(' . implode(' ' . $this->params['combination'] . ' ', $conditions) . ')';
        }
    }
}
