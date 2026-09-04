<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $email = 'test'.uniqid().'@gmail.com';

        $response = $this->post('/register', [
            'name' => 'Test User',
            'business_name' => 'Test Trading',
            'email' => $email,
            'pin' => '1234',
            'pin_confirmation' => '1234',
            'terms' => true,
            'business_type' => 'Sole Proprietorship',
            'line_of_business' => 'Retail & Wholesale',
            'bir_registration_type' => 'Non-VAT',
            'business_address' => '123 Mabini St, Cebu City',
            'contact_no' => '09171234567',
            'second_contact_name' => 'Jane Doe',
            'second_contact_channel' => 'viber',
            'second_contact_no' => 'jane.doe',
            'second_email' => 'jane@example.com',
            'birth_date' => '1990-01-01',
            'tin_no' => '123-456-789',
            'mother_maiden_name' => 'Maria Santos',
            'father_name' => 'Juan Reyes',
        ]);

        $this->assertDatabaseHas('users', ['email' => $email]);
        $this->assertGuest();
        $response->assertRedirect(route('verify.account'));
    }
}
