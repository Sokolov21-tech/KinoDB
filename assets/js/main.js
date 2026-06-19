



const BASE = window.APP_URL || '';




function onGSAPReady(cb) {
  if (typeof gsap !== 'undefined') {
    cb();
    return;
  }
  window.addEventListener('load', () => {
    if (typeof gsap !== 'undefined') cb();
  }, { once: true });
}




(function initHeader() {
  const header = document.getElementById('site-header');
  if (!header) return;
  const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 20);
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
})();




(function initMobileNav() {
  const burger = document.getElementById('burger-btn');
  const nav    = document.getElementById('mobile-nav');
  if (!burger || !nav) return;

  burger.addEventListener('click', () => {
    const open = burger.classList.toggle('open');
    burger.setAttribute('aria-expanded', open);
    nav.classList.toggle('open', open);
    nav.setAttribute('aria-hidden', !open);
  });
})();




(function initUserDropdown() {
  document.querySelectorAll('.user-dropdown').forEach(el => {
    const trigger = el.querySelector('.user-trigger');
    if (!trigger) return;
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = el.classList.toggle('open');
      trigger.setAttribute('aria-expanded', open);
    });
  });
  document.addEventListener('click', () => {
    document.querySelectorAll('.user-dropdown.open').forEach(el => {
      el.classList.remove('open');
      el.querySelector('.user-trigger')?.setAttribute('aria-expanded', 'false');
    });
  });
})();




(function initFlash() {
  const container = document.getElementById('flash-container');
  if (!container) return;

  container.querySelectorAll('.flash').forEach(flash => {
    const close = flash.querySelector('.flash-close');
    close?.addEventListener('click', () => dismissFlash(flash));
    setTimeout(() => dismissFlash(flash), 5000);
  });

  function dismissFlash(el) {
    el.style.transition = 'opacity 0.3s, transform 0.3s';
    el.style.opacity = '0';
    el.style.transform = 'translateX(120%)';
    setTimeout(() => el.remove(), 300);
  }
})();




(function initHero() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots   = document.querySelectorAll('.hero-dot');
  if (slides.length < 2) return;

  let current = 0, timer;

  function goTo(index) {
    slides[current].classList.remove('active');
    dots[current]?.classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current]?.classList.add('active');
  }

  function startTimer() {
    clearInterval(timer);
    timer = setInterval(() => goTo(current + 1), 6000);
  }

  dots.forEach((dot, i) => dot.addEventListener('click', () => { goTo(i); startTimer(); }));

  
  onGSAPReady(() => {
    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });
    const content = document.querySelector('.hero-content');
    if (!content) return;
    tl.from('.hero-label',       { y: 20, opacity: 0, duration: 0.6 })
      .from('.hero-title',       { y: 40, opacity: 0, duration: 0.8 }, '-=0.3')
      .from('.hero-meta',        { y: 20, opacity: 0, duration: 0.6 }, '-=0.4')
      .from('.hero-description', { y: 20, opacity: 0, duration: 0.6 }, '-=0.4')
      .from('.hero-actions',     { y: 20, opacity: 0, duration: 0.6 }, '-=0.3');
  });

  startTimer();
})();




(function initScrollReveal() {
  const targets = document.querySelectorAll('.reveal');
  if (!targets.length) return;

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

    targets.forEach(el => io.observe(el));
  } else {
    targets.forEach(el => el.classList.add('visible'));
  }
})();




(function initSearchSuggestions() {
  const inputs = document.querySelectorAll('[data-search-suggest], #header-search-input');
  if (!inputs.length) return;

  inputs.forEach(input => {
    const targetId = input.dataset.suggestionsTarget;
    const box = (targetId && document.getElementById(targetId))
      || input.closest('.search-field, .header-search')?.querySelector('.search-suggestions');
    if (!box) return;

    let debounce;

    const load = () => {
      clearTimeout(debounce);
      const q = input.value.trim();
      if (q.length < 2) {
        hideSuggestions(box);
        return;
      }

      debounce = setTimeout(async () => {
        try {
          const res = await fetch(`${BASE}/api/search.php?q=${encodeURIComponent(q)}&limit=6`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
          });
          const data = await res.json();
          renderSuggestions(box, data.movies || []);
        } catch {
          hideSuggestions(box);
        }
      }, 220);
    };

    input.addEventListener('input', load);
    input.addEventListener('focus', load);
  });

  function renderSuggestions(box, movies) {
    if (!movies.length) {
      hideSuggestions(box);
      return;
    }

    box.innerHTML = movies.map(m => `
      <a class="suggestion-item" href="${BASE}/movie.php?id=${m.id}">
        <img class="suggestion-poster" src="${escHtml(m.poster_url || '')}" alt="${escHtml(m.title || '')}"
             onerror="this.style.display='none'">
        <div class="suggestion-info">
          <div class="suggestion-title">${escHtml(m.title || '')}</div>
          <div class="suggestion-year">${escHtml(m.release_year || '')}</div>
        </div>
      </a>
    `).join('');
    box.classList.add('active');
  }

  function hideSuggestions(box) {
    box.classList.remove('active');
    box.innerHTML = '';
  }

  document.addEventListener('click', e => {
    document.querySelectorAll('.search-suggestions.active').forEach(box => {
      const wrap = box.closest('.search-field, .header-search');
      if (wrap && !wrap.contains(e.target)) hideSuggestions(box);
    });
  });
})();




document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-action]');
  if (!btn) return;
  e.preventDefault();

  const action  = btn.dataset.action;
  const movieId = btn.dataset.movieId || '';

  if (action === 'modal-close') {
    closeCollectionModal();
    return;
  }

  if (action === 'watchlist') {
    if (!movieId) return;
    const type = btn.dataset.type || 'want';
    const res  = await apiPost(BASE + '/api/watchlist.php', { movie_id: movieId, type });
    if (res.ok) {
      updateWatchlistButtons(movieId, res.type || type, !!res.added);
      showToast(res.message || 'Сохранено', 'success');
    } else if (res.login) {
      window.location.href = BASE + '/login.php';
    } else {
      showToast(res.error || 'Ошибка', 'error');
    }
  }

  if (action === 'rate') {
    if (!movieId) return;
    const rating = btn.dataset.rating;
    const res    = await apiPost(BASE + '/api/rate.php', { movie_id: movieId, rating });
    if (res.ok) {
      document.querySelectorAll('.star-btn').forEach((s, i) => {
        s.classList.toggle('active', (i + 1) <= parseInt(rating));
      });
      const avgEl = document.querySelector('[data-avg-rating]');
      if (avgEl && res.avg_rating) avgEl.textContent = res.avg_rating;
      showToast('Оценка сохранена', 'success');
    } else if (res.login) {
      window.location.href = BASE + '/login.php';
    }
  }

  if (action === 'list-picker') {
    if (!movieId) return;
    openCollectionPicker(movieId);
  }

  if (action === 'list-toggle') {
    if (!movieId) return;
    const listId = btn.dataset.listId;
    const hasMovie = btn.dataset.hasMovie === '1';
    const res = await apiPost(BASE + '/api/lists.php', {
      action: hasMovie ? 'remove' : 'add',
      list_id: listId,
      movie_id: movieId,
    });

    if (res.ok) {
      const nextHasMovie = !hasMovie;
      btn.dataset.hasMovie = nextHasMovie ? '1' : '0';
      btn.classList.toggle('active', nextHasMovie);
      btn.querySelector('.collection-list__state').textContent = nextHasMovie ? 'Добавлено' : 'Добавить';
      showToast(res.message || 'Коллекция обновлена', 'success');
    } else if (res.login) {
      window.location.href = BASE + '/login.php';
    } else {
      showToast(res.error || 'Ошибка', 'error');
    }
  }

  if (action === 'comment-like') {
    const commentId = btn.dataset.commentId;
    const res = await apiPost(BASE + '/api/comment.php', { action: 'like', comment_id: commentId });
    if (res.ok) {
      btn.classList.toggle('liked', res.liked);
      const countEl = btn.querySelector('.like-count');
      if (countEl) countEl.textContent = res.likes;
    } else if (res.login) {
      window.location.href = BASE + '/login.php';
    } else {
      showToast(res.error || 'Ошибка', 'error');
    }
  }

  if (action === 'comment-delete') {
    const commentId = btn.dataset.commentId;
    if (!commentId || !confirm('Удалить комментарий?')) return;
    const res = await apiPost(BASE + '/api/comment.php', { action: 'delete', comment_id: commentId });
    if (res.ok) {
      btn.closest('.comment')?.remove();
      showToast('Комментарий удалён', 'success');
    } else if (res.login) {
      window.location.href = BASE + '/login.php';
    } else {
      showToast(res.error || 'Ошибка удаления', 'error');
    }
  }

  if (action === 'comment-reply-toggle') {
    const form = btn.closest('.comment')?.querySelector('.reply-form');
    form?.classList.toggle('open');
    form?.querySelector('textarea')?.focus();
  }
});

function updateWatchlistButtons(movieId, activeType, added) {
  document.querySelectorAll(`[data-action="watchlist"][data-movie-id="${movieId}"]`).forEach(button => {
    const active = added && button.dataset.type === activeType;
    button.classList.toggle('active', active);

    if (button.classList.contains('btn')) {
      button.classList.toggle('btn--primary', active);
      button.classList.toggle('btn--ghost', !active);
    }

    const label = active ? button.dataset.activeLabel : button.dataset.defaultLabel;
    if (label) button.textContent = label;
  });
}

function ensureCollectionModal() {
  let modal = document.getElementById('collection-modal');
  if (modal) return modal;

  document.body.insertAdjacentHTML('beforeend', `
    <div class="modal-backdrop" id="collection-modal" aria-hidden="true">
      <div class="collection-modal" role="dialog" aria-modal="true" aria-labelledby="collection-modal-title">
        <div class="collection-modal__header">
          <div class="collection-modal__title" id="collection-modal-title">Добавить в коллекцию</div>
          <button class="modal-close" type="button" data-action="modal-close" aria-label="Закрыть">×</button>
        </div>
        <div class="collection-modal__body"></div>
      </div>
    </div>
  `);

  modal = document.getElementById('collection-modal');
  modal.addEventListener('click', e => {
    if (e.target === modal) closeCollectionModal();
  });
  return modal;
}

async function openCollectionPicker(movieId) {
  const modal = ensureCollectionModal();
  const body = modal.querySelector('.collection-modal__body');
  body.innerHTML = '<p class="text-muted">Загрузка коллекций...</p>';
  modal.classList.add('active');
  modal.setAttribute('aria-hidden', 'false');

  const res = await apiPost(BASE + '/api/lists.php', { action: 'get_user_lists', movie_id: movieId });
  if (res.login) {
    window.location.href = BASE + '/login.php';
    return;
  }
  if (!res.ok) {
    body.innerHTML = `<p class="text-red">${escHtml(res.error || 'Не удалось загрузить коллекции')}</p>`;
    return;
  }

  const lists = Array.isArray(res.lists) ? res.lists : [];
  if (!lists.length) {
    body.innerHTML = `
      <div class="empty-state" style="padding:var(--gap-xl) var(--gap);">
        <div class="empty-state-title">Коллекций пока нет</div>
        <p style="margin:var(--gap) 0 var(--gap-lg);">Создайте коллекцию, затем добавьте в неё фильм.</p>
        <a href="${BASE}/lists.php" class="btn btn--primary">Создать коллекцию</a>
      </div>
    `;
    return;
  }

  body.innerHTML = `
    <div class="collection-list">
      ${lists.map(list => {
        const hasMovie = Number(list.has_movie) > 0;
        return `
          <button class="collection-list__btn ${hasMovie ? 'active' : ''}" type="button"
                  data-action="list-toggle" data-list-id="${list.id}" data-movie-id="${movieId}"
                  data-has-movie="${hasMovie ? '1' : '0'}">
            <span class="collection-list__title">${escHtml(list.title)}</span>
            <span class="collection-list__state">${hasMovie ? 'Добавлено' : 'Добавить'}</span>
          </button>
        `;
      }).join('')}
    </div>
  `;
}

function closeCollectionModal() {
  const modal = document.getElementById('collection-modal');
  if (!modal) return;
  modal.classList.remove('active');
  modal.setAttribute('aria-hidden', 'true');
}




(function initStarRating() {
  const container = document.querySelector('.rating-stars');
  if (!container) return;
  const stars = container.querySelectorAll('.star-btn');

  stars.forEach((star, i) => {
    star.addEventListener('mouseover', () => {
      stars.forEach((s, j) => s.style.color = j <= i ? 'var(--amber)' : '');
    });
    star.addEventListener('mouseleave', () => {
      stars.forEach(s => s.style.color = '');
    });
  });
})();




(function initTabs() {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.tab-btn[data-tab-target]');
    if (!btn) return;

    const tabs = btn.closest('.tabs');
    const panel = document.getElementById(btn.dataset.tabTarget);
    if (!tabs || !panel) return;

    e.preventDefault();

    const group = tabs.dataset.tabs;
    const btns = tabs.querySelectorAll('.tab-btn');
    const contents = group
      ? document.querySelectorAll(`[data-tab-panel="${group}"]`)
      : (tabs.closest('section, main')?.querySelectorAll('.tab-content') || []);

    btns.forEach(b => {
      const active = b === btn;
      b.classList.toggle('active', active);
      b.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    contents.forEach(c => c.classList.remove('active'));
    panel.classList.add('active');
  });
})();




(function initCommentForm() {
  const form = document.getElementById('comment-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form));
    const res  = await apiPost(BASE + '/api/comment.php', { action: 'post', ...data });

    if (res.ok) {
      form.reset();
      const list = document.getElementById('comments-list');
      if (list && res.html) {
        list.querySelector('.empty-state')?.remove();
        list.insertAdjacentHTML('afterbegin', res.html);
        list.firstElementChild?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    } else {
      showToast(res.error || 'Ошибка', 'error');
    }
  });

  
  document.addEventListener('submit', async (e) => {
    const replyDiv = e.target.closest('.reply-form');
    if (!replyDiv) return;
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res  = await apiPost(BASE + '/api/comment.php', { action: 'reply', ...data });

    if (res.ok) {
      e.target.reset();
      replyDiv.classList.remove('open');
      const commentEl = replyDiv.closest('.comment');
      let repliesEl = commentEl
        ? Array.from(commentEl.children).find(el => el.classList?.contains('comment-replies'))
        : null;
      if (!repliesEl) {
        repliesEl = document.createElement('div');
        repliesEl.className = 'comment-replies';
        commentEl?.appendChild(repliesEl);
      }
      if (res.html) repliesEl.insertAdjacentHTML('beforeend', res.html);
    } else {
      showToast(res.error || 'Ошибка', 'error');
    }
  });
})();




(function initSearchFilters() {
  const form = document.getElementById('search-form');
  if (!form) return;

  form.querySelectorAll('select, input[type="range"]').forEach(el => {
    el.addEventListener('change', () => {
      form.querySelector('[name="page"]')?.setAttribute('value', '1');
      form.submit();
    });
  });

  
  document.querySelectorAll('.genre-filter-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const genreInput = form.querySelector('[name="genre"]');
      const slug = btn.dataset.slug;
      if (btn.classList.toggle('active')) {
        genreInput.value = slug;
        document.querySelectorAll('.genre-filter-btn').forEach(b => {
          if (b !== btn) b.classList.remove('active');
        });
      } else {
        genreInput.value = '';
      }
      form.submit();
    });
  });
})();




document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', (e) => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});




onGSAPReady(() => {
  if (typeof ScrollTrigger === 'undefined') return;
  gsap.registerPlugin(ScrollTrigger);

  gsap.utils.toArray('.movie-card').forEach((card, i) => {
    gsap.from(card, {
      scrollTrigger: { trigger: card, start: 'top 90%', once: true },
      y: 40, opacity: 0, duration: 0.5,
      delay: (i % 6) * 0.06,
      ease: 'power2.out',
    });
  });

  gsap.utils.toArray('.section-title').forEach(el => {
    gsap.from(el, {
      scrollTrigger: { trigger: el, start: 'top 88%', once: true },
      x: -30, opacity: 0, duration: 0.7, ease: 'power3.out',
    });
  });

  gsap.utils.toArray('.top-item').forEach((item, i) => {
    gsap.from(item, {
      scrollTrigger: { trigger: item, start: 'top 92%', once: true },
      x: -20, opacity: 0, duration: 0.4,
      delay: i * 0.05, ease: 'power2.out',
    });
  });

  
  document.querySelectorAll('.rating-bar-fill').forEach(bar => {
    const target = bar.dataset.width || '0%';
    bar.style.width = '0%';
    ScrollTrigger.create({
      trigger: bar,
      start: 'top 90%',
      once: true,
      onEnter: () => gsap.to(bar, { width: target, duration: 1, ease: 'power2.out' }),
    });
  });
});




(function init2FAInput() {
  const input = document.getElementById('twofa-code');
  if (!input) return;
  input.addEventListener('input', () => {
    if (input.value.replace(/\D/g, '').length === 6) {
      input.closest('form')?.submit();
    }
  });
})();




document.querySelectorAll('[data-preview-target]').forEach(input => {
  const target = document.getElementById(input.dataset.previewTarget);
  if (!target) return;
  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      if (target.tagName === 'IMG') {
        target.src = e.target.result;
      } else {
        target.innerHTML = `<img src="${e.target.result}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">`;
      }
    };
    reader.readAsDataURL(file);
  });
});




(function initTmdbImport() {
  const form = document.getElementById('tmdb-import-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn    = form.querySelector('[type="submit"]');
    const result = document.getElementById('import-result');
    btn.disabled = true;
    btn.textContent = 'Импорт...';

    const res = await apiPost(BASE + '/api/import-movie.php', Object.fromEntries(new FormData(form)));

    btn.disabled = false;
    btn.textContent = 'Импортировать';

    if (res.ok) {
      showToast('Фильм импортирован: ' + res.title, 'success');
      if (result) result.innerHTML = `<a href="${BASE}/admin/movies.php" class="btn btn--ghost btn--sm mt">Перейти к фильмам</a>`;
    } else {
      showToast(res.error || 'Ошибка импорта', 'error');
    }
  });
})();




function escHtml(str) {
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

async function apiPost(url, data) {
  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res  = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data),
    });
    return await res.json();
  } catch {
    return { ok: false, error: 'Ошибка сети' };
  }
}

function showToast(message, type = 'info') {
  let container = document.getElementById('flash-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'flash-container';
    container.className = 'flash-container';
    document.body.appendChild(container);
  }
  const el = document.createElement('div');
  el.className = `flash flash--${type}`;
  el.innerHTML = `<span>${escHtml(message)}</span><button class="flash-close" aria-label="Закрыть">×</button>`;
  el.querySelector('.flash-close').addEventListener('click', () => el.remove());
  container.appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transform = 'translateX(120%)';
    el.style.transition = '0.3s';
    setTimeout(() => el.remove(), 300);
  }, 4000);
}
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-show-pw');
  if (!btn) return;
  const wrap = btn.closest('.pw-wrap');
  const input = wrap?.querySelector('input[type="password"], input[type="text"]');
  if (!input) return;
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.textContent = show ? 'Скрыть' : 'Показать';
});
(function setupCsrf() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) return;
  const field = document.querySelector('input[name="csrf_token"]');
  if (field) {
    const m = document.createElement('meta');
    m.name = 'csrf-token';
    m.content = field.value;
    document.head.appendChild(m);
  }
})();
