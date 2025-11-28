<?php

namespace Tests\Unit\User;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Application\UseCases\User\CreateUserUseCase;
use App\Application\dtos\user\CreateUserDto;
use App\Application\dtos\user\UserReponseDto;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Domain\ValueObjects\User\Email;
use App\Domain\ValueObjects\User\Password;
use App\Domain\ValueObjects\User\Role;

class CreateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $repositoryMock;
    private CreateUserUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Arrange: Créer le mock du repository
        $this->repositoryMock = $this->createMock(UserRepositoryInterface::class);
        
        // Arrange: Créer l'instance du use case avec le mock
        $this->useCase = new CreateUserUseCase($this->repositoryMock);
    }

    public function test_execute_creates_user_successfully(): void
    {
        // Arrange: Préparer les données de test
        $email = new Email('test@example.com');
        $password = new Password('password123');
        $role = new Role('user');
        $createUserDto = new CreateUserDto('John', 'Doe', $email, $password, $role);
        
        // Arrange: Configurer le mock pour vérifier que l'email n'existe pas
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn(null);
        
        // Arrange: Configurer le mock pour retourner l'utilisateur qui lui est passé
        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(function (User $user) {
                return $user; // Retourner l'utilisateur tel qu'il a été créé
            });
        
        // Act: Exécuter le use case
        $result = $this->useCase->execute($createUserDto);
        
        // Assert: Vérifier que le résultat est une instance de UserReponseDto
        $this->assertInstanceOf(UserReponseDto::class, $result);
        
        // Assert: Vérifier les propriétés du DTO retourné
        $this->assertEquals('John', $result->firstName);
        $this->assertEquals('Doe', $result->name);
        $this->assertEquals('test@example.com', $result->email->getEmail());
        $this->assertEquals('user', $result->role->getRole());
        
        // Assert: Vérifier que l'ID a été généré (format UUID)
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $result->id->getId()
        );
    }

    public function test_execute_hashes_password_before_storing(): void
    {
        // Arrange
        $email = new Email('test@example.com');
        $password = new Password('plainpassword123');
        $role = new Role('user');
        $createUserDto = new CreateUserDto('John', 'Doe', $email, $password, $role);
        
        $capturedUser = null;
        
        // Arrange: Configurer le mock pour vérifier que l'email n'existe pas
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn(null);
        
        // Arrange: Capturer l'utilisateur passé au repository pour vérifier le hash
        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;
                return $user;
            });
        
        // Act
        $this->useCase->execute($createUserDto);
        
        // Assert: Vérifier que l'utilisateur a été capturé
        $this->assertNotNull($capturedUser);
        $this->assertInstanceOf(User::class, $capturedUser);
        
        // Type narrowing: garantir que $capturedUser n'est pas null pour le linter
        if ($capturedUser === null) {
            $this->fail('Captured user should not be null');
        }
        
        // Assert: Vérifier que le mot de passe stocké est hashé (ne correspond pas au mot de passe en clair)
        $this->assertNotEquals('plainpassword123', $capturedUser->getPassword());
        
        // Assert: Vérifier que le hash est valide (peut être vérifié avec password_verify)
        $this->assertTrue(password_verify('plainpassword123', $capturedUser->getPassword()));
    }

    public function test_execute_throws_exception_when_email_already_exists(): void
    {
        // Arrange
        $email = new Email('existing@example.com');
        $password = new Password('password123');
        $role = new Role('user');
        $createUserDto = new CreateUserDto('John', 'Doe', $email, $password, $role);
        
        // Arrange: Créer un utilisateur existant avec le même email
        $existingUser = new User(
            '123e4567-e89b-12d3-a456-426614174000',
            'user',
            'Existing',
            'User',
            'existing@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            new \DateTime('2024-01-01 10:00:00'),
            new \DateTime('2024-01-01 10:00:00')
        );
        
        // Arrange: Configurer le mock pour retourner l'utilisateur existant
        $this->repositoryMock
            ->expects($this->once())
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);
        
        // Assert: Vérifier qu'une exception est levée
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Email already exists: existing@example.com");
        
        // Act
        $this->useCase->execute($createUserDto);
    }
}

