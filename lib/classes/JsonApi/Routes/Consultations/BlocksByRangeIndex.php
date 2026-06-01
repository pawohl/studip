<?php
namespace JsonApi\Routes\Consultations;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all consultation blocks of a range
 */
class BlocksByRangeIndex extends JsonApiController
{
    use FilterTrait;

    protected $allowedPagingParameters = ['offset', 'limit'];
    protected $allowedFilteringParameters = ['current', 'expired'];

    public function __invoke(Request $request, Response $response, $args)
    {
        $this->validateFilters();

        $range_id = $args['id'];
        $range_type = substr($args['type'], 0, -1); // Strips trailing plural s

        $range = \RangeFactory::createRange($range_type, $range_id);
        if ($range->isNew()) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowRange($this->getUser($request), $range)) {
            throw new AuthorizationFailedException();
        }

        [$offset, $limit] = $this->getOffsetAndLimit();

        $filters = $this->getFilters();
        $blocks = $this->getBlocks($range, $filters);

        return $this->getPaginatedContentResponse(
            $blocks->limit($offset, $limit)->getArrayCopy(),
            count($blocks)
        );
    }

    private function getBlocks(\Range $range, array $filters): \SimpleCollection
    {
        if (!$filters['current'] && !$filters['expired']) {
            return \SimpleCollection::createFromArray([]);
        }

        if ($filters['current'] && $filters['expired']) {
            return $range->consultation_blocks;
        }

        $blocks = \ConsultationBlock::findByRange($range, 'ORDER BY start', $filters['expired']);
        return \SimpleCollection::createFromArray($blocks);
    }
}
