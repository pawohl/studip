<?php

namespace JsonApi\Routes\Files;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

class SubfoldersIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $folder = \FileManager::getTypedFolder($args['id']);
        if (!$folder) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);

        if (!Authority::canShowFolder($user, $folder)) {
            throw new AuthorizationFailedException();
        }

        $subfolders = array_reduce(
            $folder->subfolders->getArrayCopy(),
            function ($result, $subfolder) use ($user) {
                $folder = $subfolder->getTypedFolder();

                if (Authority::canShowFolder($user, $folder)) {
                    $result[] = $folder;
                }

                return $result;
            },
            []
        );

        list($offset, $limit) = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            array_slice($subfolders, $offset, $limit),
            count($subfolders)
        );
    }
}
