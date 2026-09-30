<section id="login-news-content-wrapper">
    <? foreach ($news_entries as $entry): ?>
        <article class="login-news hidden" id="login-news-<?= htmlReady($entry['news_id']) ?>">
            <header>
                <h2><?= htmlReady($entry['topic']) ?></h2>
            </header>
            <section class="login-news-details">
                <p>
                    <?= formatReady($entry['body']) ?>
                </p>
            </section>
        </article>
    <? endforeach; ?>
</section>
<? if (count($news_entries) > 1): ?>
<nav id="login-news-nav">
    <? foreach ($news_entries as $entry): ?>
        <button class="login-news-nav" id="login-news-nav-<?= $entry['news_id'] ?>">
        </button>
    <? endforeach; ?>
</nav>
<? endif; ?>

<? if (!empty($news_entries)) : ?>
<script>
    const entries = <?= json_encode(array_map(fn($entry) => $entry->toArray('news_id'), $news_entries)) ?>;
    let currentEntryId = entries[0]['news_id'];
    const first = document.getElementById("login-news-<?= htmlReady($news_entries[0]['news_id']) ?>");
    first.classList.remove('hidden');
    const NewsNav = document.getElementById('login-news-nav');
    if (NewsNav) {
        const firstTeaserBullet = document.getElementById('login-news-nav-<?= htmlReady($news_entries[0]['news_id']) ?>');
        firstTeaserBullet.classList.add('active-news-bullet');
        entries.forEach(entry => {
            document.getElementById(`login-news-nav-${entry.news_id}`).addEventListener('click', e => {
                setTeaserById(entry.news_id);
            });
        });
    }

function setTeaserById(id) {
    document.querySelectorAll('.login-news-nav').forEach(el => {
        el.classList.remove("active-news-bullet");
    });

    currentEntryId = id;

    document.querySelectorAll('.login-news').forEach(el => {
        el.classList.add('hidden');
    });

    const news = document.getElementById(`login-news-${id}`);
    news.classList.remove('hidden');

    const teaserBullet = document.getElementById(`login-news-nav-${id}`);
    teaserBullet.classList.add('active-news-bullet');
}
</script>
<? endif ?>
