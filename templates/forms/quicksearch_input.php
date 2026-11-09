<div class="formpart" data-form-input-for="<?= htmlReady($name) ?>">
    <label<?= ($required ? ' class="studiprequired"' : '') ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </label>
    <span>
        <quicksearch value="<?= htmlReady($value) ?>"
                     name="<?= htmlReady($name) ?>"
                     @update:modelValue="(new_id, new_item_name) => { this.<?= htmlReady($name) ?> = new_id; }"
                     id="<?= $id ?>"
                     <?= ($required ? 'required aria-required="true"' : '') ?>
                     <?= $attributes ?>>
    </span>'
</div>
