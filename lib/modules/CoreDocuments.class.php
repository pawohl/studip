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

class CoreDocuments implements StudipModule {
    
    function getIconNavigation($course_id, $last_visit, $user_id) {
        $navigation = new Navigation(_('Dateibereich'), "seminar_main.php?auswahl=$course_id&redirect_to=folder.php");
        $navigation->setImage('icons/16/grey/files.png');
        return $navigation;
    }
    
    function getTabNavigation($course_id) {
        $navigation = new Navigation(_('Dateien'));
        $navigation->setImage('icons/16/white/files.png');
        $navigation->setActiveImage('icons/16/black/files.png');
        $navigation->addSubNavigation('tree', new Navigation(_('Ordneransicht'), "folder.php?cmd=tree"));
        $navigation->addSubNavigation('all', new Navigation(_('Alle Dateien'), "folder.php?cmd=all"));
        return array('files' => $navigation);
    }

    function getNotificationObjects($course_id, $since, $user_id)
    {
        $items = array();
        $type = get_object_type($course_id, array('sem', 'inst', 'fak'));
        
        if ($type == 'sem') {
            $query = 'SELECT dokumente.*, seminare.Name, '. $GLOBALS['_fullname_sql']['full'] .' as fullname
                FROM dokumente
                JOIN auth_user_md5 USING (user_id)
                JOIN user_info USING (user_id)
                JOIN seminar_user USING (Seminar_id)
                JOIN seminare USING (Seminar_id)
                WHERE seminar_user.user_id = ? AND Seminar_id = ? 
                    AND dokumente.chdate > ?';
        } else {
            $query = 'SELECT dokumente.*, Institute.Name, '. $GLOBALS['_fullname_sql']['full'] .' as fullname
                FROM dokumente
                JOIN auth_user_md5 USING (user_id)
                JOIN user_info USING (user_id)
                JOIN user_inst ON (seminar_id = Institut_id)
                JOIN Institute USING (Institut_id)
                WHERE user_inst.user_id = ? AND Institut_id = ? 
                    AND dokumente.chdate > ?';
        }

        $stmt = DBManager::get()->prepare($query);
        $stmt->execute(array($user_id, $course_id, $since));
        
        while ($row = $stmt->fetch()) {
            $folder_tree = TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $row['seminar_id']));

            if ($folder_tree->isDownloadFolder($row['range_id'], $user_id)) {
                // use correct text depending on type of object
                if ($type == 'sem') {
                    $summary = sprintf('%s hat im Dateibereich der Veranstaltung "%s" die Datei "%s" hochgeladen.',
                        $row['fullname'], $row['Name'], $row['name']);
                } else {
                    $summary = sprintf('%s hat im Dateibereich der Einrichtung "%s" die Datei "%s" hochgeladen.',
                        $row['fullname'], $row['Name'], $row['name']);
                }
                
                // create ContentElement
                $items[] = new ContentElement(
                    _('Datei') . ': ' . $row['name'], $summary, 
                    formatReady(GetDownloadLink($row['dokument_id'], $row['name'])),
                    $row['user_id'], $row['fullname'],
                    URLHelper::getLink('folder.php#anker',
                        array('cid' => $row['seminar_id'], 'cmd' => 'tree', 'open' => $row['dokument_id'])),
                    $row['chdate']
                );
            }
        }

        return $items;
    }

    /** 
     * @see StudipModule::getMetadata()
     */ 
    function getMetadata()
    {
        return array(
            'summary' => _('Austausch von Dateien'),
            'description' => _('Im Dateibereich k�nnen Dateien sowohl von '.
                'Lehrenden als auch von Studierenden hoch- bzw. '.
                'heruntergeladen werden. Es k�nnen Ordner angelegt und '.
                'individuell benannt werden (nur Lehrende). Die Dateien '.
                'k�nnen somit strukturiert zur Verf�gung gestellt werden. '.
                'Multimediadateien wie Grafiken, Audio- und Videodateien '.
                'k�nnen sofort angezeigt bzw. abgespielt werden. �ber das '.
                'PlugIn "Dateiordnerberechtigung" k�nnen Im Dateibereich '.
                'bestimmte Rechte f�r Studierende, wie z.B. das Leserecht, '.
                'festgelegt werden.'),
            'displayname' => _('Dateien'),
            'category' => _('Lehr- und Lernorganisation'),
            'keywords' => _('Hoch- und Herunterladen von Dateien;
                            Anlegen von Ordnern und Unterordnern;
                            Verschieben einer Datei/eines Ordners per drag and drop innerhalb einer Veranstaltung;
                            Verschieben einer Datei/eines Ordners in eine andere Veranstaltung;
                            Kopieren einer Datei/eines Ordners in eine andere oder mehrere Veranstaltungen;
                            Verlinkung auf abgelegte Dateien m�glich;
                            Erstellung Hausaufgabenordner durch Aktivierung der Funktion "Dateiordnerberechtigung"'),
            'descriptionshort' => _('Austausch von Dateien'),
            'descriptionlong' => _('Dateien k�nnen sowohl von Lehrenden als auch von Studierenden hoch- bzw. '.
                                    'heruntergeladen werden. Ordner k�nnen angelegt und individuell benannt werden '.
                                    '(Standard: nur Lehrende), so dass Dateien strukuriert zur Verf�gung gestellt '.
                                    'werden k�nnen. Multimediadateien wie Grafiken, Audio- und Videodateien werden '.
                                    'sofort angezeigt bzw. abspielbar dargestellt. �ber das PlugIn "Dateiordnerberechtigungen" '.
                                    'k�nnen Im Dateibereich bestimmte Rechte (r, w, x, f) f�r Studierende, wie z.B. das '.
                                    'Leserecht (r), festgelegt werden.'),
            'icon' => 'icons/16/black/files.png',
            'screenshots' => array(
                'path' => 'plus/screenshots/Dateibereich_-_Dateiordnerberechtigung',
                'pictures' => array(
                    0 => array('source' => 'Ordneransicht_mit_geoeffnetem_Ordner.jpg', 'title' => _('Ordneransicht mit ge�ffnetem Ordner')),
                    1 => array('source' => 'Ordneransicht_mit_Dateiinformationen.jpg', 'title' => _('Ordneransicht mit Dateiinformationen')),
                    2 => array( 'source' => 'Neuen_Ordner_erstellen.jpg', 'title' => _('Neuen Ordner erstellen')),
                    3 => array( 'source' => 'Ordner_zum_Hausaufgabenordner_umwandeln.jpg', 'title' => _('Ordner zum Hausaufgabenordner umwandeln')),
                    4 => array( 'source' => 'Ansicht_alle_Dateien.jpg', 'title' => _('Ansicht alle Dateien'))
                )
            )
        );
    }
}
