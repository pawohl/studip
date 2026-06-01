<?php

namespace JsonApi\Routes\Themes;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\JsonApiController;
use JsonApi\Routes\ValidationTrait;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ThemesUpdate extends JsonApiController
{
    use ValidationTrait;

    public function __invoke(Request $request, Response $response, $args): Response
    {
        $resource = \Theme::find($args['id']);
        if (!$resource) {
            throw new RecordNotFoundException();
        }

        $user = $this->getUser($request);
        if (!Authority::canUpdateTheme($user)) {
            throw new AuthorizationFailedException();
        }

        $json = $this->validate($request);

        if ($resource->origin === 'custom') {
            $resource = $this->updateResource($resource, $json);
        }

        if (self::arrayGet($json, 'data.attributes.active') === true) {
            $activeThemes = \Theme::getActiveThemes();
            foreach ($activeThemes as $theme) {
                if ($theme->id !== $resource->id && $theme->type === $resource->type) {
                    $theme->active = false;
                    $theme->store();
                    $resource->active = true;
                    $resource->store();
                }
            }
        }

        return $this->getContentResponse($resource);
    }

    protected function validateResourceDocument($json, $data)
    {
        if (!self::arrayHas($json, 'data')) {
            return 'Missing `data` member at document´s top level.';
        }

        if (!self::arrayHas($json, 'data.id')) {
            return 'Document must have an `id`.';
        }
    }

    private function updateResource(\Theme $resource, array $json): \Theme
    {
        $attributes = [
            'name',
            'version',
            'studip-min-version',
            'studip-max-version',
            'author',
            'description',
            'type',
            'values'
        ];
        foreach ($attributes as $jsonKey) {
            $sormKey = strtr($jsonKey, '-', '_');
            $val = self::arrayGet($json, 'data.attributes.' . $jsonKey, '');
            if ($val) {
                $resource->$sormKey = $val;
            }
        }
        
        $resource->store();

        return $resource;
    }

}
