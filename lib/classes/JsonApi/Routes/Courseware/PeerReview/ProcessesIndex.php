<?php

namespace JsonApi\Routes\Courseware\PeerReview;

use Course;
use Courseware\PeerReviewProcess;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\JsonApiController;
use JsonApi\Routes\Courses\Authority as CoursesAuthority;
use JsonApi\Routes\Courseware\Authority;
use JsonApi\Schemas\Courseware\PeerReviewProcess as ProcessSchema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use User;

/**
 * Displays all visible PeerReviewProcesses.
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class ProcessesIndex extends JsonApiController
{
    protected $allowedFilteringParameters = ['cid'];

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
        $user = $this->getUser($request);
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        $this->validateFilters($filtering);
        $this->authorize($user, $filtering);

        $resources = empty($filtering) ? $this->findAllProcesses($user) : $this->filterProcesses($user, $filtering);

        return $this->getPaginatedContentResponse(
            array_slice($resources, ...$this->getOffsetAndLimit()),
            count($resources)
        );
    }

    /**
     * @throws BadRequestException
     */
    private function validateFilters(array $filtering): void
    {
        if (isset($filtering['cid']) && !Course::exists($filtering['cid'])) {
            throw new BadRequestException('Could not find a course matching this `filter[cid]`.');
        }
    }

    /**
     * @throws AuthorizationFailedException
     */
    private function authorize(User $user, array $filtering): void
    {
        if (!Authority::canIndexPeerReviewProcesses($user)) {
            throw new AuthorizationFailedException();
        }

        if (isset($filtering['cid'])) {
            if (
                !CoursesAuthority::canShowCourse(
                    $user,
                    Course::find($filtering['cid']),
                    CoursesAuthority::SCOPE_EXTENDED
                )
            ) {
                throw new AuthorizationFailedException();
            }
        }
    }

    private function findAllProcesses(User $user): array
    {
        return PeerReviewProcess::findByUser($user);
    }

    private function filterProcesses(User $user, array $filtering): array
    {
        if (isset($filtering['cid'])) {
            /** @var ?\Course $course */
            $course = \Course::find($filtering['cid']);

            return array_filter(PeerReviewProcess::findByCourse($course), function ($process) use ($user) {
                return Authority::canShowPeerReviewProcess($user, $process);
            });
        }

        return [];
    }
}
