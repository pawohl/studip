<?php
/*
 * QuickSelection.php - widget plugin for start page
 *
 * Copyright (C) 2014 - Nadine Werner <nadwerner@uos.de>
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

class QuickSelection extends CorePlugin implements PortalPlugin
{
    public function getPluginName()
    {
        return _('Schnellzugriff');
    }

    public function getMetadata()
    {
        return [
            'description' => _('Mit dem Schnellzugriff-Widget können Sie Links zu bestimmten Bereichen in Stud.IP verwalten.')
        ];
    }

    public function getPortalTemplate()
    {
        $names = UserConfig::get($GLOBALS['user']->id)->getValue('QUICK_SELECTION');

        $template = $GLOBALS['template_factory']->open('start/quickselection');
        $template->navigation = $this->getFilteredNavigation($names);

        $navigation = new Navigation('', 'dispatch.php/quickselection/configuration');
        $navigation->setImage(Icon::create('edit'));
        $navigation->setLinkAttributes([
            'title'       => _('Schnellzugriff konfigurieren'),
            'data-dialog' => 'size=auto',
        ]);

        $template->icons = [$navigation];

        return $template;
    }

    private function getFilteredNavigation($items)
    {
        $result = [];

        $navigation = Navigation::getItem('/start');
        foreach ($navigation as $name => $nav) {
            // if config is new (key:value) display values which are not in config array
            // otherwise hide items which are not in config array
            // This is important for patching.
            if (!isset($items[$name]) || $items[$name] !== 'deactivated') {
                $result[] = $nav;
            }
        }

        return $result;
    }
}
