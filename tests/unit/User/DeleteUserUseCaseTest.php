<?php

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\User\DeleteUserUseCase;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;

class DeleteUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private DeleteUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Arrange: Créer le mock du repository
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        
        // Arrange: Créer l'instance du use case
        $this->useCase = new DeleteUserUseCase($this->repositoryMock);
    }

    public function test_execute_deletes_user_successfully(): void
    {
        // Arrange: Préparer l'ID de l'utilisateur
        $userId = '123e4567-e89b-12d3-a456-426614174000';
        
        // Arrange: Créer un utilisateur existant
        $existingUser = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'test@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            new \DateTime('2024-01-01 10:00:00'),
            new \DateTime('2024-01-01 10:00:00')
        );
        
        // Arrange: Configurer les mocks
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($existingUser);
        
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId)
            ->willReturn(true);
        
        // Act: Exécuter le use case
        $result = $this->useCase->execute($userId);
        
        // Assert: Vérifier que la suppression a réussi
        $this->assertTrue($result);
    }

    public function test_execute_throws_exception_when_user_not_found(): void
    {
        // Arrange
        $userId = 'non-existent-id';
        
        // Arrange: Configurer le mock pour retourner null
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);
        
        // Assert: Vérifier qu'une exception est levée
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('User not found.');
        
        // Act
        $this->useCase->execute($userId);
    }

    public function test_execute_returns_false_when_delete_fails(): void
    {
        // Arrange
        $userId = '123e4567-e89b-12d3-a456-426614174000';
        $existingUser = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'test@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            new \DateTime('2024-01-01 10:00:00'),
            new \DateTime('2024-01-01 10:00:00')
        );
        
        // Arrange: Configurer les mocks
        $this->repositoryMock
            ->method('findById')
            ->willReturn($existingUser);
        
        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with($userId)
            ->willReturn(false);
        
        // Act
        $result = $this->useCase->execute($userId);
        
        // Assert: Vérifier que la suppression a échoué
        $this->assertFalse($result);
    }
}

