<?= $this->render_partial(
    'sidebar/clipboard-area.php',
    [
        'clipboards' => $clipboards,
        'empty_clipboard_string' => _('Ziehen Sie Elemente in diesen Bereich um den Merkzettel zu füllen.'),
        'selected_clipboard_id' => ($clipboards[0] instanceof Clipboard
                                  ? $clipboards[0]->id
                                  : ''),
        'draggable_items' => $draggable_items,
        'clipboard_widget_id' => $clipboard_widget_id
    ]
) ?>

<ul class="widget-list widget-links invisible">
<? foreach ($elements as $index => $element): ?>
    <li id="<?= htmlReady('link-' . md5($element->url)) ?>">
        <?= $element->render() ?>
    </li>
<? endforeach; ?>
</ul>

<form class="default new-clipboard-form"
      action="<?= URLHelper::getLink(
              'dispatch.php/clipboard/add'
              )?>"
      method="post">
    <?= CSRFProtection::tokenTag() ?>
    <input type="hidden" name="allowed_item_class"
           value="<?= htmlReady($allowed_item_class) ?>">
    <input type="hidden" name="widget_id"
           value="<?= htmlReady($clipboard_widget_id) ?>">
    <label>
        <?= _('Merkzettel hinzufügen') ?>
        <?= tooltipIcon(_('Geben Sie bitte einen Namen ein und klicken Sie auf das Plus-Symbol um einen neuen Merkzettel zu erstellen.')) ?>
        <input type="text" name="name" placeholder="<?= _('Name des neuen Merkzettels') ?>">
    </label>

    <?= Icon::create('add')->asInput([
        'name'   => 'save',
        'id' => 'add-clipboard-button',
        'class' => 'middle',
        'disabled' => 'disabled',
        'title' => _('Hinzufügen'),
    ]) ?>
</form>
