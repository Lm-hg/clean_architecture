<?php

namespace Tests\Unit\Application\UseCases\Abonnement;

use App\Application\UseCases\Abonnement\GetAbonnementUseCase;
use App\Domain\Entities\Abonnement;
use App\Domain\Exceptions\NotFoundException;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\ValueObjects\Pricing\Price;
use PHPUnit\Framework\TestCase;

class GetAbonnementUseCaseTest extends TestCase
{
    public function testExecuteReturnsDtoWhenFound()
    {
        $abonnement = new Abonnement(
            'user-1',
            'parking-1',
            Abonnement::TYPE_TOTAL,
            [],
            new \DateTime('2025-01-01'),
            new \DateTime('2025-12-31'),
            Price::fromFloat(100.0),
            new \DateTime(),
            new \DateTime(),
            'id-123'
        );

        $repo = $this->createMock(AbonnementRepositoryInterface::class);
        $repo->method('findById')->with('id-123')->willReturn($abonnement);

        $uc = new GetAbonnementUseCase($repo);
        $dto = $uc->execute('id-123');

        $this->assertEquals('id-123', $dto->id);
        $this->assertEquals('user-1', $dto->userId);
        $this->assertEquals('parking-1', $dto->parkingId);
        $this->assertEquals(100.0, $dto->monthlyPrice->getAmount());
    }

    public function testExecuteThrowsNotFoundWhenMissing()
    {
        $repo = $this->createMock(AbonnementRepositoryInterface::class);
        $repo->method('findById')->with('missing')->willReturn(null);

        $uc = new GetAbonnementUseCase($repo);

        $this->expectException(NotFoundException::class);
        $uc->execute('missing');
    }
}
