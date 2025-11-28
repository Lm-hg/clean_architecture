<?php

namespace Tests\Integration\User;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\Sql\UserRepository;
use App\Domain\Entities\User;

class UserRepositoryIntegrationTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Connexion à la base de données
        $this->pdo = require __DIR__ . '/../../../../config/database.php';
        
        // Nettoyer la table avant chaque test (seulement si elle existe)
        try {
            $this->pdo->exec('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Si la table n'existe pas, on continue quand même
            // La migration doit être exécutée avant les tests
        }
        
        // Créer le repository
        $this->repository = new UserRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        // Nettoyer après chaque test (seulement si la table existe)
        try {
            $this->pdo->exec('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        } catch (\PDOException $e) {
            // Ignorer si la table n'existe pas
        }
        parent::tearDown();
    }

    public function test_create_and_find_user(): void
    {
        // Arrange
        $userId = $this->generateUuid();
        $user = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'john@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        
        // Act: Créer l'utilisateur
        $savedUser = $this->repository->create($user);
        
        // Assert
        $this->assertNotNull($savedUser);
        $this->assertEquals($userId, $savedUser->getId());
        
        // Act: Récupérer l'utilisateur
        $foundUser = $this->repository->findById($userId);
        
        // Assert
        $this->assertNotNull($foundUser);
        $this->assertEquals('John', $foundUser->getFirstName());
        $this->assertEquals('Doe', $foundUser->getName());
        $this->assertEquals('john@example.com', $foundUser->getEmail());
        $this->assertEquals('user', $foundUser->getRole());
    }

    public function test_find_by_email(): void
    {
        // Arrange
        $userId = $this->generateUuid();
        $user = new User(
            $userId,
            'user',
            'Jane',
            'Doe',
            'jane@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $this->repository->create($user);
        
        // Act
        $foundUser = $this->repository->findByEmail('jane@example.com');
        
        // Assert
        $this->assertNotNull($foundUser);
        $this->assertEquals($userId, $foundUser->getId());
        $this->assertEquals('jane@example.com', $foundUser->getEmail());
    }

    public function test_update_user(): void
    {
        // Arrange
        $userId = $this->generateUuid();
        $user = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'john@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $this->repository->create($user);
        
        // Act: Mettre à jour
        $updatedUser = new User(
            $userId,
            'admin',
            'John',
            'Updated',
            'john@example.com', // Email ne change pas
            password_hash('newpassword123', PASSWORD_DEFAULT),
            $user->getCreatedAt(),
            date('Y-m-d H:i:s')
        );
        $saved = $this->repository->update($updatedUser);
        
        // Assert
        $this->assertNotNull($saved);
        
        // Vérifier en base
        $found = $this->repository->findById($userId);
        $this->assertEquals('John', $found->getFirstName());
        $this->assertEquals('Updated', $found->getName());
        $this->assertEquals('admin', $found->getRole());
        $this->assertTrue(password_verify('newpassword123', $found->getPassword()));
    }

    public function test_delete_user(): void
    {
        // Arrange
        $userId = $this->generateUuid();
        $user = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'john@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $this->repository->create($user);
        
        // Act
        $deleted = $this->repository->delete($userId);
        
        // Assert
        $this->assertTrue($deleted);
        
        // Vérifier que l'utilisateur n'existe plus
        $found = $this->repository->findById($userId);
        $this->assertNull($found);
    }

    public function test_find_all_users(): void
    {
        // Arrange: Créer plusieurs utilisateurs
        $user1 = new User(
            $this->generateUuid(),
            'user',
            'User',
            'One',
            'user1@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $user2 = new User(
            $this->generateUuid(),
            'admin',
            'User',
            'Two',
            'user2@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $this->repository->create($user1);
        $this->repository->create($user2);
        
        // Act
        $users = $this->repository->findAll();
        
        // Assert
        $this->assertCount(2, $users);
        $this->assertEquals('user2@example.com', $users[0]->getEmail()); // Le plus récent en premier
        $this->assertEquals('user1@example.com', $users[1]->getEmail());
    }

    public function test_find_by_role(): void
    {
        // Arrange
        $admin1 = new User(
            $this->generateUuid(),
            'admin',
            'Admin',
            'One',
            'admin1@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $admin2 = new User(
            $this->generateUuid(),
            'admin',
            'Admin',
            'Two',
            'admin2@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $user = new User(
            $this->generateUuid(),
            'user',
            'User',
            'One',
            'user1@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $this->repository->create($admin1);
        $this->repository->create($admin2);
        $this->repository->create($user);
        
        // Act
        $admins = $this->repository->findByRole('admin');
        
        // Assert
        $this->assertCount(2, $admins);
        foreach ($admins as $admin) {
            $this->assertEquals('admin', $admin->getRole());
        }
    }

    public function test_save_creates_new_user(): void
    {
        // Arrange
        $userId = $this->generateUuid();
        $user = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'john@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        
        // Act
        $saved = $this->repository->save($user);
        
        // Assert
        $this->assertNotNull($saved);
        $found = $this->repository->findById($userId);
        $this->assertNotNull($found);
    }

    public function test_save_updates_existing_user(): void
    {
        // Arrange
        $userId = $this->generateUuid();
        $user = new User(
            $userId,
            'user',
            'John',
            'Doe',
            'john@example.com',
            password_hash('password123', PASSWORD_DEFAULT),
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        );
        $this->repository->create($user);
        
        // Act: Mettre à jour avec save
        $updatedUser = new User(
            $userId,
            'admin',
            'John',
            'Updated',
            'john@example.com',
            password_hash('newpassword123', PASSWORD_DEFAULT),
            $user->getCreatedAt(),
            date('Y-m-d H:i:s')
        );
        $saved = $this->repository->save($updatedUser);
        
        // Assert
        $this->assertNotNull($saved);
        $found = $this->repository->findById($userId);
        $this->assertEquals('admin', $found->getRole());
        $this->assertEquals('John', $found->getFirstName());
        $this->assertEquals('Updated', $found->getName());
    }

    /**
     * Helper: Génère un UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}

