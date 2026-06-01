<?php

namespace JsonApi\Routes\Courseware;

use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\RecordNotFoundException;
use JsonApi\Errors\UnsupportedRequestError;
use JsonApi\NonJsonApiController;
use Courseware\Unit;
use Courseware\Certificate;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Displays a certificate for a given courseware.
 */
class CertificateShow extends NonJsonApiController
{
    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        if (!\Config::get()->COURSEWARE_CERTIFICATES_ENABLE) {
            throw new UnsupportedRequestError();
        }

        $unit = Unit::find($args['id']);
        if (!$unit) {
            throw new RecordNotFoundException('Unit could not be found');
        }

        $user = null;
        if (isset($args['user'])) {
            $user = \User::find($args['user']);
            if (!$user) {
                throw new RecordNotFoundException('User could not be found');
            }
        }

        $config = $unit->config;

        // No user given: create a preview PDF certificate
        if (!$user) {
            $file = Certificate::createPDF($unit, time(), null, $config['certificate']['image'] ?? '');

            $response->getBody()->write(file_get_contents($file));

            return $response->withHeader('Content-type', 'application/pdf');
        // User ID given: check if a certificate exists for the given unit and output the file ID.
        } else {
            $certificate = Certificate::findOneBySQL(
                "`unit_id` = :unit AND `user_id` = :user",
                ['unit' => $unit->id, 'user' => $user->id]
            );
            if (!$certificate) {
                throw new RecordNotFoundException();
            }

            $response->getBody()->write($certificate->fileref_id);

            return $response->withHeader('Content-type', 'text/plain');
        }
    }
}
