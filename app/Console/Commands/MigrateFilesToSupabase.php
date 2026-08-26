<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\CompanyCertificate;
use App\Models\Document;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateFilesToSupabase extends Command
{
    protected $signature = 'storage:migrate-to-supabase {--dry-run : Preview without uploading}';

    protected $description = 'Migrate all local files (documents, certificates, payment images, announcements) to Supabase Storage';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $local = Storage::disk('local');
        $supabase = Storage::disk('supabase');
        $supabaseReady = (bool) env('SUPABASE_S3_ACCESS_KEY');
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        $this->line($dryRun ? '<info>DRY RUN — no files will be uploaded</info>' : '<info>Migrating files to Supabase Storage…</info>');

        if (! $supabaseReady && ! $dryRun) {
            $this->error('SUPABASE_S3_ACCESS_KEY is not set in .env. Cannot upload.');
            return 1;
        }

        $this->newLine();

        $jobs = [
            'Client documents' => Document::whereNotNull('path')->pluck('path')->map(fn ($p) => ['path' => $p])->toArray(),
            'Certificates' => CompanyCertificate::whereNotNull('file_path')->pluck('file_path')->map(fn ($p) => ['path' => $p])->toArray(),
            'Payment images' => $this->paymentPaths(),
            'Announcement images' => Announcement::whereNotNull('image_path')->pluck('image_path')->map(fn ($p) => ['path' => $p])->toArray(),
        ];

        foreach ($jobs as $label => $items) {
            $this->info("▸ {$label}");
            foreach ($items as $item) {
                $path = $item['path'];
                $localExists = $local->exists($path);

                if (! $localExists) {
                    $this->line("  <error>MISS</error> {$path} (not on local disk)");
                    $failed++;
                    continue;
                }

                if ($dryRun) {
                    $this->line("  <info>will</info> {$path}");
                    $migrated++;
                    continue;
                }

                $supabase->put($path, $local->get($path));
                $this->line("  <info>done</info> {$path}");
                $migrated++;
            }
            $this->newLine();
        }

        $this->info("Done. Migrated: {$migrated}, Skipped: {$skipped}, Missing: {$failed}");

        if ($failed > 0) {
            $this->warn('Some files were missing from the local disk. They may have already been lost.');
        }

        return $failed > 0 ? 1 : 0;
    }

    private function paymentPaths(): array
    {
        $paths = [];

        $gcashPath = Setting::get('gcash_qr_code');
        if ($gcashPath) {
            $paths[] = ['path' => $gcashPath];
        }

        $bankAccounts = Setting::get('bank_accounts', []);
        foreach ($bankAccounts as $account) {
            $bankPath = $account['bank_qr_code'] ?? null;
            if ($bankPath) {
                $paths[] = ['path' => $bankPath];
            }
        }

        return $paths;
    }
}
