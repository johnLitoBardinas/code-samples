/**
 * Source: tp-playwright/tests/specs/admin-applicant-edit.spec.ts
 *
 * Context: TrabahongPinoy: live job-application portal for Filipino blue-collar workers
 * (Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo). Private repo.
 * Separate Playwright (TypeScript) E2E suite (7 specs) that runs against staging.
 *
 * Problem solved: covers the admin edit page end to end. Each test seeds its own
 * applicant through the public form (Faker data), then checks that edits persist,
 * that files can be added and removed, and that the face photo can be replaced.
 * The last test checks a business rule at two levels: the Edit link disappears
 * once an applicant is approved, and a direct GET to the edit route returns 403.
 * Selectors live in a Page Object (AdminApplicantEditPage.ts).
 *
 * My part: I own the E2E suite. The specs were generated with Claude Code under the
 * suite's CLAUDE.md rules (excerpt 06); I reviewed and applied every change myself.
 */

import { test, expect, Page } from '@playwright/test';
import { ApplyFormPage } from '../pages/ApplyFormPage';
import { AdminDashboardPage } from '../pages/AdminDashboardPage';
import { AdminApplicantDetailPage } from '../pages/AdminApplicantDetailPage';
import { AdminApplicantEditPage } from '../pages/AdminApplicantEditPage';
import { loginAsAdmin } from '../pages/AdminLoginPage';
import {
    buildApplicant,
    SAMPLE_CERTIFICATE_PATH,
    SAMPLE_GOVERNMENT_ID_PATH,
} from '../fixtures/test-data';

/**
 * Submits a fresh applicant and lands on their admin detail page, logged in.
 * Every edit-page test needs a "new" status applicant to act on.
 */
async function submitAndOpenDetail(page: Page) {
    const applyForm = new ApplyFormPage(page);
    const applicant = buildApplicant();
    await applyForm.goto('general');
    await applyForm.submitFullApplication(applicant);
    await expect(page).toHaveURL(/\/success$/);

    await loginAsAdmin(page);
    const dashboard = new AdminDashboardPage(page);
    await dashboard.selectTab('new');
    await dashboard.searchFor(applicant.email);
    await dashboard.openApplicantByEmail(applicant.email);

    const detail = new AdminApplicantDetailPage(page);
    await detail.expectLoaded();
    await detail.expectStatusBadge('New');

    return { applicant, detail };
}

test.describe('Admin — edit applicant (critical path)', () => {
    test('admin edits a new applicant and the changes persist', async ({ page }) => {
        const { applicant, detail } = await submitAndOpenDetail(page);
        await detail.openEdit();

        const edit = new AdminApplicantEditPage(page);
        await edit.expectLoaded();
        await edit.expectFieldValue('first_name', applicant.firstName);
        await edit.expectFieldValue('email', applicant.email);

        const updatedMobile = '0917' + Date.now().toString().slice(-7);
        const updatedAddress = `Updated ${applicant.address}`;
        await edit.fillField('mobile_number', updatedMobile);
        await edit.fillField('address', updatedAddress);
        await edit.save();

        await detail.expectLoaded();
        await expect(page.locator('body')).toContainText(updatedMobile);
        await expect(page.locator('body')).toContainText(updatedAddress);
    });

    test('admin adds and deletes a government ID on the edit page', async ({ page }) => {
        const { detail } = await submitAndOpenDetail(page);
        await detail.openEdit();

        const edit = new AdminApplicantEditPage(page);
        await edit.expectLoaded();

        // Applicant already has one government ID from the public form (max 3) —
        // add a second under a distinct name, save, reopen, then delete it.
        await edit.uploadNewGovernmentId(SAMPLE_GOVERNMENT_ID_PATH, 'government-id-2.jpg');
        await expect(page.getByText('government-id-2.jpg').first()).toBeVisible();
        await edit.save();

        await detail.expectLoaded();
        await detail.openEdit();
        await edit.expectLoaded();
        await expect(edit.fileCard('government-id.jpg')).toHaveCount(1);
        await expect(edit.fileCard('government-id-2.jpg')).toHaveCount(1);

        await edit.deleteFileByName('government-id-2.jpg');
        await expect(edit.fileCard('government-id-2.jpg')).toHaveCount(0);
        await expect(edit.fileCard('government-id.jpg')).toHaveCount(1);
    });

    test('admin adds and deletes a certificate on the edit page', async ({ page }) => {
        const { detail } = await submitAndOpenDetail(page);
        await detail.openEdit();

        const edit = new AdminApplicantEditPage(page);
        await edit.expectLoaded();

        // Applicant already has one certificate from the public form (max 6) —
        // add a second under a distinct name, save, reopen, then delete it.
        await edit.uploadNewCertificate(SAMPLE_CERTIFICATE_PATH, 'certificate-2.pdf');
        await expect(page.getByText('certificate-2.pdf').first()).toBeVisible();
        await edit.save();

        await detail.expectLoaded();
        await detail.openEdit();
        await edit.expectLoaded();
        await expect(edit.fileCard('certificate.pdf')).toHaveCount(1);
        await expect(edit.fileCard('certificate-2.pdf')).toHaveCount(1);

        await edit.deleteFileByName('certificate-2.pdf');
        await expect(edit.fileCard('certificate-2.pdf')).toHaveCount(0);
        await expect(edit.fileCard('certificate.pdf')).toHaveCount(1);
    });

    test('admin replaces the face photo on the edit page', async ({ page }) => {
        const { detail } = await submitAndOpenDetail(page);
        const originalSrc = await detail.facePhotoSrc();
        expect(originalSrc).not.toBe('');

        await detail.openEdit();
        const edit = new AdminApplicantEditPage(page);
        await edit.expectLoaded();

        // Reuse the government-id JPEG fixture as the replacement — new_face_photo
        // only requires image/jpeg or image/png, no webcam involved on this page.
        await edit.replaceFacePhoto(SAMPLE_GOVERNMENT_ID_PATH);
        await expect(page.getByAltText('New face photo preview')).toBeVisible();
        await edit.save();

        // ApplicantEdit::save() deletes the old ApplicantFile (R2 + DB row) and
        // stores the new one — the detail page's <img src> should now differ.
        await detail.expectLoaded();
        const updatedSrc = await detail.facePhotoSrc();
        expect(updatedSrc).not.toBe(originalSrc);
    });

    test('edit link is hidden and route is blocked once applicant is no longer new', async ({ page }) => {
        const { detail } = await submitAndOpenDetail(page);
        await detail.approve();
        await detail.expectStatusBadge('Approved');

        // Edit link removed from the UI once status flips away from "New".
        await detail.expectEditHidden();

        // Direct navigation to the edit route must 403 (ApplicantEdit::mount abort).
        const applicantId = detail.idFromUrl();
        const response = await page.goto(`/admin/applicants/${applicantId}/edit`);
        expect(response?.status()).toBe(403);
    });
});
