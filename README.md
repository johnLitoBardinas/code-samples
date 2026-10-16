# Code samples — John Lito Bardinas

Curated excerpts from two production Laravel applications I built alone. Both repositories are private, so this repo holds selected files, each with a header note covering **context**, **problem solved** and **my part**. Full read access to the source repositories is available on request.

The files are excerpts, not a runnable app. Secrets, config, infrastructure and client names have been removed.

| # | Excerpt | Project | What it shows | Relevant to |
|---|---|---|---|---|
| 01 | [Livewire apply form](trabahongpinoy/01-livewire-apply-form/ApplyForm.php) | TrabahongPinoy | A single component for first-time apply and reapply, `#[Validate]` attributes with backed enums, per-IP rate limiting, R2 uploads and a queued email | Laravel for new apps, modern PHP 8 |
| 02 | [Signed 72h reapply link](trabahongpinoy/02-signed-reapply-link/) | TrabahongPinoy | A queued mail job that builds a temporary signed URL, the `signed` route, and a Playwright test that checks unsigned and tampered links get 403 | Queues, third-party API (Brevo), security |
| 03 | [R2 file storage + prune command](trabahongpinoy/03-r2-file-storage/) | TrabahongPinoy | A storage service on S3-compatible R2, and a staging-only artisan command with dry-run, `chunkById`, hash-set orphan detection and 1000-key batch deletes | Third-party integration, large datasets |
| 04 | [Backed enum](trabahongpinoy/04-backed-enum/ApplicantStatus.php) | TrabahongPinoy | A PHP 8.1 backed enum cast on the model, used in place of status strings | Typed PHP (PHP 5.4 → 8 migration) |
| 05 | [Playwright E2E](trabahongpinoy/05-playwright-e2e/) | TrabahongPinoy | AI-generated (Claude Code) TypeScript E2E tests with a Page Object; a business rule checked in both the UI and the route | JavaScript/TypeScript, AI-generated tests |
| 06 | [App CLAUDE.md](trabahongpinoy/06-ai-workflow/CLAUDE.md) + [E2E suite CLAUDE.md](trabahongpinoy/06-ai-workflow/e2e-suite-CLAUDE.md) | TrabahongPinoy | Context files that steer Claude Code: collaboration style, glossary and layering rules for the app; Page Object pattern and Livewire/WireUI test quirks for the E2E suite | Claude Code, project rules/context files, AI-generated tests |
| 07 | [FormRequest + custom Rules](fix-and-free/07-form-request-rules/) | Fix and Free | Visit preconditions split into reusable Rule objects; time-bound vs count-bound packages | Laravel validation design, older codebase |
| 08 | [Scheduled bulk expiry](fix-and-free/08-scheduled-bulk-expiry/) | Fix and Free | A daily scheduled command that expires packages with 3 set-based `UPDATE`s, no per-row loop | MySQL, scheduled jobs |

## Projects

- **TrabahongPinoy** (2026, live at https://trabahongpinoy.com): job-application portal for Filipino blue-collar workers. Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo, Laravel Forge. I designed, built and deployed it, and built its Playwright E2E suite with Claude Code.
  - Public application form: https://trabahongpinoy.com
  - Admin panel: walkthrough available on request
- **Fix and Free** (2020–2021): subscription and visit system for a gym, salon and spa business. Laravel 7, PHP 7.4, MySQL. Sole developer.

## Contact

- Portfolio: https://jlbardinas.dev
- GitHub: https://github.com/johnLitoBardinas
- LinkedIn: https://www.linkedin.com/in/john-lito-bardinas-7140a7145/
