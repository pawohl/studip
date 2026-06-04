<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version. *
 *
 * @author  André Klaßen (aklassen@uos)
 * @author  Marcus Lunzenauer (mlunzena@uos)
 * @author  Jan-Hendirk Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 * @since   1.7
 */
class Avatar
{
    public const AVATAR_TYPE = 'user';
    protected const CREATE_CHUNKED_FOLDERS = true;

    public const EXTENSION = 'webp';
    public const IMAGE_QUALITY = 90;

    /**
     * This constant stands for the maximal size of a user picture.
     */
    public const ORIGINAL = 'original';

    /**
     * This constant stands for the maximal size of a user picture.
     */
    public const NORMAL = 'normal';

    /**
     * This constant stands for a medium size of a user picture.
     */
    public const MEDIUM = 'medium';

    /**
     * This constant stands for an icon size of a user picture.
     */
    public const SMALL    = 'small';

    /**
     * This constant represents the maximal size of a user picture in bytes.
     */
    public const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 Megabyte

    /**
     * This constant holds the username and ID of the "nobody" avatar.
     */
    protected const NOBODY = 'nobody';

    /**
     * Holds the user's id
     *
     * @var string
     */
    protected $user_id;

    /**
     * Holds the user's username
     *
     * @var string
     */
    protected $username;

    /**
     * Returns an avatar object of the appropriate class.
     *
     * @param string $id the user's id
     * @param string $username the user's username (optional)
     *
     * @return static the user's avatar.
     */
    public static function getAvatar($id)
    {
        $username = null;

        if (func_num_args() === 2) {
            $username = func_get_arg(1);
        }

        return new static($id, $username);
    }

    /**
     * Returns an avatar object for "nobody".
     *
     * @return Avatar the user's avatar.
     */
    public static function getNobody()
    {
        return new static(static::NOBODY, static::NOBODY);
    }

    /**
     * Returns the url to the customized avatars
     *
     * @return string
     */
    public function getAvatarDirectoryUrl()
    {
        return sprintf(
            '%s/%s%s',
            $GLOBALS['DYNAMIC_CONTENT_URL'],
            static::AVATAR_TYPE,
            static::CREATE_CHUNKED_FOLDERS ? '/' . substr($this->user_id, 0, 2) : ''
        );
    }

    /**
     * Returns the path to the customized avatars
     *
     * @return string
     */
    public function getAvatarDirectoryPath()
    {
        return sprintf(
            '%s/%s%s',
            $GLOBALS['DYNAMIC_CONTENT_PATH'],
            static::AVATAR_TYPE,
            static::CREATE_CHUNKED_FOLDERS ? '/' . substr($this->user_id, 0, 2) : ''
        );
    }

    /**
     * Returns the url to the default avatars
     */
    public function getDefaultAvatarDirectoryUrl(): string
    {
        return Assets::url('images/avatars/' . static::AVATAR_TYPE);
    }

    /**
     * Returns the path to the default avatars
     */
    public function getDefaultAvatarDirectoryPath(): string
    {
        return Assets::path('images/avatars/' . static::AVATAR_TYPE);
    }

    /**
     * Returns the url to a customized avatar
     *
     * @return string
     */
    public function getCustomAvatarUrl($size)
    {
        if ($this->isNobody()) {
            return sprintf(
                '%s/%s_%s.%s',
                $this->getDefaultAvatarDirectoryUrl(),
                $this->user_id,
                $size,
                self::EXTENSION
            );
        }

        return sprintf(
            '%s/%s_%s.%s?d=%s',
            $this->getAvatarDirectoryUrl(),
            $this->user_id,
            $size,
            self::EXTENSION,
            @filemtime($this->getCustomAvatarPath($size)) ?: "0"
        );
    }

    /**
     * Returns the path to a customized avatar
     *
     * @return string
     */
    public function getCustomAvatarPath($size)
    {
        if ($this->isNobody()) {
            return sprintf(
                '%s/%s_%s.%s',
                $this->getDefaultAvatarDirectoryPath(),
                $this->user_id,
                $size,
                self::EXTENSION
            );
        }

        return sprintf(
            '%s/%s_%s.%s',
            $this->getAvatarDirectoryPath(),
            $this->user_id,
            $size,
            self::EXTENSION
        );
    }

    /**
     * Constructs a new Avatar object belonging to a user with the given id.
     *
     * @param string $user_id  the user's id
     * @param string $username the user's username (optional)
     */
    final protected function __construct($user_id, $username = null)
    {
        $this->user_id = $user_id;
        $this->username = $username;

        $this->checkAvatarVisibility();
    }

    /**
     * Returns the file name of a user's avatar.
     *
     * @param string $size one of the constants Avatar::(NORMAL|MEDIUM|SMALL)
     *
     * @return string    the absolute file path to the avatar
     */
    public function getFilename($size)
    {
        return $this->is_customized()
            ? $this->getCustomAvatarPath($size)
            : $this->getNobody()->getCustomAvatarPath($size);
    }

    /**
     * Returns the URL of a user's picture.
     *
     * @param string $size one of the constants Avatar::(NORMAL|MEDIUM|SMALL)
     *
     * @return string    the URL to the user's picture
     */
    # TODO (mlunzena) in Url umbenennen
    public function getURL($size)
    {
        return $this->is_customized()
            ? $this->getCustomAvatarUrl($size)
            : $this->getNobody()->getCustomAvatarUrl($size);
    }

    /**
     * Returns whether this avatar is a default/"nobody" avatar.
     */
    public function isNobody(): bool
    {
        return $this->user_id === static::NOBODY;
    }

    /**
     * Returns whether a customized file exists
     */
    public function customizedFileExists(): bool
    {
        return file_exists($this->getCustomAvatarPath(static::MEDIUM));
    }

    /**
     * Returns whether a user has uploaded a custom picture.
     *
     * @return boolean    returns TRUE if the user customized her picture, FALSE
     *                                    otherwise.
     */
    public function is_customized()
    {
        return !$this->isNobody()
            && $this->customizedFileExists();
    }

    /**
     * Returns the CSS class to use for this avatar image.
     *
     * @param string $size one of the constants Avatar::(NORMAL|MEDIUM|SMALL)
     *
     * @return string CSS class to use for the avatar
     */
    protected function getCssClass($size)
    {
        if (!isset($this->username)) {
            $this->username = get_username($this->user_id);
        }

        return sprintf(
            'avatar-%s user-%s' . ($this->is_customized() ? '' : ' recolor'),
            $size,
            htmlReady($this->username)
        );
    }

    /**
     * Constructs a desired HTML image tag for an Avatar. Additional
     * html attributes may also be specified using the $opt parameter.
     *
     * @param string $size one of the constants Avatar::(NORMAL|MEDIUM|SMALL)
     * @param array  $opt  array of attributes to add to the HTML image tag
     *
     * @return string returns the HTML image tag
     */
    public function getImageTag($size = self::MEDIUM, $opt = [])
    {
        $opt['src'] = $this->getURL($size);

        if (isset($opt['class'])) {
            $opt['class'] = $this->getCssClass($size) . ' ' . $opt['class'];
        } else {
            $opt['class'] = $this->getCssClass($size);
        }

        // Apply cast to string for title if necessary
        if (isset($opt['title']) && !is_string($opt['title'])) {
            $opt['title'] = (string) $opt['title'];
        }

        if (!empty($opt['title']) && $opt['title'] !== html_entity_decode($opt['title'])) {
            // Decode already htmlready encoded titles (which were used until
            // all attributes were encoded inside this method)
            $opt['title'] = html_entity_decode($opt['title']);

            if (Studip\ENV === 'development') {
                $trace  = debug_backtrace();
                $caller = array_shift($trace);

                $file = str_replace("{$GLOBALS['STUDIP_BASE_PATH']}/", '', $caller['file']);
                trigger_error(
                    "{$file}:{$caller['line']}: Passes already encoded title to Avatar::getImageTag()",
                    E_USER_DEPRECATED
                );
            }
        }

        if (!isset($opt['alt']) && !isset($opt['title'])) {
            //Add an empty alt attribute to prevent screen readers from
            //reading the URL of the icon:
            $opt['alt'] = '';
        }

        return '<img ' . arrayToHtmlAttributes($opt) . '>';
    }

    /**
     * Creates all the different sized thumbnails for an uploaded file.
     *
     * @param string $userfile the key of the uploaded file, see documentation about $_FILES
     *
     * @return void
     */
    public function createFromUpload($userfile)
    {
        try {
            // keine Datei ausgewählt!
            if (!$_FILES[$userfile]['name']) {
                throw new Exception(_('Sie haben keine Datei zum Hochladen ausgewählt!'));
            }

            // Fehler beim Hochladen
            if ($_FILES[$userfile]['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(_('Es gab einen Fehler beim Hochladen der Datei!'));
            }


            // Bilddatei ist zu groß
            if ($_FILES[$userfile]['size'] > self::MAX_FILE_SIZE) {
                throw new Exception(sprintf(
                    _('Die hochgeladene Bilddatei ist %s KB groß. Die maximale Dateigröße beträgt %s KB!'),
                    round($_FILES[$userfile]['size'] / 1024),
                    self::MAX_FILE_SIZE / 1024)
                );
            }

            // get extension
            $pathinfo = pathinfo($_FILES[$userfile]['name']);
            $ext = mb_strtolower($pathinfo['extension']);

            // passende Endung ?
            if (!in_array($ext, words('jpg jpeg gif png webp'))) {
                throw new Exception(sprintf(
                    _('Der Dateityp der Bilddatei ist falsch (%s). Es sind nur die Dateiendungen .gif, .png, .jpeg, .jpg oder .webp erlaubt!'),
                    $ext
                ));
            }

            // na dann kopieren wir mal...
            $filename = tempnam($GLOBALS['TMP_PATH'], 'avatar-upload');

            if (!@move_uploaded_file($_FILES[$userfile]['tmp_name'], $filename)) {
                throw new Exception(_("Es ist ein Fehler beim Kopieren der Datei aufgetreten. Das Bild wurde nicht hochgeladen!"));
            }

            // set permissions for uploaded file
            @chmod($filename, 0666 & ~umask());

            $this->sanitizeOrientation($filename);
            $this->createFrom($filename);
        } finally {
            if (isset($filename)) {
                @unlink($filename);
            }
        }
    }

    /**
     * Creates thumbnails from an image.
     *
     * @param string $filename filename of the image to create thumbnails from
     *
     * @return void
     */
    public function createFrom($filename)
    {
        if (!extension_loaded('gd')) {
            throw new Exception(_('Es ist ein Fehler beim Bearbeiten des Bildes aufgetreten.') . ' (' . _('Fehlende GD-Lib') . ')');
        }

        set_error_handler([__CLASS__, 'error_handler']);

        NotificationCenter::postNotification('AvatarWillCreate', $this->user_id);
        $this->resize(static::NORMAL, $filename);
        $this->resize(static::MEDIUM, $filename);
        $this->resize(static::SMALL,  $filename);
        NotificationCenter::postNotification('AvatarDidCreate', $this->user_id);

        restore_error_handler();
    }

    /**
     * Removes all uploaded pictures of a user.
     */
    public function reset()
    {
        if ($this->is_customized()) {
            NotificationCenter::postNotification('AvatarWillDelete', $this->user_id);
            @unlink($this->getCustomAvatarPath(static::NORMAL));
            @unlink($this->getCustomAvatarPath(static::SMALL));
            @unlink($this->getCustomAvatarPath(static::MEDIUM));
            NotificationCenter::postNotification('AvatarDidDelete', $this->user_id);
        }
    }

    /**
     * Return the dimension of a size
     *
     * @param string $size the dimension of a size
     * @return array{0: int, 1: int} a tupel of integers [width, height]
     */
    public static function getDimension($size)
    {
        $dimensions = [
            static::NORMAL => [250, 250],
            static::MEDIUM => [100, 100],
            static::SMALL  => [25, 25]
        ];
        return $dimensions[$size];
    }

    /**
     * Create from an image thumbnails of a specified size.
     *
     * @param string $size     the size of the thumbnail to create
     * @param string $filename the filename of the image to make thumbnail of
     */
    private function resize(string $size, string $filename)
    {
        [$thumb_width, $thumb_height] = static::getDimension($size);

        $thumb_width = $thumb_width * 2;
        $thumb_height = $thumb_height * 2;

        [$width, $height, $type] = getimagesize($filename);

        # create image resource from filename
        $lookup = [
            IMAGETYPE_GIF  => 'imagecreatefromgif',
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG  => 'imagecreatefrompng',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
        ];
        if (!isset($lookup[$type])) {
            throw new Exception(_("Der Typ des Bilds wird nicht unterstützt."));
        }
        $image = $lookup[$type]($filename);

        imagealphablending($image, false);
        imagesavealpha($image, true);

        # resize image if needed
        if ($height > $thumb_height || $width > $thumb_width) {
            $factor = max($thumb_width / $width, $thumb_height / $height);
            $resized_width  = round($width * $factor);
            $resized_height = round($height * $factor);
        } else {
            $resized_width  = $width;
            $resized_height = $height;
        }

        $image = self::imageresize($image, $width, $height, $resized_width, $resized_height);

        $dst = imagecreatetruecolor($thumb_width, $thumb_height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        $trans_colour = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $trans_colour);

        // center the new image
        $ypos = intval($thumb_height - $resized_height) >> 1;
        $xpos = intval($thumb_width - $resized_width) >> 1;

        imagecopy(
            $dst, $image,
            $xpos, $ypos,
            0, 0,
            $resized_width, $resized_height
        );

        $output_file = $this->getCustomAvatarPath($size);
        $directory = dirname($output_file);
        if (!is_dir($directory) && !@mkdir($directory)) {
            throw new Exception(_('Das Verzeichnis zum Speichern der Datei konnte nicht angelegt werden.'));
        }

        imagewebp($dst, $output_file, self::IMAGE_QUALITY);
    }

    private function imageresize($image, $current_width, $current_height, $width, $height)
    {
        $image_resized = imagecreatetruecolor($width, $height);

        imagealphablending($image_resized, false);
        imagesavealpha($image_resized, true);
        imagecopyresampled(
            $image_resized, $image,
            0, 0,
            0, 0,
            $width, $height,
            $current_width, $current_height
        );

        return $image_resized;
    }

    public static function error_handler($errno, $errstr, $errfile, $errline)
    {
        if (defined('E_RECOVERABLE_ERROR')
            && $errno == constant('E_RECOVERABLE_ERROR'))
        {
            $message = sprintf(
                'Recoverable error "%s" occured in file %s line %u.',
                $errstr,
                $errfile,
                $errline
            );
            throw new Exception($message);
        }

        # execute PHP internal error handler
        return false;
    }

    /**
     * Return the default title of the avatar.
     * @return string the default title
     */
    public function getDefaultTitle()
    {
        if ($this->isNobody()) {
            return static::NOBODY;
        }

        require_once 'lib/functions.php';
        return get_fullname($this->user_id);
    }

    /**
     * Return if avatar is visible to the current user.
     * Also set the user_id of avatar to nobody if not visible to current user.
     * @return boolean: true if visible
     */
    protected function checkAvatarVisibility()
    {
        $visible = Visibility::verify('picture', $this->user_id);
        if (!$visible) {
            $this->user_id = self::NOBODY;
        }
        return $visible;
    }

    /**
     * Corrects the orientation of images from iOS/OS X devices which might
     * lead to a rotated image. EXIF information is checked and when the
     * orientation is set by EXIF data, we rotate the image accordingly.
     *
     * @param string $filename Filename of the image to correct
     */
    protected function sanitizeOrientation($filename)
    {
        if (!function_exists('exif_read_data')) {
            return;
        }

        if (exif_imagetype($filename) !== IMAGETYPE_JPEG) {
            return;
        }

        $exif = exif_read_data($filename);
        if (!$exif || !$exif['Orientation'] || $exif['Orientation'] == 1) {
            return;
        }

        $degree = 0;
        switch ($exif['Orientation']) {
            case 3:
                $degree = 180;
                break;
            case 6:
                $degree = -90;
                break;
            case 8:
                $degree = 90;
                break;
        }

        if ($degree) {
            $img = imagecreatefromstring(file_get_contents($filename));
            $img = imagerotate($img, $degree, 0);

            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if ($extension === 'jpg' || $extension === 'jpeg') {
                imagejpeg($img, $filename, 95);
            } elseif ($extension === 'gif') {
                imagegif($img, $filename);
            } elseif ($extension === 'png') {
                imagepng($img, $filename, 9);
            } else {
                imagewebp($img, $filename, self::IMAGE_QUALITY);
            }

            imagedestroy($img);
        }
    }

    public function getId()
    {
        return $this->user_id;
    }

    public static function getAvatarDropdownHTML(User $user, bool $with_name = false): string
    {
        $attributes = [
            ':user' => json_encode([
                'id'         => $user->id,
                'name'       => $user->getFullName(),
                'username'   => $user->username,
                'avatar_url' => self::getAvatar($user->id)->getURL(self::MEDIUM)
            ])
        ];

        $user_profile_link = '';
        if ($with_name) {
            $user_profile_link = '<a href="' . URLHelper::getLink('dispatch.php/profile', ['username' => $user->username]) . '">' . htmlReady($user->getFullName()) . '</a>';
        }

        return '
                <div class="user-avatar-container">
                    <div class="use-vue-components">
                        <user-avatar-dropdown ' . HTMLAttributes::from($attributes) . '/>
                    </div>
                    '. $user_profile_link .'
                </div>
        ';
    }
}
