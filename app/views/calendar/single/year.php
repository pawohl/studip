<table style="width: 100%;">
    <tr>
        <td colspan="3" style="text-align: center; vertical-align: middle;">
            <div style="text-align: left; display: inline-block; width: 20%; white-space: nowrap;">
                <a href="<?= $controller->url_for('calendar/single/year', array('atime' => strtotime('-1 year', $atime))) ?>">
                    <span style="vertical-align: middle;" <?= tooltip(_('ein Jahr zur�ck')) ?>>
                        <?= Assets::img('icons/16/blue/arr_2left.png') ?>
                    </span>
                    <?= strftime('%Y', strtotime('-1 year', $atime)) ?>
                </a>
            </div>
            <div class="calhead" style="text-align: center; display: inline-block; width:50%;">
                <?= date('Y', $calendar->getStart()) ?>
            </div>
            <div style="text-align: right; display: inline-block; width: 20%; white-space: nowrap;">
                <a href="<?= $controller->url_for('calendar/single/year', array('atime' => strtotime('+1 year', $atime))) ?>">
                    <?= strftime('%Y', strtotime('+1 year', $atime)) ?>
                    <span style="vertical-align: middle;" <?= tooltip(_('ein Jahr vor')) ?>>
                        <?= Assets::img('icons/16/blue/arr_2right.png') ?>
                    </span>
                </a>
            </div>
        </td>
    </tr>
    <tr>
        <td colspan="3" class="blank">
            <table width="100%">
            <? $days_per_month = array(31, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
                if (date('L', $calendar->getStart())) {
                    $days_per_month[2]++;
                }
            ?>
                <tr>
            <? for ($i = 1; $i < 13; $i++) : ?>
                    <?  $ts_month += ( $days_per_month[$i] - 1) * 86400; ?>
                    <td align="center" width="8%">
                        <a class="calhead" href="<?= $controller->url_for('calendar/single/month', array('atime' => $calendar->getStart() + $ts_month)) ?>">
                            <b><?= strftime('%B', $ts_month); ?></b>
                        </a>
                    </td>
            <? endfor; ?>
                </tr>
            <? $now = date('Ymd'); ?>
            <? for ($i = 1; $i < 32; $i++) : ?>
                <tr>
                <? for ($month = 1; $month < 13; $month++) : ?>
                    <? $aday = mktime(12, 0, 0, $month, $i, date('Y', $calendar->getStart())); ?>
                    <? $iday = date('Ymd', $aday); ?>
                    <? if ($i <= $days_per_month[$month]) : ?>
                           <? $wday = date('w', $aday);
                            // emphasize current day
                            if (date('Ymd', $aday) == $now) {
                                $day_class = ' class="celltoday"';
                            } else if ($wday == 0 || $wday == 6) {
                                $day_class = ' class="weekend"';
                            } else {
                                $day_class = ' class="weekday"';
                            }
                    ?>
                            <? if ($month == 1) : ?>
                                <td<?= $day_class ?> height="25">
                            <? else : ?>
                                <td<?= $day_class ?>>
                            <? endif; ?>
                            <? if (count($count_list[$iday])) : ?>
                                <table width="100%" cellspacing="0" cellpadding="0">
                                    <tr>
                                        <td<?= $day_class ?>>
                            <? endif; ?>
                            <? $weekday = strftime('%a', $aday); ?>

                            <? $hday = holiday($aday); ?>
                            <? if ($hday['col'] == '1') : ?>
                                <? if (date('w', $aday) == '0') : ?>
                                    <a style="font-weight:bold;" class="sday" href="<?= $controller->url_for('calendar/single/day', array('atime' => $aday)) ?>"><?= $i ?></a> <?= $weekday; ?>
                                    <? $count++; ?>
                                <? else : ?>
                                    <a style="font-weight:bold;" class="day" href="<?= $controller->url_for('calendar/single/day', array('atime' => $aday)) ?>"><?= $i ?></a> <?= $weekday; ?>
                                <? endif; ?>
                            <? elseif ($hday['col'] == '2' || $hday['col'] == '3') : ?>
                                <? if (date('w', $aday) == '0') : ?>
                                    <a style="font-weight:bold;" class="sday" href="<?= $controller->url_for('calendar/single/day', array('atime' => $aday)) ?>"><?= $i ?></a> <?= $weekday; ?>
                                    <? $count++; ?>
                                <? else : ?>
                                    <a style="font-weight:bold;" class="hday" href="<?= $controller->url_for('calendar/single/day', array('atime' => $aday)) ?>"><?= $i ?></a> <?= $weekday; ?>
                                <? endif; ?>
                            <? else : ?>
                                <? if (date('w', $aday) == '0') : ?>
                                    <a style="font-weight:bold;" class="sday" href="<?= $controller->url_for('calendar/single/day', array('atime' => $aday)) ?>"><?= $i ?></a> <?= $weekday; ?>
                                    <? $count++; ?>
                                <? else : ?>
                                    <a style="font-weight:bold;" class="day" href="<?= $controller->url_for('calendar/single/day', array('atime' => $aday)) ?>"><?= $i ?></a> <?= $weekday; ?>
                                <? endif; ?>
                            <? endif; ?>

                            <? if (count($count_list[$iday])) : ?>
                                <? $event_count_txt = sprintf(ngettext('1 Termin', '%s Termine', count($count_list[$iday])), count($count_list[$iday])) ?>
                                </td>
                                <td<?= $day_class ?> align="right">
                                    <?= Assets::img('icons/16/blue/date.png', array('alt' => $event_count_txt, 'title' => $event_count_txt)); ?>
                                </td>
                            </tr>
                        </table>
                            <? endif; ?>
                    </td>
                <? else : ?>
                    <td class="weekday"> </td>
                <? endif; ?>
            <? endfor; ?>
            </tr>
        <? endfor; ?>
            <tr>
            <? $ts_month = 0; ?>
            <? for ($i = 1; $i < 13; $i++) : ?>
                <? $ts_month += ( $days_per_month[$i] - 1) * 86400; ?>
                <td align="center" width="8%">
                    <a class="calhead" href="<?= $controller->url_for('calendar/single/month', array('atime' => $calendar->getStart() + $ts_month)) ?>">
                        <b><?= strftime('%B', $ts_month); ?></b>
                    </a>
                </td>
            <? endfor; ?>
            </tr>
        </table>
        </td>
    </tr>
</table>