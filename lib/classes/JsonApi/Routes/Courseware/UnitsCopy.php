<?php

namespace JsonApi\Routes\Courseware;

use JsonApi\NonJsonApiController;
use Courseware\Unit;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\Errors\UnprocessableEntityException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
* Copy an courseware unit into a course or users contents
*
* @author  Ron Lucke <lucke@elan-ev.de>
* @license GPL2 or any later version
*
* @since   Stud.IP 5.3
*/

class UnitsCopy extends NonJsonApiController
{
    public function __invoke(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody()['data'];

        $sourceUnit = Unit::find($args['id']);
        $user = $this->getUser($request);
        $rangeId = $data['rangeId'];
        $rangeType = $data['rangeType'];
        $modified = $data['modified'];
        $duplicate = $data['duplicate'];

        if (!$sourceUnit) {
            throw new RecordNotFoundException('Unknown source unit');
        }

        if (!Authority::canUpdateUnit($user, $sourceUnit)) {
            throw new AuthorizationFailedException();
        }

        try {
            $range = \RangeFactory::createRange($rangeType, $rangeId);
        } catch (\Exception $e) {
            throw new RecordNotFoundException('Range could not be found');
        }

        if (!Authority::canCreateUnit($user, $range)) {
            throw new AuthorizationFailedException();
        }

        $newUnit = $sourceUnit->copy($user, $rangeId, $rangeType, $modified, $duplicate);

        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write((string) json_encode($newUnit));

        return $response;
    }
}
