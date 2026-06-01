<?php

namespace JsonApi\Routes\Courseware;

use Courseware\StructuralElement;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use Neomerx\JsonApi\Contracts\Http\ResponsesInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays all descendants of a structural element.
 */
class DescendantsOfStructuralElementsIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        /** @var ?StructuralElement $resource */
        $resource = StructuralElement::find($args['id']);
        if (!$resource) {
            throw new RecordNotFoundException();
        }

        if (!Authority::canShowStructuralElement($user = $this->getUser($request), $resource)) {
            throw new AuthorizationFailedException();
        }

        $descendants = $resource->findDescendants($user);

        list($offset, $limit) = $this->getOffsetAndLimit();
        $page = array_slice($descendants, $offset, $limit);
        $total = count($descendants);

        // compute ETag, compare it and short-cut this route if they match
        $etag = $this->getETag($user, $resource, $descendants);
        if ($request->hasHeader('If-None-Match')) {
            $sentETag = $request->getHeaderLine('If-None-Match');
            if ($etag === $sentETag) {
                return $response->withStatus(304)->withHeader('Cache-Control', 'private, must-revalidate');
            }
        }

        return $this->getPaginatedContentResponse(
            $page,
            $total,
            ResponsesInterface::HTTP_OK,
            [],
            [],
            [
                'Cache-Control' => 'private, must-revalidate',
                'ETag' => $etag,
            ]
        );
    }

    private function getEtag(\User $user, StructuralElement $resource, array $elements): string
    {
        $ids = join(',', array_column($elements, 'id'));
        $maxChdate = count($elements) ? max(array_column($elements, 'chdate')) : '';

        $payload = [$user->id, $ids, $maxChdate];

        return 'W/"' . md5(join(',', $payload)) . '"';
    }
}
