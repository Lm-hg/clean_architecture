<?php

namespace App\Presenter\Http\Middleware;

use App\Domain\Services\JwtServiceInterface;

class AuthenticationMiddleware
{
    private JwtServiceInterface $jwtService;

    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Vérifie si la requête contient un token JWT valide
     * 
     * @return array|null Les données de l'utilisateur décodées ou null si non authentifié
     */
    public function authenticate(): ?array
    {
        // Récupérer le token depuis le header Authorization
        // Essayer plusieurs méthodes car cela dépend de la configuration du serveur
        $authHeader = null;
        
        // Méthode 1: $_SERVER['HTTP_AUTHORIZATION'] (fonctionne avec certains serveurs)
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        // Méthode 2: apache_request_headers() si disponible (Apache)
        elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $authHeader = $headers['authorization'];
            }
        }
        // Méthode 3: getallheaders() si disponible (certaines configurations)
        elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $authHeader = $headers['authorization'];
            }
        }
        // Méthode 4: REDIRECT_HTTP_AUTHORIZATION (certaines configurations Apache)
        elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if ($authHeader === null) {
            return null;
        }

        // Extraire le token (format: "Bearer <token>")
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            // Si pas de format Bearer, essayer directement le header
            $token = $authHeader;
        }

        // Valider le token
        $decoded = $this->jwtService->validateToken($token);

        return $decoded;
    }

    /**
     * Vérifie si l'utilisateur est authentifié et retourne une réponse d'erreur si nécessaire
     * 
     * @param callable $callback Fonction à appeler si authentifié
     * @return array Réponse JSON (succès ou erreur)
     */
    public function handle(callable $callback): array
    {
        $user = $this->authenticate();

        if ($user === null) {
            http_response_code(401);
            return [
                'status' => 'error',
                'message' => 'Unauthorized. Valid authentication token required.'
            ];
        }

        // Passer les données de l'utilisateur au callback via $_SERVER
        $_SERVER['AUTHENTICATED_USER'] = $user;

        return $callback($user);
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     * 
     * @param array $allowedRoles Liste des rôles autorisés
     * @return bool True si l'utilisateur a un rôle autorisé
     */
    public function hasRole(array $allowedRoles): bool
    {
        $user = $this->authenticate();

        if ($user === null) {
            return false;
        }

        $userRole = $user['role'] ?? null;
        return in_array($userRole, $allowedRoles);
    }

    /**
     * Middleware pour vérifier les rôles
     * 
     * @param array $allowedRoles Liste des rôles autorisés
     * @param callable $callback Fonction à appeler si autorisé
     * @return array Réponse JSON (succès ou erreur)
     */
    public function authorize(array $allowedRoles, callable $callback): array
    {
        $user = $this->authenticate();

        if ($user === null) {
            http_response_code(401);
            return [
                'status' => 'error',
                'message' => 'Unauthorized. Valid authentication token required.'
            ];
        }

        $userRole = $user['role'] ?? null;

        if (!in_array($userRole, $allowedRoles)) {
            http_response_code(403);
            return [
                'status' => 'error',
                'message' => 'Forbidden. You do not have the required permissions.'
            ];
        }

        // Passer les données de l'utilisateur au callback
        $_SERVER['AUTHENTICATED_USER'] = $user;

        return $callback($user);
    }
}

