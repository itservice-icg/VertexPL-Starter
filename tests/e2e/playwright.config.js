const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  timeout: 30000,
  expect: { timeout: 10000 },
  retries: 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: process.env.NCR_E2E_HTML_REPORT_DIR || 'reports/playwright-html', open: 'never' }],
  ],
  outputDir: process.env.NCR_E2E_OUTPUT_DIR || 'test-results',
  use: {
    baseURL: process.env.NCR_E2E_BASE_URL || 'http://127.0.0.1:8001',
    actionTimeout: 10000,
    navigationTimeout: 20000,
    browserName: 'chromium',
    headless: process.env.NCR_E2E_HEADED !== 'YES',
    screenshot: 'only-on-failure',
    // The runner creates and closes isolated BrowserContexts itself, so it owns
    // their trace lifecycle explicitly in workflow.spec.js.
    trace: 'off',
  },
});
