<?php
/**
 * globalsearch.php - controller to perform global search operations and provide settings.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Thomas Hackl <thomas.hackl@uni-passau.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @since       4.1
 */

class GlobalSearchController extends AuthenticatedController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        if (in_array($action, ['settings', 'saveconfig'])) {
            $GLOBALS['perm']->check('root');
        }
    }

    /**
     * Perform search in all registered modules for the given search term.
     */
    public function find_action($limit)
    {
        $limit = min(100, (int)$limit);

        // Now load all modules
        $modules = GlobalSearchModule::getActiveSearchModules();

        $search = trim(Request::get('search'));

        $filter = json_decode(Request::get('filters'), true);

        $result = [];

        foreach ($modules as $className) {
            $partSQL = $className::getSQL($search, $filter, $limit);

            // No valid sql? Leave.
            if (!$partSQL) {
                continue;
            }

            $entries = DBManager::get()->fetchAll($partSQL);
            $entries_count_array = DBManager::get()->fetchAll('SELECT FOUND_ROWS() as found_rows');
            $entries_count = (int)$entries_count_array[0]['found_rows'];

            // No results? Leave.
            if (!is_array($entries)) {
                continue;
            }

            // Walk through results
            $found = [];
            foreach ($entries as $one) {
                // Filter item and add to result if necessary.
                if ($item = $className::filter($one, $search)) {
                    $found[] = $item;
                }
            }

            // Nothing found? Leave.
            if (count($found) === 0) {
                continue;
            }

            $result[$className] = [
                'name'       => $className::getName(),
                'fullsearch' => $className::getSearchURL($search),
                'content'    => $found,
                // If we found more results than needed, indicate a "more" link
                // for full search.
                'more'       => count($found) > Config::get()->GLOBALSEARCH_MAX_RESULT_OF_TYPE,
                // If there are more results than our arbitrary LIMIT, a plus
                // ('+') should be shown besides the category result count
                'plus'       => count($found) < $entries_count,
            ];
        }

        GlobalSearchModule::clearCache();

        // Sort
        $positions = array_flip($modules);
        uksort($result, function($a, $b) use ($positions) {
            return $positions[$a] - $positions[$b];
        });

        // Send me an answer
        $this->render_json($result);
    }

    /**
     * Provide a GUI for configuring the search module order and other settings.
     */
    public function settings_action()
    {
        PageLayout::setTitle(_('Globale Suche: Einstellungen'));
        Navigation::activateItem('/admin/config/globalsearch');

        $this->config = Config::get()->GLOBALSEARCH_MODULES;
        $this->modules = [];

        foreach ($this->config as $className => $config) {
            if (class_exists($className)) {
                $this->modules[$className] = new $className();
            }
        }

        // Search declared classes for GlobalSearchModules
        foreach (get_declared_classes() as $className) {
            if (is_subclass_of($className, 'GlobalSearchModule')) {

                // Add new classes at module array end and not activated.
                if (!isset($this->modules[$className])) {
                    $this->modules[$className] = new $className();
                }
            }
        }
    }

    /**
     * Saves the set values to global configuration.
     */
    public function saveconfig_action()
    {
        CSRFProtection::verifyUnsafeRequest();

        $config = [];

        foreach (Request::getArray('modules') as $module) {
            $config[$module['class']] = [
                'active'   => !empty($module['active']),
                'fulltext' => is_a($module['class'], 'GlobalSearchFulltext', true) && !empty($module['fulltext'])
            ];
        }

        Config::get()->store('GLOBALSEARCH_MAX_RESULT_OF_TYPE', Request::int('entries_per_type', 3));
        Config::get()->store('GLOBALSEARCH_MODULES', $config);

        PageLayout::postSuccess(_('Die Einstellungen wurden gespeichert.'));

        $this->redirect('globalsearch/settings');
    }
}
