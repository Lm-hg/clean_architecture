<?php

namespace Tests\Functional;

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    // Un test simple juste pour vérifier que la suite Fonctionnelle se lance
    public function test_application_is_alive(): void
    {
        $this->assertTrue(true);
    }
}