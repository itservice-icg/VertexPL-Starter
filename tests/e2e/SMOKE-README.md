# NCR Playwright Smoke Test

Run from `D:\01_Project\MCP\VertexPL\tests\e2e`.

1. Start the existing VertexPL Laravel server on `http://127.0.0.1:8001` (separate terminal).
2. Run `npx.cmd playwright test` or `npx.cmd playwright test --headed`.
3. View report with `npx.cmd playwright show-report`.

`smoke.spec.js` only reads `GET /dev`, enumerates active mock accounts and validates the page. It does **not** click account-login buttons, create NCR records, or write to MySQL. Report files will be stored in `playwright-report` and `test-results`.

This is **not** the multi-user workflow runner yet. It is a verified starting point for the full E2E suite. Playwright itself is installed locally in this folder; do not run migrations, seeders, or destructive operations.


##
cd /d D:\01_Project\MCP\VertexPL\tests\e2e

set NCR_E2E_ALLOW_WRITES=YES
set NCR_E2E_TARGET_DB=vertex_pl

npx.cmd playwright test workflow.spec.js --grep "Fresh Scenario OWNER" --headed --workers=1