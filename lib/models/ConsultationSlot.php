<?php
/**
 * Representation of a consultation slot.
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 * @since   Stud.IP 4.3
 *
 * @property int $id alias column for slot_id
 * @property int $slot_id database column
 * @property int $block_id database column
 * @property int|null $previous_slot_id database column
 * @property int $start_time database column
 * @property int $end_time database column
 * @property string $note database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property SimpleORMapCollection<ConsultationBooking> $bookings has_many ConsultationBooking
 * @property SimpleORMapCollection<ConsultationEvent> $events has_many ConsultationEvent
 * @property ConsultationBlock $block belongs_to ConsultationBlock
 * @property ConsultationSlot|null $previous_slot has_one ConsultationSlot
 * @property ConsultationSlot|null $next_slot has_one ConsultationSlot
 * @property-read mixed $has_bookings additional field
 * @property-read mixed $is_expired additional field
 */
class ConsultationSlot extends SimpleORMap
{
    private const EVENT_PREFIX = 'Stud.IP-Consultation-Event#';

    /**
     * Configures the model.
     * @param array  $config Configuration
     */
    protected static function configure($config = [])
    {
        $config['db_table'] = 'consultation_slots';

        $config['belongs_to']['block'] = [
            'class_name'  => ConsultationBlock::class,
            'foreign_key' => 'block_id',
        ];
        $config['has_many']['bookings'] = [
            'class_name'        => ConsultationBooking::class,
            'assoc_func'        => 'findValidBySlot_id',
            'assoc_foreign_key' => 'slot_id',
            'on_store'          => 'store',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['events'] = [
            'class_name'        => ConsultationEvent::class,
            'assoc_foreign_key' => 'slot_id',
            'on_delete'         => 'delete',
        ];
        $config['has_one']['previous_slot'] = [
            'class_name'  => ConsultationSlot::class,
            'foreign_key' => 'previous_slot_id',
        ];
        $config['has_one']['next_slot'] = [
            'class_name' => ConsultationSlot::class,
            'assoc_func' => 'findOneByPrevious_slot_id',
        ];

        $config['registered_callbacks']['before_create'][] = function (ConsultationSlot $slot) {
            $slot->updateEvents();
        };
        $config['registered_callbacks']['before_store'][] = function (ConsultationSlot $slot) {
            $previous = static::findOneBySQL(
                "block_id = ? AND start_time < ? ORDER BY start_time DESC",
                [$slot->block_id, $slot->start_time]
            );
            $slot->previous_slot_id = $previous?->id;
        };
        $config['registered_callbacks']['after_delete'][] = function ($slot) {
            $block = $slot->block;
            if ($block && count($block->slots) === 0) {
                $block->delete();
            }

            // Close gap
            self::findEachBySQL(
                function (ConsultationSlot $s) use ($slot) {
                    $s->previous_slot_id = $slot->previous_slot_id;
                    $s->store();
                },
                'previous_slot_id = ?',
                [$slot->id]
            );
        };

        $config['additional_fields']['has_bookings']['get'] = function ($slot): bool {
            return count($slot->bookings) > 0;
        };
        $config['additional_fields']['is_expired']['get'] = function ($slot): bool {
            return $slot->end_time < time();
        };

        parent::configure($config);
    }

    /**
     * Counts all slots of the given range.
     *
     * @param  Range $range   Range
     * @param  bool  $expired
     * @return int
     */
    public static function countByRange(Range $range, $expired = false)
    {
        $expired_condition = $expired
                           ? "end <= UNIX_TIMESTAMP()"
                           : "end > UNIX_TIMESTAMP()";

        $condition = "JOIN `consultation_blocks` USING (`block_id`)
                      WHERE `range_id` = :range_id
                        AND `range_type` = :range_type
                        AND {$expired_condition}";
        return self::countBySQL($condition, [
            ':range_id'   => $range->getRangeId(),
            ':range_type' => $range->getRangeType(),
        ]);
    }

    /**
     * Finds slots of the given teacher.
     *
     * @param Range  $range   Range
     * @param string $order   Desired order of items
     * @param bool   $expired Show expired items?
     * @return array
     */
    public static function findByRange(Range $range, $order = '', $expired = false)
    {
        $expired_condition = $expired
                           ? "end <= UNIX_TIMESTAMP()"
                           : "end > UNIX_TIMESTAMP()";

        $condition = "JOIN consultation_blocks USING (block_id)
                      WHERE range_id = :range_id
                        AND range_type = :range_type
                        AND {$expired_condition}
                      {$order}";
        return self::findBySQL($condition, [
            ':range_id'   => $range->getRangeId(),
            ':range_type' => $range->getRangeType(),
        ]);
    }

    /**
     * Find all occupied slots for a given user and teacher combination.
     *
     * @param string $user_id Id of the user
     * @param Range  $range   Range
     * @return array
     */
    public static function findOccupiedSlotsByUserAndRange($user_id, Range $range)
    {
        $condition = "JOIN consultation_blocks USING (block_id)
                      JOIN consultation_bookings USING (slot_id)
                      WHERE user_id = :user_id
                        AND range_id = :range_id
                        AND range_type = :range_type
                        AND end > UNIX_TIMESTAMP()
                      ORDER BY start_time ASC";
        return self::findBySQL($condition, [
            ':user_id'    => $user_id,
            ':range_id'   => $range->getRangeId(),
            ':range_type' => $range->getRangeType(),
        ]);
    }

    /**
     * Returns whether the given event is an event for a consultation slot.
     */
    public static function isSlotEvent(CalendarDate $event): bool
    {
        return str_starts_with($event->unique_id, self::EVENT_PREFIX);
    }

    /**
     * Returns whether this slot is occupied (by a given user).
     */
    public function isOccupied($user_id = null)
    {
        return $user_id === null
             ? count($this->bookings) >= $this->block->size
             : (bool) $this->bookings->findOneBy('user_id', $user_id);
    }

    /**
     * Returns whether the slot is locked for bookings.
     *
     */
    public function isLocked(): bool
    {
        return $this->block->lock_time
            && strtotime("-{$this->block->lock_time} hours", $this->block->start) < time();
    }

    /**
     * Returns whether the slot is bookable for the given user_id
     */
    public function isBookable(): bool
    {
        return !$this->isOccupied()
            && !$this->isLocked()
            && (
                !$this->block->consecutive
                || !$this->block->has_bookings
                || ($this->previous_slot && $this->previous_slot->isOccupied())
                || ($this->next_slot && $this->next_slot->isOccupied())
            );
    }

    /**
     * Creates a Stud.IP calendar event relating to the slot.
     *
     * @param  User $user User object to create the event for
     * @param string $type Create an event for which type (slot or booking)
     * @return CalendarDate Created event
     */
    public function createEvent(User $user, string $type = 'slot') : CalendarDate
    {
        $event = new CalendarDate();
        $event->unique_id = $this->createEventId($user, $type);
        $event->author_id = $user->id;
        $event->editor_id = $user->id;
        $event->begin     = $this->start_time;
        $event->end       = $this->end_time;
        $event->access    = 'PRIVATE';
        $event->location  = $this->block->room;
        $event->repetition_type = 'SINGLE';
        $event->store();

        $calendar_event = new CalendarDateAssignment();
        $calendar_event->range_id         = $user->id;
        $calendar_event->calendar_date_id = $event->id;

        // Suppress mails for users that do not want mails from the consultations
        $calendar_event->suppress_mails = !$user->getConfiguration()->CONSULTATION_SEND_MESSAGES;

        $calendar_event->store();

        return $event;
    }

    /**
     * Returns a unique event id.
     *
     * @return string unique event id
     */
    protected function createEventId(User $user, string $type): string
    {
        return self::EVENT_PREFIX . "{$this->id}:{$user->id}:{$type}";
    }

    /**
     * Updates the teacher event that belongs to the slot. This will either be
     * set to be unoccupied, occupied by only one user or by a group of user.
     */
    public function updateEvents()
    {
        if ($this->isNew()) {
            return;
        }

        // If no range is associated, remove the event
        if (!$this->block->range) {
            $this->events->delete();
            return;
        }

        if (count($this->bookings) === 0 && !$this->block->calendar_events) {
            $this->events->delete();
            return;
        }

        // Get responsible user ids
        $responsible_ids = array_map(
            function (User $user) {
                return $user->id;
            },
            $this->block->responsible_persons
        );

        // Remove events for no longer responsible users
        foreach ($this->events as $event) {
            if (!in_array($event->user_id, $responsible_ids)) {
                $event->delete();
            }
        }

        // Add events for missing responsible users
        $missing = array_diff($responsible_ids, $this->events->pluck('user_id'));
        foreach ($missing as $user_id) {
            $user = User::find($user_id);
            if (!$user) {
                continue;
            }

            $event = $this->createEvent($user);
            ConsultationEvent::create([
                'slot_id'  => $this->id,
                'user_id'  => $user_id,
                'event_id' => $event->id,
            ]);
        }

        // Reset relation in order to account to the above changes
        $this->resetRelation('events');

        $deleted = 0;
        foreach ($this->events as $event) {
            if (!$event->event) {
                $event->delete();

                $deleted += 1;

                continue;
            }

            setTempLanguage($event->user_id);

            $bookings = $this->bookings->filter(function (ConsultationBooking $booking) {
                return !$booking->isDeleted()
                    && $booking->user;
            });

            if (count($bookings) > 0) {
                $event->event->category = 1;

                if (count($bookings) === 1) {
                    $booking = $bookings->first();

                    $event->event->title = sprintf(
                        _('Termin mit %s'),
                        $booking->user ? $booking->user->getFullName() : _('unbekannt')
                    );
                    $event->event->description = $booking->reason;
                } else {
                    $event->event->title = sprintf(
                        _('Termin mit %u Personen'),
                        count($bookings)
                    );
                    $event->event->description = implode("\n\n----\n\n", $bookings->map(function ($booking) {
                        $name = $booking->user ? $booking->user->getFullName() : _('unbekannt');
                        return "- {$name}:\n{$booking->reason}";
                    }));
                }
            } else {
                $event->event->category    = 9;
                $event->event->title       = _('Freier Termin');
                $event->event->description = _('Dieser Termin ist noch nicht belegt.');
            }

            $event->event->store();

            restoreLanguage();
        }

        if ($deleted > 0) {
            $this->initRelation('events');
        }
    }

    /**
     * Returns whether the given user may create a booking for this slot.
     */
    public function userMayCreateBookingForSlot(?\User $user = null): bool
    {
        $user = $user ?? User::findCurrent();

        return ConsultationBooking::userMayCreateBookingForRange($this->block->range, $user)
            && $this->isBookable($user->id);
    }


    /**
     * @return string A string representation of the consultation slot.
     */
    public function __toString() : string
    {
        return sprintf(
            _('Termin am %1$s, %2$s von %3$s bis %4$s'),
            strftime('%A', $this->start_time),
            strftime('%x', $this->start_time),
            date('H:i', $this->start_time),
            date('H:i', $this->end_time)
        );
    }
}
