<?
/**
 * @var array $fach_sem_data
 * @var Studiengang $studiengang
 * @var StgteilBezeichnung $stg_teil_bez
 * @var StgteilVersion $current_version
 * @var SimpleCollection $versionen
 * @var array $abschnitte_data
 * @var Search_StudiengaengeController $controller
 * @var int $close_sections
 * @var Semester $active_sem
 */
?>
<div>
<?= $this->render_partial('search/breadcrumb') ?>
</div>
<? if (!empty($stg_teil)) : ?>
    <? $max_fachsemester = count($fach_sem_data) ? max($fach_sem_data) : 0 ?>
    <table class="default collapsable">
        <caption>
            <?= htmlReady($stg_teil->getDisplayName()) ?>
    <? if (!empty($studiengang) && !empty($stg_teil_bez)) : ?>
        <h3>
            <?= sprintf(_('%s im Studiengang %s'), htmlReady($stg_teil_bez->getDisplayName()), htmlReady($studiengang->getDisplayName())) ?>
            <? if (Config::get()->ENABLE_STUDYCOURSE_INFO_PAGE) : ?>
                <a href="<?= $controller->link_for('search/studiengaenge/info', $studiengang->id)?>" data-dialog>
                    <?= Icon::create('infopage2', Icon::ROLE_CLICKABLE, ['title' => _('Informationen zum Studiengang')]) ?>
                </a>
            <? endif ?>
        </h3>
    <? endif ?>
    <? if (!empty($current_version)) : ?>
        <h4><?= $current_version->getDisplayName() ?></h4>
    <? else : ?>
        <h4><?= htmlReady($versionen->first()->getDisplayName()) ?></h4>
    <? endif ?>
        </caption>
        <colgroup>
            <col style="width: 50%">
            <col>
            <col style="width: 25px;">
            <? for ($i = 1; $i <= $max_fachsemester; $i++) : ?>
                <col style="width: 25px;">
            <? endfor ?>
        </colgroup>
        <thead>
            <tr >
                <th><?= _('Modul') ?></th>
                <th><?= _('Modulteil') ?></th>
                <th><?= _('Info') ?></th>
                <? if ($max_fachsemester) : ?>
                <th colspan="<?= $max_fachsemester ?>" style="text-align: center"><?= _('Semester') ?></th>
                <? endif ?>
            </tr>
        </thead>
        <? foreach ($abschnitte_data as $abschnitt_id => $abschnitt) : ?>
            <tbody class="<?= $close_sections ? 'collapsed' : '' ?>">
                <? $displayed_abschnitt_name = false; ?>
                <? $ueberschrift = (mb_strlen($abschnitt['subheading'])) ?>
                <? if ($ueberschrift) : ?>
                    <tr class="header-row">
                        <td colspan="<?= $max_fachsemester + 4 ?>"><?= htmlReady($abschnitt['subheading']) ?></td>
                    </tr>
                <? endif ?>
                <? foreach ($abschnitt['modules'] as $modul_id => $modul): ?>
                    <? $displayed_module_name = false ?>
                    <? $count_modulteile = count($modul['modulTeile']) ?>
                    <? foreach ($modul['modulTeile'] as $modulTeil_id => $modulTeil): ?>
                        <? if (!$displayed_abschnitt_name) : ?>
                            <tr class="table_header header-row">
                                <? $displayed_abschnitt_name = true ?>
                                <th class="toggle-indicator" colspan="2">
                                    <a class="toggler" href="#"><?= htmlReady($abschnitt['name']) ?><?= $abschnitt['credit_points'] ? ' ' . $abschnitt['credit_points'] . ' ' . _('CP') : '' ?></a>
                                </th>
                                <th class="dont-hide">
                                    <? if (trim($abschnitt['kommentar'])) : ?>
                                        <a data-dialog title="<?= sprintf(_('%s (Kommentar)'), htmlReady($abschnitt['name'])) ?>" href="<?= $controller->link_for('search/studiengaenge/kommentar', $abschnitt_id) ?>">
                                            <?= Icon::create('info-circle', ['title' => _('Zusatzinformationen zum Studiengangabschnitt')]) ?>
                                        </a>
                                    <? endif ?>
                                </th>
                                <? for ($i = 1; $i <= $max_fachsemester; $i++) : ?>
                                    <th><span><?= $i ?><span></th>
                                <? endfor ?>
                            </tr>
                        <? endif ?>
                        <? if (!$displayed_module_name) : ?>
                            <? $displayed_module_name = true ?>
                            <tr>
                                <td<?= $count_modulteile-- > 1 ? ' style="border: none;"' : '' ?>>
                                    <? $abschnitt_modul = StgteilabschnittModul::findOneBySQL('`abschnitt_id` = ? AND `modul_id` = ?', [$abschnitt_id, $modul_id]) ?>
                                    <a data-dialog="size=auto"
                                       title="<?= htmlReady($modul['name']) . ' (' . _('Vollständige Modulbeschreibung') . ')' ?>"
                                       href="<?= $controller->link_for('shared/modul/description/' . $modul_id,
                                           [
                                               'display_language' => ModuleManagementModel::getLanguage(),
                                               'abschnitt_id' => $abschnitt_id,
                                           ]) ?>">
                                        <?= Icon::create('log', Icon::ROLE_CLICKABLE, ['title' => _('Vollständige Modulbeschreibung')]) ?>
                                    </a>
                                    <? if ($modul['courses']) : ?>
                                    <a data-dialog
                                       href="<?= $controller->link_for(
                                           'shared/modul/overview',
                                           $modul_id,
                                           $active_sem->id,
                                           [
                                               'display_language' => ModuleManagementModel::getLanguage(),
                                               'abschnitt_id' => $abschnitt_id,
                                           ]); ?>">
                                        <?= htmlReady($abschnitt_modul->getDisplayName()) ?>
                                    </a>
                                    <? else: ?>
                                        <?= htmlReady($abschnitt_modul->getDisplayName()) ?>
                                    <? endif ?>
                                </td>
                                <td colspan="2"><?= htmlReady($modulTeil['name']) ?></td>
                        <? else : ?>
                            <tr>
                                <td<?= $count_modulteile-- > 1 ? ' style="border: none;"' : '' ?>></td>
                                <td colspan="2"><?= htmlReady($modulTeil['name']) ?></td>
                        <? endif ?>
                        <? for ($i = 1; $i <= $max_fachsemester; $i++) : ?>
                            <? $fachsemester_typ = null ?>
                            <? if (isset(
                                $fach_sem_data[$i],
                                $modulTeil['fachsemester'][$fach_sem_data[$i]],
                                $GLOBALS['MVV_MODULTEIL_STGABSCHNITT']['STATUS']['values'][$modulTeil['fachsemester'][$fach_sem_data[$i]]]
                            )) : ?>
                                <? $fachsemester_typ = $GLOBALS['MVV_MODULTEIL_STGABSCHNITT']['STATUS']['values'][$modulTeil['fachsemester'][$fach_sem_data[$i]]] ?>
                            <? endif ?>
                            <? if (!empty($fachsemester_typ['visible'])) : ?>
                                <td class="mvv-type-<?= $modulTeil['fachsemester'][$fach_sem_data[$i]] ?? '' ?>">
                                    <span title="<? printf(_('%s Semester (%s)'), $i . ModuleManagementModel::getLocaleOrdinalNumberSuffix($i), $fachsemester_typ['name']) ?>">
                                        <?= $fachsemester_typ['icon'] ?>
                                    </span>
                                </td>
                            <? else : ?>
                                <td></td>
                            <? endif ?>
                        <? endfor ?>
                        </tr>
                    <? endforeach ?>
                <? endforeach ?>
            </tbody>
        <? endforeach ?>
    </table>

    <? if (Config::get()->STUDYGROUP_ON_STGTEIL_ENABLE) : ?>
        <h2><?= _('Studentische Arbeitsgruppen') ?></h2>

        <section class="studip-tiles">
            <? foreach ($stg_teil->studygroups as $course) : ?>
                <div>
                    <div class="with-action-menu">
                        <div>
                            <a href="<?= URLHelper::getLink('dispatch.php/course/studygroup/details/'.$course->id) ?>">
                                <?= CourseAvatar::getAvatar($course->id)->getImageTag(Avatar::MEDIUM) ?>
                            </a>
                            <a href="<?= URLHelper::getLink('dispatch.php/course/studygroup/details/'.$course->id) ?>">
                                <strong>
                                    <?= htmlReady($course->name) ?>
                                </strong>
                                <div>
                                    <?= sprintf(
                                        ngettext(
                                            '1 Mitglied',
                                            '%s Mitglieder',
                                            count($course->members)
                                        ),
                                        $course->members
                                    ) ?>
                                </div>
                            </a>
                        </div>
                        <? if ($GLOBALS['perm']->have_perm('admin')) : ?>
                            <form method="post">
                                <?= CSRFProtection::tokenTag() ?>
                                <button class="undecorated"
                                   data-confirm="<?= sprintf(_('Wirklich diese Studiengruppe aus dem Studiengang %s entfernen?'), $stg_teil->getDisplayName()) ?>"
                                   formaction="<?= $controller->remove_studygroup($course->id, $stg_teil->id) ?>">
                                    <?= Icon::create('trash') ?>
                                </button>
                            </form>
                        <? endif ?>
                    </div>
                    <? if (count($course->tags)) : ?>
                    <div>
                        <? foreach ($course->tags as $tag) : ?>
                            <?= '#'.htmlReady($tag->name) ?>
                        <? endforeach ?>
                    </div>
                    <? endif ?>
                </div>
            <? endforeach ?>

        </section>
    <? endif ?>
<? endif ?>
