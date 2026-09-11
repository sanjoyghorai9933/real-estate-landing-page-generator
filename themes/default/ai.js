/** Optional AI assistant for the default theme. */
export function mountAi(container, ctx) {
    const endpoint = ctx.aiUrl || 'backend/ai.php';
    const wrap = document.createElement('div');
    wrap.className = 'g-ai-wrap';
    wrap.innerHTML = `
        <div class="g-ai-card"><div><strong>AI Content Assistant</strong><span>Generate polished property copy from a short brief.</span></div><button type="button" class="g-ai-btn">Generate with AI</button></div>
        <div class="g-ai-modal g-hidden" aria-hidden="true"><div class="g-ai-dialog" role="dialog" aria-modal="true" aria-labelledby="gAiTitle">
            <div class="g-ai-head"><div><h3 id="gAiTitle">Generate landing-page content</h3><p>Paste the facts you already have. AI will draft copy but will not invent missing facts.</p></div><button type="button" class="g-ai-close" aria-label="Close">×</button></div>
            <textarea class="g-ai-brief" rows="8" placeholder="Example: Southern Star, premium 3/4 BHK apartments in Begur Road. 33 acres, 2400+ units, 2B+G+25 floors. Price from 2.9 Cr. Developer: ..."></textarea>
            <div class="g-ai-actions"><span class="g-ai-status"></span><button type="button" class="g-ai-cancel">Cancel</button><button type="button" class="g-ai-generate">Generate content</button></div>
            <div class="g-ai-result g-hidden"><div class="g-ai-result-title">Draft ready</div><p>Review the generated values before applying them to the form.</p><div class="g-ai-preview"></div><button type="button" class="g-ai-apply">Apply to form</button></div>
        </div></div>`;
    container.prepend(wrap);

    const modal = wrap.querySelector('.g-ai-modal');
    const brief = wrap.querySelector('.g-ai-brief');
    const status = wrap.querySelector('.g-ai-status');
    const result = wrap.querySelector('.g-ai-result');
    const preview = wrap.querySelector('.g-ai-preview');
    let generated = null;
    const close = () => { modal.classList.add('g-hidden'); modal.setAttribute('aria-hidden', 'true'); };

    wrap.querySelector('.g-ai-btn').addEventListener('click', () => { modal.classList.remove('g-hidden'); modal.setAttribute('aria-hidden', 'false'); setTimeout(() => brief.focus(), 0); });
    wrap.querySelector('.g-ai-close').addEventListener('click', close);
    wrap.querySelector('.g-ai-cancel').addEventListener('click', close);

    wrap.querySelector('.g-ai-generate').addEventListener('click', async () => {
        const text = brief.value.trim();
        if (text.length < 10) { status.textContent = 'Add a little more project information.'; return; }
        status.textContent = 'Generating…'; result.classList.add('g-hidden');
        try {
            const res = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ themeId: ctx.themeId || 'default', brief: text }) });
            const body = await res.json();
            if (!res.ok || !body.success) throw new Error(body.error || 'AI request failed.');
            generated = body.content; renderPreview(generated); result.classList.remove('g-hidden'); status.textContent = 'Draft generated.';
        } catch (err) { status.textContent = err.message || 'Could not generate content.'; }
    });

    wrap.querySelector('.g-ai-apply').addEventListener('click', () => {
        if (!generated) return; applyContent(generated); close(); status.textContent = 'Applied to form.'; container.dispatchEvent(new Event('input', { bubbles: true }));
    });

    function renderPreview(data) {
        const rows = [['Project', data.projectName], ['Price', data.priceRange], ['Location', data.address], ['Land area', data.landArea], ['Units', data.totalUnits], ['Floors', data.floors]].filter(([, v]) => v);
        preview.innerHTML = rows.map(([k, v]) => `<div><b>${esc(k)}</b><span>${esc(v)}</span></div>`).join('') + (data.highlights?.length ? `<p><b>Highlights:</b> ${data.highlights.map(esc).join(' • ')}</p>` : '');
    }

    function applyContent(data) {
        ['projectName','statusBadge','priceRange','address','landArea','totalUnits','floors','configHeading','aboutBuilderHeading','aboutBuilderText'].forEach(id => setValue(id, data[id] || ''));
        setTags('highlightInput', data.highlights || []);
        setTags('locationAdvInput', data.locationAdvantages || []);
        const rows = document.getElementById('priceRows'); const add = document.getElementById('addPriceRow');
        if (rows && add && Array.isArray(data.priceRows) && data.priceRows.length) {
            rows.innerHTML = '';
            data.priceRows.forEach(row => { add.click(); const last = rows.lastElementChild; if (!last) return; last.querySelector('.p-type').value = row.type || ''; last.querySelector('.p-area').value = row.area || ''; last.querySelector('.p-price').value = row.price || ''; });
        }
    }
    function setValue(id, value) { const el = document.getElementById(id); if (!el) return; el.value = value; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); }
    function setTags(inputId, values) { const input = document.getElementById(inputId); if (!input) return; (values || []).slice(0, 8).forEach(value => { input.value = value; input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true })); }); input.value = ''; }
    function esc(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c])); }

    return { unmount() { wrap.remove(); } };
}
