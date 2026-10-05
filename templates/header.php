<?php
# Lifter010: TODO

$nav_items = Navigation::getItem('/')->getIterator()->getArrayCopy();
$nav_items = array_filter($nav_items, function ($item) {
    return $item->isVisible(true);
});

$header_nav = ['visible' => $nav_items, 'hidden' => []];
if (isset($_COOKIE['navigation-length'])) {
    $header_nav['hidden'] = array_splice(
        $header_nav['visible'],
        $_COOKIE['navigation-length']
    );
}

$navigation = PageLayout::getTabNavigation();
$tab_root_path = PageLayout::getTabNavigationPath();
if ($navigation) {
    $subnavigation = $navigation->activeSubNavigation();
    if ($subnavigation !== null) {
        $nav_links = new NavigationWidget();
        $nav_links->setId('sidebar-navigation');
        $nav_links->addCSSClass('navigation-level-3');
        $nav_links->setTitle(_('Dritte Navigationsebene'));
        if (!$navigation->getImage()) {
            $nav_links->addLayoutCSSClass('show');
        }
        foreach ($subnavigation as $path => $nav) {
            if (!$nav->isVisible()) {
                continue;
            }
            $nav_id = "nav_".implode("_", preg_split("/\//", $tab_root_path, -1, PREG_SPLIT_NO_EMPTY))."_".$path;
            $link = $nav_links->addLink(
                $nav->getTitle(),
                URLHelper::getURL($nav->getURL()),
                null,
                array_merge($nav->getLinkAttributes(), ['id' => $nav_id])
            );
            $link->setActive($nav->isActive());
            if (!$nav->isEnabled()) {
                $link['disabled'] = true;
                $link->addClass('quiet');
            }
        }
        if ($nav_links->hasElements()) {
            Sidebar::get()->insertWidget($nav_links, ':first');
        }
    }
}

?>
<!-- Begin main site header -->
<header id="main-header">

    <!-- Top bar with site title, quick search and avatar menu -->
    <div id="top-bar" role="banner">
        <div id="responsive-menu">
            <?
            $user = User::findCurrent();
            if ($user) {
                $me = [
                    'avatar' => Avatar::getAvatar($user->id)->getURL(Avatar::MEDIUM),
                    'email' => $user->email,
                    'fullname' => $user->getFullName(),
                    'username' => $user->username,
                    'perm' => $GLOBALS['perm']->get_perm()
                ];
            } else {
                $me = ['username' => 'nobody'];
            } ?>
            <?= Studip\VueApp::create('responsive/ResponsiveNavigation')->withProps([
                'context' => Context::get()?->getFullName() ?? '',
                'me' => $me,
                'navigation' => ResponsiveHelper::getNavigationObject($_COOKIE['responsive-navigation-hash'] ?? null),
            ]) ?>
        </div>
        <div id="site-title">
            <?= htmlReady(Config::get()->UNI_NAME_CLEAN) ?>
        </div>

        <!-- Dynamische Links ohne Icons -->
        <div id="header-links">
            <ul>
            <? if (Navigation::hasItem('/links')): ?>
                <? foreach (Navigation::getItem('/links') as $nav): ?>
                    <? if ($nav->isVisible()) : ?>
                        <li class="<? if ($nav->isActive()) echo 'active'; ?> <?= htmlReady($nav->getLinkAttributes()['class'] ?? '') ?>">
                            <a
                                <? if (is_internal_url($url = $nav->getURL())) : ?>
                                    href="<?= URLHelper::getLink($url) ?>"
                                <? else: ?>
                                    href="<?= htmlReady($url) ?>" target="_blank" rel="noopener noreferrer"
                                <? endif; ?>
                                <? if ($nav->getDescription()): ?>
                                    title="<?= htmlReady($nav->getDescription()) ?>"
                                <? endif; ?>
                                    <?= arrayToHtmlAttributes(array_diff_key($nav->getLinkAttributes(), array_flip(['class', 'title']))) ?>
                                ><?= htmlReady($nav->getTitle()) ?></a>
                        </li>
                    <? endif; ?>
                <? endforeach; ?>
            <? endif; ?>

            <? if (isset($show_quicksearch)) : ?>
                <? if (PageLayout::hasCustomQuicksearch()): ?>
                    <?= PageLayout::getCustomQuicksearch() ?>
                <? else: ?>
                    <? SkipLinks::addIndex(_('Suche'), 'globalsearch-input', 910, false) ?>
                    <li id="quicksearch_item">
                        <script>
                            var selectSem = function (seminar_id, name) {
                                document.location = "<?= URLHelper::getURL("dispatch.php/course/details/", ["send_from_search" => 1, "send_from_search_page" => URLHelper::getURL("dispatch.php/search/courses?keep_result_set=1")])  ?>&sem_id=" + seminar_id;
                            };
                        </script>
                        <?= $GLOBALS['template_factory']->render('globalsearch/searchbar') ?>
                    </li>
                <? endif; ?>
            <? endif; ?>

        <? if (is_object($GLOBALS['perm']) && $GLOBALS['perm']->have_perm('user')): ?>
            <? if ($GLOBALS['perm']->have_perm('autor')) : ?>

                <? if (PersonalNotifications::isActivated()): ?>
                    <li id="notification-wrapper">
                        <? $notifications = PersonalNotifications::getMyNotifications() ?>
                        <div id="notification-container"  <?= count($notifications) > 0 ? ' class="hoverable"' : '' ?>>
                            <button id="notification_marker"
                                    data-toggles="#notification_checkbox"
                                    title="<?= sprintf(
                                        ngettext('%u Benachrichtigung', '%u Benachrichtigungen', count($notifications)),
                                        count($notifications)
                                    ) ?>"
                                    aria-controls="notification-list"
                                    data-lastvisit="<?= UserConfig::get($GLOBALS['user']->id)->getValue('NOTIFICATIONS_SEEN_LAST_DATE') ?>"
                                    <? if (count($notifications) === 0) echo 'disabled'; ?>
                                    class="<?= PersonalNotifications::hasUnseenNotifications() ? 'alert' : '' ?>"
                                    aria-expanded="false"
                            >
                                <span class="count" aria-hidden="true"><?= count($notifications) ?></span>
                                <?= Icon::create('notification2', Icon::ROLE_INFO)->asImg() ?>
                            </button>
                            <input type="checkbox" id="notification_checkbox">
                            <div class="list below" id="notification_list">
                                <a class="mark-all-as-read <? if (count($notifications) < 2) echo 'invisible'; ?>"
                                href="<?= URLHelper::getLink('dispatch.php/jsupdater/mark_notification_read/all', ['return_to' => $_SERVER['REQUEST_URI']]) ?>"
                                >
                                    <?= _('Alle Benachrichtigungen als gelesen markieren') ?>
                                </a>
                                <a class="enable-desktop-notifications" href="#" style="display: none;">
                                    <?= _('Desktop-Benachrichtigungen aktivieren') ?>
                                </a>
                                <ul>
                                <? foreach ($notifications as $notification) : ?>
                                    <?= $notification->getLiElement() ?>
                                <? endforeach ?>
                                </ul>
                            </div>
                        </div>
                    </li>
                <? endif; ?>

                <? if (Navigation::hasItem('/avatar')): ?>
                    <li id="avatar-wrapper">
                        <form id="avatar-menu" method="post">
                        <?= AvatarMenu::forUser(User::findCurrent())->withNavigation('/avatar') ?>
                        <?php SkipLinks::addIndex(_('Profilmenü'), 'header_avatar_image_link', 1, false); ?>
                        </form>
                    </li>
                <? endif; ?>

            <? endif; ?>

            <li id="responsive-create-shortlink" class="hidden-small-up">
                <a href="<?= URLHelper::getLink('dispatch.php/short_urls/create') ?>" id="responsive-create-shortlink-dummy">
                    <?= Icon::create('share', Icon::ROLE_INFO_ALT)->asImg(24) ?>
                </a>
                <?= Studip\VueApp::create('short-urls/ShortUrlLink')
                    ->withProps([
                        'isInContext' => Context::isCourse() && Context::get()->hasCourseSet(),
                        'withIcon' => true
                    ]) ?>
            </li>
        <? else: ?>
                <li><?= $this->render_partial('login/_header_contrast') ?></li>
                <li><?= $this->render_partial('login/_header_languages') ?></li>
        <? endif; ?>

                <li id="responsive-toggle-fullscreen">
                    <button class="styleless" id="fullscreen-off"
                            title="<?= _('Kompakte Navigation ausschalten') ?>">
                        <?= Icon::create('screen-standard', Icon::ROLE_INFO_ALT)->asImg(24) ?>
                    </button>
                </li>
                <li id="responsive-toggle-focusmode">
                    <button class="styleless consuming_mode_trigger" id="focusmode-on"
                            title="<?= _('Vollbild aktivieren') ?>">
                        <?= Icon::create('screen-full', Icon::ROLE_INFO_ALT)->asImg(24) ?>
                    </button>
                </li>
            </ul>
        </div>
    </div>
    <!-- End top bar -->
    <!-- Main navigation and right-hand logo -->
    <nav id="navigation-level-1" aria-label="<?= _('Hauptnavigation') ?>">
        <? if (!empty($header_nav['visible'])) : ?>
            <? SkipLinks::addIndex(_('Hauptnavigation'), 'navigation-level-1', 2, false) ?>
        <? endif ?>
        <ul id="navigation-level-1-items" <? if (count($header_nav['hidden']) > 0) echo 'class="overflown"'; ?>>
        <? foreach ($header_nav['visible'] as $path => $nav): ?>
            <?= $this->render_partial(
                'header-navigation-item.php',
                compact('path', 'nav')
            ) ?>
        <? endforeach; ?>
            <li class="overflow">
                <input type="checkbox" id="header-sink">
                <button class="as-link"
                        aria-controls="header-sink-list"
                        aria-expanded="false"
                        data-toggles="#header-sink"
                >
                    <?= Icon::create('action', 'navigation')->asImg(32, [
                        'class'  => 'headericon original',
                        'title'  => '',
                        'alt'    => '',
                    ]) ?>
                    <div class="navtitle">
                        <?= _('Mehr') ?>&hellip;
                    </div>
                </button>

                <ul id="header-sink-list">
                <? foreach ($header_nav['hidden'] as $path => $nav) : ?>
                    <?= $this->render_partial(
                        'header-navigation-item.php',
                        compact('path', 'nav')
                    ) ?>
                <? endforeach; ?>
                </ul>
            </li>
        </ul>

        <!-- Stud.IP Logo -->
        <a class="studip-logo" id="top-logo" href="http://www.studip.de/" title="Stud.IP Homepage" target="_blank" rel="noopener noreferrer">
            Stud.IP Homepage
        </a>
    </nav>
    <!-- End main navigation -->

    <? $contextable = Context::get() && (
            (Navigation::hasItem('/course') && Navigation::getItem('/course')->isActive()) ||
            (Navigation::hasItem('/admin/institute') && Navigation::getItem('/admin/institute')->isActive())); ?>

    <div id="current-page-structure" <? if (!($contextable)) echo 'class="contextless"'; ?>>

        <? if (Context::get() || PageLayout::isHeaderEnabled()): ?>
            <? if ($contextable) : ?>
                <div id="context-title">
                <? if (is_object($GLOBALS['perm']) && !$GLOBALS['perm']->have_perm('admin')) : ?>
                        <? $membership = CourseMember::find([Context::get()->id, $GLOBALS['user']->id]) ?>
                        <? if ($membership) : ?>
                            <a href="<?= URLHelper::getLink('dispatch.php/my_courses/groups') ?>"
                            data-dialog
                            class="colorblock gruppe<?= $membership ? $membership['gruppe'] : 1 ?>"></a>
                        <? endif ?>
                    <? endif ?>
                    <? if (Context::isCourse()) : ?>
                        <? if (Context::get()->isStudygroup()) : ?>
                            <?= StudygroupAvatar::getAvatar(Context::getId())->getImageTag(Avatar::NORMAL, ['class' => 'context-avatar']) ?>
                        <? else : ?>
                            <?= CourseAvatar::getAvatar(Context::getId())->getImageTag(Avatar::NORMAL, ['class' => 'context-avatar']) ?>
                        <? endif ?>
                        <span class="course-type"><?= htmlReady(Context::get()->getFullName('type')) ?>:</span> <span class="course-name"><?= htmlReady(Context::get()->getFullName(Config::get()->IMPORTANT_SEMNUMBER ? 'number-name' : 'name')) ?></span>
                        <? if ($GLOBALS['user']->config->SHOWSEM_ENABLE && !Context::get()->isOpenEnded()): ?>
                            <span class="course-semester">(<?= htmlReady(Context::get()->getTextualSemester()) ?>)</span>
                        <? endif ?>
                    <? elseif (Context::isInstitute()) : ?>
                        <?= InstituteAvatar::getAvatar(Context::get()->id)->getImageTag(Avatar::SMALL, ['class' => 'context-avatar']) ?>
                        <?= htmlReady(Context::get()->name) ?>
                    <? endif ?>
                </div>
            <? endif ?>

            <? if (
                PageLayout::isHeaderEnabled()
                && Navigation::hasItem('/course')
                && Navigation::getItem('/course')->isActive()
                && !empty($_SESSION['seminar_change_view_'.Context::getId()])
            ) : ?>
                <?= $this->render_partial('change_view', ['changed_status' => $_SESSION['seminar_change_view_'.Context::getId()]]) ?>
            <? endif ?>

            <nav id="navigation-level-2" aria-label="<?= _('Zweite Navigationsebene') ?>">

                <? if (PageLayout::isHeaderEnabled() /*&& isset($navigation)*/) : ?>
                    <? if (!empty($navigation)) : ?>
                        <? SkipLinks::addIndex(_('Zweite Navigationsebene'), 'navigation-level-2', 910) ?>
                    <? endif ?>
                    <?= $this->render_partial('tabs', compact('navigation')) ?>
                <? endif; ?>
            </nav>
        <? endif; ?>

        <?
        $public_hint = '';
        if (is_object($GLOBALS['user']) && $GLOBALS['user']->id != 'nobody') {
            // only mark course if user is logged in and free access enabled
            $is_public_course = Context::isCourse() && Config::get()->ENABLE_FREE_ACCESS;
            $is_public_institute = Context::isInstitute()
                && Config::get()->ENABLE_FREE_ACCESS
                && Config::get()->ENABLE_FREE_ACCESS != 'courses_only';
            if (($is_public_course || $is_public_institute)
                && Navigation::hasItem('/course')
                && Navigation::getItem('/course')->isActive())
            {
                // indicate to the template that this course is publicly visible
                // need to handle institutes separately (always visible)
                if (isset($GLOBALS['SessSemName']['class']) && $GLOBALS['SessSemName']['class'] === 'inst') {
                    $public_hint = _('öffentliche Einrichtung');
                } else if (Course::findCurrent() && !Course::findCurrent()->lesezugriff) {
                    $public_hint = _('öffentliche Veranstaltung');
                }
            }
        }
        ?>

        <div id="page-title-container" class="hidden-medium-up">
            <div id="page-title">
                <? if (Context::get() && strpos(PageLayout::getTitle(), Context::getHeaderLine() . ' - ') !== FALSE) : ?>
                    <?= htmlReady(str_replace(Context::getHeaderLine() . ' - ' , '', PageLayout::getTitle())) ?>
                <? else: ?>
                    <?= htmlReady( PageLayout::getTitle()) ?>
                <? endif ?>
                <?= !empty($public_hint) ? '(' . htmlReady($public_hint) . ')' : '' ?>
            </div>
        </div>
    </div>

    <div id="responsive-contentbar-container"></div>

<!-- End main site header -->
</header>
