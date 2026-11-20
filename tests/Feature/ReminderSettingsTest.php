<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_links_directly_to_reminder_settings(): void
    {
        $this->actingAs(User::factory()->create())->get(route('care.today'))
            ->assertOk()->assertSee(route('users').'#reminder-heading');
    }

    public function test_only_signed_in_users_can_save_their_own_reminder_preferences(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();

        $this->put('/users/reminders')->assertRedirect(route('login'));

        $this->actingAs($owner)->put('/users/reminders', [
            'reminders_enabled' => '0',
            'reminder_timezone' => 'Europe/Warsaw',
            'reminder_time' => '18:30',
            'user_id' => $otherOwner->id,
        ])->assertRedirect(route('users'));

        $this->assertDatabaseHas('users', [
            'id' => $owner->id,
            'reminders_enabled' => false,
            'reminder_timezone' => 'Europe/Warsaw',
            'reminder_time' => '18:30',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherOwner->id,
            'reminders_enabled' => true,
            'reminder_timezone' => 'UTC',
            'reminder_time' => '10:10',
        ]);
        $this->get(route('users'))->assertOk()->assertSee('Daily care reminders')->assertSee('18:30');
    }

    public function test_invalid_form_values_leave_the_settings_page_readable(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('users'))
            ->followingRedirects()
            ->put(route('reminders.update'), [
                'reminders_enabled' => '1',
                'reminder_timezone' => 'UTC',
                'reminder_time' => ['12:00'],
            ])
            ->assertOk()
            ->assertSee('Daily care reminders')
            ->assertSee('does not match the format');
    }

    public function test_reminder_preferences_validate_time_and_time_zone(): void
    {
        $this->actingAs(User::factory()->create())->putJson('/users/reminders', [
            'reminders_enabled' => true,
            'reminder_timezone' => 'Invalid/Zone',
            'reminder_time' => '24:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['reminder_timezone', 'reminder_time']);
    }
}
