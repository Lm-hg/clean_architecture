import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import { Euro, ParkingSquare, Calendar, TrendingUp, Loader2, AlertCircle, Building } from 'lucide-react';
import { parkingService, reservationService, subscriptionService } from '../services';
import { useApi } from '../hooks/useApi';
import type { Parking, Reservation, Subscription } from '../types';

export function OverviewPage() {
  // Récupération des données via API
  const { data: parkings, loading: parkingsLoading, error: parkingsError } = useApi<Parking[]>(() => parkingService.getMyParkings());
  const { data: reservations, loading: reservationsLoading } = useApi<Reservation[]>(() => reservationService.getMyReservations());
  const { data: subscriptions, loading: subscriptionsLoading } = useApi<Subscription[]>(() => subscriptionService.getUserSubscriptions());

  // États de chargement
  const isLoading = parkingsLoading || reservationsLoading || subscriptionsLoading;

  if (isLoading) {
    return (
      <div className="p-8 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="size-8 animate-spin mx-auto mb-4 text-indigo-600" />
          <p className="text-gray-600">Chargement des statistiques...</p>
        </div>
      </div>
    );
  }

  if (parkingsError) {
    return (
      <div className="p-8">
        <div className="bg-red-50 border border-red-200 rounded-md p-4 flex items-center gap-3">
          <AlertCircle className="size-5 text-red-600 flex-shrink-0" />
          <div>
            <h3 className="text-red-800 font-medium">Erreur de chargement</h3>
            <p className="text-red-700 mt-1">Impossible de charger les données: {parkingsError.message}</p>
          </div>
        </div>
      </div>
    );
  }

  // Calcul des statistiques avec les vraies données
  const totalParkings = parkings?.length || 0;
  const totalSpaces = parkings?.reduce((sum, p) => sum + p.totalSpots, 0) || 0;
  const availableSpaces = parkings?.reduce((sum, p) => sum + p.availableSpaces, 0) || 0;
  const occupancyRate = totalSpaces > 0 ? ((totalSpaces - availableSpaces) / totalSpaces * 100).toFixed(1) : '0.0';

  // Réservations du mois en cours
  const currentMonth = new Date().getMonth();
  const currentYear = new Date().getFullYear();
  const monthlyReservations = reservations?.filter(r => {
    const date = new Date(r.startTime);
    return date.getMonth() === currentMonth && date.getFullYear() === currentYear;
  }) || [];

  // Chiffre d'affaires du mois
  const monthlyRevenue = monthlyReservations
    .filter(r => r.status === 'completed')
    .reduce((sum, r) => sum + r.totalPrice, 0);

  const subscriptionRevenue = subscriptions?.filter(sp => sp.status === 'active')
    .reduce((sum, sp) => sum + sp.price, 0) || 0;

  const totalRevenue = monthlyRevenue + subscriptionRevenue;

  return (
    <div className="p-8">
      <div className="mb-8">
        <h1 className="text-gray-900 mb-2">Vue d'ensemble</h1>
        <p className="text-gray-600">Statistiques et activité de vos parkings</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-gray-600">Chiffre d'affaires</CardTitle>
            <Euro className="size-4 text-green-600" />
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{totalRevenue.toFixed(2)} €</div>
            <p className="text-gray-500 mt-1">Ce mois-ci</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-gray-600">Parkings</CardTitle>
            <ParkingSquare className="size-4 text-indigo-600" />
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{totalParkings}</div>
            <p className="text-gray-500 mt-1">Parkings actifs</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-gray-600">Réservations</CardTitle>
            <Calendar className="size-4 text-blue-600" />
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{monthlyReservations.length}</div>
            <p className="text-gray-500 mt-1">Ce mois-ci</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-gray-600">Taux d'occupation</CardTitle>
            <TrendingUp className="size-4 text-orange-600" />
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{occupancyRate}%</div>
            <p className="text-gray-500 mt-1">{totalSpaces - availableSpaces}/{totalSpaces} places</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Réservations récentes</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {reservations && reservations.length > 0 ? (
                reservations.slice(0, 5).map((reservation) => (
                  <div key={reservation.id} className="flex items-center justify-between pb-4 border-b last:border-0">
                    <div>
                      <p className="text-gray-900">{reservation.userName || 'Utilisateur'}</p>
                      <p className="text-gray-500">{reservation.parkingName}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-gray-900">{reservation.totalPrice.toFixed(2)} €</p>
                      <span
                        className={`inline-block px-2 py-1 rounded text-white ${
                          reservation.status === 'completed'
                            ? 'bg-green-500'
                            : reservation.status === 'active'
                            ? 'bg-blue-500'
                            : 'bg-gray-400'
                        }`}
                    >
                      {reservation.status === 'completed' ? 'Terminé' : reservation.status === 'active' ? 'En cours' : 'En attente'}
                    </span>
                  </div>
                </div>
              ))
              ) : (
                <div className="text-center py-8 text-gray-500">
                  <Calendar className="size-12 mx-auto mb-4 text-gray-300" />
                  <p>Aucune réservation ce mois</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Parkings</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {parkings && parkings.length > 0 ? (
                parkings.map((parking) => (
                  <div key={parking.id} className="flex items-center justify-between pb-4 border-b last:border-0">
                    <div>
                      <p className="text-gray-900">{parking.title}</p>
                      <p className="text-gray-500">{parking.address.street}, {parking.address.city}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-gray-900">{parking.availableSpaces}/{parking.totalSpots}</p>
                      <p className="text-gray-500">places disponibles</p>
                    </div>
                  </div>
                ))
              ) : (
                <div className="text-center py-8 text-gray-500">
                  <Building className="size-12 mx-auto mb-4 text-gray-300" />
                  <p>Aucun parking enregistré</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
