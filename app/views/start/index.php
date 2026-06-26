<?php
/**
 * @var array $columns
 * @var Flexi\Template $widget_layout
 * @var MessageBox[] $messages
 */
?>
<?= implode('', $messages) ?>

<?php
// display a random banner if the module is enabled
if (Config::get()->BANNER_ADS_ENABLE) {
    echo Banner::getRandomBanner()->toHTML();
}
?>

<div class="start-widgetcontainer">
    <? foreach ($columns as $column): ?>
        <ul class="portal-widget-list">
            <? foreach ($column as $widget): ?>
                <li class="studip-widget-wrapper" id="<?= $widget->getPluginId() ?>">
                    <div class="ui-widget-content studip-widget">
                        <? if ($template = $widget->getPortalTemplate()): ?>
                            <? $widget_layout->clear_attributes() ?>
                            <? $template->set_layout($widget_layout) ?>
                            <?= $this->render_partial($template, compact('widget')) ?>
                        <? else: ?>
                            <?= $this->render_partial('start/_widget', compact('widget')) ?>
                        <? endif ?>
                    </div>
                </li>
            <? endforeach ?>
        </ul>
    <? endforeach ?>
</div>
