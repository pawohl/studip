<?php
namespace JsonApi\Routes\Forum;

use Forum\Category;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\JsonApiController;

class CategoryShow extends JsonApiController
{
    public function __invoke(Request $request, Response $response, $args)
    {
        $category = Category::find($args['category_id']);
        if (!$category) {
            throw new RecordNotFoundException();
        }

        $range = get_object_by_range_id($category->range_id);
        if (!$range) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        if (!Authority::canShowForum($user, $range)) {
            throw new AuthorizationFailedException();
        }

        return $this->getContentResponse($category);
    }
}
