<?php
/**
 * PSR 15 middleware Stud.IP initialization
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       6.0
 */

namespace Studip\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;


final class SeminarOpenMiddleware implements MiddlewareInterface
{

    public function __construct(
        private readonly ResponseFactoryInterface $response_factory
    ) {
    }

    /**
     * @param $page_code
     *
     * @return ResponseInterface
     */
    public function startpageRedirect($page_code): ResponseInterface
    {
        switch ($page_code) {
            case 1:
            case 2:
                $jump_page = 'dispatch.php/my_courses';
                break;
            case 3:
                $jump_page = 'dispatch.php/calendar/schedule';
                break;
            case 4:
                $jump_page = 'dispatch.php/contact';
                break;
            case 5:
                $jump_page = 'dispatch.php/calendar/calendar';
                break;
            case 6:
                // redirect to global blubberstream
                // or no redirection if blubber isn't active
                if (\Config::get()->BLUBBER_GLOBAL_MESSENGER_ACTIVATE) {
                    $jump_page = 'dispatch.php/blubber';
                }
                break;
            case 7:
                $jump_page = 'dispatch.php/contents/overview';
                break;
        }

        $response = $this->response_factory->createResponse(302);
        return $response->withHeader('Location', \URLHelper::getURL($jump_page));
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        global $user, $perm, $_language_path;

        // Try to select the course or institute given by the parameter 'cid'
        // in the current request.

        $course_id = (\Request::bool('cancel_login') && (!is_object($user) || $user->id === 'nobody'))
            ? null
            : \Request::option('cid');

        // Select the current course or institute if we got one from 'cid' or session.
        // This also binds Context::getId()
        // to the URL parameter 'cid' for all generated links.
        if (isset($course_id)) {
            try {
                \Context::set($course_id);
            } catch (\LoginException $e) {
                $_SESSION['redirect_after_login'] ??= \Request::url();
                return $this->response_factory->createResponse(302)
                    ->withHeader('Location', \URLHelper::getScriptURL('dispatch.php/login'));
            }
            unset($course_id);
        }

        if (\Request::bool('disable_plugins') !== null && ($user->id === 'nobody' || $perm->have_perm('root'))) {
            // deactivate non-core plugins
            \PluginManager::getInstance()->setPluginsDisabled(\Request::bool('disable_plugins'));
        }

        // load the default set of plugins
        \PluginEngine::loadPlugins();

        //INITS
        $seminar_open_redirected = false;
        $user_did_login = false;

        // session init starts here
        if (empty($_SESSION['SessionStart'])) {
            $_SESSION['SessionStart'] = time();
            $_SESSION['object_cache'] = [];

            // try to get accepted languages from browser
            if (!isset($_SESSION['_language'])) {
                $_SESSION['_language'] = get_accepted_languages();
            }
            if (!$_SESSION['_language']) {
                $_SESSION['_language'] = \Config::get()->DEFAULT_LANGUAGE;
            }
        }

        // user init starts here
        if (is_object($user) && $user->id !== 'nobody') {
            if ($_SESSION['SessionStart'] > \UserConfig::get($user->id)->CURRENT_LOGIN_TIMESTAMP) {      // just logged in
                // store old CURRENT_LOGIN in LAST_LOGIN and set CURRENT_LOGIN to start of session
                \UserConfig::get($user->id)->store(
                    'LAST_LOGIN_TIMESTAMP', \UserConfig::get($user->id)->CURRENT_LOGIN_TIMESTAMP
                );
                \UserConfig::get($user->id)->store('CURRENT_LOGIN_TIMESTAMP', $_SESSION['SessionStart']);
                //find current semester and store it in $_SESSION['_default_sem']
                $current_sem = \Semester::findDefault();
                $_SESSION['_default_sem'] = $current_sem->semester_id ?? null;
                //redirect user to another page if he want to, redirect is deferred to allow plugins to catch the UserDidLogin notification
                if (
                    \UserConfig::get($user->id)->PERSONAL_STARTPAGE > 0
                    && !isset($_SESSION['redirect_after_login'])
                    && !$perm->have_perm('root')
                ) {
                    $seminar_open_redirected = true;
                }
                if (isset($_SESSION['contrast'])) {
                    \UserConfig::get($GLOBALS['user']->id)->store('USER_HIGH_CONTRAST', $_SESSION['contrast']);
                    unset($_SESSION['contrast']);
                }
                // store last language click
                if (!empty($_SESSION['forced_language'])) {
                    \User::findCurrent()->preferred_language = $_SESSION['forced_language'];
                    \User::findCurrent()->store();
                    $_SESSION['_language'] = $_SESSION['forced_language'];
                } else {
                    $_SESSION['_language'] = getUserLanguage($user->id);
                }
                $_SESSION['forced_language'] = null;
                $user_did_login = true;
            }

            \TwoFactorAuth::get()->secureSession();
        }

        if (!empty($_SESSION['contrast']) || \UserConfig::get($GLOBALS['user']->id)->USER_HIGH_CONTRAST) {
            \PageLayout::addStylesheet('accessibility.css');
        }

        // init of output via I18N
        $_language_path = init_i18n($_SESSION['_language']);
        //force reload of config to get translated data
        include $GLOBALS['STUDIP_BASE_PATH'] . '/config/config.inc.php';

        // add navigation item for profile: add modules
        if (\Navigation::hasItem('/profile/edit')) {
            $plus_nav = new \Navigation(_('Mehr …'), 'dispatch.php/profilemodules/index');
            $plus_nav->setDescription(_('Mehr Stud.IP-Funktionen für Ihr Profil'));
            \Navigation::addItem('/profile/modules', $plus_nav);
        }

        if ($user_did_login) {
            \NotificationCenter::postNotification('UserDidLogin', $user->id);

            if (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                return $this->response_factory->createResponse(302)
                    ->withHeader('Location', \URLHelper::getURL($redirect));
            } elseif (isset($_SESSION[\StudipAuthOAuth2::class]['redirect'])) {
                $redirect = $_SESSION[\StudipAuthOAuth2::class]['redirect'];
                unset($_SESSION[\StudipAuthOAuth2::class]);
                return $this->response_factory->createResponse(302)
                    ->withHeader('Location', \URLHelper::getURL($redirect));
            }
        }

        if (!\Request::isXhr() && $perm->have_perm('root')) {
            if (!isset($_SESSION['migration-check']) || $_SESSION['migration-check']['timestamp'] < time() - 5 * 60) {
                $migrator = new \Migrator(
                    "{$GLOBALS['STUDIP_BASE_PATH']}/db/migrations",
                    new \DBSchemaVersion('studip')
                );

                $_SESSION['migration-check'] = [
                    'disabled'  => $_SESSION['migration-check']['disabled'] ?? false,
                    'timestamp' => time(),
                    'count'     => $migrator->pendingMigrations()
                ];
            }

            if (\Request::option('stop-migration-nag')) {
                $_SESSION['migration-check']['disabled'] = true;
            }

            if (empty($_SESSION['migration-check']['disabled'])
                && $_SESSION['migration-check']['count'] > 0
            ) {
                $info = sprintf(
                    _('Es gibt %u noch nicht ausgeführte Migration(en).'),
                    $_SESSION['migration-check']['count']
                );

                $message = \MessageBox::info($info, [
                        sprintf(
                            _('Zur %sMigrationsseite%s'),
                            '<a class="link-intern" href="' . \URLHelper::getLink('web_migrate.php') . '">',
                            '</a>'
                        ),
                        sprintf(
                            '<small><a href="%s">%s</a></small>',
                            \URLHelper::getLink('', ['stop-migration-nag' => true]),
                            _('Diese Nachricht bis zum nächsten Login nicht mehr anzeigen')
                        )
                    ]
                );
                \PageLayout::postMessage($message, 'migration-info');
            }
        }
        if (
            $GLOBALS['perm']->have_perm('root')
            && \Config::get()->MIGRATION_START_VERSION
            && \Config::get()->MIGRATION_START_VERSION < \StudipVersion::getStudipVersion(true)
            && !\Config::get()->UPDATE_NEWS_SEEN
        ) {
            $message = \MessageBox::warning(
                _('Sie haben ein Stud.IP-Update durchgeführt.'),
                [
                    sprintf(
                        _('Zu den %sRelease-Notes%s'),
                        '<a class="link-intern" href="' . \URLHelper::getLink('dispatch.php/root_assistant') . '" data-dialog>',
                        '</a>'
                    ),
                ]
            );
            \PageLayout::postMessage($message, 'release-notes');
        }

        if ($seminar_open_redirected) {
            return $this->startpageRedirect(\UserConfig::get($user->id)->PERSONAL_STARTPAGE);
        }

        // Show terms on first login
        if (
            is_object($GLOBALS['user'])
            && $GLOBALS['user']->needsToAcceptTerms()
            && !match_route('dispatch.php/terms')
            && !match_route('dispatch.php/siteinfo/*')
            && !match_route('dispatch.php/logout')
        ) {
            if (!\Request::isXhr()) {
                $response = $this->response_factory->createResponse(302);
                return $response->withHeader(
                    'Location', \URLHelper::getURL(
                    'dispatch.php/terms',
                    [
                        'return_to'      => $_SERVER['REQUEST_URI'],
                        'redirect_token' => \Token::create(600)
                    ],
                    true
                )
                );
            } else {
                throw new \Trails\Exception(400);
            }
        }

        if (
            \Config::get()->USER_VISIBILITY_CHECK
            && is_object($GLOBALS['user'])
            && $GLOBALS['user']->id !== 'nobody'
            && !(
                \Config::get()->DOZENT_ALWAYS_VISIBLE
                && $perm->get_perm() === 'dozent'
            )
            && !match_route('dispatch.php/siteinfo/*')
            && $GLOBALS['user']->visible === 'unknown'
        ) {
            require_once('lib/user_visible.inc.php');
            $html = first_decision($GLOBALS['user']->id);
            if ($html) {
                $response = $this->response_factory->createResponse(200);
                $response->getBody()->write($html);
                return $response;
            }
        }

        return $handler->handle($request);
    }
}
