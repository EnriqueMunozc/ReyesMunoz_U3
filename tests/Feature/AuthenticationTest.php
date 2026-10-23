<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Persona de prueba', 'email' => 'persona@example.test',
            'password' => 'PruebaSegura123', 'password_confirmation' => 'PruebaSegura123',
        ], $overrides);
    }

    public function test_visitors_can_view_forms_but_cannot_open_dashboard(): void
    {
        $this->get('/login')->assertOk()->assertSee('Correo electrónico');
        $this->get('/registro')->assertOk()->assertSee('Confirma tu contraseña');
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_registration_normalizes_email_hashes_password_and_starts_session(): void
    {
        $oldId = $this->app['session']->getId();
        $this->post('/registro', $this->registration(['email' => ' PERSONA@EXAMPLE.TEST ']))
            ->assertRedirect(route('dashboard'));
        $user = User::sole();
        $this->assertSame('persona@example.test', $user->email);
        $this->assertNotSame('PruebaSegura123', $user->password);
        $this->assertTrue(Hash::check('PruebaSegura123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, $this->app['session']->getId());
        $this->get('/dashboard')->assertOk()->assertSee('Persona de prueba')->assertSee('Tu espacio está listo');
    }

    public function test_duplicate_email_is_rejected_even_with_different_case(): void
    {
        User::factory()->create(['email' => 'persona@example.test']);
        $this->from('/registro')->post('/registro', $this->registration(['email' => 'PERSONA@example.test']))
            ->assertRedirect('/registro')->assertSessionHasErrors(['email' => 'Este correo electrónico ya está registrado.']);
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_registration_rejects_invalid_data_and_does_not_flash_passwords(): void
    {
        $this->from('/registro')->post('/registro', $this->registration([
            'name' => '', 'email' => 'no-es-correo', 'password' => 'corta', 'password_confirmation' => 'otra',
        ]))->assertSessionHasErrors(['name', 'email', 'password'])
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_rejects_long_multibyte_passwords(): void
    {
        $password = str_repeat('ñ', 40).'123';
        $this->post('/registro', $this->registration(['password' => $password, 'password_confirmation' => $password]))
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_successful_login_regenerates_session_and_uses_intended_destination(): void
    {
        $user = User::factory()->create(['password' => 'PruebaSegura123']);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $oldId = $this->app['session']->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'PruebaSegura123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldId, $this->app['session']->getId());
    }

    public function test_wrong_password_and_unknown_email_have_same_generic_error(): void
    {
        $user = User::factory()->create();
        foreach ([$user->email, 'desconocida@example.test'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => 'incorrecta'])
                ->assertSessionHasErrors(['email' => 'El correo o la contraseña no son correctos.']);
            $this->assertGuest();
        }
    }

    public function test_authenticated_users_are_redirected_away_from_guest_forms(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertRedirect(route('dashboard'));
        $this->get('/login')->assertRedirect(route('dashboard'));
        $this->get('/registro')->assertRedirect(route('dashboard'));
        $this->post('/registro', $this->registration())->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_logout_invalidates_session_rotates_csrf_and_blocks_dashboard(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/dashboard');
        $session = $this->app['session'];
        $session->put('private-marker', 'secreto de prueba');
        $oldId = $session->getId();
        $oldToken = $session->token();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNotSame($oldId, $session->getId());
        $this->assertNotSame($oldToken, $session->token());
        $this->assertFalse($session->has('private-marker'));
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/logout')->assertMethodNotAllowed();
    }

    public function test_login_is_blocked_after_five_failures_and_recovers_after_window(): void
    {
        $user = User::factory()->create(['password' => 'PruebaSegura123']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'PruebaSegura123'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertSee('Demasiados intentos');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'PruebaSegura123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_successful_login_clears_failures_for_the_email_and_ip(): void
    {
        $user = User::factory()->create(['password' => 'PruebaSegura123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrecta']);
        $key = 'login:'.hash('sha256', $user->email.'|127.0.0.1');
        $this->assertSame(1, RateLimiter::attempts($key));
        $this->post('/login', ['email' => $user->email, 'password' => 'PruebaSegura123']);
        $this->assertSame(0, RateLimiter::attempts($key));
    }

    public function test_user_data_is_escaped_and_private_response_is_not_cached(): void
    {
        $this->actingAs(User::factory()->create(['name' => '<script>alert(1)</script>']));
        $this->get('/dashboard')->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_each_user_only_sees_their_own_identity_in_dashboard(): void
    {
        $first = User::factory()->create(['name' => 'Primera persona']);
        $second = User::factory()->create(['name' => 'Segunda persona']);
        $this->actingAs($first)->get('/dashboard')->assertSee('Primera persona')->assertDontSee('Segunda persona');
        $this->actingAs($second)->get('/dashboard')->assertSee('Segunda persona')->assertDontSee('Primera persona');
    }

    public function test_a_password_suffix_cannot_bypass_the_bcrypt_length_limit(): void
    {
        $password = str_repeat('A', 70).'12';
        $user = User::factory()->create(['password' => $password]);
        $this->post('/login', ['email' => $user->email, 'password' => $password.'extra'])
            ->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_ip_limit_blocks_attempts_that_rotate_email_addresses(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->post('/login', ['email' => "persona{$attempt}@example.test", 'password' => 'incorrecta'])
                ->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'otra@example.test', 'password' => 'incorrecta'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
    }
}
