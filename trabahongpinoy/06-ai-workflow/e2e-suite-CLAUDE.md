> **Excerpt of the `CLAUDE.md` context file from the TrabahongPinoy Playwright E2E suite** (private repo; Playwright + TypeScript).
>
> **Context:** Claude Code loads this file at the start of every session in the test repo. The specs in excerpts 02 and 05 were generated with Claude Code under these rules.
>
> **Problem solved:** AI-written E2E tests for a Livewire + WireUI app break in predictable ways: custom selects, server round-trips, native confirm dialogs, webcam capture. This file records each quirk once, with the fix the page objects use, so every generated spec follows the same Page Object pattern and waits correctly. It also keeps the suite serial, because it runs against a shared live environment. Its working-style rules keep me in the loop: small changes, each with file and line, which I review and apply myself.
>
> **Removed for publishing:** the Commands section (setup and credential env vars) and the staging URL. The e2e email domain is replaced with a placeholder. The sections below are otherwise unedited.
>
> **My part:** I wrote this file and own the suite.

---

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Playwright E2E suite for **Trabahong Pinoy** (a Laravel + Livewire/Alpine + WireUI app). This repo contains tests only — it drives a remote deployment, it does not host the app. Default target is staging; override per-run with `BASE_URL`.

## Architecture

Page Object Model. Specs in `tests/specs/`, page objects in `tests/pages/`, fixtures + data builders in `tests/fixtures/`.

- **Page objects** wrap one route each and expose intent-level methods (`submitFullApplication`, `approve`). Specs never touch raw locators.
- **`tests/fixtures/test-data.ts`** — `buildApplicant()` generates faker-randomized applicants. Emails tagged `e2e-{timestamp}-{rand}@<e2e-test-domain>` so staging records are identifiable for cleanup. Sample upload files (`government-id.jpg`, `certificate.pdf`) live alongside.
- Approve/reject specs are **end-to-end chains**: they submit a fresh applicant via the public form first, then log in as admin to act on it. No fixture seeding — the test creates its own data on the live target.

### App-specific quirks the page objects handle

These are why the abstraction exists; preserve them when editing:

- **Webcam capture is mandatory** on the apply form. `playwright.config.ts` launches Chromium with `--use-fake-ui-for-media-stream` + `--use-fake-device-for-media-stream` and grants `permissions: ['camera']`, feeding a fake video stream. `captureFacePhoto()` drives Start Camera → wait `streaming` → Capture → wait for Retake button (`captured` state) → networkidle.
- **Livewire address cascade** (Region → Province → City → Barangay) uses `wire:model.live`; each pick is a server roundtrip. `fillAddressCascade()` waits for `networkidle` between every selection.
- **WireUI selects are not native `<select>`** — they render custom popup listboxes. Use the `selectWireUIOption` / `selectWireUIFirstAvailableOption` helpers (click label control → click `role=option`), not `selectOption`.
- **Confirm dialogs**: approve/reject trigger Livewire `wire:confirm` → native `confirm()`. Page objects auto-accept via `page.once('dialog', d => d.accept())` **before** the click.
- **Logout has no UI button** — `admin-auth.spec.ts` POSTs to `/admin/logout` with a CSRF token scraped from the `meta[name=csrf-token]` tag.
- **Search** uses `wire:model.live.debounce.300ms` — wait past the debounce before asserting filtered rows.
- **Reapply** route is signature-protected (Laravel `signed` middleware). Only the negative case (403 without signature) is covered; the full email→reapply chain is deferred (needs DB/mail access on staging).

## Config notes

- `workers: 1`, `fullyParallel: false` — serial. Tests hit a **shared live environment**; parallelism would collide. Keep it serial.
- Two projects: `chromium` (Desktop Chrome) and `Mobile Chrome` (Pixel 5).
- CI: `retries: 2`, `forbidOnly` enforced.
- ESM project (`"type": "module"`); use `import.meta.url` + `fileURLToPath` for `__dirname`.
- `ignoreHTTPSErrors: true` in `use` block — required for local `https://localhost`, which serves a self-signed cert that Chromium otherwise rejects with `net::ERR_CERT_AUTHORITY_INVALID`. Harmless against staging (valid cert).

## Working style

- When proposing or making a code change, ALWAYS state the affected file path, the exact line number,
  and the reason for the change. Do not output a config/code snippet alone — be explicit about where it
  goes. ALWAYS ask to do the edit or the developer will do it.
- The developer is **hands-on and copy-pastes the code themselves**. Default to handing over a
  copy-paste-ready snippet (with file path + exact line) rather than applying the edit, so they build a
  mental model of every change and know exactly what each line does.
- The developer reviews **at the change level in the codebase, not in a pull request** — review each
  line as it is proposed. Keep changes small and reviewable; don't bundle unrelated edits.
- For every code change, give the **Why?**, **What?**, and **How it works** — but do NOT over-explain.
  The developer is a **Senior Developer**; Skip basics, Direct and to the point — cut the chase, no
  over-explaining. keep it tight by default and only go deeper when they ask.
- **Mental models:** only explain non-obvious patterns (e.g. WireUI Alpine internals, Livewire lifecycle).
  One tight paragraph, senior level. Skip if obvious.
