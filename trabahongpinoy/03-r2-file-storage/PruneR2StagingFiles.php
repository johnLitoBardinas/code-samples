<?php

/*
 * Source: app/Console/Commands/PruneR2StagingFiles.php
 *
 * Context: TrabahongPinoy: live job-application portal for Filipino blue-collar workers
 * (Laravel 12, PHP 8.4, Livewire 3, PostgreSQL, Cloudflare R2, Brevo). Private repo.
 *
 * Problem solved: staging uploads count against the 10 GB Cloudflare R2 free tier.
 * This artisan command clears them safely: it refuses to run outside `staging`, has
 * --dry-run and a confirm prompt, deletes tracked files with chunkById(100), and
 * finds orphaned objects by checking each R2 key against a hash set of DB paths
 * (O(1) per key). Orphans are deleted in batches of 1000, the S3 DeleteObjects limit.
 *
 * My part: sole developer. I designed, built and deployed the app.
 */

namespace App\Console\Commands;

use App\Models\Applicant;
use App\Models\ApplicantFile;
use App\Services\ApplicantFileService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneR2StagingFiles extends Command
{
    protected $signature = 'r2:prune-staging
                            {--dry-run : Preview what will be deleted without deleting}
                            {--force : Skip confirmation prompt}
                            {--older-than= : Only delete files older than N days}
                            {--purge-orphans : Delete R2 objects that have no matching DB record}
                            {--print-path : Print each file path as it is deleted}';

    protected $description = '[STAGING ONLY] Delete applicant files from Cloudflare R2 to free storage';

    public function handle(): int
    {
        if (app()->environment() !== 'staging') {
            $this->error('This command only runs in the [staging] environment. Current: '.app()->environment());

            return self::FAILURE;
        }

        $query = ApplicantFile::query();

        if ($olderThan = $this->option('older-than')) {
            $query->where('created_at', '<', now()->subDays((int) $olderThan));
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No files to delete.');

            if ($this->option('purge-orphans')) {
                $this->purgeOrphans();
            }

            return self::SUCCESS;
        }

        $label = $this->option('older-than')
            ? "files older than {$olderThan} day(s)"
            : 'all applicant files';

        $this->warn('Environment: staging');
        $this->line("Target: {$label}");
        $this->line("Files to delete: {$total}");

        if ($this->option('dry-run')) {
            $this->info('[dry-run] No files deleted.');

            return self::SUCCESS;
        }

        if (! $this->shouldProceed("Delete {$total} file(s) from R2 and the database?")) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $this->pruneTrackedFiles($query);

        if ($this->option('purge-orphans')) {
            $this->purgeOrphans();
        }

        return self::SUCCESS;
    }

    private function purgeOrphans(): void
    {
        $this->line('');
        $this->line('Scanning R2 bucket for orphaned objects...');

        // List every object in the bucket — try/catch detects real connection failures
        // NOTE: Storage::exists('') was removed — on R2/S3 it checks for an object with
        // an empty string key, not bucket reachability. Returns false even on healthy buckets.
        try {
            $r2Objects = Storage::disk('r2')->allFiles();
        } catch (\Exception $e) {
            $this->error('R2 bucket not accessible: '.$e->getMessage());
            Log::error('[r2:prune-staging] R2 bucket not accessible', ['error' => $e->getMessage()]);
            return;
        }

        if (empty($r2Objects)) {
            $this->info('No objects found in R2 bucket.');

            return;
        }

        // Build O(1) lookup set from DB — flip() turns values into keys
        $dbPaths = ApplicantFile::pluck('file_path')->flip()->all();

        // Find R2 objects with no matching DB row
        $orphans = array_filter($r2Objects, fn ($path) => ! isset($dbPaths[$path]));

        $orphanCount = count($orphans);

        if ($orphanCount === 0) {
            $this->info('No orphaned R2 objects found.');
            return;
        }

        $this->warn("Orphaned R2 objects (no DB record): {$orphanCount}");

        if ($this->option('dry-run')) {
            $this->info('[dry-run] No orphans deleted.');
            return;
        }

        if (! $this->shouldProceed("Delete {$orphanCount} orphaned object(s) from R2?")) {
            $this->line('Orphan purge aborted.');
            return;
        }

        // Batch delete orphans in chunks of 1000 (S3 DeleteObjects limit)
        $deletedOrphans = 0;
        foreach (array_chunk($orphans, 1000) as $batch) {
            if ($this->option('print-path')) {
                foreach ($batch as $path) {
                    $this->line("  Orphan: {$path}");
                }
            }

            Storage::disk('r2')->delete($batch);
            $deletedOrphans += count($batch);
        }

        $this->info("Deleted {$deletedOrphans} orphaned object(s) from R2.");

        Log::info('[r2:prune-staging] Orphan purge completed', [
            'orphans_found'   => $orphanCount,
            'orphans_deleted' => $deletedOrphans,
            'ran_at'          => now()->toDateTimeString(),
        ]);

    }

    private function shouldProceed(string $question): bool
    {
        return $this->option('force') || $this->confirm($question);
    }

    private function pruneTrackedFiles(Builder $query): void
    {
        $deleted = 0;

        $query->chunkById(100, function ($files) use (&$deleted) {
            if ($this->option('print-path')) {
                $files->each(fn ($f) => $this->line("  Deleting: {$f->file_path}"));
            }

            ApplicantFileService::deleteMany($files);
            $deleted += $files->count();
        });

        $this->info("Deleted {$deleted} file(s) from R2 and applicant_files table.");

        // Delete applicant rows — applicant_files already gone, cascade fires on nothing
        $applicantsDeleted = Applicant::doesntHave('files')->delete();
        $this->info("Deleted {$applicantsDeleted} applicant record(s) from database.");

        Log::info('[r2:prune-staging] Prune completed', [
            'files_deleted'      => $deleted,
            'applicants_deleted' => $applicantsDeleted,
            'older_than_days'    => $this->option('older-than'),
            'dry_run'            => $this->option('dry-run'),
            'ran_at'             => now()->toDateTimeString(),
        ]);
    }
}
