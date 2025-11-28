<?php

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\User\GetUserUseCase;
use App\Application\dtos\user\UserReponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;

class GetUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private GetUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Arrange: Créer le mock du repository
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        
        // Arrange: Créer l'instance du use case
        $this->useCase = new GetUserUseCase($this->repositoryMock);
    }

    public function test_execute_returns_user_when_found(): void
    {
        // Arrange: Préparer l'ID de l'utilisateur
        $userId = '123e4567-e89b-12d3-a456-426614174000';
        
        // Arrange: Créer un utilisateur mocké qui sera retourné
        $User = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'test@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            new \DateTime('2024-01-01 10:00:00'),
            new \DateTime('2024-01-01 10:00:00')
        );
        
        // Arrange: Configurer le mock pour retourner l'utilisateur
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($User);
        
        // Act: Exécuter le use case
        $result = $this->useCase->execute($userId);
        
        // Assert: Vérifier que le résultat n'est pas null
        $this->assertNotNull($result);
        
        // Assert: Vérifier que c'est une instance de UserReponseDto
        $this->assertInstanceOf(UserReponseDto::class, $result);
        
        // Assert: Vérifier les données de l'utilisateur
        $this->assertEquals('John', $result->firstName);
        $this->assertEquals('Doe', $result->name);
        $this->assertEquals('test@example.com', $result->email->getEmail());
        $this->assertEquals('user', $result->role->getRole());
        $this->assertEquals($userId, $result->id->getId());
    }

    public function test_execute_returns_null_when_user_not_found(): void
    {
        // Arrange
        $userId = 'non-existent-id';
        
        // Arrange: Configurer le mock pour retourner null
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);
        
        // Act
        $result = $this->useCase->execute($userId);
        
        // Assert: Vérifier que le résultat est null
        $this->assertNull($result);
    }
}

