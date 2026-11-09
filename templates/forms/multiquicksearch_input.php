<div class="formpart" data-form-input-for="<?= htmlReady($name) ?>">
    <label<?= ($required ? ' class="studiprequired"' : '') ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
    </label>
    <multiquicksearch name="<?= htmlReady($name) ?>"
                 v-model="<?= htmlReady($name) ?>"
                 <?= ($required ? 'required aria-required="true"' : '') ?>
                 :value="<?= htmlReady(json_encode($value)) ?>"
                 id="<?= $id ?>"
                 <?= $attributes ?>>
    </multiquicksearch>
</div>
