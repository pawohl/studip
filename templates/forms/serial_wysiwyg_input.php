<div class="formpart" data-form-input-for="<?= htmlReady($name) ?>">
    <label<?= $required ? ' class="studiprequired"' : '' ?> for="<?= htmlReady($id) ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </label>
    <serial-text-markers :markers="<?= htmlReady($markers) ?>" editor="<?= htmlReady($id) ?>"></serial-text-markers>
    <studip-wysiwyg
        id="<?= htmlReady($id) ?>"
        v-model="<?= htmlReady($name) ?>"
        value="<?= htmlReady($value) ?>"
        <?= $required ? 'required' : '' ?>>
    </studip-wysiwyg>
</div>
