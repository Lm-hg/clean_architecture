<?php

namespace Tests\Unit\Domain\TimeSlot;

use PHPUnit\Framework\TestCase;
use App\Domain\ValueObjects\TimeSlot;

class TimeSlotTest extends TestCase
{
    public function test_same_day_slot(): void
    {
        $slot = TimeSlot::fromHm(1, '10:00', '12:00'); // Monday 10-12
        $dt = new \DateTimeImmutable('2025-11-24 11:00:00'); // Monday
        $this->assertTrue($slot->coversDateTime($dt));

        $dt2 = new \DateTimeImmutable('2025-11-24 09:00:00');
        $this->assertFalse($slot->coversDateTime($dt2));
    }

    public function test_night_slot_wraps_to_next_day(): void
    {
        // Monday 18:00 -> Tuesday 08:00
        $slot = TimeSlot::fromDayTime(1, '18:00', 2, '08:00');
        $mondayEvening = new \DateTimeImmutable('2025-11-24 19:00:00');
        $tuesdayMorning = new \DateTimeImmutable('2025-11-25 07:30:00');
        $tuesdayNoon = new \DateTimeImmutable('2025-11-25 12:00:00');

        $this->assertTrue($slot->coversDateTime($mondayEvening));
        $this->assertTrue($slot->coversDateTime($tuesdayMorning));
        $this->assertFalse($slot->coversDateTime($tuesdayNoon));
    }

    public function test_multi_day_slot_over_week_boundary(): void
    {
        // Friday 18:00 -> Monday 10:00 (weekend subscription)
        $slot = TimeSlot::fromDayTime(5, '18:00', 1, '10:00');
        $friEvening = new \DateTimeImmutable('2025-11-28 20:00:00'); // Friday
        $sunMid = new \DateTimeImmutable('2025-11-30 02:00:00');
        $monMorning = new \DateTimeImmutable('2025-12-01 09:00:00');
        $tue = new \DateTimeImmutable('2025-12-02 11:00:00');

        $this->assertTrue($slot->coversDateTime($friEvening));
        $this->assertTrue($slot->coversDateTime($sunMid));
        $this->assertTrue($slot->coversDateTime($monMorning));
        $this->assertFalse($slot->coversDateTime($tue));
    }
}
