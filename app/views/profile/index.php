<?php
/**
 * @var User $current_user
 * @var string|null $public_email
 * @var string|null $private_nr
 * @var string|null $private_cell
 * @var string|null $privadr
 * @var string|null $homepage
 * @var array $study_institutes
 * @var array $institutes
 * @var array $shortDatafields
 * @var string|null $news
 * @var string|null $dates
 * @var string|null $questionnaires
 * @var array|null $public_files
 * @var array|null $ausgabe_inhalt
 * @var array $longDatafields
 * @var string $hompage_plugin
 * @var array $categories
 * @var ProfileController $controller
 */
?>
<div class="hidden-medium-up">
    <?= Avatar::getAvatar($current_user->user_id)->getImageTag(Avatar::NORMAL) ?>
</div>
<section class="contentbox">
    <header>
        <h1>
            <?= _('Allgemeine Informationen') ?>
        </h1>
    </header>
    <section>
        <dl>
        <? if ($public_email): ?>
            <dt><?= _('E-Mail') ?>:</dt>
            <dd>
                <a href="mailto:<?= htmlReady($public_email) ?>">
                    <?= htmlReady($public_email) ?>
                </a>
            </dd>
        <? endif; ?>

        <? if ($private_nr) : ?>
            <dt><?= _('Telefon (privat)') ?>:</dt>
            <dd><?= htmlReady($private_nr) ?></dd>
        <? endif ?>

        <? if ($private_cell) : ?>
            <dt><?= _('Mobiltelefon') ?>:</dt>
            <dd><?= htmlReady($private_cell) ?></dd>
        <? endif ?>

        <? if ($privadr) : ?>
            <dt><?= _('Adresse (privat)') ?>:</dt>
            <dd><?= htmlReady($privadr) ?></dd>
        <? endif ?>

        <? if ($homepage) : ?>
            <dt><?= _('Homepage') ?>:</dt>
            <dd><?= formatLinks($homepage) ?></dd>
        <? endif ?>

        <? if (count($study_institutes) > 0): ?>
            <dt><?= _('Wo ich studiere') ?>:</dt>
            <dd>
                <ul>
                <? foreach ($study_institutes as $inst_result) : ?>
                    <li>
                        <a href="<?= $controller->link_for('institute/overview', ['auswahl' => $inst_result->institut_id]) ?>">
                            <?= htmlReady($inst_result->institute->name) ?>
                        </a>
                    </li>
                <? endforeach ?>
                </ul>
            </dd>
        <? endif ?>

        <? if (count($institutes) > 0) : ?>
            <?= $this->render_partial('profile/working_place') ?>
        <? endif ?>

        <? if ($current_user->user_id === $GLOBALS['user']->id) : ?>
            <dt><?= _('Status') ?>:</dt>
            <dd><?= htmlReady(ucfirst($current_user->perms)) ?></dd>
          <? if ($current_user->matriculation_number): ?>
            <dt><?= _('Matrikelnummer') ?>:</dt>
            <dd><?= htmlReady($current_user->matriculation_number) ?></dd>
          <? endif ?>
        <? endif ?>

        <? if (!empty($shortDatafields)) : ?>
            <? foreach ($shortDatafields as $name => $entry) : ?>
                <dt><?= htmlReady($name) ?>:</dt>
                <dd>
                    <?= $entry['content'] ?>
                    <span class="minor"><?= $entry['visible'] ?></span>
                </dd>
            <? endforeach ?>
        <? endif ?>
        </dl>
    </section>

</section>

<?= $news ?? '' ?>

<?= $dates ?? '' ?>

<?= $questionnaires ?? '' ?>

<? if (!empty($ausgabe_inhalt)) : ?>
    <? foreach ($ausgabe_inhalt as $key => $inhalt) : ?>
        <article class="studip">
            <header>
                <h1><?= htmlReady($key) ?></h1>
            </header>
            <section>
                <?= formatReady($inhalt) ?>
            </section>
        </article>
    <? endforeach ?>
<? endif ?>

<? if (isset($public_files)) : ?>
    <?= $this->render_partial('profile/public_files') ?>
<? endif ?>

<? if ($current_user->perms === 'dozent' && !empty($seminare)) : ?>
    <?= $this->render_partial('profile/seminare') ?>
<? endif ?>

<? if (!empty($longDatafields)) : ?>
    <? foreach ($longDatafields as $name => $entry) : ?>
        <article class="studip">
            <header>
                <h1><?= htmlReady($name . ' ' . $entry['visible']) ?></h1>
            </header>
            <section>
                <?= $entry['content'] ?>
            </section>
        </article>
    <? endforeach ?>
<? endif ?>

<?= $hompage_plugin ?>

<? if (!empty($categories)) : ?>
    <? foreach ($categories as $cat) : ?>
        <article class="studip">
            <header>
                <h1><?= htmlReady($cat['head'] . $cat['zusatz']) ?></h1>
            </header>
            <section>
                <?= formatReady($cat['content']) ?>
            </section>
        </article>
    <? endforeach ?>
<? endif; ?>
