<?php

namespace App\Presenter\Http\Controllers\Api;

use App\Application\UseCases\ParkingOwner\RegisterParkingOwnerUseCase;
use App\Application\UseCases\ParkingOwner\LoginParkingOwnerUseCase;
use App\Application\dtos\parkingOwner\RegisterParkingOwnerDto;
use App\Application\dtos\parkingOwner\LoginParkingOwnerDto;
use App\Domain\ValueObjects\User\Email;
use App\Domain\Exceptions\DuplicateEmailException;
use App\Domain\Exceptions\InvalidCredentialsException;

class ParkingOwnerController
{
    private RegisterParkingOwnerUseCase $registerUseCase;
    private LoginParkingOwnerUseCase $loginUseCase;

    public function __construct(
        RegisterParkingOwnerUseCase $registerUseCase,
        LoginParkingOwnerUseCase $loginUseCase
    ) {
        $this->registerUseCase = $registerUseCase;
        $this->loginUseCase = $loginUseCase;
    }

    /**
     * POST /api/parking-owners/register
     * Inscription d'un nouveau propriétaire de parking
     */
    public function register(array $requestData): array
    {
        try {
            // Validation des données requises
            if (!isset($requestData['email']) || !isset($requestData['password']) ||
                !isset($requestData['firstName']) || !isset($requestData['lastName'])) {
                return [
                    'success' => false,
                    'error' => 'Données manquantes: email, password, firstName et lastName sont requis',
                    'status' => 400
                ];
            }

            // Validation de l'email
            if (!filter_var($requestData['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => 'Format d\'email invalide',
                    'status' => 400
                ];
            }

            // Validation du mot de passe
            if (strlen($requestData['password']) < 8) {
                return [
                    'success' => false,
                    'error' => 'Le mot de passe doit contenir au moins 8 caractères',
                    'status' => 400
                ];
            }

            // Créer le DTO
            $registerDto = new RegisterParkingOwnerDto(
                firstName: trim($requestData['firstName']),
                lastName: trim($requestData['lastName']),
                email: new Email($requestData['email']),
                password: $requestData['password']
            );

            // Exécuter le use case
            $response = $this->registerUseCase->execute($registerDto);

            return [
                'success' => true,
                'data' => [
                    'id' => $response->id,
                    'firstName' => $response->firstName,
                    'lastName' => $response->lastName,
                    'email' => $response->email->getEmail(),
                    'token' => $response->token,
                    'createdAt' => $response->createdAt->format('Y-m-d H:i:s')
                ],
                'message' => 'Propriétaire de parking inscrit avec succès',
                'status' => 201
            ];

        } catch (DuplicateEmailException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 409
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'inscription',
                'status' => 500
            ];
        }
    }

    /**
     * POST /api/parking-owners/login
     * Connexion d'un propriétaire de parking
     */
    public function login(array $requestData): array
    {
        try {
            // Validation des données requises
            if (!isset($requestData['email']) || !isset($requestData['password'])) {
                return [
                    'success' => false,
                    'error' => 'Email et mot de passe requis',
                    'status' => 400
                ];
            }

            // Validation de l'email
            if (!filter_var($requestData['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'error' => 'Format d\'email invalide',
                    'status' => 400
                ];
            }

            // Créer le DTO
            $loginDto = new LoginParkingOwnerDto(
                email: new Email($requestData['email']),
                password: $requestData['password']
            );

            // Exécuter le use case
            $response = $this->loginUseCase->execute($loginDto);

            return [
                'success' => true,
                'data' => [
                    'id' => $response->id,
                    'firstName' => $response->firstName,
                    'lastName' => $response->lastName,
                    'email' => $response->email->getEmail(),
                    'token' => $response->token
                ],
                'message' => 'Connexion réussie',
                'status' => 200
            ];

        } catch (InvalidCredentialsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 401
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur lors de la connexion',
                'status' => 500
            ];
        }
    }

    /**
     * GET /api/parking-owners/profile
     * Récupérer le profil du propriétaire connecté
     */
    public function getProfile(string $ownerId): array
    {
        // Cette méthode nécessiterait un GetParkingOwnerProfileUseCase
        // Pour l'instant, retourner une réponse basique
        return [
            'success' => true,
            'message' => 'Endpoint profile en développement',
            'status' => 200
        ];
    }
}