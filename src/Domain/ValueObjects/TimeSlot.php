<?php

namespace App\Domain\ValueObjects;

class TimeSlot
{
    private int $startDay; // 1 (Monday) .. 7 (Sunday)
    private int $startMinute; // minutes from 00:00
    private int $endDay; // 1..7
    private int $endMinute; // minutes from 00:00

    /**
     * Represent a weekly recurring slot from startDay/startTime to endDay/endTime.
     * end can be before or equal to start (wraps over week boundary).
     *
     * @param int $startDay 1..7
     * @param int $startMinute 0..1439
     * @param int $endDay 1..7
     * @param int $endMinute 0..1439
     */
    public function __construct(int $startDay, int $startMinute, int $endDay, int $endMinute)
    {
        foreach (['startDay' => $startDay, 'endDay' => $endDay] as $n => $d) {
            if ($d < 1 || $d > 7) {
                throw new \InvalidArgumentException("{$n} must be between 1 and 7");
            }
        }
        foreach (['startMinute' => $startMinute, 'endMinute' => $endMinute] as $n => $m) {
            if ($m < 0 || $m > 24 * 60) {
                throw new \InvalidArgumentException("{$n} out of range");
            }
        }

        $this->startDay = $startDay;
        $this->startMinute = $startMinute;
        $this->endDay = $endDay;
        $this->endMinute = $endMinute;
    }

    /**
     * Legacy helper: same-day slot (start and end on same day)
     */
    public static function fromHm(int $dayOfWeek, string $startHm, string $endHm): self
    {
        [$sh, $sm] = array_map('intval', explode(':', $startHm));
        [$eh, $em] = array_map('intval', explode(':', $endHm));
        $start = $sh * 60 + $sm;
        $end = $eh * 60 + $em;
        return new self($dayOfWeek, $start, $dayOfWeek, $end);
    }

    /**
     * Create a slot given start day/time and end day/time strings (eg "18:00")
     */
    public static function fromDayTime(int $startDay, string $startHm, int $endDay, string $endHm): self
    {
        [$sh, $sm] = array_map('intval', explode(':', $startHm));
        [$eh, $em] = array_map('intval', explode(':', $endHm));
        $start = $sh * 60 + $sm;
        $end = $eh * 60 + $em;
        return new self($startDay, $start, $endDay, $end);
    }

    /**
     * Return true if this weekly slot covers the given date/time
     */
    public function coversDateTime(\DateTimeInterface $dt): bool
    {
        $day = (int)$dt->format('N'); // 1..7
        $minute = (int)$dt->format('G') * 60 + (int)$dt->format('i');
        return $this->isActiveAt($day, $minute);
    }

    /**
     * Backwards-compatible: check by dayOfWeek and minuteOfDay
     */
    public function isActiveAt(int $dayOfWeek, int $timeOfDay): bool
    {
        $weekMinute = ($dayOfWeek - 1) * 1440 + $timeOfDay;
        $start = ($this->startDay - 1) * 1440 + $this->startMinute;
        $end = ($this->endDay - 1) * 1440 + $this->endMinute;

        if ($start < $end) {
            return $weekMinute >= $start && $weekMinute < $end;
        }

        // wraps over week boundary
        return $weekMinute >= $start || $weekMinute < $end;
    }

    public function getStartDay(): int
    {
        return $this->startDay;
    }

    public function getEndDay(): int
    {
        return $this->endDay;
    }

    public function getStartMinute(): int
    {
        return $this->startMinute;
    }

    public function getEndMinute(): int
    {
        return $this->endMinute;
    }

    public function toArray(): array
    {
        return [
            'startDay' => $this->startDay,
            'startMinute' => $this->startMinute,
            'endDay' => $this->endDay,
            'endMinute' => $this->endMinute,
        ];
    }
}
