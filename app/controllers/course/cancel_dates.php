<?php

/**
 * cancel_dates.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     admin
 */
class Course_CancelDatesController extends AuthenticatedController
{
    /**
     * common tasks for all actions
     */
    public function before_filter(&$action, &$args)
    {
        global $perm;

        parent::before_filter($action, $args);

        if (Request::get('termin_id')) {
            $this->dates[0]  = CourseDate::find(Request::option('termin_id'));
            $this->course_id = $this->dates[0]->range_id;
        }

        if (Request::get('issue_id')) {
            $this->issue_id  = Request::option('issue_id');
            $this->dates = CourseDate::findBySQL(
                "JOIN `themen_termine` USING (`termin_id`)
                WHERE `issue_id` = :issue_id ORDER BY `date`",
                ['issue_id' => Request::option('issue_id')]
            );
            $this->course_id = $this->dates[0]->range_id;
        }
        if (!get_object_type($this->course_id, ['sem']) || !$perm->have_studip_perm("tutor", $this->course_id)) {
            throw new Trails\Exception(400);
        }
        PageLayout::setHelpKeyword('Basis.VeranstaltungenVerwaltenAendernVonZeitenUndTerminen');
        PageLayout::setTitle(Course::findCurrent()->getFullName() . " - " . _('Veranstaltungstermine absagen'));
    }

    public function index_action()
    {
    }

    public function store_action()
    {
        CSRFProtection::verifyUnsafeRequest();
        $msg = '';
        $cancelled_dates = [];
        $ex_dates = [];

        foreach ($this->dates as $date) {
            $cancelled_dates[] = $date->getFullName();
            $ex_date = $date->cancelDate();
            if ($ex_date) {
                $ex_date->content = Request::get('cancel_dates_comment');
                $ex_date->store();
                $ex_dates[] = $ex_date;
            }
        }
        if (Request::int('cancel_dates_snd_message') && count($ex_dates) > 0) {
            $snd_messages = raumzeit_send_cancel_message(Request::get('cancel_dates_comment'), $ex_dates);
            if ($snd_messages > 0) {
                $msg = _('Alle Teilnehmenden wurden benachrichtigt.');
            }
        }

        PageLayout::postSuccess(
            _('Folgende Termine wurden abgesagt') . ($msg ? ' (' . $msg . '):' : ':'),
            array_map('htmlReady', $cancelled_dates)
        );

        $this->redirect($this->url_for('course/dates'));
    }

    public function after_filter($action, $args)
    {
        if (Request::isXhr()) {
            foreach ($this->response->headers as $k => $v) {
                if ($k === 'Location') {
                    $this->response->headers['X-Location'] = $v;
                    unset($this->response->headers['Location']);
                    $this->response->set_status(200);
                    $this->response->body = '';
                }
            }
        }
        parent::after_filter($action, $args);
    }

}
