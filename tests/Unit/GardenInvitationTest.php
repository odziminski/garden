<?php

namespace Tests\Unit;

use App\Models\GardenInvitation;
use PHPUnit\Framework\TestCase;

class GardenInvitationTest extends TestCase
{
    public function test_invitation_tokens_are_unique_and_digests_are_deterministic(): void
    {
        $token = GardenInvitation::generateToken();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertNotSame($token, GardenInvitation::generateToken());
        $this->assertNotSame($token, GardenInvitation::digest($token));
        $this->assertSame(hash('sha256', $token), GardenInvitation::digest($token));
    }
}
