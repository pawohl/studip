<?php
# Lifter010: TODO

/*
 * management.php - realises a redirector for administrative pages
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      tgloeggl <tgloeggl@uos.de>
 * @author      aklassen <andre.klassen@elan-ev.de>
 * @author      dsiegfried <david.siegfried@uni-vechta.de>
 * @copyright   2010 ELAN e.V.
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       1.10
 */

class Course_ManagementController extends AuthenticatedController
{
    function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (!$GLOBALS['perm']->have_studip_perm("tutor", $GLOBALS['SessionSeminar'])) {
            throw new AccessDeniedException();
        }

        if (Context::isCourse()) {
            $sem_class = $GLOBALS['SEM_CLASS'][$GLOBALS['SEM_TYPE'][Context::get()->status]['class']] ?: SemClass::getDefaultSemClass();
        } else {
            $sem_class = SemClass::getDefaultInstituteClass(Context::get()->type);
        }
        if (!$sem_class->isModuleAllowed("CoreAdmin")) {
            throw new Exception(_('Dies ist eine Studiengruppe und kein Seminar!'));
        }
        PageLayout::setTitle(sprintf(_("%s - Verwaltung"), Context::getHeaderLine()));
        PageLayout::setHelpKeyword('Basis.InVeranstaltungVerwaltung');
    }

    /**
     * shows index page of course or institute management
     *
     * @return void
     */
    public function index_action()
    {
        $this->redirect('course/contentmodules');
    }

    public function order_settings_action()
    {
        PageLayout::setTitle(_('Sortiereinstellungen'));
        $this->order_by_field = UserConfig::get($GLOBALS['user']->id)->COURSE_MANAGEMENT_SELECTOR_ORDER_BY ?? 'name';
        $this->render_template('course/shared/order_settings');
    }

    public function store_order_settings_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        UserConfig::get($GLOBALS['user']->id)->store('COURSE_MANAGEMENT_SELECTOR_ORDER_BY', Request::get('order_by', 'name'));
        PageLayout::postSuccess(_('Die Sortiereinstellungen wurden erfolgreich gespeichert.'));

        $from = URLHelper::getURL(Request::get('from'));
        if (!is_internal_url($from)) {
            throw new RuntimeException('Only internal redirects allowed');
        }

        $this->redirect($from);
    }

    /**
     * change the visibility of a course
     *
     * @return void
     */
    public function change_visibility_action()
    {
        if ((Config::get()->ALLOW_DOZENT_VISIBILITY || $GLOBALS['perm']->have_perm('admin'))
            && !LockRules::Check($GLOBALS['SessionSeminar'], 'seminar_visibility')
            && check_ticket(Request::option('studip_ticket')))
        {
            $course = Course::findCurrent();
            if ($course->isOpenEnded() || $course->end_semester->visible) {
                if (!$course->visible) {
                    StudipLog::log('SEM_VISIBLE', $course->id);
                    $course->visible = true;
                    $msg = _('Die Veranstaltung wurde sichtbar gemacht.');
                } else {
                    StudipLog::log('SEM_INVISIBLE', $course->id);
                    $course->visible = false;
                    $msg = _('Die Veranstaltung wurde versteckt.');
                }
                if ($course->store()) {
                    PageLayout::postSuccess($msg);
                }
            }
        }
        $this->redirect('course/basicdata/view');
    }

    /**
     * shows the lock rules
     *
     * @return void
     */
    public function lock_action()
    {
        PageLayout::setTitle(_('Sperrebene ändern'));
        $course = Course::findCurrent();

        if (!$course) {
            $this->redirect($this->action_url('index'));
            return;
        }

        $this->all_lock_rules    = array_merge([['name' => ' -- ' . _("keine Sperrebene") . ' -- ', 'lock_id' => 'none']], LockRule::findAllByType('sem'));
        $this->current_lock_rule = LockRule::find($course->lock_rule);
    }

    /**
     * set the lock rule
     *
     * @return void
     */
    public function set_lock_rule_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        if (!$GLOBALS['perm']->have_studip_perm('admin', $GLOBALS['SessionSeminar'])) {
            throw new AccessDeniedException();
        }
        $course = Course::findCurrent();

        if ($course) {
            $rule_id = Request::get('lock_sem') != 'none' ? Request::get('lock_sem') : null;

            $course->lock_rule = $rule_id;
            if ($course->store()) {
                if (!is_null($rule_id)) {
                    $lock_rule = LockRule::find($rule_id);
                    $msg       = sprintf(_('Die Sperrebene %s wurde erfolgreich übernommen!'), $lock_rule->name);
                } else {
                    $msg = _('Die Sperrebene wurde erfolgreich zurückgesetzt!');
                }
                PageLayout::postSuccess($msg);
            }
        }
        $this->relocate('course/basicdata/view');
    }
}
