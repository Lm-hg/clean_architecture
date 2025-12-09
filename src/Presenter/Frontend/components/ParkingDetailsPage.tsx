import { useState } from 'react';
import { Button } from './ui/button';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from './ui/tabs';
import { ArrowLeft, Settings, AlertTriangle, Loader2 } from 'lucide-react';
import { parkingService, reservationService, stationnementService, subscriptionService } from '../services';
import { useApi } from '../hooks/useApi';
import type { Parking, Reservation, Stationnement, Subscription, SubscriptionType } from '../types';
import { EditParkingDialog } from './EditParkingDialog';
import { AddSubscriptionDialog } from './AddSubscriptionDialog';
import { AvailabilityChecker } from './AvailabilityChecker';

interface ParkingDetailsPageProps {
  parkingId: string;
  onBack: () => void;
}

export function ParkingDetailsPage({ parkingId, onBack }: ParkingDetailsPageProps) {
  const [showEditDialog, setShowEditDialog] = useState(false);
  const [showAddSubscriptionDialog, setShowAddSubscriptionDialog] = useState(false);

  // API calls pour récupérer les données
  const { data: parkings, loading: parkingsLoading, error: parkingsError } = useApi<Parking[]>(() => parkingService.getMyParkings());
  const { data: reservations, loading: reservationsLoading } = useApi<Reservation[]>(() => reservationService.getParkingReservations(parkingId));
  const { data: stationnements, loading: stationnementsLoading } = useApi<Stationnement[]>(() => stationnementService.getParkingStationnements(parkingId));
  const { data: subscriptionTypes, loading: subscriptionsLoading } = useApi<SubscriptionType[]>(() => subscriptionService.getSubscriptionTypes(parkingId));

  // États de chargement
  const isLoading = parkingsLoading || reservationsLoading || stationnementsLoading || subscriptionsLoading;

  // Trouver le parking spécifique
  const parking = parkings?.find((p) => p.id === parkingId);
  const parkingReservations = reservations || [];
  const parkingStationnements = stationnements || [];
  const parkingSubscriptions = subscriptionTypes || [];
  
  // Calculer le chiffre d'affaires mensuel
  const currentMonth = new Date().getMonth();
  const currentYear = new Date().getFullYear();
  const monthlyReservations = parkingReservations.filter((r) => {
    const date = new Date(r.startTime);
    return date.getMonth() === currentMonth && 
           date.getFullYear() === currentYear && 
           r.status === 'completed';
  });
  
  const reservationRevenue = monthlyReservations.reduce((sum, r) => sum + r.totalPrice, 0);
  
  // Pour les abonnements, nous devrons récupérer les achats d'abonnements actifs
  // Pour l'instant, on simule avec les données disponibles
  const subscriptionRevenue = parkingSubscriptions.reduce((sum, s) => sum + s.price, 0);
  
  const totalRevenue = reservationRevenue + subscriptionRevenue;

  // Calculer les abonnements actifs (simulé pour l'instant)
  const activeSubscriptions = parkingSubscriptions.filter(s => s.isActive);

  // Calculer les stationnements non autorisés
  const unauthorizedStationnements = parkingStationnements.filter(s => s.status === 'violation');

  // État de chargement global
  if (isLoading) {
    return (
      <div className="p-8 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="size-8 animate-spin mx-auto mb-4 text-indigo-600" />
          <p className="text-gray-600">Chargement des détails du parking...</p>
        </div>
      </div>
    );
  }

  // Erreur si parking non trouvé
  if (parkingsError || !parking) {
    return (
      <div className="p-8">
        <div className="bg-red-50 border border-red-200 rounded-md p-4 flex items-center gap-3">
          <AlertTriangle className="size-5 text-red-600 flex-shrink-0" />
          <div>
            <h3 className="text-red-800 font-medium">Parking non trouvé</h3>
            <p className="text-red-700 mt-1">
              {parkingsError ? parkingsError.message : 'Le parking demandé n\'existe pas.'}
            </p>
          </div>
        </div>
        <Button onClick={onBack} className="mt-4">
          <ArrowLeft className="size-4 mr-2" />
          Retour
        </Button>
      </div>
    );
  }

  if (!parking) {
    return (
      <div className="p-8">
        <p>Parking non trouvé</p>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-8">
        <div className="flex items-center gap-4">
          <Button variant="ghost" size="icon" onClick={onBack}>
            <ArrowLeft className="size-5" />
          </Button>
          <div>
            <h1 className="text-gray-900 mb-1">{parking.title}</h1>
            <p className="text-gray-600">{parking.address.street}, {parking.address.city}</p>
          </div>
        </div>
        <Button onClick={() => setShowEditDialog(true)}>
          <Settings className="size-4 mr-2" />
          Modifier
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Chiffre d'affaires</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{totalRevenue.toFixed(2)} €</div>
            <p className="text-gray-500 mt-1">
              Réservations: {reservationRevenue.toFixed(2)} € | Abonnements: {subscriptionRevenue.toFixed(2)} €
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Réservations</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{monthlyReservations.length}</div>
            <p className="text-gray-500 mt-1">Ce mois-ci</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Abonnés actifs</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{activeSubscriptions.length}</div>
            <p className="text-gray-500 mt-1">En cours</p>
          </CardContent>
        </Card>
      </div>

      {unauthorizedStationnements.length > 0 && (
        <Card className="mb-8 border-red-200 bg-red-50">
          <CardHeader>
            <CardTitle className="flex items-center text-red-700">
              <AlertTriangle className="size-5 mr-2" />
              Stationnements non autorisés ({unauthorizedStationnements.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {unauthorizedStationnements.map((stationnement) => (
                <div key={stationnement.id} className="flex items-center justify-between p-3 bg-white rounded-lg">
                  <div>
                    <p className="text-gray-900">{stationnement.vehiclePlate}</p>
                    <p className="text-gray-500">
                      Entrée: {new Date(stationnement.entryTime).toLocaleString('fr-FR')}
                    </p>
                  </div>
                  <Button variant="destructive" size="sm">
                    Signaler
                  </Button>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      <Tabs defaultValue="reservations" className="w-full">
        <TabsList>
          <TabsTrigger value="reservations">Réservations</TabsTrigger>
          <TabsTrigger value="stationnements">Stationnements</TabsTrigger>
          <TabsTrigger value="subscriptions">Abonnements</TabsTrigger>
          <TabsTrigger value="availability">Disponibilité</TabsTrigger>
        </TabsList>

        <TabsContent value="reservations" className="mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Liste des réservations</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {parkingReservations.length === 0 ? (
                  <p className="text-gray-500 text-center py-8">Aucune réservation</p>
                ) : (
                  parkingReservations.map((reservation) => (
                    <div key={reservation.id} className="flex items-center justify-between p-4 border rounded-lg">
                      <div className="flex-1">
                        <p className="text-gray-900">{reservation.userName || 'Utilisateur'}</p>
                        <p className="text-gray-500">{reservation.userEmail}</p>
                        <p className="text-gray-600 mt-1">
                          {new Date(reservation.startTime).toLocaleString('fr-FR')} - {new Date(reservation.endTime).toLocaleString('fr-FR')}
                        </p>
                      </div>
                      <div className="text-right">
                        <p className="text-gray-900">{reservation.totalPrice.toFixed(2)} €</p>
                        <span
                          className={`inline-block px-3 py-1 rounded mt-2 text-white ${
                            reservation.status === 'completed'
                              ? 'bg-green-500'
                              : reservation.status === 'active'
                              ? 'bg-blue-500'
                              : reservation.status === 'cancelled'
                              ? 'bg-red-500'
                              : 'bg-gray-400'
                          }`}
                        >
                          {reservation.status === 'completed' 
                            ? 'Terminé' 
                            : reservation.status === 'active' 
                            ? 'En cours' 
                            : reservation.status === 'cancelled'
                            ? 'Annulé'
                            : 'En attente'}
                        </span>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="stationnements" className="mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Stationnements en cours</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {parkingStationnements.length === 0 ? (
                  <p className="text-gray-500 text-center py-8">Aucun stationnement</p>
                ) : (
                  parkingStationnements.map((stationnement) => (
                    <div key={stationnement.id} className="flex items-center justify-between p-4 border rounded-lg">
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <p className="text-gray-900">{stationnement.vehiclePlate}</p>
                          {!stationnement.isAuthorized && (
                            <span className="px-2 py-1 bg-red-100 text-red-700 rounded">Non autorisé</span>
                          )}
                        </div>
                        <p className="text-gray-500">{stationnement.customerName}</p>
                        <p className="text-gray-600 mt-1">
                          Entrée: {new Date(stationnement.entryTime).toLocaleString('fr-FR')}
                        </p>
                        {stationnement.exitTime && (
                          <p className="text-gray-600">
                            Sortie: {new Date(stationnement.exitTime).toLocaleString('fr-FR')}
                          </p>
                        )}
                      </div>
                      <div>
                        {stationnement.reservationId && (
                          <span className="px-3 py-1 bg-green-100 text-green-700 rounded">Réservation</span>
                        )}
                        {stationnement.subscriptionId && (
                          <span className="px-3 py-1 bg-blue-100 text-blue-700 rounded">Abonnement</span>
                        )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="subscriptions" className="mt-6">
          <div className="flex justify-end mb-4">
            <Button onClick={() => setShowAddSubscriptionDialog(true)}>
              Ajouter un abonnement
            </Button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            {parkingSubscriptions.map((subscription) => (
              <Card key={subscription.id}>
                <CardHeader>
                  <CardTitle>{subscription.name}</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Prix</span>
                      <span className="text-gray-900">{subscription.price} €</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Durée</span>
                      <span className="text-gray-900">{subscription.duration} jours</span>
                    </div>
                    <div className="mt-4">
                      <p className="text-gray-600 mb-2">Avantages:</p>
                      <ul className="list-disc list-inside space-y-1">
                        {subscription.benefits.map((benefit, index) => (
                          <li key={index} className="text-gray-700">{benefit}</li>
                        ))}
                      </ul>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Abonnés actifs</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {activeSubscriptions.length === 0 ? (
                  <p className="text-gray-500 text-center py-8">Aucun abonné</p>
                ) : (
                  activeSubscriptions.map((purchase) => (
                    <div key={purchase.id} className="flex items-center justify-between p-4 border rounded-lg">
                      <div>
                        <p className="text-gray-900">{purchase.customerName}</p>
                        <p className="text-gray-500">{purchase.subscriptionName}</p>
                        <p className="text-gray-600 mt-1">
                          Du {new Date(purchase.startDate).toLocaleDateString('fr-FR')} au {new Date(purchase.endDate).toLocaleDateString('fr-FR')}
                        </p>
                      </div>
                      <span className="px-3 py-1 bg-green-100 text-green-700 rounded">Actif</span>
                    </div>
                  ))
                )}
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="availability" className="mt-6">
          <AvailabilityChecker parking={parking} />
        </TabsContent>
      </Tabs>

      <EditParkingDialog
        open={showEditDialog}
        onOpenChange={setShowEditDialog}
        parking={parking}
      />

      <AddSubscriptionDialog
        open={showAddSubscriptionDialog}
        onOpenChange={setShowAddSubscriptionDialog}
        parkingId={parkingId}
      />
    </div>
  );
}
