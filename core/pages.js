/**
 * core/pages.js
 * ---------------------------------------------------------
 * "My Landing Pages" screen: lists every previously generated page
 * (any theme) and lets you edit it using the SAME KIND of fields and
 * images as that theme's generate form — instead of raw code.
 *
 * Saving calls backend/pages.php?action=save with the changed field
 * values (and any replacement image files). The backend finds each
 * field's old rendered text inside the already-generated index.html
 * and swaps in the new value, and writes replacement images to the
 * exact same path the original renderer used — it never re-runs a
 * theme's generator, so nothing is regenerated from scratch.
 *
 * Self-contained and reusable: this file knows nothing about any one
 * theme's specific fields. It builds its form generically from
 * whatever `fields` and `images` backend/pages.php returns for a page:
 *   - images.single / images.repeated -> image controls with a current
 *     thumbnail + a file input to replace it (repeated items also get
 *     their paired label text input, e.g. floor plan captions)
 *   - a field whose value is a JSON array of strings  -> an editable list
 *   - a field whose value is a JSON array of objects   -> editable rows
 *   - anything else                                    -> a text input
 *     (or textarea for long values)
 *
 * Usage (mirrors the theme form.js contract):
 *   const controller = mount(container, { apiBase: 'backend/pages.php' });
 *   controller.unmount();
 * ---------------------------------------------------------
 */

// Fields that exist for internal bookkeeping only, or that control
// structural page behavior (which sections render, lightbox vs modal,
// etc.) rather than plain text content — editing those safely needs
// more than a find/replace patch, so they're left out of this form.
const SKIP_FIELD_KEYS = new Set(['themeId', 'refNumber']);
const SKIP_FIELD_PATTERNS = [/^include[A-Z]/, /Lightbox$/, /^crmOption$/];
const LONG_TEXT_THRESHOLD = 140;

export function mount(container, opts = {}) {
    const apiBase = opts.apiBase || 'backend/pages.php';

    renderList();

    function unmount() {
        container.innerHTML = '';
    }

    async function renderList() {
        container.innerHTML = '<div class="g-loading">Loading your landing pages&hellip;</div>';
        let pages = [];
        try {
            const res = await fetch(`${apiBase}?action=list`);
            const data = await res.json();
            pages = Array.isArray(data.pages) ? data.pages : [];
        } catch (err) {
            container.innerHTML = '<div class="g-empty">Could not load your landing pages. Please try again.</div>';
            return;
        }

        if (pages.length === 0) {
            container.innerHTML = '<div class="g-empty">No landing pages generated yet. Create one first, then come back here to edit it.</div>';
            return;
        }

        container.innerHTML = `
            <div class="gp-list">
                ${pages.map(p => `
                    <div class="gp-row" data-id="${escapeAttr(p.id)}">
                        <div class="gp-row-main">
                            <div class="gp-name">${escapeHtml(p.projectName || p.id)}</div>
                            <div class="gp-meta">${escapeHtml(p.themeId || '')} &middot; updated ${formatDate(p.updatedAt)}</div>
                        </div>
                        <div class="gp-row-actions">
                            ${p.previewLink ? `<a class="gp-btn gp-btn-ghost" href="${escapeAttr(p.previewLink)}" target="_blank" rel="noopener">Preview</a>` : ''}
                            <button type="button" class="gp-btn gp-btn-primary" data-action="edit">Edit</button>
                            <button type="button" class="gp-btn gp-btn-danger" data-action="delete">Delete</button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        container.querySelectorAll('.gp-row').forEach(row => {
            const id = row.dataset.id;
            row.querySelector('[data-action="edit"]').addEventListener('click', () => renderEditor(id));
            row.querySelector('[data-action="delete"]').addEventListener('click', () => handleDelete(id, row));
        });
    }

    async function handleDelete(id, row) {
        const name = row.querySelector('.gp-name')?.textContent || id;
        if (!window.confirm(`Delete "${name}"? This cannot be undone.`)) return;

        const deleteBtn = row.querySelector('[data-action="delete"]');
        deleteBtn.disabled = true;
        deleteBtn.textContent = 'Deleting...';

        try {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            const res = await fetch(apiBase, { method: 'POST', body: fd });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || 'Delete failed');
            row.remove();
            if (!container.querySelector('.gp-row')) {
                container.innerHTML = '<div class="g-empty">No landing pages generated yet. Create one first, then come back here to edit it.</div>';
            }
        } catch (err) {
            deleteBtn.disabled = false;
            deleteBtn.textContent = 'Delete';
            window.alert('Could not delete this page. Please try again.');
        }
    }

    async function renderEditor(id) {
        container.innerHTML = '<div class="g-loading">Loading page fields&hellip;</div>';
        let page;
        try {
            const res = await fetch(`${apiBase}?action=get&id=${encodeURIComponent(id)}`);
            if (!res.ok) throw new Error('not found');
            page = await res.json();
        } catch (err) {
            container.innerHTML = '<div class="g-empty">Could not load this page for editing.</div>';
            return;
        }

        const fields = page.fields || {};
        const images = page.images || { single: [], repeated: [] };

        // Label fields (e.g. floorplanLabel) are edited inline next to
        // their image, not as a separate generic field row.
        const labelKeysUsedByImages = new Set(
            images.repeated.filter(r => r.labelField).map(r => r.labelField)
        );
        const editableKeys = Object.keys(fields).filter(
            key => isEditableKey(key) && !labelKeysUsedByImages.has(key)
        );

        const hasImages = images.single.length > 0 || images.repeated.length > 0;

        container.innerHTML = `
            <div class="gp-editor">
                <div class="gp-editor-head">
                    <button type="button" class="gp-btn gp-btn-ghost" data-action="back">&larr; Back to my pages</button>
                    <div class="gp-editor-title">${escapeHtml(page.projectName || page.id)}</div>
                    ${page.previewLink ? `<a class="gp-btn gp-btn-ghost" href="${escapeAttr(page.previewLink)}" target="_blank" rel="noopener">View live</a>` : ''}
                </div>
                <p class="gp-editor-hint">Update the fields and images below and save. This updates the existing page in place — it will not be regenerated.</p>

                ${hasImages ? `
                    <div class="gp-section">
                        <h3 class="gp-section-title">Images</h3>
                        <div class="gp-field-form">
                            ${images.single.map(img => renderSingleImageControl(img)).join('')}
                            ${images.repeated.map(rep => renderRepeatedImageControl(rep)).join('')}
                        </div>
                    </div>
                ` : ''}

                ${editableKeys.length > 0 ? `
                    <div class="gp-section">
                        <h3 class="gp-section-title">Content</h3>
                        <div class="gp-field-form">
                            ${editableKeys.map(key => renderFieldControl(key, fields[key])).join('')}
                        </div>
                    </div>
                ` : ''}

                ${(!hasImages && editableKeys.length === 0) ? '<div class="g-empty">No editable fields were found for this page.</div>' : ''}

                <div class="gp-editor-foot">
                    <span class="gp-save-status"></span>
                    ${(hasImages || editableKeys.length > 0) ? '<button type="button" class="gp-btn gp-btn-primary" data-action="save">Save changes</button>' : ''}
                </div>
            </div>
        `;

        container.querySelector('[data-action="back"]').addEventListener('click', renderList);

        // Live-preview any newly chosen replacement image.
        container.querySelectorAll('.gp-image-file-input').forEach(input => {
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                const thumb = input.closest('.gp-image-slot').querySelector('.gp-image-thumb');
                if (file && thumb) {
                    thumb.src = URL.createObjectURL(file);
                    thumb.classList.remove('gp-image-thumb-empty');
                }
            });
        });

        const saveBtn = container.querySelector('[data-action="save"]');
        if (!saveBtn) return;

        const statusEl = container.querySelector('.gp-save-status');

        saveBtn.addEventListener('click', async () => {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            statusEl.textContent = '';
            statusEl.classList.remove('gp-error');

            const updatedFields = {};
            editableKeys.forEach(key => {
                updatedFields[key] = collectFieldValue(container, key, fields[key]);
            });
            // Repeated-image labels get folded back into their own field
            // (e.g. floorplanLabel), same shape it was originally in.
            images.repeated.forEach(rep => {
                if (!rep.labelField) return;
                const labelInputs = [...container.querySelectorAll(
                    `.gp-image-slot[data-field="${cssEscape(rep.field)}"] .gp-image-label`
                )];
                updatedFields[rep.labelField] = JSON.stringify(labelInputs.map(i => i.value));
            });

            try {
                const fd = new FormData();
                fd.append('action', 'save');
                fd.append('id', id);
                fd.append('fields', JSON.stringify(updatedFields));

                container.querySelectorAll('.gp-image-file-input').forEach(input => {
                    const file = input.files && input.files[0];
                    if (file) fd.append(input.dataset.uploadName, file, file.name);
                });

                const res = await fetch(apiBase, { method: 'POST', body: fd });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.error || 'Save failed');
                statusEl.textContent = 'Saved.';
            } catch (err) {
                statusEl.textContent = 'Could not save changes. Please try again.';
                statusEl.classList.add('gp-error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save changes';
            }
        });
    }

    return { unmount };
}

/* ---------- Image controls ---------- */

function renderSingleImageControl(img) {
    const uploadName = img.field;
    return `
        <div class="gp-field-row gp-image-slot" data-field="${escapeAttr(img.field)}">
            <label class="gp-field-label">${escapeHtml(img.label)}</label>
            <div class="gp-image-item">
                ${imageThumb(img.url)}
                <input type="file" class="gp-image-file-input" accept="image/*" data-upload-name="${escapeAttr(uploadName)}">
            </div>
        </div>
    `;
}

function renderRepeatedImageControl(rep) {
    if (!rep.items || rep.items.length === 0) {
        return `
            <div class="gp-field-row gp-image-slot" data-field="${escapeAttr(rep.field)}">
                <label class="gp-field-label">${escapeHtml(rep.label)}</label>
                <div class="gp-empty-note">Nothing uploaded here yet.</div>
            </div>
        `;
    }
    return `
        <div class="gp-field-row gp-image-slot" data-field="${escapeAttr(rep.field)}">
            <label class="gp-field-label">${escapeHtml(rep.label)}</label>
            <div class="gp-image-grid">
                ${rep.items.map(item => `
                    <div class="gp-image-item">
                        ${imageThumb(item.url)}
                        <input type="file" class="gp-image-file-input" accept="image/*" data-upload-name="img_slot__${escapeAttr(rep.field)}__${item.n}">
                        ${rep.labelField ? `<input type="text" class="gp-image-label" value="${escapeAttr(item.label || '')}" placeholder="Caption">` : ''}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function imageThumb(url) {
    return url
        ? `<img class="gp-image-thumb" src="${escapeAttr(url)}" alt="">`
        : `<div class="gp-image-thumb gp-image-thumb-empty"></div>`;
}

/* ---------- Generic field rendering (no theme-specific knowledge) ---------- */

function isEditableKey(key) {
    if (SKIP_FIELD_KEYS.has(key)) return false;
    return !SKIP_FIELD_PATTERNS.some(re => re.test(key));
}

function tryParseArray(value) {
    if (Array.isArray(value)) return value;
    if (typeof value !== 'string') return null;
    const trimmed = value.trim();
    if (!trimmed.startsWith('[')) return null;
    try {
        const parsed = JSON.parse(trimmed);
        return Array.isArray(parsed) ? parsed : null;
    } catch (err) {
        return null;
    }
}

function renderFieldControl(key, rawValue) {
    const label = labelFor(key);
    const arr = tryParseArray(rawValue);

    if (arr) {
        if (arr.length === 0) {
            return `
                <div class="gp-field-row" data-field="${escapeAttr(key)}" data-kind="array">
                    <label class="gp-field-label">${escapeHtml(label)}</label>
                    <div class="gp-empty-note">Nothing to edit here yet.</div>
                </div>
            `;
        }
        if (arr.every(item => typeof item !== 'object' || item === null)) {
            // list of plain strings/numbers (e.g. highlights, location advantages)
            return `
                <div class="gp-field-row" data-field="${escapeAttr(key)}" data-kind="array">
                    <label class="gp-field-label">${escapeHtml(label)}</label>
                    <div class="gp-array-list">
                        ${arr.map((item, i) => `
                            <input type="text" class="gp-array-item" data-i="${i}" value="${escapeAttr(item)}">
                        `).join('')}
                    </div>
                </div>
            `;
        }
        // list of objects (e.g. price rows: { type, area, price })
        const columns = Object.keys(arr[0] || {});
        return `
            <div class="gp-field-row" data-field="${escapeAttr(key)}" data-kind="array-obj" data-columns="${escapeAttr(JSON.stringify(columns))}">
                <label class="gp-field-label">${escapeHtml(label)}</label>
                <div class="gp-array-obj-list">
                    ${arr.map((item, i) => `
                        <div class="gp-array-obj-row" data-i="${i}">
                            ${columns.map(col => `
                                <input type="text" class="gp-array-obj-cell" data-col="${escapeAttr(col)}" placeholder="${escapeAttr(labelFor(col))}" value="${escapeAttr(item[col] ?? '')}">
                            `).join('')}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    const value = rawValue == null ? '' : String(rawValue);
    const isColor = /^colo(u)?r/i.test(key) && /^#[0-9a-fA-F]{3,6}$/.test(value);
    if (isColor) {
        return `
            <div class="gp-field-row" data-field="${escapeAttr(key)}" data-kind="text">
                <label class="gp-field-label">${escapeHtml(label)}</label>
                <input type="color" class="gp-field-input gp-field-color" value="${escapeAttr(value)}">
            </div>
        `;
    }
    if (value.length > LONG_TEXT_THRESHOLD || /\n/.test(value)) {
        return `
            <div class="gp-field-row" data-field="${escapeAttr(key)}" data-kind="text">
                <label class="gp-field-label">${escapeHtml(label)}</label>
                <textarea class="gp-field-textarea">${escapeHtml(value)}</textarea>
            </div>
        `;
    }
    return `
        <div class="gp-field-row" data-field="${escapeAttr(key)}" data-kind="text">
            <label class="gp-field-label">${escapeHtml(label)}</label>
            <input type="text" class="gp-field-input" value="${escapeAttr(value)}">
        </div>
    `;
}

function collectFieldValue(container, key, originalRawValue) {
    const row = container.querySelector(`.gp-field-row[data-field="${cssEscape(key)}"][data-kind]`);
    if (!row) return originalRawValue;

    const kind = row.dataset.kind;
    if (kind === 'array') {
        const inputs = [...row.querySelectorAll('.gp-array-item')];
        return JSON.stringify(inputs.map(i => i.value));
    }
    if (kind === 'array-obj') {
        const rows = [...row.querySelectorAll('.gp-array-obj-row')];
        const columns = JSON.parse(row.dataset.columns || '[]');
        return JSON.stringify(rows.map(r => {
            const obj = {};
            columns.forEach(col => {
                const cell = r.querySelector(`.gp-array-obj-cell[data-col="${cssEscape(col)}"]`);
                obj[col] = cell ? cell.value : '';
            });
            return obj;
        }));
    }
    const textarea = row.querySelector('.gp-field-textarea');
    if (textarea) return textarea.value;
    const input = row.querySelector('.gp-field-input');
    return input ? input.value : originalRawValue;
}

function labelFor(key) {
    return String(key)
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/^./, c => c.toUpperCase())
        .trim();
}

function cssEscape(str) {
    return String(str).replace(/["\\]/g, '\\$&');
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString();
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function escapeAttr(str) {
    return escapeHtml(str);
}
