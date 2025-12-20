<?php
declare(strict_types=1);

namespace App\Application\UseCases\Stationnement;

use App\Domain\Entities\Stationnement;
use App\Domain\Repositories\StationnementRepositoryInterface;
use App\Domain\Exceptions\EntityNotFoundException;

class GetStationnementUseCase
{
    private StationnementRepositoryInterface $stationnementRepository;

    public function __construct(StationnementRepositoryInterface $stationnementRepository)
    {
        $this->stationnementRepository = $stationnementRepository;
    }

    /**
     * Récupère un stationnement par son ID
     * 
     * @param string $stationnementId
     * @param string|null $userId Optionnel: vérifier que le stationnement appartient à l'utilisateur
     * @return Stationnement
     * @throws EntityNotFoundException Si le stationnement n'existe pas
     * @throws \DomainException Si le stationnement n'appartient pas à l'utilisateur
     */
    public function execute(string $stationnementId, ?string $userId = null): Stationnement
    {
        $stationnement = $this->stationnementRepository->findById($stationnementId);
        
        if ($stationnement === null) {
            throw new EntityNotFoundException("Stationnement non trouvé");
        }

        // Si un userId est fourni, vérifier que le stationnement lui appartient
        if ($userId !== null && !$stationnement->belongsToUser($userId)) {
            throw new \DomainException("Ce stationnement n'appartient pas à cet utilisateur");
        }

        return $stationnement;
    }
}

