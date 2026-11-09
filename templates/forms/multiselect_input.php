<div class="formpart" data-form-input-for="<?= htmlReady($name) ?>">
    <label<?= ($required ? ' class="studiprequired"' : '') ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </label>
    <multiselect name="<?= htmlReady($name) ?>[]" <?= ($required ? 'required aria-required="true"' : '') ?>
                 :options="<?= htmlReady(json_encode($options)) ?>"
                 :value="<?= htmlReady(json_encode($value)) ?>"
                 v-model="<?= htmlReady($name) ?>"
                 id="<?= $id ?>"
                 <?= $attributes ?>>
    </multiselect>
</div>
