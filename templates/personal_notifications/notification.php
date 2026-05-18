<?php

use Studip\Forum\Enum\ReactionEmoji;

?>
<li class="notification item" data-id="<?= $notification['personal_notification_id'] ?>" data-timestamp="<?= (int) $notification['mkdate'] ?>">
    <div class="main">
        <a class="content" href="<?= URLHelper::getLink('dispatch.php/jsupdater/mark_notification_read/' . $notification['personal_notification_id']) ?>"<?= $notification['dialog'] ? ' data-dialog' : '' ?>>
            <? if ($notification['avatar']): ?>
                <? if (filter_var($notification['avatar'], FILTER_VALIDATE_URL)): ?>
                    <div class="avatar" style="background-color: currentColor; mask: url(<?= $notification['avatar'] ?>) no-repeat center / contain;;"></div>
                <? elseif (ReactionEmoji::tryFrom($notification['avatar'])): ?>
                    <div class="emoji-icon">
                        <?= ReactionEmoji::tryFrom($notification['avatar'])->value ?>
                    </div>
                <? endif ?>
            <? endif ?>

            <?= htmlReady($notification['text']) ?>
        </a>
        <button class="options mark_as_read">
            <?= Icon::create('decline')->asImg(16, ['title' => _('Als gelesen markieren')]) ?>
        </button>
    </div>
    <? if ($notification->more_unseen > 0): ?>
        <div class="more">
            <?= htmlReady(sprintf(_('... und %u weitere'), $notification->more_unseen)) ?>
        </div>
    <? endif ?>
</li>
