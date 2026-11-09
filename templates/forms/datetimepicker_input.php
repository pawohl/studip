<div class="formpart" data-form-input-for="<?= htmlReady($name) ?>">
    <label<?= ($required ? ' class="studiprequired"' : '') ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </label>
    <datetimepicker v-model="<?= htmlReady($name) ?>"
                    name="<?= htmlReady($name) ?>"
                    id="<?= $id ?>"
                    <?= ($required ? 'required aria-required="true"' : '')?>
                    <?= $attributes ?>>
</div>
