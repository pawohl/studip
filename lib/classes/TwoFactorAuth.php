<?php
/**
 * Class handling the two factor authentication
 *
 * @author  Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license GPL2 or any later version
 * @since   Stud.IP 4.4
 *
 * @see TFASecret model
 */
final class TwoFactorAuth
{
    const SESSION_KEY           = 'tfa/confirmed';
    const SESSION_DATA          = 'tfa/data';
    const SESSION_CONFIRMATIONS = 'tfa/confirmations';
    const SESSION_FAILED        = 'tfa/failed';
    const SESSION_TOKEN_SENT    = 'tfa/token-sent';

    const COOKIE_KEY = 'tfa/authentication';

    private static $instance = null;

    /**
     * Returns an instance of the authentication
     * @return TwoFactorAuth object
     */
    public static function get(): TwoFactorAuth
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Returns whether the two factor authentication is enabled for the given
     * user (defaults to current user). The user's permissions decide whether
     * the two factor authentication is enabled or not.
     *
     * @param  User|null $user User to check (optional, defaults to current user)
     * @return boolean
     */
    public static function isEnabledForUser(?User $user = null): bool
    {
        if ($user === null) {
            $user = User::findCurrent();
        }

        $valid_perms = array_filter(array_map('trim', explode(',', Config::get()->getValue('TFA_PERMS'))));
        return in_array($user->perms, $valid_perms);
    }

    public static function removeCookie()
    {
        // Remove cookie
        setcookie(
            self::COOKIE_KEY . '/' . $GLOBALS['user']->id,
            '',
            strtotime('-1 year'),
            $GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP']
        );
    }

    private $secret = null;

    /**
     * Private constructor to enforce singleton
     *
     * @throws SessionRequiredException
     */
    private function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new SessionRequiredException('2FA requires a valid session');
        }

        if (!isset($_SESSION[self::SESSION_FAILED])) {
            $_SESSION[self::SESSION_FAILED] = [];
        } else {
            // Remove failed items after 5 minutes
            $_SESSION[self::SESSION_FAILED] = array_filter(
                $_SESSION[self::SESSION_FAILED],
                function ($timestamp) {
                    return $timestamp > time() - Config::get()->getValue('TFA_MAX_TRIES_TIMESPAN');
                }
            );
        }

        $user = User::findCurrent();
        if (!$user) {
            return;
        }

        if (!self::isEnabledForUser($user)) {
            return;
        }

        if (!TFASecret::exists($user->id)) {
            return;
        }

        $this->secret = TFASecret::find($user->id);
    }

    /**
     * Secures the current session, if applicable.
     *
     * This method checks the following:
     * - is 2fa enabled for the current user
     * - is the request an ajax call
     * - does the user have a secret, meaning 2fa is enabled
     * - is the secret already confirmed
     * - has the session already been confirmed (identified by a valid random
     *   token stored in the session)
     * - is the computer trusted (identified by a valid random token stored in
     *   a cookie)
     *
     * If the user has 2fa enabled, it's secret is confirmed and the session has
     * not been secured yet, a validation screen with a prompt to enter a valid
     * token is presented to the user.
     */
    public function secureSession()
    {
        // Not enabled for user's perm?
        if (!self::isEnabledForUser()) {
            return;
        }

        if (match_route('dispatch.php/logout')) {
            return;
        }

        // Not enabled?
        if (!$this->secret) {
            return;
        }

        $this->validateFromRequest();

        // Not confirmed?
        if (!$this->secret->confirmed) {
            return;
        }

        // User has already confirmed this session?
        if (isset($_SESSION[self::SESSION_KEY])) {
            [$code, $timeslice] = array_values($_SESSION[self::SESSION_KEY]);
            if ($this->secret->validateToken($code, (int) $timeslice, true)) {
                return;
            }
            unset($_SESSION[self::SESSION_KEY]);
        }

        // Trusted computer?
        $user_cookie_key = self::COOKIE_KEY . '/' . $GLOBALS['user']->id;
        if (isset($_COOKIE[$user_cookie_key])) {
            [$code, $timeslice] = explode(':', $_COOKIE[$user_cookie_key]);
            if ($this->secret->validateToken($code, (int) $timeslice, true)) {
                $this->registerSecretInSession();
                return;
            }

            self::removeCookie();
        }

        if (Request::isXhr()) {
            throw new Studip\TFARequiredException();
        }

        $this->showConfirmationScreen('', [
            'global' => true,
        ]);
    }

    /**
     * Requests a 2fa token input to confirm a specific action.
     *
     * @param  string $action Name of the action to confirm
     * @param  string $text   Text to display to the user
     * @param  array  $data   Optional additional data to pass to the
     *                        confirmation screen (for internal use)
     */
    public function confirm(string $action, string $text, array $data = []): void
    {
        if (
            isset($_SESSION[self::SESSION_CONFIRMATIONS])
            && is_array($_SESSION[self::SESSION_CONFIRMATIONS])
            && in_array($action, $_SESSION[self::SESSION_CONFIRMATIONS])
        ) {
            $_SESSION[self::SESSION_CONFIRMATIONS] = array_diff(
                $_SESSION[self::SESSION_CONFIRMATIONS],
                [$action]
            );
        } else {
            $this->showConfirmationScreen($text, $data + [
                'confirm' => $action,
            ]);
        }
    }

    /**
     * Displays the token input screen to the user. This will be the last
     * action since it dies after display.
     *
     * @param  string $text Text to display to the user
     * @param  array  $data Optional additional data (for internal use)
     */
    private function showConfirmationScreen(string $text = '', array $data = [])
    {
        PageLayout::setTitle(_('Zwei-Faktor-Authentifizierung'));

        $data = array_merge(['global' => false], $data);

        $_SESSION[self::SESSION_DATA] = array_merge($data, [
            '__nonce'  => md5(uniqid('tfa-nonce', true)),
            '__params' => Request::getInstance()->getIterator()->getArrayCopy(),
        ]);

        if ($this->secret->type === 'email') {
            if (
                !isset($_SESSION[self::SESSION_TOKEN_SENT])
                || $_SESSION[self::SESSION_TOKEN_SENT] < time()
                || Request::submitted('resend-token')
            ) {
                StudipMail::sendMessage(
                    $this->secret->user->email,
                    _('Ihr Zwei-Faktor-Token'),
                    sprintf(
                        _('Bitte geben Sie dieses Token ein: %s'),
                        $this->secret->getToken()
                    )
                );

                $_SESSION[self::SESSION_TOKEN_SENT] = time() + TFASecret::getValidationDuration('email');
            }
        }
        PageLayout::setBodyElementId('tfa-confirmation-screen');

        echo $GLOBALS['template_factory']->render(
            'tfa-validate.php',
            $_SESSION[self::SESSION_DATA] + [
                'secret'   => $this->secret,
                'text'     => $text,
                'waittime' => $this->getWaitingTime(),
                'duration' => Config::get()->getValue('TFA_TRUST_DURATION'),
            ],
            'layouts/base.php'
        );
        sess()->save();
        die;
    }

    /**
     * Registers the current secret in session by storing a valid random token
     * along with the according timeslice.
     */
    private function registerSecretInSession()
    {
        $timeslice = mt_rand(0, PHP_INT_MAX);
        $_SESSION[self::SESSION_KEY] = [
            'code'      => $this->secret->getToken($timeslice),
            'timeslice' => $timeslice,
        ];
    }

    /**
     * Registers the current secret in a cookie by storing a valid random token
     * along with the according timeslice.
     */
    private function registerSecretInCookie()
    {
        $lifetime_in_days = Config::get()->getValue('TFA_TRUST_DURATION');
        $lifetime = $lifetime_in_days > 0 ? strtotime("+{$lifetime_in_days} days") : 2147483647;

        $timeslice = mt_rand(0, PHP_INT_MAX);
        setcookie(
            self::COOKIE_KEY . '/' . $GLOBALS['user']->id,
            implode(':', [$this->secret->getToken($timeslice), $timeslice]),
            $lifetime,
            $GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP']
        );
    }

    /**
     * Detects and validates a submitted tfa token input. This will stop the
     * current request if token is present and invalid and will return to the
     * request as expected when either token is present and valid or no token
     * was submitted at all.
     *
     * This method also registers the secret in session (if global in data is
     * set to true) or registers the secret in a cookie (if request parameter
     * "tfa-trusted" was sent).
     */
    private function validateFromRequest()
    {
        if (
            $this->getWaitingTime()
            || !Request::isPost()
            || !Request::submitted('tfacode-input')
            || !Request::submitted('tfa-nonce')
            || !isset($_SESSION[self::SESSION_DATA])
            || !is_array($_SESSION[self::SESSION_DATA])
            || Request::submitted('resend-token')
        ) {
            return;
        }

        $data = $_SESSION[self::SESSION_DATA];
        if (Request::option('tfa-nonce') === $data['__nonce']) {
            $token = implode('', Request::intArray('tfacode-input'));

            if ($this->secret->validateToken($token)) {
                $_SESSION[self::SESSION_FAILED] = [];
                unset($_SESSION[self::SESSION_TOKEN_SENT]);

                if ($data['global'] ?? false) {
                    $this->registerSecretInSession();

                    if (Request::int('tfa-trusted')) {
                        $this->registerSecretInCookie();
                    }
                }

                if ($data['confirm'] ?? false) {
                    if (!isset($_SESSION[self::SESSION_CONFIRMATIONS])) {
                        $_SESSION[self::SESSION_CONFIRMATIONS] = [];
                    }
                    $_SESSION[self::SESSION_CONFIRMATIONS][] = $data['confirm'];
                }

                // Remove tfa parameters from request
                Request::set('tfa-nonce', null);
                Request::set('tfacode-input', null);
                Request::set('tfa-trusted', null);

                // Add previous parameters to request
                foreach ($data['__params'] as $key => $value) {
                    Request::set($key, $value);
                }
            } else {
                $_SESSION[self::SESSION_FAILED][] = time();

                PageLayout::postError(_('Das eingegebene Token ist nicht gültig.'));
            }

            unset($_SESSION[self::SESSION_DATA]);
        }
    }

    /**
     * Returns the time the user has to wait before entering a token.
     *
     * This happens if too many invalid tokens have been input.
     */
    private function getWaitingTime(): int
    {
        if (count($_SESSION[self::SESSION_FAILED]) < Config::get()->getValue('TFA_MAX_TRIES')) {
            return 0;
        }

        $min_timestamp = min(array_slice($_SESSION[self::SESSION_FAILED], -Config::get()->getValue('TFA_MAX_TRIES')));

        $wait_time = $min_timestamp + Config::get()->getValue('TFA_MAX_TRIES_TIMESPAN') - time();

        return ceil($wait_time / 60);
    }
}
