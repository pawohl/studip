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

class CoreSchedule implements StudipModule {
    
    function getIconNavigation($course_id, $last_visit, $user_id) {
        $navigation = new Navigation(_('Ablaufplan'), URLHelper::getURL("seminar_main.php", array('auswahl' => $course_id, 'redirect_to' => "dispatch.php/course/dates")));
        $navigation->setImage('icons/16/grey/schedule.png');
        return $navigation;
    }
    
    function getTabNavigation($course_id) {
        // cmd und open_close_id mit durchziehen, damit ge�ffnete Termine ge�ffnet bleiben
        $req = Request::getInstance();
        $openItem = '';
        if (isset($req['cmd']) && isset($req['open_close_id'])) {
            $openItem = '&cmd='.$req['cmd'].'&open_close_id='.$req['open_close_id'];
        }
        
        $navigation = new Navigation(_('Ablaufplan'));
        $navigation->setImage('icons/16/white/schedule.png');
        $navigation->setActiveImage('icons/16/black/schedule.png');

        $navigation->addSubNavigation('dates', new Navigation(_('Termine'), "dispatch.php/course/dates"));
        $navigation->addSubNavigation('topics', new Navigation(_('Themen'), "dispatch.php/course/topics"));

        return array('schedule' => $navigation);
    }

    function getNotificationObjects($course_id, $since, $user_id)
    {
        return null;
    }

    /** 
     * @see StudipModule::getMetadata()
     */ 
    function getMetadata()
    {
        return array(
            'summary' => _('Anzeige aller Termine der Veranstaltung'),
            'description' => _('Der Ablaufplan listet alle Pr�senz-, '.
                'E-Learning-, Klausur-, Exkursions- und sonstige '.
                'Veranstaltungstermine auf. Zur besseren Orientierung und zur '.
                'inhaltlichen Einstimmung der Studierenden k�nnen Lehrende den '.
                'Terminen Themen hinzuf�gen, die z. B. eine Kurzbeschreibung '.
                'der Inhalte darstellen.'),
            'displayname' => _('Ablaufplan'),
            'category' => _('Lehr- und Lernorganisation'),
            'keywords' => _('Inhaltliche und r�umliche Orientierung f�r Studierende;
                            Beschreibung der Inhalte einzelner Termine;
                            Raumangabe;
                            Themenzuordnung zu Terminen;
                            Terminzuordnung zu Themen'),
            'descriptionshort' => _('Anzeige aller Termine der Veranstaltung, ggf. mit Themenansicht'),
            'descriptionlong' => _('Der Ablaufplan listet alle Pr�senz-, E-Learning-, Klausur-, Exkursions- ' .
                                    'und sonstige Veranstaltungstermine auf. Zur besseren Orientierung und zur ' .
                                    'inhaltlichen Einstimmung der Studierenden k�nnen Lehrende den Terminen ' .
                                    'Themen hinzuf�gen, die z. B. eine Kurzbeschreibung der Inhalte darstellen.'),          
            'icon' => 'icons/16/black/schedule.png',
            'screenshots' => array(
                'path' => 'plus/screenshots/Ablaufplan',
                'pictures' => array(
                    0 => array('source' => 'Termine_mit_Themen.jpg', 'title' => _('Termine mit Themen')),
                    1 => array( 'source' => 'Thema_bearbeiten_und_einem_Termin_zuordnen.jpg', 'title' => _('Thema bearbeiten und einem Termin zuordnen'))
                )
            )
        );
    }
}
