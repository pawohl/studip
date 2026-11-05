<?php
# Lifter010: TODO
/*
 * Copyright (c) 2009  Stud.IP CoreGroup
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

// Include composer's autoload
require __DIR__ . '/../composer/autoload.php';

// Load enviroment
$dot_env_path = __DIR__ . '/..';
$dotenv = \Dotenv\Dotenv::createImmutable($dot_env_path);
$dotenv->safeLoad();

// Default environment, do not change. Change in config/config_local.inc.php.
define('DEFAULT_ENV',  $_ENV['STUDIP_ENV'] ?? 'production');

//software version - please leave it as it is!
$SOFTWARE_VERSION = '6.2';

// Store startup time
$STUDIP_STARTUP_TIME = microtime(true);

global $PHP_SELF, $STUDIP_BASE_PATH;

$PHP_SELF = $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
$STUDIP_BASE_PATH = realpath(dirname(__FILE__) . '/..');

set_include_path(
    $STUDIP_BASE_PATH
    . PATH_SEPARATOR . $STUDIP_BASE_PATH . DIRECTORY_SEPARATOR . 'config'
    . PATH_SEPARATOR . get_include_path()
);

$ABSOLUTE_PATH_STUDIP = $STUDIP_BASE_PATH . '/public/';

$CANONICAL_RELATIVE_PATH_STUDIP = dirname($_SERVER['PHP_SELF']);
if (DIRECTORY_SEPARATOR != '/') {
    $CANONICAL_RELATIVE_PATH_STUDIP = str_replace(DIRECTORY_SEPARATOR, '/', $CANONICAL_RELATIVE_PATH_STUDIP);
}
// CANONICAL_RELATIVE_PATH_STUDIP should end with a '/'
if (substr($CANONICAL_RELATIVE_PATH_STUDIP,-1) != "/"){
    $CANONICAL_RELATIVE_PATH_STUDIP .= "/";
}

$ABSOLUTE_URI_STUDIP = "";

// automagically compute ABSOLUTE_URI_STUDIP if $_SERVER['SERVER_NAME'] is set
if (isset($_SERVER['SERVER_NAME'])) {
    // work around possible bug in lighttpd
    if (mb_strpos($_SERVER['SERVER_NAME'], ':') !== false) {
        [$_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT']] =
            explode(':', $_SERVER['SERVER_NAME']);
    }

    $ABSOLUTE_URI_STUDIP = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
    $ABSOLUTE_URI_STUDIP .= '://'.$_SERVER['SERVER_NAME'];

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' && $_SERVER['SERVER_PORT'] != 443 ||
        empty($_SERVER['HTTPS']) && $_SERVER['SERVER_PORT'] != 80) {
        $ABSOLUTE_URI_STUDIP .= ':'.$_SERVER['SERVER_PORT'];
    }

    $ABSOLUTE_URI_STUDIP .= $CANONICAL_RELATIVE_PATH_STUDIP;
}

// Check if instance is configured; redirect to install script if not
if (!file_exists($GLOBALS['STUDIP_BASE_PATH'] . '/config/config_local.inc.php') && php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/classes/URLHelper.php';

    URLHelper::setBaseUrl($GLOBALS['ABSOLUTE_URI_STUDIP']);
    header('Location: ' . URLHelper::getURL('install.php'));
    die;
}

// Load configuration
require __DIR__ . '/classes/StudipFileloader.php';

$added_configs = [];

StudipFileloader::load('config_defaults.inc.php config_local.inc.php', $added_configs, compact('STUDIP_BASE_PATH', 'ABSOLUTE_URI_STUDIP', 'CANONICAL_RELATIVE_PATH_STUDIP'), true);

foreach($added_configs as $key => $value) {
    $GLOBALS[$key] = $value;
}

// create ASSETS_URL and ASSETS_PATH if not customized in config_local.inc.php
if (!isset($GLOBALS['ASSETS_URL'])) {
    $GLOBALS['ASSETS_URL'] = $ABSOLUTE_URI_STUDIP . 'assets/';
}
if (!isset($GLOBALS['ASSETS_PATH'])) {
    $GLOBALS['ASSETS_PATH'] = $ABSOLUTE_PATH_STUDIP . 'assets/';
}

// If no ENV setting was found in the config files, assume ENV=production
if (!defined('Studip\ENV')) {
    define('Studip\ENV', DEFAULT_ENV);
}

require __DIR__ . '/bootstrap-autoload.php';

// construct absolute URL for ASSETS_URL
if ($GLOBALS['ASSETS_URL'][0] === '/') {
    $host = preg_replace('%^([a-z]+:/*[^/]*).*%', '$1', $GLOBALS['ABSOLUTE_URI_STUDIP']);
    $GLOBALS['ASSETS_URL'] = $host . $GLOBALS['ASSETS_URL'];
} else if (!preg_match('/^[a-z]+:/', $GLOBALS['ASSETS_URL'])) {
    $GLOBALS['ASSETS_URL'] = $GLOBALS['ABSOLUTE_URI_STUDIP'] . $GLOBALS['ASSETS_URL'];
}

require $GLOBALS['STUDIP_BASE_PATH'] . '/config/config.inc.php';

require 'lib/helpers.php';
require_once 'lib/functions.php';
require_once 'lib/dates.inc.php';
require_once 'lib/language.inc.php';
require_once 'lib/visual.inc.php';

// set assets url
Assets::set_assets_url($GLOBALS['ASSETS_URL']);
Assets::set_assets_path($GLOBALS['ASSETS_PATH']);

// globale template factory anlegen
$GLOBALS['template_factory'] = app(Flexi\Factory::class);

// set default pdo connection
try {
    DBManager::getInstance()->setConnection(
        'studip',
        app(StudipPDO::class)
    );
} catch (\PDOException $exception) {
    if (Studip\ENV === 'development') {
        throw $exception;
    } else {
        error_log($exception);
        header('HTTP/1.1 500 Internal Server Error');
        die(_('Momentan ist das Stud.IP System nicht erreichbar, bitte versuchen Sie es später noch einmal.'));
    }
}
// set slave connection
if (isset($GLOBALS['DB_STUDIP_SLAVE_HOST'])) {
    try {
        DBManager::getInstance()
            ->setConnection('studip-slave',
                'mysql:host=' . $GLOBALS['DB_STUDIP_SLAVE_HOST'] .
                ';dbname=' . $GLOBALS['DB_STUDIP_SLAVE_DATABASE'] .
                ';charset=utf8mb4',
                $GLOBALS['DB_STUDIP_SLAVE_USER'],
                $GLOBALS['DB_STUDIP_SLAVE_PASSWORD']);
    } catch (PDOException $exception) {
        // if connection to slave fails, fall back to master instead
        DBManager::getInstance()->aliasConnection('studip', 'studip-slave');
    }
} else {
    DBManager::getInstance()->aliasConnection('studip', 'studip-slave');
}

// Check if we need to enable the fix for column default values for mariadb >= 10.2.7
if (DBManager::get()->isMariaDB('10.2.7')) {
    SimpleORMap::setMariadbDefaultColumnFix();
}

// set default exception handler
// command line or http request?
if (isset($_SERVER['REQUEST_METHOD'])) {
    set_exception_handler('studip_default_exception_handler');
}

// Prime autoloader if cache is enabled (this cannot be in autoloader's
// bootstrap because the stud.ip cache needs to have a db conenction)
if ($GLOBALS['CACHING_ENABLE']) {
    $lookup_hash = null;
    $cached = \Studip\Cache\Factory::getCache()->read('STUDIP#autoloader-classes');
    if ($cached) {
        $class_lookup = json_decode($cached, true);
        if (is_array($class_lookup)) {
            $lookup_hash = md5($cached);
            StudipAutoloader::addClassLookups($class_lookup);
        }
    }

    register_shutdown_function(function () use ($lookup_hash) {
        $cached = json_encode(StudipAutoloader::$class_lookup, JSON_UNESCAPED_UNICODE);
        if (md5($cached) !== $lookup_hash) {
            \Studip\Cache\Factory::getCache()->write(
                'STUDIP#autoloader-classes',
                $cached,
                7 * 24 * 60 * 60
            );
        }
    });
}

// set default time zone
date_default_timezone_set(Config::get()->DEFAULT_TIMEZONE ? : @date_default_timezone_get());

// sample the request time and number of db queries every tenth time
register_shutdown_function(function ($timer) {
    $timer('core.request_time', 0.1);

    $query_count = DBManager::get()->query_count;
    Metrics::gauge('core.database.queries', $query_count, 0.1);
}, Metrics::startTimer());

//include 'tools/debug/StudipDebugPDO.class.php';

if (Config::get()->CALENDAR_ENABLE) {
    require_once 'lib/calendar_functions.inc.php';
}

if (Config::Get()->ILIAS_INTERFACE_ENABLE) {
    require_once 'lib/ilias_interface/IliasUserObserver.php';
    require_once 'lib/ilias_interface/IliasCourseObserver.php';
    require_once 'lib/ilias_interface/StudipSoapClient.php';
}

// set dummy navigation until db is ready
Navigation::setRootNavigation(new Navigation(''));

// set up default page layout
PageLayout::initialize();

// init notification observers
Studip\Activity\ActivityObserver::initialize();
FilesSearch\NotificationObserver::initialize();
if (Config::Get()->ILIAS_INTERFACE_ENABLE) {
    IliasUserObserver::initialize();
    IliasCourseObserver::initialize();
}

//Besser hier globale Variablen definieren...
$GLOBALS['_fullname_sql'] = [];
$GLOBALS['_fullname_sql']['full'] = "TRIM(CONCAT(title_front,' ',Vorname,' ',Nachname,IF(title_rear!='',CONCAT(', ',title_rear),'')))";
$GLOBALS['_fullname_sql']['full_rev'] = "TRIM(CONCAT(Nachname,', ',Vorname,IF(title_front!='',CONCAT(', ',title_front),''),IF(title_rear!='',CONCAT(', ',title_rear),'')))";
$GLOBALS['_fullname_sql']['no_title'] = "CONCAT(Vorname ,' ', Nachname)";
$GLOBALS['_fullname_sql']['no_title_rev'] = "CONCAT(Nachname ,', ', Vorname)";
$GLOBALS['_fullname_sql']['no_title_short'] = "CONCAT(Nachname,', ',UCASE(LEFT(TRIM(Vorname),1)),'.')";
$GLOBALS['_fullname_sql']['no_title_motto'] = "CONCAT(Vorname ,' ', Nachname,IF(motto!='',CONCAT(', ',motto),''))";
$GLOBALS['_fullname_sql']['full_rev_username'] = "TRIM(CONCAT(Nachname,', ',Vorname,IF(title_front!='',CONCAT(', ',title_front),''),IF(title_rear!='',CONCAT(', ',title_rear),''),' (',username,')'))";

//Initialize $SEM_TYPE and $SEM_CLASS arrays
$GLOBALS['SEM_CLASS'] = SemClass::getClasses();
$GLOBALS['SEM_TYPE'] = SemType::getTypes();

// set up global navigation
Navigation::setRootNavigation(new StudipNavigation(''));

/* set default umask to a sane value */
umask(022);

/*mail settings
----------------------------------------------------------------*/
if ($GLOBALS['MAIL_TRANSPORT']) {
    $mail_transporter_name = mb_strtolower($GLOBALS['MAIL_TRANSPORT']) . '_message';
} else {
    $mail_transporter_name = 'smtp_message';
}
include 'vendor/email_message/email_message.php';
include 'vendor/email_message/' . $mail_transporter_name . '.php';
$mail_transporter_class = $mail_transporter_name . '_class';
$mail_transporter = new $mail_transporter_class;
if ($mail_transporter_name == 'smtp_message') {
    include 'vendor/email_message/smtp.php';
    $mail_transporter->localhost = $GLOBALS['MAIL_LOCALHOST'] ?: $_SERVER['SERVER_NAME'];
    $mail_transporter->smtp_host = $GLOBALS['MAIL_HOST_NAME'] ?: 'localhost';
    if (is_array($GLOBALS['MAIL_SMTP_OPTIONS'])) {
        foreach ($GLOBALS['MAIL_SMTP_OPTIONS'] as $key => $value) {
            $mail_transporter->{"smtp_$key"} = $value;
        }
        if ($mail_transporter->smtp_user !== '') {
            include 'vendor/sasl/sasl.php';
        }
    }
}
$mail_transporter->default_charset = 'UTF-8';
$mail_transporter->SetBulkMail((int)$GLOBALS['MAIL_BULK_DELIVERY']);
StudipMail::setDefaultTransporter($mail_transporter);
unset($mail_transporter);
