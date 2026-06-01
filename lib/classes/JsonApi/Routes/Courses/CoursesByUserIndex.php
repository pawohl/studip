<?php

namespace JsonApi\Routes\Courses;

use Course;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\Users\Authority as UsersAuthority;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Semester;
use User;

class CoursesByUserIndex extends JsonApiController
{
    protected $allowedPagingParameters = ['offset', 'limit'];

    protected $allowedFilteringParameters = ['semester'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        $observer = $this->getUser($request);

        if (!$user) {
            throw new RecordNotFoundException();
        }

        if (!UsersAuthority::canShowUser($observer, $user)) {
            throw new AuthorizationFailedException();
        }

        $error = $this->validateFilters();
        if ($error) {
            throw new BadRequestException($error);
        }

        if ($observer->id === $user->id || $GLOBALS['perm']->have_perm('root', $observer->id)) {
            $permission = null;
        } else {
            $permission = 'dozent';
        }

        $courses = $this->findCoursesByUser(
            $user,
            $this->getSemesterFilter(),
            $permission
        );
        [$offset, $limit] = $this->getOffsetAndLimit();

        return $this->getPaginatedContentResponse(
            array_slice($courses, $offset, $limit),
            count($courses)
        );
    }

    private function validateFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        // semester
        if (
            !empty($filtering['semester'])
            && !Semester::exists($filtering['semester'])
        ) {
            return 'Invalid "semester".';
        }
    }

    private function getSemesterFilter(): ?Semester
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters();

        if (!isset($filtering['semester'])) {
            return null;
        }

        return Semester::find($filtering['semester']);
    }


    /**
     * @param User $user
     * @param Semester|null $semester
     * @param string|null $permission
     *
     * @return Course[]
     */
    private function findCoursesByUser(User $user, ?Semester $semester, ?string $permission): array
    {
        if ($permission) {
            $courses = Course::findBySQL('JOIN seminar_user USING(Seminar_id) WHERE user_id = ? AND seminare.visible = 1 AND seminar_user.status = ?', [$user->id, $permission]);
        } else {
            $courses = Course::findBySQL('JOIN seminar_user USING(Seminar_id) WHERE user_id = ?', [$user->id]);
        }

        if ($semester) {
            $courses = array_filter($courses, fn(Course $course) => $course->isInSemester($semester));
        }

        return $courses;
    }
}
