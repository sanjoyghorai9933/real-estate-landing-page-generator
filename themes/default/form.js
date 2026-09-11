/**
 * themes/default/form.js
 * ---------------------------------------------------------
 * Default theme — form definitions + components + client-side
 * behavior, extracted verbatim from the original single-file
 * request form (index.html) and preserved byte-for-byte in logic.
 * This theme owns its ENTIRE post-selection experience: the form
 * fields, all custom widgets (tag inputs, repeaters, color
 * pickers + live swatch preview, section picker), validation, and
 * the submit -> success screen flow. The core generator (core/app.js)
 * only knows how to call mount()/unmount() on whichever theme module
 * the user picked — it never reaches into this file's internals.
 *
 * Contract (same for every theme module):
 *   export async function mount(container, ctx) -> controller
 *   controller.unmount()
 * ---------------------------------------------------------
 */

export async function mount(container, ctx) {
    const themeId = ctx.themeId || 'default';
    const webhookUrl = ctx.webhookUrl || 'backend/generate.php';

    // ---- Load this theme's markup + styles ----
    const [html, styleLink] = await Promise.all([
        fetch(new URL('./form.html', import.meta.url)).then(r => r.text()),
        loadStylesheet(new URL('./form.css', import.meta.url)),
    ]);
    container.innerHTML = html;

    // Track listeners attached outside `container` (e.g. document-level
    // "click outside to close" handlers) so unmount() can clean them up.
    const teardownFns = [];
    function onDoc(type, fn) {
        document.addEventListener(type, fn);
        teardownFns.push(() => document.removeEventListener(type, fn));
    }

    /* ---------- Section picker (nav-driven show/hide) ---------- */
    const sectionPickerBtn = document.getElementById('sectionPickerBtn');
    const sectionPickerPanel = document.getElementById('sectionPickerPanel');
    const sectionPickerLabel = document.getElementById('sectionPickerLabel');
    const sectionToggleInputs = document.querySelectorAll('.section-toggle');

    function updateSectionCards() {
        sectionToggleInputs.forEach(input => {
            const section = input.dataset.section;
            document.querySelectorAll(`[data-section="${section}"]`).forEach(card => {
                card.classList.toggle('section-hidden', !input.checked);
            });
        });
        const total = sectionToggleInputs.length;
        const checked = [...sectionToggleInputs].filter(i => i.checked).length;
        sectionPickerLabel.textContent = checked === total
            ? 'All sections included'
            : `${checked + 1} of ${total + 1} sections included`; // +1 for the always-on Overview
    }
    sectionToggleInputs.forEach(input => input.addEventListener('change', updateSectionCards));
    sectionPickerBtn.addEventListener('click', () => {
        sectionPickerBtn.classList.toggle('open');
        sectionPickerPanel.classList.toggle('open');
    });
    onDoc('click', (e) => {
        if (!e.target.closest('.section-picker')) {
            sectionPickerBtn.classList.remove('open');
            sectionPickerPanel.classList.remove('open');
        }
    });
    updateSectionCards();

    /* ---------- Lead delivery: Email only vs Email + CRM ---------- */
    const crmOptionSelect = document.getElementById('crmOption');
    const crmApiKeyField = document.getElementById('crmApiKeyField');
    crmOptionSelect.addEventListener('change', () => {
        crmApiKeyField.style.display = crmOptionSelect.value === 'crm' ? 'block' : 'none';
    });

    /* ---------- Highlights (tag input) ---------- */
    const highlights = [];
    const highlightInput = document.getElementById('highlightInput');
    const highlightTags = document.getElementById('highlightTags');

    function renderHighlights() {
        highlightTags.innerHTML = highlights.map((h, i) =>
            `<span class="tag">${h}<button type="button" data-i="${i}">✕</button></span>`
        ).join('');
        highlightTags.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                highlights.splice(+btn.dataset.i, 1);
                renderHighlights();
            });
        });
    }
    highlightInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && highlightInput.value.trim()) {
            e.preventDefault();
            highlights.push(highlightInput.value.trim());
            highlightInput.value = '';
            renderHighlights();
        }
    });

    /* ---------- Location Advantages (tag input) ---------- */
    const locationAdvantages = [];
    const locationAdvInput = document.getElementById('locationAdvInput');
    const locationAdvTags = document.getElementById('locationAdvTags');

    function renderLocationAdv() {
        locationAdvTags.innerHTML = locationAdvantages.map((h, i) =>
            `<span class="tag">${h}<button type="button" data-i="${i}">✕</button></span>`
        ).join('');
        locationAdvTags.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                locationAdvantages.splice(+btn.dataset.i, 1);
                renderLocationAdv();
            });
        });
    }
    locationAdvInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && locationAdvInput.value.trim()) {
            e.preventDefault();
            locationAdvantages.push(locationAdvInput.value.trim());
            locationAdvInput.value = '';
            renderLocationAdv();
        }
    });

    /* ---------- Pricing rows ---------- */
    const priceRowsEl = document.getElementById('priceRows');

    function addPriceRow(type = '', area = '', price = '') {
        const row = document.createElement('div');
        row.className = 'price-row';
        row.innerHTML = `
            <input type="text" placeholder="Type (e.g. 2 BHK)" class="p-type" value="${type}">
            <input type="text" placeholder="Area (e.g. 1100-1200 Sq.ft)" class="p-area" value="${area}">
            <input type="text" placeholder="Price (e.g. 1.2 Cr Onwards)" class="p-price" value="${price}">
            <button type="button" class="price-remove">Remove</button>
        `;
        row.querySelector('.price-remove').addEventListener('click', () => row.remove());
        priceRowsEl.appendChild(row);
    }
    document.getElementById('addPriceRow').addEventListener('click', () => addPriceRow());
    // start with 2 blank rows
    addPriceRow();
    addPriceRow();

    /* ---------- Theme colors ---------- */
    const colorIds = ['colorPrimary', 'colorSecondary', 'colorBtn', 'colorCard', 'colorOverlay'];
    function updateThemePreview() {
        const primary = document.getElementById('colorPrimary').value;
        const secondary = document.getElementById('colorSecondary').value;
        const btnText = document.getElementById('colorBtn').value;
        const card = document.getElementById('colorCard').value;
        const overlay = document.getElementById('colorOverlay').value;

        colorIds.forEach(id => {
            document.getElementById('hex_' + id).textContent = document.getElementById(id).value;
        });

        document.getElementById('themePreviewHeader').style.background = secondary;
        const badge = document.getElementById('tpBadge');
        badge.style.background = primary;
        badge.style.color = btnText;
        document.getElementById('tpTitle').style.color = '#ffffff';
        const btn = document.getElementById('tpBtn');
        btn.style.background = primary;
        btn.style.color = btnText;

        // navigation active link (matches .navbar.micro-navbar .nav-item .nav-link.active on the real page)
        const navActive = document.getElementById('tpNavLink2');
        navActive.style.background = primary;
        navActive.style.color = btnText;

        // section heading (matches .section .head on the real page)
        document.getElementById('tpSectionHead').style.color = primary;

        document.getElementById('tpCard').style.background = card;
        const cardOverlay = document.getElementById('tpCardOverlay');
        cardOverlay.style.background = `linear-gradient(180deg, ${overlay} 0%, transparent 100%)`;
        document.getElementById('tpCardText').style.color = btnText;
    }
    colorIds.forEach(id => document.getElementById(id).addEventListener('input', updateThemePreview));
    updateThemePreview();

    /* ---------- Single fixed-slot uploads (logo, master plan) ---------- */
    const singleUploadFields = [
        { id: 'logo', label: 'Logo', required: false },
        { id: 'masterplan', label: 'Master Plan', required: false },
        { id: 'authPartnerLogo', label: 'Authorized Partner Logo', required: false },
    ];
    const singleUploadGrid = document.getElementById('singleUploadGrid');
    singleUploadFields.forEach(f => {
        const box = document.createElement('label');
        box.className = 'upload-box';
        box.innerHTML = `
            <input type="file" accept="image/*" id="file_${f.id}">
            <div class="label">${f.label} ${f.required ? '<span class="req">*</span>' : ''}</div>
            <div class="status" id="status_${f.id}">Tap to choose photo</div>
        `;
        singleUploadGrid.appendChild(box);
    });
    singleUploadFields.forEach(f => {
        const input = document.getElementById(`file_${f.id}`);
        const box = input.closest('.upload-box');
        input.addEventListener('change', () => {
            const file = input.files[0];
            const statusEl = document.getElementById(`status_${f.id}`);
            if (file) {
                statusEl.textContent = file.name;
                const existingThumb = box.querySelector('img.thumb');
                if (existingThumb) existingThumb.remove();
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = document.createElement('img');
                    img.className = 'thumb';
                    img.src = e.target.result;
                    box.insertBefore(img, box.firstChild);
                };
                reader.readAsDataURL(file);
            } else {
                statusEl.textContent = 'Tap to choose photo';
            }
        });
    });

    /* ---------- Generic repeatable image list (slider / floorplan / gallery / amenities) ----------
       Each list keeps an array of { file, text } items. Rendered as .dyn-item cards with an
       upload-box + optional text input + remove button. Files/text pulled at submit time. */
    function createDynRepeater(opts) {
        const {
            containerId,
            withText = false,
            textPlaceholder = '',
            uploadLabel = 'Photo',
            minItems = 0,
            firstRequired = false,
        } = opts;

        const container = document.getElementById(containerId);
        let items = []; // { id, file, text }
        let uid = 0;

        function render() {
            container.innerHTML = '';
            items.forEach((item, idx) => {
                const el = document.createElement('div');
                el.className = 'dyn-item';
                el.dataset.id = item.id;

                const showRemove = !(firstRequired && idx === 0 && items.length === 1);
                const reqBadge = (firstRequired && idx === 0) ? '<span class="req-badge">Required</span>' : '';

                el.innerHTML = `
                    ${reqBadge}
                    ${showRemove ? '<button type="button" class="dyn-remove-btn" title="Remove">✕</button>' : ''}
                    <label class="upload-box">
                        <input type="file" accept="image/*" class="dyn-file-input">
                        <div class="label">${uploadLabel} ${idx + 1}</div>
                        <div class="status">${item.file ? item.file.name : 'Tap to choose photo'}</div>
                    </label>
                    ${withText ? `<input type="text" class="dyn-text-input" placeholder="${textPlaceholder}" value="${item.text || ''}">` : ''}
                `;

                // existing thumbnail
                if (item.thumbDataUrl) {
                    const img = document.createElement('img');
                    img.className = 'thumb';
                    img.src = item.thumbDataUrl;
                    el.querySelector('.upload-box').insertBefore(img, el.querySelector('.upload-box').firstChild);
                }

                const fileInput = el.querySelector('.dyn-file-input');
                fileInput.addEventListener('change', () => {
                    const file = fileInput.files[0];
                    item.file = file || null;
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            item.thumbDataUrl = e.target.result;
                            render();
                        };
                        reader.readAsDataURL(file);
                    } else {
                        item.thumbDataUrl = null;
                        render();
                    }
                });

                if (withText) {
                    const textInput = el.querySelector('.dyn-text-input');
                    textInput.addEventListener('input', () => { item.text = textInput.value; });
                }

                const removeBtn = el.querySelector('.dyn-remove-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', () => {
                        items = items.filter(it => it.id !== item.id);
                        if (items.length < minItems) addItem();
                        render();
                    });
                }

                container.appendChild(el);
            });
        }

        function addItem() {
            items.push({ id: ++uid, file: null, text: '', thumbDataUrl: null });
            render();
        }

        // Fill one item's file + thumbnail (used by both single add and bulk add)
        function setFile(item, file) {
            item.file = file;
            const reader = new FileReader();
            reader.onload = (e) => {
                item.thumbDataUrl = e.target.result;
                render();
            };
            reader.readAsDataURL(file);
        }

        // Bulk-add: takes a FileList (from a multi-select <input>) and creates one
        // item per file in one go. Reuses the first empty/unfilled slot (e.g. the
        // seeded required-first item) instead of leaving it as a redundant blank row.
        function addFiles(fileList) {
            const files = Array.from(fileList || []);
            if (files.length === 0) return;
            let startIdx = 0;
            const emptySlot = items.find(it => !it.file);
            if (emptySlot) {
                setFile(emptySlot, files[0]);
                startIdx = 1;
            }
            for (let i = startIdx; i < files.length; i++) {
                const item = { id: ++uid, file: null, text: '', thumbDataUrl: null };
                items.push(item);
                setFile(item, files[i]);
            }
            render();
        }

        // seed with minItems (at least 1 if firstRequired)
        const seedCount = Math.max(minItems, firstRequired ? 1 : 0);
        for (let i = 0; i < seedCount; i++) addItem();
        if (seedCount === 0) render();

        return {
            addItem,
            addFiles,
            getItems: () => items,
        };
    }

    const sliderRepeater = createDynRepeater({
        containerId: 'sliderList',
        withText: false,
        uploadLabel: 'Slide',
        minItems: 1,
        firstRequired: true,
    });
    document.getElementById('addSlider').addEventListener('click', () => sliderRepeater.addItem());
    document.getElementById('sliderMultiInput').addEventListener('change', (e) => {
        sliderRepeater.addFiles(e.target.files);
        e.target.value = '';
    });

    const floorplanRepeater = createDynRepeater({
        containerId: 'floorplanList',
        withText: true,
        textPlaceholder: 'Unit type (e.g. 2 BHK)',
        uploadLabel: 'Floor Plan',
        minItems: 1,
    });
    document.getElementById('addFloorplan').addEventListener('click', () => floorplanRepeater.addItem());
    document.getElementById('floorplanMultiInput').addEventListener('change', (e) => {
        floorplanRepeater.addFiles(e.target.files);
        e.target.value = '';
    });

    const galleryRepeater = createDynRepeater({
        containerId: 'galleryList',
        withText: true,
        textPlaceholder: 'Caption (optional)',
        uploadLabel: 'Photo',
        minItems: 1,
    });
    document.getElementById('addGallery').addEventListener('click', () => galleryRepeater.addItem());
    document.getElementById('galleryMultiInput').addEventListener('change', (e) => {
        galleryRepeater.addFiles(e.target.files);
        e.target.value = '';
    });

    const amenityRepeater = createDynRepeater({
        containerId: 'amenityList',
        withText: true,
        textPlaceholder: 'Amenity name (e.g. Swimming Pool)',
        uploadLabel: 'Amenity',
        minItems: 1,
    });
    document.getElementById('addAmenity').addEventListener('click', () => amenityRepeater.addItem());
    document.getElementById('amenityMultiInput').addEventListener('change', (e) => {
        amenityRepeater.addFiles(e.target.files);
        e.target.value = '';
    });

    /* ---------- Submit ---------- */
    const form = document.getElementById('requestForm');
    const formError = document.getElementById('formError');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        formError.style.display = 'none';
        formError.textContent = 'Please fill in all required fields marked with *.';

        // required text fields
        const required = ['projectName', 'priceRange', 'address', 'phone', 'toEmail'];
        let missing = required.some(id => !document.getElementById(id).value.trim());

        // required image: at least the first slider image
        const sliderItems = sliderRepeater.getItems();
        if (!sliderItems.length || !sliderItems[0].file) missing = true;

        if (missing) {
            formError.style.display = 'block';
            formError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        const fd = new FormData();
        fd.append('projectName', document.getElementById('projectName').value.trim());
        fd.append('statusBadge', document.getElementById('statusBadge').value.trim());
        fd.append('priceRange', document.getElementById('priceRange').value.trim());
        fd.append('address', document.getElementById('address').value.trim());
        fd.append('landArea', document.getElementById('landArea').value.trim());
        fd.append('totalUnits', document.getElementById('totalUnits').value.trim());
        fd.append('floors', document.getElementById('floors').value.trim());
        fd.append('phone', document.getElementById('phone').value.trim());
        fd.append('toEmail', document.getElementById('toEmail').value.trim());
        fd.append('ccEmail', document.getElementById('ccEmail').value.trim());
        fd.append('bccEmail', document.getElementById('bccEmail').value.trim());
        fd.append('mapLink', document.getElementById('mapLink').value.trim());
        fd.append('locationAdvantages', JSON.stringify(locationAdvantages));
        fd.append('highlights', JSON.stringify(highlights));
        fd.append('configHeading', document.getElementById('configHeading').value.trim());
        fd.append('aboutBuilderHeading', document.getElementById('aboutBuilderHeading').value.trim());
        fd.append('aboutBuilderText', document.getElementById('aboutBuilderText').value.trim());
        fd.append('disclaimerText', document.getElementById('disclaimerText').value.trim());
        fd.append('gtagId', document.getElementById('gtagId').value.trim());
        fd.append('conversionSendTo', document.getElementById('conversionSendTo').value.trim());

        // lead delivery (email only vs email + CRM)
        fd.append('crmOption', crmOptionSelect.value);
        fd.append('crmApiKey', document.getElementById('crmApiKey').value.trim());

        // click behaviour: lightbox view vs Enquiry modal
        fd.append('floorplanLightbox', document.getElementById('floorplanLightbox').checked ? '1' : '0');
        fd.append('galleryLightbox', document.getElementById('galleryLightbox').checked ? '1' : '0');
        fd.append('amenityLightbox', document.getElementById('amenityLightbox').checked ? '1' : '0');
        fd.append('masterplanLightbox', document.getElementById('masterplanLightbox').checked ? '1' : '0');

        // which nav sections to include on the generated page
        fd.append('includePrice', document.querySelector('.section-toggle[data-section="price"]').checked ? '1' : '0');
        fd.append('includeFloorplan', document.querySelector('.section-toggle[data-section="floorplan"]').checked ? '1' : '0');
        fd.append('includeGallery', document.querySelector('.section-toggle[data-section="gallery"]').checked ? '1' : '0');
        fd.append('includeLocation', document.querySelector('.section-toggle[data-section="location"]').checked ? '1' : '0');

        // theme colors
        fd.append('colorPrimary', document.getElementById('colorPrimary').value);
        fd.append('colorSecondary', document.getElementById('colorSecondary').value);
        fd.append('colorBtn', document.getElementById('colorBtn').value);
        fd.append('colorCard', document.getElementById('colorCard').value);
        fd.append('colorOverlay', document.getElementById('colorOverlay').value);

        const priceRows = [...priceRowsEl.querySelectorAll('.price-row')].map(row => ({
            type: row.querySelector('.p-type').value.trim(),
            area: row.querySelector('.p-area').value.trim(),
            price: row.querySelector('.p-price').value.trim(),
        })).filter(r => r.type || r.area || r.price);
        fd.append('priceRows', JSON.stringify(priceRows));

        // single fixed-slot uploads
        singleUploadFields.forEach(f => {
            const file = document.getElementById(`file_${f.id}`).files[0];
            if (file) fd.append(f.id, file, file.name);
        });

        // dynamic slider images -> slider[]
        sliderItems.forEach(item => {
            if (item.file) fd.append('slider[]', item.file, item.file.name);
        });

        // dynamic floor plans -> floorplanImage[] + floorplanLabel[] (parallel arrays)
        floorplanRepeater.getItems().forEach(item => {
            if (item.file) {
                fd.append('floorplanImage[]', item.file, item.file.name);
                fd.append('floorplanLabel[]', item.text || '');
            }
        });

        // dynamic gallery images -> galleryImage[] + galleryCaption[]
        galleryRepeater.getItems().forEach(item => {
            if (item.file) {
                fd.append('galleryImage[]', item.file, item.file.name);
                fd.append('galleryCaption[]', item.text || '');
            }
        });

        // dynamic amenities -> amenityImage[] + amenityLabel[] (parallel arrays)
        amenityRepeater.getItems().forEach(item => {
            if (item.file) {
                fd.append('amenityImage[]', item.file, item.file.name);
                fd.append('amenityLabel[]', item.text || '');
            }
        });

        const refNumber = 'REQ-' + Date.now().toString().slice(-8);
        fd.append('refNumber', refNumber);
        fd.append('themeId', themeId);

        try {
            const res = await fetch(webhookUrl, { method: 'POST', body: fd });

            let data = {};
            try { data = await res.json(); } catch (_) { /* no JSON body returned */ }

            if (!res.ok) throw new Error(data.error || `Request failed (status ${res.status}).`);

            document.getElementById('formScreen').classList.add('hidden');
            document.getElementById('successScreen').style.display = 'block';
            document.getElementById('refNumber').textContent = 'Reference: ' + refNumber;

            const previewBtn = document.getElementById('previewBtn');
            const downloadBtn = document.getElementById('downloadBtn');
            if (data.previewLink) {
                previewBtn.href = data.previewLink;
                previewBtn.style.display = 'block';
            }
            if (data.downloadLink) {
                downloadBtn.href = data.downloadLink;
                downloadBtn.style.display = 'block';
                document.getElementById('successTitle').textContent = 'Your landing page is ready!';
                document.getElementById('successMsg').textContent = 'Preview it below, download the full folder (index.html, images, CSS/JS, and the enquiry form backend) as a zip, or go back and edit your details.';
            } else {
                document.getElementById('successMsg').textContent = 'We\'re generating your landing page now. You\'ll receive the download link by email shortly.';
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

    document.getElementById('editDetailsBtn').addEventListener('click', () => {
        document.getElementById('successScreen').style.display = 'none';
        document.getElementById('formScreen').classList.remove('hidden');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Request';
        formError.style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    document.getElementById('startOverBtn').addEventListener('click', () => location.reload());


    function getPreviewSummary() {
        const val = (id) => (document.getElementById(id)?.value || '').trim();
        const firstSlide = sliderRepeater.getItems().find(it => it.thumbDataUrl);
        return {
            badge: val('statusBadge'),
            title: val('projectName'),
            subtitle: val('address'),
            heroImage: firstSlide ? firstSlide.thumbDataUrl : null,
            colors: { primary: val('colorPrimary') || '#8a691c', secondary: val('colorSecondary') || '#3D3D3D' },
            rows: [
                { label: 'Price', value: val('priceRange') },
                { label: 'Land Area', value: val('landArea') },
                { label: 'Total Units', value: val('totalUnits') },
                { label: 'Floors', value: val('floors') },
                { label: 'Phone', value: val('phone') },
            ],
            chips: highlights.slice(0, 6),
        };
    }

    function unmount() {
        teardownFns.forEach(fn => fn());
        container.innerHTML = '';
        if (styleLink && styleLink.parentNode) styleLink.parentNode.removeChild(styleLink);
    }

    return { unmount, getPreviewSummary };
}

function loadStylesheet(href) {
    return new Promise((resolve) => {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.themeStylesheet = 'default';
        link.onload = () => resolve(link);
        link.onerror = () => resolve(link);
        document.head.appendChild(link);
    });
}
