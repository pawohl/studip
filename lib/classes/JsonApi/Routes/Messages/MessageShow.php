<?php

namespace JsonApi\Routes\Messages;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;

/**
 * Liefert die Daten der angegebenen Nachricht zurück.
 */
class MessageShow extends JsonApiController
{
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!$message = \Message::find($args['id'])) {
            throw new RecordNotFoundException();
        }

        if (!MessageAuthority::canShowMessage($this->getUser($request), $message)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($message);
    }
}
