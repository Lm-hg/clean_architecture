import { useState } from 'react';
import { Button } from './ui/button';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from './ui/tabs';
import { ArrowLeft, Settings, AlertTriangle, Loader2, Check, User } from 'lucide-react';
import { parkingService, reservationService, stationnementService, subscriptionService } from '../services';
import { useApi } from '../hooks/useApi';
import { useAsyncOperation } from '../hooks/useAsyncOperation';
import { toast } from 'sonner';
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
  const [confirmingReservationId, setConfirmingReservationId] = useState<string | null>(null);
  
  const { loading: confirming, execute: executeConfirm } = useAsyncOperation();

  // API calls pour récupérer les données
  const { data: parkings, loading: parkingsLoading, error: parkingsError } = useApi<Parking[]>(() => parkingService.getMyParkings());
  const { data: reservations, loading: reservationsLoading } = useApi<Reservation[]>(() => reservationService.getParkingReservations(parkingId));
  const { data: stationnements, loading: stationnementsLoading } = useApi<Stationnement[]>(() => stationnementService.getParkingStationnements(parkingId));
  const { data: subscriptionTypes, loading: subscriptionsLoading } = useApi<SubscriptionType[]>(() => subscriptionService.getSubscriptionTypes(parkingId));
  const { data: parkingSubscriptions, loading: parkingSubsLoading } = useApi<Subscription[]>(() => subscriptionService.getParkingSubscriptions(parkingId));

  // États de chargement
  const isLoading = parkingsLoading || reservationsLoading || stationnementsLoading || subscriptionsLoading || parkingSubsLoading;

  // Trouver le parking spécifique
  const parking = parkings?.find((p) => p.id === parkingId);
  const parkingReservations = reservations || [];
  const parkingStationnements = stationnements || [];
  const parkingSubscriptionTypes = subscriptionTypes || [];
  
  // Calculer le chiffre d'affaires mensuel
  const currentMonth = new Date().getMonth();
  const currentYear = new Date().getFullYear();
  const monthlyReservations = parkingReservations.filter((r) => {
    const date = new Date(r.startTime);
    return date.getMonth() === currentMonth && 
           date.getFullYear() === currentYear && 
           r.status === 'completed';
  });
  
  const reservationRevenue = monthlyReservations.reduce((sum, r) => sum + (r.totalPrice || 0), 0);
  
  // Le chiffre d'affaires des abonnements sera de 0 pour l'instant
  // car nous n'avons pas encore d'endpoint pour les achats d'abonnements
  const subscriptionRevenue = 0;
  
  const totalRevenue = (reservationRevenue || 0) + (subscriptionRevenue || 0);

  const handleConfirmReservation = async (reservationId: string) => {
    setConfirmingReservationId(reservationId);
    const result = await executeConfirm(async () => {
      return await reservationService.confirmReservation(reservationId);
    });
    
    if (result) {
      toast.success('Réservation confirmée avec succès!');
      window.location.reload(); // Recharger pour mettre à jour la liste
    }
    setConfirmingReservationId(null);
  };

  // Types d'abonnements actifs disponibles pour ce parking
  const activeSubscriptionTypes = parkingSubscriptionTypes.filter(s => s.isActive);

  // Calculer les stationnements non autorisés
  const unauthorizedStationnements = parkingStationnements.filter(s => s.status === 'violation');

  // Calculer le taux d'occupation
  const occupancyRate = parking && parking.totalSpots ? (((parking.totalSpots || 0) - (parking.availableSpaces || 0)) / parking.totalSpots) * 100 : 0;
  const occupiedSpots = parking ? (parking.totalSpots || 0) - (parking.availableSpaces || 0) : 0;

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
            <p className="text-red-800">
              {parkingsError || 'Le parking demandé n\'existe pas.'}
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
            <p className="text-gray-600">Coordonnées: {parking.latitude?.toFixed(4) ?? '0.0000'}°, {parking.longitude?.toFixed(4) ?? '0.0000'}°</p>
          </div>
        </div>
        <Button onClick={() => setShowEditDialog(true)}>
          <Settings className="size-4 mr-2" />
          Modifier
        </Button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Places disponibles</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900 text-3xl font-bold">{parking.availableSpaces}</div>
            <p className="text-gray-500 mt-1">sur {parking.totalSpots} places</p>
            <div className="mt-3 bg-gray-200 rounded-full h-2">
              <div 
                className="bg-indigo-600 h-2 rounded-full transition-all duration-300" 
                style={{ width: `${occupancyRate}%` }}
              />
            </div>
            <p className="text-gray-500 text-sm mt-1">{(occupancyRate || 0).toFixed(1)}% occupé</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Chiffre d'affaires</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900 text-2xl">{(totalRevenue || 0).toFixed(2)} €</div>
            <p className="text-gray-500 mt-1">
              Réservations: {(reservationRevenue || 0).toFixed(2)} €
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Réservations</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900 text-3xl font-bold">{monthlyReservations.length}</div>
            <p className="text-gray-500 mt-1">Ce mois-ci</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Types d'abonnements</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900 text-3xl font-bold">{activeSubscriptionTypes.length}</div>
            <p className="text-gray-500 mt-1">Actifs</p>
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
                      <div className="text-right flex flex-col items-end gap-2">
                        <p className="text-gray-900">{(reservation.totalPrice || 0).toFixed(2)} €</p>
                        <span
                          className={`inline-block px-3 py-1 rounded text-white ${
                            reservation.status === 'completed'
                              ? 'bg-green-500'
                              : reservation.status === 'confirmed'
                              ? 'bg-indigo-500'
                              : reservation.status === 'active'
                              ? 'bg-blue-500'
                              : reservation.status === 'cancelled'
                              ? 'bg-red-500'
                              : reservation.status === 'pending'
                              ? 'bg-yellow-500'
                              : 'bg-gray-400'
                          }`}
                        >
                          {reservation.status === 'completed' 
                            ? 'Terminé' 
                            : reservation.status === 'confirmed'
                            ? 'Réservé'
                            : reservation.status === 'active' 
                            ? 'En cours' 
                            : reservation.status === 'cancelled'
                            ? 'Annulé'
                            : reservation.status === 'pending'
                            ? 'En attente'
                            : 'Inconnu'}
                        </span>
                        {reservation.status === 'pending' && (
                          <Button
                            size="sm"
                            onClick={() => handleConfirmReservation(reservation.id)}
                            disabled={confirmingReservationId === reservation.id}
                            className="bg-green-600 hover:bg-green-700"
                          >
                            {confirmingReservationId === reservation.id ? (
                              <><Loader2 className="size-4 mr-2 animate-spin" /> Confirmation...</>
                            ) : (
                              <><Check className="size-4 mr-2" /> Confirmer</>
                            )}
                          </Button>
                        )}
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
                          <p className="text-gray-900">{stationnement.vehiclePlate || 'N/A'}</p>
                          {!stationnement.reservationId && (
                            <span className="px-2 py-1 bg-red-100 text-red-700 rounded">Non autorisé</span>
                          )}
                        </div>
                        <p className="text-gray-500">{stationnement.userName || 'Utilisateur'}</p>
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
                        {stationnement.abonnementId && (
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
            {parkingSubscriptionTypes.map((subscriptionType) => (
              <Card key={subscriptionType.id}>
                <CardHeader>
                  <CardTitle>{subscriptionType.name}</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Prix</span>
                      <span className="text-gray-900">{(subscriptionType.price || 0).toFixed(2)} €</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Durée</span>
                      <span className="text-gray-900">{subscriptionType.duration} jours</span>
                    </div>
                    <div className="mt-4">
                      <p className="text-gray-600 mb-2">Avantages:</p>
                      <ul className="list-disc list-inside space-y-1">
                        {(subscriptionType.benefits || []).map((benefit, index) => (
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
              <CardTitle>Utilisateurs abonnés ({parkingSubscriptions?.length || 0})</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {parkingSubscriptions && parkingSubscriptions.length > 0 ? (
                  parkingSubscriptions.map((subscription) => (
                    <div key={subscription.id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                      <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center">
                          <User className="w-5 h-5 text-indigo-600" />
                        </div>
                        <div>
                          <p className="font-medium text-gray-900">{subscription.userName || subscription.userEmail || 'Utilisateur'}</p>
                          <p className="text-sm text-gray-500">{subscription.subscriptionTypeName || 'Abonnement'}</p>
                        </div>
                      </div>
                      <div className="text-right">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                          subscription.status === 'active' 
                            ? 'bg-green-100 text-green-800' 
                            : subscription.status === 'expired'
                            ? 'bg-red-100 text-red-800'
                            : 'bg-gray-100 text-gray-800'
                        }`}>
                          {subscription.status === 'active' ? 'Actif' : subscription.status === 'expired' ? 'Expiré' : subscription.status}
                        </span>
                        <p className="text-xs text-gray-500 mt-1">
                          Jusqu'au {new Date(subscription.endDate).toLocaleDateString('fr-FR')}
                        </p>
                      </div>
                    </div>
                  ))
                ) : (
                  <p className="text-gray-500 text-center py-8">Aucun utilisateur n'a encore acheté d'abonnement pour ce parking</p>
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
