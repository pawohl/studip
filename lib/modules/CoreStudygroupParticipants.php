<?php

/*
 *  Copyright (c) 2012  Rasmus Fuhse <fuhse@data-quest.de>
 *
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation; either version 2 of
 *  the License, or (at your option) any later version.
 */

class CoreStudygroupParticipants extends CorePlugin implements StudipModuleExtended
{
    use IconNavigationTrait;

    public function getManyIconNavigation(array $course_ids, ?string $user_id = null): array
    {
        $results = DBManager::get()->fetchAll(
            "SELECT seminar_user.Seminar_id, COUNT(seminar_user.user_id) as neue
                  FROM seminar_user
                  LEFT JOIN object_user_visits AS ouv
                    ON ouv.object_id = seminar_user.Seminar_id
                       AND ouv.user_id = :user_id
                       AND ouv.plugin_id = :plugin_id
                  WHERE seminar_user.Seminar_id IN (:course_ids)
                    AND seminar_user.mkdate > IFNULL(ouv.visitdate, :threshold)
                  GROUP BY seminar_user.Seminar_id",
            [
                ':course_ids' => $course_ids,
                ':user_id' => $user_id,
                ':plugin_id' => $this->getPluginId(),
                'threshold' => object_get_visit_threshold()
            ],
        );
        $navs = [];
        foreach ($course_ids as $course_id) {
            $navigation = new Navigation(_('Teilnehmende'), "dispatch.php/course/studygroup/members/{$course_id}");
            $navigation->setImage(Icon::create('persons'));
            if (isset($results[$course_id]) && !empty($results[$course_id]['neue'])) {
                $navigation->setImage(Icon::create('persons', Icon::ROLE_ATTENTION));
            }
            $navs[$course_id] = $navigation;
        }
        return $navs;
    }

    /**
     * {@inheritdoc}
     */
    public function getTabNavigation($course_id)
    {
        $navigation = new Navigation(_('Teilnehmende'), "dispatch.php/course/studygroup/members/".$course_id);
        $navigation->setImage(Icon::create('persons', Icon::ROLE_INFO_ALT));
        $navigation->setActiveImage(Icon::create('persons', Icon::ROLE_INFO));
        return ['members' => $navigation];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        return [
            'summary' => _('Liste aller Teilnehmenden einschließlich Nachrichtenfunktionen'),
            'category' => _('Lehr- und Lernorganisation'),
            'icon' => Icon::create('persons', Icon::ROLE_INFO),
            'displayname' => _('Teilnehmende'),
        ];
    }

    public function getInfoTemplate($course_id)
    {
        // TODO: Implement getInfoTemplate() method.
        return null;
    }
}
