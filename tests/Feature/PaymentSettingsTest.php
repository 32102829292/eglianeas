<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Payment Settings QA Admin',
            'email' => 'payment-admin-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function validRow(array $overrides = []): array
    {
        return array_merge([
            'bank_name' => 'BDO',
            'account_number' => '001234567890',
            'account_name' => 'Harris Egliane',
            'existing_bank_qr_code' => '',
            'bank_qr_code' => null,
        ], $overrides);
    }

    private function postSettings(array $payload, User $admin)
    {
        return $this->actingAs($admin)
            ->post(route('admin.billing.paymentSettings.update'), $payload);
    }

    public function test_valid_bdo_account_saves(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '09171234567',
            'bank_accounts' => [$this->validRow()],
        ], $admin);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $response->assertSessionHasNoErrors();

        $this->assertSame('09171234567', Setting::get('gcash_number'));
        $this->assertSame([
            [
                'bank_name' => 'BDO',
                'account_number' => '001234567890',
                'account_name' => 'Harris Egliane',
                'bank_qr_code' => '',
            ],
        ], Setting::get('bank_accounts'));
    }

    public function test_bank_name_aliases_resolve_server_side(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [[
                'bank_name' => 'Banco de Oro',
                'account_number' => '001234567890',
                'account_name' => 'Harris Egliane',
                'existing_bank_qr_code' => '',
                'bank_qr_code' => null,
            ]],
        ], $admin);

        $response->assertSessionHasNoErrors();
        $this->assertCount(1, Setting::get('bank_accounts'));
        $this->assertSame('Banco de Oro', Setting::get('bank_accounts')[0]['bank_name']);
    }

    public function test_multiple_valid_bank_accounts_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [
                $this->validRow(),
                $this->validRow([
                    'bank_name' => 'BPI',
                    'account_number' => '1234567890',
                ]),
                $this->validRow([
                    'bank_name' => 'Metrobank',
                    'account_number' => '1234567890123',
                ]),
            ],
        ], $admin);

        $response->assertSessionHasNoErrors();
        $stored = Setting::get('bank_accounts');
        $this->assertCount(3, $stored);
        $this->assertSame('BDO', $stored[0]['bank_name']);
        $this->assertSame('BPI', $stored[1]['bank_name']);
        $this->assertSame('Metrobank', $stored[2]['bank_name']);
    }

    public function test_valid_account_without_qr_saves(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow()],
        ], $admin);

        $response->assertSessionHasNoErrors();
        $this->assertSame('', Setting::get('bank_accounts')[0]['bank_qr_code']);
    }

    public function test_valid_account_with_qr_upload_saves(): void
    {
        Storage::fake('supabase');

        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [[
                'bank_name' => 'BDO',
                'account_number' => '001234567890',
                'account_name' => 'Harris Egliane',
                'existing_bank_qr_code' => '',
                'bank_qr_code' => UploadedFile::fake()->image('qr.png'),
            ]],
        ], $admin);

        $response->assertSessionHasNoErrors();
        $stored = Setting::get('bank_accounts');
        $this->assertStringStartsWith('payment-images/', $stored[0]['bank_qr_code']);
        Storage::disk('supabase')->assertExists($stored[0]['bank_qr_code']);
    }

    public function test_missing_bank_name_blocks_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '09171234567',
            'bank_accounts' => [$this->validRow(['bank_name' => ''])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.bank_name');
        $this->assertSame(null, Setting::get('bank_accounts'));
        $this->assertSame(null, Setting::get('gcash_number'));
    }

    public function test_missing_account_name_blocks_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow(['account_name' => ''])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.account_name');
        $this->assertSame(null, Setting::get('bank_accounts'));
    }

    public function test_missing_account_number_blocks_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow(['account_number' => ''])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.account_number');
        $this->assertSame(null, Setting::get('bank_accounts'));
    }

    public function test_invalid_account_number_format_blocks_save(): void
    {
        $admin = $this->admin();

        // BDO must be 10-12 digits; 9 digits is invalid.
        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow(['account_number' => '123456789'])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.account_number');
        $sessionErrors = session('errors')->get('bank_accounts.0.account_number');
        $this->assertStringContainsString('valid account number', $sessionErrors[0]);
        $this->assertSame(null, Setting::get('bank_accounts'));
    }

    public function test_invalid_characters_in_account_number_blocks_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow(['account_number' => '00123456a890'])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.account_number');
        $this->assertSame(null, Setting::get('bank_accounts'));
    }

    public function test_oversized_account_number_length_blocks_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow(['account_number' => '12345678901234567'])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.account_number');
        $this->assertSame(null, Setting::get('bank_accounts'));
    }

    public function test_one_invalid_row_among_multiple_blocks_the_whole_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '09171234567',
            'bank_accounts' => [
                $this->validRow(),
                $this->validRow(['account_number' => '12345']),
            ],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.1.account_number');
        $this->assertArrayNotHasKey('bank_accounts.0.bank_name', session('errors')->toArray());
        $this->assertSame(null, Setting::get('bank_accounts'));
        $this->assertSame(null, Setting::get('gcash_number'));
    }

    public function test_blank_newly_added_bank_row_blocks_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [
                $this->validRow(),
                $this->validRow([
                    'bank_name' => '',
                    'account_number' => '',
                    'account_name' => '',
                ]),
            ],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.1.bank_name');
        $response->assertSessionHasErrors('bank_accounts.1.account_number');
        $response->assertSessionHasErrors('bank_accounts.1.account_name');
        $this->assertSame(null, Setting::get('bank_accounts'));
    }

    public function test_unsupported_bank_blocks_save(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow(['bank_name' => 'MegaBank', 'account_number' => '123456789012'])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.bank_name');
        $this->assertSame(null, Setting::get('bank_accounts'));
    }

    public function test_failed_save_does_not_overwrite_existing_payment_settings(): void
    {
        Setting::set('gcash_number', '09170000000');
        Setting::set('gcash_qr_code', 'payment-images/gcash-keep.png');
        Setting::set('bank_accounts', [[
            'bank_name' => 'BDO',
            'account_name' => 'Existing Owner',
            'account_number' => '987654321012',
            'bank_qr_code' => 'payment-images/bank-keep.png',
        ]]);

        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '09179999999',
            'bank_accounts' => [$this->validRow(['account_number' => 'xx'])],
        ], $admin);

        $response->assertSessionHasErrors('bank_accounts.0.account_number');

        $this->assertSame('09170000000', Setting::get('gcash_number'));
        $this->assertSame('payment-images/gcash-keep.png', Setting::get('gcash_qr_code'));
        $this->assertSame('Existing Owner', Setting::get('bank_accounts')[0]['account_name']);
        $this->assertSame('payment-images/bank-keep.png', Setting::get('bank_accounts')[0]['bank_qr_code']);
    }

    public function test_failed_save_keeps_entered_values_for_correction(): void
    {
        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '',
            'bank_accounts' => [$this->validRow(['account_number' => '12345'])],
        ], $admin);

        $old = session()->getOldInput('bank_accounts');
        $this->assertSame('BDO', $old[0]['bank_name']);
        $this->assertSame('12345', $old[0]['account_number']);
        $this->assertSame('Harris Egliane', $old[0]['account_name']);
    }

    public function test_payment_settings_page_lists_supported_banks(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.billing.paymentSettings'));

        $response->assertOk();
        $response->assertSee('id="supported-banks"', false);
        $response->assertSee('<option value="BDO"', false);
        $response->assertSee('<option value="BPI"', false);
        $response->assertSee('data-bank-row', false);
    }

    public function test_payment_settings_page_prefills_existing_bank_accounts(): void
    {
        Setting::set('bank_accounts', [[
            'bank_name' => 'BDO',
            'account_name' => 'Harris Egliane',
            'account_number' => '001234567890',
            'bank_qr_code' => 'payment-images/bank-keep.png',
        ]]);

        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.billing.paymentSettings'));

        $response->assertOk();
        $response->assertSee('value="BDO"', false);
        $response->assertSee('value="001234567890"', false);
        $response->assertSee('value="Harris Egliane"', false);
        $response->assertSee('value="payment-images/bank-keep.png"', false);
    }

    public function test_valid_edit_keeps_existing_qr_path_when_no_new_file_uploaded(): void
    {
        Setting::set('gcash_number', '09170000000');
        Setting::set('bank_accounts', [[
            'bank_name' => 'BDO',
            'account_name' => 'Existing Owner',
            'account_number' => '987654321012',
            'bank_qr_code' => 'payment-images/bank-keep.png',
        ]]);

        $admin = $this->admin();

        $response = $this->postSettings([
            'gcash_number' => '09170000000',
            'bank_accounts' => [[
                'bank_name' => 'BDO',
                'account_number' => '987654321012',
                'account_name' => 'Existing Owner',
                'existing_bank_qr_code' => 'payment-images/bank-keep.png',
                'bank_qr_code' => null,
            ]],
        ], $admin);

        $response->assertSessionHasNoErrors();
        $stored = Setting::get('bank_accounts');
        $this->assertCount(1, $stored);
        $this->assertSame('payment-images/bank-keep.png', $stored[0]['bank_qr_code']);
    }
}