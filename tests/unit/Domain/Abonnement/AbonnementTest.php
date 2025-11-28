<?php

namespace Tests\Unit\Domain\Abonnement;

use PHPUnit\Framework\TestCase;
use App\Domain\Entities\Abonnement;
use App\Domain\ValueObjects\TimeSlot;
use App\Domain\ValueObjects\Pricing\Price;

class AbonnementTest extends TestCase
{
    public function test_total_subscription_covers_any_time(): void
    {
        $start = new \DateTime('2025-11-01 00:00:00');
        $end = new \DateTime('2026-10-31 23:59:59');
        $price = Price::fromCents(10000, 'EUR');
        $ab = new Abonnement('user1', 'parking1', Abonnement::TYPE_TOTAL, [], $start, $end, $price, new \DateTime(), new \DateTime());

        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-24 03:00:00')));
        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-24 15:00:00')));
    }

    public function test_specific_slots_cover(): void
    {
        // Thursday 10:00 -> Friday 10:00 (specific)
        $slot = TimeSlot::fromDayTime(4, '10:00', 5, '10:00');
        $start = new \DateTime('2025-11-01 00:00:00');
        $end = new \DateTime('2026-10-31 23:59:59');
        $price = Price::fromCents(5000, 'EUR');
        $ab = new Abonnement('user1', 'parking1', Abonnement::TYPE_SPECIFIQUE, [$slot], $start, $end, $price, new \DateTime(), new \DateTime());

        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-27 12:00:00'))); // Thursday
        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-28 09:00:00'))); // Friday early
        $this->assertFalse($ab->coversDateTime(new \DateTime('2025-11-28 11:00:00')));
    }

    public function test_multiple_slots_combination(): void
    {
        // Two slots in same abonnement:
        // Monday 08:00-10:00 and Monday 18:00 -> Tuesday 02:00
        $slot1 = TimeSlot::fromHm(1, '08:00', '10:00');
        $slot2 = TimeSlot::fromDayTime(1, '18:00', 2, '02:00');

        $start = new \DateTime('2025-11-01 00:00:00');
        $end = new \DateTime('2026-10-31 23:59:59');
        $price = Price::fromCents(6000, 'EUR');
        $ab = new Abonnement('user1', 'parking1', Abonnement::TYPE_SPECIFIQUE, [$slot1, $slot2], $start, $end, $price, new \DateTime(), new \DateTime());

        // Covered times
        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-24 08:30:00'))); // Mon morning
        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-24 19:00:00'))); // Mon evening
        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-25 01:30:00'))); // Tue early

        // Not covered
        $this->assertFalse($ab->coversDateTime(new \DateTime('2025-11-24 12:00:00'))); // Mon noon
        $this->assertFalse($ab->coversDateTime(new \DateTime('2025-11-25 03:00:00'))); // Tue after slot
    }

    public function test_multiple_non_contiguous_slots_different_days(): void
    {
        // Wednesday 09:00-12:00 and Friday 14:00-16:00
        $slot1 = TimeSlot::fromHm(3, '09:00', '12:00');
        $slot2 = TimeSlot::fromHm(5, '14:00', '16:00');

        $start = new \DateTime('2025-11-01 00:00:00');
        $end = new \DateTime('2026-10-31 23:59:59');
        $price = Price::fromCents(4000, 'EUR');
        $ab = new Abonnement('user1', 'parking1', Abonnement::TYPE_SPECIFIQUE, [$slot1, $slot2], $start, $end, $price, new \DateTime(), new \DateTime());

        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-26 10:00:00'))); // Wed
        $this->assertTrue($ab->coversDateTime(new \DateTime('2025-11-28 15:00:00'))); // Fri
        $this->assertFalse($ab->coversDateTime(new \DateTime('2025-11-27 10:00:00'))); // Thu
    }
}
