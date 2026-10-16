/**
 * Source: tp-playwright/tests/specs/reapply-signed-url.spec.ts
 *
 * Context: TrabahongPinoy: live job-application portal for Filipino blue-collar workers
 * (Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo). Private repo.
 * Separate Playwright (TypeScript) E2E suite that runs against staging.
 *
 * Problem solved: proves the reapply route rejects an unsigned link and a tampered
 * signature with 403, at the HTTP level.
 *
 * My part: I own the E2E suite. The specs were generated with Claude Code under the
 * suite's CLAUDE.md rules (excerpt 06); I reviewed and applied every change myself.
 */

import { test, expect } from '@playwright/test';

test.describe('Reapply — signed-URL middleware (smoke)', () => {
    test('GET /reapply/{uuid} without a valid signature returns 403', async ({ request }) => {
        // The reapply route is protected by Laravel's `signed` middleware
        // (routes/web.php). A URL without a valid signature should be rejected.
        const fakeUuid = '00000000-0000-4000-8000-000000000000';
        const response = await request.get(`/reapply/${fakeUuid}`, {
            maxRedirects: 0,
            failOnStatusCode: false,
        });
        expect(response.status()).toBe(403);
    });

    test('GET /reapply/{uuid} with a tampered signature returns 403', async ({ request }) => {
          // mermaid C -->|Invalid sig| D. We can't forge a *valid* signature without the
          // app's APP_KEY, so the positive path is covered separately; here we assert that a
          // syntactically-present-but-wrong signature is still rejected by `signed` middleware.
          const fakeUuid = '00000000-0000-4000-8000-000000000000';
          const response = await request.get(`/reapply/${fakeUuid}?expires=9999999999&signature=deadbeef`, {
              maxRedirects: 0,
              failOnStatusCode: false,
          });
          expect(response.status()).toBe(403);
      });

});
