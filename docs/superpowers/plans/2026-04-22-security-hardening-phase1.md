# Security Hardening Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden the Ticky repo auth and admin surface without breaking the existing site flow.

**Architecture:** Replace the current stateless auth cookies with a server-side file-backed session layer, add CSRF enforcement to auth/admin write paths, remove the `/admin` alias fallback, and tighten logout/admin entry behavior. Keep the changes local to the PHP backend and add node-based regression tests because the current environment has no PHP CLI.

**Tech Stack:** PHP, file-backed session storage in `sys_get_temp_dir()`, Node-based regression tests, GitHub Actions-ready npm scripts.

---

### Task 1: Lock In Regression Tests

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\package.json`
- Create: `C:\Users\david\Documents\New project\Ticky\tests\security-hardening.test.mjs`
- Test: `C:\Users\david\Documents\New project\Ticky\tests\security-hardening.test.mjs`

- [ ] Write a failing test for `/admin` alias removal, CSRF plumbing, logout POST-only behavior, and security header helper presence.
- [ ] Run `node C:\Users\david\Documents\New project\Ticky\tests\security-hardening.test.mjs` and confirm it fails before implementation.
- [ ] Add an `npm test` script that runs all repo tests.

### Task 2: Introduce Session and CSRF Helpers

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\utils\helpers.php`

- [ ] Add a unified file-backed session store with create/read/rotate/destroy helpers.
- [ ] Add CSRF generation and validation helpers.
- [ ] Add reusable same-origin, rate-limit, and security-header helpers.
- [ ] Keep compatibility cleanup for old cookies on logout/login transition.

### Task 3: Harden Login, Logout, and Admin Entry

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\api\auth\login.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\api\auth\logout.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\pages\login.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\pages\admin.php`

- [ ] Require CSRF for login and logout.
- [ ] Make logout POST-only.
- [ ] Apply rate limiting to the secret admin login form.
- [ ] Replace GET logout links with POST/JS logout using CSRF.

### Task 4: Harden Admin APIs and Routing

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\index.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\api\admin_felhasznalo.php`
- Modify: `C:\Users\david\Documents\New project\Ticky\ticky-backend\api\admin_diagnosztika.php`

- [ ] Remove the unconditional `/admin` route fallback.
- [ ] Require session-backed CSRF on admin API requests.
- [ ] Add last-admin protections and stronger password validation.
- [ ] Remove sensitive filesystem path leakage from diagnostics output.

### Task 5: Verify and Publish

**Files:**
- Modify: `C:\Users\david\Documents\New project\Ticky\.github\workflows\ci.yml`

- [ ] Run `npm test`.
- [ ] Run `git diff --check`.
- [ ] Add a minimal CI workflow that executes `npm test`.
- [ ] Commit, push, and open a PR.
