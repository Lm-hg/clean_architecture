<?php

namespace Tests\Unit\Application\UseCases\Abonnement;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\Abonnement\CreateAbonnementUseCase;
use App\Application\dtos\Abonnement\CreateAbonnementDto;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\ValueObjects\TimeSlot;
use App\Domain\ValueObjects\Pricing\Price;
use App\Domain\Entities\Abonnement;

class CreateAbonnementUseCaseTest extends TestCase
{
    private AbonnementRepositoryInterface|MockObject $repoMock;
    private CreateAbonnementUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repoMock = $this->createMock(AbonnementRepositoryInterface::class);
        $this->useCase = new CreateAbonnementUseCase($this->repoMock);
    }

    public function test_execute_creates_and_returns_response(): void
    {
        $slot = TimeSlot::fromHm(1, '08:00', '10:00');
        $start = new \DateTime('2025-11-01 00:00:00');
        $end = new \DateTime('2026-10-31 23:59:59');
        $price = Price::fromCents(5000, 'EUR');

        $dto = new CreateAbonnementDto('u1', 'p1', 'specifique', [$slot], $start, $end, $price);

        $this->repoMock->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Abonnement $a) {
                // simulate repository assigning id
                return $a;
            });

        $resp = $this->useCase->execute($dto);

        $this->assertNotNull($resp);
        $this->assertEquals('u1', $resp->userId);
        $this->assertEquals('p1', $resp->parkingId);
        $this->assertEquals('specifique', $resp->type);
    }
}
