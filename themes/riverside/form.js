/**
 * themes/riverside/form.js
 * ---------------------------------------------------------
 * Riverside Residences theme — content-rich, image-led microsite.
 * Own field set (hero slider, overview, highlights, amenities, price
 * list, floor plan, master plan, gallery, virtual tour, location,
 * about developer) matching backend/themes/riverside/renderer.php's
 * $_POST/$_FILES contract. Same mount()/unmount() contract as every
 * other theme module.
 * ---------------------------------------------------------
 */

export async function mount(container, ctx) {
    const themeId = ctx.themeId || 'riverside';
    const webhookUrl = ctx.webhookUrl || 'backend/generate.php';

    const [html, styleLink] = await Promise.all([
        fetch(new URL('./form.html', import.meta.url)).then(r => r.text()),
        loadStylesheet(new URL('./form.css', import.meta.url)),
    ]);
    container.innerHTML = html;

    const $ = (id) => document.getElementById(id);

    const teardownFns = [];
    function onDoc(type, fn) {
        document.addEventListener(type, fn);
        teardownFns.push(() => document.removeEventListener(type, fn));
    }

    /* ---------- Section picker (nav-driven show/hide) ---------- */
    const sectionPickerBtn = $('rv_sectionPickerBtn');
    const sectionPickerPanel = $('rv_sectionPickerPanel');
    const sectionPickerLabel = $('rv_sectionPickerLabel');
    const sectionToggleInputs = document.querySelectorAll('.rv-section-toggle');
    const lockedSectionCount = 2; // Overview, Contact — always included

    function updateSectionCards() {
        sectionToggleInputs.forEach(input => {
            const section = input.dataset.section;
            document.querySelectorAll(`[data-section="${section}"]`).forEach(card => {
                card.classList.toggle('rv-section-hidden', !input.checked);
            });
        });
        const total = sectionToggleInputs.length;
        const checked = [...sectionToggleInputs].filter(i => i.checked).length;
        sectionPickerLabel.textContent = checked === total
            ? 'All sections included'
            : `${checked + lockedSectionCount} of ${total + lockedSectionCount} sections included`;
    }
    sectionToggleInputs.forEach(input => input.addEventListener('change', updateSectionCards));
    sectionPickerBtn.addEventListener('click', () => {
        sectionPickerBtn.classList.toggle('rv-open');
        sectionPickerPanel.classList.toggle('rv-open');
    });
    onDoc('click', (e) => {
        if (!e.target.closest('.rv-section-picker')) {
            sectionPickerBtn.classList.remove('rv-open');
            sectionPickerPanel.classList.remove('rv-open');
        }
    });
    updateSectionCards();

    function sectionChecked(section) {
        const input = document.querySelector(`.rv-section-toggle[data-section="${section}"]`);
        return input ? (input.checked ? '1' : '0') : '1';
    }

    /* ---------- Single image uploads ---------- */
    function wireSingleUpload(inputId, statusId, boxId) {
        const input = $(inputId);
        const box = $(boxId);
        const status = $(statusId);
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (file) {
                status.textContent = file.name;
                const existing = box.querySelector('img.rv-thumb');
                if (existing) existing.remove();
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.createElement('img');
                    img.className = 'rv-thumb';
                    img.src = e.target.result;
                    box.insertBefore(img, box.firstChild);
                };
                reader.readAsDataURL(file);
            } else {
                status.textContent = 'Tap to choose photo';
            }
        });
    }
    wireSingleUpload('rv_logo', 'rv_logoStatus', 'rv_logoBox');
    wireSingleUpload('rv_overviewImage', 'rv_overviewStatus', 'rv_overviewBox');
    wireSingleUpload('rv_highlightImage', 'rv_highlightStatus', 'rv_highlightBox');
    wireSingleUpload('rv_masterplanImage', 'rv_masterplanStatus', 'rv_masterplanBox');
    wireSingleUpload('rv_locationImage', 'rv_locationStatus', 'rv_locationBox');

    /* ---------- Hero slider (unlimited, no label) ---------- */
    let heroItems = []; // { file, thumbDataUrl }
    const heroGrid = $('rv_heroGrid');
    function renderHero() {
        heroGrid.innerHTML = '';
        heroItems.forEach((item, idx) => {
            const el = document.createElement('div');
            el.className = 'rv-mitem';
            el.innerHTML = `<button type="button" class="rv-mremove">&times;</button><img src="${item.thumbDataUrl}">`;
            el.querySelector('.rv-mremove').addEventListener('click', () => {
                heroItems = heroItems.filter((_, i) => i !== idx);
                renderHero();
            });
            heroGrid.appendChild(el);
        });
    }
    $('rv_heroImages').addEventListener('change', (e) => {
        Array.from(e.target.files || []).forEach(file => {
            const reader = new FileReader();
            reader.onload = (ev) => {
                heroItems.push({ file, thumbDataUrl: ev.target.result });
                renderHero();
            };
            reader.readAsDataURL(file);
        });
        e.target.value = '';
    });
    function heroThumbDataUrl() {
        return heroItems.length ? heroItems[0].thumbDataUrl : null;
    }

    /* ---------- Multi image + label grids (amenities / floor plan / gallery) ---------- */
    function makeLabeledMultiGrid(inputId, gridId, placeholder) {
        let items = []; // { file, label, thumbDataUrl }
        const grid = $(gridId);
        function render() {
            grid.innerHTML = '';
            items.forEach((item, idx) => {
                const el = document.createElement('div');
                el.className = 'rv-mitem';
                el.innerHTML = `<button type="button" class="rv-mremove">&times;</button><img src="${item.thumbDataUrl}"><input type="text" class="rv-mlabel" placeholder="${placeholder}" value="${escapeHtml(item.label)}">`;
                el.querySelector('.rv-mremove').addEventListener('click', () => {
                    items = items.filter((_, i) => i !== idx);
                    render();
                });
                el.querySelector('.rv-mlabel').addEventListener('input', (e) => {
                    items[idx].label = e.target.value;
                });
                grid.appendChild(el);
            });
        }
        $(inputId).addEventListener('change', (e) => {
            Array.from(e.target.files || []).forEach(file => {
                const reader = new FileReader();
                reader.onload = (ev) => {
                    items.push({ file, label: '', thumbDataUrl: ev.target.result });
                    render();
                };
                reader.readAsDataURL(file);
            });
            e.target.value = '';
        });
        return { getItems: () => items };
    }
    const amenityGrid = makeLabeledMultiGrid('rv_amenityInput', 'rv_amenityGrid', 'Label');
    const floorplanGrid = makeLabeledMultiGrid('rv_floorplanInput', 'rv_floorplanGrid', 'Label');
    const galleryGrid = makeLabeledMultiGrid('rv_galleryInput', 'rv_galleryGrid', 'Caption');

    /* ---------- Tag inputs (highlights / location advantages) ---------- */
    function makeTagInput(inputId, tagsId) {
        const tags = [];
        const input = $(inputId);
        const tagsEl = $(tagsId);
        function render() {
            tagsEl.innerHTML = tags.map((t, i) =>
                `<span class="rv-tag">${escapeHtml(t)}<button type="button" data-i="${i}">&times;</button></span>`
            ).join('');
            tagsEl.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => {
                    tags.splice(+btn.dataset.i, 1);
                    render();
                });
            });
        }
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && input.value.trim()) {
                e.preventDefault();
                tags.push(input.value.trim());
                input.value = '';
                render();
            }
        });
        return { getTags: () => tags };
    }
    const highlightTags = makeTagInput('rv_highlightInput', 'rv_highlightTags');
    const locationAdvTags = makeTagInput('rv_locationAdvInput', 'rv_locationAdvTags');

    /* ---------- Repeatable rows (hero badges / price rows) ---------- */
    function makeRowGroup(containerId, addBtnId, fields) {
        const rowsEl = $(containerId);
        function addRow(values = {}) {
            const row = document.createElement('div');
            row.className = 'rv-row-item' + (fields.length === 2 ? ' rv-row-2' : '');
            row.innerHTML = fields.map(f => `<input type="text" data-field="${f.key}" placeholder="${f.placeholder}" value="${escapeHtml(values[f.key] || '')}">`).join('')
                + `<button type="button" class="rv-row-remove">&times;</button>`;
            row.querySelector('.rv-row-remove').addEventListener('click', () => row.remove());
            rowsEl.appendChild(row);
        }
        $(addBtnId).addEventListener('click', () => addRow());
        function getRows() {
            return Array.from(rowsEl.querySelectorAll('.rv-row-item')).map(row => {
                const obj = {};
                fields.forEach(f => {
                    obj[f.key] = row.querySelector(`[data-field="${f.key}"]`).value.trim();
                });
                return obj;
            });
        }
        return { addRow, getRows };
    }
    const heroBadgeRows = makeRowGroup('rv_heroBadgeRows', 'rv_addHeroBadge', [
        { key: 'label', placeholder: 'Label (e.g. Type)' },
        { key: 'value', placeholder: 'Value (e.g. 3 & 4 BHK)' },
    ]);
    const priceRowsGroup = makeRowGroup('rv_priceRows', 'rv_addPriceRow', [
        { key: 'type', placeholder: 'Unit Type (e.g. 3 BHK)' },
        { key: 'size', placeholder: 'Unit Size (e.g. 1450 sqft)' },
        { key: 'price', placeholder: 'Price (e.g. 1.2 Cr)' },
    ]);

    /* ---------- Submit ---------- */
    const form = $('riversideForm');
    const formError = $('rv_formError');
    const submitBtn = $('rv_submitBtn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        formError.style.display = 'none';

        const required = ['rv_projectName', 'rv_locationTagline', 'rv_priceText', 'rv_phone', 'rv_toEmail'];
        let missing = required.some(id => !$(id).value.trim());
        if (!heroItems.length) missing = true;

        if (missing) {
            formError.style.display = 'block';
            formError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        const fd = new FormData();
        fd.append('projectName', $('rv_projectName').value.trim());
        fd.append('locationTagline', $('rv_locationTagline').value.trim());
        fd.append('heroSubtitle', $('rv_heroSubtitle').value.trim());
        fd.append('priceText', $('rv_priceText').value.trim());
        fd.append('phone', $('rv_phone').value.trim());
        fd.append('toEmail', $('rv_toEmail').value.trim());
        fd.append('ccEmail', $('rv_ccEmail').value.trim());
        fd.append('bccEmail', $('rv_bccEmail').value.trim());
        fd.append('metaDescription', $('rv_metaDescription').value.trim());
        fd.append('metaKeywords', $('rv_metaKeywords').value.trim());
        fd.append('overviewText', $('rv_overviewText').value.trim());
        fd.append('aboutDeveloperText', $('rv_aboutDeveloperText').value.trim());
        fd.append('footerDisclaimerText', $('rv_footerDisclaimerText').value.trim());
        fd.append('whatsappLink', $('rv_whatsappLink').value.trim());
        fd.append('virtualTourEmbedUrl', $('rv_virtualTourEmbedUrl').value.trim());
        fd.append('gtagId', $('rv_gtagId').value.trim());
        fd.append('conversionSendTo', $('rv_conversionSendTo').value.trim());
        fd.append('colorPrimary', $('rv_colorPrimary').value);
        fd.append('colorSecondary', $('rv_colorSecondary').value);

        fd.append('heroBadges', JSON.stringify(heroBadgeRows.getRows().filter(r => r.label || r.value)));
        fd.append('highlights', JSON.stringify(highlightTags.getTags()));
        fd.append('priceRows', JSON.stringify(priceRowsGroup.getRows().filter(r => r.type)));
        fd.append('locationAdvantages', JSON.stringify(locationAdvTags.getTags()));

        fd.append('includeHighlights', sectionChecked('highlights'));
        fd.append('includeAmenities', sectionChecked('amenities'));
        fd.append('includePrice', sectionChecked('price'));
        fd.append('includeFloorplan', sectionChecked('floorplan'));
        fd.append('includeMasterplan', sectionChecked('masterplan'));
        fd.append('includeGallery', sectionChecked('gallery'));
        fd.append('includeVirtualTour', sectionChecked('virtualtour'));
        fd.append('includeLocation', sectionChecked('location'));

        const logoFile = $('rv_logo').files[0];
        if (logoFile) fd.append('logo', logoFile, logoFile.name);
        const overviewFile = $('rv_overviewImage').files[0];
        if (overviewFile) fd.append('overviewImage', overviewFile, overviewFile.name);
        const highlightFile = $('rv_highlightImage').files[0];
        if (highlightFile) fd.append('highlightImage', highlightFile, highlightFile.name);
        const masterplanFile = $('rv_masterplanImage').files[0];
        if (masterplanFile) fd.append('masterplanImage', masterplanFile, masterplanFile.name);
        const locationFile = $('rv_locationImage').files[0];
        if (locationFile) fd.append('locationImage', locationFile, locationFile.name);

        heroItems.forEach(item => fd.append('heroImage[]', item.file, item.file.name));

        amenityGrid.getItems().forEach(item => {
            fd.append('amenityImage[]', item.file, item.file.name);
            fd.append('amenityLabel[]', item.label);
        });
        floorplanGrid.getItems().forEach(item => {
            fd.append('floorplanImage[]', item.file, item.file.name);
            fd.append('floorplanLabel[]', item.label);
        });
        galleryGrid.getItems().forEach(item => {
            fd.append('galleryImage[]', item.file, item.file.name);
            fd.append('galleryCaption[]', item.label);
        });

        const refNumber = 'REQ-' + Date.now().toString().slice(-8);
        fd.append('refNumber', refNumber);
        fd.append('themeId', themeId);

        try {
            const res = await fetch(webhookUrl, { method: 'POST', body: fd });

            let data = {};
            try { data = await res.json(); } catch (_) { /* no JSON body */ }

            if (!res.ok) throw new Error(data.error || `Request failed (status ${res.status}).`);

            form.style.display = 'none';
            $('rv_successScreen').style.display = 'block';
            $('rv_refNumber').textContent = 'Reference: ' + refNumber;

            const previewBtn = $('rv_previewBtn');
            const downloadBtn = $('rv_downloadBtn');
            if (data.previewLink) {
                previewBtn.href = data.previewLink;
                previewBtn.style.display = 'block';
            }
            if (data.downloadLink) {
                downloadBtn.href = data.downloadLink;
                downloadBtn.style.display = 'block';
                $('rv_successTitle').textContent = 'Your landing page is ready!';
                $('rv_successMsg').textContent = 'Preview it below or download the full folder as a zip.';
            }
        } catch (err) {
            const isNetworkError = err instanceof TypeError;
            formError.textContent = isNetworkError
                ? 'Something went wrong submitting your request. Please check your connection and try again.'
                : (err.message || 'Something went wrong submitting your request. Please try again.');
            formError.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Request';
        }
    });

    $('rv_editDetailsBtn').addEventListener('click', () => {
        $('rv_successScreen').style.display = 'none';
        form.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Request';
        formError.style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    $('rv_startOverBtn').addEventListener('click', () => location.reload());

    /* ---------- Live preview summary (generic contract) ---------- */
    function getPreviewSummary() {
        const val = (id) => ($(id)?.value || '').trim();
        return {
            badge: val('rv_priceText') ? ('From ' + val('rv_priceText')) : '',
            title: val('rv_projectName'),
            subtitle: val('rv_locationTagline') || val('rv_heroSubtitle'),
            heroImage: heroThumbDataUrl(),
            colors: { primary: val('rv_colorPrimary') || '#956543', secondary: val('rv_colorSecondary') || '#1a1a1a' },
            rows: [
                { label: 'Phone', value: val('rv_phone') },
                { label: 'Price rows', value: priceRowsGroup.getRows().filter(r => r.type).length || '' },
                { label: 'Gallery photos', value: galleryGrid.getItems().length || '' },
            ],
            chips: highlightTags.getTags().slice(0, 6),
        };
    }

    function unmount() {
        container.innerHTML = '';
        if (styleLink && styleLink.parentNode) styleLink.parentNode.removeChild(styleLink);
        teardownFns.forEach(fn => fn());
    }

    return { unmount, getPreviewSummary };
}

function loadStylesheet(href) {
    return new Promise((resolve) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.themeStylesheet = 'riverside';
        link.onload = () => resolve(link);
        link.onerror = () => resolve(link);
        document.head.appendChild(link);
    });
}

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}
