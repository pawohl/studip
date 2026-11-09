<?php
/**
 * @var Admin_BannerController $controller
 * @var Banner $banner
 * @var array $target_types
 * @var Course $seminar
 * @var Institute $institut
 * @var User $user
 * @var array $priorities
 * @var array $assigned
 * @var array $rolesStats
 * @var Role $roles
 */
?>
<form action="<?= $controller->url_for('admin/banner/edit', $banner->id) ?>" method="post" enctype="multipart/form-data" class="default">
    <?= CSRFProtection::tokenTag() ?>

    <fieldset>

        <label>
            <? if ($banner['banner_path']) : ?>
                <?= $banner->toImg(['style' => 'max-width:500px']) ?>
            <? else : ?>
                <?= _('Noch kein Bild hochgeladen') ?>
            <? endif; ?><br>

            <label class="file-upload">
                <?= _('Bilddatei auswählen') ?>
                <input id="imgfile" name="imgfile" type="file" accept="image/*">
                <input type="hidden" name="banner_path" value="<?= $banner['banner_path'] ?>">
            </label>
        </label>

        <label>
            <?= _('Beschreibung') ?>
            <input type="text" id="description" name="description" value="<?= htmlReady($banner['description']) ?>" size="40" maxlength="254">
        </label>

        <label>
            <?= _('Alternativtext') ?>

            <input type="text" id="alttext" name="alttext" value="<?= htmlReady($banner['alttext']) ?>" size="40" maxlength="254">
        </label>

        <label>
            <?= _('Verweis-Typ') ?>

            <? if (!$banner->isNew()) : ?>
                <input name="target_type" type="hidden" size="8" value="<?= $banner['target_type'] ?>">
            <? endif; ?>
            <select id="target_type" name="target_type" <?= $banner->isNew() ?: 'disabled' ?>>
                <? foreach ($target_types as $key => $label) : ?>
                    <option value="<?= $key ?>" <? if ($banner['target_type'] == $key) echo 'selected'; ?>>
                        <?= $label ?>
                    </option>
                <? endforeach; ?>
            </select>
        </label>

        <label>
            <?= _('Verweisziel') ?>

            <? if ($banner->isNew()) : ?>
                <input type="url" class="target-url" name="target" placeholder="<?= _('URL eingeben') ?>" value="<?= htmlReady($flash['request']['target']) ?>" style="width: 240px;" maxlen="254">

                <?= QuickSearch::get('seminar', new StandardSearch('Seminar_id'))
                    ->setInputStyle('width: 240px')
                    ->setInputClass('target-seminar')
                    ->render() ?>

                <?= QuickSearch::get('institut', new StandardSearch('Institut_id'))
                    ->setInputStyle('width: 240px')
                    ->setInputClass('target-inst')
                    ->render() ?>

                <?= QuickSearch::get('user', new StandardSearch('username'))
                    ->setInputStyle('width: 240px')
                    ->setInputClass('target-user')
                    ->render() ?>

                <span class="target-none"><?= _('Kein Verweisziel') ?></span>
            <? else : ?>
                <? if (in_array($banner['target_type'], words('none url'))) : ?>
                    <input type="text" name="target" size="40" maxlen="254" value="<?= htmlReady($banner['target']) ?>">
                <? elseif ($banner['target_type'] == "seminar") : ?>
                    <?= $seminar ?>
                <? elseif ($banner['target_type'] == "inst") : ?>
                    <?= $institut ?>
                <? else : ?>
                    <?= $user ?>
                <? endif; ?>
            <? endif; ?>
        </label>

        <label>
            <?= _('Anzeigen ab') ?>

            <input type="text" size="20" name="start_date" id="start_date" value="<?= $banner['startdate'] ?
                date('d.m.Y H:i', $banner['startdate']) : '' ?>" data-datetime-picker>
        </label>

        <label>
            <?= _('Anzeigen bis') ?>

            <input type="text" size="20" name="end_date" id="end_date" value="<?= $banner['enddate'] ?
                date('d.m.Y H:i', $banner['enddate']) : '' ?>" data-datetime-picker>
        </label>

        <label>
            <?= _('Priorität') ?>

            <select id="priority" name="priority">
                <? foreach ($priorities as $key => $label) : ?>
                    <option value="<?= $key ?>" <? if ($banner['priority'] == $key) echo 'selected'; ?>>
                        <?= $label ?>
                    </option>
                <? endforeach; ?>
            </select>
        </label>

        <label>
            <?= _('Sichtbarkeit') ?>

            <select id="assignedroles" class="nested-select" name="assignedroles[]" multiple>
                <? if ($assigned) : ?>
                    <? foreach ($assigned as $assignedrole) : ?>
                        <option value="<?= $assignedrole->getRoleid() ?>" selected>
                            <?= htmlReady($assignedrole->getRolename()) ?>
                            <? if ($assignedrole->getSystemtype()) : ?>[<?= _('Systemrolle') ?>]<? endif ?>
                            (<?= $rolesStats[$assignedrole->getRoleid()]['explicit'] + $rolesStats[$assignedrole->getRoleid()]['implicit'] ?>)
                        </option>
                    <? endforeach ?>
                <? endif ?>
                <? foreach ($roles as $role) : ?>
                    <option value="<?= $role->getRoleid() ?>">
                        <?= htmlReady($role->getRolename()) ?>
                        <? if ($role->getSystemtype()) : ?>[<?= _('Systemrolle') ?>]<? endif ?>
                        (<?= $rolesStats[$role->getRoleid()]['explicit'] + $rolesStats[$role->getRoleid()]['implicit'] ?>)
                    </option>
                <? endforeach ?>
            </select>
        </label>
    </fieldset>

    <footer data-dialog-button>
        <?= Studip\Button::create(_('Speichern'), 'speichern') ?>
        <?= Studip\LinkButton::createCancel(
            _('Abbrechen'),
            $controller->index("#banner-{$banner->id}")
        ) ?>
    </footer>
</form>

<? if ($banner->isNew()) : ?>
    <script type="text/javascript">
        jQuery(function($) {
            $('#target_type').change(function() {
                var target = $(this).val();
                $(this).closest('label').next().find('[class^="target"]').hide().filter('.target-' + target).show();
            }).change();
        });
    </script>
<? endif; ?>
