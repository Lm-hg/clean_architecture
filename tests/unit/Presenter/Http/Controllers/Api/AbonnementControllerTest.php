<?php

namespace Tests\Unit\Presenter\Http\Controllers\Api;

use App\Presenter\Http\Controllers\Api\AbonnementController;
use App\Application\dtos\Abonnement\AbonnementResponseDto;
use App\Domain\ValueObjects\Pricing\Price;
use PHPUnit\Framework\TestCase;

class AbonnementControllerTest extends TestCase
{
    public function testCreateReturnsSuccess()
    {
        $dto = new AbonnementResponseDto('id-1', 'user-1', 'parking-1', 'total', '2025-01-01T00:00:00+00:00', '2025-12-31T00:00:00+00:00', Price::fromFloat(100.0));

        $createUseCase = $this->createMock(\App\Application\UseCases\Abonnement\CreateAbonnementUseCase::class);
        $createUseCase->method('execute')->willReturn($dto);

        $getUseCase = $this->createMock(\App\Application\UseCases\Abonnement\GetAbonnementUseCase::class);
        $listUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ListAbonnementsForParkingUseCase::class);
        $subscribeUseCase = $this->createMock(\App\Application\UseCases\Abonnement\SubscribeToAbonnementUseCase::class);
        $validateUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ValidateAbonnementUseCase::class);

        $controller = new AbonnementController($createUseCase, $getUseCase, $listUseCase, $subscribeUseCase, $validateUseCase);

        $request = [
            'userId' => 'user-1',
            'parkingId' => 'parking-1',
            'type' => 'total',
            'startDate' => '2025-01-01',
            'endDate' => '2025-12-31',
            'monthlyPrice' => 100.0,
            'timeSlots' => []
        ];

        $res = $controller->create($request);
        $this->assertEquals('success', $res['status']);
        $this->assertEquals('id-1', $res['data']['id']);
    }

    public function testShowNotFoundReturns404()
    {
        $createUseCase = $this->createMock(\App\Application\UseCases\Abonnement\CreateAbonnementUseCase::class);
        $getUseCase = $this->createMock(\App\Application\UseCases\Abonnement\GetAbonnementUseCase::class);
        $getUseCase->method('execute')->will($this->throwException(new \App\Domain\Exceptions\NotFoundException('not found')));
        $listUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ListAbonnementsForParkingUseCase::class);
        $subscribeUseCase = $this->createMock(\App\Application\UseCases\Abonnement\SubscribeToAbonnementUseCase::class);
        $validateUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ValidateAbonnementUseCase::class);

        $controller = new AbonnementController($createUseCase, $getUseCase, $listUseCase, $subscribeUseCase, $validateUseCase);

        $res = $controller->show('missing');
        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('not found', $res['message']);
    }

    public function testIndexForParkingReturnsList()
    {
        $respDto = new AbonnementResponseDto('id-2', 'user-2', 'parking-2', 'total', '2025-01-01T00:00:00+00:00', '2025-12-31T00:00:00+00:00', Price::fromFloat(50.0));

        $createUseCase = $this->createMock(\App\Application\UseCases\Abonnement\CreateAbonnementUseCase::class);
        $getUseCase = $this->createMock(\App\Application\UseCases\Abonnement\GetAbonnementUseCase::class);
        $listUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ListAbonnementsForParkingUseCase::class);
        $listUseCase->method('execute')->willReturn([$respDto]);
        $subscribeUseCase = $this->createMock(\App\Application\UseCases\Abonnement\SubscribeToAbonnementUseCase::class);
        $validateUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ValidateAbonnementUseCase::class);

        $controller = new AbonnementController($createUseCase, $getUseCase, $listUseCase, $subscribeUseCase, $validateUseCase);

        $res = $controller->indexForParking('parking-2');
        $this->assertEquals('success', $res['status']);
        $this->assertCount(1, $res['data']);
    }

    public function testSubscribeNotFoundReturns404()
    {
        $createUseCase = $this->createMock(\App\Application\UseCases\Abonnement\CreateAbonnementUseCase::class);
        $getUseCase = $this->createMock(\App\Application\UseCases\Abonnement\GetAbonnementUseCase::class);
        $listUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ListAbonnementsForParkingUseCase::class);
        $subscribeUseCase = $this->createMock(\App\Application\UseCases\Abonnement\SubscribeToAbonnementUseCase::class);
        $subscribeUseCase->method('execute')->willReturn(null);
        $validateUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ValidateAbonnementUseCase::class);

        $controller = new AbonnementController($createUseCase, $getUseCase, $listUseCase, $subscribeUseCase, $validateUseCase);

        $res = $controller->subscribe('missing');
        $this->assertEquals('error', $res['status']);
    }

    public function testValidateReturnsBoolean()
    {
        $createUseCase = $this->createMock(\App\Application\UseCases\Abonnement\CreateAbonnementUseCase::class);
        $getUseCase = $this->createMock(\App\Application\UseCases\Abonnement\GetAbonnementUseCase::class);
        $listUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ListAbonnementsForParkingUseCase::class);
        $subscribeUseCase = $this->createMock(\App\Application\UseCases\Abonnement\SubscribeToAbonnementUseCase::class);
        $validateUseCase = $this->createMock(\App\Application\UseCases\Abonnement\ValidateAbonnementUseCase::class);
        $validateUseCase->method('execute')->willReturn(true);

        $controller = new AbonnementController($createUseCase, $getUseCase, $listUseCase, $subscribeUseCase, $validateUseCase);

        $res = $controller->validate('id-1', ['dateTime' => '2025-11-28T12:00:00+00:00']);
        $this->assertEquals('success', $res['status']);
        $this->assertTrue($res['data']['valid']);
    }
}
