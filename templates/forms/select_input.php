<div class="formpart" data-form-input-for="<?= htmlReady($name) ?>">
    <label<?= ($required ? ' class="studiprequired"' : '') ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </label>
    <select class="select2" v-model="<?= htmlReady($name) ?>" name="<?= htmlReady($name) ?>" <?= ($required ? 'required aria-required="true"' : '') ?> id="<?= $id ?>" <?= $attributes ?>>
    <? foreach ($options as $key => $option) : ?>
        <option value="<?= htmlReady($key) ?>"<?= ($key == $value ? " selected" : "") ?>>
            <?= htmlReady($option) ?>
        </option>
    <? endforeach ?>
    </select>
</div>
