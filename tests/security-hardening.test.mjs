import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const ROOT = resolve(import.meta.dirname, '..')
const read = (path) => readFileSync(resolve(ROOT, path), 'utf8')

const indexPhp = read('ticky-backend/index.php')
const helpersPhp = read('ticky-backend/utils/helpers.php')
const loginApiPhp = read('ticky-backend/api/auth/login.php')
const logoutApiPhp = read('ticky-backend/api/auth/logout.php')
const loginPagePhp = read('ticky-backend/pages/login.php')
const adminPagePhp = read('ticky-backend/pages/admin.php')
const adminUserApiPhp = read('ticky-backend/api/admin_felhasznalo.php')
const adminDiagApiPhp = read('ticky-backend/api/admin_diagnosztika.php')

assert.ok(
  !indexPhp.includes("if ($uri === '/admin' || admin_path_matches_request($uri))"),
  'index.php should not keep the fixed /admin alias fallback'
)

assert.match(
  helpersPhp,
  /function\s+ticky_csrf_token\s*\(/,
  'helpers.php should define a CSRF token helper'
)

assert.match(
  helpersPhp,
  /function\s+ticky_require_csrf(?:_token)?\s*\(/,
  'helpers.php should define a CSRF validation helper'
)

assert.match(
  helpersPhp,
  /function\s+ticky_session_[a-z_]+\s*\(/,
  'helpers.php should define session helpers for server-side auth'
)

assert.match(
  loginApiPhp,
  /ticky_require_csrf(?:_token)?\s*\(/,
  'login API should enforce CSRF'
)

assert.match(
  logoutApiPhp,
  /\$_SERVER\['REQUEST_METHOD'\]\s*!==\s*'POST'/,
  'logout API should reject non-POST requests'
)

assert.match(
  logoutApiPhp,
  /ticky_require_csrf(?:_token)?\s*\(/,
  'logout API should enforce CSRF'
)

assert.match(
  loginPagePhp,
  /csrf/i,
  'login page should expose a CSRF token for the login request'
)

assert.ok(
  !adminPagePhp.includes('?logout=1'),
  'admin page should not use GET logout links anymore'
)

assert.match(
  adminPagePhp,
  /X-CSRF-Token/i,
  'admin page should send a CSRF header on admin requests'
)

assert.match(
  adminPagePhp,
  /csrf/i,
  'admin page should embed a CSRF token'
)

assert.match(
  adminPagePhp,
  /secret.*rate|rate.*secret|too many|túl sok/i,
  'admin page should contain secret admin rate-limit handling or messaging'
)

assert.match(
  adminUserApiPhp,
  /utols[oó]\s+admin|last admin/i,
  'admin user API should protect the last admin account'
)

assert.ok(
  !adminDiagApiPhp.includes("'file_path'"),
  'admin diagnostics API should not return the source file path'
)

console.log('security-hardening assertions passed')
