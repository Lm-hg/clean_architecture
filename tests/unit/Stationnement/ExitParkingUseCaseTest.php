<?php
declare(strict_types=1);

namespace Tests\Unit\Stationnement;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\Stationnement\ExitParkingUseCase;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Services\PricingService;
use App\Domain\Services\PenaltyCalculator;
use App\Domain\Entities\Stationnement;
use App\Domain\Entities\Parking;
use App\Domain\Entities\Reservation;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\ValueObjects\Price\Price;

class ExitParkingUseCaseTest extends TestCase
{
    private StationnementRepositoryInterface|MockObject $stationnementRepositoryMock;
    private ParkingRepositoryInterface|MockObject $parkingRepositoryMock;
    private ReservationRepositoryInterface|MockObject $reservationRepositoryMock;
    private AbonnementRepositoryInterface|MockObject $abonnementRepositoryMock;
    private PricingService|MockObject $pricingServiceMock;
    private PenaltyCalculator|MockObject $penaltyCalculatorMock;
    private ExitParkingUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->stationnementRepositoryMock = $this->createMock(StationnementRepositoryInterface::class);
        $this->parkingRepositoryMock = $this->createMock(ParkingRepositoryInterface::class);
        $this->reservationRepositoryMock = $this->createMock(ReservationRepositoryInterface::class);
        $this->abonnementRepositoryMock = $this->createMock(AbonnementRepositoryInterface::class);
        $this->pricingServiceMock = $this->createMock(PricingService::class);
        $this->penaltyCalculatorMock = $this->createMock(PenaltyCalculator::class);
        
        $this->useCase = new ExitParkingUseCase(
            $this->stationnementRepositoryMock,
            $this->parkingRepositoryMock,
            $this->reservationRepositoryMock,
            $this->abonnementRepositoryMock,
            $this->pricingServiceMock,
            $this->penaltyCalculatorMock
        );
    }

    public function test_execute_exits_stationnement_successfully(): void
    {
        // Arrange
        $stationnementId = 'stationnement-123';
        $userId = 'user-456';
        $parkingId = 'parking-789';
        
        $entryTime = new \DateTime('-2 hours');
        $stationnement = new Stationnement(
            $userId,
            $parkingId,
            $entryTime,
            $entryTime,
            $entryTime
        );

        $parking = $this->createMock(Parking::class);

        $this->stationnementRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($stationnementId)
            ->willReturn($stationnement);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn($parking);

        $this->pricingServiceMock
            ->expects($this->once())
            ->method('calculateParkingPrice')
            ->willReturn(Price::fromFloat(10.0, 'EUR'));

        $this->penaltyCalculatorMock
            ->expects($this->once())
            ->method('calculatePenalty')
            ->willReturn(0.0);

        $parking->method('getAvailableSpots')->willReturn(9);
        $parking->method('getTotalSpots')->willReturn(10);
        $parking->expects($this->once())
            ->method('incrementAvailableSpots');

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($parking)
            ->willReturn($parking);

        $this->stationnementRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Stationnement $s) {
                return $s;
            });

        // Act
        $result = $this->useCase->execute($stationnementId, $userId);

        // Assert
        $this->assertInstanceOf(Stationnement::class, $result);
        $this->assertNotNull($result->getExitTime());
        $this->assertNotNull($result->getPrice());
        $this->assertTrue($result->isCompleted() || $result->isPenalized());
    }

    public function test_execute_throws_exception_when_stationnement_not_found(): void
    {
        // Arrange
        $this->stationnementRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        // Assert
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Stationnement non trouvé');

        // Act
        $this->useCase->execute('non-existent', 'user-123');
    }

    public function test_execute_applies_penalty_when_overrun(): void
    {
        // Arrange
        $stationnementId = 'stationnement-123';
        $userId = 'user-456';
        $parkingId = 'parking-789';
        $reservationId = 'reservation-999';
        
        $entryTime = new \DateTime('-2 hours');
        $reservationStart = new \DateTime('-2 hours');
        $reservationEnd = new \DateTime('-1 hour');
        
        $stationnement = new Stationnement(
            $userId,
            $parkingId,
            $entryTime,
            $entryTime,
            $entryTime,
            $reservationId
        );

        $parking = $this->createMock(Parking::class);
        $reservation = $this->createMock(Reservation::class);
        
        // Mock reservation start/end times
        $reservation->method('getStartTime')->willReturn($reservationStart);
        $reservation->method('getEndTime')->willReturn($reservationEnd);
        $reservation->method('isActive')->willReturn(false); // Already completed or not active

        $this->stationnementRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn($stationnement);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn($parking);

        $this->reservationRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($reservationId)
            ->willReturn($reservation);

        // Since there is a reservation, calculateReservationPrice is called
        $this->pricingServiceMock
            ->expects($this->once())
            ->method('calculateReservationPrice')
            ->willReturn(Price::fromFloat(10.0, 'EUR'));

        $this->penaltyCalculatorMock
            ->expects($this->once())
            ->method('calculatePenalty')
            ->willReturn(25.0); // Pénalité de 25€

        $parking->method('getAvailableSpots')->willReturn(9);
        $parking->method('getTotalSpots')->willReturn(10);
        $parking->expects($this->once())->method('incrementAvailableSpots');
        $this->parkingRepositoryMock->expects($this->once())->method('save')->willReturn($parking);
        $this->stationnementRepositoryMock->expects($this->once())->method('save')->willReturnCallback(fn($s) => $s);

        // Act
        $result = $this->useCase->execute($stationnementId, $userId);

        // Assert
        $this->assertTrue($result->getHasPenalty());
        $this->assertEquals(25.0, $result->getPenaltyAmount());
    }
}

