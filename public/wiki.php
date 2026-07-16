<?php
/**
 * wiki.php
 *
 * This script is needed to redirect wiki pages referenced by an URL to the old wiki
 * (before Stud.IP 5.5) to the controller of the new wiki (Stud.IP 5.5 and newer).
 *
 * @author      Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @author      Moritz Strohm <strohm@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       5.5
 */

// Set up a minimal Stud.IP environment:
require_once __DIR__ . '/../lib/bootstrap.php';
URLHelper::setBaseUrl($GLOBALS['ABSOLUTE_URI_STUDIP']);

// Set exception handler which outputs the message of the exception.
// In case the Stud.IP is in development mode, the stack trace
// is also printed before halting execution.
set_exception_handler(function (Throwable $exception) {
    header('Content-Type: text/plain');
    echo $exception->getMessage();
    if (Studip\ENV === 'development') {
        echo "\n" . $exception->getTraceAsString();
    }
    die;
});

// Handle URL parameters:
$course_id = Request::option('cid');
$keyword   = Request::get('keyword');
$version   = Request::int('version');
if (!$course_id) {
    // Invalid wiki page.
    header('Content-Type: text/plain');
    die(sprintf(_('Fehlender URL-Parameter: %s'), 'cid'));
}

// Load the wiki page or a version of it:
$page         = null;
$page_version = null;

if (!$keyword || $keyword === 'WikiWikiWeb') {
    // Load the start page for the course:
    $page_id = RangeConfig::get($course_id)->WIKI_STARTPAGE_ID;
    $load_newest_version = true;
    if ($version) {
        // Check if the requested version is the newest version.
        // In that case, the wiki_pages table instead of the wiki_versions
        // table must be used to get the correct version of the page.
        $newest_version = 1 + WikiVersion::countByPage_id($page_id);

        $load_newest_version = $version === $newest_version;
    }
    if ($load_newest_version) {
        // Load the newest version:
        $page = WikiPage::find($page_id);
    } else {
        // Load the requested version:
        $page_version = WikiVersion::findOneBySQL(
            '`page_id` = :page_id
             ORDER BY `mkdate` ASC
             LIMIT 1 OFFSET :version',
            [
                'page_id' => $page_id,
                'version' => $version - 1
            ]
        );
    }
} else {
    // Load the page by its keyword and course-ID:
    $load_newest_version = true;
    if ($version) {
        // Check if the requested version is the newest version.
        // In that case, the wiki_pages table instead of the wiki_versions
        // table must be used to get the correct version of the page.
        $newest_version = 1 + WikiVersion::countBySql(
            'JOIN `wiki_pages` USING (`page_id`)
             WHERE `wiki_pages`.`range_id` = :course_id AND `wiki_pages`.`name` = :keyword',
            [
                'course_id' => $course_id,
                'keyword' => $keyword
            ]
        );
        $load_newest_version = $version === $newest_version;
    }

    if ($load_newest_version) {
        // Load the newest version:
        $page = WikiPage::findOneBySQL(
            '`range_id` = :course_id AND `name` = :keyword',
            [
                'course_id' => $course_id,
                'keyword' => $keyword
            ]
        );
    } else {
        // Load the requested version:
        $page_version = WikiVersion::findOneBySQL(
            'JOIN `wiki_pages` USING (`page_id`)
             WHERE `wiki_pages`.`range_id` = :course_id AND `wiki_pages`.`name` = :keyword
             ORDER BY `wiki_versions`.`mkdate` ASC
             LIMIT 1 OFFSET :version',
            [
                'course_id' => $course_id,
                'keyword'   => $keyword,
                'version'   => $version - 1
            ]
        );
    }
}
if (!$page && !$page_version) {
    // Page not found:
    header('Content-Type: text/plain');
    die(sprintf(_('Die Wikiseite mit dem Schlagwort "%s" wurde nicht gefunden.'), $keyword));
}

// Everything went well: Redirect permanently to the new wiki page.
// Assume that the optional version parameter is not set and redirect to the newest
// version of the wiki page, which should be the usual case.
$url = URLHelper::getURL('dispatch.php/course/wiki/page/' . $page->id, ['cid' => $course_id]);
if ($page_version) {
    //Redirect to the specific version of the wiki page instead of the newest version:
    $url = URLHelper::getURL('dispatch.php/course/wiki/version/' . $page_version->id, ['cid' => $course_id]);
}

header('Location: ' . $url, true, 301);
