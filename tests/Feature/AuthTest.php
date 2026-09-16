<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_landing_page(): void
    {
        $this->get('/')->assertOk()->assertSee('Temukan celah di API kamu');
    }

    public function test_authenticated_user_is_sent_from_landing_to_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirect(route('tickets.index'));
    }

    public function test_guest_is_redirected_to_login_from_protected_pages(): void
    {
        foreach (['/tickets', '/tickets/create', '/profile', '/dashboard'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_auth_pages_render(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Masuk ke akun');
        $this->get(route('register'))->assertOk()->assertSee('Buat akun');
        $this->get(route('password.request'))->assertOk()->assertSee('Lupa password');
        $this->get(route('password.reset', 'token'))->assertOk()->assertSee('Buat password baru');
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $this->post(route('register'), [
            'name' => 'Rina',
            'email' => 'rina@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('tickets.index'));

        $this->assertAuthenticated();
        $this->assertTrue(Hash::check('rahasia123', User::firstWhere('email', 'rina@example.com')->password));
    }

    public function test_register_rejects_duplicate_email_and_short_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('register'), [
            'name' => 'Budi',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('tickets.index'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_forgot_password_sends_reset_link_without_revealing_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('passwordbaru1', $user->fresh()->password));
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.store'), [
            'token' => 'invalid',
            'email' => $user->email,
            'password' => 'passwordbaru1',
            'password_confirmation' => 'passwordbaru1',
        ])->assertSessionHasErrors('email');
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Profil & keamanan');

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Nama Baru', 'email' => 'baru@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('baru@example.com', $user->fresh()->email);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => 'lama12345']);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'salah',
                'password' => 'baru123456',
                'password_confirmation' => 'baru123456',
            ])->assertSessionHasErrorsIn('password', 'current_password');

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'lama12345',
                'password' => 'baru123456',
                'password_confirmation' => 'baru123456',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('baru123456', $user->fresh()->password));
    }

    public function test_account_deletion_removes_user_and_tickets(): void
    {
        $user = User::factory()->create(['password' => 'hapus12345']);
        $user->tickets()->create(['title' => 'API', 'api_url' => 'https://example.com']);

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'hapus12345'])
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('tickets', 0);
    }
}
