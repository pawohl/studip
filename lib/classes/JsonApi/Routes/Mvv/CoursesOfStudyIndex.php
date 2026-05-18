<?php

namespace JsonApi\Routes\Mvv;

use JsonApi\Schemas\CourseOfStudy;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\JsonApiController;

class CoursesOfStudyIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['q', 'institute', 'semester', 'degree', 'category', 'type'];

    protected $allowedIncludePaths = [
        CourseOfStudy::REL_SECTIONS,
        CourseOfStudy::REL_INSTITUTE,
        CourseOfStudy::REL_COMPONENTS,
        CourseOfStudy::REL_DEGREE,
        CourseOfStudy::REL_END_SEMESTER,
        CourseOfStudy::REL_START_SEMESTER,
    ];

    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = $this->getUser($request);
        if (!Authority::canIndexCoursesOfStudy($user)) {
            throw new AuthorizationFailedException();
        }

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];
        $error = $this->validateFilters($filtering);
        if ($error) {
            throw new BadRequestException($error);
        }

        [$offset, $limit] = $this->getOffsetAndLimit();
        $courses_of_study = array_filter(
            $this->getCoursesOfStudy($filtering),
            fn($cos) => Authority::canShowCourseOfStudy($user, $cos)
        );

        return $this->getPaginatedContentResponse(
            array_slice($courses_of_study, $offset, $limit),
            count($courses_of_study)
        );
    }

    private function validateFilters($filtering)
    {
        // keyword aka q
        if (isset($filtering['q']) && mb_strlen($filtering['q']) < 3) {
            return 'Search term too short.';
        }

        // institute
        if (isset($filtering['institute']) && !\Institute::exists($filtering['institute'])) {
            return 'Filter `institute` must be a valid id.';
        }

        // degree
        if (isset($filtering['degree']) && !\Abschluss::exists($filtering['degree'])) {
            return 'Filter `degree` must be a valid id.';
        }

        // degree category
        if (isset($filtering['category']) && !\AbschlussKategorie::find($filtering['category'])) {
            return 'Filter `category` must be a valid id';
        }

        // semester
        if (isset($filtering['semester']) && !\Semester::exists($filtering['semester'])) {
            return 'Filter `semester` must be a valid id.';
        }
    }

    private function getCoursesOfStudy($filtering): array
    {
        $join = '';
        $where = ' 1 ';
        if (isset($filtering['institute'])) {
            $where .= ' AND `institut_id` = :institute ';
        }
        if (isset($filtering['type'])) {
            $where .= ' AND `typ` = :type ';
        }
        if (isset($filtering['degree'])) {
            $where .= ' AND `mvv_studiengang`.`abschluss_id` = :degree ';
        }
        if (isset($filtering['category'])) {
            $join .= 'LEFT JOIN `mvv_abschluss_zuord` USING(`abschluss_id`) ';
            $where .= ' AND `mvv_abschluss_zuord`.`kategorie_id` = :category';
        }
        if (isset($filtering['semester'])) {
            $semester = \Semester::find($filtering['semester']);
            unset($filtering['semester']);
            $filtering['semester_start'] = $semester->beginn;
            $filtering['semester_end'] = $semester->ende;
            $join .= 'LEFT JOIN `semester_data` AS `start_sem`
                        ON (`mvv_studiengang`.`start` = `start_sem`.`semester_id`)
                    LEFT JOIN `semester_data` AS `end_sem`
                        ON (`mvv_studiengang`.`end` = `end_sem`.`semester_id`) ';
            $where .= ' AND (`start_sem`.`beginn` <= :semester_end OR ISNULL(`start_sem`.`beginn`))
                        AND (`end_sem`.`ende` >= :semester_start OR ISNULL(`end_sem`.`ende`))';
        }
        if (isset($filtering['q'])) {
            $where .= " AND (`mvv_studiengang`.`name` LIKE CONCAT('%', :q, '%') OR `mvv_studiengang`.`name_kurz` LIKE CONCAT('%', :q, '%')) ";
        }
        $where .= ' ORDER BY `mvv_studiengang`.`name` ASC';
        return \Studiengang::findBySQL(
            ($join ? $join . ' WHERE ' : '') . $where,
            $filtering
        );
    }

}
