<div class="formpart">
    <label <?= $required ? 'class="studiprequired"' : '' ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
        <input type="password"
               v-model="<?= htmlReady($name) ?>"
               name="<?= htmlReady($name) ?>"
               value="<?= htmlReady($value) ?>"
               id="<?= $id ?>" <?= $required ? 'required aria-required="true"' : '' ?>
            <?= $attributes ?>>
    </label>
</div>
