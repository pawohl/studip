<?php
/**
 * StudipFormat.php - simple Stud.IP text markup parser
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Elmar Ludwig
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */

class WikiFormat extends StudipFormat
{
    private static $wiki_rules = [
        'wiki-links' => [
            'start'    => '\[\[(.*?)(?:\|(.*?))?\]\]',
            'end'      => '',
            'callback' => 'WikiFormat::markupWikiLinks',
            'before'   => 'links'
        ],
    ];

    private $range_id = null;
    private $page_id = null;

    /**
     * Adds a new markup rule to the wiki markup set. This can
     * also be used to replace an existing markup rule. The end regular
     * expression is optional (i.e. may be NULL) to indicate that this
     * rule has an empty content model. The callback is called whenever
     * the rule matches and is passed the following arguments:
     *
     * - $markup    the markup parser object
     * - $matches   match results of preg_match for $start
     * - $contents  (parsed) contents of this markup rule
     *
     * Sometimes you may want your rule to apply before another specific rule
     * will apply. For this case the parameter $before defines a rulename of
     * existing markup, before which your rule should apply.
     *
     * @param string $name      name of this rule
     * @param string $start     start regular expression
     * @param string $end       end regular expression (optional)
     * @param callback $callback function generating output of this rule
     * @param string $before mark before which rule this rule should be appended
     */
    public static function addWikiMarkup($name, $start, $end, $callback, $before = null)
    {
        $inserted = false;
        foreach (self::$wiki_rules as $rule_name => $rule) {
            if ($rule_name === $before) {
                self::$wiki_rules[$name] = compact('start', 'end', 'callback');
                $inserted = true;
            }
            if ($inserted) {
                unset(self::$wiki_rules[$rule_name]);
                self::$wiki_rules[$rule_name] = $rule;
            }
        }
        if (!$inserted) {
            self::$wiki_rules[$name] = compact('start', 'end', 'callback');
        }
    }

    /**
     * Returns a single markup-rule if it exists.
     * @return array: array('start' => "...", 'end' => "...", 'callback' => "...")
     */
    public static function getWikiMarkup($name)
    {
        return self::$wiki_rules[$name];
    }

    /**
     * Removes a markup rule from the wiki markup set.
     *
     * @param string $name Name of the rule
     */
    public static function removeWikiMarkup($name)
    {
        unset(self::$wiki_rules[$name]);
    }

    /**
     * Initializes a new WikiFormat instance.
     * @param string|null $range_id ID of the course or institute.
     */
    public function __construct($range_id = null, $page_id = null)
    {
        parent::__construct();
        $this->range_id = $range_id;
        $this->page_id  = $page_id;
        foreach (self::$wiki_rules as $name => $rule) {
            $this->addMarkup(
                $name,
                $rule['start'],
                $rule['end'],
                $rule['callback'],
                $rule['before'] ?? null
            );
        }
    }

    /**
     * Returns the range_id of the wiki-page for which the markup is desired.
     * @return string|null
     */
    public function getRangeId()
    {
        return $this->range_id;
    }

    /**
     * Returns the page_id of the wiki-page for which the markup is desired.
     * @return string|null
     */
    public function getPageId()
    {
        return $this->page_id;
    }

    /**
     * Stud.IP markup for wiki links
     *
     * @param  StudipFormat $markup  Markup object
     * @param  array        $matches Found matches
     * @return string
     */
    protected static function markupWikiLinks($markup, $matches)
    {
        $keyword = decodeHTML($matches[1]);
        $display_page = !empty($matches[2]) ? $markup->format($matches[2]) : htmlReady($keyword);

        $range_id = $markup->getRangeId() ?? Context::getId();
        $page = WikiPage::findByName($range_id, $keyword);

        // Page does not exist
        if (!$page) {
            return sprintf('<a href="%s">%s(?)</a>',
                URLHelper::getLink('dispatch.php/course/wiki/edit', [
                    'keyword'   => trim($keyword),
                    'parent_id' => $markup->getPageId(),
                    'cid'       => $range_id,
                ]),
                $display_page
            );
        }

        // Page is visible to current user
        if ($page->isReadable()) {
            return sprintf(
                '<a href="%s">%s</a>',
                URLHelper::getLink('dispatch.php/course/wiki/page/' . $page->id, ['cid' => $range_id]),
                $display_page
            );
        }

        // Page is not visible to current user and show be displayed accordingly
        return sprintf(
            '<a href="%s" class="wiki-restricted" title="%s">%s</a>',
            URLHelper::getLink('dispatch.php/course/wiki/page/' . $page->id, ['cid' => $range_id]),
            sprintf(
                _('Sie haben keine Berechtigung, die Seite %s zu lesen!'),
                htmlReady($keyword)
            ),
            $display_page
        );
    }

}
