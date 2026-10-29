// Playwright config for the WHPC Partner Portal e2e tests. The app is served by the
// local Apache on the box the suite runs on; see e2e/helpers.js for how the app's
// absolute https URLs are routed to it.
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './e2e',
  timeout: 30000,
  expect: { timeout: 8000 },
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.WHPC_E2E_BASE || 'https://partner-test.whpc.com',
    ignoreHTTPSErrors: true,
    viewport: { width: 1400, height: 900 },
    launchOptions: { args: ['--no-sandbox'] },
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
