<?php
namespace JsonApi\Routes\Consultations;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SlotsByBlockIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, $args)
    {
        $block = \ConsultationBlock::find($args['id']);
        if (!$block) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowBlock($this->getUser($request), $block)) {
            throw new AuthorizationFailedException();
        }

        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            $block->slots->limit($offset, $limit),
            count($block->slots)
        );
    }
}
