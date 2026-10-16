> **Excerpt of the `CLAUDE.md` context file from TrabahongPinoy** (private repo; Laravel 12, Livewire 3, PostgreSQL, Cloudflare R2).
>
> **Context:** Claude Code loads this file at the start of every session. It sets how the agent works with me and what it must know about the project before it touches code.
>
> **Problem solved:** it keeps an AI pair-programmer consistent across sessions. It fixes the collaboration style (I type the code; the agent guides and reviews against Clean Code, SOLID, DRY, KISS and Big-O). It forbids invented terms and points to a glossary. It states the layering rule the code must follow. The full file (573 lines) also holds Mermaid user-journey flows, an EER diagram and data dictionaries for every table.
>
> **Removed for publishing:** URLs, admin and developer access paths, environment variables, database schema and data dictionaries, and ops notes. Partner (client) names are replaced with generic wording. The sections below are otherwise unedited. The `app/Actions/` and `app/Repositories/` layers it names are the stated target architecture; they are not built yet.
>
> **My part:** I wrote and maintain this file.

---

# TrabahongPinoy — Claude Code Context

## Collaboration Style

- The user is hands-on and wants to type the code themselves to build understanding.
- Act as a partner/guide: explain what to change, where (exact file path and line number), and why — but do NOT write or edit the code directly.
- Always point to the exact file, line number, and code snippet that needs changing.
- When the user types **"review"**: (1) check the current task context to understand what is being built, (2) read the changed files and review against **Clean Code, SOLID principles, DRY (Do Not Repeat), KISS (Keep It Simple Stupid, and Big-O space/time complexity**, (3) give honest, direct feedback — no praise, no sugar-coating, (4) work through findings **1 issue at a time**, bounce ideas, and rigorously arrive at the best solution together before moving to the next issue.

## Terminology — DO NOT INVENT TERMS

**`docs/GLOSSARY_TERMS.md` is the single source of truth for project vocabulary** — business/domain terms, application-architecture terms, DB terms, status values, and migration-plan labels.

Rules:
- **Use the glossary's exact wording.** Do not coin new names, abbreviations, shorthand, or step labels mid-conversation. If a term isn't in the glossary, it isn't an agreed term.
- **If a new concept genuinely needs a name**, say so explicitly, propose the name, and **add it to `docs/GLOSSARY_TERMS.md` in the same turn** — don't let it live only in chat history where the user can't look it up later.
- **Never use an invented label as if the user already knows it.** Referring to something as "3c2" or "the lock-down step" without saying what it *is* forces the user to reverse-engineer the meaning. Say what it does, or point at the file.
- **Respect the "Terms to Avoid" table** in the glossary (e.g. say *agency*, not *tenant*; say *soft delete* or *hard delete*, never bare *delete*).
- **Keep the glossary honest** — mark planned-but-not-built things as `[planned]`. Never describe a directory, class, or column as existing when it doesn't.

## Project Overview

A job application portal for Filipino workers. Applicants apply online, upload documents, and get processed by an admin. The portal supports multiple job sources (managed dynamically in a `sources` DB table — one row per partner agency, plus a catch-all `General`) and sends automated emails at every stage.

## Tech Stack

- **Framework**: Laravel (latest stable)
- **Frontend**: TALL Stack — Tailwind CSS, Alpine.js, Laravel Livewire, Laravel Blade
- **UI Components**: WireUI v2
- **Database**: PostgreSQL (provisioned via Laravel Forge)
- **File Storage**: Cloudflare R2 (via Laravel Filesystem, `r2` disk)
- **Email**: Brevo — sent via the Brevo transactional HTTP API (`App\Services\BrevoService`, `https://api.brevo.com/v3/smtp/email`), 300 emails/day free tier
- **PDF Generation**: DomPDF (`barryvdh/laravel-dompdf`)
- **Server**: Laravel Forge on DigitalOcean
- **Queue**: Laravel Queue with database driver (for email jobs)
- **Address Data**: Philippine PSGC served from local JSON in `public/locations/` (`regions.json`, `provinces.json`, `cities-municipalities.json`, `barangays.json`) via `App\Services\PsgcService`

## Current Scope & Future Vision

- **Current focus:** Salon and security-agency hiring, alongside other onboarded partner sources and a catch-all `General`.
- **Future vision:** Evolve into a general-purpose **Blue Collar Hiring** platform that can onboard any trade or industry (construction, manufacturing, logistics, hospitality, etc.).
- **Design principle:** Do NOT hardcode logic around specific sources — they are rows in the `sources` table (`name`, `slug`, `is_active`). Adding a source is a data change (seed a new row), not a code change. All filters, UI tabs, and business rules read from the table.


## Reapplication Flow

- Route: `GET /reapply/{uuid}` → same `ApplyForm` component, protected by Laravel's `signed` middleware (validates signature + expiry)
- `ApplyForm::mount()` receives the `uuid`, calls `prefillFromPrevious()` to load the previous applicant's data into the form (including resolving stored PSGC names back to dropdown codes via `PsgcService::findCodeByName`)
- Applicant edits and resubmits
- On resubmit:
  - Creates a **new** applicant record (status: new)
  - Sets `original_applicant_id` to the original UUID
  - Sends confirmation email again
  - Does NOT overwrite original record (audit trail preserved)


## Code Architecture

This project uses **two complementary architectures** — they do not conflict:

1. **TALL Stack MVC/MVP** — Livewire components act as fused Controller+View (MVP Presenter). This is the framework's natural pattern and is embraced as-is.

2. **N-Tier (Java-style) Business Layer** — business logic must NOT live in Livewire components. Extract it into dedicated layers:
   - `app/Services/` — Business Logic Layer (BLL). Stateless service classes (e.g., `BrevoService`, `PsgcService`). One service per domain concern.
   - `app/Actions/` — Single-responsibility action classes for complex operations (e.g., `SubmitApplicationAction`, `ApproveApplicantAction`).
   - `app/Repositories/` — Data Access Layer (DAL) if query logic grows complex enough to warrant extraction from models.
   - Eloquent Models stay as Data/ORM layer only — no business logic on models beyond accessors, casts, and scopes.

**Rule:** Livewire components call Services. Services call Models. Never skip layers (no raw DB queries in Livewire, no HTTP calls in models).

```
Livewire Component   ← Presentation (MVC thin controller + view)
    ↓ calls
Service     ← Business Logic (N-Tier BLL)
    ↓ calls
Model   ← Data Access (N-Tier DAL)
```
