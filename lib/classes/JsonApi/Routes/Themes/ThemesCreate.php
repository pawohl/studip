<?php

namespace JsonApi\Routes\Themes;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ThemesCreate extends JsonApiController
{
    use ValidationTrait;

    public function __invoke(Request $request, Response $response, $args): Response
    {
        $user = $this->getUser($request);
        if (!Authority::canCreateTheme($user)) {
            throw new AuthorizationFailedException();
        }

        $json = $this->validate($request);
        $resource = $this->createResource($json, $user);

        return $this->getContentResponse($resource);
    }

    protected function validateResourceDocument($json, $data)
    {
        if (!self::arrayHas($json, 'data')) {
            return 'Missing `data` member at document´s top level.';
        }
    }

    private function createResource(array $json, \User $user): \Theme
    {
        $theme = \Theme::build([
            'name' => self::arrayGet($json, 'data.attributes.name', 'custom theme'),
            'origin' => 'custom',
            'version' => '1.0',
            'studip_min_version' => self::arrayGet($json, 'data.attributes.studip-min-version', '6.1'),
            'studip_max_version' => self::arrayGet($json, 'data.attributes.studip-max-version', '6.1'),
            'author' => $user->getFullname(),
            'description' => self::arrayGet($json, 'data.attributes.description', 'custom theme'),
            'type' => self::arrayGet($json, 'data.attributes.type', 'light'),
            'values' => json_encode(self::arrayGet($json, 'data.attributes.values', []))
        ]);

        $theme->store();

        return $theme;
    }
}
