<?php
/**
 * login.php - login
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */
class LoginController extends AuthenticatedController
{
    protected $allow_nobody = true;

    public function __construct(\Trails\Dispatcher $dispatcher)
    {
        Config::get()->USER_VISIBILITY_CHECK = false;
        Request::set('cid', null);
        parent::__construct($dispatcher);
    }

    public function index_action(): void
    {
        PageLayout::setTitle(_('Login'));

        if (User::findCurrent()) {
            $this->redirect(URLHelper::getURL('dispatch.php/start'));
            return;
        }

        if (Request::isXhr()) {
            if (Request::isDialog()) {
                $this->relocate(URLHelper::getURL($_SERVER['REQUEST_URI']));
                return;
            }
            throw new AccessDeniedException();
        }

        if (Request::submitted('user_config_submitted')) {
            CSRFProtection::verifyUnsafeRequest();
            if (Request::submitted('unset_contrast')) {
                $_SESSION['contrast'] = 0;
                $this->redirect('login/index'); //we're too late to remove the high contrast mode, so we reload the page
                return;
            }
            if (Request::submitted('set_contrast')) {
                $_SESSION['contrast'] = 1;
            }


            foreach (array_keys($GLOBALS['INSTALLED_LANGUAGES']) as $language_key) {
                if (Request::get('set_language') === $language_key) {
                    $_SESSION['forced_language'] = $language_key;
                    $_SESSION['_language'] = $language_key;
                    init_i18n($_SESSION['_language']);
                }
            }
            if (!empty($_SESSION['contrast'])) {
                \PageLayout::addStylesheet('accessibility.css');
            }

        }

        if (Request::submitted('Login')) {
            CSRFProtection::verifyUnsafeRequest();

            $check_auth = StudipAuthAbstract::CheckAuthentication(
                Request::get('loginname'),
                Request::get('password')
            );

            if ($check_auth['uid']) {
                $uid = $check_auth['uid'];
                if (
                    isset($check_auth['need_email_activation'])
                    && $check_auth['need_email_activation'] === $uid
                ) {
                    $this->need_email_activation = $uid;
                    $_SESSION['semi_logged_in'] = $uid;
                    $this->redirect('login/activate_email', ['uid' => $uid]);
                    return;
                } else {
                    auth()->setAuthenticatedUser(
                        $check_auth['user'],
                        ['auth_plugin' => 'standard']
                    );
                    Metrics::increment('core.login.succeeded');
                    sess()->regenerateId(\Studip\Authentication\Manager::DEFAULT_KEPT_SESSION_VARIABLES);
                    $this->redirect('start/index');
                    return;
                }
            } else {
                Metrics::increment('core.login.failed');
                $this->error_msg = $check_auth['error'];
            }
        }

        $this->has_login_error = false;
        if ($this->error_msg) {
            PageLayout::postException(_('Bei der Anmeldung trat ein Fehler auf!'), $this->error_msg);
            $this->has_login_error = true;
        }
        $this->uname = $this->auth['uname'] ?? Request::username('loginname');
        $this->self_registration_activated = Config::get()->getValue('ENABLE_SELF_REGISTRATION');

        $this->news_entries = [];
        if (Config::get()->getValue('LOGIN_NEWS_VISIBILITY')) {
            $news_entries = StudipNews::GetNewsByRange('login', true, true);
            $this->news_entries = array_values($news_entries);
        }

        $this->faq_entries = [];
        if (Config::get()->getValue('LOGIN_FAQ_VISIBILITY')) {
            $this->faq_entries = LoginFaq::findBySQL("1 ORDER BY `faq_id`");
        }

        PageLayout::setHelpKeyword('Basis.AnmeldungLogin');
        PageLayout::disableSidebar();
        PageLayout::setBodyElementId('login');
    }

    public function activate_email_action(): void
    {
        PageLayout::setTitle(_('E-Mail Aktivierung'));
        $uid = Request::option('uid');
        $user = User::find($uid);

        if (!$user) {
            throw new \Trails\Exception(400);
        }
        if (Request::get('key')) {
            $key = $user->validation_key;

            if (Request::get('key') === $key) {
                $user->validation_key = '';
                $user->store();
                unset($_SESSION['semi_logged_in']);
                PageLayout::postSuccess(_('Ihre E-Mail-Adresse wurde erfolgreich geändert.'));
                $this->redirect(URLHelper::getURL('dispatch.php/start'));
            } else if (!$key) {
                PageLayout::postInfo(_('Ihre E-Mail-Adresse ist bereits geändert.'));
                $this->redirect(URLHelper::getURL('dispatch.php/start'));
            } else {
                if (Request::get('key')) {
                    PageLayout::postError(_("Falscher Bestätigungscode."));
                }
                $this->mail_explain = true;
                if ($_SESSION['semi_logged_in'] === Request::option('uid')) {
                    $this->reenter_mail = true;
                } else {
                    PageLayout::postInfo(_('Sie können sich einloggen und sich den Bestätigungscode neu oder an eine andere E-Mail-Adresse schicken lassen.'));
                    $this->redirect(URLHelper::getURL('dispatch.php/start'));
                }
            }

        // checking semi_logged_in is important to avoid abuse
        } elseif (
            Request::get('email1')
            && Request::get('email2')
            && $_SESSION['semi_logged_in'] === Request::option('uid')
        ) {
            if (Request::get('email1') == Request::get('email2')) {
                // change mail
                $tmp_user = User::find(Request::option('uid'));
                if ($tmp_user && $tmp_user->changeEmail(Request::get('email1'), true)) {
                    $_SESSION['semi_logged_in'] = false;
                }
            } else {
                PageLayout::postError(_('Die eingegebenen E-Mail-Adressen stimmen nicht überein. Bitte überprüfen Sie Ihre Eingabe.'));
            }
            $this->mail_explain = true;
            $this->reenter_mail = true;
        } else {
            $this->mail_explain = true;
        }
    }

    public function privacy_info_action(): void
    {
        // this page must be accessible during visibility decision
        Config::get()->USER_VISIBILITY_CHECK = false;

        PageLayout::setTitle(_('Erläuterungen zum Datenschutz'));
    }
}
