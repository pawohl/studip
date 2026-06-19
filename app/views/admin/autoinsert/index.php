<?php
/**
 * @var Admin_AutoinsertController $controller
 * @var array $seminar_search
 * @var array $userdomains
 */
?>
<? if (isset($flash['delete'])): ?>
    <?= (string)QuestionBox::create(
        _('Wollen Sie die Zuordnung der Veranstaltung zum automatischen Eintragen wirklich löschen?'),
        $controller->deleteURL($flash['delete'], ['delete' => 1]),
        $controller->deleteURL($flash['delete'], ['back' => 1])
    ) ?>
<? endif; ?>
    <form class="default" action="<?= $controller->index() ?>" method="post">
        <?= CSRFProtection::tokenTag() ?>
        <?= $this->render_partial('admin/autoinsert/_search.php', compact('sem_search', 'sem_select')) ?>
    </form>

<? if (count($seminar_search) > 0): ?>
    <br>
    <form class="default" action="<?= $controller->new() ?>" method="post">
        <?= CSRFProtection::tokenTag() ?>
        <fieldset>
            <legend>
                <?= _('Suchergebnisse') ?>
            </legend>

            <label>
                <?= _('Veranstaltung') ?>
                <select name="sem_id" id="sem_id">
                    <? foreach ($seminar_search as $seminar): ?>
                        <option value="<?= $seminar[0] ?>">
                            <?= htmlReady($seminar[1]) ?>
                        </option>
                    <? endforeach; ?>
                </select>
            </label>

            <h2>
                <?= _('Automatisches Eintragen mit Nutzerstatus:') ?>
            </h2>

            <?php foreach ($userdomains as $domain): ?>
                <h3>
                    <?= htmlReady($domain['name']) ?>
                </h3>
                <section class="hgroup">
                    <label>
                        <input type="checkbox" name="rechte[<?= $domain['id'] ?>][]" value="dozent">
                        <?= _('Dozent') ?>
                    </label>
                    <label>
                        <input type="checkbox" name="rechte[<?= $domain['id'] ?>][]" value="tutor">
                        <?= _('Tutor') ?>
                    </label>
                    <label>
                        <input type="checkbox" name="rechte[<?= $domain['id'] ?>][]" value="autor">
                        <?= _('Autor') ?>
                    </label>
                </section>
            <?php endforeach; ?>
        </fieldset>
        <footer>
            <?= Studip\Button::create(_('Anlegen'), 'anlegen') ?>
        </footer>
    </form>
<? endif; ?>

<? if (!empty($auto_sems)) : ?>
    <table class="default">
        <caption><?= _('Vorhandene Zuordnungen') ?></caption>
        <thead>
        <tr>
            <th><?= _('Veranstaltungen') ?></th>
            <th style="text-align: center;"><?= _('Dozent') ?></th>
            <th style="text-align: center;"><?= _('Tutor') ?></th>
            <th style="text-align: center;"><?= _('Autor') ?></th>
            <th class="actions"><?= _('Aktionen') ?></th>
        </tr>
        </thead>
        <tbody>
        <? foreach ($auto_sems as $auto_sem): ?>
            <tr>
                <td>
                    <a href="<?= URLHelper::getLink('dispatch.php/course/go', ['to' => $auto_sem['seminar_id']]) ?>">
                        <?= htmlReady($auto_sem['Name']) ?>
                    </a>
                </td>

                <?= $this->render_partial("admin/autoinsert/_status.php", ['status' => 'dozent', 'auto_sem' => $auto_sem, 'domains' => $userdomains]) ?>
                <?= $this->render_partial("admin/autoinsert/_status.php", ['status' => 'tutor', 'auto_sem' => $auto_sem, 'domains' => $userdomains]) ?>
                <?= $this->render_partial("admin/autoinsert/_status.php", ['status' => 'autor', 'auto_sem' => $auto_sem, 'domains' => $userdomains]) ?>
                <td class="actions">
                    <a href="<?= $controller->delete($auto_sem['seminar_id'] ) ?>">
                        <?= Icon::create('trash')->asImg([
                            'title' => _('Veranstaltung entfernen'),
                            'class' => 'text-top',
                        ]) ?>
                    </a>
                </td>
            </tr>
        <? endforeach; ?>
        </tbody>
    </table>
<? endif ?>
