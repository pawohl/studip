<?php
/**
 * @var Modul $modul
 * @var Semester $semester
 * @var string $pruef_ebene
 * @var int $type
 * @var string $code
 * @var string $title
 */
?>

<? $deskriptor = $modul->getDeskriptor() ?>
<table class="default mvv-modul-details nohover">
    <tr>
        <th class="mvv-modul-details-head" style="width: 30%">
            <?= htmlReady($code ?: $modul->code) ?>
        </th>
        <th class="mvv-modul-details-head" style="width: 30%">
            <?= $modul->responsible_institute ? htmlReady($modul->responsible_institute->institute->name) : '' ?>
        </th>
        <th class="mvv-modul-details-head" style="width: 40%"></th>
        <th class="mvv-modul-details-head" style="white-space: nowrap;">
            <?= sprintf("%d CP", $modul->getReplacedValue('kp')) ?>
        </th>
    </tr>
    <tr>
        <td colspan="2">
            <h3><?= htmlReady($title ?: $deskriptor->bezeichnung) ?></h3>
            <?= _('Lehrveranstaltungen') ?> <?= htmlReady($semester->name) ?>
        </td>
        <td>
            <dl>
            <? foreach ($GLOBALS['MVV_MODUL']['PERSONEN_GRUPPEN']['values'] as $key => $gruppe) : ?>
                <? $contacts = $modul->contact_assignments->findBy('category', $key)->orderBy('position', SORT_NUMERIC); ?>
                <? if ($gruppe['visible'] && count($contacts)) : ?>
                <dt><?= htmlReady($gruppe['name']) ?></dt>
                <? foreach ($contacts as $modul_contact): ?>
                <dd><?= htmlReady($modul_contact->contact->getDisplayName()) ?></dd>
                <? endforeach; ?>
                <? endif; ?>
            <? endforeach; ?>
            </dl>
        </td>
        <td>
            <? if ($GLOBALS['perm']->have_perm('admin')) : ?>
                <a href="<?= URLHelper::getLink("dispatch.php/shared/modul/mail/{$modul->id}/{$semester->id}")?>" data-dialog>
                    <?= Icon::create('mail', Icon::ROLE_CLICKABLE, tooltip2(_('Nachricht an alle Teilnehmenden der Veranstaltungen, die diesem Modul zugeordnet sind.')))?>
                </a>
            <? endif; ?>
        </td>
    </tr>
    <tr>
        <td colspan="4" style="padding: 0;">
            <table class="default nohover">
                <? if (mb_strlen($deskriptor->getReplacedValue('voraussetzung')) > 0): ?>
                    <tr>
                        <td style="width: 20%; font-weight: bold;"><?= _('Teilnahmevoraussetzungen') ?></td>
                        <td ><?= formatReady($deskriptor->getReplacedValue('voraussetzung')) ?></td>
                    </tr>
                <? endif; ?>
                <? if (mb_strlen($deskriptor->getReplacedValue('kommentar'))) : ?>
                    <tr>
                        <td style="width: 20%; font-weight: bold;"><?= _('Hinweise') ?></td>
                        <td><?= formatReady($deskriptor->getReplacedValue('kommentar')) ?></td>
                    </tr>
                <? endif; ?>
                <? if (mb_strlen($deskriptor->ersatztext) > 0): ?>
                    <tr>
                        <td style="width: 20%; font-weight: bold;"> </td>
                        <td><?= formatReady($deskriptor->getReplacedValue('ersatztext')) ?></td>
                    </tr>
                <? else: ?>

                    <? if ($modul->kapazitaet > 0): ?>
                        <tr>
                            <td style="width: 20%; font-weight: bold;"><?= _('Kapazität Modul') ?></td>
                            <td>
                                <?= htmlReady($modul->kapazitaet) ?>
                                <? if (mb_strlen($deskriptor->getReplacedValue('kommentar_kapazitaet')) > 0): ?>
                                    (<?= formatReady($deskriptor->getReplacedValue('kommentar_kapazitaet')) ?>)
                                <? endif; ?>

                            </td>
                        </tr>
                    <? endif; ?>
                    <? if (mb_strlen($pruef_ebene) > 0): ?>
                        <tr>
                            <td style="width: 20%; font-weight: bold;"><?= _('Prüfungsebene') ?></td>
                            <td><?= htmlReady($pruef_ebene) ?></td>
                        </tr>
                    <? endif; ?>
                    <? if (mb_strlen($deskriptor->getReplacedValue('pruef_vorleistung'))) : ?>
                        <tr>
                            <td style="width: 20%; font-weight: bold;"><?= _('Prüfungsvorleistung Modul') ?></td>
                            <td><?= formatReady($deskriptor->getReplacedValue('pruef_vorleistung')) ?></td>
                        </tr>
                    <? endif; ?>
                    <? if (mb_strlen($deskriptor->pruef_leistung)) : ?>
                        <tr>
                            <td style="width: 20%; font-weight: bold;"><?= _('Prüfungsleistung Modul') ?></td>
                            <td><?= formatReady($deskriptor->getReplacedValue('pruef_leistung')) ?></td>
                        </tr>
                    <? endif; ?>
                    <? if (mb_strlen($deskriptor->kompetenzziele)): ?>
                        <tr>
                            <td style="width: 20%; font-weight: bold;"><?= _('Kompetenzziele') ?></td>
                            <td><?= formatReady($deskriptor->getReplacedValue('kompetenzziele')) ?></td>
                        </tr>
                    <? endif; ?>

                <? endif; ?>
            </table>
        </td>
    </tr>
    <? if ($type !== 3): ?>
        <tr>
            <? if ($type === 1): ?>
                <th><?= _('Modulteile') ?></th>
            <? endif; ?>
            <th <? if ($type === 2): ?> colspan="4" <? endif; ?> ><?= _('Semesterveranstaltungen') ?></th>
            <? if ($type === 1): ?>
                <th colspan="2"><?= _('Prüfungsleistung') ?></th>
            <? endif; ?>
        </tr>
        <? foreach ($modul->modulteile as $modulteil): ?>
            <? $modulteil_deskriptor = $modulteil->getDeskriptor() ?>
            <tr>
                <? if ($type === 1): ?>
                <td>
                    <b> <?= htmlReady($modulteil->getDisplayName()) ?> </b>
                    <? if (mb_strlen($modulteil_deskriptor->getReplacedValue('kommentar')) > 0): ?>
                    <?= trim($modulteil_deskriptor->getReplacedValue('kommentar')) ? '<br>(' . formatReady($modulteil_deskriptor->getReplacedValue('kommentar')) . ')' : '' ?>
                    <? endif; ?>
                    <? if (mb_strlen($modulteil_deskriptor->getReplacedValue('voraussetzung')) > 0): ?>
                        <br>
                        <b><?= _('Teilnahmevoraussetzungen') ?>:</b> <?= formatReady($modulteil_deskriptor->getReplacedValue('voraussetzung')) ?>
                    <? endif; ?>
                </td>
                <? endif; ?>
                <td  <? if ($type === 2): ?> colspan="3" <? endif; ?>>
                    <? foreach ($modulteil->lvgruppen as $gruppe): ?>
                        <? if (mb_strlen($gruppe->alttext) > 0): ?>
                            <?= formatReady($gruppe->alttext) ?>
                        <? endif; ?>
                        <?
                        $courses = $gruppe->courses->filter(
                            fn(\Course $course) => $course->isInSemester($semester) && $course->isVisibleForUser()
                        );
                        $sort_format = Config::get()->getValue('IMPORTANT_SEMNUMBER') ? 'number_name' : 'number';
                        $courses->uasort(
                            fn(\Course $course_a, \Course $course_b) =>
                                strnatcasecmp($course_a->getFullName($sort_format), $course_b->getFullName($sort_format))
                        );
                        ?>
                        <? if (count($courses)) : ?>
                        <ul>
                        <? foreach ($courses as $course): ?>
                            <li>
                                <a href="<?= URLHelper::getLink('dispatch.php/course/details', ['sem_id' => $course->id]) ?>">
                                <?= htmlReady(($course->veranstaltungsnummer ? $course->veranstaltungsnummer . ' - ' : '') . $course->name) ?>
                                </a>
                                <? if (!$course->visible) : ?>
                                <em><?= _('[versteckt]') ?></em>
                                <? endif; ?>
                                <? if (Config::get()->COURSE_SEARCH_SHOW_ADMISSION_STATE) : ?>
                                    <?
                                    $admission_status = GlobalSearchCourses::getStatusCourseAdmission($course->id, $course->admission_prelim);
                                    ?>
                                    <? echo match ($admission_status) {
                                        1 => Icon::create('span-2quarter', Icon::ROLE_STATUS_YELLOW, [
                                            'alt' => _('Eingeschränkter Zugang'),
                                            'title' => _('Eingeschränkter Zugang'),
                                            'style' => 'vertical-align: text-bottom',
                                        ]),
                                        2 => Icon::create('span-empty', Icon::ROLE_STATUS_RED, [
                                            'alt' => _('Kein Zugang'),
                                            'title' => _('Kein Zugang'),
                                            'style' => 'vertical-align: text-bottom',
                                        ]),
                                        default => Icon::create('span-full', Icon::ROLE_STATUS_GREEN, [
                                            'alt' => _('Uneingeschränkter Zugang'),
                                            'title' => _('Uneingeschränkter Zugang'),
                                            'style' => 'vertical-align: text-bottom',
                                        ]),
                                    }; ?>
                                <? endif; ?>
                            </li>
                        <? endforeach; ?>
                        </ul>
                        <? endif; ?>
                    <? endforeach; ?>
                </td>
                <? if ($type === 1) : ?>
                    <td colspan="2">
                        <? if (mb_strlen($modulteil_deskriptor->getReplacedValue('pruef_vorleistung')) > 0) : ?>
                            <b><?= _('Prüfungsvorleistung') ?>:</b> <?= htmlReady($modulteil_deskriptor->getReplacedValue('pruef_vorleistung')) ?>

                        <? endif; ?>
                        <? if (mb_strlen($modulteil_deskriptor->pruef_leistung) > 0) : ?>
                            <b><?= _('Prüfungsform') ?>:</b> <br/><?= htmlReady($modulteil_deskriptor->getReplacedValue('pruef_leistung')) ?> (<?= ($modulteil->anteil_note ? '(' . htmlReady($modulteil->anteil_note) . '%)' : '') ?>
                        <? endif; ?>
                    </td>
                <? endif; ?>
            </tr>
        <? endforeach; ?>
    <? endif; ?>
</table>
