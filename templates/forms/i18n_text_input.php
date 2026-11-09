<div class="formpart" data-form-input-for="<?= htmlReady($name) ?>">
    <label<?= ($required ? ' class="studiprequired"' : '') ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </label>
    <i18n-textarea
        type="text"
        id="<?= $id ?>"
        name="<?= htmlReady($name) ?>"
        value="<?= htmlReady($value) ?>"
        <?= $required ? 'required' : '' ?>
        @allinputs="setInputs">
    </i18n-textarea>
</div>
