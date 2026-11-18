<?php

namespace Tests\Unit;

use App\Enums\OnlineStatus;
use App\Models\Customer;
use Tests\TestCase;

class OnlineStatusTest extends TestCase
{
    public function test_status_is_derived_from_last_seen_at(): void
    {
        $this->travelTo(now()->startOfMinute());

        $this->assertSame(OnlineStatus::Unknown, (new Customer)->status);
        $this->assertSame(OnlineStatus::Online, (new Customer)->forceFill(['last_seen_at' => now()->subMinutes(5)])->status);
        $this->assertSame(OnlineStatus::Offline, (new Customer)->forceFill(['last_seen_at' => now()->subMinutes(6)])->status);
    }
}
