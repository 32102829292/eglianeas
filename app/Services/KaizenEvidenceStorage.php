<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class KaizenEvidenceStorage
{
    private const DIRECTORY = 'kaizen-evidence';

    public function candidateDisks(): array
    {
        $configured = (string) config('filesystems.evidence_disk', 'local');
        $disks = [$configured];

        foreach (['local', 'supabase'] as $fallback) {
            if (! in_array($fallback, $disks, true)) {
                $disks[] = $fallback;
            }
        }

        return array_values(array_filter(
            $disks,
            fn (string $disk): bool => $this->shouldUseDisk($disk)
        ));
    }

    private function shouldUseDisk(string $disk): bool
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config)) {
            return false;
        }

        if ($disk !== 'supabase') {
            return true;
        }

        if ((string) config('filesystems.evidence_disk', 'local') === 'supabase') {
            return true;
        }

        if (($config['driver'] ?? null) === 'local') {
            return true;
        }

        return filled($config['key'] ?? null)
            && filled($config['secret'] ?? null)
            && filled($config['endpoint'] ?? null);
    }

    public function store(UploadedFile $file): string
    {
        $lastException = null;

        foreach ($this->candidateDisks() as $disk) {
            try {
                $path = $file->store(self::DIRECTORY, $disk);

                if (is_string($path) && $path !== '') {
                    return $path;
                }
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new RuntimeException('Unable to store Kaizen evidence.');
    }

    public function diskFor(string $path): ?string
    {
        foreach ($this->candidateDisks() as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    return $disk;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    public function exists(string $path): bool
    {
        return $this->diskFor($path) !== null;
    }

    public function response(string $path, string $name, array $headers): StreamedResponse
    {
        return Storage::disk($this->diskOrAbort($path))->response($path, $name, $headers);
    }

    public function download(string $path, string $name, array $headers): StreamedResponse
    {
        return Storage::disk($this->diskOrAbort($path))->download($path, $name, $headers);
    }

    private function diskOrAbort(string $path): string
    {
        $disk = $this->diskFor($path);

        if ($disk === null) {
            Log::warning('Kaizen evidence file is missing.', [
                'path' => $path,
                'configured_disk' => (string) config('filesystems.evidence_disk', 'local'),
                'candidate_disks' => $this->candidateDisks(),
            ]);

            abort(404);
        }

        return $disk;
    }

    public function delete(string $path): bool
    {
        $disk = $this->diskFor($path);

        if ($disk === null) {
            return false;
        }

        return Storage::disk($disk)->delete($path);
    }
}
