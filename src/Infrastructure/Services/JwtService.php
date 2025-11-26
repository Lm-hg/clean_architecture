<?php

namespace App\Infrastructure\Services;

use App\Domain\Services\JwtServiceInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JwtService implements JwtServiceInterface
{
    private string $secretKey;
    private string $algorithm;
    private int $expirationTime; // en secondes

    public function __construct(
        string $secretKey,
        string $algorithm = 'HS256',
        int $expirationTime = 3600 // 1 heure par défaut
    ) {
        $this->secretKey = $secretKey;
        $this->algorithm = $algorithm;
        $this->expirationTime = $expirationTime;
    }

    public function generateToken(string $userId, string $email, string $role): string
    {
        $issuedAt = time();
        $expirationTime = $issuedAt + $this->expirationTime;

        $payload = [
            'iat' => $issuedAt,           // Issued at
            'exp' => $expirationTime,     // Expiration time
            'user_id' => $userId,
            'email' => $email,
            'role' => $role
        ];

        return JWT::encode($payload, $this->secretKey, $this->algorithm);
    }

    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            // Token expiré
            return null;
        } catch (SignatureInvalidException $e) {
            // Signature invalide
            return null;
        } catch (\Exception $e) {
            // Autre erreur
            return null;
        }
    }

    public function getUserIdFromToken(string $token): ?string
    {
        $decoded = $this->validateToken($token);
        
        if ($decoded === null) {
            return null;
        }

        return $decoded['user_id'] ?? null;
    }
}

