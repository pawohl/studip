<?php

namespace JsonApi\Routes\Feedback;

use FeedbackElement;
use FeedbackEntry;
use User;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;
use JsonApi\Schemas\FeedbackEntry as FeedbackEntrySchema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Update a FeedbackEntry.
 *
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class FeedbackEntriesUpdate extends JsonApiController
{
    use RatingHelper;
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
        $resource = \FeedbackEntry::find($args['id']);
        if (!$resource) {
            throw new RecordNotFoundException();
        }

        $json = $this->validate($request);
        $user = $this->getUser($request);

        if (!Authority::canUpdateFeedbackEntry($user, $resource)) {
            throw new AuthorizationFailedException();
        }

        $feedbackEntry = $this->update($resource, $json);

        return $this->getContentResponse($feedbackEntry);
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
        if (FeedbackEntrySchema::TYPE !== self::arrayGet($json, 'data.type')) {
            return 'Invalid `type` of document´s `data`.';
        }
        if (!self::arrayHas($json, 'data.id')) {
            return 'An existing document must have an `id`.';
        }

        $required = ['rating'];
        foreach ($required as $attribute) {
            if (!self::arrayHas($json, 'data.attributes.' . $attribute)) {
                return 'Missing `' . $attribute . '` attribute.';
            }
        }
    }

    private function update(FeedbackEntry $feedbackEntry, array $json): FeedbackEntry
    {
        $feedbackEntry->rating = $this->getRating(
            $feedbackEntry->feedback,
            (int) self::arrayGet($json, 'data.attributes.rating')
        );
        if ($feedbackEntry->feedback->commentable && self::arrayHas($json, 'data.attributes.comment')) {
            $feedbackEntry->comment = self::arrayGet($json, 'data.attributes.comment');
        }
        $feedbackEntry->anonymous = (int) self::arrayGet($json, 'data.attributes.anonymous');
        $feedbackEntry->store();

        return $feedbackEntry;
    }
}
