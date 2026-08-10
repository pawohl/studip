<?php


# Lifter010: TODO
/*
 * BrowseNavigation.php - navigation for my courses / institutes
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Elmar Ludwig
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */

class BrowseNavigation extends Navigation
{
    /**
     * Initialize a new Navigation instance.
     */
    public function __construct()
    {
        global $perm;

        $courselink = null;
        if (User::findCurrent()) {
            $coursetext = _('Veranstaltungen');
            $courseinfo = _('Meine Veranstaltungen & Einrichtungen');

            if ($perm->have_perm('admin')) {
                $courselink = 'dispatch.php/admin/courses';
            }
        } else {
            $coursetext = _('Freie Veranstaltungen');
            $courseinfo = _('Freie Veranstaltungen');
            $courselink = 'dispatch.php/public_courses';
        }

        parent::__construct($coursetext, $courselink);

        if (!Context::getId()) {
            $this->setImage(Icon::create('seminar', Icon::ROLE_NAVIGATION, ['title' => $courseinfo]));
        }
    }

    /**
     * Initialize the subnavigation of this item. This method
     * is called once before the first item is added or removed.
     */
    public function initSubNavigation()
    {
        parent::initSubNavigation();

        // my courses
        if (User::findCurrent()) {

            if ($GLOBALS['perm']->have_perm('admin')) {
                $navigation = new Navigation(_('Administration'));
            } else {
                $navigation = new Navigation(_('Meine Veranstaltungen'));
            }

            $navigation->addSubNavigation('list', new Navigation(
                $GLOBALS['perm']->have_perm('admin') ? _('Veranstaltungsadministration') : _('Aktuelle Veranstaltungen'),
                'dispatch.php/my_courses'
            ));

            if ($GLOBALS['perm']->have_perm('admin')) {
                $navigation->addSubNavigation('overlapping', new Navigation(_('Überschneidungsfreiheit'), 'dispatch.php/admin/overlapping'));
                $navigation->addSubNavigation('schedule', new Navigation(_('Veranstaltungs-Stundenplan'), 'dispatch.php/admin/courseplanning'));
            } elseif (ArchivedCourseMember::findByUser(User::findCurrent()->id)) {
                $navigation->addSubNavigation('archive', new Navigation(_('Archivierte Veranstaltungen'), 'dispatch.php/my_courses/archive'));
            }

            if (isset($_SESSION['ContentItemSelection'])) {
                $navigation->addSubNavigation('content_item', new Navigation(_('Veranstaltung verknüpfen'), 'dispatch.php/lti/content_item'));
            }

            $this->addSubNavigation('my_courses', $navigation);

            if (Config::get()->MY_COURSES_ENABLE_STUDYGROUPS && !$GLOBALS['perm']->have_perm('admin')) {
                $navigation = new Navigation(_('Meine Studiengruppen'), 'dispatch.php/my_studygroups');
                $navigation->addSubNavigation('all', new Navigation(_('Studiengruppensuche'), 'dispatch.php/studygroup/browse'));
                $navigation->addSubNavigation('index', new Navigation(_('Meine Studiengruppen'), 'dispatch.php/my_studygroups'));
                $this->addSubNavigation('my_studygroups', $navigation);
            }

            if (!$GLOBALS['perm']->have_perm('admin')) {
                $navigation = new Navigation(_('Meine Einrichtungen'), 'dispatch.php/my_institutes');
                $this->addSubNavigation('my_institutes', $navigation);

                if (RolePersistence::isAssignedRole($GLOBALS['user']->id, 'DedicatedAdmin')) {
                    $navigation = new Navigation(_('Administration'), 'dispatch.php/admin/courses');
                    $this->addSubNavigation('admincourses', $navigation);
                }
            }

            if ($GLOBALS['perm']->have_perm('admin') || ($GLOBALS['perm']->have_perm('dozent') && Config::get()->ALLOW_DOZENT_COURSESET_ADMIN)) {
                $navigation = new Navigation(_('Anmeldesets'), 'dispatch.php/admission/courseset/index');
                $this->addSubNavigation('coursesets', $navigation);
                $navigation->addSubNavigation('sets', new Navigation(_('Anmeldesets verwalten'), 'dispatch.php/admission/courseset/index'));
                $navigation->addSubNavigation('userlists', new Navigation(_('Personenlisten'), 'dispatch.php/admission/userlist/index'));
                $navigation->addSubNavigation('restricted_courses', new Navigation(_('teilnahmebeschränkte Veranstaltungen'), 'dispatch.php/admission/restricted_courses'));
            }


        }
    }
}
