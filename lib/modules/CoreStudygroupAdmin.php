<?php

/*
 *  Copyright (c) 2012  Rasmus Fuhse <fuhse@data-quest.de>
 *
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation; either version 2 of
 *  the License, or (at your option) any later version.
 */

class CoreStudygroupAdmin extends CorePlugin implements StudipModuleExtended
{
    use IconNavigationTrait;

    public function getManyIconNavigation(array $course_ids, ?string $user_id = null): array
    {
        $navs = [];
        foreach ($course_ids as $course_id) {
            $navigation = new Navigation(_('Verwaltung'), "dispatch.php/course/studygroup/edit/?cid={$course_id}");
            $navigation->setImage(Icon::create('admin'));
            $navigation->setLinkAttributes(['title' => _('Verwaltung')]);
            $navs[$course_id] = $navigation;
        }
        return $navs;
    }

    /**
     * {@inheritdoc}
     */
    public function getTabNavigation($course_id)
    {
        if (!$GLOBALS['perm']->have_studip_perm('dozent', $course_id)) {
            return [];
        }

        $navigation = new Navigation(_('Verwaltung'));
        $navigation->setImage(Icon::create('admin', Icon::ROLE_INFO_ALT));
        $navigation->setActiveImage(Icon::create('admin', Icon::ROLE_INFO));

        $navigation->addSubNavigation('contentmodules', new Navigation(_('Werkzeuge'), "dispatch.php/course/contentmodules?cid={$course_id}"));
        $navigation->addSubNavigation('main', new Navigation(_('Verwaltung'), "dispatch.php/course/studygroup/edit/?cid={$course_id}"));
        $navigation->addSubNavigation('avatar', new Navigation(_('Studiengruppenbild'), "dispatch.php/course/studygroup/avatar?cid={$course_id}"));
        $navigation->addSubNavigation('connectedcourses', new Navigation(_('Verknüpfte Veranstaltungen'), "dispatch.php/course/connectedcourses?cid={$course_id}"));

        if (!$GLOBALS['perm']->have_perm('admin') && Config::get()->VOTE_ENABLE) {
            $item = new Navigation(_('Fragebögen'), 'dispatch.php/questionnaire/courseoverview');
            $item->setDescription(_('Erstellen und bearbeiten von Fragebögen.'));
            $navigation->addSubNavigation('questionnaires', $item);
        }
        return ['admin' => $navigation];
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        return [
            'displayname' => _('Verwaltung'),
            'summary' => _('Verwaltung der Grunddaten, Zugangsberechtigungen und sonstigen Einstellungen'),
            'icon' => Icon::create('admin', Icon::ROLE_INFO),
            'icon_clickable' => Icon::create('admin')
        ];
    }

    public function getInfoTemplate($course_id)
    {
        // TODO: Implement getInfoTemplate() method.
        return null;
    }
}
