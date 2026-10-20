const { test, expect } = require('@playwright/test');

// Login is a real write: the app updates last_login and creates audit records.
// Opt-in explicitly; this suite does not create, edit or delete NCR records.
const users = [
  'production.reporter01@mock.vertexpl.local',
  'production.manager01@mock.vertexpl.local',
  'deputy.managing.director01@mock.vertexpl.local',
  'engineering.staff01@mock.vertexpl.local',
  'warehouse.staff01@mock.vertexpl.local',
  'purchasing.coordinator01@mock.vertexpl.local',
  'qa.supervisor01@mock.vertexpl.local',
  'plant.manager01@mock.vertexpl.local',
];

test('NCR /dev: eight primary roles login in isolated browser contexts', async ({ browser }, testInfo) => {
  test.skip(process.env.NCR_E2E_LOGIN !== 'YES', 'Set NCR_E2E_LOGIN=YES to allow dev login audit/last_login writes.');
  test.setTimeout(150000);

  const baseURL = testInfo.project.use.baseURL;
  const target = new URL(baseURL);
  expect(['localhost', '127.0.0.1']).toContain(target.hostname);
  expect(target.port).toBe('8001');

  const records = [];
  const failures = [];
  for (const email of users) {
    const context = await browser.newContext({ baseURL });
    const page = await context.newPage();
    try {
      const response = await page.goto('/dev', { waitUntil: 'domcontentloaded', timeout: 12000 });
      expect(response && response.status(), 'GET /dev for ' + email).toBe(200);
      // Match the real button aria-label without guessing numeric user IDs.
      const button = page.locator('form[action$="/dev"] button[name="user_id"]')
        .filter({ hasText: 'เข้าใช้บัญชีนี้' })
        .and(page.getByRole('button', { name: 'เข้าสู่ระบบด้วยบัญชี ' + email, exact: true }));
      await expect(button, 'Account should be active on /dev: ' + email).toHaveCount(1);

      await button.click();
      await page.waitForLoadState('domcontentloaded');
      const profile = await page.goto('/profile', { waitUntil: 'domcontentloaded', timeout: 12000 });
      expect(profile && profile.status(), 'GET /profile after login').toBe(200);
      await expect(page.locator('#profile_email')).toHaveValue(email);
      records.push({ email, status: 'PASS' });
      console.log('PASS: ' + email);
    } catch (error) {
      const message = String(error && error.message || error).slice(0, 600);
      records.push({ email, status: 'FAIL', message });
      failures.push(email + ': ' + message);
      console.log('FAIL: ' + email + ': ' + message);
      await page.screenshot({ path: testInfo.outputPath('failed-' + email.replace(/[^a-z0-9]/gi, '_') + '.png'), fullPage: true }).catch(() => {});
    } finally {
      await context.close();
    }
  }

  await testInfo.attach('login-results.json', {
    body: Buffer.from(JSON.stringify(records, null, 2), 'utf8'),
    contentType: 'application/json',
  });
  expect(failures, 'All primary role logins should succeed').toEqual([]);
});