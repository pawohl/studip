<?php
/**
 * ExternPageCourseDetails.php - Class to provide course details as extern page.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Peter Thienel <thienel@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       5.4
 */

class ExternPageCourseDetails extends ExternPage
{

    public $institute;

    public function __construct($config)
    {
        parent::__construct($config);
        $this->institute = Institute::find($this->page_config->range_id);
    }

    /**
     * @see ExternPage::getSortFields()
     */
    public function getSortFields(): array
    {
        return [];
    }

    /**
     * @see ExternPage::getDataFields()
     */
    public function getDataFields(array $object_classes = []): array
    {
        return parent::getDataFields(
            [
                'sem',
                'user',
                'userinstrole'
            ]
        );
    }

    /**
     * @see ExternPage::getConfigFields()
     */
    public function getConfigFields(bool $as_array = false)
    {
        $args = '
            language       option,
            escaping       option,
            rangepathlevel int
        ';
        return $as_array ? self::argsToArray($args) : $args;
    }

    /**
     * @see ExternPage::getAllowedRequestParams()
     */
    public function getAllowedRequestParams(bool $as_array = false)
    {
        $params = [
            'language',
            'course_id',
        ];
        return $as_array ? $params : implode(',', $params);
    }

    /**
     * @see ExternPage::getMarkersContents()
     */
    public function getMarkersContents(): array
    {
        return $this->getContent();
    }

    /**
     *
     *
     * @return array|array[]
     * @throws Exception
     */
    protected function getContent(): array
    {
        $course = Course::find($this->course_id);

        if (
            $this->range_id !== 'studip'
            && (
                $course->institut_id !== $this->institute->id
                || !$course->institutes->find($this->institute->id)
            )
        ) {
            return [];
        }
        if (!$course->visible) {
            return [];
        }

        $content = $this->getContentCourse($course);
        $content = array_merge($content, [
            'LECTURERS' => $this->getContentMembers($course, 'dozent'),
            'TUTORS'    => $this->getContentMembers($course, 'tutor'),
        ]);
        $content += $this->getContentRangePaths($course);
        $content += $this->getContentModules($course);
        $content += $this->getContentNews($course);
        $content += $this->getContentParticipatingInstitutes($course);

        return $content;
    }

    /**
     * Retrieves the basic data of current course.
     *
     * @param Course $course The current course.
     * @return array The content with basic data.
     * @throws Exception
     */
    protected function getContentCourse(Course $course): array
    {
        $content = [
            'TITLE'             => $course->name,
            'SUBTITLE'          => $course->untertitel,
            'FULLNAME'          => $course->getFullName(),
            'SEMESTER'          => $course->getTextualSemester(),
            'CYCLE'             => $course->getAllDatesInSemester(),
            'ROOM'              => $course->ort,
            'NUMBER'            => $course->veranstaltungsnummer,
            'PRELIM_DISCUSSION' => vorbesprechung($course->id, 'export'),
            'SEMTYPE_ID'        => $course->status,
            'SEMTYPE'           => $GLOBALS['SEM_TYPE'][$course->status]['name'],
            'SEMCLASS_ID'       => $GLOBALS['SEM_TYPE'][$course->status]['class'],
            'SEMCLASS'          => $GLOBALS['SEM_CLASS'][$GLOBALS['SEM_TYPE'][$course->status]['class']]['name'],
            'FORM'              => $course->art,
            'PARTICIPANTS'      => $course->teilnehmer,
            'DESCRIPTION'       => $course->beschreibung,
            'MISCELLANEOUS'     => $course->sonstiges,
            'REQUIREMENTS'      => $course->vorrausetzungen,
            'ORGA'              => $course->lernorga,
            'CERTIFICATE'       => $course->leistungsnachweis,
            'ECTS'              => $course->ects,
            'FIRST_MEETING'     => $course->getFirstDate(),
            'HOME_INST_NAME'    => $course->home_institut->name,
            'HOME_INST_ID'      => $course->home_institut->id,
            'COUNT_USER'        => count($course->members),
        ];
        return array_merge(
            $content,
            $this->getDatafieldMarkers($course),
            $this->getContentChildCourses($course));
    }

    /**
     * Retrieves all subcourses.
     *
     * @param Course $course The parent course.
     * @return array[] Array of subcourses content.
     * @throws Exception
     */
    protected function getContentChildCourses(Course $course): array
    {
        $content = [];
        foreach ($course->children as $child) {
            $content[] = $this->getContentCourse($child);
        }
        return ['SUBCOURSES' => $content];
    }

    /**
     * Retrieves all assignments to study areas as paths.
     *
     * @param Course $course The course.
     * @return array[] Array with full paths of study areas (ranges).
     */
    protected function getContentRangePaths(Course $course): array
    {
        $paths = [];
        foreach ($course->study_areas as $study_area) {
            $paths[] = $study_area->getPath();
        }
        asort($paths);

        return ['RANGE_PATHS' => $paths];
    }

    /**
     * Retrieves all module assignments as paths.
     *
     * @param Course $course The current course.
     * @return array[] The paths through mvv.
     */
    protected function getContentModules(Course $course): array
    {
        $content = [];
        $sem_class = $course->getSemClass();
        if ($sem_class['module']) {
            ModuleManagementModelTreeItem::setObjectFilter('Modul', function ($modul) use ($course) {
                // check for public status
                if (!$GLOBALS['MVV_MODUL']['STATUS']['values'][$modul->stat]['public']) {
                    return false;
                }
                $course_start = $course->start_semester->beginn ?? 0;
                $course_end   = $course->end_semester->ende ?? PHP_INT_MAX;
                $modul_start = Semester::find($modul->start)->beginn ?: 0;
                $modul_end = Semester::find($modul->end)->beginn ?: PHP_INT_MAX;
                return ($course_start <= $modul_end)
                    && (
                        ($course_start >= $modul_start)
                        || $course->isOpenEnded()
                        || $course_end <= $modul_end
                        || $course_end >= $modul_start
                    );
            });
            ModuleManagementModelTreeItem::setObjectFilter('StgteilVersion', function ($version) {
                return $GLOBALS['MVV_STGTEILVERSION']['STATUS']['values'][$version->stat]['public'];
            });
            $trail_classes = ['Modulteil', 'StgteilabschnittModul', 'StgteilAbschnitt', 'StgteilVersion'];
            $mvv_object_paths = MvvCourse::get($this->course_id)->getTrails($trail_classes);

            foreach ($mvv_object_paths as $mvv_object_path) {
                $modul_id = '';
                // show only complete paths
                if (count($mvv_object_path) === 4) {
                    $mvv_object_names = [];
                    foreach ($mvv_object_path as $mvv_object) {
                        $mvv_object_names[] = $mvv_object->getDisplayName();
                        if ($mvv_object instanceof StgteilabschnittModul) {
                            $modul_id = $mvv_object->modul_id;
                        }
                    }
                    $content[] = [
                        'PATH' => implode(' > ', $mvv_object_names),
                        'ID' => $modul_id,
                    ];
                }
            }
        }

        return ['MODULES' => $content];
    }

    /**
     * Retrieves data of participated institutes as content array.
     *
     * @param Course $course The course.
     * @return array[] Array with data of participated institutes.
     */
    protected function getContentParticipatingInstitutes(Course $course): array
    {
        $content = [];
        foreach ($course->institutes as $institute) {
            if ($institute->id !== $course->home_institut) {
                $content[] = [
                    'NAME' => $institute->name,
                    'ID'   => $institute->id,
                ];
            }
        }
        return ['INVOLVED_INSTITUTES' => $content];
    }

    /**
     * Retrieves content from news for current course.
     *
     * @param Course $course The current course.
     * @return array[] The content with news data.
     */
    private function getContentNews(Course $course): array
    {
        $news = StudipNews::GetNewsByRange($course->id, true, true);
        $content = [];
        foreach ($news as $news_detail) {
            $content[] = [
                'BODY'       => $news_detail->body,
                'TOPIC'      => $news_detail->topic,
                'DATE'       => $news_detail->date,
                'FULLNAME'   => $news_detail->owner->getFullName(),
                'LASTNAME'   => $news_detail->owner->nachname,
                'FIRSTNAME'  => $news_detail->owner->vorname,
                'TITLEFRONT' => $news_detail->owner->title_front,
                'TITLEREAR'  => $news_detail->owner->title_rear,
                'USERNAME'   => $news_detail->owner->username,
                'USERID'     => $news_detail->owner->id,
            ];
        }
        return ['NEWS' => $content];
    }

}
