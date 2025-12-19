import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../ui/dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { parkingService, subscriptionService, reservationService } from '../../services';
import { useApi, useAsyncOperation } from '../../hooks/useApi';
import type { Parking, Subscription } from '../../types';
import { MapPin, Euro, Clock, Calendar, CreditCard } from 'lucide-react';
import { toast } from 'sonner';

interface ParkingDetailsDialogProps {
  parkingId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function ParkingDetailsDialog({ parkingId, open, onOpenChange }: ParkingDetailsDialogProps) {
  const [startDate, setStartDate] = useState('');
  const [startTime, setStartTime] = useState('');
  const [endDate, setEndDate] = useState('');
  const [endTime, setEndTime] = useState('');

  const { data: parking, execute: refreshParking } = useApi(() => parkingService.getParkingById(parkingId));
  const { data: allSubscriptions } = useApi(() => subscriptionService.getAvailableSubscriptionTypes(parkingId));
  const { loading: reserving, execute: executeReservation } = useAsyncOperation();
  const { loading: subscribing, execute: executeSubscription } = useAsyncOperation();
  
  const subscriptions = allSubscriptions || [];

  if (!parking) return null;

  const handleReserve = async () => {
    if (!startDate || !startTime || !endDate || !endTime) {
      toast.error('Veuillez remplir tous les champs');
      return;
    }

    const start = new Date(`${startDate}T${startTime}`);
    const end = new Date(`${endDate}T${endTime}`);

    if (end <= start) {
      toast.error('La date de fin doit être après la date de début');
      return;
    }

    // Vérifier la durée minimale de 15 minutes
    const durationMinutes = (end.getTime() - start.getTime()) / (1000 * 60);
    if (durationMinutes < 15) {
      toast.error('La durée minimale de réservation est de 15 minutes');
      return;
    }

    // Vérification de disponibilité
    if (parking.availableSpaces <= 0) {
      toast.error('Aucune place disponible pour ce créneau');
      return;
    }

    // Créer la réservation via l'API
    const result = await executeReservation(async () => {
      return await reservationService.createReservation({
        parkingId: parking.id,
        startTime: start.toISOString(),
        endTime: end.toISOString()
      });
    });

    if (result) {
      const hours = (end.getTime() - start.getTime()) / (1000 * 60 * 60);
      const price = hours * (parking.pricePerHour || 0);
      toast.success(`Réservation confirmée ! Montant: ${price.toFixed(2)} €`);
      setStartDate('');
      setStartTime('');
      setEndDate('');
      setEndTime('');
      // Recharger les données du parking pour mettre à jour les places disponibles
      await refreshParking();
      onOpenChange(false);
    }
  };

  const handleSubscribe = async (subscriptionTypeId: string) => {
    const subscription = subscriptions.find((s) => s.id === subscriptionTypeId);
    if (!subscription) return;

    const result = await executeSubscription(async () => {
      return await subscriptionService.createSubscription({
        subscriptionTypeId: subscriptionTypeId
      });
    });

    if (result) {
      toast.success(`Abonnement "${subscription.name}" souscrit avec succès !`);
      onOpenChange(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{parking.title}</DialogTitle>
          <DialogDescription>
            Réservez une place ou souscrivez à un abonnement pour ce parking
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          <div className="space-y-3">
            <div className="flex items-start text-gray-600">
              <MapPin className="size-4 mr-2 mt-1 flex-shrink-0" />
              <span>{parking.latitude?.toFixed(4) ?? '0.0000'}°, {parking.longitude?.toFixed(4) ?? '0.0000'}°</span>
            </div>

            <div className="flex items-center text-gray-600">
              <Euro className="size-4 mr-2" />
              <span>Tarif: {(parking.pricePerHour || 0).toFixed(2)} € / heure</span>
            </div>

            <div className="flex items-center text-gray-600">
              <Clock className="size-4 mr-2" />
              <span>
                {(() => {
                  if (!parking.openingHours || Object.keys(parking.openingHours).length === 0) {
                    return 'Ouvert 24h/24 et 7j/7';
                  }
                  const days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
                  const today = days[new Date().getDay()];
                  const todayHours = parking.openingHours[today];
                  if (todayHours && todayHours.length > 0) {
                    return `Horaires: ${todayHours[0]}`;
                  }
                  return 'Fermé aujourd\'hui';
                })()}
              </span>
            </div>

            <p className="text-gray-600">{parking.description}</p>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <Card>
              <CardContent className="p-4">
                <p className="text-gray-600 mb-1">Places disponibles</p>
                <p className="text-gray-900">
                  {parking.availableSpaces} / {parking.totalSpots}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <p className="text-gray-600 mb-1">Taux d'occupation</p>
                <p className="text-gray-900">
                  {(((parking.totalSpots || 0) - (parking.availableSpaces || 0)) / (parking.totalSpots || 1) * 100).toFixed(0)}%
                </p>
              </CardContent>
            </Card>
          </div>

          <Tabs defaultValue="reserve" className="w-full">
            <TabsList className="grid w-full grid-cols-2">
              <TabsTrigger value="reserve">Réserver</TabsTrigger>
              <TabsTrigger value="subscriptions">Abonnements</TabsTrigger>
            </TabsList>

            <TabsContent value="reserve" className="mt-6">
              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="start-date">Date de début</Label>
                    <Input
                      id="start-date"
                      type="date"
                      value={startDate}
                      onChange={(e) => setStartDate(e.target.value)}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="start-time">Heure de début</Label>
                    <Input
                      id="start-time"
                      type="time"
                      value={startTime}
                      onChange={(e) => setStartTime(e.target.value)}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="end-date">Date de fin</Label>
                    <Input
                      id="end-date"
                      type="date"
                      value={endDate}
                      onChange={(e) => setEndDate(e.target.value)}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="end-time">Heure de fin</Label>
                    <Input
                      id="end-time"
                      type="time"
                      value={endTime}
                      onChange={(e) => setEndTime(e.target.value)}
                    />
                  </div>
                </div>

                <div className="bg-indigo-50 rounded-lg p-4">
                  <p className="text-gray-600 mb-1">Important</p>
                  <ul className="text-gray-700 space-y-1 list-disc list-inside">
                    <li><strong>Durée minimum de réservation : 15 minutes</strong></li>
                    <li>Facturation par tranche de 15 minutes</li>
                    <li>Vous devez entrer dans le parking pendant votre créneau</li>
                    <li>Pénalité de 20€ si vous dépassez votre créneau</li>
                    <li>Le temps additionnel sera facturé au tarif horaire</li>
                  </ul>
                </div>

                <Button onClick={handleReserve} className="w-full" size="lg" disabled={reserving}>
                  <Calendar className="size-4 mr-2" />
                  {reserving ? 'Réservation en cours...' : 'Réserver maintenant'}
                </Button>
              </div>
            </TabsContent>

            <TabsContent value="subscriptions" className="mt-6">
              {subscriptions.length === 0 ? (
                <p className="text-center text-gray-500 py-8">
                  Aucun abonnement disponible pour ce parking
                </p>
              ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {subscriptions.map((subscription) => (
                    <Card key={subscription.id}>
                      <CardHeader>
                        <CardTitle>{subscription.name}</CardTitle>
                      </CardHeader>
                      <CardContent className="space-y-4">
                        <div className="space-y-2">
                          <div className="flex justify-between">
                            <span className="text-gray-600">Prix</span>
                            <span className="text-gray-900">{subscription.price} €</span>
                          </div>
                          <div className="flex justify-between">
                            <span className="text-gray-600">Durée</span>
                            <span className="text-gray-900">{subscription.durationDays || subscription.duration} jours</span>
                          </div>
                        </div>

                        <div>
                          <p className="text-gray-600 mb-2">Avantages:</p>
                          <ul className="space-y-1">
                            {(subscription.benefits || []).map((benefit, index) => (
                              <li key={index} className="flex items-start text-gray-700">
                                <span className="text-green-600 mr-2">✓</span>
                                {benefit}
                              </li>
                            ))}
                          </ul>
                        </div>

                        <Button 
                          onClick={() => handleSubscribe(subscription.id)}
                          className="w-full"
                          disabled={subscribing}
                        >
                          <CreditCard className="size-4 mr-2" />
                          {subscribing ? 'Souscription en cours...' : 'Souscrire'}
                        </Button>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              )}
            </TabsContent>
          </Tabs>
        </div>
      </DialogContent>
    </Dialog>
  );
}
