<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Clipboard;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all clipboards
 * 
 * @author  Ron Lucke <lucke@elan-ev.de>
 * @license GPL2 or any later version
 *
 * @since   Stud.IP 5.4
 */
class ClipboardsIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = $this->getUser($request);
        if (!Authority::canIndexClipboards($user)) {
            throw new AuthorizationFailedException();
        }

        list($offset, $limit) = $this->getOffsetAndLimit();

        $total = Clipboard::countBySQL('1');
        $resources = Clipboard::findBySQL("1 ORDER BY mkdate LIMIT {$offset}, {$limit}");

        return $this->getPaginatedContentResponse($resources, $total);
    }
}