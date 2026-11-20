<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

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
}
