<?php
/**
 * Forum: Discussion of specific topics within courses
 *
 * @author  Murtaza Sultani <sultani@data-quest.de>
 * @author  Rasmus Fuhse <fuhse@data-quest.de>
 * @license GPL2 or any later version
 * @since   Stud.IP 6.1
 */

use Forum\Posting;

class CoreForum extends CorePlugin implements StudipModuleExtended
{
    use IconNavigationTrait;

    public function getTabNavigation($course_id)
    {
        $navigation = new Navigation(_('Forum'), 'dispatch.php/course/forum/topics');

        $navigation->setImage(Icon::create('forum', 'info_alt'));

        $navigation->addSubNavigation(
            'topics',
            new Navigation(_('Themenübersicht'), 'dispatch.php/course/forum/topics')
        );

        if (!RangeConfig::get($course_id)->FORUM_HIDE_CATEGORIES) {
            $navigation->addSubNavigation(
                'categories',
                new Navigation(_('Kategorien'), 'dispatch.php/course/forum/categories')
            );
        }

        $navigation->addSubNavigation(
            'discussions',
            new Navigation(_('Alle Diskussionen'), 'dispatch.php/course/forum/discussions')
        );

        $navigation->addSubNavigation(
            'subscriptions',
            new Navigation(_('Abonnements'), 'dispatch.php/course/forum/subscriptions')
        );

        return ['forum' => $navigation];
    }

    public function getManyIconNavigation(array $course_ids, ?string $user_id = null): array
    {
        $navs = [];
        $posts = Posting::getRecentPosts($course_ids);
        foreach ($course_ids as $course_id) {
            $recent_posts_count = 0;
            $navigation_title = _('Forum');

            if ($GLOBALS['perm']->have_studip_perm('user', $course_id)) {
                $recent_posts_count = !empty($posts[$course_id])
                    ? array_sum(array_column($posts[$course_id], 'posts'))
                    : 0;

                if ($recent_posts_count > 0) {
                    $navigation_title = sprintf(_('%s neue Beiträge seit Ihrem letzten Besuch.'), $recent_posts_count);
                } else {
                    $navigation_title = _('Keine neuen Beiträge seit Ihrem letzten Besuch.');
                }
            }

            $navigation = new Navigation(_('Forum'));
            $navigation->setBadgeNumber($recent_posts_count);
            $navigation->setLinkAttributes(['title' => $navigation_title]);
            if ($recent_posts_count > 0) {
                $navigation->setImage(Icon::create('forum', Icon::ROLE_ATTENTION));
                $navigation->setURL('dispatch.php/course/forum/recent');
            } else {
                $navigation->setImage(Icon::create('forum'));
                $navigation->setURL('dispatch.php/course/forum/topics');
            }
            $navs[$course_id] = $navigation;
        }
        return $navs;
    }

    public function getInfoTemplate($course_id)
    {
        // TODO: Implement getInfoTemplate() method.
        return null;
    }

    public static function isAdmin($range_id): bool
    {
        return $GLOBALS['perm']->have_perm('root')
            || $GLOBALS['perm']->have_studip_perm('dozent', $range_id);
    }

    public static function isModerator($range_id): bool
    {
        if (self::isAdmin($range_id)) {
            return true;
        }

        $moderation_permission = RangeConfig::get($range_id)->getValue('FORUM_MODERATION_PERMISSION');

        return $moderation_permission === 'all'
            || $GLOBALS['perm']->have_studip_perm($moderation_permission, $range_id);
    }


    /**
     * {@inheritdoc}
     */
    public function getMetadata(): array
    {
        return [
            'summary' => _('Veranstaltungsbegleitender Meinungsaustausch zu bestimmten Themen'),
            'description' => _('Textbasierte zeit- und ortsunabhängige Möglichkeit zum Austausch von Gedanken, Meinungen und Erfahrungen. Lehrende und/oder Studierende können parallel zu Veranstaltungsthemen Fragen stellen, die in Form von Textbeiträgen besprochen werden können. Diese Beiträge können von allen Teilnehmenden der Veranstaltung gemerkt, verlinkt, positiv bewertet (sog. "Gefällt mir") und editiert werden (Letzeres nur von Lehrenden). Lehrende können zusätzlich Themen in Bereiche gliedern, zwischen den Bereichen verschiebe, im Bereich hervorheben und diesen öffnen und schließen.'),
            'descriptionlong' => _('Textbasierte zeit- und ortsunabhängige Möglichkeit zum Austausch von Gedanken, Meinungen und Erfahrungen. Lehrende und/oder Studierende können parallel zu Veranstaltungsthemen Fragen stellen, die in Form von Textbeiträgen besprochen werden können. Diese Beiträge können von allen Teilnehmenden der Veranstaltung gemerkt, verlinkt, positiv bewertet (sog. "Gefällt mir") und editiert werden (Letzeres nur von Lehrenden). Lehrende können zusätzlich Themen in Bereiche gliedern, zwischen den Bereichen verschieben, im Bereich hervorheben und diesen öffnen und schließen.'),
            'category' => _('Kommunikation und Zusammenarbeit'),
            'keywords' => _('Möglichkeit zum intensiven, nachhaltigen textbasierten Austausch; (nachträgliche) Strukturierung der Beiträge; Editierfunktion für Lehrende'),
            'icon' => Icon::create('forum', Icon::ROLE_INFO),
            'icon_clickable' => Icon::create('forum'),
            'screenshots' => [
                'path' => 'assets/images/plus/screenshots/Forum',
                'pictures' => [
                    ['source' => 'Lehrendensicht_-_Kategorien_mit_Bereichen_und_Beitraegen.jpg'],
                    ['source' => 'Studentische_Sicht_-_Kategorien_mit_Bereichen_und_Beitraegen.jpg'],
                    ['source' => 'Einen_Forumsbeitrag_erstellen.jpg'],
                ]
            ]
        ];
    }

    public static function deleteCourseContents($range_id): void
    {
        \Forum\Category::deleteBySQL("range_id = ?", [$range_id]);
        \Forum\Topic::deleteBySQL("range_id = ?", [$range_id]);
    }
}
