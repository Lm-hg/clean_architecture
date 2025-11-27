<?php

namespace App\Domain\Entities;

class UserEntity
{
    # attributes

    private string $id;
    private string $role;
    private string $firstName;
    private string $name;
    private string $email;
    private string $password;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;

    public function __construct(string $id, string $role, string $firstName, string $name, string $email, string $password, \DateTime $createdAt, \DateTime $updatedAt)
    {
        // Valider l'ID
        if (!$this->validateId($id)) {
            throw new \InvalidArgumentException("Invalid user ID.");
        }
        
        // Valider le rôle
        if (!$this->validateRole($role)) {
            throw new \InvalidArgumentException("Invalid role: " . $role . ". Allowed roles: admin, user, ownerParking");
        }
        
        // Valider le prénom
        if (!$this->validateFirstName($firstName)) {
            throw new \InvalidArgumentException("Invalid first name. First name must be at least 2 characters long.");
        }
        
        // Valider le nom
        if (!$this->validateName($name)) {
            throw new \InvalidArgumentException("Invalid name. Name must be at least 2 characters long.");
        }
        
        // Valider l'email
        if (!$this->validateEmail($email)) {
            throw new \InvalidArgumentException("Invalid email format: " . $email);
        }
        
        // Valider le hash du mot de passe
        if (!$this->validatePasswordHash($password)) {
            throw new \InvalidArgumentException("Invalid password hash. Hash must be at least 8 characters long.");
        }
        
        $this->id = $id;
        $this->role = $role;
        $this->firstName = $firstName;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    # business rules

    public function validateRole(string $role): bool
    {
        return in_array($role, ['admin', 'user', 'ownerParking']);
    }

    public function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function validatePasswordHash(string $passwordHash): bool
    {
        return strlen($passwordHash) >= 8;
    }


    public function validateFirstName(string $firstName): bool
    {
        return strlen(trim($firstName)) >= 2;
    }

    public function validateName(string $name): bool
    {
        return strlen(trim($name)) >= 2;
    }

    public function validateId(string $id): bool
    {
        return !empty($id);
    }


    # getters and setters

    public function getId(): string
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getName(): string
    {
        return $this->name;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
    
    public function getRole(): string
    {
        return $this->role;
    }
    
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    // Business methods

    public function isOwner(): bool
    {
        return $this->role === 'ownerParking';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRegularUser(): bool
    {
        return $this->role === 'user';
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->name;
    }

    public function updateProfile(string $firstName, string $name): void
    {
        $this->validateFirstName($firstName);
        $this->validateName($name);
        $this->firstName = $firstName;
        $this->name = $name;
        $this->updatedAt = new \DateTime();
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->validatePasswordHash($newPasswordHash);
        $this->password = $newPasswordHash;
        $this->updatedAt = new \DateTime();
    }
}