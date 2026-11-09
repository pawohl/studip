<label<?= ($required ? ' class="studiprequired"' : '') ?> for="<?= $id ?>">
    <span class="textlabel">
        <?= htmlReady($title) ?>
    </span>
    <? if ($required) : ?>
        <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
    <? endif ?>
</label>
<range-input v-model="<?= htmlReady($name) ?>"
             name="<?= htmlReady($name) ?>"
             id="<?= htmlReady($id) ?>"
             <? if (isset($min)) printf(':min="%s"', $min); ?>
             <? if (isset($max)) printf(':max="%s"', $max); ?>
             <? if (isset($step)) printf(':max="%s"', $step); ?>
    <?= $attributes ?>></range-input>
