<?php
/*
 * news.php - News controller
 *
 * Copyright (C) 2014 - Nadine Werner <nadwerner@uos.de>
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 */

class NewsWidget extends CorePlugin implements PortalPlugin
{
    public function getPluginName()
    {
        return _('Ankündigungen');
    }

    public function getMetadata()
    {
        return [
            'description' => _('Mit diesem Widget haben Sie Zugriff auf systemweite Ankündigungen.')
        ];
    }

    function getPortalTemplate()
    {
        $controller = app(\Trails\Dispatcher::class)->load_controller('news');
        $response = $controller->relayWithRedirect('news/display/studip');
        $template = $GLOBALS['template_factory']->open('shared/string');
        $template->content = $response->body;
        $icons = [];
        if (StudipNews::CountUnread() > 0) {
            $navigation = new Navigation('', 'dispatch.php/news/visit_all');
            $navigation->setImage(Icon::create('accept'));
            $navigation->setLinkAttributes([
                'class' => 'visit-all',
                'title' => _('Alles als gelesen markieren'),
            ]);
            $icons[] = $navigation;
        }

        if (Config::get()->NEWS_RSS_EXPORT_ENABLE) {
            if ($rss_id = StudipNews::GetRssIdFromRangeId('studip')) {
                $navigation = new Navigation('', 'rss.php', ['id' => $rss_id]);
                $navigation->setImage(Icon::create('rss'));
                $navigation->setLinkAttributes(['title' => _('RSS-Feed')]);
                $icons[] = $navigation;
            }
        }

        if ($GLOBALS['perm']->have_perm('root')) {
            $navigation = new Navigation('', 'dispatch.php/news/edit_news/new/studip');
            $navigation->setImage(Icon::create('add'));
            $navigation->setLinkAttributes([
                'title' => _('Ankündigungen bearbeiten'),
                'data-dialog' => '',
            ]);

            $icons[] = $navigation;
            if (Config::get()->NEWS_RSS_EXPORT_ENABLE) {
                $navigation = new Navigation('', 'dispatch.php/news/rss_config/studip');
                $navigation->setImage(Icon::create('admin'));
                $navigation->setLinkAttributes([
                    'title' => _('RSS-Feed konfigurieren'),
                    'data-dialog' => 'size=auto',
                ]);
                $icons[] = $navigation;
            }
        }

        $template->icons = $icons;

        return $template;
    }
}
