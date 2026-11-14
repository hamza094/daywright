// @ts-check
import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'path';

// Load environment variables from .env file if present
dotenv.config({ path: path.resolve(process.cwd(), '.env') });

const baseURL = process.env.PLAYWRIGHT_BASE_URL || process.env.APP_URL || 'http://127.0.0.1:8000';

/**
 * Playwright E2E configuration tuned for Daywright
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig({
  testDir: './tests/e2e',
  /* Maximum time one test can run for (Docker on Windows filesystem takes ~50s for multi-step flows) */
  timeout: 90 * 1000,
  expect: {
    timeout: 15000,
  },
  /* Avoid race conditions during database/session mutations in E2E smoke tests */
  fullyParallel: false,
  /* Fail the build on CI if you accidentally left test.only in the source code */
  forbidOnly: !!process.env.CI,
  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,
  /* Opt out of parallel tests to keep sequential tenant and auth state predictable */
  workers: process.env.CI ? 1 : 1,
  /* Reporter: terminal list for instant CLI feedback + HTML artifact */
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  /* Shared settings for all the projects below */
  use: {
    baseURL,
    /* Action and navigation timeouts tuned for Docker on Windows filesystem speed */
    actionTimeout: 25 * 1000,
    navigationTimeout: 40 * 1000,
    /* Collect trace and diagnostics on failure */
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
  ],
});
