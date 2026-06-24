<?php
/**
 * @author      André Klaßen <klassen@elan-ev.de>
 * @author      Till Glöggler <tgloeggl@uos.de>
 * @license     GPL 2 or later
 */
class ActivityFeed extends CorePlugin implements PortalPlugin
{
    public function getPluginName()
    {
        return _('Aktivitäten');
    }

    public function getMetadata()
    {
        return [
            'description' => _('Mit diesem Widget haben Sie alle Aktivitäten im Überblick.')
        ];
    }

    public function getPortalTemplate()
    {
        $template = $GLOBALS['template_factory']->open('start/activityfeed');

        $template->user_id = $GLOBALS['user']->id;
        $template->scrolledfrom = strtotime('+1 day');
        $template->config = UserConfig::get($GLOBALS['user']->id)->getValue('ACTIVITY_FEED');

        $navigation = new Navigation('', 'dispatch.php/activityfeed/configuration');
        $navigation->setImage(Icon::create('edit'));
        $navigation->setLinkAttributes([
            'data-dialog' =>'size=auto',
            'title' => _('Aktivitäten konfigurieren'),
        ]);

        $icons[] = $navigation;

        $navigation = new Navigation('', '#', ['cid' => null]);
        $navigation->setImage(Icon::create('person-online'));
        $navigation->setLinkAttributes([
            'id'    => 'toggle-user-activities',
            'title' => _('Eigene Aktivitäten ein-/ausblenden'),
        ]);
        $icons[] = $navigation;

        $navigation = new Navigation('', '#', ['cid' => null]);
        $navigation->setImage(Icon::create('no-activity'));
        $navigation->setLinkAttributes([
            'id'    => 'toggle-all-activities',
            'title' => _('Aktivitätsdetails ein-/ausblenden'),
        ]);
        $icons[] = $navigation;

        $template->icons = $icons;

        return $template;
    }
}
