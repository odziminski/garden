<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_opens_today_unless_a_protected_page_was_requested(): void
    {
        $user = User::factory()->create(['password' => Hash::make('a regular password')]);
        $credentials = ['email' => $user->email, 'password' => 'a regular password'];

        $this->post(route('login'), $credentials)->assertRedirect(route('care.today'));
        $this->post(route('logout'));
        $this->get(route('browse'))->assertRedirect(route('login'));
        $this->post(route('login'), $credentials)->assertRedirect(route('browse'));
    }

    public function test_public_authentication_screens_have_a_main_landmark_and_labelled_fields(): void
    {
        $screens = [
            [route('login'), ['email', 'password', 'remember']],
            [route('register'), ['email', 'password', 'password-confirm']],
            [route('password.request'), ['email']],
            [route('password.reset', ['token' => 'reset-token']), ['email', 'password', 'password-confirm']],
        ];

        foreach ($screens as [$url, $fields]) {
            $response = $this->get($url)->assertOk();
            $page = new DOMDocument();
            $page->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $elements = new DOMXPath($page);

            $this->assertCount(1, $elements->query('//main'), "Main landmark on {$url}");

            foreach ($fields as $field) {
                $this->assertCount(1, $elements->query("//main//label[@for='{$field}']"), "Label for {$field} on {$url}");
                $this->assertCount(1, $elements->query("//main//input[@id='{$field}']"), "Input for {$field} on {$url}");
            }
        }
    }

    public function test_registration_rejects_password_over_the_bcrypt_byte_limit(): void
    {
        $password = str_repeat("\u{00E9}", 37);

        $this->postJson(route('register'), [
            'email' => 'gardener@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_reset_keeps_working_after_authentication_upgrade(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a different password',
            'password_confirmation' => 'a different password',
        ])->assertRedirect(route('welcome'));

        $this->assertTrue(Hash::check('a different password', $user->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($user, $token));
    }

    public function test_reset_link_responses_do_not_disclose_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $sent = $this->postJson(route('password.email'), ['email' => $user->email])->assertOk();
        $this->postJson(route('password.email'), ['email' => 'missing@example.test'])
            ->assertOk()->assertExactJson($sent->json());
        $this->postJson(route('password.email'), ['email' => $user->email])
            ->assertOk()->assertExactJson($sent->json());

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_link_requests_are_limited_across_email_addresses(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('password.email'), ['email' => "missing{$i}@example.test"])->assertOk();
        }

        $this->postJson(route('password.email'), ['email' => 'sixth@example.test'])->assertTooManyRequests();
    }

    public function test_failed_password_reset_responses_do_not_disclose_whether_an_account_exists(): void
    {
        $user = User::factory()->create();
        $credentials = [
            'token' => str_repeat('x', 64),
            'password' => 'a different password',
            'password_confirmation' => 'a different password',
        ];

        $known = $this->postJson(route('password.update'), $credentials + ['email' => $user->email])
            ->assertUnprocessable();
        $this->postJson(route('password.update'), $credentials + ['email' => 'missing@example.test'])
            ->assertUnprocessable()->assertExactJson($known->json());
    }

    public function test_password_reset_rejects_nonscalar_tokens_at_validation(): void
    {
        $user = User::factory()->create();
        Password::broker()->createToken($user);

        $this->postJson(route('password.update'), [
            'token' => ['invalid'],
            'email' => $user->email,
            'password' => 'a different password',
            'password_confirmation' => 'a different password',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');
    }
}
