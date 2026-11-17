import { test, expect, type Page } from '@playwright/test';

// PHP diagnostics (wp-env runs with display_errors on) are rendered into the
// HTML. Every issue fixed in this batch (e.g. #18 uninitialized string offset,
// #21 just-in-time textdomain) was visible this way, so assert on them.
// Tags are stripped first because html_errors renders "<b>Warning</b>:".
const PHP_DIAGNOSTIC = /(?:Notice|Warning|Deprecated|Fatal error):/;

function consoleErrorsOf(page: Page): string[] {
	return (page as unknown as { __consoleErrors?: string[] }).__consoleErrors ?? [];
}

test.beforeEach(async ({ page }) => {
	const consoleErrors: string[] = [];
	page.on('console', (msg) => {
		if (msg.type() === 'error') consoleErrors.push(msg.text());
	});
	page.on('pageerror', (err) => consoleErrors.push(String(err)));
	(page as unknown as { __consoleErrors: string[] }).__consoleErrors = consoleErrors;
});

async function expectCleanPhp(html: string) {
	const text = html.replace(/<[^>]*>/g, '');
	const hits = text.match(new RegExp(PHP_DIAGNOSTIC.source + '[^\\n]{0,120}', 'g')) ?? [];
	expect(hits, `PHP diagnostics found in rendered HTML: ${hits.join(' | ')}`).toEqual([]);
}

async function expectCleanConsole(page: Page) {
	const errors = consoleErrorsOf(page);
	expect(errors, `Browser console errors: ${errors.join(' | ')}`).toEqual([]);
}

async function loginAsAdmin(page: Page) {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', 'qa_admin');
	await page.fill('#user_pass', 'qa_admin_password_123');
	await page.click('input[name="wp-submit"]');
	// a failed login stays on wp-login.php; a successful one lands in wp-admin
	await expect(page).toHaveURL(/wp-admin/);
	await page.waitForLoadState('networkidle');
}

test('homepage renders without PHP diagnostics (#18, #21)', async ({ page }) => {
	const response = await page.goto('/');
	expect(response?.status()).toBe(200);
	await expectCleanPhp(await page.content());
	await expectCleanConsole(page);
});

test('language switcher widget renders and links languages', async ({ page }) => {
	await page.goto('/');
	// The switcher renders inside the sidebar widget container; the default style
	// exposes language targets as options of a select named "lang". Other styles
	// keep the same widget container, so assert on the container plus the select.
	const widget = page.locator('.widget.widget_transposh');
	await expect(widget).toBeVisible();
	const switcherSelect = widget.locator('select[name="lang"]');
	await expect(switcherSelect).toBeVisible();
	const optionValues = await switcherSelect
		.locator('option')
		.evaluateAll((options) => options.map((option) => (option as HTMLOptionElement).value));
	expect(
		optionValues.length,
		`switcher offered no language targets; options were: ${optionValues.join(', ')}`,
	).toBeGreaterThan(0);
	await expectCleanPhp(await page.content());
});

test('switching language via ?lang=fr works without diagnostics', async ({ page }) => {
	const response = await page.goto('/?lang=fr');
	expect(response?.status()).toBe(200);
	await expectCleanPhp(await page.content());
	await expectCleanConsole(page);
});

test('switcher select navigates to the chosen language', async ({ page }) => {
	await page.goto('/');
	const switcherSelect = page.locator('select[name="lang"]');
	const frenchOption = switcherSelect.locator('option[value*="lang=fr"]');
	const optionValues = await switcherSelect
		.locator('option')
		.evaluateAll((options) => options.map((option) => (option as HTMLOptionElement).value));
	await expect(
		frenchOption,
		`no French target in the switcher; options were: ${optionValues.join(', ')}`,
	).toBeAttached();
	await Promise.all([
		page.waitForURL(/lang=fr/),
		switcherSelect.selectOption({ value: await frenchOption.getAttribute('value') }),
	]);
	expect(page.url()).toContain('lang=fr');
	const response = await page.reload();
	expect(response?.status()).toBe(200);
	// The widget marks the current language as selected after the switch.
	await expect(switcherSelect.locator('option[selected], option:checked').first()).toHaveText(
		/Français/i,
	);
	await expectCleanPhp(await page.content());
	await expectCleanConsole(page);
});

test('translation edit mode shows the transposh script and t_jp params', async ({ page }) => {
	await loginAsAdmin(page);
	const response = await page.goto('/?tpedit=1&lang=fr');
	expect(response?.status()).toBe(200);
	const transposhScript = page.locator('script#transposh-js');
	await expect(transposhScript).toHaveAttribute('src', /transposh/);
	// Playwright's text matchers read innerText, which is empty for <script>
	// elements, so read textContent directly.
	const scriptParams = (await page.locator('script#transposh-js-extra').textContent()) ?? '';
	expect(scriptParams).toContain('var t_jp =');
	expect(scriptParams).toContain('ajaxurl');
	await expectCleanPhp(await page.content());
	await expectCleanConsole(page);
});

test('translation edit mode loads for an administrator (#29, #31 surfaces)', async ({ page }) => {
	await loginAsAdmin(page);
	const response = await page.goto('/?tpedit=1&lang=fr');
	expect(response?.status()).toBe(200);
	await expectCleanPhp(await page.content());
	await expectCleanConsole(page);
});

test('admin settings page loads without diagnostics', async ({ page }) => {
	await loginAsAdmin(page);
	// the settings pages hang off the plugin's own top-level menu, not options-general.php
	const response = await page.goto('/wp-admin/admin.php?page=tp_settings');
	expect([200, 302]).toContain(response?.status() ?? 0);
	// a redirect back to the login page would also answer 200, so pin the destination
	await expect(page).toHaveURL(/admin\.php\?page=tp_settings/);
	// the settings form carries the transposh_nonce field (see docs/guides/security.md);
	// it is a hidden input, so assert presence rather than visibility
	await expect(page.locator('input[name="transposh_nonce"]')).toBeAttached();
	await expectCleanPhp(await page.content());
	await expectCleanConsole(page);
});
