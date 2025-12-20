<?php
declare(strict_types=1);

namespace Tests\Unit\ParkingOwner;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\ParkingOwner\UpdateParkingHoursUseCase;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Entities\Parking;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\TimeSlot;
use App\Domain\Exceptions\EntityNotFoundException;
use App\Domain\Exceptions\UnauthorizedAccessException;

class UpdateParkingHoursUseCaseTest extends TestCase
{
    private ParkingRepositoryInterface|MockObject $parkingRepositoryMock;
    private UpdateParkingHoursUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->parkingRepositoryMock = $this->createMock(ParkingRepositoryInterface::class);
        $this->useCase = new UpdateParkingHoursUseCase($this->parkingRepositoryMock);
    }

    public function test_execute_updates_parking_hours_successfully(): void
    {
        // Arrange
        $parkingId = 'parking-123';
        $ownerId = 'owner-456';
        $newOpeningHours = new OpeningHours([
            TimeSlot::fromHm(1, '09:00', '18:00'), // Monday
            TimeSlot::fromHm(2, '09:00', '18:00'), // Tuesday
        ]);

        $parking = $this->createMock(Parking::class);
        $parking->method('belongsToOwner')->with($ownerId)->willReturn(true);
        $parking->expects($this->once())
            ->method('updateOpeningHours')
            ->with($newOpeningHours);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn($parking);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($parking)
            ->willReturn($parking);

        // Act
        $this->useCase->execute($parkingId, $ownerId, $newOpeningHours);

        // Assert: Pas d'exception levée = succès
        $this->assertTrue(true);
    }

    public function test_execute_throws_exception_when_parking_not_found(): void
    {
        // Arrange
        $parkingId = 'non-existent-parking';
        $ownerId = 'owner-456';
        $newOpeningHours = new OpeningHours([]);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn(null);

        // Assert
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Parking non trouvé');

        // Act
        $this->useCase->execute($parkingId, $ownerId, $newOpeningHours);
    }

    public function test_execute_throws_exception_when_owner_not_authorized(): void
    {
        // Arrange
        $parkingId = 'parking-123';
        $ownerId = 'unauthorized-owner';
        $newOpeningHours = new OpeningHours([]);

        $parking = $this->createMock(Parking::class);
        $parking->method('belongsToOwner')->with($ownerId)->willReturn(false);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($parkingId)
            ->willReturn($parking);

        // Assert
        $this->expectException(UnauthorizedAccessException::class);
        $this->expectExceptionMessage('Vous n\'avez pas accès à ce parking');

        // Act
        $this->useCase->execute($parkingId, $ownerId, $newOpeningHours);
    }
}

