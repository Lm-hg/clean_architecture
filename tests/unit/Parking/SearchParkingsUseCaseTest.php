<?php
declare(strict_types=1);

namespace Tests\Unit\Parking;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\Parking\SearchParkingsUseCase;
use App\Domain\Repositories\ParkingRepositoryInterface;
use App\Domain\Repositories\ReservationRepositoryInterface;
use App\Domain\Entities\Parking;
use App\Domain\Entities\Reservation;
use App\Domain\ValueObjects\Parking\Address;
use App\Domain\ValueObjects\Parking\GPSCoordinates;
use App\Domain\ValueObjects\Parking\OpeningHours;
use App\Domain\ValueObjects\Pricing\TarifCollection;
use App\Domain\ValueObjects\Pricing\Tarif;
use App\Domain\ValueObjects\Price\Price;
use App\Domain\ValueObjects\TimeSlot;

class SearchParkingsUseCaseTest extends TestCase
{
    private ParkingRepositoryInterface|MockObject $parkingRepositoryMock;
    private ReservationRepositoryInterface|MockObject $reservationRepositoryMock;
    private SearchParkingsUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->parkingRepositoryMock = $this->createMock(ParkingRepositoryInterface::class);
        $this->reservationRepositoryMock = $this->createMock(ReservationRepositoryInterface::class);
        
        $this->useCase = new SearchParkingsUseCase(
            $this->parkingRepositoryMock,
            $this->reservationRepositoryMock
        );
    }

    public function test_execute_returns_available_parkings_near_location(): void
    {
        // Arrange
        $latitude = 48.8566; // Paris
        $longitude = 2.3522;
        $radius = 5.0;

        $parking = $this->createMockParking('parking-1', true, 10, 5);
        
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findByLocation')
            ->with($latitude, $longitude, $radius)
            ->willReturn([$parking]);

        $this->reservationRepositoryMock
            ->expects($this->never())
            ->method('findReservationsInInterval');

        // Act
        $result = $this->useCase->execute($latitude, $longitude, $radius);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('parking-1', $result[0]->id);
    }

    public function test_execute_filters_out_unavailable_parkings(): void
    {
        // Arrange
        $latitude = 48.8566;
        $longitude = 2.3522;
        $radius = 5.0;

        $availableParking = $this->createMockParking('parking-1', true, 10, 5);
        $unavailableParking = $this->createMockParking('parking-2', false, 10, 0);
        $fullParking = $this->createMockParking('parking-3', true, 10, 0);

        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findByLocation')
            ->willReturn([$availableParking, $unavailableParking, $fullParking]);

        // Act
        $result = $this->useCase->execute($latitude, $longitude, $radius);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('parking-1', $result[0]->id);
    }

    public function test_execute_checks_availability_for_time_range(): void
    {
        // Arrange
        $latitude = 48.8566;
        $longitude = 2.3522;
        $radius = 5.0;
        $startTime = new \DateTime('2024-01-15 10:00:00');
        $endTime = new \DateTime('2024-01-15 12:00:00');

        $parking = $this->createMockParking('parking-1', true, 10, 5);
        
        // Override isOpenDuring for this specific test
        $parking->method('isOpenDuring')
            ->with($startTime, $endTime)
            ->willReturn(true);
        
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findByLocation')
            ->willReturn([$parking]);

        $this->reservationRepositoryMock
            ->expects($this->once())
            ->method('findReservationsInInterval')
            ->with($this->equalTo('parking-1'), $this->anything(), $this->anything())
            ->willReturn([]);

        // Act
        $result = $this->useCase->execute($latitude, $longitude, $radius, $startTime, $endTime);

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_execute_filters_out_parkings_closed_during_time_range(): void
    {
        // Arrange
        $latitude = 48.8566;
        $longitude = 2.3522;
        $radius = 5.0;
        $startTime = new \DateTime('2024-01-15 22:00:00');
        $endTime = new \DateTime('2024-01-15 23:00:00');

        $parking = $this->createMock(Parking::class);
        $parking->method('getId')->willReturn('parking-1');
        $parking->method('getIsAvailable')->willReturn(true);
        $parking->method('hasAvailableSpots')->willReturn(true);
        $parking->method('getAvailableSpots')->willReturn(5);
        $parking->method('getTotalSpots')->willReturn(10);
        $parking->method('getOwnerId')->willReturn('owner-1');
        $parking->method('getTitle')->willReturn('Test Parking');
        $parking->method('getDescription')->willReturn(null);
        $parking->method('getAddress')->willReturn(
            new Address('123 Test St', 'Paris', '75001', 'FR')
        );
        $parking->method('getCoordinates')->willReturn(
            new GPSCoordinates(48.8566, 2.3522)
        );
        $parking->method('getTarifs')->willReturn(
            new TarifCollection([new Tarif('Standard', Price::fromFloat(5.0, 'EUR'), null)])
        );
        $parking->method('getOpeningHours')->willReturn(
            new OpeningHours([TimeSlot::fromHm(1, '08:00', '20:00')])
        );
        $parking->method('getCreatedAt')->willReturn(new \DateTime());
        $parking->method('getUpdatedAt')->willReturn(new \DateTime());
        
        // Override isOpenDuring to return false (parking closed)
        $parking->method('isOpenDuring')
            ->with($this->anything(), $this->anything())
            ->willReturn(false);
        
        $this->parkingRepositoryMock
            ->expects($this->once())
            ->method('findByLocation')
            ->willReturn([$parking]);

        // Act
        $result = $this->useCase->execute($latitude, $longitude, $radius, $startTime, $endTime);

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_execute_validates_latitude_range(): void
    {
        // Arrange
        $invalidLatitude = 100.0; // Invalid (> 90)
        $longitude = 2.3522;

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Latitude must be between -90 and 90');

        // Act
        $this->useCase->execute($invalidLatitude, $longitude);
    }

    public function test_execute_validates_longitude_range(): void
    {
        // Arrange
        $latitude = 48.8566;
        $invalidLongitude = 200.0; // Invalid (> 180)

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Longitude must be between -180 and 180');

        // Act
        $this->useCase->execute($latitude, $invalidLongitude);
    }

    public function test_execute_validates_radius_range(): void
    {
        // Arrange
        $latitude = 48.8566;
        $longitude = 2.3522;
        $invalidRadius = 150.0; // Invalid (> 100)

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Radius must be between 0 and 100 km');

        // Act
        $this->useCase->execute($latitude, $longitude, $invalidRadius);
    }

    private function createMockParking(
        string $id,
        bool $isAvailable,
        int $totalSpots,
        int $availableSpots
    ): Parking|MockObject {
        $parking = $this->createMock(Parking::class);
        
        $parking->method('getId')->willReturn($id);
        $parking->method('getIsAvailable')->willReturn($isAvailable);
        $parking->method('hasAvailableSpots')->willReturn($availableSpots > 0);
        $parking->method('getAvailableSpots')->willReturn($availableSpots);
        $parking->method('isOpenDuring')->willReturn(true);
        $parking->method('getTotalSpots')->willReturn($totalSpots);
        $parking->method('getOwnerId')->willReturn('owner-1');
        $parking->method('getTitle')->willReturn('Test Parking');
        $parking->method('getDescription')->willReturn(null);
        $parking->method('getAddress')->willReturn(
            new Address('123 Test St', 'Paris', '75001', 'FR')
        );
        $parking->method('getCoordinates')->willReturn(
            new GPSCoordinates(48.8566, 2.3522)
        );
        $parking->method('getTarifs')->willReturn(
            new TarifCollection([new Tarif('Standard', Price::fromFloat(5.0, 'EUR'), null)])
        );
        $parking->method('getOpeningHours')->willReturn(
            new OpeningHours([TimeSlot::fromHm(1, '08:00', '20:00')])
        );
        $parking->method('getCreatedAt')->willReturn(new \DateTime());
        $parking->method('getUpdatedAt')->willReturn(new \DateTime());
        
        return $parking;
    }
}

