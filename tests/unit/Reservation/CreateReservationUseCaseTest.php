<?php

namespace Tests\Unit\Reservation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\Reservation\CreateReservationUseCase;
use App\Domain\Entities\Reservation;
use App\Domain\Entities\Parking;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Repositories\ParkingRepositoryInterface;

class CreateReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface|MockObject $repositoryMock;
    private ParkingRepositoryInterface|MockObject $parkingRepositoryMock;
    private CreateReservationUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock des repositories
        $this->repositoryMock = $this->createMock(ReservationRepositoryInterface::class);
        $this->parkingRepositoryMock = $this->createMock(ParkingRepositoryInterface::class);
        
        // Instance du Use Case avec les deux dépendances
        $this->useCase = new CreateReservationUseCase(
            $this->repositoryMock,
            $this->parkingRepositoryMock
        );
    }

    /**
     * Helper pour créer un mock de Parking avec des places disponibles
     */
    private function createParkingMock(string $parkingId, int $availableSpots = 10): Parking|MockObject
    {
        $parking = $this->createMock(Parking::class);
        $parking->method('getId')->willReturn($parkingId);
        $parking->method('getAvailableSpots')->willReturn($availableSpots);
        return $parking;
    }

    public function test_execute_creates_reservation_when_no_conflict(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 12:00:00');
        
        // Mock du parking avec des places disponibles
        $parking = $this->createParkingMock($parkingId, 10);
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn($parking);
        
        // Le parking doit être mis à jour après la réservation
        $parking->expects($this->once())->method('decrementAvailableSpots');
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($parking);
        
        // 1. Le repository ne trouve pas de conflit (retourne tableau vide)
        $this->repositoryMock
            ->expects($this->once())
            ->method('findReservationsInInterval')
            ->with($parkingId, $start, $end)
            ->willReturn([]);
            
        // 2. Le repository sauvegarde la réservation
        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (Reservation $reservation) {
                // On simule que la DB retourne l'objet (avec un ID généré idéalement, mais ici on retourne l'objet)
                return $reservation;
            });
            
        // Act
        $result = $this->useCase->execute($userId, $parkingId, $start, $end);
        
        // Assert
        $this->assertInstanceOf(Reservation::class, $result);
        $this->assertEquals($userId, $result->getUserId());
        $this->assertEquals($parkingId, $result->getParkingId());
        $this->assertEquals($start, $result->getStartTime());
        $this->assertEquals(Reservation::STATUS_CONFIRMED, $result->getStatus());
    }

    public function test_execute_throws_exception_when_conflict_exists(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 12:00:00');
        
        // Mock du parking avec des places disponibles
        $parking = $this->createParkingMock($parkingId, 10);
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn($parking);
        
        // 1. Le repository trouve un conflit (une réservation existante)
        $existingReservation = new Reservation(
            'other-user', 
            $parkingId, 
            new \DateTime('2025-01-01 11:00:00'), 
            new \DateTime('2025-01-01 13:00:00'),
            new \DateTime(),
            new \DateTime()
        );

        $this->repositoryMock
            ->expects($this->once())
            ->method('findReservationsInInterval')
            ->with($parkingId, $start, $end)
            ->willReturn([$existingReservation]);
            
        // 2. On s'attend à ce que save ne soit JAMAIS appelé
        $this->repositoryMock
            ->expects($this->never())
            ->method('save');
            
        // Assert Exception
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Le parking n'est pas disponible sur ce créneau.");
        
        // Act
        $this->useCase->execute($userId, $parkingId, $start, $end);
    }

    public function test_execute_throws_exception_when_start_date_is_after_end_date(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 12:00:00');
        $end = new \DateTime('2025-01-01 10:00:00'); // Fin avant Début

        // Parking repository ne doit pas être appelé car la validation échoue avant
        $this->parkingRepositoryMock->expects($this->never())->method('findById');
        
        // Repository ne doit pas être appelé car la validation échoue avant
        $this->repositoryMock->expects($this->never())->method('findReservationsInInterval');
        $this->repositoryMock->expects($this->never())->method('save');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La date de début doit être antérieure à la date de fin.");

        // Act
        $this->useCase->execute($userId, $parkingId, $start, $end);
    }

    public function test_execute_throws_exception_when_duration_less_than_15_minutes(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 10:10:00'); // 10 minutes (min 15)

        // Parking repository ne doit pas être appelé car la validation de durée échoue avant
        $this->parkingRepositoryMock->expects($this->never())->method('findById');
        
        // Repository ne doit pas être appelé
        $this->repositoryMock->expects($this->never())->method('findReservationsInInterval');
        $this->repositoryMock->expects($this->never())->method('save');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La durée minimale de réservation est de 15 minutes.");

        // Act
        $this->useCase->execute($userId, $parkingId, $start, $end);
    }

    public function test_execute_throws_exception_when_parking_not_found(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-inexistant';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 12:00:00');

        // Le parking n'existe pas
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn(null);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Parking non trouvé.");

        // Act
        $this->useCase->execute($userId, $parkingId, $start, $end);
    }

    public function test_execute_throws_exception_when_no_available_spots(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 12:00:00');

        // Mock du parking sans places disponibles
        $parking = $this->createParkingMock($parkingId, 0);
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn($parking);

        // Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Aucune place disponible dans ce parking.");

        // Act
        $this->useCase->execute($userId, $parkingId, $start, $end);
    }
}

