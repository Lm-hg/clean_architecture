<?php

namespace Tests\Unit\Domain\Parking;

use PHPUnit\Framework\TestCase;
use App\Domain\ValueObjects\TimeSlot;
use App\Domain\ValueObjects\Parking\OpeningHours;

class OpeningHoursTest extends TestCase
{
    public function test_single_day_slot(): void
    {
        $slot = TimeSlot::fromHm(1, '08:00', '17:00');
        $oh = new OpeningHours([$slot]);

        $this->assertTrue($oh->isOpenAt(new \DateTimeImmutable('2025-11-24 09:00:00')));
        $this->assertFalse($oh->isOpenAt(new \DateTimeImmutable('2025-11-24 17:00:00'))); // end exclusive
        $this->assertFalse($oh->isOpenAt(new \DateTimeImmutable('2025-11-25 09:00:00')));
    }

    public function test_multi_day_slot_no_wrap(): void
    {
        $slot = TimeSlot::fromDayTime(1, '20:00', 2, '06:00');
        $oh = new OpeningHours([$slot]);

        $this->assertTrue($oh->isOpenAt(new \DateTimeImmutable('2025-11-24 21:00:00'))); // Mon 21:00
        $this->assertTrue($oh->isOpenAt(new \DateTimeImmutable('2025-11-25 03:00:00'))); // Tue 03:00
        $this->assertFalse($oh->isOpenAt(new \DateTimeImmutable('2025-11-26 03:00:00'))); // Wed 03:00
    }

    public function test_wrap_over_week(): void
    {
        // Saturday 22:00 -> Monday 06:00 (wrap over week boundary)
        $slot = TimeSlot::fromDayTime(6, '22:00', 1, '06:00');
        $oh = new OpeningHours([$slot]);

        $this->assertTrue($oh->isOpenAt(new \DateTimeImmutable('2025-11-29 23:00:00'))); // Sat
        $this->assertTrue($oh->isOpenAt(new \DateTimeImmutable('2025-11-30 12:00:00'))); // Sun
        $this->assertTrue($oh->isOpenAt(new \DateTimeImmutable('2025-12-01 05:00:00'))); // Mon
        $this->assertFalse($oh->isOpenAt(new \DateTimeImmutable('2025-12-02 12:00:00'))); // Tue
    }
}
