<?php
/**
 * ResponsiveHelper.php
 *
 * This class collects helper methods for Stud.IP's responsive design.
 *
 * @author    Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @license   GPL2 or any later version
 * @copyright Stud.IP core group
 * @since     Stud.IP 3.2
 */
class ResponsiveHelper
{
    /**
     * Returns the current navigation as an array.
     *
     * @return Array containing the navigation
     */
    public static function getNavigationArray()
    {
        $navigation = [];
        $activated  = [];

        $link_params = array_fill_keys(array_keys(URLHelper::getLinkParams()), null);
        $old_base = URLHelper::setBaseURL('');

        foreach (Navigation::getItem('/')->getSubNavigation() as $path => $nav) {
            $image = $nav->getImage();

            $forceVisibility = false;
            /*
             * Special treatment for "browse" navigation which is normally hidden
             * when we are inside a course.
             */
            if ($path === 'browse' && !$image) {
                $image = Icon::create('seminar');
                $forceVisibility = true;
            }
            /*
             * Special treatment for "footer" navigation because
             * the real footer is hidden in responsive view.
             */
            if ($path === 'footer' && !$image) {
                $image = Icon::create('info');
                $nav->setTitle(_('Impressum & Information'));
                $forceVisibility = true;
            }

            $item = [
                'icon'     => $image?->isStatic() ? self::getAssetsURL($image->asImagePath()) : $image?->getShape() ?? false,
                'title'    => (string) $nav->getTitle(),
                'url'      => URLHelper::getURL($nav->getURL(), $link_params, true),
                'parent'   => '/',
                'path'     => $path,
                'visible'  => $forceVisibility || $nav->isVisible(true),
                'active'   => $nav->isActive(),
                'button'   => $nav->getRenderAsButton(),
            ];

            if ($nav->isActive()) {
                // course navigation is integrated in course sub-navigation items
                if ($path === 'course') {
                    $activated[] = 'browse/my_courses/' . (Context::get()->getId());
                } else {
                    $activated[] = $path;
                }
            }

            if ($nav->getSubnavigation() && $path != 'start') {
                $item['children'] = self::getChildren($nav, $path, $activated);
            }

            if ($path !== 'course') {
                $navigation[$path] = $item;
            }
        }
        URLHelper::setBaseURL($old_base);

        return [$navigation, $activated];
    }

    /**
     * Returns the navigation object required for the Vue.js component.
     *
     * The object will always contain the currently selected navigation path.
     * Besides that, the object may contain the whole navigation and a hash
     * for that navigation. If a hash is passed and it matches the currently
     * genereated hash, the navigation and hash will be omitted from the
     * response for performance reasons. We don't want to include the large
     * navigation object in every response.
     *
     * @return array
     */
    public static function getNavigationObject(?string $stored_hash = null): array
    {
        [$navigation, $activated] = self::getNavigationArray();
        $hash = md5(json_encode($navigation));

        $response = compact('activated');
        if ($stored_hash !== $hash) {
            $response = array_merge($response, compact('navigation', 'hash'));
        }

        return $response;
    }

    /**
     * Recursively build a navigation array from the subnavigation/children
     * of a navigation object.
     *
     * @param Navigation  $navigation The navigation object
     * @param String      $path       Current path segment
     * @param array       $activated  Activated items
     * @param String|null $cid       Optional context ID
     * @return Array containing the children (+ grandchildren...)
     */
    protected static function getChildren(Navigation $navigation, $path, &$activated = [], ?string $cid = null)
    {
        $children = [];

        foreach ($navigation->getSubNavigation() as $subpath => $subnav) {
            /*if (!$subnav->isVisible()) {
                continue;
            }*/

            $originalSubpath = $subpath;
            $subpath = "{$path}/{$subpath}";

            $item = [
                'title'   => (string) $subnav->getTitle(),
                'url'     => URLHelper::getURL($subnav->getURL(), $cid ? ['cid' => $cid] : []),
                'parent'  => $path,
                'path'    => $subpath,
                'visible' => $subnav->isVisible(),
                'active'  => $subnav->isActive(),
                'button'  => $subnav->getRenderAsButton(),
            ];

            if ($subnav->isActive()) {
                // course navigation is integrated in course sub-navigation items
                if ($path === 'course') {
                    $activated[] = 'browse/my_courses/' . Context::get()->getId() . '/' . $originalSubpath;
                } else {
                    $activated[] = $subpath;
                }
            }

            if ($subnav->getSubNavigation()) {
                $item['children'] = self::getChildren($subnav, $subpath);
            }

            if ($subpath === 'browse/my_courses') {
                $item['children'] = array_merge($item['children'] ?? [], static::getMyCoursesNavigation($activated));
            }

            $children[$subpath] = $item;
        }

        return $children;
    }

    /**
     * Try to get a compressed version of the passed navigation url.
     * The URL is processed is processed by URLHelper and the absolute uri
     * of the Stud.IP installation is stripped from it afterwards.
     *
     * @param  String $url The url to compress
     * @return String containing the compressed url
     */
    protected static function getURL($url, $params = [])
    {
        return str_replace($GLOBALS['ABSOLUTE_URI_STUDIP'], '', URLHelper::getURL($url, $params));
    }

    /**
     * Try to get a compressed version of the passed assets url.
     * The absolute uri of the Stud.IP installation is stripped from the url.
     *
     * @param  String $url The assets url to compress
     * @return String containing the compressed assets url
     */
    protected static function getAssetsURL($url)
    {
        return str_replace($GLOBALS['ASSETS_URL'], '', $url);
    }

    /**
     * Specialty for responsive navigation: build navigation items
     * for my courses in current semester.
     *
     * @return array
     */
    protected static function getMyCoursesNavigation($activated): array
    {
        $cache = \Studip\Cache\Factory::getCache();
        $cached = $cache->read("my_courses_of_" . User::findCurrent()->id);

        if (!$cached) {

            if (!$GLOBALS['perm']->have_perm('admin')) {

                $sem_data = Semester::getAllAsArray();

                $currentIndex = -1;

                foreach ($sem_data as $index => $semester) {
                    if (!empty($semester['current'])) {
                        $currentIndex = $index;
                        break;
                    }
                }

                $params = [
                    'deputies_enabled' => Config::get()->DEPUTIES_ENABLE
                ];

                $courses = MyRealmModel::getCourses($currentIndex, $currentIndex, $params);

            } else {
                $courses = [];
            }

            $items = [];
            foreach ($courses as $course) {
                $avatar = CourseAvatar::getAvatar($course->id);
                $hasAvatar = $avatar->is_customized();
                $icon = $hasAvatar
                    ? $avatar->getURL(Avatar::SMALL)
                    : Icon::create('seminar', Icon::ROLE_INFO_ALT)->asImagePath();

                $items['browse/my_courses/' . $course->id] = [
                    'icon' => $icon,
                    'avatar' => $hasAvatar,
                    'title' => $course->getFullName(),
                    'url' => URLHelper::getURL('dispatch.php/course/details', ['cid' => $course->id]),
                    'parent' => 'browse/my_courses',
                    'path' => 'browse/my_courses/' . $course->id,
                    'visible' => true,
                    'active' => Context::getId() === $course->id,
                    'children' => self::getRangeNavigation(
                        $course,
                        'browse/my_courses/' . $course->id,
                        $activated
                    ),
                ];

            }

            $cache->write("my_courses_of_" . User::findCurrent()->id, json_encode($items));

        } else {

            $items = json_decode($cached, true);

        }

        // Add current course/institute to list if not already present. This should not be cached.
        if (Context::get()) {

            if (Context::isInstitute()) {
                $avatarClass = InstituteAvatar::class;
                $url = 'dispatch.php/institute/overview';
                $standardIcon = Icon::create('institute', Icon::ROLE_INFO_ALT)->asImagePath();
            } else {
                $avatarClass = CourseAvatar::class;
                $url = 'dispatch.php/course/details';
                $standardIcon = Icon::create('seminar', Icon::ROLE_INFO_ALT)->asImagePath();
            }

            $avatar = $avatarClass::getAvatar(Context::getId());
            $hasAvatar = $avatar->is_customized();
            $icon = $hasAvatar
                ? $avatar->getURL(Avatar::SMALL)
                : $standardIcon;

            $items['browse/my_courses/' . Context::getId()] = [
                'icon' => $icon,
                'avatar' => $hasAvatar,
                'title' => Context::get()->getFullName(),
                'url' => URLHelper::getURL($url, ['cid' => Context::getId()]),
                'parent' => 'browse/my_courses',
                'path' => 'browse/my_courses/' . Context::getId(),
                'visible' => true,
                'active' => true,
                'children' => self::getRangeNavigation(
                    Context::get(),
                    'browse/my_courses/' . Context::getId(),
                    $activated
                ),
            ];

        }

        return $items;
    }

    private static function getRangeNavigation(Range $range, string $path_prefix, array &$activated): array
    {
        if ($range->id === Context::getId()) {
            $navigation = Navigation::getItem('/course');
        } else {
            $navigation = new CourseNavigation($range);
        }

        $result = [];

        foreach ($navigation as $nav_name => $nav) {
            $result[$path_prefix . '/' . $nav_name] = [
                'icon'     => $nav->getImage() ? $nav->getImage()->asImagePath() : '',
                'title'    => $nav->getTitle(),
                'url'      => URLHelper::getURL($nav->getURL(), ['cid' => $range->id]),
                'parent'   => 'browse/my_courses/' . $range->id,
                'path'     => 'browse/my_courses/' . $range->id . '/' . $nav_name,
                'visible'  => true,
                'active'   => $nav->isActive(),
                'children' => static::getChildren(
                    $nav,
                    'browse/my_courses/' . $range->id . '/' . $nav_name,
                    $activated,
                    $range->id
                ),
            ];
        }

        return $result;
    }
}
