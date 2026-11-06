<?php

use Courseware\StructuralElement;
use Courseware\Block;
use Courseware\Container;
use Courseware\Unit;

/**
 * Global search module for files
 *
 * @author      Ron Lucke <lucke@elan-ev.de>
 * @category    Stud.IP
 * @since       5.2
 */
class GlobalSearchCourseware extends GlobalSearchModule implements GlobalSearchFulltext
{
    /**
     * Returns the displayname for this module
     *
     * @return string
     */
    public static function getName()
    {
        return _('Courseware');
    }

    /**
     * Returns the filters that are displayed in the sidebar of the global search.
     *
     * @return array Filters for this class.
     */
    public static function getFilters()
    {
        return [];
    }

    public static function getSQL($search, $filter, $limit)
    {
        if (!$search) {
            return null;
        }
        $payload_search = addcslashes(substr(json_encode($search), 1, -1), '\\_%');

        $query = DBManager::get()->quote("%{$search}%");
        $payload_query = DBManager::get()->quote("%{$payload_search}%");
        if (!empty($filter['rangeId'])) {
            $range_id = DBManager::get()->quote($filter['rangeId']);
            $sql = "(SELECT `cw_structural_elements` . `id` AS id, CONCAT('', 'cw_structural_elements') AS type
            FROM `cw_structural_elements`
            WHERE (`title` LIKE {$query} OR `payload` LIKE {$payload_query})
                AND `range_id` = {$range_id}
            ORDER BY `cw_structural_elements`.`mkdate` DESC)
            UNION (
                SELECT se . `id` AS id, CONCAT('', 'cw_containers') AS type
                FROM `cw_containers` c
                JOIN cw_structural_elements se
                ON se . `id` = c . `structural_element_id`
                WHERE c. `payload` LIKE {$payload_query}
                    AND `container_type` != 'list'
                    AND se . `range_id` = {$range_id}
                ORDER BY c . `mkdate` DESC)
            UNION (
                SELECT se . `id` AS id, CONCAT('', 'cw_blocks') AS type
                FROM `cw_blocks` b
                JOIN cw_containers c
                ON c.id = b.container_id
                JOIN cw_structural_elements se
                ON se . `id` = c . `structural_element_id`
                WHERE b.payload LIKE {$payload_query}
                    AND se . `range_id` = {$range_id}
                ORDER BY b . `mkdate` DESC
            ) LIMIT {$limit}";
        } else {
            $user_id = DBManager::get()->quote($GLOBALS['user']->id);
            $mycourses = "SELECT `Seminar_id`
            FROM `seminar_user`
            WHERE `user_id` = {$user_id}";

            if (Config::get()->DEPUTIES_ENABLE) {
            $mycourses .= "
                UNION
                SELECT `range_id` AS Seminar_id
                FROM `deputies`
                WHERE `user_id` = {$user_id}";
            }

            $sql = "(SELECT `cw_structural_elements` . `id` AS id, CONCAT('', 'cw_structural_elements') AS type
            FROM `cw_structural_elements`
            WHERE (`title` LIKE {$query} OR `payload` LIKE {$payload_query})
                AND (`range_id` IN ({$mycourses}) OR `range_id` = {$user_id})
            ORDER BY `cw_structural_elements`.`mkdate` DESC)
            UNION (
                SELECT se . `id` AS id, CONCAT('', 'cw_containers') AS type
                FROM `cw_containers` c
                JOIN cw_structural_elements se
                ON se . `id` = c . `structural_element_id`
                WHERE c. `payload` LIKE {$payload_query}
                    AND `container_type` != 'list'
                    AND (se . `range_id` IN ({$mycourses}) OR se .`range_id` = {$user_id})
                ORDER BY c . `mkdate` DESC)
            UNION (
                SELECT se . `id` AS id, CONCAT('', 'cw_blocks') AS type
                FROM `cw_blocks` b
                JOIN cw_containers c
                ON c.id = b.container_id
                JOIN cw_structural_elements se
                ON se . `id` = c . `structural_element_id`
                WHERE b.payload LIKE {$payload_query}
                    AND (se . `range_id` IN ({$mycourses}) OR se .`range_id` = {$user_id})
                ORDER BY b . `mkdate` DESC
            ) LIMIT {$limit}";
        }

        return $sql;
    }

    public static function filter($data, $search)
    {
        $structural_element = StructuralElement::find($data['id']);
        $unit = $structural_element->findUnit();
        if ($unit && $structural_element->canRead(User::findCurrent())) {
            $description = '';
            if ($data['type'] === 'cw_structural_elements') {
                $description = self::mark($structural_element->payload['description'], $search, true);
            }
            if ($data['type'] === 'cw_containers') {
                $description = _('Suchbegriff wurde in einem Abschnitt gefunden');
            }
            if ($data['type'] === 'cw_blocks') {
                $description = _('Suchbegriff wurde in einem Block gefunden');
            }
            $pageData = self::getPageData($structural_element, $unit);
            $date = new DateTime();
            $date->setTimestamp($structural_element->chdate);

            $name = $unit->structural_element->id === $structural_element->id
                  ? $structural_element->title
                  : $unit->structural_element->title . ': ' . $structural_element->title;

            return [
                'name' => self::mark($name, $search, true),
                'description' => $description,
                'url' => $pageData['url'],
                'img' => $structural_element->image ? $structural_element->getImageUrl() : Icon::create('courseware')->asImagePath(),
                'additional' => htmlReady($pageData['originName']),
                'date' => $date->format('d.m.Y H:i'),
                'structural-element-id' => $structural_element->id,
                'unit-id' => $unit->id,
                'expand' => null
            ];
        }
        return [];
    }

    private static function getPageData(StructuralElement $structural_element, Unit $unit): Array
    {
        $url = '';
        $originUrl = '';
        $originName = '';
        if ($structural_element->range_type === 'course') {
            $url = URLHelper::getURL(
                "dispatch.php/course/courseware/courseware/{$unit->id}?cid={$structural_element->range_id}#/structural_element/{$structural_element->id}",
                [],
                true);
            $originUrl = URLHelper::getURL(
                "dispatch.php/course/overview?cid={$structural_element->range_id}",
                [],
                true);
            $originName = Course::find($structural_element->range_id)->name;
        }
        if ($structural_element->range_type === 'user') {
            $url = URLHelper::getURL(
                "dispatch.php/contents/courseware/courseware/{$unit->id}#/structural_element/{$structural_element->id}",
                [],
                true);
            $originUrl = URLHelper::getURL(
                "dispatch.php/contents/courseware/index",
                [],
                true);
            $originName = _('Persönliche Lernmaterialien');
        }


        return array(
            'url' => $url,
            'originUrl' => $originUrl,
            'originName' => $originName
        );
    }

    public static function enable()
    {
    }

    public static function disable()
    {
    }

    public static function getSearchURL($searchterm)
    {
        return URLHelper::getURL('dispatch.php/search/globalsearch', [
            'q'        => $searchterm,
            'category' => self::class
        ]);
    }

}
