<?php

namespace App\Domain\ValueObjects\Parking;

use App\Domain\ValueObjects\TimeSlot;

class OpeningHours
{
    /** @var TimeSlot[][] indexed by dayOfWeek => array of TimeSlot */
    private array $slotsByDay = [];

    /**
     * @param TimeSlot[] $timeSlots
     */
    public function __construct(array $timeSlots = [])
    {
        foreach ($timeSlots as $slot) {
            if (!($slot instanceof TimeSlot)) {
                throw new \InvalidArgumentException('All opening hours must be TimeSlot instances');
            }
            // A TimeSlot can span multiple days (and wrap over week boundary).
            // Add the slot to every day it covers so `isOpenAt` can check per-day lists.
            $start = $slot->getStartDay();
            $end = $slot->getEndDay();

            if ($start <= $end) {
                for ($d = $start; $d <= $end; $d++) {
                    $this->slotsByDay[$d][] = $slot;
                }
            } else {
                // wraps week boundary: start..7 and 1..end
                for ($d = $start; $d <= 7; $d++) {
                    $this->slotsByDay[$d][] = $slot;
                }
                for ($d = 1; $d <= $end; $d++) {
                    $this->slotsByDay[$d][] = $slot;
                }
            }
        }
    }

    public function isOpenAt(\DateTimeInterface $dateTime): bool
    {
        $day = (int)$dateTime->format('N'); // 1..7
        $minute = (int)$dateTime->format('G') * 60 + (int)$dateTime->format('i');
        if (!isset($this->slotsByDay[$day])) {
            return false;
        }
        foreach ($this->slotsByDay[$day] as $slot) {
            if ($slot->isActiveAt($day, $minute)) {
                return true;
            }
        }
        return false;
    }

    public function isOpenDuring(\DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        // Simple check: ensure every day/time in range has at least one slot enveloping the times.
        $period = new \DatePeriod($start, new \DateInterval('PT1H'), $end);
        foreach ($period as $dt) {
            if (!$this->isOpenAt($dt)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Retourne tous les TimeSlot uniques de cet OpeningHours
     * 
     * @return TimeSlot[]
     */
    public function getAllSlots(): array
    {
        $uniqueSlots = [];
        $seenSlots = [];
        
        foreach ($this->slotsByDay as $daySlots) {
            foreach ($daySlots as $slot) {
                $slotKey = spl_object_hash($slot);
                if (!isset($seenSlots[$slotKey])) {
                    $uniqueSlots[] = $slot;
                    $seenSlots[$slotKey] = true;
                }
            }
        }
        
        return $uniqueSlots;
    }
}
