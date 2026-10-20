const { test, expect } = require('@playwright/test');

// Discovery only. Never click POST login buttons or modify NCR records.
test('NCR /dev renders active mock accounts (read-only)', async ({ page }, testInfo) => {
  const base = new URL(testInfo.project.use.baseURL);
  if (!['localhost', '127.0.0.1'].includes(base.hostname)) {
    throw new Error('Smoke test accepts only local Laravel server (localhost/127.0.0.1).');
  }

  let response;
  try {
    response = await page.goto('/dev', { waitUntil: 'domcontentloaded', timeout: 10000 });
  } catch (error) {
    throw new Error('Laravel /dev is not reachable. Start the existing Laravel server on port 8001 before testing. Details: ' + error.message);
  }

  expect(response, 'GET /dev must return a response').not.toBeNull();
  expect(response.status(), 'GET /dev should be HTTP 200').toBe(200);
  await expect(page.getByRole('heading', { name: 'บัญชีทดสอบ' })).toBeVisible();

  const forms = page.locator('form[action$="/dev"][method="POST"], form[action$="/dev"][method="post"]');
  const count = await forms.count();
  expect(count, 'Expected at least one active test account on /dev').toBeGreaterThan(0);

  const accounts = [];
  for (let i = 0; i < count; i++) {
    const form = forms.nth(i);
    const userId = await form.locator('button[name="user_id"]').getAttribute('value');
    const label = await form.locator('button[name="user_id"]').getAttribute('aria-label');
    expect(userId, 'Missing user_id for account #' + (i + 1)).toBeTruthy();
    expect(label, 'Missing login label for account #' + (i + 1)).toBeTruthy();
    accounts.push({ userId, label });
  }

  console.log('Discovered ' + accounts.length + ' active account(s) on /dev (read-only):');
  accounts.forEach((account) => console.log('- ' + account.label));
  await testInfo.attach('dev-accounts.json', {
    body: Buffer.from(JSON.stringify(accounts, null, 2), 'utf8'),
    contentType: 'application/json',
  });
});