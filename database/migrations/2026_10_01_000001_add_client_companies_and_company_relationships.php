<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The existing client_code column is unique, but validate the values
         * which will become company-code prefixes before changing any schema.
         * A malformed legacy value is still safe to retain; only duplicate
         * generated company IDs would make the backfill ambiguous.
         */
        $duplicateParentCodes = DB::table('users')
            ->where('role', 'client')
            ->whereNotNull('client_code')
            ->select('client_code')
            ->groupBy('client_code')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateParentCodes) {
            throw new \RuntimeException('Cannot add client companies: duplicate client codes must be resolved first.');
        }

        Schema::create('client_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('branch_number');
            $table->string('company_code', 32)->unique();
            $table->string('company_name')->nullable();
            $table->boolean('needs_company_completion')->default(false);
            $table->string('business_type')->nullable();
            $table->string('line_of_business')->nullable();
            $table->string('bir_registration_type')->nullable();
            $table->string('business_address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('business_email')->nullable();
            $table->string('business_contact_no', 40)->nullable();
            $table->timestamps();

            $table->unique(['client_id', 'branch_number']);
            $table->index('client_id');
        });

        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('tin_document_path')->nullable();
            $table->string('valid_id_document_path')->nullable();
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->foreignId('client_company_id')->nullable()->after('client_id')->constrained('client_companies')->nullOnDelete();
        });
        Schema::table('bir_form_statuses', function (Blueprint $table) {
            $table->foreignId('client_company_id')->nullable()->after('client_id')->constrained('client_companies')->nullOnDelete();
        });
        Schema::table('bir_forms', function (Blueprint $table) {
            $table->foreignId('client_company_id')->nullable()->after('client_id')->constrained('client_companies')->nullOnDelete();
        });

        $parentIds = collect()
            ->merge(DB::table('users')->where('role', 'client')->pluck('id'))
            ->merge(DB::table('billings')->pluck('client_id'))
            ->merge(DB::table('bir_form_statuses')->pluck('client_id'))
            ->merge(DB::table('bir_forms')->pluck('client_id'))
            ->filter()
            ->unique()
            ->values();

        $existingCodes = DB::table('users')->whereNotNull('client_code')->pluck('client_code');
        $next = $existingCodes->map(function ($code) {
            preg_match('/^EAS-(\d+)$/', (string) $code, $matches);
            return (int) ($matches[1] ?? 0);
        })->max() + 1;

        foreach ($parentIds as $clientId) {
            $user = DB::table('users')->where('id', $clientId)->first();
            if (! $user) {
                continue;
            }

            $clientCode = $user->client_code;
            if (! $clientCode) {
                do {
                    $clientCode = 'EAS-'.str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
                } while ($existingCodes->contains($clientCode));

                DB::table('users')->where('id', $clientId)->update(['client_code' => $clientCode]);
                $existingCodes->push($clientCode);
            }

            $profile = DB::table('client_profiles')->where('user_id', $clientId)->first();
            $companyName = trim((string) $user->business_name) ?: null;

            DB::table('client_companies')->updateOrInsert(
                ['client_id' => $clientId, 'branch_number' => 1],
                [
                    'company_code' => $clientCode.'-01',
                    'company_name' => $companyName,
                    'needs_company_completion' => $companyName === null,
                    'business_type' => $profile->business_type ?? null,
                    'line_of_business' => $profile->line_of_business ?? null,
                    'bir_registration_type' => $profile->bir_registration_type ?? null,
                    'business_address' => $profile->business_address ?? null,
                    'latitude' => $profile->latitude ?? null,
                    'longitude' => $profile->longitude ?? null,
                    'business_email' => $user->email,
                    'business_contact_no' => $profile->contact_no ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $companyId = DB::table('client_companies')
                ->where('client_id', $clientId)->where('branch_number', 1)->value('id');
            DB::table('billings')->where('client_id', $clientId)->update(['client_company_id' => $companyId]);
            DB::table('bir_form_statuses')->where('client_id', $clientId)->update(['client_company_id' => $companyId]);
            DB::table('bir_forms')->where('client_id', $clientId)->update(['client_company_id' => $companyId]);
        }

        Schema::table('billings', function (Blueprint $table) {
            $table->dropUnique('billings_client_period_unique');
            $table->unique(['client_company_id', 'quarter', 'year'], 'billings_company_period_unique');
        });
        Schema::table('bir_form_statuses', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'form_type']);
            $table->unique(['client_company_id', 'form_type'], 'bir_form_statuses_company_form_unique');
        });
    }

    public function down(): void
    {
        /*
         * A parent-level unique key cannot represent two branches in the same
         * billing period or with the same BIR form. Refuse a lossy rollback
         * before altering constraints or deleting the company records.
         */
        $hasDuplicateBillingPeriods = DB::table('billings')
            ->whereNotNull('client_company_id')
            ->select('client_id', 'quarter', 'year')
            ->groupBy('client_id', 'quarter', 'year')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        $hasDuplicateBirForms = DB::table('bir_form_statuses')
            ->whereNotNull('client_company_id')
            ->select('client_id', 'form_type')
            ->groupBy('client_id', 'form_type')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateBillingPeriods || $hasDuplicateBirForms) {
            throw new \RuntimeException('Cannot roll back client companies while branch-specific billing periods or BIR forms exist. Remove or consolidate branch records first.');
        }

        Schema::table('bir_form_statuses', function (Blueprint $table) {
            $table->dropUnique('bir_form_statuses_company_form_unique');
            $table->unique(['client_id', 'form_type']);
            $table->dropConstrainedForeignId('client_company_id');
        });
        Schema::table('bir_forms', fn (Blueprint $table) => $table->dropConstrainedForeignId('client_company_id'));
        Schema::table('billings', function (Blueprint $table) {
            $table->dropUnique('billings_company_period_unique');
            $table->unique(['client_id', 'quarter', 'year'], 'billings_client_period_unique');
            $table->dropConstrainedForeignId('client_company_id');
        });
        Schema::table('client_profiles', fn (Blueprint $table) => $table->dropColumn(['tin_document_path', 'valid_id_document_path']));
        Schema::dropIfExists('client_companies');
    }
};
