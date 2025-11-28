<?php

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\User\ListUsersUseCase;
use App\Application\dtos\user\UserReponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;

class ListUsersUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private ListUsersUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Arrange: Créer le mock du repository
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        
        // Arrange: Créer l'instance du use case
        $this->useCase = new ListUsersUseCase($this->repositoryMock);
    }

    public function test_execute_returns_list_of_users(): void
    {
        // Arrange: Créer une liste d'utilisateurs
        $users = [
            new User(
                '123e4567-e89b-12d3-a456-426614174000',
                'user',
                'John',
                'Doe',
                'john@example.com',
                password_hash('password1', PASSWORD_DEFAULT),
                new \DateTime('2024-01-01 10:00:00'),
                new \DateTime('2024-01-01 10:00:00')
            ),
            new User(
                '223e4567-e89b-12d3-a456-426614174001',
                'admin',
                'Jane',
                'Doe',
                'jane@example.com',
                password_hash('password2', PASSWORD_DEFAULT),
                new \DateTime('2024-01-02 10:00:00'),
                new \DateTime('2024-01-02 10:00:00')
            ),
        ];
        
        // Arrange: Configurer le mock pour retourner la liste
        $this->repositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($users);
        
        // Act: Exécuter le use case
        $result = $this->useCase->execute();
        
        // Assert: Vérifier que le résultat est un tableau
        $this->assertIsArray($result);
        
        // Assert: Vérifier le nombre d'utilisateurs
        $this->assertCount(2, $result);
        
        // Assert: Vérifier que chaque élément est une instance de UserReponseDto
        foreach ($result as $userDto) {
            $this->assertInstanceOf(UserReponseDto::class, $userDto);
        }
        
        // Assert: Vérifier les données du premier utilisateur
        $this->assertEquals('John', $result[0]->firstName);
        $this->assertEquals('Doe', $result[0]->name);
        $this->assertEquals('john@example.com', $result[0]->email->getEmail());
        $this->assertEquals('user', $result[0]->role->getRole());
        
        // Assert: Vérifier les données du second utilisateur
        $this->assertEquals('Jane', $result[1]->firstName);
        $this->assertEquals('Doe', $result[1]->name);
        $this->assertEquals('jane@example.com', $result[1]->email->getEmail());
        $this->assertEquals('admin', $result[1]->role->getRole());
    }

    public function test_execute_returns_empty_array_when_no_users(): void
    {
        // Arrange: Configurer le mock pour retourner un tableau vide
        $this->repositoryMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);
        
        // Act
        $result = $this->useCase->execute();
        
        // Assert: Vérifier que le résultat est un tableau vide
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}

