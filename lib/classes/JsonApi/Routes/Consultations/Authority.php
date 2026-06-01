<?php

namespace JsonApi\Routes\Consultations;

final class Authority
{
    public static function canShowRange(\User $user, \Range $range): bool
    {
        return $range->isAccessibleToUser($user->id);
    }

    public static function canEditRange(\User $user, \Range $range): bool
    {
        return $range->isEditableByUser($user->id);
    }

    public static function canShowBlock(\User $user, \ConsultationBlock $block): bool
    {
        return self::canShowRange($user, $block->range);
    }

    public static function canEditBlock(\User $user, \ConsultationBlock $block): bool
    {
        return self::canEditRange($user, $block->range);
    }

    public static function canShowSlot(\User $user, \ConsultationSlot $slot): bool
    {
        return self::canShowBlock($user, $slot->block);
    }

    public static function canEditSlot(\User $user, \ConsultationSlot $slot): bool
    {
        return self::canEditBlock($user, $slot->block);
    }

    public static function canBookSlot(\User $user, \ConsultationSlot $slot): bool
    {
        return \ConsultationBooking::userMayCreateBookingForRange(
            $slot->block->range,
            $user
        );
    }

    public static function canBookSlotForUser(\User $user, \ConsultationSlot $slot, \User $booking_user): bool
    {
        if ($user->id !== $booking_user->id && !self::canEditSlot($user, $slot)) {
            return false;
        }

        return self::canBookSlot($booking_user, $slot);
    }

    public static function canShowBooking(\User $user, \ConsultationBooking $booking): bool
    {
        return self::canShowSlot($user, $booking->slot)
            || $booking->user_id === $user->id;
    }

    public static function canEditBooking(\User $user, \ConsultationBooking $booking): bool
    {
        return self::canEditSlot($user, $booking->slot)
            || $booking->user_id === $user->id;
    }
}
