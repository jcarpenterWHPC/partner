// The roster as a PMC user and as an admin see it in a browser. Accounts and the sign-in
// link come from `php spark` on the box under test (partner:login-link refuses in
// production, so this suite cannot run there).
const { execFileSync } = require('child_process');
const path = require('path');
const { test, expect } = require('./helpers');

const PHP = process.env.PHP_CLI || 'php';
const SPARK = path.resolve(__dirname, '..', 'spark');
const RUN = Date.now().toString(36);
const PMC = 'E2E Management ' + RUN;
const USER = `e2e-${RUN}@example.com`;
const ADMIN = `e2e-admin-${RUN}@example.com`;

function spark(args) {
  return execFileSync(PHP, [SPARK, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}

async function signIn(page, email) {
  const link = spark(['partner:login-link', email]).split('\n').pop().trim();
  const u = new URL(link);
  await page.goto(u.pathname);
  await expect(page).toHaveURL(/\/$/);
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
  // A company of our own with one property, so nothing real is touched.
  spark(['partner:e2e-seed', PMC, 'E2E-' + RUN, '--admin', ADMIN]);
  spark(['partner:user', 'add', USER, 'E2E Partner', '--pmc', PMC]);
});

test('a PMC user adds a person with a property, edits them and sees the history', async ({ page }) => {
  await signIn(page, USER);
  await expect(page.locator('#cardRoster')).toBeVisible();
  await expect(page.locator('#cardUsers')).toHaveCount(0);
  await page.click('#cardRoster');
  await expect(page).toHaveURL(/\/roster$/);
  await page.click('#addPerson');
  await page.fill('#name', 'Pat E2E');
  await page.selectOption('#position', 'Site Manager');
  await page.fill('#email', `pat-${RUN}@example.com`);
  await page.fill('#mobile_phone', '608-555-0199');
  await page.fill('#start_date', '2026-10-01');
  await page.fill('#propFilter', 'E2E-' + RUN);
  const box = page.locator('#propChecks .pp-check:visible input');
  await expect(box).toHaveCount(1);
  await box.check();
  await page.click('#savePerson');
  await expect(page).toHaveURL(/\/roster$/);
  await expect(page.locator('.whpc-alert--success')).toContainText('Pat E2E was added');
  const row = page.locator('#rosterTable tbody tr', { hasText: 'Pat E2E' });
  await expect(row).toHaveCount(1);
  await expect(row.locator('.pp-props')).toContainText('E2E Property');

  await row.locator('a[aria-label^="Edit"]').click();
  await page.fill('#title', 'Community Manager');
  await page.click('#savePerson');
  await expect(page.locator('.whpc-alert--success')).toContainText('updated');
  await page.locator('#rosterTable tbody tr', { hasText: 'Pat E2E' }).locator('a[aria-label^="History"]').click();
  const items = page.locator('#historyList .pp-timeline__item');
  await expect(items).toHaveCount(2);
  await expect(items.first()).toContainText('Updated');
  await expect(items.first()).toContainText('Community Manager');
  await expect(items.last()).toContainText('Added');
});

test('a departure keeps the person, hidden by default', async ({ page }) => {
  await signIn(page, USER);
  await page.goto('/roster');
  await page.locator('#rosterTable tbody tr', { hasText: 'Pat E2E' }).locator('a[aria-label^="Edit"]').click();
  await page.fill('#end_date', '2026-10-31');
  await page.click('#savePerson');
  await expect(page.locator('.whpc-alert--success')).toContainText('departed');
  await expect(page.locator('#rosterTable tbody tr', { hasText: 'Pat E2E' })).toHaveCount(0);
  await page.click('#toggleDeparted');
  const row = page.locator('#rosterTable tbody tr', { hasText: 'Pat E2E' });
  await expect(row).toHaveCount(1);
  await expect(row.locator('.pp-badge--departed')).toContainText('2026-10-31');
});

test('a bad form is sent back with the fields marked', async ({ page }) => {
  await signIn(page, USER);
  await page.goto('/roster/new');
  await page.evaluate(() => document.getElementById('personForm').noValidate = true);
  await page.fill('#email', 'not-an-address');
  await page.click('#savePerson');
  await expect(page.locator('.whpc-alert--error')).toBeVisible();
  await expect(page.locator('#name ~ .pp-fielderror')).toBeVisible();
  await expect(page.locator('#position ~ .pp-fielderror')).toBeVisible();
  await expect(page.locator('#email ~ .pp-fielderror')).toBeVisible();
});

test('an admin sees every company, the accounts and the change log', async ({ page }) => {
  await signIn(page, ADMIN);
  await expect(page.locator('#cardUsers')).toBeVisible();
  await page.goto('/admin/pmcs');
  const pmcRow = page.locator('#pmcTable tbody tr', { hasText: PMC });
  await expect(pmcRow).toHaveCount(1);
  await pmcRow.locator('a').click();
  await expect(page).toHaveURL(/\/roster/);
  await expect(page.locator('#pmcPick')).toBeVisible();
  await page.click('#toggleDeparted');
  await expect(page.locator('#rosterTable tbody tr', { hasText: 'Pat E2E' })).toHaveCount(1);
  await page.goto('/admin/users');
  await expect(page.locator('#usersTable tbody tr', { hasText: USER })).toHaveCount(1);
  await page.goto('/admin/changes');
  await expect(page.locator('#changesTable tbody tr', { hasText: 'Pat E2E' }).first()).toBeVisible();
});

test('a PMC user is kept out of the admin pages', async ({ page }) => {
  await signIn(page, USER);
  const r = await page.goto('/admin/users');
  expect(r.status()).toBe(403);
});

test.afterAll(() => {
  spark(['partner:e2e-seed', PMC, '--clean']);
});
