<?php
/**
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 * @since   Stud.IP 5.1
 *
 * @property array $id alias for pk
 * @property int $slot_id database column
 * @property string $user_id database column
 * @property string $event_id database column
 * @property int $mkdate database column
 * @property ConsultationSlot $slot belongs_to ConsultationSlot
 * @property CalendarDate $event has_one CalendarDate
 */
class ConsultationEvent extends SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'consultation_events';

        $config['belongs_to']['slot'] = [
            'class_name'  => ConsultationSlot::class,
            'foreign_key' => 'slot_id',
        ];
        $config['has_one']['event'] = [
            'class_name'        => CalendarDate::class,
            'foreign_key'       => 'event_id',
            'assoc_foreign_key' => 'id',
            'on_delete'         => 'delete',
        ];

        $config['registered_callbacks'] = [
            'before_delete' => [
                function (ConsultationEvent $event) {
                    if (!isset($event->event->calendars)) {
                        return;
                    }

                    // Suppress all mails from calendar for users that do not want to receive emails about
                    // consultation bookings or for calendar dates that lie in the past.
                    $event->event->calendars->each(function (CalendarDateAssignment $assignment) use ($event) {
                        if (
                            empty($assignment->calendar_date->end)
                            || $assignment->calendar_date->end < time()
                            || (
                                $assignment->user
                                && $event->slot->block->shouldSuppressMailsForUser($assignment->user)
                            )
                        ) {
                            $assignment->suppress_mails = true;
                        }
                        $assignment->delete();
                    });
                },
            ],
        ];

        parent::configure($config);
    }
}
