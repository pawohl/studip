<div class="formpart">
    <label <?= $required ? 'class="studiprequired"' : '' ?> for="<?= $id ?>">
        <span class="textlabel">
            <?= htmlReady($title) ?>
        </span>
        <? if ($required) : ?>
            <span class="asterisk" title="<?= _('Dies ist ein Pflichtfeld') ?>" aria-hidden="true">*</span>
        <? endif ?>
        <input type="text"
               list="<?= htmlReady($title) ?>"
               v-model="<?= htmlReady($name) ?>"
               name="<?= htmlReady($name) ?>"
               value="<?= htmlReady($value) ?>"
               id="<?= htmlReady($id) ?>"
               <?= $required ? 'required aria-required="true"' : '' ?>
            <?= $attributes ?>>
        <datalist class="" id="<?= htmlReady($title)  ?>">
            <? foreach ($options as $key => $option) : ?>
                <option value="<?= htmlReady($option) ?>"<?= ($option == $value ? " selected" : "") ?>>
                </option>
            <? endforeach ?>
        </datalist>
    </label>

</div>


