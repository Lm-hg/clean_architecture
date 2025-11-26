<?php

namespace App\Domain\Services;

interface JwtServiceInterface
{
    /**
     * Génère un token JWT pour un utilisateur
     * 
     * @param string $userId L'ID de l'utilisateur
     * @param string $email L'email de l'utilisateur
     * @param string $role Le rôle de l'utilisateur
     * @return string Le token JWT
     */
    public function generateToken(string $userId, string $email, string $role): string;

    /**
     * Valide un token JWT et retourne les données décodées
     * 
     * @param string $token Le token JWT à valider
     * @return array|null Les données décodées du token ou null si invalide
     */
    public function validateToken(string $token): ?array;

    /**
     * Récupère l'ID de l'utilisateur depuis un token
     * 
     * @param string $token Le token JWT
     * @return string|null L'ID de l'utilisateur ou null si invalide
     */
    public function getUserIdFromToken(string $token): ?string;
}

