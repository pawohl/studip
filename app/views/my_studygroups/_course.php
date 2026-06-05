<?php
/**
 * @var array $studygroups
 * @var bool $is_widget
 */
?>
<? foreach ($studygroups as $group)  : ?>
    <tr>
        <td class="gruppe<?= $group['gruppe'] ?>"></td>
        <td>
            <?= StudygroupAvatar::getAvatar($group['seminar_id'])->getImageTag(Avatar::SMALL, ['title' => $group['name']]) ?>
        </td>

        <td style="text-align: left">
            <a href="<?= URLHelper::getLink('dispatch.php/course/go', ['to' => $group['seminar_id']]) ?>"
                <?= $group['last_visitdate'] < $group['chdate'] ? 'style="color: red;"' : '' ?>>
                <?= htmlReady($group['name']) ?>
            </a>
            <? if ($group['visible'] == 0) : ?>
                <? $infotext = _("Versteckte Studiengruppen können über die Suchfunktionen nicht gefunden werden."); ?>
                <? $infotext .= " "; ?>
                <? if (Config::get()->ALLOW_DOZENT_VISIBILITY) : ?>
                    <? $infotext .= _("Um die Studiengruppe sichtbar zu machen, wählen Sie den Punkt \"Sichtbarkeit\" im Administrationsbereich der Veranstaltung."); ?>
                <? else : ?>
                    <? $infotext .= _("Um die Studiengruppe sichtbar zu machen, wenden Sie sich an die Admins."); ?>
                <? endif ?>
                <?= _("[versteckt]") ?>
                <?= tooltipicon($infotext) ?>
            <? endif ?>
        </td>
        <td data-sort-value="<?= $group['mkdate'] ?>">
            <?= htmlReady(date('d.m.Y', $group['mkdate'])) ?>
        </td>
        <td style="text-align: left; white-space: nowrap;">
            <? if (!empty($group['navigation'])) : ?>
                <ul class="my-courses-navigation" style="flex-wrap: nowrap">
                <? foreach (MyRealmModel::array_rtrim($group['navigation']) as $key => $nav)  : ?>
                    <? if (isset($nav) && $nav->isVisible(true)) : ?>
                        <li class="my-courses-navigation-item <? if ($nav->getImage()->signalsAttention()) echo 'my-courses-navigation-important'; ?>">
                            <a href="<?=
                            URLHelper::getLink('dispatch.php/course/go',
                                ['to'     => $group['seminar_id'],
                                      'redirect_to' => $nav->getURL()]) ?>" <?= $nav->hasBadgeNumber() ? 'class="badge" data-badge-number="' . intval($nav->getBadgeNumber()) . '"' : '' ?>>
                                <?= $nav->getImage()->asImg($nav->getLinkAttributes()) ?>
                            </a>
                        </li>
                    <? elseif (is_string($key)) : ?>
                        <li class="my-courses-navigation-item">
                            <span class="empty-slot" style="width: 20px"></span>
                        </li>
                    <? endif ?>
                <? endforeach ?>
                </ul>
            <? endif ?>
        </td>
        <? if (!$is_widget) : ?>
            <td style="text-align: right">
                <? if ($group['user_status'] === 'dozent') : ?>
                    <? $adminmodule = $group["sem_class"]->getAdminModuleObject(); ?>
                    <? if ($adminmodule && $group['tools']->findOneby('plugin_id', $adminmodule->getPluginId())) : ?>
                        <? $adminnavigation = $adminmodule->getIconNavigation($group['seminar_id'], 0, $GLOBALS['user']->id); ?>
                    <? endif ?>
                    <? if (!empty($adminnavigation)) : ?>
                        <a href="<?= URLHelper::getLink($adminnavigation->getURL(), ['cid' => $group['seminar_id']]) ?>">
                            <?= $adminnavigation->getImage()->asImg($adminnavigation->getLinkAttributes())?>
                        </a>
                    <? endif ?>

                <? elseif (!empty($group['binding'])) : ?>
                    <?= Icon::create('door-leave', Icon::ROLE_INACTIVE)->asImg(['title' => _('Die Teilnahme ist bindend. Bitte wenden Sie sich an die Lehrenden.')]) ?>
                <? else : ?>
                    <form action="<?= URLHelper::getLink("dispatch.php/my_courses/decline/{$group['seminar_id']}") ?>"
                          method="post"
                          data-confirm="<?= htmlReady(sprintf(_('Wollen Sie sich von der Studiengruppe "%s" wirklich abmelden?'), $group['name'])) ?>"
                    >
                        <input type="hidden" name="from" value="studygroups">
                        <input type="hidden" name="cmd" value="kill">
                        <input type="hidden" name="studipticket" value="<?= htmlReady(get_ticket()) ?>">
                        <?= Icon::create('door-leave')->asInput(['title' => _('Aus der Studiengruppe abmelden')]) ?>
                    </form>
                <? endif ?>
            </td>
            <? endif ?>
    </tr>
<? endforeach ?>
