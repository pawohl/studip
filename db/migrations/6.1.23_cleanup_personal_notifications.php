<?php

final class CleanupPersonalNotifications extends Migration
{
    private ?string $reportText;

    public function description()
    {
        return 'Cleanup personal_notifications avatar values containing XSS <script> tags and writes a cleanup report.';
    }

    protected function up()
    {
        $db = DBManager::get();

        $rows = $db->fetchAll(
            "SELECT personal_notification_id, text, avatar FROM personal_notifications WHERE LOWER(avatar) LIKE '%<script%'",
        );

        if (empty($rows)) {
            return;
        }

        $this->reportText = 'Total infected rows found: '.count($rows);

        foreach ($rows as $row) {
            $id   = $row['personal_notification_id'];
            $text = $row['text'] ?? '(empty)';

            $this->reportText .= "\n row with id: {$id} and text \"{$text}\" has been xss attacked.";

            $db->execute(
                "UPDATE personal_notifications SET `avatar` = NULL WHERE `personal_notification_id` = ?",
                [$id]
            );
        }

        $this->writeCleanupReport();
    }

    private function writeCleanupReport()
    {
        if ($this->reportText === null) {
            return;
        }

        $db = DBManager::get();
        $safeReport = $db->quote($this->reportText);

        $db->exec("
            INSERT INTO `log_actions`
            SET `action_id` = MD5('CleanupReportPersonalNotifications'),
                `name` = 'CleanupReportPersonalNotifications',
                `description` = 'Cleanup report.',
                `info_template` = $safeReport,
                `active` = 1,
                `expires` = 0,
                `type` = 'core',
                `mkdate` = UNIX_TIMESTAMP(),
                `chdate` = UNIX_TIMESTAMP()
            ON DUPLICATE KEY UPDATE
                `info_template` = $safeReport,
                `chdate` = UNIX_TIMESTAMP()
        ");

        $db->exec("
            INSERT INTO `log_events`
            SET `action_id` = MD5('CleanupReportPersonalNotifications'),
                `user_id` = 'studip-migrations',
                `affected_range_id` = '6.1.23',
                `coaffected_range_id` = 'studip',
                `mkdate` = UNIX_TIMESTAMP()
        ");
    }
}
