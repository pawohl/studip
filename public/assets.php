<?php
/**
 * Output plugin assets
 *
 * This will load and output plugin assets. For now, this will be the
 * compiled LESS files of plugins.
 * All served assets will set the appropriate headers so that the browser
 * will cache the assets for a certain amount of time.
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 * @since   Stud.IP 3.4
 */

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;

require_once __DIR__  .'/../lib/bootstrap.php';

// Build PHP_DI Container
$container = app();

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath($GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP'] . 'assets.php');

$app->get('/{type:js|css}/{id}', function (ServerRequestInterface $request, ResponseInterface $response, array $args) use ($app) {
    $model = PluginAsset::find($args['id']);
    if (!$model) {
        return $response->withStatus(404);
    }

    if (
        $request->hasHeader('If-Modified-Since')
        && $model->chdate <= strtotime($request->getHeaderLine('If-Modified-Since')[0])
    ) {
        return $response->withStatus(304);
    }

    $asset = new Assets\PluginAsset($model);

    try {
        $response->getBody()->write($asset->getContent());

        $response = $response->withHeader('Content-Type', $args['type'] === 'css' ? 'text/css' : 'application/javascript');
        $response = $response->withHeader('Content-Length', $model->size ?? 0);

        // Store cache information
        if (Studip\ENV !== 'development') {
            $response = $response->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $model->chdate) . ' GMT');
            $response = $response->withHeader('Expires', gmdate('D, d M Y H:i:s', $model->chdate + PluginAsset::CACHE_DURATION) . ' GMT');
        }

        return $response;
    } catch (Exception $e) {
        $asset->delete();
        return $response->withStatus(500);
    }
});

$app->run();
