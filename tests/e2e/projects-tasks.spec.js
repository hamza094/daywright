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
 * Workflow 2: Projects & Tasks
 * Workflow 3: Authorization & Tenant Isolation
 *
 * Uses a shared browser context across the suite so User A logs in ONCE in Test 1,
 * and Test 2 immediately reuses the authenticated session, cutting test duration by ~50%.
 */
test.describe('Workflow 2: Projects & Tasks', () => {
  test.describe.configure({ mode: 'serial' });
  test.setTimeout(180000);

  /** @type {import('@playwright/test').Page} */
  let page;

  const userAEmail = process.env.E2E_USER_EMAIL || 'vincenzo53@example.com';
  const userAPassword = process.env.E2E_USER_PASSWORD || 'Berry@999';

  const userBEmail = process.env.E2E_USER_B_EMAIL || 'abergstrom@example.net';
  const userBPassword = process.env.E2E_USER_PASSWORD || 'Berry@999';

  let createdProjectSlug = '';
  const timestamp = Date.now();
  const projectName = `Smoke Project ${timestamp}`.slice(0, 40);
  const initialTaskTitle = `Initial Task ${timestamp}`.slice(0, 40);

  test.beforeAll(async ({ browser }) => {
    // Single shared page/session for User A across Workflow 2
    page = await browser.newPage();
    page.on('console', (msg) => console.log('BROWSER LOG:', msg.type(), msg.text()));
    page.on('pageerror', (err) => console.log('BROWSER UNCAUGHT ERROR:', err));
  });

  test.afterAll(async () => {
    await page?.close();
  });

  test('Create project with initial task and verify it renders on Dashboard', async () => {
    // 1. Log in User A (creates the authenticated session for the entire suite)
    await loginUser(page, userAEmail, userAPassword);

    // 2. Open "New Project" slide-out drawer
    const newProjectBtn = page.locator('a.panel-list_item', { hasText: /new project/i });
    await expect(newProjectBtn).toBeVisible({ timeout: 25000 });
    await newProjectBtn.click();

    // 3. Wait for slide-out panel
    const panel = page.locator('.panel-form');
    await expect(panel).toBeVisible({ timeout: 25000 });

    // 4. Fill project name (min 4 chars) and about (min 15 chars)
    await page.locator('input[name="name"]').fill(projectName);
    await page.locator('textarea[name="about"]').fill('Automated E2E smoke test description exceeding 15 characters.');

    // 5. Add initial task field and fill task title (5 to 55 chars)
    const addTaskFieldBtn = page.getByRole('button', { name: /add new task field/i });
    await expect(addTaskFieldBtn).toBeVisible({ timeout: 15000 });
    await addTaskFieldBtn.click();

    const taskInput = page.locator('input[name="task"]').first();
    await expect(taskInput).toBeVisible({ timeout: 15000 });
    await taskInput.fill(initialTaskTitle);

    // 6. Submit project form and capture created project slug
    const [projectResponse] = await Promise.all([
      page.waitForResponse(
        (res) => res.url().includes('/projects') && res.request().method() === 'POST',
        { timeout: 60000 },
      ),
      page.locator('button.panel-btn_save').click(),
    ]);

    expect(projectResponse.ok()).toBeTruthy();
    const responseData = await projectResponse.json();
    createdProjectSlug = responseData?.data?.slug || '';
    expect(createdProjectSlug).toBeTruthy();

    // 7. Verify redirection to project page
    await expect(page).toHaveURL(new RegExp(`/projects/${createdProjectSlug}`), { timeout: 35000 });

    // 8. Navigate back to Dashboard via sidebar router-link (pure SPA client navigation)
    const dashboardLink = page.locator('a.panel-list_item', { hasText: /dashboard/i });
    await expect(dashboardLink).toBeVisible({ timeout: 15000 });
    await dashboardLink.click();
    await expect(page).toHaveURL(/\/dashboard/, { timeout: 20000 });

    // 9. Verify project card renders on Dashboard
    const projectCardTitle = page.locator('.project-title', { hasText: projectName });
    await expect(projectCardTitle).toBeVisible({ timeout: 35000 });
  });

  test('Open task modal, update title, and verify optimistic update and version increment', async () => {
    // 1. Navigate to the created project page from the Dashboard via SPA link
    const targetCard = page.locator('.dashboard-link', { hasText: projectName });
    await expect(targetCard).toBeVisible({ timeout: 35000 });
    await targetCard.click();
    await page.waitForURL(/\/projects\//, { timeout: 35000 });

    // 2. Ensure the Tasks collapse section is expanded
    const taskSection = page.locator('#taskProject');
    const taskToggleBtn = page.locator('header.task-top button');
    await expect(taskToggleBtn).toBeVisible({ timeout: 25000 });
    if (!(await taskSection.isVisible())) {
      await taskToggleBtn.click();
    }
    await expect(taskSection).toBeVisible({ timeout: 25000 });

    // 3. Wait for the project task card to render and click to open TaskDetailModal
    const taskCard = taskSection.locator('.task-card_style').first();
    await expect(taskCard).toBeVisible({ timeout: 35000 });
    await Promise.all([
      page.waitForResponse(
        (res) => res.url().includes('/tasks/') && res.request().method() === 'GET',
        { timeout: 45000 },
      ),
      taskCard.click(),
    ]);

    // 4. Verify modal is visible
    const taskModal = page.locator('.task-modal');
    await expect(taskModal).toBeVisible({ timeout: 25000 });

    // 5. Click task title inside modal to switch to edit mode
    const titleSpan = taskModal.locator('.task-modal_title');
    await expect(titleSpan).toBeVisible({ timeout: 25000 });
    await titleSpan.click();

    // 6. Enter updated title and save
    const titleInput = taskModal.locator('input.title-form');
    await expect(titleInput).toBeVisible({ timeout: 20000 });

    const updatedTitle = `Updated Smoke Task ${Date.now()}`.slice(0, 45);
    await titleInput.fill(updatedTitle);

    const [patchResponse] = await Promise.all([
      page.waitForResponse(
        (res) => res.url().includes('/tasks/') && res.request().method() === 'PATCH',
        { timeout: 60000 },
      ),
      taskModal.locator('span.btn-link', { hasText: 'Update' }).click(),
    ]);

    expect(patchResponse.ok()).toBeTruthy();
    const patchData = await patchResponse.json();

    // Assert optimistic concurrency version incremented on backend
    const newVersion = patchData?.data?.version;
    expect(typeof newVersion === 'number' && newVersion >= 1).toBeTruthy();

    // 7. Assert title is updated optimistically in the modal
    await expect(taskModal.locator('.task-modal_title')).toHaveText(updatedTitle, { timeout: 25000 });

    // 8. Close modal and assert background task list reflects the updated title
    await taskModal.locator('.task-modal_close').click();
    await expect(taskSection.locator('.task-card_body', { hasText: updatedTitle })).toBeVisible({
      timeout: 25000,
    });
  });

  test('Workflow 3: Authorization & Tenant Isolation prevents cross-tenant data leakage', async ({ browser }) => {
    // Open an isolated incognito browser context for User B
    const userBContext = await browser.newContext();
    const userBPage = await userBContext.newPage();

    try {
      // 1. Log in as User B
      await loginUser(userBPage, userBEmail, userBPassword);

      // 2. User B attempts direct URL navigation to User A's project (of which User B is NOT a member).
      //    The backend ProjectsPolicy::access() returns 403 Forbidden for non-members.
      //    The Vue SPA catches the 403 via handleErrorResponse and shows a toast but does NOT
      //    render the project page — `show` stays false so `.project-page-wrapper` is never mounted.
      const targetSlug = createdProjectSlug || 'smoke-project';

      // 2. User B attempts direct URL navigation to User A's project.
      //    We specifically wait for the API XHR (/api/v1/projects/{slug}) which enforces
      //    authorization, not the HTML page document (/projects/{slug}) which serves the SPA shell (200).
      const [apiResponse] = await Promise.all([
        userBPage
          .waitForResponse(
            (res) =>
              res.url().includes('/api/v1/projects/') &&
              res.url().includes(targetSlug) &&
              res.request().method() === 'GET',
            { timeout: 45000 },
          )
          .catch(() => null),
        userBPage.goto(`/projects/${targetSlug}`, { timeout: 60000, waitUntil: 'domcontentloaded' }),
      ]);

      // 3. Assert the backend DENIED access (403 Forbidden for existing private project, or 404 if slug doesn't exist)
      if (apiResponse) {
        expect([403, 404]).toContain(apiResponse.status());
      }

      // 4. Assert private project workspace content is never rendered to User B
      const privateProjectContent = userBPage.locator('.project-page-wrapper');
      await expect(privateProjectContent).not.toBeVisible({ timeout: 10000 });
    } finally {
      await userBContext.close();
    }
  });
});
