<?
# Lifter010: TODO
?>
<?= !empty($colon) ? ', ' : '' ?><a href="<?= URLHelper::getLink('dispatch.php/profile', ['username' => $_dozent['username']]) ?>"><?= htmlReady($_dozent['fullname']) ?></a><? $colon = true; ?>
