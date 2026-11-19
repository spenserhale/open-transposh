import { defineConfig } from '@playwright/test';

// QA suite for the wp-env dev site (npm run env:start, http://localhost:8888).
// Run with: npx playwright test -c tests/e2e/playwright.config.ts
export default defineConfig({
	testDir: '.',
	timeout: 30_000,
	expect: { timeout: 10_000 },
	fullyParallel: false,
	workers: 1,
	retries: 0,
	reporter: [['list']],
	use: {
		baseURL: 'http://localhost:8888',
		headless: true,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [{ name: 'chromium', use: { browserName: 'chromium' } }],
});
