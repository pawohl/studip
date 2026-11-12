<?php
/**
 * @var Studip\Forms\Form $form
 * @var SimpleORMapCollection $conflicts
 * @var array $stgteil_versions
 * @var string $fullcalendar
 */
?>
<?= $form->render() ?>
<br>
<? if (count($conflicts)) : ?>
    <article class="studip">
        <header>
            <h1><? printf(_('%s Konflikte'), count($conflicts)) ?></h1>
        </header>
        <?= $this->render_partial('admin/overlapping/overlapping') ?>
    </article>
<? endif; ?>


<!-- server is loading data? - let's show to user! will prevent multiple clicking/sending -->
<script>
    jQuery(function ($) {
        $(document).on('ajaxSend', function (event, xhr, settings) {
            if (settings.type === 'POST'
                && typeof settings.data === 'string'
                && settings.data.indexOf('STUDIPFORM_AUTOSTORE=1') !== -1
                && settings.url.indexOf('admin/overlapping') !== -1
            ) {
                STUDIP.Overlay.show(true, null, false, function () {
                    this.element.css('cursor', 'default');
                }); // show Overlay, keep Cursor

                xhr.done(function (output) {
                    if (output !== 'STUDIPFORM_STORE_SUCCESS') {
                        STUDIP.Overlay.hide(0);
                    }
                });
                xhr.fail(function () {
                    STUDIP.Overlay.hide(0);
                });
            }
        });
    });
</script>
