<?php

namespace Tests\Unit\Reservation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\Reservation\CreateReservationUseCase;
use App\Domain\Entities\Reservation;
use App\Domain\Repositories\ReservationRepositoryInterface;

class CreateReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface|MockObject $repositoryMock;
    private CreateReservationUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock du repository
        $this->repositoryMock = $this->createMock(ReservationRepositoryInterface::class);
        
        // Instance du Use Case
        $this->useCase = new CreateReservationUseCase($this->repositoryMock);
    }

    public function test_execute_creates_reservation_when_no_conflict(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 12:00:00');
        
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
        $this->assertEquals(Reservation::STATUS_PENDING, $result->getStatus());
    }

    public function test_execute_throws_exception_when_conflict_exists(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 12:00:00');
        
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

        // Repository ne doit pas être appelé car la validation échoue avant
        $this->repositoryMock->expects($this->never())->method('findReservationsInInterval');
        $this->repositoryMock->expects($this->never())->method('save');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La date de début doit être antérieure à la date de fin.");

        // Act
        $this->useCase->execute($userId, $parkingId, $start, $end);
    }

    public function test_execute_propagates_entity_validation_exception_for_short_duration(): void
    {
        // Arrange
        $userId = 'user-123';
        $parkingId = 'parking-123';
        $start = new \DateTime('2025-01-01 10:00:00');
        $end = new \DateTime('2025-01-01 10:10:00'); // 10 minutes (min 15)

        // Le repo vérifie la dispo (c'est un appel de lecture, donc ça passe)
        $this->repositoryMock
            ->expects($this->once())
            ->method('findReservationsInInterval')
            ->willReturn([]); // Pas de conflit

        // Mais save n'est jamais appelé car new Reservation() va échouer
        $this->repositoryMock->expects($this->never())->method('save');

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Minimum duration is 15 minutes");

        // Act
        $this->useCase->execute($userId, $parkingId, $start, $end);
    }
}

