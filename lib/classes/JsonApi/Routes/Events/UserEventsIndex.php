<?php

namespace JsonApi\Routes\Events;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;

class UserEventsIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['timestamp'];

    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, $args)
    {
        if (!$observedUser = \User::find($args['id'])) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);

        if ($user->id !== $observedUser->id) {
            // absichtlich keine AuthorizationFailedException
            // damit unsichtbare Nutzer nicht ermittelt werden können
            throw new RecordNotFoundException();
        }

        $filtering = $this->getQueryParameters()->getFilteringParameters();
        if (isset($filtering['timestamp'])) {
            $start = max(0, (int) $filtering['timestamp']);
        } else {
            $start = (new \DateTime())->modify('midnight')->getTimestamp();
        }
        $end = strtotime('+2 weeks', $start);

        //See the RecordNotFoundException above: This route only lets user
        //retrieve the dates of their own calendar. So no permission check
        //is needed.
        $start_dt = new \DateTime();
        $start_dt->setTimestamp($start);
        $end_dt = new \DateTime();
        $end_dt->setTimestamp($end);
        $list = \CalendarDateAssignment::getEvents($start_dt, $end_dt, $user->id);
        list($offset, $limit) = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            array_slice(array_values($list), $offset, $limit),
            count($list)
        );
    }
}
