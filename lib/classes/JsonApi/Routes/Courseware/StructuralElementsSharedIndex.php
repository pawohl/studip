<?php

namespace JsonApi\Routes\Courseware;

use Courseware\StructuralElement;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Class StructuralElementsSharedIndex.
 */
class StructuralElementsSharedIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $user = $this->getUser($request);
        if (!Authority::canIndexStructuralElementsShared($user)) {
            throw new AuthorizationFailedException();
        }

        list($offset, $limit) = $this->getOffsetAndLimit();
        $elements = [];
        $contents = StructuralElement::findBySQL(
            'range_id != ? AND range_type = ? ORDER BY mkdate ASC',
            [$user->id, 'user']
        );

        foreach ($contents as $content) {
            if (count($content->content_approval) === 0) {
                continue;
            }

            $add_content = false;

            foreach ($content->content_approval['users'] as $listedUserPerm) {
                if ($listedUserPerm['id'] == $user->id && !empty($listedUserPerm['read'])) {
                    $add_content = true;
                }
            }

            if ($add_content) {
                $elements[] = $content;
            }
        }

        $resources = [];
        foreach ($elements as $element) {
            $has_parent = array_column($elements, null, 'id')[$element->parent_id] ?? false;
            if (!$has_parent) {
                $resources[] = $element;
            }
        }

        return $this->getPaginatedContentResponse($resources, count($resources));
    }
}
