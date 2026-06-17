<?php
/**
 * Representation of a block of consultation slots - defining metadata.
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 * @since   Stud.IP 4.3
 *
 * @todo Rewrite countBlocks and generateBlocks to use the same underlying
 *       method when the dev board finally fully supports PHP7 since that
 *       required "yield from".
 *
 * @property int $id alias column for block_id
 * @property int $block_id database column
 * @property string $range_id database column
 * @property string $range_type database column
 * @property int $start database column
 * @property int $end database column
 * @property string $room database column
 * @property int $calendar_events database column
 * @property int $show_participants database column
 * @property string $require_reason database column
 * @property bool $mail_to_tutors database column
 * @property string|null $confirmation_text database column
 * @property string $note database column
 * @property int $size database column
 * @property int|null $lock_time database column
 * @property bool $consecutive database column
 * @property int $mkdate database column
 * @property int $chdate database column
 * @property SimpleORMapCollection<ConsultationSlot> $slots has_many ConsultationSlot
 * @property SimpleORMapCollection<ConsultationResponsibility> $responsibilities has_many ConsultationResponsibility
 * @property mixed $range additional field
 * @property-read mixed $range_display additional field
 * @property-read mixed $has_bookings additional field
 * @property-read mixed $is_expired additional field
 * @property-read mixed $responsible_persons additional field
 */
class ConsultationBlock extends SimpleORMap implements PrivacyObject
{
    /**
     * Configures the model.
     * @param array  $config Configuration
     */
    protected static function configure($config = [])
    {
        $config['db_table'] = 'consultation_blocks';

        $config['has_many']['slots'] = [
            'class_name'        => ConsultationSlot::class,
            'assoc_foreign_key' => 'block_id',
            'on_store'          => 'store',
            'on_delete'         => 'delete',
        ];
        $config['has_many']['responsibilities'] = [
            'class_name'        => ConsultationResponsibility::class,
            'assoc_foreign_key' => 'block_id',
            'on_delete'         => 'delete',
            'order_by'          => "ORDER BY range_type = 'user' DESC, range_type = 'statusgroup' DESC",
        ];

        $config['additional_fields']['range'] = [
            'set' => function ($block, $field, Range $range) {
                $block->range_id   = $range->getRangeId();
                $block->range_type = $range->getRangeType();
            },
            'get' => function ($block) {
                return RangeFactory::createRange($block->range_type, $block->range_id);
            },
        ];
        $config['additional_fields']['range_display']['get'] = function ($block) {
            if ($block->range instanceof User) {
                return $block->range->getFullName() . ' <' . $block->range->email . '>';
            }
            if ($block->range instanceof Course) {
                return sprintf(_('Veranstaltung: %s'), $block->range->getFullName());
            }

            if ($block->range instanceof Institute) {
                return sprintf(_('Einrichtung: %s'), $block->range->getFullName());
            }

            throw new Exception('Not implemented yet');
        };

        $config['additional_fields']['has_bookings']['get'] = function ($block) {
            return ConsultationBooking::countBySql(
                "JOIN consultation_slots USING(slot_id) WHERE block_id = ?",
                [$block->id]
            ) > 0;
        };
        $config['additional_fields']['is_expired']['get'] = function ($block) {
            return $block->slots->every(function ($slot) {
                return $slot->is_expired;
            });
        };

        $config['additional_fields']['responsible_persons']['get'] = function (ConsultationBlock $block) {
            $persons = [];
            if (count($block->responsibilities) > 0) {
                foreach (array_merge(...$block->responsibilities->getUsers()) as $user) {
                    $persons[$user->id] = $user;
                }
            } elseif ($block->range instanceof User) {
                $persons[] = $block->range;
            } elseif ($block->range instanceof Course) {
                $persons = ConsultationResponsibility::getCourseResponsibilities($block->range);
            } elseif (!($block->range instanceof Institute)) {
                throw new Exception('Unknown range type');
            }

            // Ensure we only return objects of type User
            $persons = array_filter($persons, function ($person) {
                return $person instanceof User;
            });
            return array_values($persons);
        };

        $config['registered_callbacks']['after_store'][] = function (ConsultationBlock $block): void {
            $block->updateEvents();
        };

        parent::configure($config);
    }

    /**
     * Count generated blocks according to the given data.
     *
     * @param  int $start              Start of the time range as unix timestamp
     * @param  int $end                End of the time range as unix timestamp
     * @param int $week_day            Day of the week the blocks should be
     *                                 created (0 = sunday, 1 = monday ...)
     * @param int $interval            Week interval (skip $interval weeks
     *                                 between blocks)
     * @param int $duration            Duration of a slot in minutes
     * @param int|null $pause_time     Create a pause after $pause_time minutes
     * @param int|null $pause_duration Duration of the pause
     */
    public static function countSlots($start, $end, $week_day, $interval, $duration, $pause_time = null, $pause_duration = null)
    {
        $count = 0;
        foreach (self::generateBlocks(new User(), $start, $end, $week_day, $interval, true) as $block) {
            $count += count($block->createSlots($duration, $pause_time, $pause_duration));
        }
        return $count;
    }

    /**
     * Generate blocks according to the given data.
     *
     * Be aware, that this is an actual generator that yields the results. You
     * cannot count the generated blocks without iterating over them.
     *
     * @throws OverlapException
     * @param  Range  $range      Range
     * @param  int    $start      Start of the time range as unix timestamp
     * @param  int    $end        End of the time range as unix timestamp
     * @param  int    $week_day   Day of the week the blocks should be created
     *                            (0 = sunday, 1 = monday ...)
     * @param  int    $interval   Week interval (skip $interval weeks between
     *                            blocks)
     */
    public static function generateBlocks(Range $range, $start, $end, $week_day, $interval, bool $fail_silent = false)
    {
        $start_time = date('H:i', $start);
        $end_time   = date('H:i', $end);

        // Adjust current date to match week of day
        $current = $start;
        while (date('w', $current) != $week_day) {
            $current = strtotime('+1 day', $current);
        }

        $warnings = [];

        while ($current <= $end) {
            $temp    = holiday($current);
            $holiday = is_array($temp) && $temp['col'] === 3;

            if (!$holiday) {
                $overlaps = self::checkOverlaps($range, $start, $end);
                if (!$overlaps) {
                    $block = new self();
                    $block->range_id   = $range->getRangeId();
                    $block->range_type = $range->getRangeType();
                    $block->start      = strtotime("today {$start_time}", $current);
                    $block->end        = strtotime("today {$end_time}", $current);

                    yield $block;
                } elseif (!$fail_silent) {
                    $details = [];
                    foreach ($overlaps as $overlap) {
                        $details[] = sprintf(
                            _('%s bis %s von %s bis %s Uhr'),
                            strftime('%x', $overlap->start),
                            strftime('%x', $overlap->end),
                            date('H:i', $overlap->start),
                            date('H:i', $overlap->end)
                        );
                    }

                    throw new OverlapException(
                        _('Die Zeiten überschneiden sich mit anderen bereits definierten Terminen'),
                        $details
                    );
                }
            } elseif (!$fail_silent) {
                $warnings[] = sprintf(
                    _('Am %1$s können keine Termine erzeugt werden, da es ein Feiertag (%2$s) ist.'),
                    strftime('%x', $current),
                    $temp['name']
                );
            }

            if ($interval <= 0) {
                break;
            }

            $current = strtotime("+{$interval} weeks", $current);
        }

        $warnings = array_unique($warnings);
        foreach ($warnings as $warning) {
            PageLayout::postInfo(htmlReady($warning));
        }
    }

    /**
     * Checks if there any consultation slots already exist in the given
     * time range for the given user.
     *
     * @param Range $range Id of the range
     * @param int   $start Start of the time range as unix timestamp
     * @param int   $end   End of the time range as unix timestamp
     * @return array of overlapping consultation slots
     */
    protected static function checkOverlaps(Range $range, $start, $end)
    {
        $query = "SELECT DISTINCT `block_id`
                  FROM `consultation_slots`
                  JOIN `consultation_blocks` USING (`block_id`)
                  WHERE `range_id` = :range_id
                    AND `range_type` = :range_type
                    AND `start_time` <= :start
                    AND `end_time` >= :end";
        $statement = DBManager::get()->prepare($query);
        $statement->bindValue(':range_id', $range->getRangeId());
        $statement->bindValue(':range_type', $range->getRangeType());
        $statement->bindValue(':start', $start);
        $statement->bindValue(':end', $end);
        $statement->execute();
        $ids = $statement->fetchAll(PDO::FETCH_COLUMN);

        return self::findMany($ids);
    }

    /**
     * Creates individual slots according to the defined data and given
     * duration.
     *
     * @param int      $duration       Duration of a slot in minutes
     * @param int|null $pause_time     Create a pause after $pause_time minutes
     * @param int|null $pause_duration Duration of the pause
     * @return ConsultationSlot[]
     */
    public function createSlots($duration, ?int $pause_time = null, ?int $pause_duration = null): array
    {
        $slots = [];
        $accumulated_durations = 0;

        $now = $this->start;
        while ($now < $this->end) {
            $accumulated_durations += $duration;

            if ($pause_time && $accumulated_durations > $pause_time) {
                $accumulated_durations = 0;
                $now = strtotime("+{$pause_duration} minutes", $now);
                continue;
            }

            $slots[] = ConsultationSlot::build([
                'block_id'      => $this->id,
                'start_time'    => $now,
                'end_time'      => strtotime("+{$duration} minutes", $now),
            ]);

            $now = strtotime("+{$duration} minutes", $now);
        }

        return $slots;
    }

    /**
     * Returns whether this slot is visible for a user.
     *
     * @param  mixed $user_id Id of the user (optional, defaults to current user)
     * @return boolean defining whether the slot is visible
     */
    public function isVisibleForUser($user_id = null)
    {
        return $this->range->isAccessibleToUser();
    }

    /**
     * Returns a list of responsible ranges for this block.
     *
     * @return array<string, array<Range>>
     */
    public function getPossibleResponsibilites(): array
    {
        if ($this->range instanceof User) {
            return [
                'users' => [$this->range]
            ];
        }

        if ($this->range instanceof Course) {
            return [
                'users' => $this->range->getMembersWithStatus('tutor dozent', true)->pluck('user'),
            ];
        }

        if ($this->range instanceof Institute) {
            $users = $this->range->members->filter(function ($member) {
                return in_array($member->inst_perms, ['tutor', 'dozent']);
            })->pluck('user');

            $groups     = $this->range->status_groups;
            $institutes = $this->range->sub_institutes;

            return compact('users', 'groups', 'institutes');
        }

        throw new UnexpectedValueException('Not implemented yet');
    }

    /**
     * Export available data of a given user into a storage object
     * (an instance of the StoredUserData class) for that user.
     *
     * @param StoredUserData $storage object to store data into
     */
    public static function exportUserData(StoredUserData $storage)
    {
        $blocks = self::findByRange($storage->user_id, 'user');
        if ($blocks) {
            $storage->addTabularData(
                _('Terminblöcke'),
                'consultation_blocks',
                array_map(function ($block) {
                    return $block->toRawArray();
                }, $blocks)
            );

            $slots = [];
            foreach ($blocks as $block) {
                foreach ($block->slots as $slot) {
                    $slots[] = $slot->toRawArray();
                }
            }

            if ($slots) {
                $storage->addTabularData(_('Terminvergabe'), 'consultation_slots', $slots);
            }
        }
    }

    /**
     * Finds all blocks of a range. Specialized version of the sorm method
     * that excludes expired blocks by default and may be used to explicitely
     * select expired blocks.
     *
     * @param Range  $range    Range
     * @param string $order    Optional order
     * @param boolean $expired Select expired blocks
     * @return static[]
     */
    public static function findByRange(Range $range, $order = '', $expired = false)
    {
        if ($expired) {
            return parent::findBySQL(
                "range_id = ? AND range_type = ? AND end <= UNIX_TIMESTAMP() {$order}",
                [$range->getRangeId(), $range->getRangeType()]
            );
        }

        return parent::findBySQL(
            "range_id = ? AND range_type = ? AND end > UNIX_TIMESTAMP() {$order}",
            [$range->getRangeId(), $range->getRangeType()]
        );
    }

    /**
     * Count all blocks of a range. Specialized version of the sorm method
     * that excludes expired blocks by default and may be used to explicitely
     * select expired blocks.
     *
     * @param Range   $range   Range
     * @param boolean $expired Select expired blocks
     * @return number
     */
    public static function countByRange(Range $range, $expired = false)
    {
        if ($expired) {
            return parent::countBySQL(
                "range_id = ? AND range_type = ? AND end <= UNIX_TIMESTAMP()",
                [$range->getRangeId(), $range->getRangeType()]
            );
        }

        return parent::countBySQL(
            "range_id = ? AND range_type = ? AND end > UNIX_TIMESTAMP()",
            [$range->getRangeId(), $range->getRangeType()]
        );
    }


    /**
     * Checks if a given time span (defined by $begin and $end) is inside a
     * defined pause of a block.
     *
     * @param int $begin
     * @param int $end
     * @param int $block_begin
     * @param int $block_end
     * @param int $pause_time
     * @param int $pause_duration
     *
     * @return bool
     */
    private static function checkIfSlotIsInPause($begin, $end, $block_begin, $block_end, $pause_time, $pause_duration): bool
    {
        $now = $block_begin;
        while ($now < $block_end) {
            $pause_begin = strtotime("+{$pause_time} minutes", $now);
            $pause_end   = strtotime("+{$pause_duration} minutes", $pause_begin);

            if ($begin < $pause_end && $end > $pause_begin) {
                return true;
            }

            $now = $pause_end;
        }

        return false;
    }

    /**
     * @return string A string representation of the consultation block instance.
     */
    public function __toString() : string
    {
        return sprintf(
            _('Terminblock am %1$s, %2$s von %3$s bis %4$s Uhr'),
            strftime('%A', $this->start),
            strftime('%x', $this->start),
            date('H:i', $this->start),
            date('H:i', $this->end)
        );
    }

    /**
     * Convenience method that updates all events for all slots of this block.
     */
    public function updateEvents(): void
    {
        $this->slots->each(function (ConsultationSlot $slot): void {
            $slot->updateEvents();
        });
    }
}
