<?php

namespace JsonApi\Routes\Files;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FilesAnnotationsIndex extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!$file = \File::find($args['id'])) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowFile($user = $this->getUser($request), $file)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse(
            \File::findBySQL(
                "`metadata` LIKE :annotationref ORDER BY `name`",
                ['annotationref' => '%"annotations:original_file_id":"' .$file->id . '"%']
            )
        );
    }
}
