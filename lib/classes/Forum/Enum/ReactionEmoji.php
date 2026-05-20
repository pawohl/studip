<?php
declare(strict_types=1);

namespace Studip\Forum\Enum;

enum ReactionEmoji: string
{
    case THUMB_UP      = '👍';
    case THUMB_DOWN    = '👎';
    case ROCKET        = '🚀';
    case GRINNING_FACE = '😀';
    case SUNGLASSES    = '😎';
    case CONFUSED      = '😕';
    case HEART         = '♥';
    case PARTY         = '🎉';

    public function label(): string
    {
        return match($this) {
            self::THUMB_UP      => _('Gefällt mir'),
            self::THUMB_DOWN    => _('Gefällt mir nicht'),
            self::ROCKET        => _('Rakete'),
            self::GRINNING_FACE => _('Haha'),
            self::SUNGLASSES    => _('Cool'),
            self::CONFUSED      => _('Verwirrt'),
            self::HEART         => _('Liebe'),
            self::PARTY         => _('Feiern')
        };
    }

    public static function toArray(): array
    {
        return array_map(
            fn(self $case) => [
                'value' => $case->value,
                'label' => $case->label()
            ],
            self::cases()
        );
    }
}
