<? use Studip\Button, Studip\LinkButton ?>
<div id="lit_edit_element">
<? if ($msg) : ?>
<table width="99%" border="0" cellpadding="2" cellspacing="0">
    <?=parse_msg ($msg,"�","blank",1,false)?>
</table>
<? endif ?>
<?=$form->getFormStart(URLHelper::getLink('dispatch.php/literature/edit_element?_catalog_id='.$catalog_id), array('class' => 'studip_form', 'data-dialog' => ''))?>
    <fieldset>
        <legend><?= ($element->isNewEntry()) ? _("Neuer Eintrag") : _('Eintrag') ?></legend>
        <? if (! $element->isNewEntry()) : ?>
            <?= sprintf(_("Anzahl an Referenzen f�r diesen Eintrag: %s"), (int)$element->reference_count) ?><br>
            <b><?= ($element->getValue("user_id") == "studip") ? _("Systemeintrag:") : _("Eingetragen von:") ?></b><br>
            <?= ($element->getValue("user_id") == "studip") ? _("Dies ist ein vom System generierter Eintrag.") : get_fullname($element->getValue("user_id"),'full',true) ?><br>
            <b><?= _("Letzte �nderung am:")?></b><br>
            <?= strftime("%d.%m.%Y",$element->getValue("chdate")) ?><br>
        <? endif ?>
        <p style="font-size:-1">
            <?= sprintf(_('Alle mit einem Sternchen %s markierten Felder m�ssen ausgef�llt werden.'),'<span style="font-size:1.5em;color:red;font-weigth:bold;">*</span>')?>
        </p>
        <table width="100%" border="0" cellpadding="2" cellspacing="0">
        <? foreach ($element->fields as $field_name => $field_detail) : ?>
            <? if ($field_detail['caption']) : ?>
            <tr><td width="40%">
                <label for="<?= $field_name ?>" class="caption">
                <?= $field_detail['caption'] ?>
                <? if ($field_detail['mandatory']) : ?>
                    <span style="font-size:1.5em;color:red;font-weight:bold;">*</span>
                <? endif ?>
                <?= $form->getFormFieldInfo($field_name) ?>
                </label>
            </td><td>
                <?
                $element_attributes = $attributes[$form->form_fields[$field_name]['type']];
                if (!$element->isChangeable()){
                    $attributes['readonly'] = 'readonly';
                    $attributes['disabled'] = 'disabled';
                }
                ?>
                <?= $form->getFormField($field_name,$element_attributes) ?>
                <? if ($field_name == "lit_plugin") : ?>
                    &nbsp;&nbsp;<span style="font-size:10pt;">
                    <?= (($link = $element->getValue("external_link"))) ? formatReady("=) [Link zum Katalog]" . $link) : _("(Kein Link zum Katalog vorhanden.)") ?>
                    </span>
                <? endif ?>
            </td></tr>
            <? endif ?>
        <? endforeach ?>
        </table>
        <div class="submit_wrapper" data-dialog-button="1">
            <?= CSRFProtection::tokenTag() ?>
            <? if ($element->isChangeable()) : ?>
                <?= $form->getFormButton("send") . ($element->isNewEntry() ? '' : $form->getFormButton("delete")) ?>
            <? elseif ($catalog_id != "new_entry") : ?>
                <?= LinkButton::create(_('Kopie erstellen'), URLHelper::getURL('dispatch.php/literature/edit_element?cmd=clone_entry&_catalog_id='.$catalog_id), array('title' => _("Eine Kopie dieses Eintrages anlegen"), 'data-dialog' => '')) ?>
            <? endif ?>
            <? if ($catalog_id != "new_entry") : ?>
                <?= Assets::img('blank.gif', array('size' => '15@28')) ?>
                <?= LinkButton::create(_('Verf�gbarkeit'), URLHelper::getURL('dispatch.php/literature/edit_element?cmd=check_entry&_catalog_id='.$catalog_id), array('title' =>  _("Verf�gbarkeit �berpr�fen"), 'data-dialog' => '')) ?>
            <? endif ?>
            <? if ($catalog_id != "new_entry" && !$clipboard->isInClipboard($catalog_id)) : ?>
                <?= Assets::img('blank.gif', array('size' => '15@28')) ?>
                <?= LinkButton::create(_('Merkliste'), URLHelper::getURL('dispatch.php/literature/edit_element?cmd=in_clipboard&_catalog_id='.$catalog_id), array('title' =>  _("Eintrag in Merkliste aufnehmen"), 'data-dialog' => '')) ?>
            <? endif ?>
        </div>
    </fieldset>
<?= $form->getFormEnd(); ?>
</div>
<? if ($reload && $return_range) : ?>
<script>
    jQuery('#lit_edit_element').parent().dialog({
        beforeClose: function () {
            window.location.href = "<?= URLHelper::getURL('dispatch.php/literature/edit_list?_range_id='.$return_range, array(), true)?>";
        }
    });
</script>
<? endif;