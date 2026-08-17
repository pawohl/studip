<?php

/*
 *  Copyright (c) 2012  Rasmus Fuhse <fuhse@data-quest.de>
 *
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation; either version 2 of
 *  the License, or (at your option) any later version.
 */

class CoreParticipants extends CorePlugin implements StudipModuleExtended
{
    use IconNavigationTrait;

    public function getManyIconNavigation(array $course_ids, ?string $user_id = null): array
    {
        $navs = array_fill_keys($course_ids, null);
        if ($user_id === 'nobody') {
            return $navs;
        }

        // Filter courses that are auto-insert-seminars, to not show any icon
        $course_ids = array_filter($course_ids, function ($course_id) use ($user_id) {
            $auto_insert_perm = Config::get()->AUTO_INSERT_SEM_PARTICIPANTS_VIEW_PERM;
            $is_auto_insert =
                AutoInsert::checkSeminar($course_id)
                && (
                    ($GLOBALS['perm']->have_perm('admin', $user_id) && !$GLOBALS['perm']->have_perm($auto_insert_perm, $user_id))
                    || !$GLOBALS['perm']->have_studip_perm($auto_insert_perm, $course_id, $user_id)
                );
            return !$is_auto_insert;
        });

        $courses = Course::findMany($course_ids);
        $urls = [];
        foreach ($courses as $course) {
            $is_student = !$GLOBALS['perm']->have_studip_perm('tutor', $course->seminar_id, $user_id);

            // Determine url to redirect to
            if (!$course->getSemClass()->isGroup()) {
                $urls[$course->seminar_id] = 'dispatch.php/course/members/index';
            } elseif ($is_student) {
                $navs[$course->seminar_id] = 0;
                continue;
            } else {
                $urls[$course->seminar_id] = 'dispatch.php/course/grouping/members';
            }

            $navigation = new Navigation(_('Teilnehmende'), $urls[$course->seminar_id]);
            $navigation->setImage(Icon::create('persons', Icon::ROLE_CLICKABLE));

            // Check permission, show no indicator if not at least tutor
            if ($is_student) {
                $navs[$course->seminar_id] = $navigation;
            }
        }

        // For the remaining courses, show if there are new users
        $remaining_course_ids = array_filter(
            $course_ids,
            fn($c_id) => $navs[$c_id] === null
        );

        $query = "SELECT seminar_users.seminar_id as seminar_id,
                         COUNT(seminar_users.user_id) as count,
                         COUNT(IF((seminar_users.mkdate > IFNULL(b.visitdate, :threshold) AND seminar_users.user_id != :user_id), seminar_users.user_id, NULL)) AS neue
                  FROM (
                      SELECT user_id, seminar_id, mkdate
                      FROM admission_seminar_user
                      WHERE seminar_id IN (:course_ids)

                      UNION ALL

                      SELECT user_id, seminar_id, mkdate
                      FROM seminar_user
                      WHERE seminar_id IN (:course_ids)
                  ) AS seminar_users
                  LEFT JOIN object_user_visits AS b
                    ON b.object_id = seminar_users.seminar_id
                       AND b.user_id = :user_id
                       AND b.plugin_id = :plugin_id
                  GROUP BY seminar_users.seminar_id";
        $users_per_course = DBManager::get()->fetchAll($query, [
            ':course_ids' => $remaining_course_ids,
            ':user_id' => $user_id,
            ':threshold' => object_get_visit_threshold(),
            ':plugin_id' => $this->getPluginId(),
        ]);

        foreach ($users_per_course as $result) {
            $navigation = new Navigation(_('Teilnehmende'), $urls[$result['seminar_id']]);

            if ($result['neue']) {
                $navigation->setImage(Icon::create('persons', Icon::ROLE_ATTENTION));
                $navigation->setLinkAttributes([
                    'title' => sprintf(
                        ngettext(
                            '%1$d Teilnehmende/r, %2$d neue/r',
                            '%1$d Teilnehmende, %2$d neue',
                            $result['count']
                        ),
                        $result['count'],
                        $result['neue']
                    )
                ]);
                $navigation->setBadgeNumber($result['neue']);
            } elseif ($result['count']) {
                $navigation->setImage(Icon::create('persons'));
                $navigation->setLinkAttributes([
                    'title' => sprintf(
                        ngettext(
                            '%d Teilnehmende/r',
                            '%d Teilnehmende',
                            $result['count']
                        ),
                        $result['count']
                    )
                ]);
            }

            $navs[$result['seminar_id']] = $navigation;
        }
        // map the zeros to null;
        return array_map(
            fn ($nav) => $nav ?: null,
            $navs
        );
    }


    /**
     * {@inheritdoc}
     */
    public function getTabNavigation($course_id)
    {
        if ($GLOBALS['user']->id === 'nobody') {
            return [];
        }

        $navigation = new Navigation(_('Teilnehmende'));
        $navigation->setImage(Icon::create('persons', Icon::ROLE_INFO_ALT));
        $navigation->setActiveImage(Icon::create('persons', Icon::ROLE_INFO));

        $course = Course::find($course_id);

        // Only courses without children have a regular member list and statusgroups.
        if (!$course->getSemClass()->isGroup()) {
            $navigation->addSubNavigation('view', new Navigation(_('Teilnehmende'), 'dispatch.php/course/members'));
            $navigation->addSubNavigation('statusgroups', new Navigation(_('Gruppen'), 'dispatch.php/course/statusgroups'));
        } elseif ($GLOBALS['perm']->have_studip_perm('tutor', $course_id)) {
            $navigation->addSubNavigation('children', new Navigation(_('Teilnehmende in Unterveranstaltungen'), 'dispatch.php/course/grouping/members'));
        }

        return count($navigation->getSubNavigation()) > 0 ? ['members' => $navigation] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata()
    {
        return [
            'summary' => _('Liste aller Teilnehmenden einschließlich Nachrichtenfunktionen'),
            'description' => _('Die Teilnehmenden werden gruppiert nach ihrer '.
                'jeweiligen Funktion in einer Tabelle gelistet. Für Lehrende '.
                'werden sowohl das Anmeldedatum als auch der Studiengang mit '.
                'Semesterangabe dargestellt. Die Liste kann in verschiedene '.
                'Formate exportiert werden. Außerdem gibt es die '.
                'Möglichkeiten, eine Rundmail an alle zu schreiben (nur '.
                'Lehrende) bzw. einzelne Teilnehmende separat anzuschreiben.'),
            'displayname' => _('Teilnehmende'),
            'keywords' => _('Rundmail an einzelne, mehrere oder alle Teilnehmenden;
                            Gruppierung nach Lehrenden, Tutor/-innen und Studierenden (Autor/-innen);
                            Aufnahme neuer Studierender (Autor/-innen) und Tutor/-innen;
                            Import einer Teilnehmendenliste;
                            Export der Teilnehmendenliste;
                            Einrichten von Gruppen;
                            Anzeige Studiengang und Fachsemester'),
            'descriptionshort' => _('Liste aller Teilnehmenden einschließlich Nachrichtenfunktionen'),
            'descriptionlong' => _('Die Teilnehmenden werden gruppiert nach ihrer jeweiligen Rolle in '.
                                   'einer Tabelle gelistet. Für Lehrende werden sowohl das Anmeldedatum '.
                                   'als auch der Studiengang mit Semesterangabe der Studierenden dargestellt. '.
                                   'Die Liste kann in verschiedene Formate exportiert werden. Außerdem gibt '.
                                   'es die Möglichkeiten für Lehrende, allen eine Rundmail zukommen zu lassen '.
                                   'bzw. einzelne Teilnehmende separat anzuschreiben.'),
            'category' => _('Lehr- und Lernorganisation'),
            'icon' => Icon::create('persons2', Icon::ROLE_INFO),
            'icon_clickable' => Icon::create('persons2'),
            'screenshots' => [
                'path' => 'assets/images/plus/screenshots/Teilnehmende',
                'pictures' => [
                    0 => ['source' => 'Liste_aller_Teilnehmenden_einer_Veranstaltung.jpg', 'title' => _('Liste aller Teilnehmenden einer Veranstaltung')],
                    1 => ['source' => 'Rundmail_an_alle_Teilnehmenden_einer_Veranstaltung.jpg', 'title' => _('Rundmail an alle Teilnehmenden einer Veranstaltung')],
                ]
            ],
        ];
    }

    protected function getCourseStatus(Course $course, $user_id)
    {
        $member = CourseMember::find([$course->id, $user_id]);
        if ($member) {
            return $member->status;
        }

        if (Config::get()->DEPUTIES_ENABLE && Deputy::isDeputy($user_id, $course->id)) {
            return 'dozent';
        }

        return false;
    }

    public function getInfoTemplate($course_id)
    {
        // TODO: Implement getInfoTemplate() method.
        return null;
    }

    public function isActivatableForContext(Range $context)
    {
        return $context->getRangeType() === 'course';
    }
}
