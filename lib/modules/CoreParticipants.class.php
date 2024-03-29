<?php

/*
 *  Copyright (c) 2012  Rasmus Fuhse <fuhse@data-quest.de>
 * 
 *  This program is free software; you can redistribute it and/or
 *  modify it under the terms of the GNU General Public License as
 *  published by the Free Software Foundation; either version 2 of
 *  the License, or (at your option) any later version.
 */

require_once 'lib/modules/StudipModule.class.php';

class CoreParticipants implements StudipModule {
    
    function getIconNavigation($course_id, $last_visit, $user_id) {
        $navigation = new Navigation(_('TeilnehmerInnen'), "seminar_main.php?auswahl=".$course_id."&redirect_to=dispatch.php/course/members");
        $navigation->setImage('icons/16/grey/persons.png');
        return $navigation;
    }
    
    function getTabNavigation($course_id) {
        #$navigation = new AutoNavigation(_('TeilnehmerInnen'));
        $navigation = new Navigation(_('TeilnehmerInnen'));
        $navigation->setImage('icons/16/white/persons.png');
        $navigation->setActiveImage('icons/16/black/persons.png');
        $navigation->addSubNavigation('view', new Navigation(_('TeilnehmerInnen'), 'dispatch.php/course/members'));
        if (Course::find($course_id)->aux_lock_rule) {
            $navigation->addSubNavigation('additional', new Navigation(_('Zusatzangaben'), 'dispatch.php/course/members/additional'));
        }

        $navigation->addSubNavigation('view_groups', new Navigation(_('Funktionen / Gruppen'), 'statusgruppen.php?view=statusgruppe_sem'));

        if ($GLOBALS['perm']->have_studip_perm('tutor', $course_id) && !LockRules::check($course_id, 'groups')) {
            $navigation->addSubNavigation('edit_groups', new Navigation(_('Funktionen / Gruppen verwalten'), 'admin_statusgruppe.php?new_sem=TRUE&range_id=' .$course_id));
        }

        return array('members' => $navigation);
    }

    function getNotificationObjects($course_id, $since, $user_id)
    {
        $items = array();
        $type = get_object_type($course_id, array('sem', 'inst', 'fak'));
        
        // only show new participants for seminars, not for institutes
        if ($type != 'sem') return $items;

        $stmt = DBManager::get()->prepare('SELECT seminar_user.*, seminare.Name, seminare.status,
            '. $GLOBALS['_fullname_sql']['full'] .' as fullname
            FROM seminar_user
            JOIN auth_user_md5 USING (user_id)
            JOIN user_info USING (user_id)
            JOIN seminare USING (Seminar_id)
            WHERE Seminar_id = ? 
                AND seminar_user.mkdate > ?');
        
        $stmt->execute(array($course_id, $since));
        
        while ($row = $stmt->fetch()) {
            $summary = sprintf('%s ist der Veranstaltung "%s" beigetreten.',
                $row['fullname'], $row['Name']);

            $items[] = new ContentElement(
                'Studiengruppe: Neue/r Teilnehmer/in', $summary, '', $row['user_id'], $row['fullname'],
                URLHelper::getLink('seminar_main.php?auswahl='. $row['Seminar_id'] .'&redirect_to=dispatch.php/course/member'),
                $row['mkdate']
            );
        }
        
        return $items;
    }

    /** 
     * @see StudipModule::getMetadata()
     */ 
    function getMetadata()
    {
        return array(
            'summary' => _('Liste aller Teilnehmenden einschlie�lich Nachrichtenfunktionen'),
            'description' => _('Die Teilnehmenden werden gruppiert nach ihrer '.
                'jeweiligen Funktion in einer Tabelle gelistet. F�r Lehrende '.
                'werden sowohl das Anmeldedatum als auch der Studiengang mit '.
                'Semesterangabe dargestellt. Die Liste kann in verschiedene '.
                'Formate exportiert werden. Au�erdem gibt es die '.
                'M�glichkeiten, eine Rundmail an alle zu schreiben (nur '.
                'Lehrende) bzw. einzelne Teilnehmende separat anzuschreiben.'),
            'displayname' => _('TeilnehmerInnen'),
            'keywords' => _('Rundmail an einzelne, mehrere oder alle Teilnehmenden;
                            Gruppierung nach Lehrenden, TutorInnen und teilnehmenden Studierenden (AutorInnen);
                            Aufnahme neuer teilnehmender Studierender (AutorInnen) und TutorInnen;
                            Import einer Teilnehmendenliste;
                            Export der Teilnehmendenliste;
                            Einrichten von Gruppen;
                            Anzeige Studiengang und Fachsemester'),
            'descriptionshort' => _('Liste aller Teilnehmenden einschlie�lich Nachrichtenfunktionen'),
            'descriptionlong' => _('Die Teilnehmenden werden gruppiert nach ihrer jeweiligen Rolle in '.
                                   'einer Tabelle gelistet. F�r Lehrende werden sowohl das Anmeldedatum '.
                                   'als auch der Studiengang mit Semesterangabe der Studierenden dargestellt. '.
                                   'Die Liste kann in verschiedene Formate exportiert werden. Au�erdem gibt '.
                                   'es die M�glichkeiten f�r Lehrende, allen eine Rundmail zukommen zu lassen '.
                                   'bzw. einzelne Teilnehmende separat anzuschreiben.'),
            'category' => _('Lehr- und Lernorganisation'),
            'icon' => 'icons/16/black/persons.png',
            'screenshots' => array(
                'path' => 'plus/screenshots/TeilnehmerInnen',
                'pictures' => array(
                    0 => array('source' => 'Liste_aller_TeilnehmerInnen_einer_Veranstaltung.jpg', 'title' => _('Liste aller TeilnehmerInnen einer Veranstaltung')),
                    1 => array( 'source' => 'Rundmail_an_alle_TeilnehmerInnen_einer_Veranstaltung.jpg', 'title' => _('Rundmail an alle TeilnehmerInnen einer Veranstaltung'))
                )
            )
        );
    }
}
