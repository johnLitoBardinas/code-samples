/**
 * Source: tp-playwright/tests/pages/AdminApplicantEditPage.ts
 *
 * Context: Page Object used by admin-applicant-edit.spec.ts (same folder).
 *
 * My part: I own the E2E suite. The specs were generated with Claude Code under the
 * suite's CLAUDE.md rules (excerpt 06); I reviewed and applied every change myself.
 */

import { Page, expect } from '@playwright/test';
import fs from 'node:fs';

/**
 * Page Object for `/admin/applicants/{id}/edit` (App\Livewire\Admin\ApplicantEdit).
 *
 * Route is gated: only reachable/editable while applicant.status === New
 * (enforced both by the "Edit" link visibility on the detail page and a
 * 403 abort in ApplicantEdit::mount()). File inputs (new_government_ids,
 * new_certificates, new_face_photo) are plain wire:model, no webcam involved.
 */
export class AdminApplicantEditPage {
    constructor(private readonly page: Page) {}

    async expectLoaded(): Promise<void> {
        await expect(this.page.getByRole('heading', { name: /^Edit —/ })).toBeVisible();
    }

    async expectFieldValue(id: string, value: string): Promise<void> {
        await expect(this.page.locator(`#${id}`)).toHaveValue(value);
    }

    async fillField(id: string, value: string): Promise<void> {
        await this.page.locator(`#${id}`).fill(value);
    }

    // asFileName lets a test upload the same fixture bytes under a distinct
    // display name — keeps "add a second file" tests unambiguous regardless
    // of whether the backend already dedupes same-named uploads.
    async uploadNewGovernmentId(filePath: string, asFileName: string): Promise<void> {
        await this.page.locator('input[type="file"][wire\\:model="new_government_ids"]').setInputFiles({
            name: asFileName,
            mimeType: 'image/jpeg',
            buffer: fs.readFileSync(filePath),
        });
        await this.page.waitForLoadState('networkidle');
    }

    async uploadNewCertificate(filePath: string, asFileName: string): Promise<void> {
        await this.page.locator('input[type="file"][wire\\:model="new_certificates"]').setInputFiles({
            name: asFileName,
            mimeType: 'application/pdf',
            buffer: fs.readFileSync(filePath),
        });
        await this.page.waitForLoadState('networkidle');
    }

    async replaceFacePhoto(filePath: string): Promise<void> {
        await this.page.locator('input[type="file"][wire\\:model="new_face_photo"]').setInputFiles(filePath);
        await this.page.waitForLoadState('networkidle');
    }

    fileCard(fileName: string) {
        return this.page.locator('div.group', { hasText: fileName });
    }

    async deleteFileByName(fileName: string): Promise<void> {
        this.page.once('dialog', (dialog) => dialog.accept());
        await this.fileCard(fileName).getByRole('button').click();
        await this.page.waitForLoadState('networkidle');
    }

    async save(): Promise<void> {
        await this.page.getByRole('button', { name: /^Save Changes$/ }).click();
        await this.page.waitForURL(/\/admin\/applicants\/[0-9a-f-]+$/i, { timeout: 15_000 });
    }
}
