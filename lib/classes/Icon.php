<?php
/**
 * Icon class is used to create icon objects which can be rendered as
 * svg. Output will be html. Optionally, the icon can be rendered
 * as a css background.
 *
 * @author    Jan-Hendrik Willms <tleilax+studip@gmail.com>
 * @copyright Stud.IP Core Group
 * @license   GPL2 or any later version
 * @since     3.2
 */
class Icon implements JsonSerializable
{
    public const SVG = 1;
    public const CSS_BACKGROUND = 4;
    public const INPUT = 256;

    public const SIZE_DEFAULT = 20;
    public const SIZE_INLINE = 16;
    public const SIZE_BUTTON = self::SIZE_INLINE;
    public const SIZE_FILES_TABLE = 26;
    public const SIZE_LARGE = 32;

    public const DEFAULT_COLOR = 'blue';
    public const DEFAULT_ROLE = 'clickable';

    public const ROLE_INFO          = 'info';
    public const ROLE_CLICKABLE     = 'clickable';
    public const ROLE_ACCEPT        = 'accept';
    public const ROLE_STATUS_GREEN  = 'status-green';
    public const ROLE_INACTIVE      = 'inactive';
    public const ROLE_NAVIGATION    = 'navigation';
    public const ROLE_NEW           = 'new';
    public const ROLE_ATTENTION     = 'attention';
    public const ROLE_STATUS_RED    = 'status-red';
    public const ROLE_INFO_ALT      = 'info_alt';
    public const ROLE_SORT          = 'sort';
    public const ROLE_STATUS_YELLOW = 'status-yellow';

    protected string $shape;
    protected string $role;
    protected array $attributes = [];

    private static array $svg_cache = [];

    /**
     * This is the magical Role to Color mapping.
     */
    private static array $roles_to_colors = [
        self::ROLE_INFO          => 'black',
        self::ROLE_CLICKABLE     => 'blue',
        self::ROLE_ACCEPT        => 'green',
        self::ROLE_STATUS_GREEN  => 'green',
        self::ROLE_INACTIVE      => 'grey',
        self::ROLE_NAVIGATION    => 'blue',
        self::ROLE_NEW           => 'red',
        self::ROLE_ATTENTION     => 'red',
        self::ROLE_STATUS_RED    => 'red',
        self::ROLE_INFO_ALT      => 'white',
        self::ROLE_SORT          => 'blue',
        self::ROLE_STATUS_YELLOW => 'yellow'
    ];

    // return the color associated to a role
    private static function roleToColor($role)
    {
        if (!isset(self::$roles_to_colors[$role])) {
            throw new \InvalidArgumentException('Unknown role: "' . $role . '"');
        }
        return self::$roles_to_colors[$role];
    }

    // return the roles! associated to a color
    public static function colorToRoles($color)
    {
        static $colors_to_roles;

        if (!$colors_to_roles) {
            foreach (self::$roles_to_colors as $r => $c) {
                $colors_to_roles[$c][] = $r;
            }
        }

        if (!isset($colors_to_roles[$color])) {
            throw new \InvalidArgumentException('Unknown color: "' . $color . '"');
        }

        return $colors_to_roles[$color];
    }

    /**
     * Create a new Icon object.
     *
     * This is just a factory method. You could easily just call the
     * constructor instead.
     *
     * @param String $shape      Shape of the icon, may contain a mixed definition
     *                           like 'seminar'
     * @param String $role       Role of the icon, defaults to Icon::DEFAULT_ROLE
     * @param Array $attributes  Additional attributes like 'title';
     *                           only use semantic ones describing
     *                           this icon regardless of its later
     *                           rendering in a view
     * @return Icon object
     */
    public static function create($shape, $role = Icon::DEFAULT_ROLE, $attributes = [])
    {
        // $role may be omitted
        if (is_array($role)) {
            $attributes = $role;
            $role = Icon::DEFAULT_ROLE;
        }

        return new self($shape, $role, $attributes);
    }

    /**
     * Constructor of the object.
     *
     * @param String $shape      Shape of the icon, may contain a mixed definition
     *                           like 'seminar'
     * @param String $role       Role of the icon, defaults to Icon::DEFAULT_ROLE
     * @param Array $attributes  Additional attributes like 'title';
     *                           only use semantic ones describing
     *                           this icon regardless of its later
     *                           rendering in a view
     */
    public function __construct($shape, $role = Icon::DEFAULT_ROLE, array $attributes = [])
    {

        // only defined roles
        if (!isset(self::$roles_to_colors[$role])) {
            throw new \InvalidArgumentException('Creating an Icon without proper role: "' . $role . '"');
        }

        // only semantic attributes
        if ($non_semantic = array_filter(array_keys($attributes), function ($attr) {
            return !in_array($attr, ['title']);
        })) {
            // DEPRECATED
            // TODO starting with the v3.6 the following line should
            // be enabled to prevent non-semantic attributes in this position
            # throw new \InvalidArgumentException('Creating an Icon with non-semantic attributes:' . json_encode($non_semantic));
        }

        $this->shape      = $shape;
        $this->role       = $role;
        $this->attributes = $attributes;
    }

    /**
     * Returns the `shape` -- the string describing the shape of this instance.
     * @return String  the shape of this Icon
     */
    public function getShape()
    {
        return $this->shapeToPath();
    }

    /**
     * Returns the `role` -- the string describing the role of this instance.
     * @return String  the role of this Icon
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * Returns the semantic `attributes` of this instance, e.g. the title of this Icon
     * @return Array  the semantic attribiutes of the Icon
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Returns whether this icon intends to signal attention.
     *
     * @todo This is currently just a heuristic based on the associated icon
     *       role. Although this is sufficient for the current requirements,
     *       it could probably in a better, more suitable way.
     *
     * @return bool
     * @since Stud.IP 5.0
     */
    public function signalsAttention()
    {
        return $this->roleToColor($this->role) === 'red';
    }

    /**
     * Function to be called whenever the object is converted to
     * string. Internally the same as calling Icon::asImg
     *
     * @return String representation
     */
    public function __toString()
    {
        return $this->asImg();
    }

    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }

    /**
     * Renders the icon inside as an svg image.
     *
     * @param int   $size             Optional; Defines the dimension in px of the rendered icon; FALSE prevents any
     *                                width or height attributes
     * @param Array $view_attributes  Optional; Additional attributes to pass
     *                                into the rendered output
     * @return String containing the html representation for the icon.
     *
     * @deprecated will be removed in Stud.IP 7.0. Use `asImg` instead.
     */
    public function asSvg($size = self::SIZE_DEFAULT, $view_attributes = []): string
    {
        return $this->asImg(...func_get_args());
    }

    /**
     * Renders the icon as an HTML image.
     *
     * @param int   $size             Optional; Defines the dimension in px of the rendered icon; FALSE prevents any
     *                                width or height attributes
     * @param Array $view_attributes  Optional; Additional attributes to pass
     *                                into the rendered output
     * @param bool  $force_img_tag    Optional; If true, the icon will always be rendered as an img tag.
     * @return String containing the html representation for the icon.
     */
    public function asImg(
        $size = self::SIZE_DEFAULT,
        $view_attributes = [],
        bool $force_img_tag = false
    ): string {
        if (is_array($size)) {
            [$view_attributes, $size] = [$size, self::SIZE_DEFAULT];
        }

        $size ??= self::SIZE_DEFAULT;

        if ($force_img_tag || $this->isStatic()) {
            return sprintf(
                '<img %s>',
                $this->prepareHTMLAttributes($size, $view_attributes)
            );
        }

        $cacheKey = md5(json_encode([
            'role' => $this->role,
            'shape' => $this->shape,
            'size' => $size,
            'attrs' => $view_attributes
        ]));

        if (isset(self::$svg_cache[$cacheKey])) {
            return self::$svg_cache[$cacheKey];
        }

        $path = __DIR__ . '/../../public/assets/images/icons/' . self::roleToColor($this->role) . '/' . $this->shapeToPath() . '.svg';
        if (!file_exists($path)) {
            return '';
        }

        $titleTag = '';
        if (!empty($view_attributes['title'])) {
            $titleTag = '<title>' . htmlspecialchars($view_attributes['title']) . '</title>';
            unset($view_attributes['title']); // Entfernt 'title' aus den View-Attributen, da es separat hinzugefügt wird
        }

        $attributes = HTMLAttributes::from($view_attributes);
        $attributes->addAttribute('class', 'studip-icon');
        if ($this->role) {
            $attributes->addAttribute('class', "icon-role-{$this->role}");
        }

        if ($size !== false) {
            $attributes->addAttribute('style', "width: {$size}px; height: {$size}px");
        }

        $svgContent = file_get_contents($path);
        $svgContent = preg_replace('/fill="(?!none)[^"]+"/', 'fill="currentColor"', $svgContent);
        $svgContent = preg_replace('/(width|height)="[^"]+"/', '', $svgContent);
        $svgContent = preg_replace_callback('/<svg([^>]+)>/', function($matches) use ($attributes) {
            return '<svg' . $matches[1] . ' ' . $attributes->asString() . '>';
        }, $svgContent);

        if (!empty($titleTag)) {
            $svgContent = preg_replace('/(<svg[^>]*>)/', '$1' . $titleTag, $svgContent, 1);
        }

        self::$svg_cache[$cacheKey] = $svgContent;
        return $svgContent;
    }

    /**
     * Renders the icon inside an input html tag.
     *
     * @param int   $size             Optional; Defines the dimension in px of the rendered icon; FALSE prevents any
     *                                width or height attributes
     * @param Array $view_attributes  Optional; Additional attributes to pass
     *                                into the rendered output
     * @return String containing the html representation for the icon.
     */
    public function asInput($size = self::SIZE_DEFAULT, $view_attributes = [])
    {
        if (is_array($size)) {
            [$view_attributes, $size] = [$size, self::SIZE_DEFAULT];
        }

        $attributes = $this->prepareHTMLAttributes(false, $view_attributes);
        $attributes['class'] = 'as-link';
        unset($attributes['src']);

        return sprintf('<button %s>%s</button>', $attributes, $this->asImg($size));
    }

    /**
     * Renders the icon as a set of css background rules.
     *
     * @param int $size  Optional; Defines the size in px of the rendered icon
     * @return String containing the html representation for css backgrounds
     */
    public function asCSS($size = null)
    {
        $size = $this->get_size($size);

        if ($this->isStatic()) {
            return sprintf(
                'background-image:url(%1$s);background-size:%2$upx %2$upx;',
                $this->shapeToPath(),
                $size
            );
        }

        return sprintf(
            'background-image:url(%1$s);background-size:%2$s %2$s;',
            $this->get_asset_svg(),
            $size === self::SIZE_DEFAULT ? 'var(--icon-size-default)' : "{$size}px"
        );
    }

    /**
     * Returns a path to the SVG matching the icon.
     *
     * @return String containing the html representation for css backgrounds
     */
    public function asImagePath()
    {
        return $this->prepareHTMLAttributes(false, [])['src'];
    }

    /**
     * Returns a new Icon with a changed shape
     * @param mixed  $shape  New value of `shape`
     * @return Icon  A new Icon with a new `shape`
     */
    public function copyWithShape($shape)
    {
        $clone = clone $this;
        $clone->shape = $shape;
        return $clone;
    }

    /**
     * Returns a new Icon with a changed role
     * @param mixed  $role  New value of `role`
     * @return Icon  A new Icon with a new `role`
     */
    public function copyWithRole($role)
    {
        $clone = clone $this;
        $clone->role = $role;
        return $clone;
    }

    /**
     * Returns a new Icon with new attributes
     * @param mixed  $attributes  New value of `attributes`
     * @return Icon  A new Icon with a new `attributes`
     */
    public function copyWithAttributes($attributes)
    {
        $clone = clone $this;
        $clone->attributes = $attributes;
        return $clone;
    }

    /**
     * Prepares the html attributes for use assembling HTML attributes
     * from given shape, role, size, semantic and view attributes
     *
     * @param int|false $size       Size of the icon
     * @param array     $attributes Additional attributes
     */
    private function prepareHTMLAttributes($size, array $attributes): HTMLAttributes
    {
        $html_attributes = HTMLAttributes::merge($this->attributes, $attributes);

        if ($size !== false) {
            $size = $this->get_size($size);
            if ($size !== self::SIZE_DEFAULT && $size !== self::SIZE_INLINE) {
                $html_attributes['style'] = "width: {$size}px; height: {$size}px";
            }
        }

        $html_attributes['src'] = $this->isStatic() ? $this->shape : $this->get_asset_svg();

        if (!isset($html_attributes['alt']) && !isset($html_attributes['title'])) {
            //Add an empty alt attribute to prevent screen readers from
            //reading the URL of the icon:
            $html_attributes['alt'] = '';
        }

        $html_attributes['class'] = ['studip-icon', "icon-role-{$this->role}"];

        if ((int)$size === self::SIZE_INLINE) {
            $html_attributes['class'] = 'studip-icon-inline';
        }

        if (!$this->isStatic()) {
            $html_attributes['class'] = 'icon-shape-' . $this->shapeToPath();
        }

        return $html_attributes;
    }

    /**
     * Get the correct asset for an SVG icon.
     *
     * @return String containing the url of the corresponding asset
     */
    protected function get_asset_svg()
    {
        return Assets::url('images/icons/' . self::roleToColor($this->role) . '/' . $this->shapeToPath() . '.svg');
    }

    /**
     * Get the size of the icon. If a size was passed as a parameter and
     * inside the attributes array during icon construction, the size from
     * the attributes will be used.
     *
     * @param int $size  size of the icon
     * @return int Size of the icon in pixels
     */
    protected function get_size($size)
    {
        $size = $size ?: Icon::SIZE_DEFAULT;
        if (isset($this->attributes['size'])) {
            $parts =  explode('@', $this->attributes['size'], 2);
            $size = $parts[0];
            $temp = $parts[1] ?? null;
            unset($this->attributes['size']);
        }
        return (int)$size;
    }

    // an icon is static if it starts with 'http'
    public function isStatic(): bool
    {
        return preg_match('/^https?:/', $this->shape);
    }

    // transforms a shape w/ possible additions (`shape`) to a path `(addition/)?shape`
    private function shapeToPath()
    {
        if ($this->isStatic()) {
            return $this->shape;
        }
        $shape = array_reverse(explode('/', $this->shape))[0];
        $shape = explode('+', $shape)[0];
        return $shape;
    }
}
