/**
 * core/app.js
 * ---------------------------------------------------------
 * The ONLY generator logic that is not theme-specific. This file
 * must never contain a theme id, a field name, or any markup that
 * belongs to one particular theme. It knows exactly two things:
 *   1. how to read themes/registry.json and render selectable cards
 *   2. how to call mount()/unmount() on whichever theme module the
 *      person picked, and (generically) render a live-preview mockup
 *      from whatever getPreviewSummary() that module returns.
 *
 * Adding a new theme = adding a themes/<id>/ folder + a registry
 * entry. This file does not change.
 * ---------------------------------------------------------
 */

const REGISTRY_URL = 'themes/registry.json';
const WEBHOOK_URL = 'backend/generate.php';

const els = {
    topbar: document.getElementById('gTopbar'),
    changeThemeBtn: document.getElementById('gChangeThemeBtn'),
    myPagesBtn: document.getElementById('gMyPagesBtn'),
    themeSub: document.getElementById('gThemeSub'),
    selectScreen: document.getElementById('gSelectScreen'),
    themeGrid: document.getElementById('gThemeGrid'),
    workspace: document.getElementById('gWorkspace'),
    formMount: document.getElementById('gFormMount'),
    previewMock: document.getElementById('gPreviewMock'),
    pagesScreen: document.getElementById('gPagesScreen'),
    pagesMount: document.getElementById('gPagesMount'),
};

let activeController = null;
let activeTheme = null;
let pagesController = null;

init();

async function init() {
    els.changeThemeBtn.addEventListener('click', showThemeSelection);
    els.myPagesBtn.addEventListener('click', showMyPages);

    let themes = [];
    try {
        const res = await fetch(REGISTRY_URL);
        themes = await res.json();
    } catch (err) {
        els.themeGrid.innerHTML = '<div class="g-empty">Could not load the theme list. Please refresh the page.</div>';
        return;
    }

    if (!Array.isArray(themes) || themes.length === 0) {
        els.themeGrid.innerHTML = '<div class="g-empty">No themes are available yet.</div>';
        return;
    }

    renderThemeGrid(themes);
}

function renderThemeGrid(themes) {
    els.themeGrid.innerHTML = '';
    themes.forEach(theme => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'g-theme-card';
        card.innerHTML = `
            <div class="g-card-body">
                <h3>${escapeHtml(theme.name)}</h3>
                <p class="g-tagline">${escapeHtml(theme.tagline || '')}</p>
                <div class="g-sections">
                    ${(theme.sections || []).slice(0, 5).map(s => `<span>${escapeHtml(s)}</span>`).join('')}
                </div>
                <div class="g-select-btn">Use this theme</div>
            </div>
            <img class="g-thumb" src="themes/${theme.id}/${theme.thumbnail || 'thumbnail.svg'}" alt="${escapeHtml(theme.name)} preview" loading="lazy" />
        `;
        card.addEventListener('click', () => selectTheme(theme));
        els.themeGrid.appendChild(card);
    });
}

async function selectTheme(theme) {
    activeTheme = theme;

    unmountPages();
    els.pagesScreen.classList.add('g-hidden');
    els.selectScreen.classList.add('g-hidden');
    els.workspace.classList.remove('g-hidden');
    els.topbar.classList.add('g-has-theme');
    els.themeSub.textContent = theme.name;

    els.formMount.innerHTML = '<div class="g-loading">Loading theme…</div>';
    renderPreviewEmpty();

    try {
        const mod = await import(`../themes/${theme.id}/form.js`);
        els.formMount.innerHTML = '';
        activeController = await mod.mount(els.formMount, {
            themeId: theme.id,
            webhookUrl: WEBHOOK_URL,
        });
    } catch (err) {
        els.formMount.innerHTML = '<div class="g-empty">This theme failed to load. Please choose a different theme.</div>';
        console.error(err);
        return;
    }

    // Generic live preview: only wired up if the theme module implements it.
    if (activeController && typeof activeController.getPreviewSummary === 'function') {
        refreshPreview();
        const debounced = debounce(refreshPreview, 200);
        els.formMount.addEventListener('input', debounced);
        els.formMount.addEventListener('change', debounced);
    } else {
        renderPreviewEmpty();
    }
}

function showThemeSelection() {
    if (activeController && typeof activeController.unmount === 'function') {
        activeController.unmount();
    }
    activeController = null;
    activeTheme = null;

    unmountPages();
    els.pagesScreen.classList.add('g-hidden');
    els.workspace.classList.add('g-hidden');
    els.selectScreen.classList.remove('g-hidden');
    els.topbar.classList.remove('g-has-theme');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function showMyPages() {
    if (activeController && typeof activeController.unmount === 'function') {
        activeController.unmount();
    }
    activeController = null;
    activeTheme = null;

    els.selectScreen.classList.add('g-hidden');
    els.workspace.classList.add('g-hidden');
    els.topbar.classList.remove('g-has-theme');
    els.pagesScreen.classList.remove('g-hidden');

    unmountPages();
    els.pagesMount.innerHTML = '<div class="g-loading">Loading&hellip;</div>';
    try {
        const mod = await import('./pages.js');
        pagesController = mod.mount(els.pagesMount, { apiBase: WEBHOOK_URL.replace('generate.php', 'pages.php') });
    } catch (err) {
        els.pagesMount.innerHTML = '<div class="g-empty">Could not load your landing pages. Please try again.</div>';
        console.error(err);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function unmountPages() {
    if (pagesController && typeof pagesController.unmount === 'function') {
        pagesController.unmount();
    }
    pagesController = null;
}

/* ---------- Generic live-preview mockup (theme-agnostic renderer) ---------- */
function refreshPreview() {
    if (!activeController || typeof activeController.getPreviewSummary !== 'function') return;
    let summary;
    try {
        summary = activeController.getPreviewSummary();
    } catch (err) {
        return;
    }
    renderPreviewFromSummary(summary);
}

function renderPreviewEmpty() {
    els.previewMock.innerHTML = '<div class="pm-empty" style="padding:16px;">Select a theme and start filling in the form to see a live preview here.</div>';
}

/**
 * summary shape (every theme's getPreviewSummary() returns this same
 * shape, however different its own fields/sections are internally):
 * {
 *   badge: string,           // small pill above the title (e.g. status/tagline)
 *   title: string,           // main headline
 *   subtitle: string,        // supporting line
 *   heroImage: string|null,  // data URL, optional
 *   colors: { primary, secondary },
 *   rows: [{ label, value }],   // key content fields, shown as a compact list
 *   chips: string[],            // e.g. included sections / highlights
 * }
 */
function renderPreviewFromSummary(summary) {
    const s = Object.assign({
        badge: '', title: '', subtitle: '', heroImage: null,
        colors: { primary: '#2b2f77', secondary: '#1c1e21' },
        rows: [], chips: [],
    }, summary || {});

    const heroStyle = s.heroImage
        ? `background-image:url('${s.heroImage}');`
        : `background-color:${s.colors.secondary};`;

    const rowsHtml = s.rows.filter(r => r.value).length
        ? s.rows.filter(r => r.value).map(r => `<div class="pm-row"><span class="pm-k">${escapeHtml(r.label)}</span><span>${escapeHtml(String(r.value))}</span></div>`).join('')
        : '<div class="pm-empty">Fill in the form to see your content here.</div>';

    const chipsHtml = s.chips.length
        ? `<div class="pm-chips">${s.chips.map(c => `<span>${escapeHtml(c)}</span>`).join('')}</div>`
        : '';

    els.previewMock.innerHTML = `
        <div class="pm-nav" style="background:${s.colors.secondary};">
            <span>${escapeHtml(activeTheme ? activeTheme.name : '')}</span>
            <span style="opacity:.7;">MENU</span>
        </div>
        <div class="pm-hero" style="${heroStyle}">
            <div class="pm-overlay"></div>
            <div class="pm-hero-inner">
                ${s.badge ? `<span class="pm-badge" style="background:${s.colors.primary};">${escapeHtml(s.badge)}</span>` : ''}
                <p class="pm-title">${escapeHtml(s.title || 'Your project name')}</p>
                <p class="pm-sub">${escapeHtml(s.subtitle || '')}</p>
            </div>
        </div>
        <div class="pm-body">
            ${rowsHtml}
            ${chipsHtml}
        </div>
    `;
}

function debounce(fn, wait) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}
