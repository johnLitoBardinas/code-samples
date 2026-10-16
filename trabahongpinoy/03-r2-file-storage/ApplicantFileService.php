<?php

/*
 * Source: app/Services/ApplicantFileService.php
 *
 * Context: TrabahongPinoy: live job-application portal for Filipino blue-collar workers
 * (Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo). Private repo.
 *
 * Problem solved: applicants upload government IDs, certificates and a face photo.
 * Files go to Cloudflare R2 under applicants/{uuid}/{type}/{random-uuid}.{ext},
 * so client file names never become storage keys. The original name is kept only
 * as metadata. deleteMany() removes a batch with one storage call and one query.
 *
 * My part: sole developer. I designed, built and deployed the app.
 */

namespace App\Services;

use App\Enums\FileType;
use App\Models\Applicant;
use App\Models\ApplicantFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicantFileService
{
    public static function store(Applicant $applicant, array $files, FileType $type): void
    {
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $uniqueName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs(
                "applicants/{$applicant->id}/{$type->value}",
                $uniqueName,
                'r2'
            );

            ApplicantFile::create([
                'applicant_id' => $applicant->id,
                'type'         => $type,
                'file_path'    => $path,
                'file_name'    => $file->getClientOriginalName(),
                'mime_type'    => $file->getMimeType(),
                'created_at'   => now(),
            ]);
        }
    }

    public static function delete(ApplicantFile $file): void
    {
        Storage::disk('r2')->delete($file->file_path);
        $file->delete();
    }

    public static function deleteMany(Collection $files): void
    {
        Storage::disk('r2')->delete($files->pluck('file_path')->all());
        ApplicantFile::whereIn('id', $files->pluck('id'))->delete();
    }

}
