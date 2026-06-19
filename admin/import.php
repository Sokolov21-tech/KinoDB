<?php
$adminTitle = 'Импорт из Кинопоиска';
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-topbar">
    <h1 class="admin-page-title">Импорт из Кинопоиска</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--gap-xl);">


    <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);">
        <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--cream);margin-bottom:var(--gap-lg);">Импорт по Kinopoisk ID</h3>
        <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:var(--gap-lg);">
            Введите ID фильма на Кинопоиске (например, 326 для «Побега из Шоушенка»).
            ID виден в адресе страницы фильма: kinopoisk.ru/film/<b>326</b>/
        </p>
        <form id="tmdb-import-form">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Kinopoisk ID</label>
                <input type="number" name="kp_id" class="form-control" placeholder="326" required>
            </div>
            <button type="submit" class="btn btn--primary">Импортировать</button>
        </form>
        <div id="import-result"></div>
    </div>


    <div style="background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);">
        <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--cream);margin-bottom:var(--gap-lg);">Поиск в базе Кинопоиска</h3>
        <p style="color:var(--text-muted);font-size:0.88rem;margin-bottom:var(--gap-lg);">
            Найдите фильм по названию и импортируйте в один клик.
        </p>
        <div class="form-group">
            <label class="form-label">Название фильма</label>
            <input type="text" id="tmdb-search-input" class="form-control" placeholder="Матрица...">
        </div>
        <div id="tmdb-search-results" style="margin-top:var(--gap);"></div>
    </div>

</div>

<div style="margin-top:var(--gap-xl);background:var(--noir-2);border:1px solid var(--border);border-radius:var(--r-lg);padding:var(--gap-xl);">
    <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--cream);margin-bottom:var(--gap);">Настройка API</h3>
    <p style="color:var(--text-muted);font-size:0.88rem;">
        Токен ПоискКино: <code style="color:var(--amber);"><?= KP_API_KEY ? '✓ Настроен' : '⚠ Не настроен' ?></code><br>
        Токен задаётся в <code>includes/config.php</code> — константа <code>KP_API_KEY</code>.<br>
        Документация API: <a href="https://poiskkino.dev/documentation" target="_blank" style="color:var(--amber);">poiskkino.dev ↗</a>
    </p>
</div>

<script>

const APP_BASE = <?= json_encode(appPublicPath()) ?>;
let searchDebounce;
document.getElementById('tmdb-search-input')?.addEventListener('input', function() {
    clearTimeout(searchDebounce);
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('tmdb-search-results').innerHTML = ''; return; }

    searchDebounce = setTimeout(async () => {
        const res  = await fetch(`${APP_BASE}/api/import-movie.php?action=search&q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!data.results) return;

        const html = data.results.map(m => `
            <div style="display:flex;align-items:center;gap:var(--gap);padding:0.6rem;border-bottom:1px solid var(--border);cursor:pointer;"
                 onclick="importById(${m.id})">
                ${m.poster ? `<img src="${escHtml(m.poster)}" style="width:40px;height:60px;object-fit:cover;border-radius:4px;" loading="lazy">` : '<div style="width:40px;height:60px;background:var(--noir-4);border-radius:4px;"></div>'}
                <div>
                    <div style="color:var(--cream);font-size:0.9rem;">${escHtml(m.title)}</div>
                    <div style="color:var(--text-muted);font-size:0.78rem;">${m.year || '—'}${m.rating ? ' · ★ ' + Number(m.rating).toFixed(1) : ''} · ID: ${m.id}</div>
                </div>
                <button class="btn btn--ghost btn--sm" style="margin-left:auto;">Импорт</button>
            </div>
        `).join('');
        document.getElementById('tmdb-search-results').innerHTML = `<div style="background:var(--noir-3);border:1px solid var(--border);border-radius:var(--r);">${html}</div>`;
    }, 400);
});

async function importById(kpId) {
    const csrf = document.querySelector('input[name="csrf_token"]').value;
    const res  = await fetch(`${APP_BASE}/api/import-movie.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-Token':csrf,'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({ kp_id: kpId }),
    });
    const data = await res.json();
    if (data.ok) {
        alert('Импортировано: ' + data.title);
        window.location.href = `${APP_BASE}/admin/movies.php`;
    } else {
        alert('Ошибка: ' + (data.error || 'Неизвестная ошибка'));
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
