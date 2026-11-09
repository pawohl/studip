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
        type="textarea"
        id="<?= $id ?>"
        name="<?= htmlReady($name) ?>"
        value="<?= htmlReady($value) ?>"
        <?= $required ? 'required' : '' ?>
        @selectlanguage="(language_id) => selectLanguage('<?= htmlReady($name) ?>', language_id)"
        @allinputs="setInputs">
    </i18n-textarea>
</div>
