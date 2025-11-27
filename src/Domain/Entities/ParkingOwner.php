<?php

namespace App\Domain\Entities;

use App\Domain\ValueObjects\Email;

class ParkingOwner
{
    private ?int $id;
    private Email $email;
    private string $passwordHash;
    private string $nom;
    private string $prenom;
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Email $email,
        string $passwordHash,
        string $nom,
        string $prenom,
        \DateTimeImmutable $createdAt,
        ?int $id = null
    ) {
        $this->validateNom($nom);
        $this->validatePrenom($prenom);
        $this->validatePasswordHash($passwordHash);

        $this->id = $id;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->nom = $nom;
        $this->prenom = $prenom;
        $this->createdAt = $createdAt;
    }

    private function validateNom(string $nom): void
    {
        if (empty(trim($nom))) {
            throw new \InvalidArgumentException("Le nom ne peut pas etre vide");
        }
        if (strlen($nom) > 100) {
            throw new \InvalidArgumentException("Le nom ne peut pas depasser 100 caracteres");
        }
    }

    private function validatePrenom(string $prenom): void
    {
        if (empty(trim($prenom))) {
            throw new \InvalidArgumentException("Le prenom ne peut pas etre vide");
        }
        if (strlen($prenom) > 100) {
            throw new \InvalidArgumentException("Le prenom ne peut pas depasser 100 caracteres");
        }
    }

    private function validatePasswordHash(string $passwordHash): void
    {
        if (empty($passwordHash)) {
            throw new \InvalidArgumentException("Le hash du mot de passe ne peut pas etre vide");
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getEmailValue(): string
    {
        return $this->email->getValue();
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function updatePassword(string $newPasswordHash): void
    {
        $this->validatePasswordHash($newPasswordHash);
        $this->passwordHash = $newPasswordHash;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function updateProfile(string $nom, string $prenom): void
    {
        $this->validateNom($nom);
        $this->validatePrenom($prenom);
        $this->nom = $nom;
        $this->prenom = $prenom;
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
