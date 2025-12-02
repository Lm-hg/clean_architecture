<?php
declare(strict_types=1);

namespace Tests\Unit\Stationnement;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\Stationnement\EnterParkingUseCase;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\AbonnementRepositoryInterface;
use App\Domain\Entities\Stationnement;
use App\Domain\Entities\Parking;
use App\Domain\Entities\Reservation;
use App\Domain\Entities\Abonnement;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;
use App\Domain\ValueObjects\Pricing\Tarif;
use App\Domain\ValueObjects\Price\Price;
use App\Domain\ValueObjects\TimeSlot;

class EnterParkingUseCaseTest extends TestCase
{
    private StationnementRepositoryInterface|MockObject $stationnementRepositoryMock;
    private ParkingRepositoryInterface|MockObject $parkingRepositoryMock;
    private ReservationRepositoryInterface|MockObject $reservationRepositoryMock;
    private AbonnementRepositoryInterface|MockObject $abonnementRepositoryMock;
    private EnterParkingUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->stationnementRepositoryMock = $this->createMock(StationnementRepositoryInterface::class);
        $this->parkingRepositoryMock = $this->createMock(ParkingRepositoryInterface::class);
        $this->reservationRepositoryMock = $this->createMock(ReservationRepositoryInterface::class);
        $this->abonnementRepositoryMock = $this->createMock(AbonnementRepositoryInterface::class);
        
        $this->useCase = new EnterParkingUseCase(
            $this->stationnementRepositoryMock,
            $this->parkingRepositoryMock,
            $this->reservationRepositoryMock,
            $this->abonnementRepositoryMock
        );
    }

    public function test_execute_creates_stationnement_successfully(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-456';
        $parking = $this->createMockParking($parkingId, true, 10, 5);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn($parking);

        $parking->expects($this->once())
            ->method('isOpenAt')
            ->willReturn(true);

        $parking->expects($this->once())
            ->method('hasAvailableSpots')
            ->willReturn(true);

        $this->stationnementRepositoryMock
            ->expects($this->once())
            ->method('findActiveByUserId')
            ->with($userId)
            ->willReturn([]);

        $parking->expects($this->once())
            ->method('decrementAvailableSpots');

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
        $result = $this->useCase->execute($userId, $parkingId);

        // Assert
        $this->assertInstanceOf(Stationnement::class, $result);
        $this->assertEquals($userId, $result->getUserId());
        $this->assertEquals($parkingId, $result->getParkingId());
    }

    public function test_execute_throws_exception_when_parking_not_found(): void
    {
        // Arrange
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        // Assert
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Parking non trouvé');

        // Act
        $this->useCase->execute('user-123', 'non-existent-parking');
    }

    public function test_execute_throws_exception_when_parking_closed(): void
    {
        // Arrange
        $parking = $this->createMockParking('parking-456', true, 10, 5);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn($parking);

        $parking->expects($this->once())
            ->method('isOpenAt')
            ->willReturn(false);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le parking est fermé');

        // Act
        $this->useCase->execute('user-123', 'parking-456');
    }

    public function test_execute_throws_exception_when_parking_full(): void
    {
        // Arrange
        $parking = $this->createMockParking('parking-456', true, 10, 0);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn($parking);

        $parking->expects($this->once())
            ->method('isOpenAt')
            ->willReturn(true);

        $parking->expects($this->once())
            ->method('hasAvailableSpots')
            ->willReturn(false);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le parking est complet');

        // Act
        $this->useCase->execute('user-123', 'parking-456');
    }

    public function test_execute_validates_reservation_when_provided(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-456';
        $reservationId = 'reservation-789';
        
        $parking = $this->createMockParking($parkingId, true, 10, 5);
        $reservation = $this->createMock(Reservation::class);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->willReturn($parking);

        $parking->method('isOpenAt')->willReturn(true);
        $parking->method('hasAvailableSpots')->willReturn(true);

        $this->reservationRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($reservationId)
            ->willReturn($reservation);

        $reservation->expects($this->once())
            ->method('belongsToUser')
            ->with($userId)
            ->willReturn(true);

        $reservation->expects($this->once())
            ->method('isActiveAt')
            ->willReturn(true);

        $this->stationnementRepositoryMock
            ->expects($this->once())
            ->method('findActiveByUserId')
            ->willReturn([]);

        $parking->expects($this->once())
            ->method('decrementAvailableSpots');

        $this->parkingRepositoryMock->expects($this->once())->method('save')->willReturn($parking);
        $this->stationnementRepositoryMock->expects($this->once())->method('save')->willReturnCallback(fn($s) => $s);

        // Act
        $result = $this->useCase->execute($userId, $parkingId, $reservationId);

        // Assert
        $this->assertInstanceOf(Stationnement::class, $result);
        $this->assertEquals($reservationId, $result->getReservationId());
    }

    private function createMockParking(string $id, bool $isAvailable, int $totalSpots, int $availableSpots): Parking|MockObject
    {
        $parking = $this->createMock(Parking::class);
        $parking->method('getId')->willReturn($id);
        $parking->method('getIsAvailable')->willReturn($isAvailable);
        $parking->method('hasAvailableSpots')->willReturn($availableSpots > 0);
        $parking->method('getTotalSpots')->willReturn($totalSpots);
        $parking->method('getAvailableSpots')->willReturn($availableSpots);
        return $parking;
    }
}

