<?php

namespace JsonApi\Routes\Blubber;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\TimestampTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all comments of a certain blubber thread.
 */
class CommentsByThreadIndex extends JsonApiController
{
    use FilterTrait;
    use SortTrait;
    use TimestampTrait;

    protected $allowedFilteringParameters = ['since', 'before', 'search'];
    protected $allowedPagingParameters = ['offset', 'limit'];
    protected $allowedSortFields = ['mkdate'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $this->validateFilters();

        if (!($thread = \BlubberThread::find($args['id']))) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowBlubberThread($this->getUser($request), $thread)) {
            throw new AuthorizationFailedException();
        }

        $filters = $this->getFilters();
        list($total, $comments) = $this->getComments($thread, $filters);

        return $this->getPaginatedContentResponse($comments, $total);
    }

    private function getComments(\BlubberThread $thread, array $filters)
    {
        list($offset, $limit) = $this->getOffsetAndLimit();

        $query = 'thread_id = :thread_id';
        $params = ['thread_id' => $thread->id];

        if (isset($filters['before'])) {
            $query .= ' AND mkdate < :before';
            $params['before'] = $filters['before'];
        }

        if (isset($filters['since'])) {
            $query .= ' AND mkdate > :since';
            $params['since'] = $filters['since'];
        }

        if (isset($filters['search'])) {
            $query .= ' AND content LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sortParameters = $this->getSortParameters();
        if (empty($sortParameters)) {
            $query .= ' ORDER BY mkdate';
        } elseif (array_key_exists('mkdate', $sortParameters)) {
            $query .= ' ORDER BY mkdate ' . ($sortParameters['mkdate'] ? 'ASC' : 'DESC');
        }

        $query .= ' LIMIT :limit OFFSET :offset';
        $params['limit'] = $limit + 1;
        $params['offset'] = $offset;

        $comments = \BlubberComment::findBySQL($query, $params);
        return [count($comments) <= $limit ? count($comments) + $offset : null, array_slice($comments, 0, $limit)];
    }
}
