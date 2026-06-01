<?php

namespace JsonApi;

use JsonApi\JsonApiIntegration\JsonApiTrait;
use JsonApi\JsonApiIntegration\QueryParserInterface;
use JsonApi\Middlewares\Authentication;
use Neomerx\JsonApi\Contracts\Encoder\EncoderInterface;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Http\Headers\HeaderParametersParserInterface;
use Neomerx\JsonApi\Contracts\Http\Headers\MediaTypeInterface;
use Neomerx\JsonApi\Contracts\Http\ResponsesInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaInterface;
use Neomerx\JsonApi\Http\Headers\MediaType;
use Neomerx\JsonApi\Schema\Link;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Ein JsonApiController ist die einfachste Möglichkeit, eine eigene
 * JSON-API-Route zu erstellen.
 *
 * Dazu erstellt man eine Unterklasse von JsonApiController und kann
 * darin __invoke oder andere Methoden definieren und diese in der
 * RouteMap registrieren.
 *
 * Wenn man auf den JsonApiController verzichten möchte, muss man den
 * JsonApiTrait in seiner eigenen Lösung einbinden und außerdem den
 * Dependency Container als Instanzvariabel $this->container eintragen
 * und die Methode JsonApiTrait::initJsonApiSupport aufrufen.
 *
 * Diese Klasse hier übernimmt all diese Aufgaben selbst.
 *
 * @see \JsonApi\JsonApiIntegration\JsonApiTrait
 * @see \JsonApi\RouteMap
 */
class JsonApiController
{
    /**
     * Der Konstruktor.
     */
    public function __construct(
        protected \Slim\App $app,
        protected ContainerInterface $container,
        protected FactoryInterface $factory,
        protected EncoderInterface $encoder,
        protected SchemaContainerInterface $schemaContainer,
        protected QueryParserInterface $queryParser,
        protected ResponseFactoryInterface $responseFactory,
        HeaderParametersParserInterface $headerParametersParser
    ) {
        $queryChecker = new JsonApiIntegration\QueryChecker(
            $this->allowUnrecognizedParams,
            $this->allowedIncludePaths,
            $this->allowedFieldSetTypes,
            $this->allowedSortFields,
            $this->allowedPagingParameters,
            $this->allowedFilteringParameters
        );

        $queryChecker->checkQuery($queryParser);

        $this->checkAcceptHeader($headerParametersParser);
        $this->checkContentTypeHeader($headerParametersParser);
    }

    /**
     * If unrecognized parameters should be allowed in input parameters.
     *
     * @var bool
     */
    protected $allowUnrecognizedParams = false;

    /**
     * A list of allowed include paths in input parameters.
     *
     * Empty array [] means clients are not allowed to specify include paths and 'null' means all paths are allowed.
     *
     * @var string[]|null
     */
    protected $allowedIncludePaths = null;

    /**
     * A list of JSON API types which clients can sent field sets to.
     *
     * Possible values
     *
     * $allowedFieldSetTypes = null; // <-- for all types all fields are allowed
     *
     * $allowedFieldSetTypes = []; // <-- non of the types and fields are allowed
     *
     * $allowedFieldSetTypes = [
     *      'people'   => null,              // <-- all fields for 'people' are allowed
     *      'comments' => [],                // <-- no fields for 'comments' are allowed (all denied)
     *      'posts'    => ['title', 'body'], // <-- only 'title' and 'body' fields are allowed for 'posts'
     * ];
     *
     * @var string[]|null
     */
    protected $allowedFieldSetTypes = null;

    /**
     * A list of allowed sort field names in input parameters.
     *
     * Empty array [] means clients are not allowed to specify sort fields and 'null' means all fields are allowed.
     *
     * @var string[]|null
     */
    protected $allowedSortFields = [];

    /**
     * A list of allowed pagination input parameters (e.g 'number', 'size', 'offset' and etc).
     *
     * Empty array [] means clients are not allowed to specify paging and 'null' means all parameters are allowed.
     *
     * @var string[]|null
     */
    protected $allowedPagingParameters = [];

    /**
     * A list of allowed filtering input parameters.
     *
     * Empty array [] means clients are not allowed to specify filtering and 'null' means all parameters are allowed.
     *
     * @var string[]|null
     */
    protected $allowedFilteringParameters = [];

    // ***** RESPONSE GENERATORS *****

    /**
     * Get response with HTTP code only.
     */
    protected function getCodeResponse(int $statusCode, array $headers = []): Response
    {
        $responses = $this->getResponses();

        return $responses->getCodeResponse($statusCode, $headers);
    }

    /**
     * Get response with meta information only.
     *
     * @param array|object $meta       Meta information
     * @param int          $statusCode
     */
    protected function getMetaResponse($meta, $statusCode = ResponsesInterface::HTTP_OK, array $headers = []): Response
    {
        $responses = $this->getResponses();

        return $responses->getMetaResponse($meta, $statusCode, $headers);
    }

    /**
     * Get response with regular JSON API Document in body.
     *
     * @param object|array $data
     * @param int          $statusCode
     * @param array|null   $links
     * @param mixed        $meta
     */
    protected function getContentResponse(
        $data,
        $statusCode = ResponsesInterface::HTTP_OK,
        $links = [],
        $meta = [],
        array $headers = []
    ): Response {
        $responses = $this->getResponses($links, $meta);

        return $responses->getContentResponse($data, $statusCode, $headers);
    }

    /**
     * Get response with only resource identifiers.
     *
     * @param object|array $data
     * @param array|null   $links
     * @param mixed        $meta
     */
    protected function getIdentifiersResponse($data, $links = [], $meta = [], array $headers = []): Response
    {
        $responses = $this->getResponses($links, $meta);
        $statusCode = ResponsesInterface::HTTP_OK;

        return $responses->getIdentifiersResponse($data, $statusCode, $headers);
    }

    /**
     * Get response with paginated resource identifiers.
     *
     * @param object|array $data
     * @param ?int         $total
     * @param array|null   $links
     * @param mixed        $meta
     */
    protected function getPaginatedIdentifiersResponse(
        $data,
        $total,
        $links = [],
        $meta = [],
        array $headers = []
    ): Response {
        list($offset, $limit) = $this->getOffsetAndLimit();

        $meta['page'] = [
            'offset' => (int) $offset,
            'limit' => (int) $limit,
        ];

        if (isset($total)) {
            $meta['page']['total'] = (int) $total;
        }

        $paginator = new JsonApiIntegration\Paginator($total, $offset, $limit);

        foreach (words('first last prev next') as $rel) {
            if (list($off, $lim) = $paginator->{'get'.ucfirst($rel).'PageOffsetAndLimit'}()) {
                $links[$rel] = $this->createLink($off, $lim);
            }
        }

        $responses = $this->getResponses($links, $meta);
        $statusCode = ResponsesInterface::HTTP_OK;

        return $responses->getIdentifiersResponse($data, $statusCode, $headers);
    }

    /**
     * @param object     $resource
     * @param array|null $links
     * @param mixed      $meta
     */
    protected function getCreatedResponse($resource, $links = [], $meta = [], array $headers = []): Response
    {
        $responses = $this->getResponses($links, $meta);
        $urlPrefix = $this->container->get('json-api-integration-urlPrefix');
        $url = $this->schemaContainer
            ->getSchema($resource)
            ->getSelfLink($resource)
            ->getStringRepresentation($urlPrefix);

        return $responses->getCreatedResponse($resource, $url, $headers);
    }

    /**
     * @param object|array $data
     * @param ?int         $total
     * @param int          $statusCode
     * @param array|null   $links
     * @param mixed        $meta
     */
    protected function getPaginatedContentResponse(
        $data,
        $total,
        $statusCode = ResponsesInterface::HTTP_OK,
        $links = [],
        $meta = [],
        array $headers = []
    ): Response {
        list($offset, $limit) = $this->getOffsetAndLimit();

        $meta['page'] = [
            'offset' => (int) $offset,
            'limit' => (int) $limit,
        ];

        if (isset($total)) {
            $meta['page']['total'] = (int) $total;
        }

        $paginator = new JsonApiIntegration\Paginator($total, $offset, $limit);

        foreach (words('first last prev next') as $rel) {
            if (list($off, $lim) = $paginator->{'get'.ucfirst($rel).'PageOffsetAndLimit'}()) {
                $links[$rel] = $this->createLink($off, $lim);
            }
        }

        $responses = $this->getResponses($links, $meta);

        return $responses->getContentResponse($data, $statusCode, $headers);
    }

    protected function getQueryParameters(): QueryParserInterface
    {
        return $this->queryParser;
    }

    /**
     * Liefert Offset und Limit aus den Request-Parametern zurück.
     *
     * @param int $offsetDefault optional; gibt den Standard-Offset
     *                           an, falls dieser Wert nicht im Request gesetzt ist
     * @param int $limitDefault  optional; gibt das Standard-Limit an,
     *                           falls dieser Wert nicht im Request gesetzt ist
     *
     * @return array<int> {
     *
     *     @var int $offset der im Request gesetzte Offset oder
     *     ansonsten der Default-Wert 0
     *     @var int $limit das im Request gesetzte Limit oder
     *     ansonsten der Default-Wert 30
     * }
     */
    protected function getOffsetAndLimit($offsetDefault = 0, $limitDefault = 30): array
    {
        $params = iterator_to_array($this->queryParser->getPagination());

        return [
            $params && array_key_exists('offset', $params) ? (int) $params['offset'] : $offsetDefault,
            $params && array_key_exists('limit', $params) ? (int) $params['limit'] : $limitDefault,
        ];
    }

    // Hier wird der aktuelle Link zusätzlich noch mit Paginierung ausgestattet
    private function createLink(int $offset, int $limit): Link
    {
        $request = $this->container->get('request');
        $queryParams = $request->getQueryParams();
        $queryParams['page']['offset'] = $offset;
        $queryParams['page']['limit'] = $limit;

        $uri = $request->getUri()->withQuery(http_build_query($queryParams));

        $path = $uri->getPath();
        $query = $uri->getQuery();
        $fragment = $uri->getFragment();

        $uriString = $path.($query ? '?'.$query : '').($fragment ? '#'.$fragment : '');

        return new Link(false, $uriString, false);
    }

    /**
     * Gibt null oder das User-Objekt des "eingeloggten" Nutzers zurück.
     *
     * @param Request $request Request der eingehende Request
     *
     * @return null|\User entweder null oder das User-Objekt des "eingeloggten"
     *                    Nutzers
     */
    public function getUser(Request $request)
    {
        return $request->getAttribute(Authentication::USER_KEY);
    }

    /**
     * Gibt das Schema zu einer beliebigen Ressource zurück.
     *
     * @param mixed $resource die Ressource, zu der das Schema geliefert werden soll
     *
     * @return SchemaInterface das Schema zur Ressource
     */
    protected function getSchema($resource): SchemaInterface
    {
        return $this->schemaContainer->getSchema($resource);
    }

    protected function getResponses(array $links = [], array $meta = []): ResponsesInterface
    {
        $paths = $this->queryParser->getIncludePaths();
        $fieldSets = iterator_to_array($this->queryParser->getFields());

        $encoder = $this->encoder
            ->withIncludedPaths($paths)
            ->withFieldSets($fieldSets)
            ->withLinks($links);
        if (count($meta)) {
            $encoder = $encoder->withMeta($meta);
        }

        $mediaType = new MediaType(MediaTypeInterface::JSON_API_TYPE, MediaTypeInterface::JSON_API_SUB_TYPE);

        return new JsonApiIntegration\Responses($encoder, $mediaType, $this->responseFactory);
    }

    private function checkAcceptHeader(HeaderParametersParserInterface $headerParametersParser): void
    {
        $request = $this->container->get('request');
        $accept = $request->getHeader(HeaderParametersParserInterface::HEADER_ACCEPT);
        if (count($accept)) {
            $mediaType = $this->factory->createMediaType(
                MediaTypeInterface::JSON_API_TYPE,
                MediaTypeInterface::JSON_API_SUB_TYPE
            );

            foreach ($headerParametersParser->parseAcceptHeader($accept[0]) as $acceptMediaType) {
                if ($mediaType->matchesTo($acceptMediaType)) {
                    return;
                }
            }
        }
        throw new Errors\NotAcceptableException();
    }

    private function checkContentTypeHeader(HeaderParametersParserInterface $headerParametersParser): void
    {
        $request = $this->container->get('request');
        if ($this->doesRequestHaveBody($request)) {
            $contentType = $request->getHeader(HeaderParametersParserInterface::HEADER_CONTENT_TYPE);
            if (count($contentType) > 0) {
                $mediaType = $this->factory->createMediaType(
                    MediaTypeInterface::JSON_API_TYPE,
                    MediaTypeInterface::JSON_API_SUB_TYPE
                );
                $parsedContentType = $headerParametersParser->parseContentTypeHeader($contentType[0]);
                if ($mediaType->matchesTo($parsedContentType)) {
                    return;
                }
            }
            throw new Errors\UnsupportedMediaTypeException();
        }
    }

    private function doesRequestHaveBody(Request $request): bool
    {
        return in_array($request->getMethod(), ['POST', 'PATCH'])
            && $request->getBody()->isReadable()
            && $request->getBody()->getSize() > 0;
    }
}
