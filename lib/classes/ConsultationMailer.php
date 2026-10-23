<?php
/**
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 */
class ConsultationMailer
{
    /**
     * Sends a consultation information message.
     *
     * @param User|null           $sender  Sender
     * @param User                $user    Recipient
     * @param ConsultationBooking $booking    Booking in question
     * @param string              $subject Subject of the message
     * @param string|null         $reason  Reason for a booking or cancelation
     */
    public static function sendMessage(?User $sender, User $user, ConsultationBooking $booking, string $subject, ?string $reason = '')
    {
        // Don't send message if user doesn't want it
        if (
            $booking->user_id !== $user->id
            && $booking->slot->block->shouldSuppressMailsForUser($user)
        ) {
            return;
        }

        setTempLanguage($user->id);

        $message = $GLOBALS['template_factory']->open('consultations/mail.php')->render([
            'user'   => $booking->user,
            'slot'   => $booking->slot,
            'reason' => $reason ?: _('Kein Grund angegeben'),
        ]);

        if ($sender === null) {
            messaging::sendSystemMessage($user, $subject, $message);
        } else {
            $messaging = new messaging();
            $messaging->insert_message($message, $user->username, $sender->id, '', '', '', '', $subject);
        }

        restoreLanguage();
    }

    /**
     * Send a booking information message to the teacher of the booked slot.
     *
     * @param User|null            $sender
     * @param ConsultationBooking $booking The booking
     */
    public static function sendBookingMessageToResponsibilities(?User $sender, ConsultationBooking $booking)
    {
        foreach (self::getResponsiblePersonsOfBlock($booking->slot->block) as $user) {
            self::sendMessage(
                $sender,
                $user,
                $booking,
                sprintf(_('Termin von %s zugesagt'), $booking->user->getFullName()), $booking->reason
            );
        }
    }

    /**
     * Send a booking information message to the user of the booked slot.
     *
     * @param User|null            $sender
     * @param  ConsultationBooking $booking The booking
     */
    public static function sendBookingMessageToUser(?User $sender, ConsultationBooking $booking)
    {
        self::sendMessage(
            $sender,
            $booking->user,
            $booking,
            sprintf(_('Termin bei %s zugesagt'), $booking->slot->block->range_display), $booking->reason
        );
    }

    /**
     * Send an information message about a changed reason to a user of the
     * booked slot.
     *
     * @param User|null           $sender   The sender of the message
     * @param ConsultationBooking $booking  The booking
     * @param User                $receiver The receiver of the message
     */
    public static function sendReasonMessage(?User $sender, ConsultationBooking $booking, User $receiver)
    {
        self::sendMessage(
            $sender,
            $receiver,
            $booking,
            sprintf(_('Grund des Termins bei %s bearbeitet'), $booking->slot->block->range_display), $booking->reason
        );
    }

    /**
     * Send a cancelation message to the teacher of the booked slot.
     *
     * @param User|null            $sender
     * @param  ConsultationBooking $booking The booking
     * @param String               $reason  Reason of the cancelation
     */
    public static function sendCancelMessageToResponsibilities(?User $sender, ConsultationBooking $booking, string $reason = '')
    {
        foreach (self::getResponsiblePersonsOfBlock($booking->slot->block) as $user) {
            self::sendMessage(
                $sender,
                $user,
                $booking,
                sprintf(_('Termin von %s abgesagt'), $booking->user->getFullName()), trim($reason)
            );
        }
    }

    /**
     * Send a cancelation message to the user of the booked slot.
     *
     * @param User|null            $sender
     * @param  ConsultationBooking $booking The booking
     * @param String               $reason  Reason of the cancelation
     */
    public static function sendCancelMessageToUser(?User $sender, ConsultationBooking $booking, string $reason)
    {
        self::sendMessage(
            $sender,
            $booking->user,
            $booking,
            sprintf(_('Termin bei %s abgesagt'), $booking->slot->block->range_display), trim($reason)
        );
    }

    /**
     * @return Generator<User>
     */
    private static function getResponsiblePersonsOfBlock(ConsultationBlock $block): Generator
    {
        foreach ($block->responsible_persons as $user) {
            /** @var User $user */

            // No mail to self
            if ($user->id === User::findCurrent()->id) {
                continue;
            }

            // No mails to tutors
            if (
                $block->range_type === 'course'
                && !$block->mail_to_tutors
                && !$GLOBALS['perm']->have_studip_perm('dozent', $block->range_id, $user->id)
            ) {
                continue;
            }

            yield $user;
        }
    }
}
