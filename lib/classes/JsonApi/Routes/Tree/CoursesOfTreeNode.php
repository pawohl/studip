<?php

namespace JsonApi\Routes\Tree;

use JsonApi\Errors\BadRequestException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use StudipTreeNode;

class CoursesOfTreeNode extends JsonApiController
{
    protected $allowUnrecognizedParams = true;
    protected $allowedFilteringParameters = [
        'q',
        'semester',
        'semclass',
        'recursive',
        'ids',
    ];
    protected $allowedPagingParameters = ['offset', 'limit'];

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        /**
         * @var class-string<StudipTreeNode> $classname
         */
        [$classname, $id] = explode('_', $args['id']);
        if (!class_exists($classname) || !is_subclass_of($classname, StudipTreeNode::class)) {
            throw new BadRequestException('Invalid class name.');
        }

        $node = $classname::getNode($id);
        if (!$node) {
            throw new RecordNotFoundException();
        }

        $error = $this->validateFilters();
        if ($error) {
            throw new BadRequestException($error);
        }

        $filters = $this->getContextFilters();

        [$offset, $limit] = $this->getOffsetAndLimit();
        $courses = \SimpleCollection::createFromArray(
            $node->getCourses(
                $filters['semester'],
                (int) $filters['semclass'],
                $filters['q'],
                (bool) $filters['recursive'],
                $filters['ids']
            )
        );

        return $this->getPaginatedContentResponse(
            $courses->limit($offset, $limit),
            count($courses)
        );
    }

    private function validateFilters()
    {
        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        // keyword aka q
        if (isset($filtering['q']) && mb_strlen($filtering['q']) < 3) {
            return 'Search term too short.';
        }

        // semester
        if (isset($filtering['semester']) && $filtering['semester'] !== 'all') {
            $semester = \Semester::find($filtering['semester']);
            if (!$semester) {
                return 'Invalid "semester".';
            }
        }

        // course category
        if (!empty($filtering['semclass'])) {
            $semclass = \SeminarCategories::Get($filtering['semclass']);
            if (!$semclass) {
                return 'Invalid "course category".';
            }
        }
    }

    private function getContextFilters(): array
    {
        $defaults = [
            'q' => '',
            'semester' => 'all',
            'semclass' => 0,
            'recursive' => false,
            'ids' => []
        ];

        $filtering = $this->getQueryParameters()->getFilteringParameters() ?: [];

        return array_merge($defaults, $filtering);
    }
}
