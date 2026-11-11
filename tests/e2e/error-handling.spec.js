// @ts-check
import { test, expect } from '@playwright/test';

/**
 * Helper to log in a user and wait for dashboard redirect.
 */
async function loginUser(page, email, password) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);

  await Promise.all([
    page.waitForResponse((res) => res.url().includes('/session/login'), { timeout: 60000 }),
    page.getByRole('button', { name: 'Sign In' }).click(),
  ]);

  await expect(page).toHaveURL(/\/dashboard/, { timeout: 35000 });
}

/**
 * Ensures user is on a project page with an open task detail modal.
 * Waits for existing projects to load on the Dashboard and navigates to the first project.
 */
async function openFirstTaskModal(page) {
  // Wait for project cards on Dashboard to render asynchronously
  const projectLink = page.locator('.dashboard-link').first();
  await expect(projectLink).toBeVisible({ timeout: 35000 });
  await projectLink.click();

  // Assert client-side SPA navigation to project page
  await expect(page).toHaveURL(/\/projects\//, { timeout: 35000 });

  // Ensure task section is expanded
  const taskSection = page.locator('#taskProject');
  const taskToggleBtn = page.locator('header.task-top button');
  await expect(taskToggleBtn).toBeVisible({ timeout: 25000 });
  if (!(await taskSection.isVisible())) {
    await taskToggleBtn.click();
  }
  await expect(taskSection).toBeVisible({ timeout: 25000 });

  // Open first task card
  const taskCard = taskSection.locator('.task-card_style').first();
  await expect(taskCard).toBeVisible({ timeout: 35000 });
  await taskCard.click();

  const taskModal = page.locator('.task-modal');
  await expect(taskModal).toBeVisible({ timeout: 25000 });

  return { taskModal, taskSection };
}

/**
 * Workflow 4: Critical Failure States & Anti-White-Screen
 * Covers:
 * - 409 Conflict handling on task mutation
 * - Simulated network failure / drop handling
 * - Global error boundary ensuring the UI stays mounted without blanking
 */
test.describe('Workflow 4: Critical Failure States & Anti-White-Screen', () => {
  test.setTimeout(120000);

  const testEmail = process.env.E2E_USER_EMAIL || 'little.marilie@example.com';
  const testPassword = process.env.E2E_USER_PASSWORD || 'Berry@999';

  test.beforeEach(async ({ page }) => {
    await page.context().clearCookies();
  });

  test('Simulated 409 conflict during task update surfaces conflict toast without crashing UI', async ({ page }) => {
    // 1. Log in user
    await loginUser(page, testEmail, testPassword);

    // 2. Open task modal on existing project
    const { taskModal } = await openFirstTaskModal(page);

    // 3. Intercept the task PATCH mutation to simulate a 409 Conflict from concurrent editor
    await page.route('**/tasks/**', async (route) => {
      if (route.request().method() === 'PATCH') {
        await route.fulfill({
          status: 409,
          contentType: 'application/json',
          body: JSON.stringify({
            message: 'Task was modified by another user. Please refresh and try again.',
            error: 'edit_conflict',
          }),
        });
      } else {
        await route.continue();
      }
    });

    // 4. Trigger task title update
    const titleSpan = taskModal.locator('.task-modal_title');
    await expect(titleSpan).toBeVisible({ timeout: 25000 });
    await titleSpan.click();

    const titleInput = taskModal.locator('input.title-form');
    await expect(titleInput).toBeVisible({ timeout: 20000 });
    await titleInput.fill(`Conflict Test ${Date.now()}`);

    const updateBtn = taskModal.locator('span.btn-link', { hasText: 'Update' });
    await expect(updateBtn).toBeVisible({ timeout: 15000 });
    await updateBtn.click();

    // 5. Assert non-intrusive 409 conflict toast notification appears
    const conflictToast = page.getByText('Task was modified by another user').first();
    await expect(conflictToast).toBeVisible({ timeout: 15000 });

    // 6. Assert anti-white-screen: UI remains fully mounted and interactive
    await expect(page.locator('#app')).toBeVisible();
    await expect(taskModal).toBeVisible();
    await expect(page.locator('.project-page-wrapper')).toBeVisible();
  });

  test('Simulated network drop during API request displays error toast without blanking screen', async ({ page }) => {
    // 1. Log in user
    await loginUser(page, testEmail, testPassword);

    // 2. Open task modal
    const { taskModal } = await openFirstTaskModal(page);

    // 3. Intercept PATCH mutation and simulate complete network failure (aborted connection)
    await page.route('**/tasks/**', async (route) => {
      if (route.request().method() === 'PATCH') {
        await route.abort('failed');
      } else {
        await route.continue();
      }
    });

    // 4. Trigger title edit
    const titleSpan = taskModal.locator('.task-modal_title');
    await expect(titleSpan).toBeVisible({ timeout: 25000 });
    await titleSpan.click();

    const titleInput = taskModal.locator('input.title-form');
    await expect(titleInput).toBeVisible({ timeout: 20000 });
    await titleInput.fill(`Network Drop Test ${Date.now()}`);

    const updateBtn = taskModal.locator('span.btn-link', { hasText: 'Update' });
    await updateBtn.click();

    // 5. Assert network failure toast notification surfaces from global error handler
    const networkToast = page.getByText('Unable to reach the server').first();
    await expect(networkToast).toBeVisible({ timeout: 15000 });

    // 6. Assert application shell and modal remain mounted without a white screen
    await expect(page.locator('#app')).toBeVisible();
    await expect(taskModal).toBeVisible();
  });

  test('Unhandled component error triggers toast boundary without crashing root DOM', async ({ page }) => {
    // 1. Log in user and navigate to dashboard
    await loginUser(page, testEmail, testPassword);

    // 2. Trigger an unhandled rejection in the window context
    await page.evaluate(() => {
      window.dispatchEvent(
        new PromiseRejectionEvent('unhandledrejection', {
          promise: Promise.reject(new Error('Simulated runtime error for boundary testing')),
          reason: new Error('Simulated runtime error for boundary testing'),
        }),
      );
    });

    // 3. Assert global Vue/Window error handler toast appears (use .first() to prevent strict mode duplicate matches)
    const errorToast = page.getByText('An unexpected error occurred').first();
    await expect(errorToast).toBeVisible({ timeout: 15000 });

    // 4. Assert root app container remains fully rendered (anti-white-screen)
    await expect(page.locator('#app')).toBeVisible();
    await expect(page.locator('.dashboard').first()).toBeVisible();
  });
});
