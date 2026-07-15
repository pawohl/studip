<? if ($show_form): ?>
    <form class="default resource-category-form"
          method="post"
          action="<?= ($mode == 'add')
                    ? URLHelper::getLink(
                        'dispatch.php/resources/category/add'
                    )
                    : URLHelper::getLink(
                        'dispatch.php/resources/category/edit/' . $category->id
                    ) ?>"
          data-dialog="reload-on-close">

        <?= CSRFProtection::tokenTag() ?>
        <fieldset>
            <legend><?= _('Grunddaten') ?></legend>
            <label>
                <?= _('Name der Kategorie') ?>
                <input type="text" name="name" value="<?= htmlReady($name) ?>">
            </label>
            <label>
                <?= _('Beschreibungstext') ?>
                <input type="text" name="description" value="<?= htmlReady($description) ?>">
            </label>
            <label>
                <?= _('Name der Datenklasse') ?>
                <select name="class_name">
                    <? foreach ($class_names as $possible_class_name): ?>
                        <option value="<?= htmlReady($possible_class_name) ?>"
                                <?= $class_name == $possible_class_name ? 'selected="selected"' : ''?>>
                            <?= htmlReady($possible_class_name) ?>
                        </option>
                    <? endforeach ?>
                </select>
                <p><?= _('Hinweis: Je nach gewählter Datenklasse werden automatisch weitere Eigenschaften zur Kategorie hinzugefügt.') ?></p>
            </label>
            <fieldset class="undecorated resource-category-form-icon">
                <legend>
                    <?= _('Icon') ?>
                </legend>
                    <?php foreach (ResourceCategory::getAvailableIcons() as $value => $icon) : ?>
                            <label for="iconnr_<?= $value ?>" class="resource-category-form-icon-label">
                                <input type="radio" 
                                    id="iconnr_<?= $value ?>" 
                                    name="iconnr" 
                                    value="<?= $value ?>"
                                    class="resource-category-form-icon-input"
                                    <?= (int)$iconnr === $value ? 'checked' : '' ?>>
                                <?= Icon::create($icon)->asImg(20, ['aria-hidden' => true]) ?>
                                <span class="sr-only"><?= htmlReady($icon) ?></span>
                            </label>
                    <?php endforeach; ?>
            </fieldset>
        </fieldset>
        <fieldset class="Properties">
            <legend><?= _('Eigenschaften') ?></legend>
            <table class="default resource-category-properties-table">
                <thead>
                    <tr>
                        <th><?= _('Name') ?></th>
                        <th><?= _('Wünschbar') ?></th>
                        <th><?= _('Geschützt') ?></th>
                        <th class="actions"><?= _('Löschen') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <? if ($previously_set_properties): ?>
                        <? foreach ($previously_set_properties as $property): ?>
                            <tr data-property_id="<?= htmlReady($property['id']) ?>">
                                <td>
                                    <span><?= htmlReady($property['name']) ?></span>
                                    <input type="hidden" value="1"
                                           name="prop[<?= htmlReady($property['id']) ?>]">
                                </td>
                                <td>
                                    <input type="checkbox" value="1"
                                           name="prop_requestable[<?= htmlReady($property['id']) ?>]"
                                           <?= $property['requestable'] ? 'checked="checked"' : '' ?>>
                                </td>
                                <td>
                                    <input type="checkbox" value="1"
                                           name="prop_protected[<?= htmlReady($property['id']) ?>]"
                                           <?= $property['protected'] ? 'checked="checked"' : '' ?>>
                                </td>
                                <td class="actions">
                                    <? if ($property['system']) : ?>
                                        <?= tooltipIcon(
                                            _('Dies ist eine Systemeigenschaft, die nicht gelöscht werden darf!')
                                        ) ?>
                                    <? else : ?>
                                        <a title="<?= _('Löschen') ?>" class="delete-action">
                                            <?= Icon::create('trash') ?>
                                        </a>
                                    <? endif ?>
                                </td>
                            </tr>
                        <? endforeach ?>
                    <? endif ?>
                    <? if ($set_properties): ?>
                        <? foreach ($set_properties as $property): ?>
                            <tr data-property_id="<?= htmlReady($property['id']) ?>">
                                <td>
                                    <span><?= htmlReady($property['name']) ?></span>
                                    <input type="hidden" value="1"
                                           name="prop[<?= htmlReady($property['id']) ?>]">
                                </td>
                                <td>
                                    <input type="checkbox" value="1"
                                           name="prop_requestable[<?= htmlReady($property['id']) ?>]"
                                           <?= $property['requestable'] ? 'checked="checked"' : '' ?>>
                                </td>
                                <td>
                                    <input type="checkbox" value="1"
                                           name="prop_protected[<?= htmlReady($property['id']) ?>]"
                                           <?= $property['protected'] ? 'checked="checked"' : '' ?>>
                                </td>
                                <td class="actions">
                                    <a title="<?= _('Löschen') ?>" class="delete-action">
                                        <?= Icon::create('trash') ?>
                                    </a>
                                </td>
                            </tr>
                        <? endforeach ?>
                    <? endif ?>
                </tbody>
                <tfoot>
                    <tr class="template invisible">
                        <td>
                            <span class="name"></span>
                            <input class="property-input"
                                   type="hidden" value="1">
                        </td>
                        <td>
                            <input class="requestable-input"
                                   type="checkbox" value="1">
                        </td>
                        <td>
                            <input class="protected-input"
                                   type="checkbox" value="1">
                        </td>
                        <td class="actions">
                            <a title="<?= _('Löschen') ?>" class="delete-action">
                                <?= Icon::create('trash') ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6">
                            <select class="available-property-select">
                                <? foreach ($available_properties as $ap): ?>
                                    <option value="<?= htmlReady($ap->id) ?>"
                                            <?= in_array($ap->id, array_keys($previously_set_properties))
                                              ? 'disabled="disabled"'
                                              : '' ?>>
                                        <?= htmlReady($ap) ?>
                                    </option>
                                <? endforeach ?>
                            </select>
                            <?= Icon::create('add')->asInput(['class' => 'add-action']) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </fieldset>

        <div data-dialog-button>
            <?= \Studip\Button::create(_('Speichern'), 'confirmed') ?>
        </div>
    </form>
<? endif ?>
