<?php

/**
 * Theme.php - Stud.IP Theme Model Class
 *
 * @author    Ron Lucke <lucke@elan-ev.de>
 * @license   GPL2 or any later version
 *
 * @property int $id
 * @property bool $active
 * @property string $name
 * @property string $origin
 * @property string $version
 * @property string $studip_min_version
 * @property string $studip_max_version
 * @property string $author
 * @property string $description
 * @property string $type
 * @property JSONArrayObject $values
 * @property int $mkdate database column
 * @property int $chdate database column
 */

 class Theme extends SimpleORMap
 {
    public const COLOR_KEY_CATEGORIES = [
        'brand' => [
            '--color--brand-primary' => '#28497c',
            '--color--brand-primary-contrast' => '#ffffff',
            '--color--brand-secondary' => '#28497c',
            '--color--brand-secondary-contrast' => '#ffffff',
        ],
        'general' => [
            '--color--global-background' => '#ffffff',
        ],
        'text' => [
            '--color--font-primary' => '#101010',
            '--color--font-secondary' => '#3c454e',
            '--color--font-inactive' => '#676767',
            '--color--font-inverted' => '#ffffff',
        ],
        'navigation' => [
            '--color--main-navigation-item' => '#28497c',
        ],
        'sidebar' => [
            '--color--sidebar-item' => '#28497c',
            '--color--sidebar-item-hover' => '#101010',
        ],
        'content' => [
            '--color--highlight' => '#28497c',
            '--color--highlight-hover' => '#d60000',
            '--color--content-link' => '#28497c',
            '--color--content-link-hover' => '#d60000',
        ],
    ];

    protected static function configure($config = [])
    {
        $config['db_table'] = 'themes';
        $config['serialized_fields']['values'] = JSONArrayObject::class;

        $config['registered_callbacks']['after_store'][] = function (Theme $theme): void {
            if (
                $theme->isFieldDirty('active')
                || (
                    $theme->active
                    && $theme->isFieldDirty('values')
                )
            ) {
                self::regenerateCSS();
            }
        };

        $config['registered_callbacks']['after_delete'][] = function (Theme $theme): void {
            if ($theme->active) {
                self::regenerateCSS();
            }
        };

        parent::configure($config);
    }

    public static function getThemeAsset(): PluginAsset
    {
        $asset = new PluginAsset('studip-theme');
        if ($asset->isNew()) {
            $asset->plugin_id = 0;
            $asset->type = 'css';
            $asset->filename = 'theme.css';
            $asset->storagename = 'theme.css';
            $asset->store();

            $asset->writeContent(self::getActiveCSS());
        }
        return $asset;
    }

    /**
     * @return static[]
     */
    public static function getActiveThemes(): array
    {
        return [
            'light' => self::getActiveLightTheme(),
            'dark' => self::getActiveDarkTheme(),
            'high-contrast' => self::getActiveHighContrastTheme(),
        ];
    }

    public static function getDownloadURL(): string
    {
        $asset = self::getThemeAsset();
        return URLHelper::getURL(
            "assets.php/css/{$asset->id}#{$asset->filename}",
            ['v' => $asset->chdate],
            true
        );
    }

    private static function regenerateCSS(): void
    {
        self::getThemeAsset()->delete();

        self::loadActiveThemes(true);
        self::getThemeAsset()->writeContent(self::getActiveCSS());
    }

    public static function getActiveCSS(): string
    {
        $css = '';
        foreach (self::getActiveThemes() as $theme) {
            if ($theme) {
                $css .= $theme->render() . PHP_EOL;
            }
        }
        return $css;
    }

    public static function getActiveLightTheme(): ?static
    {
        return self::loadActiveThemes()['light'];
    }

    public static function getActiveDarkTheme(): ?static
    {
        return self::loadActiveThemes()['dark'];
    }

    public static function getActiveHighContrastTheme(): ?static
    {
        return self::loadActiveThemes()['high-contrast'];
    }

    protected static ?array $active_themes = null;

    public static function loadActiveThemes(bool $force = false): array
    {
        if ($force || self::$active_themes === null) {
            self::$active_themes = [
                'light'         => null,
                'dark'          => null,
                'high-contrast' => null,
            ];
            self::findEachBySQL(
                function (self $theme): void {
                    self::$active_themes[$theme->type] = $theme;
                },
                'active = 1'
            );
        }
        return self::$active_themes;
    }

    public static function getColorKeyCategories(): array
    {
        return self::COLOR_KEY_CATEGORIES;
    }

    public function render(): string
    {
        $lines = [];

        $indent = '    ';
        if ($this->type === 'dark') {
            $lines[] = '@media (prefers-color-scheme: dark) {';
        } elseif ($this->type === 'high-contrast') {
            $lines[] = '@media (prefers-contrast: more) {';
        } else {
            $indent = '';
        }

        $lines[] = $indent . ':root {';
        foreach ($this->values as $name => $value) {

            if ($value !== '') {
                $lines[] = $indent . "    {$name}: {$value};";
            }
        }
        $lines[] = $indent . '}';

        if ($indent !== '') {
            $lines[] = '}';
        }
        return implode(PHP_EOL, $lines);
    }
 }
