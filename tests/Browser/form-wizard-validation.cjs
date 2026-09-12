// Run: RFC_BROWSER_TEST_NODE_MODULES=/path/to/node_modules node --test tests/Browser/form-wizard-validation.cjs
// Uses an isolated headless browser, static fixtures and the real wizard script; no network requests.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { test, before, after } = require('node:test');
const { chromium } = process.env.RFC_BROWSER_TEST_NODE_MODULES
    ? require(path.join(process.env.RFC_BROWSER_TEST_NODE_MODULES, 'playwright'))
    : require('playwright');
const script = fs.readFileSync(path.join(__dirname, '../../public/js/form-wizard.js'), 'utf8');
let browser;

before(async () => {
    browser = await chromium.launch({ channel: process.env.RFC_BROWSER_TEST_CHANNEL || 'chrome', headless: true });
});
after(async () => { await browser?.close(); });

async function fixture(errorFields, verify) {
    const context = await browser.newContext();
    await context.route('**/*', route => route.abort());
    const page = await context.newPage();
    const errors = JSON.stringify(errorFields).replaceAll('&', '&amp;').replaceAll('"', '&quot;');
    await page.setContent(`<!doctype html><html><head><style>.d-none{display:none}.tab-pane:not(.active){display:none}.application-annex-offcanvas{display:none}</style></head><body>
      <form id="form-wizard1" data-validation-error-fields="${errors}" data-validation-focus-fieldset="" data-validation-focus-tab="" data-validation-focus-drawer="">
        <ul><li id="step1">General information</li><li id="step2">Requirements</li></ul>
        <fieldset><input name="project_name" value="Preserved title">
          <div class="streamit-tabs"><button type="button" class="active" data-bs-toggle="pill" data-bs-target="#producer_tab">Producer</button><button type="button" data-bs-toggle="pill" data-bs-target="#director_tab">Director</button></div>
          <div class="tab-pane active show" id="producer_tab"><input name="producer_name" value="Fixture Producer"></div>
          <div class="tab-pane" id="director_tab"><input name="director_email" value="invalid"></div>
        </fieldset>
        <fieldset style="display:none">
          <table><tbody>
            <tr data-requirement-target="WorkContentSummary"><td><button type="button">Synopsis</button></td></tr>
            <tr data-requirement-target="ProductionTerms"><td><button type="button">Terms</button></td></tr>
            <tr data-requirement-target="LocationList"><td><button type="button">Locations</button></td></tr>
          </tbody></table>
          <div class="legacy-annex-inline d-none"><textarea name="work_content_summary_synopsis"></textarea><input name="filming_locations[0][address]"></div>
          <div class="application-annex-offcanvas" id="WorkContentSummary"><textarea name="work_content_summary_synopsis"></textarea></div>
          <div class="application-annex-offcanvas" id="ProductionTerms"><input type="hidden" name="production_terms_accepted" value="0"><input type="checkbox" name="production_terms_accepted" value="1"></div>
          <div class="application-annex-offcanvas" id="LocationList"><input name="filming_locations[0][address]"></div>
        </fieldset>
      </form></body></html>`);
    await page.addScriptTag({ content: script });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    try { await verify(page); } finally { await context.close(); }
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
