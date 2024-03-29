<?php
# Lifter002: TODO
# Lifter007: TODO
# Lifter003: TEST
# Lifter010: TODO
/**
* AdminModules.class.php
*
* administrate modules (global and local for institutes and Veranstaltungen)
*
*
* @author       Cornelis Kater <ckater@gwdg.de>, Suchi & Berg GmbH <info@data-quest.de>
* @access       public
* @modulegroup      core
* @module       AdminModules.class.php
* @package      studip_core
*/

// +---------------------------------------------------------------------------+
// This file is part of Stud.IP
// AdminModules.class.php
// Administration fuer Module (global und lokal fuer Veranstaltungen und Einrichtungen)
// Copyright (C) 2003 Cornelis Kater <ckater@gwdg.de>, Suchi & Berg GmbH <info@data-quest.de>
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

global $RELATIVE_PATH_ELEARNING_INTERFACE;

require_once ('lib/datei.inc.php');
require_once ('lib/dates.inc.php');
require_once ($RELATIVE_PATH_ELEARNING_INTERFACE . "/ObjectConnections.class.php");
require_once ($RELATIVE_PATH_ELEARNING_INTERFACE . "/ELearningUtils.class.php");

class AdminModules extends ModulesNotification {

    function AdminModules() {
        parent::ModulesNotification();
        //please add here the special messages for modules you need consistency checks (defined below in this class)
        $this->registered_modules["forum"]["msg_warning"] = _("Wollen Sie wirklich das Forum deaktivieren?");
        //$this->registered_modules["forum"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren des Forums werden <b>%s</b> Postings ebenfalls gel�scht!");
        $this->registered_modules["forum"]["msg_activate"] = _("Das Forum kann jederzeit aktiviert werden.");
        $this->registered_modules["forum"]["msg_deactivate"] = _("Das Forum kann jederzeit deaktiviert werden.");


        $this->registered_modules["documents"]["msg_warning"] = _("Wollen Sie wirklich den Dateiordner deaktivieren?");
        //$this->registered_modules["documents"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren des Dateiordners werden <b>%s</b> Dateien und Ordner ebenfalls gel�scht!");
        $this->registered_modules["documents"]["msg_activate"] = _("Der Dateiordner kann jederzeit aktiviert werden.");
        $this->registered_modules["documents"]["msg_deactivate"] = _("Der Dateiordner kann jederzeit deaktiviert werden.");

        $this->registered_modules["schedule"]["msg_activate"] = _("Die Ablaufplanverwaltung kann jederzeit aktiviert werden.");
        $this->registered_modules["schedule"]["msg_deactivate"] = _("Die Ablaufplanverwaltung kann jederzeit deaktiviert werden.");

        $this->registered_modules["participants"]["msg_activate"] = _("Die TeilnehmerInnenverwaltung kann jederzeit aktiviert werden.");
        $this->registered_modules["participants"]["msg_deactivate"] = _("Die TeilnehmerInnenverwaltung kann jederzeit deaktiviert werden. Bachten Sie, dass Sie dann keine normalen Teilnehmer verwalten k�nnen!");

        $this->registered_modules["personal"]["msg_activate"] = _("Die Personalliste kann jederzeit aktiviert werden.");
        $this->registered_modules["personal"]["msg_deactivate"] = _("Die Personalliste kann jederzeit deaktiviert werden.");

        $this->registered_modules["literature"]["msg_warning"] = _("Wollen Sie wirklich die Literaturverwaltung deaktivieren?");
        //$this->registered_modules["literature"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren der Literaturverwaltung werden <b>%s</b> �ffentliche / nicht �ffentliche Literaturlisten ebenfalls gel�scht!");
        $this->registered_modules["literature"]["msg_activate"] = _("Die Literaturverwaltung kann jederzeit aktiviert werden.");
        $this->registered_modules["literature"]["msg_deactivate"] = _("Die Literaturverwaltung kann jederzeit deaktiviert werden.");

        $this->registered_modules["wiki"]["msg_warning"] = _("Wollen Sie wirklich das Wiki deaktivieren?");
        //$this->registered_modules["wiki"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren des Wiki-Webs werden <b>%s</b> Seitenversionen ebenfalls gel�scht!");
        $this->registered_modules["wiki"]["msg_activate"] = _("Das Wiki-Web kann jederzeit aktiviert werden.");
        $this->registered_modules["wiki"]["msg_deactivate"] = _("Das Wiki-Web kann jederzeit deaktiviert werden.");

        $this->registered_modules["scm"]["msg_activate"] = _("Die freie Informationsseite kann jederzeit aktiviert werden.");
        $this->registered_modules["scm"]["msg_warning"] = _("Wollen Sie wirklich die freie Informationsseite deaktivieren?");
        //$this->registered_modules["scm"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren der freien Informationsseite werden die eingestellten Inhalte gel�scht!");
        $this->registered_modules["scm"]["msg_deactivate"] = _("Die freie Informationsseite kann jederzeit deaktiviert werden.");

        $this->registered_modules["elearning_interface"]["name"] = _("Lernmodul-Schnittstelle");
        $this->registered_modules["elearning_interface"]["msg_warning"] = _("Wollen Sie wirklich die Schnittstelle f�r die Integration von Content-Modulen deaktivieren und damit alle bestehenden Verkn�pfungen mit Lernmodulen l�schen? (Alle erstellten Inhalte im angebundenen System werden gel�scht).");
        $this->registered_modules["elearning_interface"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren der Schnittstelle f�r die Integration von Content-Modulen werden <b>%s</b> Verkn�pfungen mit Lernmodulen aufgel�st!");
        $this->registered_modules["elearning_interface"]["msg_activate"] = _("Die Schnittstelle f�r die Integration von Content-Modulen kann jederzeit aktiviert werden.");
        $this->registered_modules["elearning_interface"]["msg_deactivate"] = _("Die Schnittstelle f�r die Integration von Content-Modulen kann jederzeit deaktiviert werden.");

        $this->registered_modules["documents_folder_permissions"]['name'] = _("Dateiordnerberechtigungen");
        $this->registered_modules["documents_folder_permissions"]["msg_activate"] = _("Die Dateiordnerberechtigungen k�nnen jederzeit aktiviert werden.");
        $this->registered_modules["documents_folder_permissions"]["msg_warning"] = _("Wollen Sie wirklich die Dateiordnerberechtigungen deaktivieren und damit eventuell versteckte Inhalte zug�nglich machen?");
        $this->registered_modules["documents_folder_permissions"]["msg_deactivate"] = _("Die Dateiordnerberechtigungen k�nnen jederzeit deaktiviert werden.");
        $this->registered_modules["documents_folder_permissions"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren der Dateiordnerberechtigungen werden <b>%s</b> gesch�tzte Ordner zug�nglich!");
        $this->registered_modules["documents_folder_permissions"]['preconditions'] = array('documents');
        $this->registered_modules["documents_folder_permissions"]['metadata'] = array(
                'summary' => _("Dateiordnerberechtigungen"),
                'descriptionshort' => _("Vergabe von Rechten f�r Dateiordner an teilnehmende Studierende (AutorInnen)"),
                'category' => _("Lehr- und Lernorganisation"),
                'keywords' => _('Das Recht "Lesen" (r): Dateien k�nnen ge�ffnet und heruntergeladen werden;
                                Das Recht "Schreiben" (w): Studierende k�nnen Dateien hochladen;
                                Das Recht "Sichtbarkeit" (x): Ordner wird angezeigt;
                                Das Recht "Ordner erstellen" (f): Studierende k�nnen Unterordner anlegen'),
                'icon' => 'icons/16/black/files.png',
                'screenshots' => array(
                    'path' => 'plus/screenshots/Dateiordnerberechtigung', 
                    'pictures' => array( 
                        0 => array(
                            'source' => 'Ordner_zum_Hausaufgabenordner_umwandeln.jpg', 
                            'title'  => _('Ordner zum Hausaufgabenordner umwandeln'),
                        )
                    )
                ),              
                'description' => _('Mit den Einstellungen zur Dateiordnerberechtigung k�nnen Lehrende die Zugriffsrechte '.
                                    'f�r Ordner im Dateibereich ver�ndern. Wird z. B. das Leserecht entfernt, entsteht ein '.
                                    'Hausaufgabenordner, in den Studierende Dateien zwar hochladen, aber nicht sehen k�nnen, '.
                                    'welche Dateien sich noch im Ordner befinden. Es gibt vier Einstellungsm�glichkeiten, '.
                                    'die miteinander kombiniert werden k�nnen (Lesen, Schreiben, Sehen, Ordner anlegen). '.
                                    'Beim Einschalten bestimmter Funktionen wird die Dateiordnerberechtigung automatisch eingeschaltet.')
            );
        if (get_config('CALENDAR_GROUP_ENABLE')) {
            $this->registered_modules["calendar"]["name"] = _("Kalender");
            $this->registered_modules["calendar"]["msg_activate"] = _("Der Kalender kann jederzeit aktiviert werden.");
            $this->registered_modules["calendar"]["msg_warning"] = _("Wollen Sie wirklich den Kalender deaktivieren?");
            $this->registered_modules["calendar"]["msg_pre_warning"] = _("Achtung: Beim Deaktivieren des Kalenders werden <b>%s</b> Termine ebenfalls gel�scht!");
            $this->registered_modules["calendar"]["msg_deactivate"] = _("Der Kalender kann jederzeit deaktiviert werden.");
        }
        $this->registered_modules["overview"]['name'] = _("�bersicht");
        $this->registered_modules["overview"]["msg_activate"] = _("Die Veranstaltungs�bersicht kann jederzeit aktiviert werden.");
        $this->registered_modules["overview"]["msg_deactivate"] = _("Die Veranstaltungs�bersicht kann jederzeit deaktiviert werden.");

        $this->registered_modules["admin"]['name'] = _("Verwaltung");
        $this->registered_modules["admin"]["msg_activate"] = _("Wenn die Verwaltungsseite aktiviert wird, kann die Veranstaltung wieder von Admin und Dozenten bearbeitet werden.");
        $this->registered_modules["admin"]["msg_deactivate"] = _("Wenn Sie die Verwaltungsseite deaktivieren, k�nnen Sie sie eventuell nicht mehr aktivieren.");
        $this->registered_modules["admin"]["msg_warning"] = _("Wenn die Verwaltungsseite deaktiviert wird, k�nnen Dozenten und Admin (Sie eventuell eingeschlossen) die Veranstaltung nicht mehr administrieren.");

        $this->registered_modules["resources"]['name'] = _("Ressourcen");
        $this->registered_modules["resources"]["msg_activate"] = _("Sie k�nnen die Ressourcenseite jederzeit aktivieren.");
        $this->registered_modules["resources"]["msg_deactivate"] = _("Sie k�nnen die Ressourcenseite jederzeit deaktivieren.");
    }

    function getDocumentsExistingItems($range_id) { //getModuleDocumentsExistingItems
        $query = "SELECT COUNT(dokument_id) FROM dokumente WHERE seminar_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        $items = $statement->fetchColumn();

        $folder_tree = TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $range_id));

        $items += $folder_tree->getNumKidsKids('root') - $folder_tree->getNumKids('root');
        return $items;
    }

    /*function moduleDocumentsDeactivate($range_id) {
        //delete_all_documents($range_id);
        //�rgs, warum immer ich...
        $this->clearBit($_SESSION['admin_modules_data']["changed_bin"], $this->registered_modules['documents_folder_permissions']['id']);
    }*/

    function moduleDocumentsActivate($range_id) {
        if ($this->getDocumentsExistingItems($range_id)) {
            return;
        }
        create_folder(_('Allgemeiner Dateiordner'),
                      _('Ablage f�r allgemeine Ordner und Dokumente der Veranstaltung'),
                      $range_id,
                      7,
                      $range_id);
    }

    /*function getModuleLiteratureExistingItems($range_id) {
        $list_count = StudipLitList::GetListCountByRange($range_id);
        return ($list_count["visible_list"] || $list_count["invisible_list"]) ? $list_count["visible_list"] . "/" . $list_count["invisible_list"] : false;
    }*/

    /*function moduleLiteratureDeactivate($range_id) {
        //return StudipLitList::DeleteListsByRange($range_id);
    }*/

    function getModuleWikiExistingItems($range_id) {
        $query = "SELECT COUNT(keyword) FROM wiki WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        return $statement->fetchColumn();
    }

    /*function moduleWikiDeactivate($range_id) {
        DBManager::get()
            ->prepare("DELETE FROM wiki WHERE range_id = ?")
            ->execute(array($range_id));

        DBManager::get()
            ->prepare("DELETE FROM wiki_links WHERE range_id = ?")
            ->execute(array($range_id));

        DBManager::get()
            ->prepare("DELETE FROM wiki_locks WHERE range_id = ?")
            ->execute(array($range_id));

    }*/

    /*function getModuleScmExistingItems($range_id) {
        $query = "SELECT COUNT(scm_id) FROM scm WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        return $statement->fetchColumn();
    }*/

    /*function moduleScmDeactivate($range_id) {
        DBManager::get()
            ->prepare("DELETE FROM scm WHERE range_id = ?")
            ->execute(array($range_id));
    }*/

    /**
     * prepares the database when activating the scm module.
     *
     * @param $range_id id
     */
    function moduleScmActivate($range_id) {
        // check if existing items are available
        $query = "SELECT COUNT(scm_id) FROM scm WHERE range_id = ?";
        $statement = DBManager::get()->prepare($query);
        $statement->execute(array($range_id));
        $existingItems = $statement->fetchColumn();

        global $user, $SCM_PRESET;
        if ($existingItems) {
            return;
        }
        //create a default folder
        $query = "INSERT IGNORE INTO scm "
               . "(scm_id, range_id, user_id, tab_name, content, mkdate, chdate) "
               . "VALUES (?, ?, ?, ?, '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())";
        DBManager::get()
            ->prepare($query)
            ->execute(array(
                md5(uniqid('simplecontentmodule')),
                $range_id,
                $GLOBALS['user']->id,
                $GLOBALS['SCM_PRESET'][1]['name']
            ));
    }

    function getModuleElearning_interfaceExistingItems($range_id) {
        $object_connections = new ObjectConnections($range_id);
        return is_array($object_connections->getConnections()) ? count($object_connections->getConnections()) : 0;
    }

    function moduleElearning_interfaceDeactivate($range_id) {
        global $connected_cms;
        foreach(ObjectConnections::GetConnectedSystems($range_id) as $system){
            ELearningUtils::loadClass($system);
            $connected_cms[$system]->deleteConnectedModules($range_id);
        }
    }

    function moduledocuments_folder_permissionsDeactivate($range_id){
        $folder_tree = TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $range_id));
        foreach($folder_tree->getKidsKids('root') as $folder_id){
            $folder_tree->setDefaultPermission($folder_id);
        }
    }

    function moduledocuments_folder_permissionsActivate($range_id){
    }

    function getModuledocuments_folder_permissionsExistingItems($range_id) {
        $folder_tree = TreeAbstract::GetInstance('StudipDocumentTree', array('range_id' => $range_id));
        return count($folder_tree->getUnreadableFolders('xxx', true));
    }

    function moduledocuments_folder_permissionsPreconditions($range_id, $args){
        if (is_array($args)){
            $must_activate = array();
            foreach($args as $m){
                if (!$this->getStatus($m, $range_id)){
                    $must_activate[] = $this->registered_modules[$m]['name'];
                }
            }
            if (count($must_activate)){
                return sprintf(_("Die Dateiordnerberechtigungen erfordern die Aktivierung von: <b>%s</b>"), join(', ',$must_activate));
            }
        }
        return null;
    }

    function getModuleCalendarExistingItems($range_id)
    {
        return CalendarEvent::countBySql('range_id = ?', array($range_id));
    }

}
