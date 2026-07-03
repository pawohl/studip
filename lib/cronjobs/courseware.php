<?php
/**
 * courseware.php
 *
 * @author Thomas Hackl <hackl@data-quest.de>
 * @access public
 * @since  5.3
 */

class CoursewareCronjob extends CronJob
{
    public static function getName()
    {
        return _('Courseware-Erinnerungen und -zertifikate verschicken sowie Fortschritt zurücksetzen');
    }

    public static function getDescription()
    {
        return _('Versendet Erinnerungen, Zertifikate bei Erreichen eines bestimmten Fortschritts und setzt ' .
            'Fortschritt bei derartig konfigurierten Coursewares zurück.');
    }

    public static function getParameters()
    {
        return [
            'verbose' => [
                'type'        => 'boolean',
                'default'     => false,
                'status'      => 'optional',
                'description' => _('Sollen Ausgaben erzeugt werden (sind später im Log des Cronjobs sichtbar)'),
            ]
        ];
    }

    public function setUp()
    {
    }

    public function execute($last_result, $parameters = [])
    {
        $verbose = $parameters['verbose'];

        $todo = [];
        if (Config::get()->COURSEWARE_CERTIFICATES_ENABLE) {
            /*
             * Fetch all units that have some relevant settings.
             */
            $todo = Courseware\Unit::findBySQL(
                "`range_type` = 'course' AND (`config` LIKE (:cert) OR `config` LIKE (:reminder) OR `config` LIKE (:reset))",
                ['cert' => '%"certificate":%', 'reminder' => '%"reminder":%', 'reset' => '%"reset_progress":%']
            );
        }

        if (count($todo) > 0) {

            if ($verbose) {
                printf("Found %u units to process.\n", count($todo));
            }

            $timezone = Config::get()->DEFAULT_TIMEZONE;

            // Process all found entries...
            foreach ($todo as $unit) {

                // Fetch all courseware block IDs belonging to the current unit.
                $instance = new Courseware\Instance($unit->structural_element);
                $blocks = array_column($instance->findAllBlocks(), 'id');

                // Send certificates to those who have progressed far enough and have not yet gotten one.
                if (isset($unit->config['certificate'])) {
                    if ($verbose) {
                        printf("Generating certificates for course %s, unit %u.\n",
                            $unit->range_id, $unit->id);
                    }

                    // Fetch accumulated progress values for all users in this course.
                    $progresses = DBManager::get()->fetchAll(
                        "SELECT DISTINCT p.`user_id`, SUM(p.`grade`) AS progress, MAX(p.`chdate`) AS pdate
                        FROM `cw_user_progresses` p
                        WHERE `block_id` IN (:blocks)
                            AND NOT EXISTS (
                                SELECT `id` FROM `cw_certificates` WHERE `user_id` = p.`user_id` AND `unit_id` = :unit
                            )
                        GROUP BY `user_id`",
                        ['blocks' => $blocks, 'unit' => $unit->id]
                    );

                    // Calculate percentual progress and send certificates if necessary.
                    foreach ($progresses as $progress) {
                        $percent = ($progress['progress'] / count($blocks)) * 100;
                        printf("User %s has progress %u.\n", $progress['user_id'], $percent);
                        if ($percent >= $unit->config['certificate']['threshold']) {
                            if ($verbose) {
                                printf("User %s will get a certificate for course %s and unit %u.\n",
                                    $progress['user_id'], $unit->range_id, $unit->id);
                            }

                            if (!$this->sendCertificate($unit, $progress['user_id'], $percent, $progress['pdate'],
                                    $unit->config['certificate']['image'])) {
                                printf("Could not send certificate for course %s and unit %u to user %s.\n",
                                    $unit->range_id, $unit->id, $progress['user_id']);
                            }
                        }
                    }
                }

                // Send reminder messages to participants if necessary.
                if (isset($unit->config['reminder'])) {
                    // Check when the last reminder was sent...
                    $now = new DateTime('', new DateTimeZone($timezone));

                    // What would be the minimum date for the last reminder?
                    $minReminder = clone $now;

                    // The last reminder has been sent at?
                    $lastReminder = new DateTime('', new DateTimeZone($timezone));
                    $lastReminder->setTimestamp($unit->config['last_reminder'] ?? 0);

                    // Check if the settings specify a start and/or end date for reminders
                    $start = new DateTime($unit->config['reminder']['startDate'] ?? '1970-01-01',
                        new DateTimeZone($timezone));
                    $end = new DateTime($unit->config['reminder']['endDate'] ?? '2199-12-31',
                        new DateTimeZone($timezone));

                    $interval = new DateInterval('P1D');
                    switch ($unit->config['reminder']['interval']) {
                        case 7:
                            $interval = new DateInterval('P7D');
                            break;
                        case 14:
                            $interval = new DateInterval('P14D');
                            break;
                        case 30:
                            $interval = new DateInterval('P1M');
                            break;
                        case 90:
                            $interval = new DateInterval('P3M');
                            break;
                        case 180:
                            $interval = new DateInterval('P6M');
                            break;
                        case 365:
                            $interval = new DateInterval('P1Y');
                            break;
                    }
                    $minReminder->sub($interval);

                    // ... and send a new one if necessary.
                    if ($lastReminder <= $minReminder && $now >= $start && $now <= $end) {
                        if ($verbose) {
                            printf("Sending reminders for course %s and unit %u.\n",
                                $unit->range_id, $unit->id);
                        }

                        if ($this->sendReminders($unit)) {
                            $unit->config['last_reminder'] = time();
                        }
                    }
                }

                // Reset progress if necessary.
                if (isset($unit->config['reset_progress'])) {
                    // Check when the last rest took place...
                    $now = new DateTime('', new DateTimeZone($timezone));

                    // What would be the minimum date for the last reset?
                    $minReset = clone $now;

                    // The last reset was done at:
                    $lastReset = new DateTime('', new DateTimeZone($timezone));
                    $lastReset->setTimestamp($unit->config['last_progress_reset'] ?? 0);

                    // Check if the settings specify a start and/or end date for reminders
                    $start = new DateTime($unit->config['reset_progress']['startDate'] ?? '1970-01-01',
                        new DateTimeZone($timezone));
                    $end = new DateTime($unit->config['reset_progress']['endDate'] ?? '2199-12-31',
                        new DateTimeZone($timezone));

                    $interval = new DateInterval('P1D');
                    switch ($unit->config['reset_progress']['interval']) {
                        case 7:
                            $interval = new DateInterval('P7D');
                            break;
                        case 14:
                            $interval = new DateInterval('P14D');
                            break;
                        case 30:
                            $interval = new DateInterval('P1M');
                            break;
                        case 90:
                            $interval = new DateInterval('P3M');
                            break;
                        case 180:
                            $interval = new DateInterval('P6M');
                            break;
                        case 365:
                            $interval = new DateInterval('P1Y');
                            break;
                    }
                    $minReset->sub($interval);

                    // ... and send a new one if necessary.
                    if ($lastReset <= $minReset && $now >= $start && $now <= $end) {
                        if ($verbose) {
                            printf("Resetting progress for course %s and unit %u.\n",
                                $unit->range_id, $unit->id);
                        }

                        if ($this->resetProgress($unit, $blocks)) {
                            $unit->config['last_progress_reset'] = time();
                        }
                    }
                }

                // Store config back, saving timestamps for reminders and progress reset.
                $unit->store();
            }

        } else if ($verbose) {
            echo "Nothing to do.\n";
        }
    }

    /**
     * @param Courseware\Unit $unit
     * @param string $user_id
     * @param int $progress
     * @param int $timestamp
     * @param string|null $image
     * @return bool|int|number
     * @throws Exception
     */
    private function sendCertificate(Courseware\Unit $unit, string $user_id, int $progress, int $timestamp, ?string $image = null)
    {
        $user = User::find($user_id);
        $course = Course::find($unit->range_id);

        $pdf = Courseware\Certificate::createPDF($unit, $timestamp, $user, $image);

        $folder = $this->requireCertificateFolder($unit);
        $data = [
            'name'=> $user->getFullName('full') . '-' . date('ymd') . '.pdf',
            'tmp_name'=> $pdf,
            'type' => 'application/pdf',
            'size' => @filesize($pdf)
        ];
        $file = $folder->addFile(StandardFile::create($data), $user->id);
        @unlink($pdf);

        setTempLanguage('', $user->preferred_language);

        // Send the message containing a link to the PDF certificate.
        $subject = _('Courseware: Zertifikat') . ' - ' . $course->getFullName() .
            ' (' . $unit->structural_element->title . ')';
        $message = sprintf(
            _('Sie haben einen Fortschritt von %1$u % % im Lernmaterial "%2$s" erreicht und können daher Ihr ' .
                '[Zertifikat herunterladen]%3$s .'),
            $progress, $unit->structural_element->title, $file->getDownloadURL());
        $message .= "\n\n" . sprintf(_('Sie können das Lernmaterial [direkt hier aufrufen]%s .'),
                URLHelper::getURL('dispatch.php/course/courseware/courseware/' . $unit->id, ['cid' => $course->id]));
        ;

        messaging::sendSystemMessage($user, $subject, $message);

        restoreLanguage();

        // Add database entry for the certificate.
        $cert = new Courseware\Certificate();
        $cert->user_id = $user_id;
        $cert->course_id = $course->id;
        $cert->unit_id = $unit->id;
        $cert->fileref_id = $file->id;
        return $cert->store();
    }

    private function sendReminders($unit)
    {
        $course = Course::find($unit->range_id);

        $recipients = $course->getMembersWithStatus('autor', true);

        $mail = new StudipMail();

        foreach ($recipients as $rec) {
            $mail->addRecipient(
                $rec->email,
                $rec->getUserFullname(),
                'bcc'
            );
        }

        $message = $unit->config['reminder']['mailText'] . "\n\n" .
            sprintf(_('Sie können das Lernmaterial [direkt hier aufrufen]%s .'),
                URLHelper::getURL('dispatch.php/course/courseware/courseware/' . $unit->id, ['cid' => $course->id]));
        ;

        $mail->setSubject(_('Courseware: Erinnerung') . ' - ' . $course->getFullName() .
                ', ' . $unit->structural_element->title)
            ->setBodyText($message);

        return $mail->send();
    }

    private function resetProgress($unit, $block_ids)
    {
        $course = Course::find($unit->range_id);

        DBManager::get()->execute(
            "DELETE FROM `cw_user_progresses` WHERE `block_id` IN (:blocks)",
            ['blocks' => $block_ids]
        );

        /*
         * If certificates are active, remove all existing entries for this unit.
         * Note that existing PDF files will stay in their course folders.
         */
        Courseware\Certificate::deleteByUnit_id($unit->id);

        $recipients = $course->getMembersWithStatus('autor');

        foreach (array_chunk($recipients, 100) as $chunkedRecipients) {
            $mail = new StudipMail();

            foreach ($chunkedRecipients as $rec) {
                $mail->addRecipient(
                    $rec->email,
                    $rec->getUserFullname(),
                    'bcc'
                );
            }

            $message = $unit->config['reset_progress']['mailText'] . "\n\n" .
                sprintf(_('Sie können das Lernmaterial [direkt hier aufrufen]%s .'),
                    URLHelper::getURL('dispatch.php/course/courseware/courseware/' . $unit->id, ['cid' => $course->id]));

            $mail->setSubject(_('Courseware: Fortschritt zurückgesetzt') . ' - ' . $course->getFullName())
                ->setBodyText($message);

            if (!$mail->send()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create or fetch the folder where certificates shall be put in this course.
     * @param Courseware\Unit $unit
     * @return FolderType
     */
    private function requireCertificateFolder(Courseware\Unit $unit)
    {
        // Try to find existing unit folder in database.
        $unitFolder = Folder::findOneBySQL(
            "`range_id` = :range AND `data_content` = :unit",
            ['range' => $unit->range_id, 'unit' => json_encode(['unit_id' => $unit->id, 'download_allowed' => 1])]
        );

        // We need to create a new folder for this unit.
        if (!$unitFolder) {
            // Try to find existing certificate folder in database.
            $certFolder = Folder::findOneBySQL(
                "`range_id` = :range AND `data_content` = :data",
                [
                    'range' => $unit->range_id,
                    'data' => json_encode(['purpose' => 'cw-certificates', 'download_allowed' => 1])
                ]
            );

            // Create parent folder, collecting all certificates for all units of this course.
            if (!$certFolder) {
                $certFolder = FileManager::createSubFolder(
                    Folder::findTopFolder($unit->range_id)->getTypedFolder(),
                    User::findCurrent(),
                    'HiddenFolder',
                    _('Courseware-Zertifikate'),
                    _('Erteilte Zertifikate für den Fortschritt in Courseware-Inhalten dieser Veranstaltung ')
                );
                $certFolder->data_content = json_encode(['purpose' => 'cw-certificates', 'download_allowed' => 1]);
                $certFolder->store();
            }

            // General folder for certificates exists now, create the subfolder for this unit.
            $unitFolder = FileManager::createSubFolder(
                is_a($certFolder, FolderType::class) ? $certFolder : $certFolder->getTypedFolder(),
                User::findCurrent(),
                'HiddenFolder',
                $unit->structural_element->title,
                sprintf(_('Zertifikate für Lernmaterial %u'), $unit->id)
            );
            $unitFolder->data_content = json_encode(['unit_id' => $unit->id, 'download_allowed' => 1]);
            $unitFolder->store();
        }

        return is_a($unitFolder, FolderType::class) ? $unitFolder : $unitFolder->getTypedFolder();
    }

}
