/**
 * themes/palm-estate/form.js
 * ---------------------------------------------------------
 * Palm Estate theme — converted from an uploaded static design.
 * Own field set (hero banner, about, amenity tags, location photo,
 * gallery, about-builder, contact) — distinct from Default.
 * Same mount()/unmount() contract as every other theme module.
 * ---------------------------------------------------------
 */

export async function mount(container, ctx) {
    const themeId = ctx.themeId || 'palm-estate';
    const webhookUrl = ctx.webhookUrl || 'backend/generate.php';

    const [html, styleLink] = await Promise.all([
        fetch(new URL('./form.html', import.meta.url)).then(r => r.text()),
        loadStylesheet(new URL('./form.css', import.meta.url)),
    ]);
    container.innerHTML = html;

    const $ = (id) => document.getElementById(id);

    // Track listeners attached outside `container` so unmount() can clean them up.
    const teardownFns = [];
    function onDoc(type, fn) {
        document.addEventListener(type, fn);
        teardownFns.push(() => document.removeEventListener(type, fn));
    }

    /* ---------- Section picker (nav-driven show/hide) ---------- */
    const sectionPickerBtn = $('pe_sectionPickerBtn');
    const sectionPickerPanel = $('pe_sectionPickerPanel');
    const sectionPickerLabel = $('pe_sectionPickerLabel');
    const sectionToggleInputs = document.querySelectorAll('.pe-section-toggle');
    const lockedSectionCount = 3; // Overview, Location, Contact — always included

    function updateSectionCards() {
        sectionToggleInputs.forEach(input => {
            const section = input.dataset.section;
            document.querySelectorAll(`[data-section="${section}"]`).forEach(card => {
                card.classList.toggle('pe-section-hidden', !input.checked);
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
        sectionPickerBtn.classList.toggle('pe-open');
        sectionPickerPanel.classList.toggle('pe-open');
    });
    onDoc('click', (e) => {
        if (!e.target.closest('.pe-section-picker')) {
            sectionPickerBtn.classList.remove('pe-open');
            sectionPickerPanel.classList.remove('pe-open');
        }
    });
    updateSectionCards();

    /* ---------- Single image uploads ---------- */
    function wireSingleUpload(inputId, statusId, boxId) {
        const input = $(inputId);
        const box = $(boxId);
        const status = $(statusId);
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (file) {
                status.textContent = file.name;
                const existing = box.querySelector('img.pe-thumb');
                if (existing) existing.remove();
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.createElement('img');
                    img.className = 'pe-thumb';
                    img.src = e.target.result;
                    box.insertBefore(img, box.firstChild);
                };
                reader.readAsDataURL(file);
            } else {
                status.textContent = 'Tap to choose photo';
            }
        });
    }
    wireSingleUpload('pe_heroImage', 'pe_heroStatus', 'pe_heroBox');
    wireSingleUpload('pe_logo', 'pe_logoStatus', 'pe_logoBox');
    wireSingleUpload('pe_aboutImage', 'pe_aboutStatus', 'pe_aboutBox');
    wireSingleUpload('pe_locationImage', 'pe_locationStatus', 'pe_locationBox');
    wireSingleUpload('pe_builderImage', 'pe_builderStatus', 'pe_builderBox');

    function heroThumbDataUrl() {
        const img = $('pe_heroBox').querySelector('img.pe-thumb');
        return img ? img.src : null;
    }

    /* ---------- Amenities (tag input) ---------- */
    const amenities = [];
    const amenityInput = $('pe_amenityInput');
    const amenityTags = $('pe_amenityTags');
    function renderAmenities() {
        amenityTags.innerHTML = amenities.map((a, i) =>
            `<span class="pe-tag">${escapeHtml(a)}<button type="button" data-i="${i}">&times;</button></span>`
        ).join('');
        amenityTags.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                amenities.splice(+btn.dataset.i, 1);
                renderAmenities();
            });
        });
    }
    amenityInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && amenityInput.value.trim()) {
            e.preventDefault();
            amenities.push(amenityInput.value.trim());
            amenityInput.value = '';
            renderAmenities();
        }
    });

    /* ---------- Gallery (multi-file upload grid) ---------- */
    let galleryItems = []; // { file, thumbDataUrl }
    const galleryGrid = $('pe_galleryGrid');
    function renderGallery() {
        galleryGrid.innerHTML = '';
        galleryItems.forEach((item, idx) => {
            const el = document.createElement('div');
            el.className = 'pe-gitem';
            el.innerHTML = `<img src="${item.thumbDataUrl}"><button type="button" class="pe-gremove">&times;</button>`;
            el.querySelector('.pe-gremove').addEventListener('click', () => {
                galleryItems = galleryItems.filter((_, i) => i !== idx);
                renderGallery();
            });
            galleryGrid.appendChild(el);
        });
    }
    $('pe_galleryInput').addEventListener('change', (e) => {
        Array.from(e.target.files || []).forEach(file => {
            const reader = new FileReader();
            reader.onload = (ev) => {
                galleryItems.push({ file, thumbDataUrl: ev.target.result });
                renderGallery();
            };
            reader.readAsDataURL(file);
        });
        e.target.value = '';
    });

    /* ---------- Submit ---------- */
    const form = $('palmForm');
    const formError = $('pe_formError');
    const submitBtn = $('pe_submitBtn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        formError.style.display = 'none';

        const required = ['pe_projectName', 'pe_locationTagline', 'pe_priceText', 'pe_phone', 'pe_toEmail'];
        let missing = required.some(id => !$(id).value.trim());
        if (!$('pe_heroImage').files[0]) missing = true;

        if (missing) {
            formError.style.display = 'block';
            formError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        const fd = new FormData();
        fd.append('projectName', $('pe_projectName').value.trim());
        fd.append('locationTagline', $('pe_locationTagline').value.trim());
        fd.append('heroSubtitle', $('pe_heroSubtitle').value.trim());
        fd.append('priceText', $('pe_priceText').value.trim());
        fd.append('aboutText', $('pe_aboutText').value.trim());
        fd.append('featureType', $('pe_featureType').value.trim());
        fd.append('featureLocation', $('pe_featureLocation').value.trim());
        fd.append('featurePossession', $('pe_featurePossession').value.trim());
        fd.append('builderHeading', $('pe_builderHeading').value.trim());
        fd.append('builderText', $('pe_builderText').value.trim());
        fd.append('addressBlock', $('pe_addressBlock').value.trim());
        fd.append('phone', $('pe_phone').value.trim());
        fd.append('toEmail', $('pe_toEmail').value.trim());
        fd.append('ccEmail', $('pe_ccEmail').value.trim());
        fd.append('bccEmail', $('pe_bccEmail').value.trim());
        fd.append('colorPrimary', $('pe_colorPrimary').value);
        fd.append('colorSecondary', $('pe_colorSecondary').value);
        fd.append('gtagId', $('pe_gtagId').value.trim());
        fd.append('conversionSendTo', $('pe_conversionSendTo').value.trim());
        fd.append('amenities', JSON.stringify(amenities));
        fd.append('includeAmenities', document.querySelector('.pe-section-toggle[data-section="amenities"]').checked ? '1' : '0');
        fd.append('includeGallery', document.querySelector('.pe-section-toggle[data-section="gallery"]').checked ? '1' : '0');
        fd.append('includeBuilder', document.querySelector('.pe-section-toggle[data-section="builder"]').checked ? '1' : '0');

        const heroFile = $('pe_heroImage').files[0];
        if (heroFile) fd.append('heroImage', heroFile, heroFile.name);
        const logoFile = $('pe_logo').files[0];
        if (logoFile) fd.append('logo', logoFile, logoFile.name);
        const aboutFile = $('pe_aboutImage').files[0];
        if (aboutFile) fd.append('aboutImage', aboutFile, aboutFile.name);
        const locationFile = $('pe_locationImage').files[0];
        if (locationFile) fd.append('locationImage', locationFile, locationFile.name);
        const builderFile = $('pe_builderImage').files[0];
        if (builderFile) fd.append('builderImage', builderFile, builderFile.name);

        galleryItems.forEach(item => {
            fd.append('gallery[]', item.file, item.file.name);
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
            $('pe_successScreen').style.display = 'block';
            $('pe_refNumber').textContent = 'Reference: ' + refNumber;

            const previewBtn = $('pe_previewBtn');
            const downloadBtn = $('pe_downloadBtn');
            if (data.previewLink) {
                previewBtn.href = data.previewLink;
                previewBtn.style.display = 'block';
            }
            if (data.downloadLink) {
                downloadBtn.href = data.downloadLink;
                downloadBtn.style.display = 'block';
                $('pe_successTitle').textContent = 'Your landing page is ready!';
                $('pe_successMsg').textContent = 'Preview it below or download the full folder as a zip.';
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

    $('pe_editDetailsBtn').addEventListener('click', () => {
        $('pe_successScreen').style.display = 'none';
        form.style.display = 'block';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Request';
        formError.style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    $('pe_startOverBtn').addEventListener('click', () => location.reload());

    /* ---------- Live preview summary (generic contract) ---------- */
    function getPreviewSummary() {
        const val = (id) => ($(id)?.value || '').trim();
        return {
            badge: val('pe_priceText') ? ('From ' + val('pe_priceText')) : '',
            title: val('pe_projectName'),
            subtitle: val('pe_locationTagline') || val('pe_heroSubtitle'),
            heroImage: heroThumbDataUrl(),
            colors: { primary: val('pe_colorPrimary') || '#0c5848', secondary: val('pe_colorSecondary') || '#000000' },
            rows: [
                { label: 'Type', value: val('pe_featureType') },
                { label: 'Possession', value: val('pe_featurePossession') },
                { label: 'Phone', value: val('pe_phone') },
                { label: 'Gallery photos', value: galleryItems.length || '' },
            ],
            chips: amenities.slice(0, 6),
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
        link.dataset.themeStylesheet = 'palm-estate';
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
