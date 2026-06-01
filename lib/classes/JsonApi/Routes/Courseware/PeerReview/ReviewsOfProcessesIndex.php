<?php

namespace JsonApi\Routes\Courseware\PeerReview;

use Courseware\PeerReviewProcess;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\Courseware\Authority;
use JsonApi\Schemas\Courseware\PeerReview as PeerReviewSchema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use User;

/**
 * Displays all visible PeerReviewProcesses.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class ReviewsOfProcessesIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param array $args
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        /** @var ?PeerReviewProcess $process */
        $process = PeerReviewProcess::find($args['id']);
        if (!$process) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        $this->authorize($user, $process);

        $resources = $this->findReviews($user, $process);

        return $this->getPaginatedContentResponse(
            $resources->limit(...$this->getOffsetAndLimit()),
            count($resources)
        );
    }

    /**
     * @throws AuthorizationFailedException
     */
    private function authorize(User $user, PeerReviewProcess $process): void
    {
        if (!Authority::canIndexReviewsOfProcesses($user, $process)) {
            throw new AuthorizationFailedException();
        }
    }

    private function findReviews(User $user, PeerReviewProcess $process): \SimpleCollection
    {
        return $process->peer_reviews->filter(function ($peerReview) use ($user) {
            return Authority::canShowPeerReview($user, $peerReview);
        });
    }
}
