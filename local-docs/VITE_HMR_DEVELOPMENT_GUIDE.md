# Vite Development & Hot Module Replacement (HMR) Guide

This guide explains how to toggle between **Live Hot Module Replacement (HMR)** for active frontend development and **Static Pre-Built Mode** for running automated Playwright smoke tests.

---

## 1. How Vite Works in Laravel

Laravel's `@vite` directive checks whether the file `public/hot` exists:

- **When `public/hot` exists:** Laravel assumes the Vite dev server is running and instructs your browser to load scripts directly from `http://localhost:5173` with instant live-reloading.
- **When `public/hot` does NOT exist:** Laravel serves the pre-compiled production bundles from `public/build/assets` via `public/build/manifest.json`.

---

## 2. Your Current Configuration (Already Configured)

Your [`vite.config.js`](file:///c:/Users/Hamza/daywright/vite.config.js) is already pre-configured to support Docker/Sail HMR seamlessly:

```javascript
server: {
  host: '0.0.0.0', // Accepts incoming connections from Windows through Docker
  hmr: {
    host: 'localhost', // Tells the browser on Windows to connect to localhost for WebSocket updates
  },
  watch: {
    usePolling: true, // Detects edits made in Windows files across the Docker mount
  },
},
```

---

## 3. How to Start Hot Module Replacement (HMR)

When you are actively building UI and want live hot-reloading:

### Step 1: Ensure Sail is running

```bash
./vendor/bin/sail up -d
```

### Step 2: Start the Vite Dev Server

In your **WSL terminal**:

```bash
./vendor/bin/sail npm run dev
```

- Leave this terminal tab open.
- Vite will generate `public/hot` containing `http://localhost:5173`.
- Any edit to a `.vue`, `.js`, or `.scss` file will instantly reflect in your browser without manual page refreshes.

---

## 4. How to Switch Back to Static Mode (For Playwright Testing)

Playwright smoke tests run best against static compiled assets so tests are not dependent on a live development server.

When you finish UI development and want to run tests:

### Step 1: Stop the Dev Server

In the terminal running `npm run dev`, press:

```bash
Ctrl + C
```

### Step 2: Remove the `public/hot` File

_(If `public/hot` remains on disk when Vite is off, the browser will spin waiting for port 5173)_:

- In **WSL**:
  ```bash
  rm -f public/hot
  ```
- Or in **Windows PowerShell**:
  ```powershell
  Remove-Item public/hot -Force -ErrorAction SilentlyContinue
  ```

### Step 3: Run the Build (If you modified frontend files)

```bash
./vendor/bin/sail npm run build
```

### Step 4: Run Playwright Tests

In **Windows PowerShell**:

```powershell
$env:PLAYWRIGHT_BASE_URL="http://localhost"; npx playwright test tests/e2e/auth.spec.js --project=chromium --headed
```

---

## 5. Troubleshooting & Gotchas

### Problem: Browser tab keeps spinning endlessly

- **Cause:** `public/hot` exists on disk, but the Vite dev server is not running.
- **Fix:** Delete `public/hot` and re-run `./vendor/bin/sail npm run build`.

### Problem: Edits on Windows are not detected by Vite in Docker

- **Cause:** Docker on Windows file-events can sometimes drop across the `/mnt/c/` mount.
- **Fix:** The `watch: { usePolling: true }` in `vite.config.js` resolves this. If it ever lags, restarting `./vendor/bin/sail npm run dev` re-establishes the file watcher.
