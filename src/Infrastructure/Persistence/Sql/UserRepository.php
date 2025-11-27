<?php

namespace App\Infrastructure\Persistence\Sql;

use PDO;
use App\Domain\Entities\UserEntity;
use App\Domain\Repositories\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?UserEntity
    {
        $stmt = $this->pdo->prepare('
            SELECT id, role, first_name, name, email, password, created_at, updated_at 
            FROM users 
            WHERE id = :id
        ');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByEmail(string $email): ?UserEntity
    {
        $stmt = $this->pdo->prepare('
            SELECT id, role, first_name, name, email, password, created_at, updated_at 
            FROM users 
            WHERE email = :email
        ');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapRowToEntity($row);
    }

    public function findByRole(string $role): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, role, first_name, name, email, password, created_at, updated_at 
            FROM users 
            WHERE role = :role
            ORDER BY created_at DESC
        ');
        $stmt->execute([':role' => $role]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, role, first_name, name, email, password, created_at, updated_at 
            FROM users 
            ORDER BY created_at DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapRowToEntity'], $rows);
    }

    public function create(UserEntity $user): ?UserEntity
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at)
            VALUES (:id, :role, :first_name, :name, :email, :password, :created_at, :updated_at)
        ');

        $success = $stmt->execute([
            ':id' => $user->getId(),
            ':role' => $user->getRole(),
            ':first_name' => $user->getFirstName(),
            ':name' => $user->getName(),
            ':email' => $user->getEmail(),
            ':password' => $user->getPassword(),
            ':created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            ':updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        if (!$success) {
            return null;
        }

        return $user;
    }

    public function update(UserEntity $user): ?UserEntity
    {
        $stmt = $this->pdo->prepare('
            UPDATE users 
            SET role = :role, first_name = :first_name, name = :name, password = :password, updated_at = :updated_at
            WHERE id = :id
        ');

        $success = $stmt->execute([
            ':id' => $user->getId(),
            ':role' => $user->getRole(),
            ':first_name' => $user->getFirstName(),
            ':name' => $user->getName(),
            ':password' => $user->getPassword(),
            ':updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        if (!$success) {
            return null;
        }

        return $user;
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function save(UserEntity $user): ?UserEntity
    {
        // Vérifier si l'utilisateur existe déjà
        $existing = $this->findById($user->getId());
        
        if ($existing !== null) {
            return $this->update($user);
        }
        
        return $this->create($user);
    }

    /**
     * Mappe une ligne de la base de données vers une UserEntity
     */
    private function mapRowToEntity(array $row): UserEntity
    {
        return new UserEntity(
            $row['id'],
            $row['role'],
            $row['first_name'],
            $row['name'],
            $row['email'],
            $row['password'],
            new \DateTime($row['created_at']),
            new \DateTime($row['updated_at'])
        );
    }
}

