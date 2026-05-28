<?php
/**
 * Course.php
 * model class for table seminare
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @copyright   2012 Stud.IP Core-Group
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 *
 * @property string $id alias column for seminar_id
 * @property string $seminar_id database column
 * @property string|null $veranstaltungsnummer database column
 * @property string $institut_id database column
 * @property I18NString $name database column
 * @property I18NString|null $untertitel database column
 * @property int $status database column
 * @property I18NString $beschreibung database column
 * @property I18NString|null $ort database column
 * @property string|null $sonstiges database column
 * @property int $lesezugriff database column
 * @property int $schreibzugriff database column
 * @property I18NString|null $art database column
 * @property I18NString|null $teilnehmer database column
 * @property I18NString|null $vorrausetzungen database column
 * @property I18NString|null $lernorga database column
 * @property I18NString|null $leistungsnachweis database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property string|null $ects database column
 * @property int|null $admission_turnout database column
 * @property int|null $admission_binding database column
 * @property int $admission_prelim database column
 * @property string|null $admission_prelim_txt database column
 * @property int $admission_disable_waitlist database column
 * @property int $visible database column
 * @property int|null $showscore database column
 * @property string|null $aux_lock_rule database column
 * @property int $aux_lock_rule_forced database column
 * @property string|null $lock_rule database column
 * @property int $admission_waitlist_max database column
 * @property int $admission_disable_waitlist_move database column
 * @property int $completion database column
 * @property string|null $parent_course database column
 * @property int|null $expires database column
 * @property SimpleORMapCollection<CourseTopic> $topics has_many CourseTopic
 * @property SimpleORMapCollection<CourseDate> $dates has_many CourseDate
 * @property SimpleORMapCollection<CourseExDate> $ex_dates has_many CourseExDate
 * @property SimpleORMapCollection<CourseMember> $members has_many CourseMember
 * @property SimpleORMapCollection<Deputy> $deputies has_many Deputy
 * @property SimpleORMapCollection<Statusgruppen> $statusgruppen has_many Statusgruppen
 * @property SimpleORMapCollection<AdmissionApplication> $admission_applicants has_many AdmissionApplication
 * @property SimpleORMapCollection<DatafieldEntryModel> $datafields has_many DatafieldEntryModel
 * @property SimpleORMapCollection<SeminarCycleDate> $cycles has_many SeminarCycleDate
 * @property SimpleORMapCollection<StudipScmEntry> $scm_entries has_many StudipScmEntry
 * @property SimpleORMapCollection<WikiPage> $wiki_pages has_many WikiPage
 * @property SimpleORMapCollection<StudipNews> $news has_many StudipNews
 * @property SimpleORMapCollection<BlubberThread> $blubberthreads has_many BlubberThread
 * @property SimpleORMapCollection<ConsultationBlock> $consultation_blocks has_many ConsultationBlock
 * @property SimpleORMapCollection<RoomRequest> $room_requests has_many RoomRequest
 * @property SimpleORMapCollection<ResourceBooking> $resource_bookings has_many ResourceBooking
 * @property SimpleORMapCollection<Course> $children has_many Course
 * @property SimpleORMapCollection<ToolActivation> $tools has_many ToolActivation
 * @property SimpleORMapCollection<CourseMemberNotification> $member_notifications has_many CourseMemberNotification
 * @property SimpleORMapCollection<ConfigValue> $config_values has_many ConfigValue
 * @property SimpleORMapCollection<Courseware\Unit> $courseware_units has_many Courseware\Unit
 * @property SimpleORMapCollection<StudygroupCourseProposal> $studygroup_proposals has_many StudygroupCourseProposal
 * @property SimpleORMapCollection<StudygroupCourseProposal> $course_proposals has_many StudygroupCourseProposal
 * @property SimpleORMapCollection<StudygroupInvitation> $studygroup_invitations has_many StudygroupInvitation
 * @property Institute $home_institut belongs_to Institute
 * @property AuxLockRule|null $aux belongs_to AuxLockRule
 * @property Course|null $parent belongs_to Course
 * @property SimpleORMapCollection<Semester> $semesters has_and_belongs_to_many Semester
 * @property SimpleORMapCollection<StudipStudyArea> $study_areas has_and_belongs_to_many StudipStudyArea
 * @property SimpleORMapCollection<Institute> $institutes has_and_belongs_to_many Institute
 * @property SimpleORMapCollection<UserDomain> $domains has_and_belongs_to_many UserDomain
 * @property SimpleORMapCollection<Course> $studygroups has_and_belongs_to_many Course
 * @property SimpleORMapCollection<Course> $connectedcourses has_and_belongs_to_many Course
 * @property SimpleORMapCollection<Tag> $tags has_and_belongs_to_many Tag
 * @property-read mixed $teachers additional field
 * @property Semester $start_semester additional field
 * @property (Semester | null) $end_semester additional field
 * @property-read mixed $semester_text additional field
 * @property-read mixed $config additional field
 */

class Course extends SimpleORMap implements Range, PrivacyObject, StudipItem, FeedbackRange, Studip\Calendar\Owner
{
    /**
     * @var Semester initial start semester.
     */
    protected $initial_start_semester;

    /**
     * @var Semester initial end semester.
     */
    protected $initial_end_semester;

    /**
     * @var array|null Currently assigned institutes, used for tracking changes
     */
    protected $currently_assigned_institutes = null;

    protected static function configure($config = [])
    {
        $config['db_table'] = 'seminare';
        $config['has_many']['topics'] = [
            'class_name' => CourseTopic::class,
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['dates'] = [
            'class_name'        => CourseDate::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
            'on_store'          => 'store',
            'order_by'          => 'ORDER BY date'
        ];
        $config['has_many']['ex_dates'] = [
            'class_name'        => CourseExDate::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
            'on_store'          => 'store',
        ];
        $config['has_many']['members'] = [
            'class_name' => CourseMember::class,
            'assoc_func' => 'findByCourse',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['deputies'] = [
            'class_name' => Deputy::class,
            'assoc_func' => 'findByRange_id',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['statusgruppen'] = [
            'class_name'        => Statusgruppen::class,
            'assoc_foreign_key' => 'range_id',
            'order_by'          => 'ORDER BY position',
            'on_delete'         => 'delete',
            'on_store'          => 'store',
        ];
        $config['has_many']['admission_applicants'] = [
            'class_name' => AdmissionApplication::class,
            'assoc_func' => 'findByCourse',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['datafields'] = [
            'class_name' => DatafieldEntryModel::class,
            'assoc_func' => 'findByModel',
            'assoc_foreign_key' => function ($model, $params) {
                $model->setValue('range_id', $params[0]->id);
            },
            'foreign_key' => function ($course) {
                return [$course];
            },
            'on_delete' => 'delete',
            'on_store'  => 'store',
        ];
        $config['has_many']['cycles'] = [
            'class_name' => SeminarCycleDate::class,
            'assoc_func' => 'findBySeminar',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['scm_entries'] = [
            'class_name'        => StudipScmEntry::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
            'on_store'          => 'store'
        ];
        $config['has_many']['wiki_pages'] = [
            'class_name'        => WikiPage::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
            'on_store'          => 'store'
        ];
        $config['has_and_belongs_to_many']['news'] = [
            'class_name'        => StudipNews::class,
            'thru_table'        => 'news_range',
            'thru_key'          => 'range_id',
            'thru_assoc_key'    => 'news_id',
        ];
        $config['has_many']['blubberthreads'] = [
            'class_name' => BlubberThread::class,
            'assoc_func' => 'findBySeminar',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['consultation_blocks'] = [
            'class_name'        => ConsultationBlock::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete',
        ];

        $config['has_and_belongs_to_many']['semesters'] = [
            'class_name'     => Semester::class,
            'thru_table'     => 'semester_courses',
            'thru_key'       => 'course_id',
            'thru_assoc_key' => 'semester_id',
            'order_by'       => 'ORDER BY beginn ASC',
            'on_delete'      => 'delete',
            'on_store'       => 'store',
        ];

        $config['belongs_to']['home_institut'] = [
            'class_name' => Institute::class,
            'foreign_key' => 'institut_id',
            'assoc_func'  => 'find',
        ];
        $config['belongs_to']['aux'] = [
            'class_name' => AuxLockRule::class,
            'foreign_key' => 'aux_lock_rule',
        ];
        $config['has_and_belongs_to_many']['study_areas'] = [
            'class_name' => StudipStudyArea::class,
            'thru_table' => 'seminar_sem_tree',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_and_belongs_to_many']['institutes'] = [
            'class_name'     => Institute::class,
            'thru_table'     => 'seminar_inst',
            'thru_key'       => 'seminar_id',
            'thru_assoc_key' => 'institut_id',
            'on_delete'      => 'delete',
            'on_store'       => 'store',
        ];

        $config['has_and_belongs_to_many']['domains'] = [
            'class_name'        => UserDomain::class,
            'thru_table'        => 'seminar_userdomains',
            'on_delete'          => 'delete',
            'on_store'           => 'store',
            'order_by'          => 'ORDER BY name',
        ];

        $config['has_many']['room_requests'] = [
            'class_name'        => RoomRequest::class,
            'assoc_foreign_key' => 'course_id',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['resource_bookings'] = [
            'class_name'        => ResourceBooking::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'         => 'delete'
        ];
        $config['belongs_to']['parent'] = [
            'class_name' => Course::class,
            'foreign_key' => 'parent_course'
        ];
        $config['has_many']['children'] = [
            'class_name'        => Course::class,
            'assoc_foreign_key' => 'parent_course',
            'order_by'          => 'GROUP BY seminar_id ORDER BY VeranstaltungsNummer, Name'
        ];
        $config['has_and_belongs_to_many']['studygroups'] = [
            'class_name'        => Course::class,
            'thru_table'        => 'studygroup_courses',
            'thru_key'          => 'course_id',
            'thru_assoc_key'    => 'studygroup_id',
            'order_by'          => 'ORDER BY VeranstaltungsNummer, Name'
        ];
        $config['has_and_belongs_to_many']['connectedcourses'] = [
            'class_name'        => Course::class,
            'thru_table'        => 'studygroup_courses',
            'thru_key'          => 'studygroup_id',
            'thru_assoc_key'    => 'course_id',
            'order_by'          => 'ORDER BY VeranstaltungsNummer, Name'
        ];
        $config['has_many']['tools'] = [
            'class_name'        => ToolActivation::class,
            'assoc_foreign_key' => 'range_id',
            'order_by'          => 'ORDER BY position',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['member_notifications'] = [
            'class_name'        => CourseMemberNotification::class,
            'on_delete'         => 'delete',
        ];

        $config['has_many']['config_values'] = [
            'class_name'        => ConfigValue::class,
            'assoc_foreign_key' => 'range_id',
            'on_store'          => 'store',
            'on_delete'         => 'delete'
        ];

        $config['has_many']['courseware_units'] = [
            'class_name' => \Courseware\Unit::class,
            'assoc_foreign_key' => 'range_id',
            'on_delete'  => 'delete',
        ];
        $config['has_and_belongs_to_many']['tags'] = [
            'class_name' => Tag::class,
            'thru_table' => 'tags_relations',
            'thru_key' => 'range_id',
            'thru_assoc_key' => 'tag_id',
            'order_by'    => 'ORDER BY `name` ASC',
            'on_delete'  => 'delete',
            'on_store'   => 'store',
        ];
        $config['has_many']['studygroup_proposals'] = [
            'class_name'        => StudygroupCourseProposal::class,
            'assoc_foreign_key' => 'studygroup_id',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['course_proposals'] = [
            'class_name'        => StudygroupCourseProposal::class,
            'assoc_foreign_key' => 'course_id',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['studygroup_invitations'] = [
            'class_name' => StudygroupInvitation::class,
            'assoc_foreign_key' => 'sem_id',
            'on_delete'  => 'delete',
        ];
        $config['has_and_belongs_to_many']['lvgruppen'] = [
            'class_name' => Lvgruppe::class,
            'thru_table' => 'mvv_lvgruppe_seminar',
            'on_delete'  => 'delete',
        ];

        $config['default_values']['lesezugriff'] = 1;
        $config['default_values']['schreibzugriff'] = 1;

        $config['additional_fields']['teachers'] = [
            'get' => 'getTeachers'
        ];

        $config['additional_fields']['start_semester'] = [
            'get' => 'getStartSemester',
            'set' => '_set_semester'
        ];
        $config['additional_fields']['end_semester'] = [
            'get' => 'getEndSemester',
            'set' => '_set_semester'
        ];
        $config['additional_fields']['semester_text'] = [
            'get' => 'getTextualSemester'
        ];

        $config['additional_fields']['config'] = [
            'get' => function (Course $course) {
                return $course->getConfiguration();
            }
        ];

        $config['notification_map']['after_create'] = 'CourseDidCreateOrUpdate';
        $config['notification_map']['after_store'] = 'CourseDidCreateOrUpdate';

        $config['i18n_fields']['name'] = true;
        $config['i18n_fields']['untertitel'] = true;
        $config['i18n_fields']['beschreibung'] = true;
        $config['i18n_fields']['art'] = true;
        $config['i18n_fields']['teilnehmer'] = true;
        $config['i18n_fields']['vorrausetzungen'] = true;
        $config['i18n_fields']['lernorga'] = true;
        $config['i18n_fields']['leistungsnachweis'] = true;
        $config['i18n_fields']['ort'] = true;

        $config['registered_callbacks']['before_store'][] = 'logStore';
        $config['registered_callbacks']['before_store'][] = 'handleInstitutes';
        $config['registered_callbacks']['after_create'][] = 'setDefaultTools';
        $config['registered_callbacks']['after_delete'][] = function (Course $course) {
            CourseAvatar::getAvatar($course->id)->reset();
            FeedbackElement::deleteBySQL('course_id = ?', [$course->id]);
            // Remove subcourse relations, leaving subcourses intact.
            DBManager::get()->execute(
                "UPDATE `seminare` SET `parent_course` = NULL WHERE `parent_course` = :course",
                ['course' => $course->id]
            );

            //Delete forum contents:
            CoreForum::deleteCourseContents($course->id);

            //Delete all files:
            $folder = Folder::findTopFolder($course->id);
            if ($folder) {
                $folder->delete();
            }

            //Unlink all news and delete them in RSS feeds:
            StudipNews::DeleteNewsRanges($course->id);
            StudipNews::UnsetRssId($course->id);

            //Remove all entries of the course in calendars:
            $query = 'DELETE FROM `schedule_courses` WHERE `course_id` = ?';
            $statement = DBManager::get()->execute($query, [$course->id]);

            //Remove all entries in object_user_vists for the course:
            object_kill_visits(null, $course->id);

            //Remove deputies:
            Deputy::deleteByRange_id($course->id);

            //Remove user domains:
            UserDomain::removeUserDomainsForSeminar($course->id);

            //Remove auto-insert entries:
            AutoInsert::deleteSeminar($course->id);

            //Remove assignments to admission sets:
            $cs = $course->getCourseSet();
            if ($cs) {
                CourseSet::removeCourseFromSet($cs->getId(), $course->id);
                $cs->load();
                if (!count($cs->getCourses()) && $cs->isGlobal() && $cs->getUserid() != '') {
                    $cs->delete();
                }
            }
            AdmissionPriority::unsetAllPrioritiesForCourse($course->id);

            //Create a log entry:
            StudipLog::log('SEM_ARCHIVE', $course->id, NULL, $course->getFullName('number-name-semester'));
        };

        parent::configure($config);
    }

    /**
     * @param string $relation
     */
    public function initRelation($relation): void
    {
        if ($relation === 'semesters' && $this->relations[$relation] === null) {
            parent::initRelation($relation);
            $this->initial_start_semester = $this->getStartSemester();
            $this->initial_end_semester = $this->getEndSemester();
        } elseif ($relation === 'institutes' && $this->currently_assigned_institutes === null) {
            parent::initRelation($relation);
            $this->currently_assigned_institutes = array_filter(
                $this->relations['institutes']->pluck('id'),
                fn($inst_id) => $inst_id !== $this->getPristineValue('institut_id')
            );
        }
        parent::initRelation($relation);
    }

    /**
     * Override of SimpleORMap::cbAfterInitialize for resetting the flags that indicate semester changes.
     *
     * @see SimpleORMap::cbAfterInitialize
     */
    protected function cbAfterInitialize($cb_type): void
    {
        parent::cbAfterInitialize($cb_type);
        //Reset the flags for the start and end semester:
        $this->initial_start_semester = null;
        $this->initial_end_semester = null;
        $this->currently_assigned_institutes = null;
    }

    /**
     * Returns the currently active course or false if none is active.
     *
     * @return Course object of currently active course, null otherwise
     * @since 3.0
     */
    public static function findCurrent()
    {
        if (Context::isCourse()) {
            return Context::get();
        }

        return null;
    }

    /**
     * Returns the associated mvv modules for a given course id.
     *
     * @param string     $course_id
     * @param array|null $statusses Limit the results by a given module status
     * @return Modul[]
     */
    public static function getMVVModulesForCourseId(string $course_id, ?array $statusses = null): array
    {
        $query = "SELECT mvv_modul.*
                  FROM mvv_lvgruppe_seminar
                  JOIN `mvv_lvgruppe` ON (`mvv_lvgruppe_seminar`.`lvgruppe_id` = `mvv_lvgruppe`.`lvgruppe_id`)
                  JOIN `mvv_lvgruppe_modulteil` ON (`mvv_lvgruppe_seminar`.`lvgruppe_id` = `mvv_lvgruppe_modulteil`.`lvgruppe_id`)
                  JOIN `mvv_modulteil` ON (`mvv_lvgruppe_modulteil`.`modulteil_id` = `mvv_modulteil`.`modulteil_id`)
                  JOIN `mvv_modul` ON (`mvv_modulteil`.`modul_id` = `mvv_modul`.`modul_id`)
                  WHERE seminar_id = ?";
        $parameters = [$course_id];

        if ($statusses !== null) {
            $query .= ' AND `mvv_modul`.`stat` IN (?)';
            $parameters[] = $statusses;
        }

        return DBManager::get()->fetchAll($query, $parameters, function ($row) {
            return Modul::buildExisting($row);
        });
    }

    public function _set_semester($field, $value)
    {
        $method = 'set' . ($field === 'start_semester' ? 'StartSemester' : 'EndSemester');
        $this->$method($value);
    }

    /**
     * @param Semester $semester
     */
    public function setStartSemester(Semester $semester)
    {
        $end_semester = $this->semesters->last();
        $start_semester = $this->semesters->first();
        if ($start_semester && $start_semester->id === $semester->id) {
            return;
        }
        if ($end_semester) {
            if (count($this->semesters) > 1 && $end_semester->beginn < $semester->beginn) {
                throw new InvalidArgumentException('start-semester must start before end-semester');
            }
            foreach ($this->semesters as $key => $one_semester) {
                if ($one_semester->beginn < $semester->beginn) {
                    $this->semesters->offsetUnset($key);
                }
            }
        }
        $this->semesters[] = $semester;
        $this->semesters->orderBy('beginn asc');
        //add possibly missing semesters between start_semester and end_semester
        if (count($this->semesters) > 1 && $semester->beginn < $start_semester->beginn) {
            $this->setEndSemester($end_semester);
        }
    }

    /**
     * @param Semester|null $semester
     */
    public function setEndSemester(?Semester $semester)
    {
        $end_semester = $this->semesters->last();
        $start_semester = $this->semesters->first();
        if (
            (is_null($end_semester) && is_null($semester))
            || ($end_semester && $semester && $end_semester->id === $semester->id)) {
            return;
        }
        if ($start_semester) {
            if ($semester && $start_semester->beginn > $semester->beginn) {
                throw new InvalidArgumentException('end-semester must start after start-semester');
            }
            $this->semesters = [];
            if ($semester) {
                $all_semester = SimpleCollection::createFromArray(Semester::getAll());
                $this->semesters = $all_semester->findBy('beginn', [$start_semester->beginn, $semester->beginn], '>=<=');
            }
        } else {
            if ($semester) {
                $this->semesters[] = $semester;
            }
        }
    }

    /**
     * Retrieves the first semester of a course, if applicable.
     *
     * @returns Semester|null Either the first semester of the course
     *     or null, if no semester could be found.
     */
    public function getStartSemester()
    {
        //this is called by __get() and therefore using magic properties is not always safe
        if ($this->relations['semesters'] === null) {
            $this->initRelation('semesters');
        }
        return $this->relations['semesters']->first() ?? Semester::findCurrent();
    }

    /**
     * Retrieves the last semester of a course, if applicable.
     *
     * @returns Semester|null Either the last semester of the course
     *     or null, if no semester could be found.
     */
    public function getEndSemester(): ?Semester
    {
        //this is called by __get() and therefore using magic properties is not always safe
        if ($this->relations['semesters'] === null) {
            $this->initRelation('semesters');
        }
        return $this->relations['semesters']->last();
    }

    /**
     * Returns the readable semester duration as as string
     * @return string : readable semester
     */
    public function getTextualSemester()
    {
        if (count($this->semesters) > 1) {
            return $this->start_semester->short_name . ' - ' . $this->end_semester->short_name;
        } elseif (count($this->semesters) === 1) {
            return $this->start_semester->short_name;
        } else {
            return _('unbegrenzt');
        }
    }

    /**
     * Returns true if this course has no end-semester. Else false.
     * @return bool : true if there is no end-semester
     */
    public function isOpenEnded()
    {
        return count($this->semesters) === 0;
    }

    /**
     * Returns if this course is in the given semester
     * @param Semester $semester : instance of the given semester
     * @return bool : true if this course is part of this semester
     */
    public function isInSemester(Semester $semester)
    {
        if (count($this->semesters) > 0) {
            foreach ($this->semesters as $s) {
                if ($s->id === $semester->id) {
                    return true;
                }
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * Returns whether the course has ended, meaning it is not open ended and
     * all semesters the course takes place in are in the past.
     */
    public function hasEnded(): bool
    {
        return !$this->isOpenEnded()
            && $this->getEndSemester()->isPast();
    }

    public function getTeachers()
    {
        return $this->members->filter(function ($m) {
            return $m['status'] === 'dozent';
        });
    }

    public function getFreeSeats() : int
    {
        $free_seats = $this->admission_turnout - $this->getNumParticipants();
        return max($free_seats, 0);
    }

    public function isWaitlistAvailable()
    {
        if ($this->admission_disable_waitlist) {
            return false;
        }

        if ($this->admission_waitlist_max) {
            return $this->admission_waitlist_max - $this->getNumWaiting() > 0;
        }

        return true;
    }

    /**
     * Determines whether the course has at least one course set attached to it.
     *
     * @return bool True, if the course has at least one course set, false otherwise.
     */
    public function hasCourseSet() : bool
    {
        return $this->getCourseSet() !== null;
    }

    /**
     * Retrieves the course set of th course, if the course is associated to a course set.
     *
     * @return CourseSet|null The course set of the course, if it is associated to one.
     */
    public function getCourseSet() : ?CourseSet
    {
        return CourseSet::getSetForCourse($this->id);
    }

    /**
     * Determines whether the number of participants in this course is limited
     * by a course set whose seat distribution is enabled.
     *
     * @return boolean True, if a course set exists and its seat distribution is enabled,
     *     false otherwise.
     */
    public function isAdmissionEnabled() : bool
    {
        $cs = $this->getCourseSet();
        return $cs && $cs->isSeatDistributionEnabled();
    }

    /**
     * Determines by the course set of the course (if any), whether the admission
     * is locked or not.
     *
     * @return bool True, if the admission is locked, false otherwise.
     */
    public function isAdmissionLocked() : bool
    {
        $cs = $this->getCourseSet();
        return $cs && $cs->hasAdmissionRule('LockedAdmission');
    }

    /**
     * Determines by looking at the course set (if any), whether the course
     * is password protected or not.
     *
     * @return bool True, fi the course is password protected, false otherwise.
     */
    public function isPasswordProtected() : bool
    {
        $cs = $this->getCourseSet();
        return $cs && $cs->hasAdmissionRule('PasswordAdmission');
    }

    /**
     * Determines if there is an admission time frame for this course by looking
     * at the course set (if any). If such a time frame exists, it is returned
     * as an associative array with the start and end timestamp.
     *
     * @returns array An associative array with the array keys "start_time" and "end_time"
     *     containing the start and end timestamp of the admission. In case no such time
     *     frame exists, an empty array is returned instead.
     */
    public function getAdmissionTimeFrame() : array
    {
        $cs = $this->getCourseSet();
        if ($cs && $cs->hasAdmissionRule(TimedAdmission::class)) {
            $rule = $cs->getAdmissionRule(TimedAdmission::class);
            return [
                'start_time' => $rule->getStartTime(),
                'end_time'   => $rule->getEndTime()
            ];
        }
        return [];
    }

    /**
     * Adds a user as preliminary member to this course.
     *
     * @param User $user The user to be added as preliminary member.
     * @param string $comment An optional comment for the preliminary membership.
     *
     * @return AdmissionApplication The AdmissionApplication object for the preliminary membership.
     *
     * @throws \Studip\Exception In case the user cannot be added as preliminary member.
     */
    public function addPreliminaryMember(User $user, string $comment = '') : AdmissionApplication
    {
        $new_admission_member = new AdmissionApplication();
        $new_admission_member->user_id = $user->id;
        $new_admission_member->position = 0;
        $new_admission_member->status = 'accepted';
        $new_admission_member->comment = $comment;

        $this->admission_applicants[] = $new_admission_member;
        if (!$new_admission_member->store()) {
            throw new \Studip\Exception(
                sprintf(
                    _('%1$s konnte nicht als vorläufig teilnehmende Person zur Veranstaltung %2$s hinzugefügt werden.'),
                    $user->getFullName(),
                    $this->name
                )
            );
        }
        if ($this->isStudygroup()) {
            StudygroupModel::applicationNotice($this->id, $user->id);
        }
        $course_set = $this->getCourseSet();
        if ($course_set) {
            AdmissionPriority::unsetPriority($course_set->getId(), $user->id, $this->id);
        }

        //Create a log entry:
        StudipLog::log('SEM_USER_ADD', $this->id, $user->id, 'accepted', 'Vorläufig akzeptiert');

        return $new_admission_member;
    }

    /**
     * Removes a preliminary member from the course.
     *
     * @param User $user The member to be removed.
     *
     * @throws \Studip\Exception In case the user is not a preliminary member or in case they
     *     cannot be removed as preliminary member.
     */
    public function removePreliminaryMember(User $user) : void
    {
        //Get the status of the user first:
        $application = AdmissionApplication::findOneBySQL(
            'seminar_id = :course_id AND user_id = :user_id',
            [
                'course_id' => $this->id,
                'user_id'   => $user->id
            ]
        );

        $deleted_from_course_set = false;
        $course_set = $this->getCourseSet();
        if ($course_set) {
            $deleted_from_course_set = AdmissionPriority::unsetPriority(
                $course_set->getId(),
                $user->id,
                $this->id
            );
        }
        if (($application && $application->delete()) || $deleted_from_course_set) {
            setTempLanguage($user->id);
            $message = '';
            if ($application->status === 'accepted') {
                $message = studip_interpolate(
                    _('Ihre vorläufige Anmeldung zur Veranstaltung %{name} wurde aufgehoben. Sie sind damit __nicht__ zugelassen worden.'),
                    ['name' => $this->getFullName()]
                );
            } else {
                $message = studip_interpolate(
                    _('Sie wurden von der Warteliste der Veranstaltung %{name} gestrichen. Sie sind damit __nicht__ zugelassen worden.'),
                    ['name' => $this->getFullName()]
                );
            }
            $messaging = new messaging();
            $messaging->insert_message(
                $message,
                $user->username,
                '____%system%____',
                false,
                false,
                '1',
                false,
                studip_interpolate(
                    _('%{course_name}: Sie wurden nicht zugelassen!'),
                    ['course_name' => $this->getFullName()]
                ),
                true
            );
            restoreLanguage();
            StudipLog::log('SEM_USER_DEL', $this->id, $user->id, 'Wurde aus der Veranstaltung entfernt');
        } else {
            throw new \Studip\Exception(
                sprintf(
                    _('%1$s konnte nicht als vorläufig teilnehmende Person aus der Veranstaltung %2$s entfernt werden.'),
                    $user->getFullName(),
                    $this->name
                )
            );
        }
    }

    /**
     * Adds a user to the waitlist of this course.
     *
     * @param User $user The user to be added onto the waitlist.
     *
     * @param int $position The position of the user on the waitlist.
     *
     * @param bool $send_mail Whether to send a mail to the user that has been added
     *     (true) or not (false). Defaults to true.
     *
     * @return AdmissionApplication The AdmissionApplication object for the added user.
     *
     * @throws \Studip\Exception In case the user cannot be added onto the waitlist.
     */
    public function addMemberToWaitlist(
        User $user,
        int $position = PHP_INT_MAX,
        bool $send_mail = true
    ) : AdmissionApplication
    {
        $member_exists = AdmissionApplication::exists([$user->id, $this->id])
            || CourseMember::find([$this->id, $user->id]);
        if ($member_exists) {
            throw new \Studip\EnrolmentException(
                sprintf(
                    _('%1$s ist bereits Mitglied der Veranstaltung %2$s.'),
                    $user->getFullName(),
                    $this->name
                ),
                \Studip\EnrolmentException::ALREADY_MEMBER
            );
        }
        if ($position === PHP_INT_MAX) {
            //Append the user to the end of the waitlist.
            //NOTE: If this method is called two times at the same time for the
            //same course, there may be course members with the same position!
            $position = DBManager::get()->fetchColumn(
                "SELECT MAX(`position`)
                 FROM `admission_seminar_user`
                 WHERE `seminar_id` = :course_id
                   AND `status`='awaiting'",
                ['course_id' => $this->id]
            );
            if ($position === false) {
                //No members on the waitlist.
                $position = 0;
            }
        }
        // New entry will be added after current max position.
        $position++;

        $new_admission_member = new AdmissionApplication();
        $new_admission_member->user_id = $user->id;
        $new_admission_member->position = strval($position);
        $new_admission_member->status = 'awaiting';
        $new_admission_member->seminar_id = $this->id;
        if (!$new_admission_member->store()) {
            throw new \Studip\EnrolmentException(
                sprintf(
                    _('%1$s konnte nicht auf die Warteliste der Veranstaltung %2$s gesetzt werden.'),
                    $user->getFullName(),
                    $this->name
                ),
                \Studip\EnrolmentException::ADD_AWAITING_FAILED
            );
        }

        //Reset the admission_applicants relation:
        $this->resetRelation('admission_applicants');

        //Renumber all members on the waitlist:
        AdmissionApplication::renumberAdmission($this->id);

        //Create a log entry:
        StudipLog::log(
            'SEM_USER_ADD',
            $this->id,
            $user->id,
            'awaiting',
            sprintf('Auf Warteliste gesetzt, Position: %u', $position)
        );

        if ($send_mail) {
            setTempLanguage($user->id);
            $body = sprintf(
                _('Sie wurden auf die Warteliste der Veranstaltung %s gesetzt.'),
                $this->getFullName()
            );
            $messaging = new messaging();
            $messaging->insert_message(
                $body,
                $user->username,
                '____%system%____',
                false,
                false,
                '1',
                false,
                _('Auf die Warteliste einer Veranstaltung eingetragen'),
                true
            );
            restoreLanguage();
        }

        //Everything went fine: Re-load the new admission member before returning it,
        //since its position number may have changed during renumbering:
        return AdmissionApplication::findOneBySQL(
            '`user_id` = :user_id AND `seminar_id` = :course_id',
            ['user_id' => $user->id, 'course_id' => $this->id]
        );
    }

    /**
     * Retrieves the course category for this course.
     *
     * @return SeminarCategories The category object of the course.
     */
    public function getCourseCategory() : SeminarCategories
    {
        return SeminarCategories::GetByTypeId($this->status);
    }

    /**
     * Retrieves all members of a status
     *
     * @param String|Array $status        the status to filter with
     * @param bool         $as_collection return collection instead of array?
     * @return CourseMember[]|SimpleCollection an array of all those members.
     */
    public function getMembersWithStatus($status, $as_collection = false)
    {
        $result = CourseMember::findByCourseAndStatus($this->id, $status);
        return $as_collection
             ? SimpleCollection::createFromArray($result)
             : $result;
    }

    /**
     * Retrieves the number of all members of a status
     *
     * @param String|Array $status  the status to filter with
     *
     * @return int the number of all those members.
     */
    public function countMembersWithStatus($status)
    {
        return CourseMember::countByCourseAndStatus($this->id, $status);
    }

    /**
     * Adds a user to this course.
     *
     * @param User $user The user to be added.
     * @param string $permission_level The permission level the user shall get in the course.
     * @param bool $regard_contingent Whether to regard the contingent of the course (true)
     *     or whether to ignore it (false). Defaults to true.
     * @param bool $send_mail Whether to send a mail to the new participant (true) or not (false).
     *     Defaults to true.
     * @param bool $renumber_admission Whether to call AdmissionApplication::renumberAdmission when
     *     the admission of the user has been removed (true) or whether not to renumber the admission
     *     entries (false). Defaults to true.
     *     Setting this parameter to false is useful when adding several users at once and then
     *     manually call AdmissionApplication::renumberAdmission so that the entries are renumbered
     *     only once after all the users have been added.
     *
     * @return CourseMember The CourseMember object for the user.
     *
     * @throws \Studip\EnrolmentException In case the user is already in the course but cannot get a higher permission level or
     *     they are the only lecturer and can therefore not get a lower permission level.
     */
    public function addMember(
        User $user,
        string $permission_level = 'autor',
        bool $regard_contingent = true,
        bool $send_mail = true,
        bool $renumber_admission = true
    ) : CourseMember
    {
        //TODO: Put checks for entry into Course::getEnrolmentInformation.
        //Checks regarding the promotion/demotion of users in courses shall be
        //transferred to a new method.

        if (!in_array($permission_level, ['user', 'autor', 'tutor', 'dozent'])) {
            throw new \Studip\EnrolmentException(
                _('Die Rechtestufe ist für die Eintragung in eine Veranstaltung unpassend.'),
                \Studip\EnrolmentException::INVALID_PERMISSION_LEVEL
            );
        }

        $db = DBManager::get();

        if (!in_array($permission_level, ['user', 'autor'])) {
            //The user shall be added with "tutor" or "dozent" permissions.
            //In case the course only allows users of the institute to be members,
            //we must check if the user is a member of the institute:
            $course_category = $this->getCourseCategory();
            if ($course_category->only_inst_user) {
                //Only institute members are allowed:
                $stmt = $db->prepare(
                    "SELECT 1
                    FROM `user_inst`
                    JOIN `seminar_inst` USING (`institut_id`)
                    WHERE `user_inst`.`user_id` = :user_id
                    AND `seminar_inst`.`seminar_id` = :course_id"
                );
                $stmt->execute([
                    'course_id' => $this->id,
                    'user_id' => $user->id,
                ]);
                $user_in_institute = $stmt->fetchColumn();
                if (!$user_in_institute) {
                    throw new \Studip\EnrolmentException(
                        sprintf(
                            _('Die einzutragende Person hat die Rechtestufe "%s", ist aber kein Mitglied einer Einrichtung, zu der die Veranstaltung zugeordnet ist.'),
                            $permission_level
                        ),
                        \Studip\EnrolmentException::NO_INSTITUTE_MEMBER
                    );
                }
            }
        }

        //Load the course member object:
        $course_member = CourseMember::findOneBySQL(
            '`seminar_id` = :course_id AND `user_id` = :user_id',
            ['course_id' => $this->id, 'user_id' => $user->id]
        );

        if ($course_member && $course_member->status === $permission_level) {
            return $course_member;
        }

        $new_member_position = $db->fetchColumn(
            'SELECT MAX(`position`) + 1
             FROM `seminar_user`
             WHERE `status` = :status
               AND `seminar_id` = :course_id',
            ['status' => $permission_level, 'course_id' => $this->id]
        ) ?? 0;
        $number_of_lecturers = CourseMember::countByCourseAndStatus($this->id, 'dozent');

        if (!$course_member) {
            $course_member = new CourseMember();
            $course_member->seminar_id = $this->id;
            $course_member->user_id    = $user->id;
            $course_member->status     = $permission_level;
        }
        $course_member->position = $new_member_position;
        if (in_array($permission_level, ['tutor', 'dozent'])) {
            //Tutors and lecturers are always visible in the course:
            $course_member->visible = 'yes';
        } else {
            //All others may decide for themselves:
            $course_member->visible = 'unknown';
        }

        $ranks = array_flip(['user', 'autor', 'tutor', 'dozent']);

        if ($course_member->isNew()) {
            //The user shall be added to the course. Before storing, we must check
            //if the contingent shall be regarded and if there is a free seat
            //for the user:

            //TODO: Move the following check back to controllers.
            //Background: Lecturers may enforce the entry of a student, but the latter must not
            //override the checks.
            if (
                !$GLOBALS['perm']->have_studip_perm('tutor', $this->id)
                && $permission_level === 'autor'
                && $regard_contingent
                && $this->isAdmissionEnabled()
                && $this->getFreeSeats() < 1
            ) {
                //There is no free seat to add another member.
                throw new \Studip\EnrolmentException(
                    sprintf(
                        _('Für %s ist kein Platz mehr in der Veranstaltung frei.'),
                        $user->getFullName()
                    ),
                    \Studip\EnrolmentException::COURSE_IS_FULL
                );
            }

            $course_member->store();

            //Delete the user from admission applications:
            $application_removed = AdmissionApplication::deleteBySQL(
                '`user_id` = :user_id AND `seminar_id` = :course_id',
                ['user_id' => $user->id, 'course_id' => $this->id]
            );
            if ($application_removed && $renumber_admission) {
                //Renumber the waitlist or the other admission list:
                AdmissionApplication::renumberAdmission($this->id);
            }

            //Remove the user from the course set, if any:
            $course_set = $this->getCourseSet();
            $removed_from_course_set = 0;
            if ($course_set) {
                $removed_from_course_set = AdmissionPriority::unsetPriority($course_set->getId(), $user->id, $this->id);
            }

            if ($permission_level === 'dozent' && Config::get()->DEPUTIES_ENABLE) {
                //Delete a possible deputy entry for the lecturer:
                $deputy = Deputy::find([$this->id, $user->id]);
                if ($deputy) {
                    $deputy->delete();
                }

                //Assign all default deputies of the lecturer to the course
                //if they are not already a lecturer of the course:
                $unassigned_deputies = Deputy::findBySQL(
                    "`range_id` = :lecturer_id
                      AND `user_id` NOT IN (
                         SELECT `user_id` FROM `seminar_user`
                         WHERE `seminar_id` = :course_id
                         AND `status` = 'dozent'
                      )",
                    [
                        'lecturer_id' => $user->id,
                        'course_id'   => $this->id
                    ]
                );
                foreach ($unassigned_deputies as $deputy) {
                    Deputy::addDeputy($deputy->user_id, $this->id);
                }
            }

            //Delete course entries in the schedule:
            ScheduleCourseDate::deleteBySQL(
                'user_id = :user_id AND course_id = :course_id',
                [
                    'user_id'   => $user->id,
                    'course_id' => $this->id
                ]
            );

            //Log the event:
            StudipLog::log('SEM_USER_ADD', $this->id, $user->id, $permission_level, 'Wurde in die Veranstaltung eingetragen');

            if ($this->parent instanceof Course) {
                $this->parent->addMember($user, $permission_level, false);
            }

            if ($send_mail) {
                setTempLanguage($user->id);
                $body = '';
                $subject = '';
                if ($application_removed) {
                    //Enrolment after being on the wait list:
                    $subject = _('Zulassung zur Veranstaltung');
                    $body = sprintf(
                        _('Sie wurden für die Veranstaltung %s zugelassen. Ihr Eintrag auf der Warteliste wurde daher entfernt.'),
                        $this->getFullName()
                    );
                } elseif ($removed_from_course_set) {
                    //Enrolment after being in a course set:
                    $subject = _('Zulassung zur Veranstaltung');
                    $body = sprintf(
                        _('Sie wurden für die Veranstaltung %s endgültig zugelassen.'),
                        $this->getFullName()
                    );
                } else {
                    //Direct enrolment without waitlist or course set:
                    $subject = _('Eintragung in Veranstaltung');
                    $body = sprintf(
                        _('Sie wurden in die Veranstaltung %s eingetragen.'),
                        $this->getFullName()
                    );
                }
                $messaging = new messaging();
                $messaging->insert_message(
                    $body,
                    $user->username,
                    '____%system%____',
                    false,
                    false,
                    '1',
                    false,
                    $subject,
                    true
                );
                restoreLanguage();
            }
        } elseif ($ranks[$course_member->status] < $ranks[$permission_level]
            && $course_member->status !== 'dozent' || $number_of_lecturers > 1) {
            //The user is already a member of the course. They shall either be promoted
            //or they are not a lecturer or there is more than one lecturer in the course
            //(please read this multiple times in case you are unsure about these conditions).

            $course_member->status = $permission_level;
            $course_member->position = $new_member_position;

            $success = !$course_member->isDirty() || $course_member->store();

            if (!$success) {
                throw new \Studip\EnrolmentException(
                    _('Die Person kann nicht hochgestuft werden.'),
                    \Studip\EnrolmentException::PROMOTION_NOT_POSSIBLE
                );
            }
        } elseif ($course_member->status === 'dozent' && $number_of_lecturers <= 1) {
            throw new \Studip\EnrolmentException(
                sprintf(
                    _('Die Person kann nicht herabgestuft werden, da mindestens eine lehrende Person (%1$s) in die Veranstaltung eingetragen sein muss! Tragen Sie deshalb zuerst eine weitere Person als lehrende Person (%1$s) ein und versuchen Sie es dann erneut!'),
                    get_title_for_status('dozent', 1, $this->status)
                ),
                \Studip\EnrolmentException::DEMOTION_NOT_POSSIBLE
            );
        }
        $this->resetRelation('members');

        return $course_member;
    }

    /**
     * Removes a user from this course.
     *
     * @param User $user The user to be removed.
     * @param bool $send_mail Whether to send a mail after the membership deletion
     *     (true) or not (false). Defaults to false.
     *
     * @return void If this method does not throw, everything went fine.
     *
     * @throws \Studip\MembershipException If the user cannot be removed from the course.
     */
    public function deleteMember(User $user, bool $send_mail = false) : void
    {
        $membership = CourseMember::find([$this->id, $user->id]);
        if (!$membership) {
            //The user is not a member of the course.
            throw new \Studip\MembershipException(
                sprintf(
                    _('%1$s ist kein Mitglied der Veranstaltung %2$s.'),
                    $user->getFullName(),
                    $this->name
                ),
                \Studip\MembershipException::NOT_A_MEMBER,
                $user
            );
        }

        if ($membership->status === 'dozent') {
            //Check if there are enough lecturers left:
            $lecturer_amount = CourseMember::countByCourseAndStatus($this->id, 'dozent');
            if ($lecturer_amount < 2) {
                //Not enough lecturers left.
                throw new \Studip\MembershipException(
                    sprintf(
                        _('In die Veranstaltung muss mindestens eine lehrende Person (%s) eingetragen sein. Um diese Person aus der Veranstaltung zu entfernen, muss zunächst eine weitere lehrende Person eingetragen werden.'),
                        get_title_for_status('dozent', 1, $this->status)
                    ),
                    \Studip\MembershipException::USER_IS_SOLE_LECTURER,
                    $user
                );
            }
        }

        //At this point, the user may be removed.
        $success = $membership->delete();
        if (!$success) {
            throw new \Studip\MembershipException(
                sprintf(
                    _('Es trat ein Fehler auf beim Austragen von %1$s aus der Veranstaltung %2$s.'),
                    $user->getFullName(),
                    $this->getFullname()
                ),
                \Studip\MembershipException::REMOVAL_FAILED,
                $user
            );
        }

        if ($this->parent_course) {
            //This course has a parent course.
            //Delete the user from the parent course if they are not part of
            //one of the other child courses.
            $other_memberships = CourseMember::countBySql(
                'JOIN `seminare` USING (`seminar_id`)
                 WHERE `user_id` = :user_id
                   AND `parent_course` = :parent_course_id
                   AND `seminar_id` <> :this_course_id',
                [
                    'user_id'          => $user->id,
                    'parent_course_id' => $this->parent->id,
                    'this_course_id'   => $this->id
                ]
            );
            if ($other_memberships === 0) {
                //No other memberships. We can delete the user from the parent course.
                $this->parent->deleteMember($user);
            }
        }

        if ($this->children) {
            //The other way around: This course has child courses and because the user
            //has been removed from this course, they shall also be removed from all
            //child courses.
            foreach ($this->children as $child) {
                $child->deleteMember($user);
            }
        }

        if ($send_mail) {
            $messaging = new messaging();
            setTempLanguage($user->id);
            $subject = sprintf(_('%s: Anmeldung aufgehoben'), $this->getFullName());
            $body = sprintf(_('Ihre Anmeldung für die Veranstaltung %s wurde aufgehoben.'), $this->getFullName());
            $messaging->insert_message(
                $body,
                $user->username,
                '____%system%____',
                false,
                false,
                '1',
                false,
                $subject,
                true
            );
            restoreLanguage();
        }

        if ($membership->status === 'dozent') {
            //Special treatment for lecturers:
            //Remove them from course dates and remove them as deputies.

            $db = DBManager::get();
            $stmt = $db->prepare(
                'DELETE FROM `termin_related_persons`
                 WHERE `user_id` = :user_id
                   AND `range_id` IN (
                     SELECT `termin_id` FROM `termine`
                     WHERE `range_id` = :course_id
                   )'
            );
            $stmt->execute(['course_id' => $this->id, 'user_id' => $user->id]);

            if (Deputy::isActivated()) {
                //For all courses where the user is a deputy, they can be removed as deputy
                //from the course, if the other lecturers are no deputies and the current user
                //is not a deputy:
                $all_user_deputy_duties = Deputy::findByRange_id($user->id);
                foreach ($all_user_deputy_duties as $deputy_duty) {
                    $other_deputy_amount = Deputy::countBySql(
                        "JOIN `seminar_user`
                           ON `seminar_user`.`user_id` = `deputies`.`range_id`
                         WHERE `seminar_user`.`user_id` <> :deleted_user_id
                           AND `seminar_user`.`status` = 'dozent'",
                        ['deleted_user_id' => $user->id]
                    );
                    if ($other_deputy_amount === 0 && $GLOBALS['user']->id !== $deputy_duty->user_id) {
                        Deputy::deleteBySQL(
                            '`range_id` = :course_id AND `user_id` = :deputy_id',
                            ['course_id' => $this->id, $deputy_duty->user_id]
                        );
                    }
                }
            }
        }

        //Delete data field entries that are related to the user and the course:
        DatafieldEntryModel::deleteBySQL(
            '`range_id` = :user_id AND `sec_range_id` = :course_id',
            ['user_id' => $user->id, 'course_id' => $this->id]
        );

        //Remove the user from course groups:
        if ($this->statusgruppen) {
            foreach ($this->statusgruppen as $group) {
                $group->removeUser($user->id, true);
            }
        }

        StudipLog::log('SEM_USER_DEL', $this->id, $user->id, 'Wurde aus der Veranstaltung entfernt');

        $this->resetRelation('members');
    }

    /**
     * Moves a regular course member back onto the waitlist.
     *
     * @param User $user The course member to be moved back to the waitlist.
     * @param bool $send_mail Whether to send a mail to inform the user of them
     *     being moved back to the waitlist (true) or not (false). Defaults to false.
     *
     * @return void
     *
     * @throws \Studip\Exception In case the former course member cannot be moved to the waitlist.
     *
     * @throws \Studip\MembershipException In case the membership cannot be terminated.
     */
    public function moveMemberToWaitlist(User $user, bool $send_mail = false): void
    {
        $this->deleteMember($user);
        $this->addMemberToWaitlist($user, PHP_INT_MAX, false);

        if ($send_mail) {
            setTempLanguage($user->id);
            $subject = studip_interpolate(
                _('%{course}: Anmeldung aufgehoben, auf Warteliste gesetzt'),
                ['course' => $this->getFullName()]
            );
            $message = studip_interpolate(
                _('Sie wurden aus der Veranstaltung %{course} abgemeldet und auf die zugehörige Warteliste gesetzt.'),
                ['course' => $this->getFullName()]
            );
            messaging::sendSystemMessage($user->id, $subject, $message);
            restoreLanguage();
        }
    }

    /**
     * Swaps the course member position with another member. This is done by specifying a course member
     * and the new position where they shall be placed in the course.
     *
     * @param CourseMember $membership The course member to move to another position.
     *
     * @return int The new position of the course member.
     *
     * @throws \Studip\MembershipException In case when moving the member position was unsuccessful.
     */
    public function swapMemberPosition(CourseMember $membership, int $new_position): int
    {
        //At this point, the user is not at the highest position.
        //Load the member with the position $position + 1 and swap the positions.

        $next_member = CourseMember::findOneBySQL(
            '`seminar_id` = :course_id AND `status` = :permission_level AND `position` = :new_position',
            [
                'course_id'        => $this->id,
                'permission_level' => $membership->status,
                'new_position'    => strval($new_position)
            ]
        );
        $success = false;
        if ($next_member) {
            $swapped_position = $next_member->position;
            $next_member->position = $membership->position;
            $membership->position = $swapped_position;

            $next_member->store();
            $success = !$membership->isDirty() || $membership->store();
        } else {
            //There is a gap in the position numbers. The user can just be placed to the new position:
            $membership->position = $new_position;
            $success = !$membership->isDirty() || $membership->store();
        }

        if (!$success) {
            //Something went wrong.
            throw new \Studip\MembershipException(
                sprintf(
                    _('%1$s konnte nicht an die Position %2$u verschoben werden.'),
                    $membership->user->getFullName(),
                    $new_position
                ),
                \Studip\MembershipException::MOVING_POSITION_FAILED,
                $membership->user
            );
        }
        return (int) $membership->position;
    }

    /**
     * Moves a course member one position up.
     *
     * @param User $user The user to move up.
     *
     * @return int The new position of the user.
     */
    public function moveMemberUp(User $user) : int
    {
        $membership = CourseMember::findOneBySQL(
            '`seminar_id` = :course_id AND `user_id` = :user_id',
            ['course_id' => $this->id, 'user_id' => $user->id]
        );
        if (!$membership) {
            //The user is not a member.
            return -1;
        }

        if ($membership->position == 0) {
            //The user is already at the highest position.
            return 0;
        }
        return $this->swapMemberPosition($membership, intval($membership->position - 1));
    }

    /**
     * Moves a course member one position down.
     *
     * @param User $user The user to move down.
     *
     * @return int The new position of the user.
     */
    public function moveMemberDown(User $user) : int
    {
        $membership = CourseMember::findOneBySQL(
            '`seminar_id` = :course_id AND `user_id` = :user_id',
            ['course_id' => $this->id, 'user_id' => $user->id]
        );
        if (!$membership) {
            //The user is not a member.
            return -1;
        }

        //Get the maximum number for the permission level in the course:
        $stmt = DBManager::get()->prepare(
            'SELECT MAX(`position`)
             FROM `seminar_user`
             WHERE `seminar_id` = :course_id
               AND `status` = :permission_level'
        );
        $stmt->execute([
            'course_id'        => $this->id,
            'permission_level' => $membership->status,
        ]);
        $max_number = $stmt->fetchColumn();
        if ($max_number === false) {
            //Nothing there to move.
            return -1;
        }

        if ($membership->position == $max_number) {
            //The user is already at the lowest position.
            return (int) $max_number;
        }

        return $this->swapMemberPosition($membership, intval($membership->position + 1));
    }

    public function getNumParticipants()
    {
        return $this->countMembersWithStatus('user autor') + $this->getNumPrelimParticipants();
    }

    public function getNumPrelimParticipants()
    {
        return AdmissionApplication::countBySql(
            "seminar_id = ? AND status = 'accepted'",
            [$this->id]
        );
    }

    public function getNumWaiting()
    {
        return AdmissionApplication::countBySql(
            "seminar_id = ? AND status = 'awaiting'",
            [$this->id]
        );
    }

    public function getParticipantStatus($user_id)
    {
        $p_status = $this->members->findBy('user_id', $user_id)->val('status');
        if (!$p_status) {
            $p_status = $this->admission_applicants->findBy('user_id', $user_id)->val('status');
        }
        return $p_status;
    }

    /**
     * Determines the enrolment status of the user and their possibilities
     * to join the course.
     *
     * @param string $user_id The ID of the user for which to get enrolment information.
     *
     * @return \Studip\EnrolmentInformation The enrolment information
     *     for the specified user.
     */
    public function getEnrolmentInformation(string $user_id) : \Studip\EnrolmentInformation
    {
        //Check the course itself:

        if ($this->getSemClass()->isGroup()) {
            return new \Studip\EnrolmentInformation(
                _('Diese Veranstaltung ist die Hauptveranstaltung einer Veranstaltungsgruppe. Sie können sich nur in die zugehörigen Unterveranstaltungen eintragen.'),
                \Studip\Information::INFO,
                'main_course',
                false
            );
        }

        //Check if the user has root or admin permissions:

        $user = User::find($user_id);

        if ($GLOBALS['perm']->have_perm('root', $user_id)) {
            return new \Studip\EnrolmentInformation(
                _('Sie haben root-Rechte und dürfen damit alles in Stud.IP.'),
                \Studip\Information::INFO,
                'root',
                true
            );
        }

        if ($GLOBALS['perm']->have_studip_perm('admin', $this->id, $user_id)) {
            return new \Studip\EnrolmentInformation(
                _('Sie verwalten diese Veranstaltung.'),
                \Studip\Information::INFO,
                'course_admin',
                true
            );
        }
        if ($GLOBALS['perm']->have_perm('admin', $user_id)) {
            return new \Studip\EnrolmentInformation(
                _('Als administrierende Person dürfen Sie sich nicht in eine Veranstaltung eintragen.'),
                \Studip\Information::INFO,
                'admin',
                false
            );
        }

        //Check the course membership:

        if ($GLOBALS['perm']->have_studip_perm('user', $this->id, $user_id)) {
            return new \Studip\EnrolmentInformation(
                _('Sie sind bereits in der Veranstaltung eingetragen.'),
                \Studip\Information::INFO,
                'already_member',
                true
            );
        }

        //Check the course set and if the user is on an admission list:

        if ($course_set = $this->getCourseSet()) {
            $info = new \Studip\EnrolmentInformation('');
            $info->setCodeword('course_set');
            // Nobody can never enroll when there is a course set present.
            $info->setEnrolmentAllowed('nobody' !== $GLOBALS['user']->id);
            $message = _('Die Anmeldung zu dieser Veranstaltung folgt bestimmten Regeln.');
            $priority = AdmissionPriority::getPrioritiesByUser($course_set->getId(), $user_id);
            if (!empty($priority[$this->id])) {
                if ($course_set->hasAdmissionRule('LimitedAdmission')) {
                    $message .= ' ' . sprintf(
                            _('Sie stehen auf der Anmeldeliste für die automatische Platzverteilung der Veranstaltung mit der Priorität %u.'),
                            $priority[$this->id]
                        );
                } else {
                    $message .= ' ' . _('Sie stehen auf der Anmeldeliste für die automatische Platzverteilung der Veranstaltung.');
                }
            }
            $info->setMessage($message);
            return $info;
        }

        if ($this->lesezugriff == '0' && Config::get()->ENABLE_FREE_ACCESS && !$GLOBALS['perm']->get_studip_perm($this->id, $user_id)) {
            return new \Studip\EnrolmentInformation(
                _('Für diese Veranstaltung ist keine Anmeldung erforderlich.'),
                \Studip\Information::INFO,
                'free_access',
                true
            );
        }

        //Check the visibility of the course for the user:
        if (
            !$this->visible
            && !$this->isStudygroup()
            && !$GLOBALS['perm']->have_perm(Config::get()->SEM_VISIBILITY_PERM, $user_id)
        ) {
            return new \Studip\EnrolmentInformation(
                _('Sie dürfen sich in diese Veranstaltung nicht eintragen.'),
                \Studip\Information::INFO,
                'invisible',
                false
            );
        }

        //Check the lock rule for participants:
        if (LockRules::Check($this->id, 'participants')) {
            return new \Studip\EnrolmentInformation(
                _('Sie dürfen sich in diese Veranstaltung nicht selbst eintragen.'),
                \Studip\Information::INFO,
                'locked',
                false
            );
        }

        //Check the permissions of users that are not root or admin:

        if (!$user) {
            return new \Studip\EnrolmentInformation(
                _('Sie sind nicht in Stud.IP angemeldet und können sich daher nicht in die Veranstaltung eintragen.'),
                \Studip\Information::WARNING,
                'nobody',
                false
            );
        }
        if (!$GLOBALS['perm']->have_perm('user', $user_id)) {
            return new \Studip\EnrolmentInformation(
                _('Sie haben keine ausreichende Berechtigung, um sich in die Veranstaltung einzutragen.'),
                \Studip\Information::INFO,
                'user',
                false
            );
        }

        //Check the admission status:

        $admission_status = $user->admission_applications->findBy('seminar_id', $this->id)->val('status');
        if ($admission_status === 'accepted') {
            return new \Studip\EnrolmentInformation(
                _('Sie wurden für diese Veranstaltung vorläufig akzeptiert.'),
                \Studip\Information::INFO,
                'preliminary_accepted',
                false
            );
        } elseif ($admission_status === 'awaiting') {
            return new \Studip\EnrolmentInformation(
                _('Sie sind auf der Warteliste für diese Veranstaltung.'),
                \Studip\Information::INFO,
                'on_waitlist',
                false
            );
        }

        //Check the user domain:
        $user_domains = UserDomain::getUserDomainsForUser($user_id);
        if (count($user_domains) > 0) {
            //The user is in at least one domain. Check if the course is in one of them.
            $course_domains = UserDomain::getUserDomainsForSeminar($this->id);
            if (
                !UserDomain::checkUserVisibility($course_domains, $user_domains)
                && !$this->isStudygroup()
            ) {
                //The user is not in the same domain as the course and the course
                //is not a studygroup.
                return new \Studip\EnrolmentInformation(
                    _('Sie sind nicht in der gleichen Domäne wie die Veranstaltung und können sich daher nicht für die Veranstaltung eintragen.'),
                    \Studip\Information::INFO,
                    'wrong_domain',
                    false
                );
            }
        }

        //In all other cases, enrolment is allowed.
        return new \Studip\EnrolmentInformation(
            _('Sie können sich zur Veranstaltung anmelden.'),
            \Studip\Information::INFO,
            'allowed',
            true
        );
    }


    /**
    * Returns the semType object that is defined for the course
    *
    * @return SemType The semTypeObject for the course
    */
    public function getSemType()
    {
        $semTypes = SemType::getTypes();
        if (isset($semTypes[$this->status])) {
            return $semTypes[$this->status];
        }

        Log::error(sprintf('SemType not found id:%s status:%s', $this->id, $this->status));
        return new SemType(['name' => 'Fehlerhafter Veranstaltungstyp']);
    }

    /**
     * Returns the SemClass object that is defined for the course
     *
     * @return SemClass The SemClassObject for the course
     */
     public function getSemClass()
     {
         return $this->getSemType()->getClass();
     }

    /**
     * Returns the full name of a course. If the important course numbers
     * (IMPORTANT_SEMNUMBER) is set in global configs it will also display
     * the coursenumber
     *
     * @param string formatting template name
     * @return string Fullname
     */
    public function getFullName($format = 'default')
    {
        $template = [
            'name'                 => '%{name}',
            'name-semester'        => '%{name} (%{semester})',
            'number-name'          => '%{number} %{name}',
            'number-name-semester' => '%{number} %{name} (%{semester})',
            'number-type-name'     => '%{number} %{type}: %{name}',
            'sem-duration-name'    => '%{semester}',
            'type'                 => '%{type}',
            'type-name'            => '%{type}: %{name}',
            'type-number-name'     => '%{type}: %{number} %{name}',
        ];

        if ($format === 'default' || !isset($template[$format])) {
           $format = Config::get()->IMPORTANT_SEMNUMBER ? 'type-number-name' : 'type-name';
        }
        $sem_type = $this->getSemType();
        $data = array_map('trim', [
            'name'     => $this->name,
            'type'     => $sem_type['name'],
            'number'   => $this->veranstaltungsnummer,
            'semester' => $this->getTextualSemester(),
        ]);
        return trim(studip_interpolate($template[$format], $data));
    }

    /**
     * Retrieves all dates (regular and irregular) that take place
     * in a specified semester or a semester range.
     *
     * @param Semester|null $start_semester The semester for which to get all dates
     *     or the start semester of a semester range.
     * @param Semester|null $end_semester The end semester for a semester range.
     *     This can also be null in case only dates for one semester
     *     shall be retrieved.
     *
     * @param bool $with_cancelled_dates Whether to include cancelled dates (true) or not (false).
     *     Defaults to false.
     *
     * @return CourseDateList A collection of irregular and regular course dates.
     *
     * @throws \Studip\Exception In case that the end semester is before the start semester.
     */
    public function getAllDatesInSemester(
        ?Semester $start_semester = null,
        ?Semester $end_semester = null,
        bool $with_cancelled_dates = false
    ) : CourseDateList {
        $all_dates_of_course = !$start_semester && !$end_semester;

        if ($all_dates_of_course) {
            $collection = new CourseDateList();
            foreach ($this->cycles as $regular_date) {
                $collection->addRegularDate($regular_date);
            }
            foreach ($this->dates as $date) {
                if (!$date->metadate_id) {
                    $collection->addSingleDate($date);
                }
            }
            if ($with_cancelled_dates) {
                foreach ($this->ex_dates as $cancelled_date) {
                    $collection->addCancelledDate($cancelled_date);
                }
            }
            return $collection;
        } else {
            if (!$start_semester) {
                return new CourseDateList();
            }
            $beginning = $start_semester->beginn;
            $end = $start_semester->ende;
            if ($end_semester) {
                if ($end_semester->ende < $start_semester->beginn) {
                    throw new \Studip\Exception(
                        _('Das Endsemester darf nicht vor dem Startsemester liegen.'),
                        \Studip\Exception::END_BEFORE_BEGINNING
                    );
                }
                $end = $end_semester->ende;
            }

            $collection = new CourseDateList();

            SeminarCycleDate::findEachBySQL(
                function ($date) use ($collection) {
                    $collection->addRegularDate($date);
                },
                "`metadate_id` IN (
                    SELECT `metadate_id`
                    FROM `termine`
                    WHERE `range_id` = :course_id
                      AND `termine`.`date` <= :end
                      AND `termine`.`end_time` >= :beginning
                      AND `metadate_id` IS NOT NULL
                      AND `metadate_id` != ''
                 )",
                [
                    'course_id' => $this->id,
                    'beginning' => $beginning,
                    'end' => $end
                ]
            );

            CourseDate::findEachBySQL(
                function ($date) use ($collection) {
                    $collection->addSingleDate($date);
                },
                "`date` >= :beginning AND `end_time` <= :end
                    AND `range_id` = :course_id
                    AND (`metadate_id` IS NULL OR `metadate_id` = '')",
                [
                    'course_id' => $this->id,
                    'beginning' => $beginning,
                    'end' => $end
                ]
            );

            if ($with_cancelled_dates) {
                CourseExDate::findEachBySQL(
                    function ($date) use ($collection) {
                        $collection->addCancelledDate($date);
                    },
                    "`date` >= :beginning AND `end_time` <= :end
                        AND `range_id` = :course_id
                        AND (`metadate_id` IS NULL OR `metadate_id` = '')",
                    [
                        'course_id' => $this->id,
                        'beginning' => $beginning,
                        'end' => $end
                    ]
                );
            }

            return $collection;
        }
    }


    /**
     * Retrieves the course dates including cancelled dates ("ex-dates").
     * The dates can be filtered by an optional time range. By default,
     * all dates are retrieved.
     *
     * @param int $range_begin The begin timestamp of the time range.
     *
     * @param int $range_end The end timestamp of the time range.
     *
     * @returns SimpleCollection A collection of all retrieved dates and
     *     cancelled dates.
     */
    public function getDatesWithExdates($range_begin = 0, $range_end = 0)
    {
        $dates = SimpleCollection::createFromArray([]);
        if (
            $range_begin > 0
            && $range_end > 0
            && $range_end > $range_begin
        ) {
            $dates->merge(
                $this->dates->findBy('date', $range_begin, '>=')
                     ->findBy('end_time', $range_end, '<=')
            );

            $dates->merge(
                $this->ex_dates->findBy('content', '', '<>')
                     ->findBy('date', $range_begin, '>=')
                     ->findBy('end_time', $range_end, '<=')
            );
        } else {
            $dates->merge($this->dates);
            $dates->merge($this->ex_dates->findBy('content', '', '<>'));
        }
        $dates->uasort(function($a, $b) {
            return $a->date - $b->date
                ?: strnatcasecmp($a->getRoomNames(), $b->getRoomNames());
        });
        return $dates;
    }

    /**
     * Retrieves the first date of the course that takes place.
     *
     * @return CourseDate|null Either the first date as CourseDate or null in case
     *     the course has no dates.
     */
    public function getFirstDate() : ?CourseDate
    {
        return $this->dates->first();
    }

    /**
     * Retrieves the next date for the course. If requested, the next cancelled
     * date is retrieved if no date can be found that takes place.
     *
     * The date must start in the future or within the past hour to be regarded
     * as next date.
     *
     * @param bool $include_cancelled Include cancelled dates (true) or not.
     *     Defaults to false.
     *
     * @return CourseDate|CourseExDate|null A CourseDate or CourseExDate representing
     *     the next date or null in case there is no next date. CourseExDate instances
     *     are only returned if $include_cancelled is set to true.
     */
    public function getNextDate(bool $include_cancelled = false)
    {
        $sql = '`range_id` = :course_id AND `date` > UNIX_TIMESTAMP() - 3600
                ORDER BY `date`, `end_time`';

        $date = CourseDate::findOneBySQL($sql, ['course_id' => $this->id]);
        if (!$date && $include_cancelled) {
            //Do the same with CourseExDate:
            $date = CourseExDate::findOneBySQL($sql, ['course_id' => $this->id]);
        }
        return $date;
    }

    /**
     * Sets this courses study areas to the given values.
     *
     * @param array $ids the new study areas
     * @return bool Changes successfully saved?
     */
    public function setStudyAreas($ids)
    {
        $old = $this->study_areas->pluck('sem_tree_id');
        $added = array_diff($ids, $old);
        $removed = array_diff($old, $ids);
        $success = false;
        if ($added || $removed) {

            $this->study_areas = SimpleCollection::createFromArray(StudipStudyArea::findMany($ids));

            if ($this->store()) {
                NotificationCenter::postNotification('CourseDidChangeStudyArea', $this);
                $success = true;

                foreach ($added as $one) {
                    StudipLog::log('SEM_ADD_STUDYAREA', $this->id, $one);

                    $area = $this->study_areas->find($one);
                    if ($area->isModule()) {
                        NotificationCenter::postNotification(
                            'CourseAddedToModule',
                            $area,
                            ['module_id' => $one, 'course_id' => $this->id]
                        );
                    }
                }

                foreach ($removed as $one) {
                    StudipLog::log('SEM_DELETE_STUDYAREA', $this->id, $one);

                    $area = StudipStudyArea::find($one);
                    if ($area->isModule()) {
                        NotificationCenter::postNotification(
                            'CourseRemovedFromModule',
                            $area,
                            ['module_id' => $one, 'course_id' => $this->id]
                        );
                    }
                }
            }
        }

        return $success;
    }

    /**
     * Is the current course visible for the current user?
     * @param string $user_id
     * @return bool Visible?
     */
    public function isVisibleForUser($user_id = null)
    {
        return $this->visible
            || $GLOBALS['perm']->have_perm(Config::get()->SEM_VISIBILITY_PERM, $user_id)
            || $GLOBALS['perm']->have_studip_perm('user', $this->id, $user_id);
    }

    /**
     * Returns a descriptive text for the range type.
     *
     * @return string
     */
    public function describeRange()
    {
        return _('Veranstaltung');
    }

    /**
     * Returns a unique identificator for the range type.
     *
     * @return string
     */
    public function getRangeType()
    {
        return 'course';
    }

    /**
     * Returns the id of the current range
     *
     * @return string
     */
    public function getRangeId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return CourseConfig::get($this);
    }

    /**
     * Decides whether the user may access the range.
     *
     * @param string|null $user_id Optional id of a user, defaults to current user
     * @return bool
     */
    public function isAccessibleToUser($user_id = null): bool
    {
        if ($this->lesezugriff == 0 && Config::get()->ENABLE_FREE_ACCESS) {
            return true;
        }

        if ($user_id === null) {
            $user_id = $GLOBALS['user']->id;
        }

        return $GLOBALS['perm']->have_studip_perm('user', $this->id, $user_id);
    }

    /**
     * Decides whether the user may edit/alter the range.
     *
     * @param string|null $user_id Optional id of a user, defaults to current user
     * @return bool
     * @todo Check permissions
     */
    public function isEditableByUser($user_id = null)
    {
        if ($user_id === null) {
            $user_id = $GLOBALS['user']->id;
        }
        return $GLOBALS['perm']->have_studip_perm('tutor', $this->id, $user_id);
    }

    /**
     * Returns the appropriate icon for the completion status.
     *
     * Mapping (completion -> icon role):
     * - 0 => status-red
     * - 1 => status-yellow
     * - 2 => status-green
     *
     * @return Icon class
     */
    public function getCompletionIcon()
    {
        $role = Icon::ROLE_STATUS_RED;
        if ($this->completion == 1) {
            $role = Icon::ROLE_STATUS_YELLOW;
        } elseif ($this->completion == 2) {
            $role = Icon::ROLE_STATUS_GREEN;
        }
        return Icon::create('radiobutton-checked', $role);
    }

    /**
     * Returns the appropriate label for the completion status.
     *
     * @return string
     */
    public function getCompetionLabel(): string
    {
        return [
            0 => _('unvollständig'),
            1 => _('in Bearbeitung'),
            2 => _('fertig'),
        ][$this->completion] ?? _('undefiniert');
    }

    /**
     * Handle all things related to storing the institutes
     */
    protected function handleInstitutes(): void
    {
        if ($this->isFieldDirty('institut_id')) {
            StudipLog::log(
                'CHANGE_INSTITUTE_DATA',
                $this->id,
                $this->institut_id,
                "Die Heimateinrichtung wurde zu \"{$this->home_institut->name}\" geändert."
            );
        }

        if ($this->currently_assigned_institutes !== null) {
            $assigned_ids = $this->institutes->pluck('id');

            // Deleted
            $deleted_ids = array_diff($this->currently_assigned_institutes, $assigned_ids);
            Institute::findEachMany(
                function (Institute $institute) {
                    StudipLog::log(
                        'CHANGE_INSTITUTE_DATA',
                        $this->id,
                        $institute->id,
                        "Die beteiligte Einrichtung \"{$institute->name}\" wurde gelöscht."
                    );
                    NotificationCenter::postNotification('SeminarInstitutionDidDelete', $institute->id, $this->id);
                },
                $deleted_ids
            );

            // Added
            $added_ids = array_diff($assigned_ids, $this->currently_assigned_institutes);
            Institute::findEachMany(
                function (Institute $institute) {
                    StudipLog::log(
                        'CHANGE_INSTITUTE_DATA',
                        $this->id,
                        $institute->id,
                        "Die beteiligte Einrichtung \"{$institute->name}\" wurde hinzugefügt."
                    );
                    NotificationCenter::postNotification('SeminarInstitutionDidCreate', $institute->id, $this->id);
                },
                $added_ids
            );

            if (count($deleted_ids) > 0 || count($added_ids) > 0) {
                NotificationCenter::postNotification('CourseDidChangeInstitutes', $this);
            }
        }

        if (
            $this->institut_id
            && !$this->institutes->find($this->institut_id)
        ) {
            $this->institutes[] = $this->home_institut;
        }
    }

    /**
     * Generates a general log entry if the course were changed.
     * Furthermore, this method emits notifications when the
     * start and/or the end semester has/have changed.
     */
    protected function logStore()
    {
        if (!$this->isNew()) {
            if ($this->initial_start_semester?->id !== $this->start_semester?->id) {
                //Log change of start semester:
                StudipLog::log('SEM_SET_STARTSEMESTER', $this->id, isset($this->start_semester) ? $this->start_semester->name : _('unbegrenzt'));
                NotificationCenter::postNotification('CourseDidChangeSchedule', $this);
            }
            if ($this->initial_end_semester?->id !== $this->end_semester?->id) {
                StudipLog::log('SEM_SET_ENDSEMESTER', $this->id, $this->getTextualSemester());
                NotificationCenter::postNotification('CourseDidChangeSchedule', $this);
            }

            $log = [];
            if ($this->isFieldDirty('admission_prelim')) {
                $log[] = $this->admission_prelim ? _('Neuer Anmeldemodus: Vorläufiger Eintrag') : _('Neuer Anmeldemodus: Direkter Eintrag');
            }

            if ($this->isFieldDirty('admission_binding')) {
                $log[] = $this->admission_binding ? _('Anmeldung verbindlich') : _('Anmeldung unverbindlich');
            }

            if ($this->isFieldDirty('admission_turnout')) {
                $log[] = sprintf(_('Neue Teilnehmerzahl: %s'), (int)$this->admission_turnout);
            }

            if ($this->isFieldDirty('admission_disable_waitlist')) {
                $log[] = $this->admission_disable_waitlist ? _('Warteliste aktiviert') : _('Warteliste deaktiviert');
            }

            if ($this->isFieldDirty('admission_waitlist_max')) {
                $log[] = sprintf(_('Plätze auf der Warteliste geändert: %u'), (int)$this->admission_waitlist_max);
            }

            if ($this->isFieldDirty('admission_disable_waitlist_move')) {
                $log[] = $this->admission_disable_waitlist ? _('Nachrücken aktiviert') : _('Nachrücken deaktiviert');
            }

            if ($this->isFieldDirty('admission_prelim_txt')) {
                if ($this->admission_prelim_txt) {
                    $log[] = sprintf(_('Neuer Hinweistext bei vorläufigen Eintragungen: %s'), strip_tags(kill_format($this->admission_prelim_txt)));
                } else {
                    $log[] = _('Hinweistext bei vorläufigen Eintragungen wurde entfernt');
                }
            }

            if (!empty($log)) {
                StudipLog::log(
                    'SEM_CHANGED_ACCESS',
                    $this->id,
                    null,
                    '',
                    implode(' - ', $log)
                );
            }

            if ($this->isFieldDirty('visible')) {
                StudipLog::log($this->visible ? 'SEM_VISIBLE' : 'SEM_INVISIBLE', $this->id);
            }
        }
    }

    //StudipItem interface implementation:

    public function getItemName($long_format = true)
    {
        if ($long_format) {
            return $this->getFullName();
        } else {
            return $this->name;
        }
    }

    public function getItemURL()
    {
        return URLHelper::getURL(
            'dispatch.php/course/details/index',
            [
                'cid' => $this->id
            ]
        );
    }

    public function getItemAvatarURL()
    {
        $avatar = CourseAvatar::getAvatar($this->id);
        if ($avatar) {
            return $avatar->getURL(Avatar::NORMAL);
        }
        return '';
    }


    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $storage object to store data into
     */
    public static function exportUserData(StoredUserData $storage)
    {
        $sorm = self::findThru($storage->user_id, [
            'thru_table'        => 'seminar_user',
            'thru_key'          => 'user_id',
            'thru_assoc_key'    => 'Seminar_id',
            'assoc_foreign_key' => 'Seminar_id',
        ]);
        if ($sorm) {
            $field_data = [];
            foreach ($sorm as $row) {
                $field_data[] = $row->toRawArray();
            }
            if ($field_data) {
                $storage->addTabularData(_('Seminare'), 'seminare', $field_data);
            }
        }
    }
    public function getRangeName()
    {
        return $this->name;
    }

    public function getRangeIcon($role)
    {
        return Icon::create('seminar', $role);
    }

    public function getRangeUrl()
    {
        return 'course/overview';
    }

    public function getRangeCourseId()
    {
        return $this->Seminar_id;
    }

    public function isRangeAccessible(?string $user_id = null): bool
    {
        $user_id = $user_id ?? $GLOBALS['user']->id;
        return $GLOBALS['perm']->have_studip_perm('autor', $this->Seminar_id, $user_id);
    }


    public function getLink() : StudipLink
    {
        return new StudipLink($this->getItemURL(), $this->name, Icon::create('seminar'));
    }


    /**
     * Returns a list of courses for the specified user.
     * Permission levels may be supplied to limit the course list.
     *
     * @param string $user_id The ID of the user whose courses shall be retrieved.
     *
     * @param string[] $perms The permission levels of the user that shall be
     *     regarded when retrieving courses.
     *
     * @param bool $with_deputies Whether to include courses where the user is
     *     a deputy (true) or not (false). Defaults to true.
     *
     * @return Course[] A list of courses.
     */
    public static function findByUser($user_id, $perms = [], $with_deputies = true)
    {
        if (!$user_id) {
            return [];
        }

        $db = DBManager::get();
        $sql = "SELECT `seminar_id`
                FROM `seminar_user`
                WHERE `user_id` = :user_id";
        $sql_params = ['user_id' => $user_id];
        if (is_array($perms) && count($perms)) {
            $sql .= ' AND `status` IN (:perms)';
            $sql_params['perms'] = $perms;
        }
        $seminar_ids = $db->fetchFirst($sql, $sql_params);
        if (Config::get()->DEPUTIES_ENABLE && $with_deputies) {
            $sql = 'SELECT range_id FROM `deputies` WHERE `deputies`.`user_id` = :user_id';
            $seminar_ids = array_merge($seminar_ids, $db->fetchFirst($sql, $sql_params));
        }

        $name_sort = Config::get()->IMPORTANT_SEMNUMBER ? 'VeranstaltungsNummer, Name' : 'Name';

        return Course::findBySQL(
            "LEFT JOIN semester_courses ON (semester_courses.course_id = seminare.Seminar_id)
             WHERE Seminar_id IN (?)
             GROUP BY seminare.Seminar_id
             ORDER BY semester_courses.semester_id IS NULL DESC, {$name_sort}",
            [$seminar_ids]
        );
    }

    /**
     * Returns whether this course is a studygroup
     * @return bool
     */
    public function isStudygroup()
    {
        return in_array($this->status, studygroup_sem_types());
    }

    /**
     *
     */
    public function setDefaultTools()
    {
        $this->tools = [];
        foreach (array_values($this->getSemClass()->getActivatedModuleObjects()) as $module) {
            PluginManager::getInstance()->setPluginActivated($module->getPluginId(), $this->id, true);
            $this->tools[] = ToolActivation::find([$this->id, $module->getPluginId()]);
        }
    }

    /**
     * @param $name string name of tool / plugin
     * @return bool
     */
    public function isToolActive($name)
    {
        $plugin = PluginEngine::getPlugin($name);
        return $plugin && $this->tools->findOneby('plugin_id', $plugin->getPluginId());
    }


    /**
     * Returns the Plugin/Tool specified by its name in case it is
     * activated in this course.
     *
     * @param string $name The name of the tool.
     *
     * @return StandardPlugin An instance for the tool.
     *
     * @throws \Studip\ToolException In case the tool is not activated.
     */
    public function getTool(string $name) : StandardPlugin
    {
        if ($this->isToolActive($name)) {
            $plugin = PluginEngine::getPlugin($name);
            if ($plugin instanceof StandardPlugin) {
                return $plugin;
            }
        }
        throw new \Studip\ToolException(
            sprintf(
                _('Das Werkzeug %s ist nicht aktiviert.'),
                $name
            ),
            \Studip\ToolException::TOOL_NOT_ACTIVATED
        );
    }

    /**
     * returns all activated plugins/modules for this course
     * @return StudipModule[]
     */
    public function getActivatedTools()
    {
        return array_filter($this->tools->getStudipModule());
    }

    /**
     * @see Range::__toString()
     */
    public function __toString() : string
    {
        return $this->getFullName();
    }

    /**
     * @inheritDoc
     */
    public static function getCalendarOwner(string $owner_id): ?\Studip\Calendar\Owner
    {
        return self::find($owner_id);
    }

    /**
     * @inheritDoc
     */
    public function isCalendarReadable(?string $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = User::findCurrent()->id;
        }

        //Calendar read permissions are granted for all participants
        //that have at least user permissions.
        return $GLOBALS['perm']->have_studip_perm('user', $this->id, $user_id);
    }

    /**
     * @inheritDoc
     */
    public function isCalendarWritable(?string $user_id = null): bool
    {
        if ($user_id === null) {
            $user_id = User::findCurrent()->id;
        }

        //Calendar write permissions are granted for all participants
        //that have autor permissions or higher.
        return $GLOBALS['perm']->have_studip_perm('autor', $this->id, $user_id);
    }

    /**
     * Get user information for all users in this course
     *
     */
    public function getMembersData(?string $status = ''): array
    {
        $result = [];

        if (!$status) {
            foreach ($this->members->findBy('status', ['dozent', 'tutor'])->orderBy('status,position,nachname,vorname') as $member) {
                $result[$member->user_id] = $member->getExportData();
            }
            foreach ($this->members->findBy('status', ['autor', 'user'])->orderBy('status,nachname,vorname') as $member) {
                $result[$member->user_id] = $member->getExportData();
            }
            foreach ($this->admission_applicants->findBy('status', 'accepted')->orderBy('position') as $member) {
                $result[$member->user_id] = $member->getExportData();
            }
        } elseif ($status === 'awaiting') {
            foreach ($this->admission_applicants->findBy('status', $status)->orderBy('position') as $member) {
                $result[$member->user_id] = $member->getExportData();
            }
        } elseif ($status === 'claiming') {
            $cs = CourseSet::getSetForCourse($this->id);
            if (is_object($cs) && !$cs->hasAlgorithmRun()) {
                $claiming_users = User::findFullMany(array_keys(AdmissionPriority::getPrioritiesByCourse($cs->getId(), $this->id)), 'ORDER BY nachname');
                foreach ($claiming_users as $claiming_user) {
                        $studycourse = [];
                        $claiming_user->studycourses->map(function($sc) use (&$studycourse) {
                            $studycourse[]= $sc->studycourse->name .  ',' . $sc->degree->name . ',' . $sc->semester;
                        });
                        $export_data = [
                            'status' => $status,
                            'salutation' => $claiming_user->salutation,
                            'Titel' => $claiming_user->title_front,
                            'Vorname' => $claiming_user->vorname,
                            'Nachname' => $claiming_user->nachname,
                            'Titel2' => $claiming_user->title_rear,
                            'username' => $claiming_user->username,
                            'privadr' => $claiming_user->privadr,
                            'privatnr' => $claiming_user->privatnr,
                            'Email' => $claiming_user->email,
                            'Anmeldedatum' => '',
                            'Matrikelnummer' => $claiming_user->matriculation_number,
                            'studiengaenge' => implode(';', $studycourse),
                            'position' => 0,
                        ];
                    $result[$claiming_user->user_id] = $export_data;
                }
            }
        }

        return $result;
    }
}
