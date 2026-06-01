<?php

namespace JsonApi\Routes\Courses;

use Modulteil;
use Course;
use JsonApi\Errors\BadRequestException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Semester;

class CoursesByModuleComponentsIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    protected $allowedFilteringParameters = ['semester', 'df'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, ?array $args): Response
    {
        $component = Modulteil::find($args['id']);
        if (!$component) {
            throw new RecordNotFoundException();
        }

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        $error = $this->validateFilters($filtering);
        if ($error) {
            throw new BadRequestException($error);
        }

        $courses = $this->findCoursesByComponent(
            $component,
            $filtering
        );
        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            array_slice($courses, $offset, $limit),
            count($courses)
        );
    }

    private function validateFilters(array $filtering): ?string
    {
        // semester
        if (
            isset($filtering['semester'])
            && !Semester::exists($filtering['semester'])
        ) {
            return 'Invalid "semester".';
        }

        // data fields
        if (isset($filtering['df']) && is_array($filtering['df'])) {
            $accepted_dfs = $this->getAcceptedDataFields();
            foreach (array_keys($filtering['df']) as $df) {
                if (!in_array($df, $accepted_dfs)) {
                    return 'Invalid data field as filtering parameter.';
                }
            }
        }
        return null;
    }

    /**
     * Get ids of accepted datafields for current user.
     * Only simple types of bool, textline, selectbox and radio with global
     * visibility for all users are accepted.
     *
     * @return array Accepted datafields
     */
    private function getAcceptedDataFields(): array
    {
        $data_fields = \DataField::findAndMapBySQL(
            fn(\DataField $data_field) => $data_field->id,
            "`object_type` = 'sem' AND `view_perms` = 'user'
                AND `type` IN('bool', 'textline', 'selectbox', 'radio')"
        );
        return $data_fields;
    }

    private function getSemesterFilter(array $filtering): ?Semester
    {
        if (!isset($filtering['semester'])) {
            return null;
        }
        return Semester::find($filtering['semester']);
    }


    /**
     * Finds visible courses by given module component.
     *
     * @param Modulteil $component
     * @param Semester|null $semester
     *
     * @return Course[] Visible courses assigned to module component
     */
    private function findCoursesByComponent(Modulteil $component, array $filtering): array
    {
        $course_ids = [];
        foreach ($component->lvgruppen as $lvgruppe) {
            $course_ids += $lvgruppe->courses->findBy('visible', '1')->pluck('id');
        }
        if (count($course_ids) === 0) {
            return [];
        }

        if (isset($filtering['df']) && is_array($filtering['df'])) {
            $df_course_ids = $course_ids;
            foreach ($filtering['df'] as $id => $value) {
                $df_course_ids = array_intersect($df_course_ids, \DatafieldEntryModel::findAndMapBySQL(
                    fn($df) => $df->range_id,
                    '`datafield_id` = ? AND `range_id` IN (?) AND `content` = ?',
                    [$id, $course_ids, $value]
                ));
            }

            $course_ids = array_merge_recursive($df_course_ids);
        }
        $courses = Course::findMany(
            $course_ids,
            'ORDER BY name'
        );

        $semester = $this->getSemesterFilter($filtering);
        if ($semester) {
            $courses = array_filter($courses, fn(\Course $course) => $course->isInSemester($semester));
        }

        return $courses;
    }
}
