<?php

/**
 * @see: https://gitlab.studip.de/studip/studip/-/work_items/7115
 * Weekly calendar dates without a weekday (calendar_dates.days is NULL or empty)
 * make CalendarDate::getRepetitionAsString() fail with a TypeError
 * (getWeekday(): Argument #1 ($day_num) must be of type int, string given).
 *
 * This migration sets the weekday of the beginning of the date as the
 * repetition weekday (1 = Monday ... 7 = Sunday).
 */
final class FixWeeklyCalendarDates extends Migration
{
    public function description(): string
    {
        return 'Sets the weekday of weekly calendar dates that have no weekday stored';
    }

    public function up(): void
    {
        $query = "UPDATE `calendar_dates`
                  SET `days` = WEEKDAY(FROM_UNIXTIME(`begin`)) + 1
                  WHERE `repetition_type` = 'WEEKLY'
                    AND (`days` IS NULL OR `days` = '')";
        $count = DBManager::get()->exec($query);

        $this->write("Repaired {$count} weekly calendar date(s) without a weekday");
    }

    public function down(): void
    {
        //The original (defective) state cannot be restored.
    }
}
