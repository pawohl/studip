<?php

namespace JsonApi\Routes\Courses;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\JsonApiController;

class CoursesIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['q', 'fields', 'semester', 'category', 'scope_choose', 'range_choose', 'df'];

    protected $allowedIncludePaths = [
        'blubber-threads',
        'end-semester',
        'events',
        'feedback-elements',
        'forum-categories',
        'institute',
        'memberships',
        'news',
        'participating-institutes',
        'sem-class',
        'sem-type',
        'start-semester',
        'status-groups',
        'wiki-pages',
    ];

    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!Authority::canIndexCourses($user = $this->getUser($request))) {
            throw new AuthorizationFailedException();
        }

        if ($error = $this->validateFilters()) {
            throw new BadRequestException($error);
        }

        $courseIds = $this->searchCourses($user, $this->getContextFilters());

        list($offset, $limit) = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            $this->getCourses(array_slice($courseIds, $offset, $limit)),
            count($courseIds)
        );
    }

    private function validateFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        // keyword aka q
        if (isset($filtering['q']) && mb_strlen($filtering['q']) < 3) {
            return 'Search term too short.';
        }

        // fields
        if (isset($filtering['fields'])) {
            $validFields = ['all', 'title_lecturer_number', 'title', 'sub_title', 'lecturer', 'number', 'comment', 'scope'];
            if (!in_array($filtering['fields'], $validFields)) {
                return 'Filter "fields" has to be one of: '.join(', ', $validFields);
            }
        }

        // semester
        if (isset($filtering['semester'])) {
            $semester = \Semester::find($filtering['semester']);
            if (!$semester) {
                return 'Invalid "semester".';
            }
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
    }

    private function getContextFilters()
    {
        $defaults = [
            'q' => '%%%',
            'fields' => 'all', // Titel, Lehrende...
            'semester' => 'all', // Semester
            'category' => null, // SEM_CLASS
            'scope_choose' => null, // Studienbereiche
            'range_choose' => null, // Einrichtungen,
            'combination' => 'OR', // OR|AND
        ];

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        return array_merge($defaults, $filtering);
    }

    private function getCourses(array $course_ids): array
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];
        if (isset($filtering['df']) && is_array($filtering['df'])) {
            $df_where = [];
            $params = [
                $course_ids
            ];
            foreach ($filtering['df'] as $id => $value) {
                $df_where[] = ' (`datafields_entries`.`datafield_id` = ? AND `datafields_entries`.`content` = ?) ';
                $params[] = $id;
                $params[] = $value;
            }
            return \Course::findBySQL("JOIN `datafields_entries`
                ON `seminare`.`seminar_id` = `datafields_entries`.`range_id`
                WHERE `seminare`.`seminar_id` IN (?)
                AND " .
                implode('AND', $df_where),
                $params);
        } else {
            return \Course::findMany($course_ids);
        }
    }

    /**
     * Get ids of accepted datafields for current user.
     * Only simple types of bool, textline, selectbox and radio with global
     * visibility for all users are accepted.
     *
     * @return array
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

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    private function searchCourses(\User $user, array $filters)
    {

        $visibleOnly = !(is_object($GLOBALS['perm'])
                         && $GLOBALS['perm']->have_perm(\Config::Get()->SEM_VISIBILITY_PERM, $user->id));
        $searchHelper = new \StudipSemSearchHelper();
        $searchHelper->setParams(
            [
                'quick_search' => $filters['q'],
                'qs_choose' => $filters['fields'],
                'sem' => $filters['semester'],
                'category' => $filters['category'],
                'scope_choose' => $filters['scope_choose'],
                'range_choose' => $filters['range_choose'],
            ],
            $visibleOnly
        );
        return $searchHelper->doSearch();
    }
}
