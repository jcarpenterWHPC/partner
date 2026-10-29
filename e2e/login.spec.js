// The sign-in mechanism as a browser sees it. No real account is used: an unknown
// address reaches the same code screen, and a code typed there is refused — which is
// the whole point of not revealing who is registered.
const { test, expect } = require('./helpers');

test('a visitor is sent to the sign-in page, which carries the brand', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveURL(/\/login\?expired=1$/);
  await expect(page.locator('h2.whpc-login__title')).toHaveText('Sign in');
  await expect(page.locator('#email')).toBeVisible();
  await expect(page.locator('.whpc-login__brand-logo')).toBeVisible();
  const font = await page.locator('h2.whpc-login__title').evaluate((el) => getComputedStyle(el).fontFamily);
  expect(font).toMatch(/Source Sans 3/);
});

test('an unknown address gets the code screen and a wrong code is refused', async ({ page }) => {
  await page.goto('/login');
  await page.fill('#email', 'nobody-e2e@example.com');
  await page.click('#loginForm button[type=submit]');
  await expect(page).toHaveURL(/\/login\/verify$/);
  await expect(page.locator('.whpc-login__subtitle')).toContainText('n***@example.com');
  await page.fill('#code', '000000');
  await page.click('#verifyForm button[type=submit]');
  await expect(page).toHaveURL(/\/login\/verify$/);
  await expect(page.locator('.whpc-alert--error')).toContainText('not right');
});

test('a malformed address is refused on the sign-in page', async ({ page }) => {
  await page.goto('/login');
  await page.fill('#email', 'not an address');
  await page.evaluate(() => document.getElementById('loginForm').noValidate = true);
  await page.click('#loginForm button[type=submit]');
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.locator('.whpc-alert--error')).toBeVisible();
});

test('the health check answers plainly', async ({ request }) => {
  const r = await request.get('/healthcheck.html');
  expect(r.status()).toBe(200);
  expect((await r.text()).trim()).toBe('ok');
});
