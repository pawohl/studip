<?php
/**
 * @var ResourceBooking $booking
 * @var bool $user_has_user_perms
 * @var bool $user_may_see_course_data
 * @var bool $make_comment_editable
 * @var bool $show_internal_comment
 * @var bool $hide_buttons
 * @var User $current_user
 * @var Resources_BookingController $controller
 */
?>
<? if (!empty($booking)): ?>
    <? if (!empty($user_has_user_perms)): ?>
        <p>
            <?= sprintf(
                _('Letzte Änderung am %s'),
                date('d.m.Y', $booking->chdate)
            ) ?>
        </p>
    <? endif ?>
    <h3><?= _('Gebuchte Ressource') ?></h3>
    <? $derived_resource = $booking->resource
                         ? $booking->resource->getDerivedClassInstance()
                         : null ?>
    <?= $derived_resource
      ? htmlReady($derived_resource->getFullName())
      : _('unbekannt') ?>

    <h3><?= _('Zeiträume') ?></h3>
    <?
        $cycle_date = $booking->assigned_course_date->cycle ?? null;
        $booking_has_metadate = $cycle_date instanceof SeminarCycleDate;
        $intervals = $booking->getTimeIntervals()
    ?>
    <? if (count($intervals) === 1) : ?>
        <div>
            <?= $intervals[0] ?>
        </div>
        <? if ($booking_has_metadate) :?>
        <div><?= _('Diese Buchung ist Teil der folgenden Terminserie:') ?></div>
        <?= htmlReady($cycle_date->toString('full')) ?>
        <? endif ?>
    <? elseif (!empty($intervals)): ?>
        <ul>
            <? foreach ($intervals as $interval): ?>
                <li>
                    <?= $interval ?>
                </li>
            <? endforeach ?>
        </ul>
    <? else: ?>
        <?= _('Nicht verfügbar') ?>
    <? endif ?>

    <? if ($booking->booking_type === ResourceBooking::TYPE_LOCK): ?>
        <? if (!empty($user_has_user_perms)): ?>
            <h3><?= _('Gesperrt für:') ?></h3>
            <?= htmlReady($booking->getAssignedUserName()) ?>
        <? endif ?>
        <h3><?= _('Sperrung vorgenommen von:') ?></h3>
        <?= $booking->booking_user
          ? htmlReady($booking->booking_user->getFullName())
          : _('unbekannt') ?>
    <? elseif ($booking->booking_type == ResourceBooking::TYPE_RESERVATION): ?>
        <? if (!empty($user_has_user_perms)): ?>
            <h3><?= _('Reserviert für:') ?></h3>
            <?= htmlReady($booking->getAssignedUserName()) ?>
        <? endif ?>
        <h3><?= _('Reservierung vorgenommen von:') ?></h3>
        <?= $booking->booking_user
          ? htmlReady($booking->booking_user->getFullName())
          : _('unbekannt') ?>
    <? else: ?>
        <? if (!empty($user_has_user_perms)): ?>
            <h3><?= _('Gebucht von:') ?></h3>
            <? if ($booking->booking_user) :?>
                <a href="<?= URLHelper::getScriptLink(
                         'dispatch.php/profile',
                         ['username' => $booking->booking_user->username]
                         ) ?>">
                <?= htmlReady($booking->booking_user->getFullName()) ?>
                </a>
                <a href="<?= URLHelper::getScriptLink(
                         'dispatch.php/messages/write',
                         ['rec_uname' => $booking->booking_user->username]
                         ) ?>" data-dialog="size=auto">
                <?= Icon::create('mail')->asImg(['class' => 'text-bottom']) ?>
                </a>
            <? else :?>
                <?= _('unbekannt') ?>
            <? endif ?>
        <? endif ?>
    <? endif ?>
    <? if (!empty($user_may_see_course_data)): ?>
        <h3><?= _('Gebucht für:') ?></h3>
        <a href="<?= URLHelper::getLink('dispatch.php/course/details/index/' . $booking->getAssignedUser()->id) ?>" target="_blank">
            <?= htmlReady($booking->getAssignedUserName(), true, true) ?>
            <?= Icon::create(
                'link-intern',
                Icon::ROLE_CLICKABLE,
                [
                    'title' => _('Veranstaltungsdetails anzeigen'),
                    'class' => 'text-bottom'
                ]
            ) ?>
        </a>
        <? if (Seminar_Perm::get()->have_studip_perm('dozent', $booking->getAssignedUser()->id)): ?>
            <div>
                <a href="<?= URLHelper::getLink('dispatch.php/course/timesrooms', [ 'cid' => $booking->getAssignedUser()->id]) ?>">
                    <?=_('Verwaltung von Zeiten und Räumen')?>
                    <?= Icon::create(
                        'schedule',
                        Icon::ROLE_CLICKABLE,
                        [
                            'title' => _('Verwaltung von Zeiten und Räumen'),
                            'class' => 'text-bottom'
                        ]) ?>
                </a>
            </div>
        <? endif ?>
    <? elseif ($booking->getAssignedUserType() === 'user') : ?>
        <? if (($booking->assigned_user->visible === 'yes') ||
               ($booking->assigned_user->id === $GLOBALS['user']->id) || !empty($user_has_user_perms)) : ?>
            <h3><?= _('Gebucht für:') ?></h3>
            <a href="<?= URLHelper::getScriptLink(
                     'dispatch.php/profile',
                     ['username' =>$booking->assigned_user->username]
                     ) ?>">
                <?= htmlReady($booking->assigned_user->getFullName()) ?>
            </a>
            <a href="<?= URLHelper::getScriptLink(
                     'dispatch.php/messages/write',
                     ['rec_uname' => $booking->assigned_user->username]
                     ) ?>" data-dialog="size=auto">
                <?= Icon::create('mail')->asImg(['class' => 'text-bottom']) ?>
            </a>
        <? endif ?>
    <? endif ?>
    <? if ($booking->description): ?>
        <h3><?= _('Buchungstext') ?></h3>
        <?= htmlReady($booking->description) ?>
    <? endif ?>
    <? if (!empty($make_comment_editable)): ?>
        <form class="default" method="post"
              action="<?= htmlReady(
                      $controller->link_for('resources/booking/index/' . $booking->id)
                      ) ?>" data-dialog="reload-on-close">
            <?= CSRFProtection::tokenTag() ?>
    <? endif ?>
    <? if (!empty($show_internal_comment)): ?>
        <h3><?= _('Interner Kommentar zur Buchung') ?>:</h3>
        <? if (!empty($make_comment_editable)): ?>
            <textarea name="internal_comment"><?= htmlReady($booking->internal_comment) ?></textarea>
        <? else: ?>
            <?= htmlReady($booking->internal_comment) ?>
        <? endif ?>
    <? endif ?>
    <? if ((Request::isDialog() || !empty($make_comment_editable)) && empty($hide_buttons)): ?>
        <div data-dialog-button>
            <? if (isset($make_comment_editable)): ?>
                <?= \Studip\Button::create(_('Speichern'), 'save') ?>
                <? if (!$booking->isReadOnlyForUser($current_user)): ?>
                    <?= \Studip\LinkButton::create(
                        _('Bearbeiten'),
                        $controller->url_for('resources/booking/edit/' . $booking->id),
                        [
                            'data-dialog' => '1'
                        ]
                    ) ?>
                    <?= \Studip\LinkButton::create(
                        _('Löschen'),
                        $controller->url_for('resources/booking/delete/' . $booking->id),
                        [
                            'data-dialog' => '1'
                        ]
                    ) ?>
                <? endif ?>
            <? endif ?>
        </div>
    <? endif ?>
    <? if (!empty($make_comment_editable)): ?>
        </form>
    <? endif ?>
<? endif ?>
