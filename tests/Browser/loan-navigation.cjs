const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('../../storage/app/stock-ui-tests/node_modules/jsdom');

(async function () {
    const dom = new JSDOM(`<button id="loanSidebarToggle">Menu</button><aside id="loanManagementSidebar">
        <input id="lmSidebarSearch">
        <div class="lm-menu-section" data-section="installments"><button class="lm-menu-section-title">Installments</button><a class="lm-menu-link" href="/loans" aria-current="page" data-lm-menu-text="all installments">All Installments</a></div>
        <div class="lm-menu-section" data-section="reports"><button class="lm-menu-section-title">Reports</button><a class="lm-menu-link" href="/reports" data-lm-menu-text="collection reports">Collection Reports</a></div>
        </aside>`, { url: 'https://example.test', runScripts: 'outside-only' });
    const { window } = dom;
    const document = window.document;
    await new Promise(resolve => document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', resolve, { once: true }) : resolve());
    window.localStorage.setItem('loan-sidebar-collapsed-sections', JSON.stringify({ installments: true, reports: true }));
    window.eval(fs.readFileSync(path.join(__dirname, '../../Modules/LoanManagement/Resources/assets/js/loan-management.js'), 'utf8'));
    const installments = document.querySelector('[data-section="installments"]');
    const reports = document.querySelector('[data-section="reports"]');
    assert.equal(installments.classList.contains('is-collapsed'), false, 'Current workspace stays visible');
    assert.equal(reports.classList.contains('is-collapsed'), true, 'Saved collapse preference is restored');
    reports.querySelector('button').click();
    assert.equal(reports.querySelector('button').getAttribute('aria-expanded'), 'true');
    assert.equal(JSON.parse(window.localStorage.getItem('loan-sidebar-collapsed-sections')).reports, false);
    reports.querySelector('button').click();
    const search = document.getElementById('lmSidebarSearch');
    search.value = 'reports';
    search.dispatchEvent(new window.Event('input', { bubbles: true }));
    await new Promise(resolve => window.setTimeout(resolve, 100));
    assert.equal(reports.classList.contains('is-collapsed'), false, 'Search reveals a collapsed match');
    assert.equal(installments.style.display, 'none');
    search.value = '';
    search.dispatchEvent(new window.Event('input', { bubbles: true }));
    await new Promise(resolve => window.setTimeout(resolve, 100));
    assert.equal(reports.classList.contains('is-collapsed'), true, 'Clearing search restores the preference');
    assert.equal(installments.style.display, '');
    window.innerWidth = 600;
    document.getElementById('loanSidebarToggle').click();
    assert.equal(document.body.classList.contains('lm-sidebar-open'), true);
    document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    assert.equal(document.body.classList.contains('lm-sidebar-open'), false);
    dom.window.close();
    console.log('Navigation UI checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
