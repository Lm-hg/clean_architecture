import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Button } from '../ui/button';
import { Badge } from '../ui/badge';
import { reservationService, stationnementService } from '../../services';
import { useApi } from '../../hooks/useApi';
import type { Reservation } from '../../types';
import { Calendar, MapPin, Clock, Euro, LogIn, LogOut as LogOutIcon, FileText, Loader2, Play } from 'lucide-react';
import { toast } from 'sonner';
import { InvoiceDialog } from './InvoiceDialog';

export function MyReservationsPage() {
  const [selectedReservationId, setSelectedReservationId] = useState<string | null>(null);
  const [startingReservationId, setStartingReservationId] = useState<string | null>(null);
  const [exitingReservationId, setExitingReservationId] = useState<string | null>(null);
  
  const { 
    data: userReservations, 
    loading, 
    error,
    execute: fetchReservations
  } = useApi<Reservation[]>(() => reservationService.getUserReservations());

  const reservations = userReservations || [];
  const activeReservations = reservations.filter((r) => r.status === 'active');
  const completedReservations = reservations.filter((r) => r.status === 'completed');
  const pendingReservations = reservations.filter((r) => r.status === 'pending');
  const confirmedReservations = reservations.filter((r) => r.status === 'confirmed');

  const handleStartStationnement = async (reservationId: string) => {
    setStartingReservationId(reservationId);
    try {
      await stationnementService.startFromReservation(reservationId);
      toast.success('Stationnement démarré avec succès!');
      await fetchReservations();
    } catch (error) {
      toast.error('Erreur lors du démarrage du stationnement');
    } finally {
      setStartingReservationId(null);
    }
  };

  const handleEnterParking = (reservationId: string) => {
    toast.success('Entrée enregistrée avec succès !');
  };

  const handleExitParking = async (stationnementId: string) => {
    setExitingReservationId(stationnementId);
    try {
      await stationnementService.endStationnement({ stationnementId });
      toast.success('Sortie du parking enregistrée avec succès!');
      await fetchReservations();
    } catch (error) {
      toast.error('Erreur lors de la sortie du parking');
    } finally {
      setExitingReservationId(null);
    }
  };

  if (loading) {
    return (
      <div className="p-8 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="size-8 animate-spin mx-auto mb-4 text-indigo-600" />
          <p className="text-gray-600">Chargement des réservations...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8">
        <div className="bg-red-50 border border-red-200 rounded-md p-4">
          <p className="text-red-800">Erreur lors du chargement des réservations : {error}</p>
        </div>
      </div>
    );
  }

  const getStatusBadge = (status: string) => {
    switch (status) {
      case 'confirmed':
        return <Badge className="bg-indigo-500">Confirmée</Badge>;
      case 'active':
        return <Badge className="bg-blue-500">En cours</Badge>;
      case 'completed':
        return <Badge className="bg-green-500">Terminée</Badge>;
      case 'pending':
        return <Badge className="bg-yellow-500">En attente</Badge>;
      case 'cancelled':
        return <Badge className="bg-red-500">Annulée</Badge>;
      default:
        return <Badge>{status}</Badge>;
    }
  };

  return (
    <div className="p-8">
      <div className="mb-8">
        <h1 className="text-gray-900 mb-2">Mes réservations</h1>
        <p className="text-gray-600">Gérez vos réservations de parking</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Confirmées</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{confirmedReservations.length}</div>
          </CardContent>
        </Card>
        
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">En cours</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{activeReservations.length}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">En attente</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{pendingReservations.length}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-gray-600">Terminées</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-gray-900">{completedReservations.length}</div>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-4">
        {reservations.length === 0 ? (
          <Card>
            <CardContent className="p-12 text-center">
              <Calendar className="size-12 mx-auto mb-4 text-gray-400" />
              <p className="text-gray-500">Vous n'avez aucune réservation</p>
            </CardContent>
          </Card>
        ) : (
          reservations.map((reservation) => (
            <Card key={reservation.id}>
              <CardContent className="p-6">
                <div className="flex items-start justify-between mb-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-2">
                      <h3 className="text-gray-900">{reservation.parkingName}</h3>
                      {getStatusBadge(reservation.status)}
                    </div>
                    <div className="flex items-center text-gray-600 mb-1">
                      <MapPin className="size-4 mr-2" />
                      <span>Réservation #{reservation.id}</span>
                    </div>
                  </div>
                  <div className="text-right">
                    <p className="text-gray-900">{reservation.totalPrice?.toFixed(2) ?? '0.00'} €</p>
                    {reservation.penalty && (
                      <p className="text-red-600">+ {reservation.penalty.toFixed(2)} € (pénalité)</p>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4 mb-4">
                  <div className="flex items-start text-gray-600">
                    <Calendar className="size-4 mr-2 mt-1 flex-shrink-0" />
                    <div>
                      <p className="text-gray-500">Début</p>
                      <p className="text-gray-900">
                        {new Date(reservation.startTime).toLocaleString('fr-FR')}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-start text-gray-600">
                    <Clock className="size-4 mr-2 mt-1 flex-shrink-0" />
                    <div>
                      <p className="text-gray-500">Fin</p>
                      <p className="text-gray-900">
                        {new Date(reservation.endTime).toLocaleString('fr-FR')}
                      </p>
                    </div>
                  </div>
                </div>

                {reservation.entryTime && (
                  <div className="bg-blue-50 rounded-lg p-3 mb-4">
                    <div className="flex items-center justify-between text-blue-900">
                      <div className="flex items-center">
                        <LogIn className="size-4 mr-2" />
                        <span>Entrée: {reservation.entryTime ? new Date(reservation.entryTime).toLocaleString('fr-FR') : 'Enregistrée'}</span>
                      </div>
                      {reservation.exitTime && (
                        <div className="flex items-center">
                          <LogOutIcon className="size-4 mr-2" />
                          <span>Sortie: {new Date(reservation.exitTime).toLocaleString('fr-FR')}</span>
                        </div>
                      )}
                    </div>
                    {reservation.overstayDuration && (
                      <p className="text-red-600 mt-2">
                        Dépassement: {reservation.overstayDuration} minutes
                      </p>
                    )}
                  </div>
                )}

                <div className="flex gap-2">
                  {(() => {
                    console.log('🔍 Reservation Debug:', {
                      id: reservation.id,
                      status: reservation.status,
                      stationnementId: reservation.stationnementId,
                      entryTime: reservation.entryTime,
                      isConfirmed: reservation.status === 'confirmed',
                      isActive: reservation.status === 'active',
                      hasStationnementId: !!reservation.stationnementId
                    });
                    return null;
                  })()}
                  {reservation.status === 'confirmed' && (
                    <Button
                      onClick={() => handleStartStationnement(reservation.id)}
                      disabled={startingReservationId === reservation.id}
                      className="flex-1 bg-indigo-600 hover:bg-indigo-700"
                    >
                      {startingReservationId === reservation.id ? (
                        <>
                          <Loader2 className="size-4 mr-2 animate-spin" />
                          Démarrage...
                        </>
                      ) : (
                        <>
                          <Play className="size-4 mr-2" />
                          Démarrer le stationnement
                        </>
                      )}
                    </Button>
                  )}

                  {reservation.status === 'active' && reservation.stationnementId && (
                    <Button
                      onClick={() => handleExitParking(reservation.stationnementId!)}
                      disabled={exitingReservationId === reservation.stationnementId}
                      variant="outline"
                      className="flex-1"
                    >
                      {exitingReservationId === reservation.stationnementId ? (
                        <>
                          <Loader2 className="size-4 mr-2 animate-spin" />
                          Sortie...
                        </>
                      ) : (
                        <>
                          <LogOutIcon className="size-4 mr-2" />
                          Sortir du parking
                        </>
                      )}
                    </Button>
                  )}

                  {reservation.status === 'completed' && (
                    <Button
                      onClick={() => setSelectedReservationId(reservation.id)}
                      variant="outline"
                      className="flex-1"
                    >
                      <FileText className="size-4 mr-2" />
                      Voir la facture
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))
        )}
      </div>

      {selectedReservationId && (
        <InvoiceDialog
          reservationId={selectedReservationId}
          open={!!selectedReservationId}
          onOpenChange={(open) => !open && setSelectedReservationId(null)}
        />
      )}
    </div>
  );
}
