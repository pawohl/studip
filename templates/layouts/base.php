<?php
NotificationCenter::postNotification('PageWillRender', PageLayout::getBodyElementId());
$getInstalledLanguages = function () {
    $languages = [];
    foreach ($GLOBALS['INSTALLED_LANGUAGES'] as $key => $value) {
        $languages[$key] = array_merge(
            $value,
            ['selected' => $_SESSION['_language'] === $key]
        );
    }

    return $languages;
};

$getJsonApiSchemas = function () {
    return array_values(
        array_unique(
            array_map(
                fn($class) => $class::TYPE,
                app('json-api-integration-schemas')
            )
        )
    );
};

$lang_attr = str_replace('_', '-', $_SESSION['_language']);
?>
<!DOCTYPE html>
<html class="no-js" lang="<?= htmlReady($lang_attr) ?>">
<head>
    <meta charset="utf-8">
    <title data-original="<?= htmlReady(PageLayout::getTitle()) ?>">
        <?= htmlReady(PageLayout::getTitle() . ' - ' . Config::get()->UNI_NAME_CLEAN) ?>
    </title>
    <script>
        CKEDITOR_BASEPATH = "<?= Assets::url('javascripts/ckeditor/') ?>";
        String.locale = "<?= htmlReady(strtr($_SESSION['_language'], '_', '-')) ?>";

        document.querySelector('html').classList.replace('no-js', 'js');

        window.STUDIP = {
            ABSOLUTE_URI_STUDIP: "<?= $GLOBALS['ABSOLUTE_URI_STUDIP'] ?>",
            ASSETS_URL: "<?= $GLOBALS['ASSETS_URL'] ?>",
            CSRF_TOKEN: {
                name: '<?=CSRFProtection::TOKEN?>',
                value: '<? try {echo CSRFProtection::token();} catch (SessionRequiredException $e){}?>'
            },
            INSTALLED_LANGUAGES: <?= json_encode($getInstalledLanguages()) ?>,
            CONTENT_LANGUAGES: <?= json_encode(array_keys($GLOBALS['CONTENT_LANGUAGES'])) ?>,
            STUDIP_SHORT_NAME: "<?= htmlReady(Config::get()->STUDIP_SHORT_NAME) ?>",
            URLHelper: {
                base_url: "<?= $GLOBALS['ABSOLUTE_URI_STUDIP'] ?>",
                parameters: <?= json_encode(URLHelper::getLinkParams(), JSON_FORCE_OBJECT) ?>
            },
            USER_ID: <?= json_encode($GLOBALS['user']->id) ?>,
            jsupdate_enable: <?= json_encode(
                             is_object($GLOBALS['perm']) &&
                             $GLOBALS['perm']->have_perm('autor')) ?>,
            server_timestamp: <?= time() ?>,
            config: <?= json_encode([
                'ACTIONMENU_THRESHOLD' => Config::get()->ACTION_MENU_THRESHOLD,
                'ENTRIES_PER_PAGE'     => Config::get()->ENTRIES_PER_PAGE,
                'OPENGRAPH_ENABLE'     => Config::get()->OPENGRAPH_ENABLE,
                'COURSEWARE_CERTIFICATES_ENABLE' => Config::get()->COURSEWARE_CERTIFICATES_ENABLE,
                'ENABLE_COURSESET_FCFS' => (bool) Config::get()->ENABLE_COURSESET_FCFS
            ]) ?>,
            jsonapi_schemas: <?= json_encode($getJsonApiSchemas()) ?>,
        }
    </script>

    <?= PageLayout::getHeadElements() ?>

    <script>
        setTimeout(() => {
            // This needs to be put in a timeout since otherwise it will not match
            if (STUDIP.Responsive.isResponsive()) {
                document.querySelector('html').classList.add('responsive-display');
            }
        }, 0);
    </script>
</head>

<body id="<?= PageLayout::getBodyElementId() ?>" <? if (!PageLayout::isSidebarEnabled()) echo 'class="no-sidebar"'; ?>>
    <nav id="skip_link_navigation" aria-label="<?= _('Sprunglinks') ?>" aria-busy="true"></nav>
    <?= PageLayout::getBodyElements() ?>

    <? include 'lib/include/header.php' ?>

    <? if (PageLayout::isSidebarEnabled()): ?>
        <?= Sidebar::get()->render() ?>
    <? endif; ?>

    <!-- Start main page content -->
    <main id="content-wrapper">
        <? SkipLinks::addIndex(_('Hauptinhalt'), 'content', 100) ?>
        <div id="content">
            <h1 class="sr-only"><?= htmlReady(PageLayout::getTitle()) ?></h1>
            <? if (PageLayout::isFullscreenModeAllowed()): ?>
                <button hidden class="fullscreen-toggle unfullscreen" aria-label="<?= _('Vollbildmodus verlassen') ?>" title="<?= _('Vollbildmodus verlassen') ?>">
                    <?= Icon::create('zoom-out2')->asImg(24) ?>
                </button>
            <? endif; ?>
            <?= implode(PageLayout::getMessages(QuestionBox::class)) ?>
            <?= $content_for_layout ?>
        </div>
        <?= Studip\VueApp::create('SystemNotificationManager')
            ->withProps([
                'id'            => 'system-notifications',
                'notifications' => PageLayout::getMessages(MessageBox::class),
                'placement'     => User::findCurrent()?->getConfiguration()->SYSTEM_NOTIFICATIONS_PLACEMENT ?? 'topcenter',
            ]) ?>
    </main>
    <!-- End main content -->

    <a id="scroll-to-top" class="hide" tabindex="0" title="<?= _('Zurück zum Seitenanfang') ?>">
        <?= Icon::create('arr_1up', 'info_alt')->asImg(24, ['class' => '']) ?>
    </a>

    <?= $this->render_partial('footer', ['link_params' => $header_template->link_params ?? null]); ?>
    <?= SkipLinks::getHTML() ?>
    <section class="sr-only" id="notes_for_screenreader" aria-live="polite"></section>

<?php
if (Studip\Debug\DebugBar::isActivated()) {
    echo app()->get(\DebugBar\DebugBar::class)->getJavascriptRenderer()->render();
}
?>
</body>
</html>
<?php NotificationCenter::postNotification('PageDidRender', PageLayout::getBodyElementId());
