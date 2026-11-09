<input type="hidden" name="<?= htmlReady($name) ?>" value="0">
    <label<?= ($required ? ' class="studiprequired"' : '') ?>>
    <input type="checkbox"
           v-model="<?= htmlReady($name) ?>"
           name="<?= htmlReady($name) ?>"
           value="1"
           id="<?= $id ?>"
           <?= ($required ? 'required aria-required="true"' : '') ?>
           <?= $attributes ?>>
    <span class="textlabel">
        <?= htmlReady($title) ?>
    </span>
    <? if ($required) : ?>
        <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
    <? endif ?>
</label>
