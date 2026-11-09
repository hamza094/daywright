// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Workflow 1: Authentication E2E Smoke Tests
 * Covers guest route guards, login, session storage, and logout.
 */
test.describe('Workflow 1: Authentication', () => {
  const testEmail = process.env.E2E_USER_EMAIL || 'little.marilie@example.com';
  const testPassword = process.env.E2E_USER_PASSWORD || 'Berry@999';

  test.beforeEach(async ({ page }) => {
    // Start each test with clean storage/session
    await page.context().clearCookies();
  });

  test('Route guard redirects unauthenticated guests to /login', async ({ page }) => {
    // Attempt direct navigation to protected dashboard
    await page.goto('/dashboard', { waitUntil: 'domcontentloaded' });

    // Assert redirection to /login
    await expect(page).toHaveURL(/\/login/);

    // Verify login form is visible
    await expect(page.locator('#email')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Sign In' })).toBeVisible();
  });

  test('Login with valid credentials redirects to /dashboard and stores session', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });

    // Fill credentials
    await page.locator('#email').fill(testEmail);
    await page.locator('#password').fill(testPassword);

    // Submit form and wait for network/navigation
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/session/login'), { timeout: 20000 }),
      page.getByRole('button', { name: 'Sign In' }).click(),
    ]);

    // Assert successful redirect to dashboard
    await expect(page).toHaveURL(/\/dashboard/, { timeout: 20000 });

    // Assert session cookie is stored
    const cookies = await page.context().cookies();
    const hasSessionCookie = cookies.some(
      (c) =>
        c.name.toLowerCase().includes('session') ||
        c.name.toLowerCase().includes('xsrf') ||
        c.name.toLowerCase().includes('sanctum'),
    );
    expect(hasSessionCookie).toBeTruthy();

    // Verify Vuex logged-in state in local storage / DOM
    const vuexState = await page.evaluate(() => {
      const stored = localStorage.getItem('vuex');
      return stored ? JSON.parse(stored) : null;
    });

    if (vuexState?.currentUser) {
      expect(vuexState.currentUser.loggedIn).toBe(true);
    }
  });

  test('Logout purges local storage/session and redirects to /login', async ({ page }) => {
    // Step 1: Log in first
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#email').fill(testEmail);
    await page.locator('#password').fill(testPassword);

    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/session/login'), { timeout: 20000 }),
      page.getByRole('button', { name: 'Sign In' }).click(),
    ]);

    await expect(page).toHaveURL(/\/dashboard/, { timeout: 20000 });

    // Step 2: Trigger logout from sidebar
    const logoutBtn = page.getByRole('link', { name: /logout/i });
    await expect(logoutBtn).toBeVisible({ timeout: 15000 });

    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/session/logout'), { timeout: 35000 }),
      logoutBtn.click(),
    ]);

    // Assert redirected to /login
    await expect(page).toHaveURL(/\/login/, { timeout: 20000 });

    // Assert local storage vuex session is purged or loggedIn is false
    const vuexState = await page.evaluate(() => {
      const stored = localStorage.getItem('vuex');
      return stored ? JSON.parse(stored) : null;
    });

    if (vuexState?.currentUser) {
      expect(vuexState.currentUser.loggedIn).toBe(false);
    }

    // Step 3: Attempting to visit /dashboard now redirects back to /login
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
  });
});
