<?php

/**
 * CourseMemberAdmission.php
 *
 * Specifies a mandatory course membership for course admission.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */

class CourseMemberAdmission extends AdmissionRule
{
    const MODE_MUST_BE_IN_COURSES = 0;
    const MODE_MAY_NOT_BE_IN_COURSES = 1;
    // --- ATTRIBUTES ---

    /**
     * End of course admission.
     */
    public $courses_to_add = '[]';
    public $modus = '';

    // --- OPERATIONS ---

    /**
     * Standard constructor
     *
     * @param  String $ruleId
     * @param  String e
     */
    public function __construct($ruleId = '', $courseSetId = '')
    {
        parent::__construct($ruleId, $courseSetId);

        if ($ruleId) {
            $this->load();
        } else {
            $this->id = $this->generateId('coursememberadmissions');
        }
    }

    /**
     * Deletes the admission rule and all associated data.
     */
    public function delete()
    {
        parent::delete();

        // Delete rule data.
        DBManager::get()->execute(
            "DELETE FROM `coursememberadmissions` WHERE `rule_id` = ?",
            [$this->id]
        );
    }

    /**
     * Gets some text that describes what this AdmissionRule (or respective
     * subclass) does.
     */
    public static function getDescription()
    {
        return _("Anmelderegeln dieses Typs legen eine Veranstaltung fest, in der die Nutzer bereits eingetragen sein müssen, oder in der sie nicht eingetragen sein dürfen, um sich zu Veranstaltungen des Anmeldesets anmelden zu können.");
    }

    /**
     * Return this rule's name.
     */
    public static function getName()
    {
        return _("Veranstaltungsbezogene Anmeldung");
    }

    /**
     * Helper function for loading rule definition from database.
     */
    public function load()
    {
        // Load data.
        $stmt = DBManager::get()->prepare("SELECT *
            FROM `coursememberadmissions` WHERE `rule_id`=? LIMIT 1");
        $stmt->execute([$this->id]);
        $current = $stmt->fetchOne();
        if ($current) {
            $this->message = $current['message'];
            $this->startTime = $current['start_time'];
            $this->endTime = $current['end_time'];
            $this->courses_to_add = $current['courses'];
            $this->modus = (int) $current['modus'];
        } else {
            $this->id = $this->generateId('coursememberadmissions');
        }
    }

    /**
     * Is admission allowed according to the defined time frame?
     *
     * @param  String $userId
     * @param  String $courseId
     * @return Array
     */
    public function ruleApplies($userId, $courseId)
    {
        $errors = [];
        if ($this->checkTimeFrame()) {
            $courses = $this->getDecodedCourses();
            foreach ($courses as $course) {
                $is_member = CourseMember::exists([$course->id, $userId]);

                if (($this->modus == self::MODE_MUST_BE_IN_COURSES && !$is_member)
                    || ($this->modus == self::MODE_MAY_NOT_BE_IN_COURSES && $is_member)
                ) {
                    $errors[] = $this->getMessage($course);
                }
            }

            // mode: "Mitgliedschaft ist in mindestens einer dieser Veranstaltungen notwendig"
            if ($this->modus == self::MODE_MUST_BE_IN_COURSES && count($errors) < count($courses)) {
                $errors = [];
            }
        }

        return $errors;
    }

    /**
     * Uses the given data to fill the object values. This can be used
     * as a generic function for storing data if the concrete rule type
     * isn't known in advance.
     *
     * @param Array $data
     * @return AdmissionRule This object.
     */
    public function setAllData($data)
    {
        $this->modus = (int) $data['modus'];
        $this->courses_to_add = json_encode(array_map(fn ($course) => $course['id'], $data['courses']));
        return $this;
    }

    /**
     * Store rule definition to database.
     */
    public function store()
    {
        // Store data.
        $stmt = DBManager::get()->prepare("INSERT INTO `coursememberadmissions`
            (`rule_id`, `message`, `courses`, `modus`, `start_time`,
            `end_time`, `mkdate`, `chdate`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `start_time`=VALUES(`start_time`),
            `end_time`=VALUES(`end_time`),message=VALUES(message),courses=VALUES(courses),modus=VALUES(modus), `chdate`=VALUES(`chdate`)");
        $stmt->execute([$this->id, $this->message, $this->courses_to_add, (int)$this->modus, (int)$this->startTime,
            (int)$this->endTime,  time(), time()]);
    }

    /**
     * A textual description of the current rule.
     *
     * @return String
     */
    public function toString()
    {
        return $this->getTemplateFactory()->render('info', [
            'courses' => $this->getDecodedCourses(),
            'rule'    => $this,
            'modus'   => $this->modus,
        ]);
    }

    /**
     * Validates if the given request data is sufficient to configure this rule
     * (e.g. if required values are present).
     *
     * @param  Array $data Request data
     * @return Array Error messages.
     */
    public function validate($data)
    {
        $errors = parent::validate($data);
        if (!$data['courses_to_add']) {
            $errors[] = _('Bitte wählen Sie eine Veranstaltung aus.');
        }
        return $errors;
    }

    public function getMessage($course = null)
    {
        $message = parent::getMessage();

        if ($course) {
            return sprintf($message, $course->getFullName('number-name'));
        } else {
            return $message;
        }
    }

    private function getDecodedCourses()
    {
        $decoded_courses = json_decode($this->courses_to_add, true);
        if (!$decoded_courses) {
            return [];
        }
        return Course::findMany($decoded_courses);
    }

    public function getValidityPeriod(): string
    {
        if ($this->getStartTime() && $this->getEndTime()) {
            return sprintf(
                _('Diese Regel gilt von %s bis %s.'),
                strftime('%d.%m.%Y %H:%M', $this->getStartTime()),
                strftime('%d.%m.%Y %H:%M', $this->getEndTime())
            );
        }

        if ($this->getStartTime() && !$this->getEndTime()) {
            return sprintf(
                _('Diese Regel gilt ab %s.'),
                strftime('%d.%m.%Y %H:%M', $this->getStartTime())
            );
        }

        if (!$this->getStartTime() && $this->getEndTime()) {
            return sprintf(
                _('Diese Regel gilt bis %s.'),
                strftime('%d.%m.%Y %H:%M', $this->getEndTime())
            );
        }

        return '';
    }

    private function getTemplateFactory(): Flexi\Factory
    {
        return new Flexi\Factory(__DIR__ . '/templates/');
    }

    /**
     * Get fields and settings defining this admission rule as array.
     */
    public function getPayload(): array
    {
        return array_merge(
            parent::getPayload(),
            [
                'courses' => array_map(
                    fn ($course) => ['id' => $course->id, 'name' => $course->getFullname()],
                    $this->getDecodedCourses()
                ),
                'modus' => $this->modus,
                'search' => (string) new SeminarSearch()
            ]
        );
    }

}
