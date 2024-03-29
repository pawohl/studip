<div>
    <h4><?= htmlReady($event->getTitle()) ?></h4>
    <div>
        <b><?= _('Beginn') ?>:</b> <?= strftime('%c', $event->getStart()) ?>
    </div>
    <div>
        <b><?= _('Ende') ?>:</b> <?= strftime('%c', $event->getEnd()) ?>
    </div>
    <? if ($event->havePermission(Event::PERMISSION_READABLE)) : ?>
        <? if ($event instanceof CourseEvent) : ?>
        <div>
            <b><?= _('Veranstaltung') ?>:</b>
            <? if ($GLOBALS['perm']->have_studip_perm('user', $event->range_id)) : ?>
            <a href="<?= URLHelper::getLink('dispatch.php/course/details/?cid=' . $event->range_id) ?>">
            <? else : ?>
            <a href="<?= URLHelper::getLink('seminar_main.php?auswahl=' . $event->range_id) ?>">
            <? endif; ?>
                <?= htmlReady($event->course->getFullname()) ?>
            </a>
        </div>
        <? endif;?>
        <? if ($text = $event->getDescription()) : ?>
            <div>
                <b><?= _('Beschreibung') ?>:</b> <?= htmlReady(mila($text, 50)) ?>
            </div>
        <? endif; ?>
        <? if ($text = $event->toStringCategories()) : ?>
            <div>
                <b><?= _('Kategorie') ?>:</b> <?= htmlReady(mila($text, 50)) ?>
            </div>
        <? endif; ?>
        <? if ($text = $event->getLocation()) : ?>
            <div>
                <b><?= _('Raum/Ort') ?>:</b> <?= htmlReady(mila($text, 50)) ?>
            </div>
        <? endif; ?>
        <? if ($text = $event->toStringPriority()) : ?>
            <div>
                <b><?= _('Prioritšt') ?>:</b> <?= htmlReady(mila($text, 50)) ?>
            </div>
        <? endif; ?>
        <? if ($text = $event->toStringAccessibility()) : ?>
            <div>
                <b><?= _('Zugriff') ?>:</b> <?= htmlReady(mila($text, 50)) ?>
            </div>
        <? endif; ?>
    <? endif; ?>
    <? if ($text = $event->toStringRecurrence()) : ?>
        <div>
            <b><?= _('Wiederholung') ?>:</b> <?= htmlReady($text) ?>
        </div>
    <? endif; ?>
    <? if ($event->havePermission(Event::PERMISSION_READABLE)) : ?>
        <? if ($event instanceof CalendarEvent) : ?>
            <? if (get_config('CALENDAR_GROUP_ENABLE')) : ?>
                <? $attendees = $event->getAttendees() ?>
                 <? $count_attendees = count(array_filter($attendees,
                    function ($att) use ($calendar) {
                        return ($att->user->user_id != $calendar->getRangeId());
                    })) ?>
                <? if ($count_attendees) : ?>
                <div>
                    <b><?= _('Teilnehmer:') ?></b>
                    <?= implode(', ', array_map(function ($att) {
                        $profil_link = '<a href="';
                        $profil_link .= URLHelper::getLink('dispatch.php/profile',
                                array('username' => $att->user-username));
                        $profil_link .= '">' . htmlReady($att->user->getFullname()) . '</a>';
                        return $profil_link;
                    }, $attendees)); ?>
                </div>
                <? endif; ?>
                <div>
                    <? $author = $event->getAuthor() ?>
                    <? if ($author) : ?>
                        <?= sprintf(_('Eingetragen am: %s von %s'),
                        strftime('%x, %X', $event->mkdate),
                            htmlReady($author->getFullName('no_title'))) ?>
                    <? endif; ?>
                </div>
                <? if ($event->event->mkdate < $event->event->chdate) : ?>
                    <? $editor = $event->getEditor() ?>
                    <? if ($editor) : ?>
                    <div>
                        <?= sprintf(_('Zuletzt bearbeitet am: %s von %s'),
                            strftime('%x, %X', $event->chdate),
                                htmlReady($editor->getFullName('no_title'))) ?>
                    </div>
                    <? endif; ?>
                <? endif; ?>
            <? endif; ?>
        <? else : ?>
            <? // related groups ?>
            <? $related_groups = $event->getRelatedGroups(); ?>
            <? if (sizeof($related_groups)) : ?>
            <div>
                <b><?= _('Betroffene Gruppen') ?>:</b>
                <?= htmlReady(implode(', ', $related_groups->pluck('name'))) ?>
            </div>
            <? endif; ?>
        <? endif; ?>
    <? endif; ?>
</div>