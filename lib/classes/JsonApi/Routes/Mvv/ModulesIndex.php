<?php

namespace JsonApi\Routes\Mvv;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\JsonApiController;
use Modul;
use Psr\Http\Message\{
    ServerRequestInterface as Request,
    ResponseInterface as Response
};

class ModulesIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['q', 'institute', 'semester', 'section', 'stat'];

    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args): Response
    {
        if (!Authority::canIndexModules($this->getUser($request))) {
            throw new AuthorizationFailedException();
        }

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];
        $error = $this->validateFilters($filtering);
        if ($error) {
            throw new BadRequestException($error);
        }

        [$offset, $limit] = $this->getOffsetAndLimit();
        return $this->getPaginatedContentResponse(
            $this->getModules($filtering, $offset, $limit),
            $this->countModules($filtering)
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

        // section
        if (isset($filtering['section']) && !\StgteilAbschnitt::exists($filtering['section'])) {
            return 'Filter `section` must be a valid id';
        }

        // semester
        if (isset($filtering['semester']) && !\Semester::exists($filtering['semester'])) {
            return 'Filter `semester` must be a valid id.';
        }

        // stat
        $allowed_module_stats = array_keys($GLOBALS['MVV_MODUL']['STATUS']['values']);
        if (isset($filtering['stat']) && !in_array($filtering['stat'], $allowed_module_stats)) {
            return 'Filter `stat` has no valid value. Must be one of these: ' . implode(', ', $allowed_module_stats);
        }
    }

    private function countModules(array $filtering): int
    {
        [$condition, $parameters] = $this->getConditionAndParameters($filtering);

        return Modul::countBySql(
            $condition,
            $parameters
        );
    }

    private function getModules(array $filtering, int $offset, int $limit): array
    {
        [$condition, $parameters] = $this->getConditionAndParameters($filtering);

        $condition .= ' ORDER BY `mvv_modul`.`code` ASC, `mvv_modul_deskriptor`.`bezeichnung` ASC
                    LIMIT :limit OFFSET :offset';
        $parameters[':offset'] = $offset;
        $parameters[':limit'] = $limit;

        return Modul::findBySQL($condition, $parameters);
    }

    private function getConditionAndParameters(array $filtering): array
    {
        $parameters = [];

        $join = '';
        $where = ' 1 ';
        if (isset($filtering['institute'])) {
            $where .= ' AND `institut_id` = :institute ';
            $parameters[':institute'] = $filtering['institute'];
        }
        if (isset($filtering['section'])) {
            $join .= 'LEFT JOIN `mvv_stgteilabschnitt_modul` USING(`modul_id`) ';
            $where .= ' AND `mvv_stgteilabschnitt_modul`.`abschnitt_id` = :section';

            $parameters[':section'] = $filtering['section'];
        }
        if (isset($filtering['semester'])) {
            $semester = \Semester::find($filtering['semester']);
            $join .= 'LEFT JOIN `semester_data` AS `start_sem`
                        ON (`mvv_modul`.`start` = `start_sem`.`semester_id`)
                    LEFT JOIN `semester_data` AS `end_sem`
                        ON (`mvv_modul`.`end` = `end_sem`.`semester_id`) ';
            $where .= ' AND (`start_sem`.`beginn` <= :semester_end OR ISNULL(`start_sem`.`beginn`))
                        AND (`end_sem`.`ende` >= :semester_start OR ISNULL(`end_sem`.`ende`))';

            $parameters[':semester_start'] = $semester->beginn;
            $parameters[':semester_end'] = $semester->ende;
        }
        $join .= 'LEFT JOIN `mvv_modul_deskriptor` USING(`modul_id`) ';
        if (isset($filtering['q'])) {
            $where .= " AND (`mvv_modul_deskriptor`.`bezeichnung` LIKE CONCAT('%', :q, '%') OR `mvv_modul`.`code` LIKE CONCAT('%', :q, '%')) ";
            $parameters[':q'] = $filtering['q'];
        }
        if (isset($filtering['stat'])) {
            $where .= " AND `stat` = :stat ";
            $parameters[':stat'] = $filtering['stat'];
        }

        return [
            $join ? $join . ' WHERE ' . $where : $where,
            $parameters
        ];
    }
}
