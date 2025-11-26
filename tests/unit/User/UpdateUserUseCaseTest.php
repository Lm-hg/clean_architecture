<?php

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\User\UpdateUserUseCase;
use App\Application\dtos\user\UpdateUserDto;
use App\Application\dtos\user\UserReponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\UserEntity;
use App\Domain\ObjectValues\User\Password;
use App\Domain\ObjectValues\User\Role;

class UpdateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private UpdateUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Arrange: Créer le mock du repository
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        
        // Arrange: Créer l'instance du use case
        $this->useCase = new UpdateUserUseCase($this->repositoryMock);
    }

    public function test_execute_updates_user_successfully(): void
    {
        // Arrange: Préparer l'ID de l'utilisateur existant
        $userId = '123e4567-e89b-12d3-a456-426614174000';
        
        // Arrange: Créer un utilisateur existant
        $existingUser = new UserEntity(
            $userId,
            'user',
            'John',
            'Doe',
            'test@example.com',
            password_hash('oldpassword123', PASSWORD_DEFAULT),
            '2024-01-01 10:00:00',
            '2024-01-01 10:00:00'
        );
        
        // Arrange: Créer le DTO de mise à jour
        $newPassword = new Password('newpassword123');
        $newRole = new Role('admin');
        $updateUserDto = new UpdateUserDto('Jane', 'Doe', $newPassword, $newRole);
        
        // Arrange: Créer l'utilisateur mis à jour qui sera retourné
        $updatedUser = new UserEntity(
            $userId,
            'admin',
            'Jane',
            'Doe',
            'test@example.com', // L'email reste le même
            password_hash('newpassword123', PASSWORD_DEFAULT),
            '2024-01-01 10:00:00', // La date de création reste la même
            '2024-01-02 10:00:00' // La date de mise à jour change
        );
        
        // Arrange: Configurer les mocks
        $this->repositoryMock
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($existingUser);
        
        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->willReturn($updatedUser);
        
        // Act: Exécuter le use case
        $result = $this->useCase->execute($userId, $updateUserDto);
        
        // Assert: Vérifier le résultat
        $this->assertInstanceOf(UserReponseDto::class, $result);
        $this->assertEquals('Jane', $result->firstName);
        $this->assertEquals('Doe', $result->name);
        $this->assertEquals('admin', $result->role->getRole());
        $this->assertEquals('test@example.com', $result->email->getEmail()); // Email inchangé
    }

    public function test_execute_throws_exception_when_user_not_found(): void
    {
        // Arrange
        $userId = 'non-existent-id';
        $password = new Password('newpassword123');
        $role = new Role('admin');
        $updateUserDto = new UpdateUserDto('Jane', 'Doe', $password, $role);
        
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
        $this->useCase->execute($userId, $updateUserDto);
    }

    public function test_execute_hashes_new_password(): void
    {
        // Arrange
        $userId = '123e4567-e89b-12d3-a456-426614174000';
        $existingUser = new UserEntity(
            $userId,
            'user',
            'John',
            'Doe',
            'test@example.com',
            password_hash('oldpassword123', PASSWORD_DEFAULT),
            '2024-01-01 10:00:00',
            '2024-01-01 10:00:00'
        );
        
        $newPassword = new Password('newplainpassword123');
        $role = new Role('user');
        $updateUserDto = new UpdateUserDto('John', 'Doe', $newPassword, $role);
        
        $capturedUser = null;
        
        // Arrange: Configurer les mocks
        $this->repositoryMock
            ->method('findById')
            ->willReturn($existingUser);
        
        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (UserEntity $user) use (&$capturedUser) {
                $capturedUser = $user;
                return $user;
            });
        
        // Act
        $this->useCase->execute($userId, $updateUserDto);
        
        // Assert: Vérifier que le mot de passe est hashé
        $this->assertNotEquals('newplainpassword123', $capturedUser->getPassword());
        $this->assertTrue(password_verify('newplainpassword123', $capturedUser->getPassword()));
    }
}

