<?php

namespace JsonApi\Routes\Feedback;

use FeedbackElement;
use FeedbackRange;
use User;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;
use JsonApi\Schemas\FeedbackElement as FeedbackElementSchema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Create a FeedbackElement.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class FeedbackElementsCreate extends JsonApiController
{
    use RangeTypeAware;
    use ValidationTrait;

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @param array $args
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $this->preparePossibleRangeTypes();

        $json = $this->validate($request);
        $range = $this->getRangeFromJson($json);
        $user = $this->getUser($request);

        if (!Authority::canCreateFeedbackElement($user, $range)) {
            throw new AuthorizationFailedException();
        }

        $feedbackElement = $this->create($user, $json);

        return $this->getCreatedResponse($feedbackElement);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameters)
     *
     * @param array $json
     * @param mixed $data
     *
     * @return string|void
     */
    protected function validateResourceDocument($json, $data)
    {
        if (!self::arrayHas($json, 'data')) {
            return 'Missing `data` member at document´s top level.';
        }
        if (FeedbackElementSchema::TYPE !== self::arrayGet($json, 'data.type')) {
            return 'Invalid `type` of document´s `data`.';
        }
        if (self::arrayHas($json, 'data.id')) {
            return 'New document must not have an `id`.';
        }

        $required = ['question', 'description', 'mode', 'results-visible', 'is-commentable', 'anonymous-entries'];
        foreach ($required as $attribute) {
            if (!self::arrayHas($json, 'data.attributes.' . $attribute)) {
                return 'Missing `' . $attribute . '` attribute.';
            }
        }

        if (!self::arrayHas($json, 'data.relationships.range')) {
            return 'Missing `range` relationship.';
        }
        if (!$this->getRangeFromJson($json)) {
            return 'Invalid `range` relationship.';
        }
    }

    private function getRangeFromJson(array $json): ?FeedbackRange
    {
        $rangeType = self::arrayGet($json, 'data.relationships.range.data.type');
        $rangeId = self::arrayGet($json, 'data.relationships.range.data.id');

        if (!isset($this->possibleRangeTypes[$rangeType])) {
            return null;
        }
        $rangeClass = $this->possibleRangeTypes[$rangeType];

        return $rangeClass::find($rangeId);
    }

    private function create(User $user, array $json): FeedbackElement
    {
        $range = $this->getRangeFromJson($json);
        return \FeedbackElement::create([
            'range_id' => $range->getId(),
            'range_type' => get_class($range),
            'user_id' => $user->id,
            'question' => self::arrayGet($json, 'data.attributes.question'),
            'description' => self::arrayGet($json, 'data.attributes.description'),
            'mode' => self::arrayGet($json, 'data.attributes.mode'),
            'results_visible' => (int) self::arrayGet($json, 'data.attributes.results-visible'),
            'commentable' => (int) self::arrayGet($json, 'data.attributes.is-commentable'),
            'anonymous_entries' => (int) self::arrayGet($json, 'data.attributes.anonymous-entries'),
            // TODO:
            'course_id' => $range->getRangeCourseId(),
        ]);
    }
}
