// Run: RFC_BROWSER_TEST_NODE_MODULES=/path/to/node_modules node --test tests/Browser/form-wizard-validation.cjs
// Uses an isolated headless browser and real scripts; all requests are intercepted locally.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test, before, after } = require('node:test');
const { chromium } = process.env.RFC_BROWSER_TEST_NODE_MODULES
    ? require(path.join(process.env.RFC_BROWSER_TEST_NODE_MODULES, 'playwright'))
    : require('playwright');
const script = fs.readFileSync(path.join(__dirname, '../../public/js/form-wizard.js'), 'utf8');
const formSource = fs.readFileSync(path.join(__dirname, '../../resources/views/applications/partials/form.blade.php'), 'utf8');
const legacyOpening = formSource.match(/<(div|fieldset)\b[^>]*class="[^"]*\blegacy-annex-inline\b[^"]*"[^>]*>/);
assert.ok(legacyOpening, 'The real form must identify its retired inline controls.');
const summaryStart = formSource.indexOf('function countApplicationArabicWords(');
const summaryEnd = formSource.indexOf("const requestForm = document.getElementById('form-wizard1');", summaryStart);
assert.ok(summaryStart > 0 && summaryEnd > summaryStart);
const summaryScript = formSource.slice(summaryStart, summaryEnd);
const requirementSource = fs.readFileSync(path.join(__dirname, '../../resources/views/applications/partials/requirement-offcanvases.blade.php'), 'utf8');
const drawerScript = [...requirementSource.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)]
    .map(match => match[1]).find(content => content.includes('function validateAnnexDrawer('))
    .replace("@js(__('app.applications.requirement_validation_summary'))", JSON.stringify('Complete the required fields.'));
assert.ok(!drawerScript.includes('@js('), 'Only translated text may be replaced in the real drawer script.');
let browser;

before(async () => {
    browser = await chromium.launch({ channel: process.env.RFC_BROWSER_TEST_CHANNEL || 'chrome', headless: true });
});
after(async () => { await browser?.close(); });

async function fixture(errorFields, verify, options = {}) {
    const context = await browser.newContext();
    const submissions = [];
    await context.route('**/*', async route => {
        if (route.request().url() === 'https://form-verification.invalid/save' && route.request().method() === 'POST') {
            submissions.push(route.request().postData());
            await route.fulfill({ status: 200, contentType: 'text/html', body: '<p>Saved fixture</p>' });
            return;
        }
        await route.abort();
    });
    const page = await context.newPage();
    const errors = JSON.stringify(errorFields).replaceAll('&', '&amp;').replaceAll('"', '&quot;');
    const summary = options.summary || '';
    await page.setContent(`<!doctype html><html><head><style>.d-none{display:none}.tab-pane:not(.active){display:none}.application-annex-offcanvas:not(.show){display:none}</style></head><body>
      <iframe name="saved-result" hidden></iframe>
      <form id="form-wizard1" action="https://form-verification.invalid/save" method="post" enctype="multipart/form-data" target="saved-result" data-validation-error-fields="${errors}" data-validation-focus-fieldset="" data-validation-focus-tab="" data-validation-focus-drawer="">
        <ul><li id="step1">General information</li><li id="step2">Requirements</li></ul>
        <fieldset><input name="project_name" value="Preserved title">
          <div class="streamit-tabs"><button type="button" class="active" data-bs-toggle="pill" data-bs-target="#producer_tab">Producer</button><button type="button" data-bs-toggle="pill" data-bs-target="#director_tab">Director</button></div>
          <div class="tab-pane active show" id="producer_tab"><input name="producer_name" value="Fixture Producer"></div>
          <div class="tab-pane" id="director_tab"><input name="director_email" value="invalid"></div>
        </fieldset>
        <fieldset style="display:none">
          <table><tbody>
            <tr data-requirement-target="WorkContentSummary"><td><button type="button" data-bs-toggle="offcanvas" data-bs-target="#WorkContentSummary">Synopsis</button></td></tr>
            <tr data-requirement-target="ProductionTerms"><td><button type="button">Terms</button></td></tr>
            <tr data-requirement-target="LocationList"><td><button type="button">Locations</button></td></tr>
          </tbody></table>
          ${legacyOpening[0]}<textarea name="work_content_summary_synopsis" data-work-summary-input>${summary}</textarea><input name="filming_locations[0][address]" value="Retired location"></${legacyOpening[1]}>
          <div class="offcanvas offcanvas-end application-annex-offcanvas" id="WorkContentSummary" tabindex="-1"><div class="offcanvas-body"><textarea name="work_content_summary_synopsis" data-work-summary-input>${summary}</textarea></div><button type="button" data-annex-save data-bs-dismiss="offcanvas">Save synopsis</button></div>
          <div class="application-annex-offcanvas" id="ProductionTerms"><input type="hidden" name="production_terms_accepted" value="0"><input type="checkbox" name="production_terms_accepted" value="1"></div>
          <div class="application-annex-offcanvas" id="LocationList"><input name="filming_locations[0][address]"></div>
          <button type="button" class="request-wizard-next">Next</button>
          <button type="submit" formnovalidate id="save-changes">Save updates</button>
        </fieldset>
      </form></body></html>`);
    if (options.annex) {
        await page.addScriptTag({ path: path.join(__dirname, '../../public/js/libs.min.js') });
        // Real word validation functions; only the server-provided translations/rules are fixture data.
        await page.addScriptTag({ content: `
            const applicationWorkSummaryMessages = {arabicOnly:'Arabic only.', minWords:'At least :min words.', counter:':count / :min', instruction:':min words'};
            const applicationWorkSummaryMinWordsByCategory = {};
            const applicationDefaultWorkSummaryMinWords = 500;
            ${summaryScript}
            refreshApplicationWorkSummaryRules(document.getElementById('form-wizard1'));
            document.querySelectorAll('[data-work-summary-input]').forEach(bindApplicationWorkSummaryValidation);
        ` });
        await page.addScriptTag({ content: drawerScript });
        await page.addScriptTag({ path: path.join(__dirname, '../../public/js/form-submit-state.js') });
    }
    await page.addScriptTag({ content: script });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    try { await verify(page, submissions); } finally { await context.close(); }
}

test('requirement errors open the requirements page, highlight affected rows and ignore legacy/hidden duplicates', async () => {
    await fixture(['production_terms_accepted', 'work_content_summary_synopsis', 'filming_locations.0.address'], async page => {
        assert.equal(await page.locator('#step2').evaluate(node => node.classList.contains('active')), true);
        assert.equal(await page.locator('fieldset').nth(0).isVisible(), false);
        assert.equal(await page.locator('fieldset').nth(1).isVisible(), true);
        assert.equal(await page.locator('[data-requirement-target].table-danger').count(), 3);
        assert.equal(await page.locator('.legacy-annex-inline .is-invalid').count(), 0);
        assert.equal(await page.locator('input[type=hidden].is-invalid').count(), 0);
        assert.equal(await page.locator('#ProductionTerms input[type=checkbox]').getAttribute('aria-invalid'), 'true');
        assert.equal(await page.evaluate(() => document.activeElement.closest('[data-requirement-target]')?.dataset.requirementTarget), 'ProductionTerms');
        assert.equal(await page.locator('[name=project_name]').inputValue(), 'Preserved title');
    });
});

test('a nested array field selects its requirement rather than the hidden legacy copy', async () => {
    await fixture(['filming_locations.0.address'], async page => {
        assert.equal(await page.locator('fieldset').nth(1).isVisible(), true);
        assert.equal(await page.evaluate(() => document.activeElement.closest('[data-requirement-target]')?.dataset.requirementTarget), 'LocationList');
        assert.equal(await page.locator('#LocationList input').getAttribute('aria-invalid'), 'true');
    });
});

test('general field errors select and focus the correct inner tab', async () => {
    await fixture(['director_email'], async page => {
        assert.equal(await page.locator('fieldset').nth(0).isVisible(), true);
        assert.equal(await page.locator('#director_tab').isVisible(), true);
        assert.equal(await page.evaluate(() => document.activeElement.name), 'director_email');
    });
});

test('the first general error takes precedence over later requirement errors', async () => {
    await fixture(['project_name', 'production_terms_accepted'], async page => {
        assert.equal(await page.locator('fieldset').nth(0).isVisible(), true);
        assert.equal(await page.evaluate(() => document.activeElement.name), 'project_name');
        assert.equal(await page.locator('[data-requirement-target=ProductionTerms].table-danger').count(), 1);
    });
});

test('a fresh form or unmapped server error retains normal first-page navigation', async () => {
    for (const fields of [[], ['unmapped_summary_error']]) {
        await fixture(fields, async page => {
            assert.equal(await page.locator('fieldset').nth(0).isVisible(), true);
            assert.equal(await page.locator('#producer_tab').isVisible(), true);
        });
    }
});

test('correcting a server-rejected synopsis submits only the valid drawer value despite stale legacy custom validity', async () => {
    const corrected = Array(500).fill('مشهد').join(' ');
    await fixture(['work_content_summary_synopsis'], async (page, submissions) => {
        const drawer = page.locator('#WorkContentSummary');
        const input = drawer.locator('textarea');
        assert.equal(await page.locator('#step2').evaluate(node => node.classList.contains('active')), true);
        assert.equal(await page.locator('[data-requirement-target=WorkContentSummary].table-danger').count(), 1);
        await page.locator('[data-requirement-target=WorkContentSummary] button').click();
        await drawer.waitFor({ state: 'visible' });
        assert.equal(await input.evaluate(node => node.checkValidity()), false);

        await input.fill(corrected);
        assert.equal(await input.evaluate(node => node.checkValidity()), true);
        // Name-mode refreshes may assign disabled=false to a child; its retired group must still win.
        await page.locator('.legacy-annex-inline textarea').evaluate(node => { node.disabled = false; });
        assert.equal(await page.locator('.legacy-annex-inline textarea').inputValue(), 'هذا ملخص عربي قصير');
        assert.equal(await page.locator('.legacy-annex-inline textarea').evaluate(node => node.matches(':disabled')), true);
        assert.equal(await page.locator('.legacy-annex-inline textarea').evaluate(node => node.validity.customError), true);
        assert.equal(await page.locator('#form-wizard1').evaluate(node => node.checkValidity()), true);
        await drawer.locator('[data-annex-save]').click();
        await drawer.waitFor({ state: 'hidden' });
        await page.locator('.request-wizard-next').click();
        assert.equal(await page.locator('#form-wizard1 > fieldset').nth(1).isVisible(), true, 'Nested disabled fieldsets must not become wizard steps.');

        const submitted = page.waitForRequest(request => request.url() === 'https://form-verification.invalid/save' && request.method() === 'POST');
        const saved = page.waitForResponse(response => response.url() === 'https://form-verification.invalid/save' && response.status() === 200);
        await page.locator('#save-changes').click();
        const request = await submitted;
        await saved;
        const body = request.postData();
        assert.equal((body.match(/name="work_content_summary_synopsis"/g) || []).length, 1);
        assert.ok(body.includes(corrected));
        assert.ok(!body.includes('هذا ملخص عربي قصير'));
        assert.ok(!body.includes('Retired location'));
        assert.equal(submissions.length, 1);
    }, { annex: true, summary: 'هذا ملخص عربي قصير' });
});

test('required controls in a CSS-hidden inactive tab still block advancing and receive focus', async () => {
    await fixture([], async page => {
        await page.locator('#director_tab input').evaluate(node => { node.required = true; node.value = ''; });
        assert.equal(await page.locator('#director_tab').isVisible(), false);
        await page.locator('#step2').click();
        assert.equal(await page.locator('#step1').evaluate(node => node.classList.contains('active')), true);
        assert.equal(await page.locator('#director_tab').isVisible(), true);
        assert.equal(await page.evaluate(() => document.activeElement.name), 'director_email');
    });
});
