<?php

/*
 * Source: app/Enums/ApplicantStatus.php
 *
 * Context: TrabahongPinoy: live job-application portal for Filipino blue-collar workers
 * (Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo). Private repo.
 *
 * Problem solved: one typed source of truth for the applicant workflow state.
 * The enum is cast on the Applicant model, so the apply form, the admin
 * approve/reject actions and the dashboard tab counts compare enum cases, not
 * strings. label() renders the status in the admin pages and the PDF export.
 * The match expressions have no default arm, so a new case without a label
 * throws UnhandledMatchError instead of rendering blank.
 *
 * My part: sole developer. I designed, built and deployed the app.
 */

namespace App\Enums;

enum ApplicantStatus: string
{
    case New = 'new';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Approved => 'positive',
            self::Rejected => 'negative',
        };
    }
}
