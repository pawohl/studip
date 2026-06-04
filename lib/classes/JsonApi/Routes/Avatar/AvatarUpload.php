<?php

namespace JsonApi\Routes\Avatar;

use Avatar;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\Routes\ValidationTrait;
use JsonApi\NonJsonApiController;
use JsonApi\Routes\Files\RoutesHelperTrait as FilesRoutesHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Create an Avatar.
 */
class AvatarUpload extends NonJsonApiController
{
    use ValidationTrait;
    use AvatarHelpers;
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $user = $this->getUser($request);
        $json = $this->validate($request);

        $range_id = self::arrayGet($json, 'data.range-id');
        $range_type = self::arrayGet($json, 'data.range-type');

        $range = self::getRange($range_id, $range_type);
        if (!$range) {
            throw new RecordNotFoundException('Unknown range given');
        }

        if (!Authority::canEditAvatarOfRange($user, $range)) {
            throw new AuthorizationFailedException();
        }

        // Extract avatar image data
        $imgdata_string = self::arrayGet($json, 'data.image');
        [, $imgdata_part] = explode(';', $imgdata_string);
        [, $imgdata_base64] = explode(',', $imgdata_part);
        $imgdata = base64_decode($imgdata_base64);

        if (strlen($imgdata) > Avatar::MAX_FILE_SIZE) {
            throw new BadRequestException('Image file is too big.');
        }

        // Write data to file.
        $filename = $GLOBALS['TMP_PATH'] . '/avatar-' . $range_id . '.webp';
        file_put_contents($filename, $imgdata);

        // Use new image file for avatar creation.
        $class = self::getAvatarClassForRange($range);
        $class::getAvatar($range_id)->createFrom($filename);

        return $response->withStatus(201);
    }

    protected function validateResourceDocument($json, $data)
    {
        if (!self::arrayHas($json, 'data')) {
            return 'Missing `data` member at document´s top level.';
        }
        if (!self::arrayHas($json, 'data.range-id')) {
            return 'New avatar must have an `range-id`.';
        }
        if (!self::arrayHas($json, 'data.range-type')) {
            return 'New avatar must have a `range-type`.';
        }
        if (!self::arrayHas($json, 'data.image')) {
            return 'New avatar must have a `image`.';
        }
    }
}
