import { test, expect, type Page } from '@playwright/test';

// PHP diagnostics (wp-env runs with display_errors on) are rendered into the
// HTML. Every issue fixed in this batch (e.g. #18 uninitialized string offset,
// #21 just-in-time textdomain) was visible this way, so assert on them.
const PHP_DIAGNOSTIC = /(Notice:|Warning:|Deprecated:|Fatal error:)/;

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
	const hits = html.match(new RegExp(PHP_DIAGNOSTIC.source + '[^<]{0,120}', 'g')) ?? [];
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
	const switcher = page
		.locator('.transposh_switcher, #transposh-wrapper, .tp-lang, [class*="tpw"]')
		.first();
	await expect(switcher).toBeVisible();
	await expectCleanPhp(await page.content());
});

test('switching language via ?lang=fr works without diagnostics', async ({ page }) => {
	const response = await page.goto('/?lang=fr');
	expect(response?.status()).toBe(200);
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
	const response = await page.goto('/wp-admin/options-general.php?page=tp_modules');
	expect([200, 302]).toContain(response?.status() ?? 0);
	await expectCleanPhp(await page.content());
	await expectCleanConsole(page);
});
