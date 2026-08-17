<?php
/**
 * @var bool $has_login_error
 * @var string $error_msg
 * @var LoginFaq[] $faq_entries
 * @var StudipNews[] $news_entries
 */

// Get background images (this should be resolved differently since mobile
// browsers might still download the desktop background)
if (!match_route('web_migrate.php')) {
    $bg_desktop = LoginBackground::getRandomPicture('desktop');
    if ($bg_desktop) {
        $bg_desktop = $bg_desktop->getURL();
    } else {
        $bg_desktop = URLHelper::getURL('pictures/loginbackgrounds/1.jpg');
    }
    $bg_mobile = LoginBackground::getRandomPicture('mobile');
    if ($bg_mobile) {
        $bg_mobile = $bg_mobile->getURL();
    } else {
        $bg_mobile = URLHelper::getURL('pictures/loginbackgrounds/2.jpg');
    }
} else {
    $bg_desktop = URLHelper::getURL('pictures/loginbackgrounds/1.jpg');
    $bg_mobile = URLHelper::getURL('pictures/loginbackgrounds/2.jpg');
}
$show_login = !(current(StudipAuthAbstract::getInstance()) instanceof StudipAuthSSO) && StudipAuthAbstract::isLoginEnabled();
$show_hidden_login = !$show_login && StudipAuthAbstract::isLoginEnabled();
$enable_faq = count($faq_entries) > 0;
$enable_news = count($news_entries) > 0;
?>
<main id="content" class="loginpage">
    <div id="background-desktop" style="background: url(<?= $bg_desktop ?>) no-repeat center center/cover;"></div>
    <div id="background-mobile" style="background: url(<?= $bg_mobile ?>) no-repeat center center/cover;"></div>

    <div id="login-wrapper" class="<?= $enable_faq || $enable_news ? 'with-infobox' : 'no-infobox' ?>">

        <div id="login-content-wrapper">
            <div id="loginbox">
                <header>
                    <h1><?= _('Login') ?></h1>
                </header>
                <? if ($show_login): ?>
                    <?= $this->render_partial('login/_standard_loginform', [
                        'hidden' => false,
                        'login_form_class' => 'login-top'
                    ]) ?>
                <? endif ?>
                <nav class="<?= $show_hidden_login ? 'login-bottom' : '' ?>">
                    <? foreach (Navigation::getItem('/login') as $key => $nav): ?>
                    <? if ($nav->isVisible()): ?>
                        <? if ($key === 'standard_login' && $show_login)
                            continue; ?>
                    <? endif ?>
                    <? $name_and_title = explode(' - ', $nav->getTitle()) ?>
                    <? if (is_internal_url($url = $nav->getURL())): ?>
                        <? SkipLinks::addLink($name_and_title[0], URLHelper::getURL($url, ['cancel_login' => 1])) ?>
                        <a href="<?= URLHelper::getLink($url, ['cancel_login' => 1]) ?>"
                            <?= arrayToHtmlAttributes($nav->getLinkAttributes()) ?>>
                    <? else: ?>
                        <a href="<?= htmlReady($url) ?>" target="_blank" rel="noopener noreferrer">
                    <? endif ?>
                            <p class="title"><?= htmlReady($name_and_title[0]) ?></p>
                            <p class="description">
                                <?= htmlReady(!empty($name_and_title[1]) ? $name_and_title[1] : $nav->getDescription()) ?>
                            </p>
                        </a>
                        <? endforeach ?>

                        <? if (Config::get()->ENABLE_SELF_REGISTRATION): ?>
                            <a href="<?= URLHelper::getLink('dispatch.php/registration', ['cancel_login' => 1]) ?>"
                               title="<?= _('Registrieren, um das System erstmalig zu nutzen') ?>" class="link-registration">
                                <?= _('Kein Zugang? Jetzt registrieren') ?>
                            </a>
                        <? endif; ?>
                </nav>

                <? if ($show_hidden_login): ?>
                    <?= $this->render_partial('login/_standard_loginform', [
                        'hidden' => !$has_login_error,
                        'login_form_class' => 'login-bottom'
                    ]) ?>
                <? endif ?>
                <? if ($GLOBALS['UNI_LOGIN_ADD']): ?>
                    <footer>
                        <div class="uni_login_add">
                            <?= $GLOBALS['UNI_LOGIN_ADD'] ?>
                        </div>
                    </footer>
                <? endif ?>
            </div>
            <div id="login-infobox" class="<?= !$enable_news && !$enable_faq ? 'hide' :'' ?> <?= !($enable_faq && $enable_news) ? 'no-toggle' : ''?>">
                <? if ($enable_faq && $enable_news): ?>
                    <div id="login-infobox-button-wrapper">
                        <button id="hide-faq" class="selected" title="<?= _('Ankündigungen anzeigen') ?>">
                            <?= Icon::create('news')->asImg(24, ['style' => 'align-self: end;']) ?>
                        </button>
                        <button id="show-faq" title="<?= _('Hinweise zum Login anzeigen')?>">
                            <?= Icon::create('faq')->asImg(24, ['style' => 'align-self: end;']) ?>
                        </button>

                    </div>
                <? endif; ?>
                <? if ($enable_faq): ?>
                    <div id="login-faq-box" class="<?= !$enable_news && $enable_faq ? '' : 'hidden' ?>">
                        <?= $this->render_partial('login/_login_faq', [
                            'faq_entries' => $faq_entries,
                        ]) ?>
                    </div>
                <? endif ?>
                <? if ($enable_news): ?>
                    <div id="login-news-box">
                        <?= $this->render_partial('login/_login_news', [
                            'news_entries' => $news_entries,
                            'enable_faq' => $enable_faq
                        ]) ?>
                    </div>
                <? endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
    //<![CDATA[
    $(function () {
        $('form[name=login]').submit(function () {
            $('input[name=resolution]', this).val(screen.width + 'x' + screen.height);
            $('input[name=device_pixel_ratio]').val(window.devicePixelRatio || 1);
        });
    });
    // -->

    <? if ($enable_faq && $enable_news): ?>
    const faqButton = document.getElementById('show-faq');
    const newsButton = document.getElementById('hide-faq');

    faqButton.addEventListener('click', e => {
        const faqBox = document.getElementById('login-faq-box');
        const newsBox = document.getElementById('login-news-box');
        newsBox.classList.add('hidden');
        faqBox.classList.remove('hidden');
        faqButton.classList.add('selected');
        newsButton.classList.remove('selected');
    });

    newsButton.addEventListener('click', e => {
        const faqBox = document.getElementById('login-faq-box');
        const newsBox = document.getElementById('login-news-box');
        faqBox.classList.add('hidden');
        newsBox.classList.remove('hidden');
        newsButton.classList.add('selected');
        faqButton.classList.remove('selected');
    });
    <? endif ?>

</script>
