<?php

namespace App\Console\Commands;

use App\Models\ClientConcern;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportServiceTrackerConcerns extends Command
{
    protected $signature = 'service-tracker:import-concerns
                            {--file= : Path to the xlsx file (defaults to storage/app/imports/*.xlsx, newest)}
                            {--dry-run : Preview rows without writing (default behavior)}
                            {--show-values : Print the exact field values for each row (user + linked concern) without writing}
                            {--commit : Write to the database (creates placeholder clients + concern rows)}';

    protected $description = 'Import the "38. ISSUES CLIENT CONCERN" sheet into client_concerns';

    private const HEADER_ALIASES = [
        'client' => ['name of client', 'client name', 'client', 'name'],
        'date' => ['date identified', 'date'],
        'description' => ['description issues and problem', 'description', 'issue', 'problem', 'issues and problem'],
        'solution' => ['propose solution', 'proposed solution', 'solution'],
        'status' => ['status', 'frequency', 'frequency (frequent/seldom/rare)', 'status (frequent/seldom/rare)'],
    ];

    public function handle(): int
    {
        $file = $this->option('file');
        if (! $file) {
            $file = $this->findDefaultFile();
        }

        if (! $file || ! is_file($file)) {
            $this->error("Input file not found: ".($file ?: '(none given)'));
            $this->line('Place the .xlsx in storage/app/imports/ or pass --file=/path/to/file.xlsx');
            return self::FAILURE;
        }

        if ($this->option('dry-run') || $this->option('show-values')) {
            $commit = false;
        } elseif ($this->option('commit')) {
            $commit = true;
        } else {
            $commit = $this->confirm('Commit writes to the database?');
        }

        $this->info("Reading: {$file}");
        $spreadsheet = IOFactory::load($file);

        $sheet = $this->findConcernSheet($spreadsheet);
        if (! $sheet) {
            $this->error('Could not find a sheet matching "38. ISSUES CLIENT CONCERN".');
            $this->line('Sheets found: '.implode(', ', $spreadsheet->getSheetNames()));
            return self::FAILURE;
        }

        $this->info("Using sheet: ".$sheet->getTitle());

        $service = TrackerService::where('sort_order', 38)
            ->orWhere('name', 'like', '%Client Concerns%')
            ->first();

        if (! $service) {
            $this->error('TrackerService #38 (Client Concerns) not found. Seed it first.');
            return self::FAILURE;
        }

        $rows = $this->readRows($sheet);
        if (count($rows) === 0) {
            $this->warn('No data rows found on the sheet.');
            return self::SUCCESS;
        }

        $clients = User::query()->where('role', User::ROLE_CLIENT)->get();

        $matched = 0;
        $unmatched = 0;
        $skippedDuplicates = 0;
        $wouldWrite = 0;
        $placeholders = 0;

        $outcome = [];
        foreach ($rows as $i => $row) {
            $client = $this->resolveClient($row['client'], $clients);
            if ($client) {
                $matched++;
                $outcome[] = [$i + 1, $row['client'], $client->id, $row['date'], $row['status'], 'MATCH', ''];
            } else {
                $unmatched++;
                $email = $this->buildPlaceholderEmail($row['client']);
                $outcome[] = [$i + 1, $row['client'], 'NEW', $row['date'], $row['status'], 'PLACEHOLDER', $email];
            }
        }

        $this->table(['#', 'Client (excel)', '-> client_id', 'Date', 'Status', 'Outcome', 'Created email'], $outcome);

        if ($this->option('show-values')) {
            $this->renderShowValues($rows, $clients, $service);
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY-RUN — no rows written and no accounts created. Re-run with --commit after review.');
            return self::SUCCESS;
        }

        if ($commit) {
            foreach ($rows as $row) {
                $client = $this->resolveClient($row['client'], $clients);
                if (! $client) {
                    $client = $this->createPlaceholder($row['client']);
                    $placeholders++;
                    $clients->push($client);
                }

                $dupe = ClientConcern::query()
                    ->where('client_id', $client->id)
                    ->where('date_identified', $row['date'])
                    ->where('description_of_issue', $row['description'])
                    ->exists();

                if ($dupe) {
                    $skippedDuplicates++;
                    continue;
                }

                ClientConcern::create([
                    'client_id' => $client->id,
                    'related_service_id' => $service->id,
                    'date_identified' => $row['date'],
                    'description_of_issue' => $row['description'],
                    'proposed_solution' => $row['solution'],
                    'status' => $row['status'],
                    'submitted_by' => ClientConcern::SUBMITTED_BY_STAFF,
                    'reviewed' => true,
                ]);
                $wouldWrite++;
            }
        }

        $this->newLine();
        $this->line("Total rows: ".count($rows));
        $this->line("Matched clients: {$matched}");
        $this->line("Unmatched clients (would create placeholder account): {$unmatched}");

        if ($commit) {
            $this->line("Placeholder clients created: {$placeholders}");
            $this->line("Concerns written: {$wouldWrite}  Skipped duplicates: {$skippedDuplicates}");
            $this->info('Database committed.');
        } else {
            $this->warn('Aborted — nothing written.');
        }

        return self::SUCCESS;
    }

    private function findDefaultFile(): ?string
    {
        $dir = storage_path('app/imports');
        if (! is_dir($dir)) {
            return null;
        }
        $files = glob($dir.'/*.xlsx');
        if (! $files) {
            return null;
        }
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        return $files[0];
    }

    private function findConcernSheet($spreadsheet): ?Worksheet
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = strtolower(trim($sheet->getTitle()));
            if (str_contains($title, 'issue') && str_contains($title, 'concern')) {
                return $sheet;
            }
        }
        return null;
    }

    private function readRows(Worksheet $sheet): array
    {
        $data = $sheet->toArray(null, true, true, false);

        $headerRow = null;
        $colMap = [];
        foreach ($data as $rowIdx => $row) {
            $colMap = $this->mapColumns($row);
            if (count($colMap) >= 3) {
                $headerRow = $rowIdx;
                break;
            }
        }

        if ($headerRow === null) {
            return [];
        }

        $rows = [];
        for ($i = $headerRow + 1; $i < count($data); $i++) {
            $line = $data[$i];

            $client = $this->cell($line, $colMap['client'] ?? null);
            $description = $this->cell($line, $colMap['description'] ?? null);

            if (trim($client) === '' && trim($description) === '') {
                continue;
            }

            $dateRaw = $this->cell($line, $colMap['date'] ?? null);
            $date = $this->normalizeDate($dateRaw);

            $statusRaw = strtolower(trim($this->cell($line, $colMap['status'] ?? null)));
            $status = match ($statusRaw) {
                'frequent' => ClientConcern::STATUS_FREQUENT,
                'rare' => ClientConcern::STATUS_RARE,
                default => ClientConcern::STATUS_SELDOM,
            };

            $rows[] = [
                'client' => trim($client),
                'date' => $date,
                'description' => trim($description),
                'solution' => trim($this->cell($line, $colMap['solution'] ?? null)),
                'status' => $status,
            ];
        }

        return $rows;
    }

    private function mapColumns(array $row): array
    {
        $map = [];
        foreach ($row as $idx => $cell) {
            $norm = strtolower(trim((string) $cell));
            foreach (self::HEADER_ALIASES as $key => $aliases) {
                if (isset($map[$key])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if ($norm === $alias || str_starts_with($norm, $alias)) {
                        $map[$key] = $idx;
                        break;
                    }
                }
            }
        }
        return $map;
    }

    private function cell(array $row, ?int $idx): string
    {
        if ($idx === null || ! array_key_exists($idx, $row)) {
            return '';
        }
        $v = $row[$idx];
        if ($v instanceof \DateTimeInterface || $v instanceof \DateTimeImmutable) {
            return $v->format('Y-m-d');
        }
        return (string) ($v ?? '');
    }

    private function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) || preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $raw)) {
            return date('Y-m-d', strtotime($raw));
        }

        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $raw, $m)) {
            $year = (int) $m[3];
            if ($year < 100) {
                $year = 2000 + $year;
            }
            return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
        }

        $parsed = strtotime($raw);
        if ($parsed !== false) {
            return date('Y-m-d', $parsed);
        }

        return null;
    }

    private function resolveClient(string $name, $clients): ?User
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        foreach ($clients as $client) {
            if (strcasecmp((string) $client->business_name, $name) === 0) {
                return $client;
            }
        }
        foreach ($clients as $client) {
            if (strcasecmp((string) $client->name, $name) === 0) {
                return $client;
            }
        }
        foreach ($clients as $client) {
            if (strcasecmp((string) $client->email, $name) === 0) {
                return $client;
            }
        }

        $norm = $this->normalize($name);
        if ($norm !== '') {
            foreach ($clients as $client) {
                if ($this->normalize((string) $client->business_name) === $norm
                    || $this->normalize((string) $client->name) === $norm) {
                    return $client;
                }
            }
        }

        return null;
    }

    private function buildPlaceholderEmail(string $name): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        $email = $slug.'+tracker@eglianeas.com';

        $exists = User::where('email', $email)->exists();
        $n = 2;
        while ($exists) {
            $email = $slug.'+tracker'.$n.'@eglianeas.com';
            $exists = User::where('email', $email)->exists();
            $n++;
        }

        return $email;
    }

    private function createPlaceholder(string $name): User
    {
        $existing = User::where('business_name', $name)->where('role', User::ROLE_CLIENT)->first();
        if ($existing) {
            return $existing;
        }

        $email = $this->buildPlaceholderEmail($name);

        return User::create([
            'name' => trim($name),
            'email' => $email,
            'business_name' => trim($name),
            'role' => User::ROLE_CLIENT,
        ]);
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private function renderShowValues(array $rows, $clients, TrackerService $service): void
    {
        $nextCode = User::generateClientCode();

        foreach ($rows as $i => $row) {
            $client = $this->resolveClient($row['client'], $clients);

            $this->newLine();
            $this->info('=== Row '.($i + 1).': '.$row['client'].' ===');

            if ($client) {
                $this->line('Existing client already linked (no user created).');
                $userValues = ['client_id' => $client->id];
            } else {
                $code = $nextCode;
                $email = $this->buildPlaceholderEmail($row['client']);
                $userValues = [
                    'users.name' => trim($row['client']),
                    'users.email' => $email,
                    'users.business_name' => trim($row['client']),
                    'users.role' => User::ROLE_CLIENT,
                    'users.client_code' => $code,
                    'users.password' => 'null (no usable password — placeholder)',
                ];
                $nextCode = $this->bumpClientCode($code);
            }

            $this->table(['Field', 'Value'], collect([
                ...array_map(fn ($k, $v) => [$k, $v], array_keys($userValues), array_values($userValues)),
                ['client_concerns.client_id', $client->id ?? '(new user id from users insert)'],
                ['client_concerns.related_service_id', (string) $service->id.' (TrackerService #38: '.$service->name.')'],
                ['client_concerns.date_identified', (string) ($row['date'] ?? 'null')],
                ['client_concerns.description_of_issue', $row['description']],
                ['client_concerns.proposed_solution', $row['solution'] === '' ? 'null' : $row['solution']],
                ['client_concerns.status', $row['status']],
                ['client_concerns.submitted_by', 'staff'],
                ['client_concerns.reviewed', 'true'],
            ])->all());
        }

        $this->newLine();
        $this->line('Predicted client_code sequence begins at: '.User::generateClientCode());
        $this->warn('SHOW-VALUES — this is a preview only. No rows written and no accounts created.');
    }

    private function bumpClientCode(string $code): string
    {
        if (preg_match('/^EAS-(\d+)$/', $code, $m)) {
            return 'EAS-'.str_pad((string) ((int) $m[1] + 1), 4, '0', STR_PAD_LEFT);
        }
        return $code;
    }
}
