<?php

/*
 * Source: app/Jobs/SendApplicationRejectedEmail.php
 *
 * Context: TrabahongPinoy: live job-application portal for Filipino blue-collar workers
 * (Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo). Private repo.
 *
 * Problem solved: a rejected applicant must be able to fix and resubmit without an
 * account. This queued job builds a temporary signed URL (valid 72h) to the
 * `reapply` route and sends it through the Brevo HTTP API. The route's `signed`
 * middleware checks signature and expiry, so no token table or login is needed.
 * Mail runs on the queue so the admin's Reject click returns at once.
 * Flow: this job -> routes-web.excerpt.php -> ApplyForm::mount($uuid) (excerpt 01).
 *
 * My part: sole developer. I designed, built and deployed the app.
 */

namespace App\Jobs;

use App\Models\Applicant;
use App\Services\BrevoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class SendApplicationRejectedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Applicant $applicant) {}

    public function handle(BrevoService $brevo): void
    {
        $reapplyUrl = URL::temporarySignedRoute(
            'reapply',
            now()->addHours(72),
            ['uuid' => $this->applicant->id]
        );

        $html = view('emails.application-rejected', [
            'applicant' => $this->applicant,
            'reapplyUrl' => $reapplyUrl,
        ])->render();

        $brevo->sendRawEmail(
            toEmail: $this->applicant->email,
            subject: __('email.rejected_subject'),
            htmlContent: $html,
            toName: $this->applicant->first_name . ' ' . $this->applicant->last_name,
        );
    }
}
