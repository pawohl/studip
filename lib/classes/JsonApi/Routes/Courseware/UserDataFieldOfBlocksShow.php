<?php

namespace JsonApi\Routes\Courseware;

use Courseware\Block;
use Courseware\UserDataField;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays the user data field of a block.
 */
class UserDataFieldOfBlocksShow extends JsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!($block = Block::find($args['id']))) {
            throw new RecordNotFoundException();
        }
        // this is automatically scoped to the requesting user
        // so we don't need to worry about the accessing the user data fields.
        $resource = UserDataField::getUserDataField($user = $this->getUser($request), $block);

        if (!Authority::canShowUserDataField($user, $resource)) {
            throw new AuthorizationFailedException();
        }

        // however, as it is intended to list all user data fields of the block, we get it here and return it back.
        $resources = UserDataField::findBySql('block_id = ?', [$block->id]);

        return $this->getContentResponse($resources);
    }
}
