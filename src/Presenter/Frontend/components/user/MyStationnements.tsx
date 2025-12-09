import { Card, CardContent } from '../ui/card';
import { Badge } from '../ui/badge';
import { stationnementService } from '../../services';
import { useApi } from '../../hooks/useApi';
import type { Stationnement } from '../../types';
import { Car, Calendar, MapPin, AlertTriangle, Loader2 } from 'lucide-react';

export function MyStationnements() {
  const { 
    data: userStationnements, 
    loading, 
    error 
  } = useApi<Stationnement[]>(() => stationnementService.getUserStationnements());
  
  const stationnements = userStationnements || [];

  const activeStationnements = stationnements.filter((s) => !s.exitTime);
  const completedStationnements = stationnements.filter((s) => s.exitTime);

  return (
    <div className="p-8">
      <div className="mb-8">
        <h1 className="text-gray-900 mb-2">Mes stationnements</h1>
        <p className="text-gray-600">Historique de vos stationnements</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-600 mb-1">Stationnements actifs</p>
                <p className="text-gray-900">{activeStationnements.length}</p>
              </div>
              <Car className="size-8 text-blue-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-600 mb-1">Total des stationnements</p>
                <p className="text-gray-900">{userStationnements.length}</p>
              </div>
              <Calendar className="size-8 text-indigo-600" />
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-4">
        {userStationnements.length === 0 ? (
          <Card>
            <CardContent className="p-12 text-center">
              <Car className="size-12 mx-auto mb-4 text-gray-400" />
              <p className="text-gray-500">Vous n'avez aucun stationnement enregistré</p>
            </CardContent>
          </Card>
        ) : (
          userStationnements.map((stationnement) => (
            <Card key={stationnement.id}>
              <CardContent className="p-6">
                <div className="flex items-start justify-between mb-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-2">
                      <h3 className="text-gray-900">{stationnement.parkingName}</h3>
                      {!stationnement.exitTime && (
                        <Badge className="bg-blue-500">En cours</Badge>
                      )}
                      {!stationnement.isAuthorized && (
                        <Badge className="bg-red-500">Non autorisé</Badge>
                      )}
                    </div>
                    <div className="flex items-center text-gray-600 mb-1">
                      <MapPin className="size-4 mr-2" />
                      <span>Plaque: {stationnement.vehiclePlate}</span>
                    </div>
                  </div>
                  {stationnement.totalPrice && (
                    <div className="text-right">
                      <p className="text-gray-900">{stationnement.totalPrice.toFixed(2)} €</p>
                      {stationnement.penalty && (
                        <p className="text-red-600">
                          dont {stationnement.penalty.toFixed(2)} € de pénalité
                        </p>
                      )}
                    </div>
                  )}
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="flex items-start text-gray-600">
                    <Calendar className="size-4 mr-2 mt-1 flex-shrink-0" />
                    <div>
                      <p className="text-gray-500">Entrée</p>
                      <p className="text-gray-900">
                        {new Date(stationnement.entryTime).toLocaleString('fr-FR')}
                      </p>
                    </div>
                  </div>

                  {stationnement.exitTime && (
                    <div className="flex items-start text-gray-600">
                      <Calendar className="size-4 mr-2 mt-1 flex-shrink-0" />
                      <div>
                        <p className="text-gray-500">Sortie</p>
                        <p className="text-gray-900">
                          {new Date(stationnement.exitTime).toLocaleString('fr-FR')}
                        </p>
                      </div>
                    </div>
                  )}
                </div>

                {stationnement.reservationId && (
                  <div className="mt-4 bg-green-50 rounded-lg p-3">
                    <p className="text-green-900">
                      ✓ Associé à la réservation #{stationnement.reservationId}
                    </p>
                  </div>
                )}

                {stationnement.subscriptionId && (
                  <div className="mt-4 bg-blue-50 rounded-lg p-3">
                    <p className="text-blue-900">
                      ✓ Associé à l'abonnement #{stationnement.subscriptionId}
                    </p>
                  </div>
                )}

                {!stationnement.isAuthorized && (
                  <div className="mt-4 bg-red-50 rounded-lg p-3">
                    <div className="flex items-center text-red-900">
                      <AlertTriangle className="size-4 mr-2" />
                      <p>Stationnement hors créneau autorisé</p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>
          ))
        )}
      </div>
    </div>
  );
}
