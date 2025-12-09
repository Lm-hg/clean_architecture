import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../ui/dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { parkingService, subscriptionService } from '../../services';
import { useApi } from '../../hooks/useApi';
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

  const { data: parking } = useApi(() => parkingService.getParkingById(parkingId));
  const { data: allSubscriptions } = useApi(() => subscriptionService.getAvailableSubscriptions(parkingId));
  
  const subscriptions = allSubscriptions || [];

  if (!parking) return null;

  const handleReserve = () => {
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

    // Vérification de disponibilité (simulée)
    if (parking.availableSpaces <= 0) {
      toast.error('Aucune place disponible pour ce créneau');
      return;
    }

    // Calcul du prix
    const hours = (end.getTime() - start.getTime()) / (1000 * 60 * 60);
    const price = hours * parking.hourlyRate;

    toast.success(`Réservation confirmée ! Montant: ${price.toFixed(2)} €`);
    onOpenChange(false);
  };

  const handleSubscribe = (subscriptionId: string) => {
    const subscription = subscriptions.find((s) => s.id === subscriptionId);
    if (!subscription) return;

    toast.success(`Abonnement "${subscription.name}" souscrit avec succès !`);
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{parking.name}</DialogTitle>
        </DialogHeader>

        <div className="space-y-6">
          <div className="space-y-3">
            <div className="flex items-start text-gray-600">
              <MapPin className="size-4 mr-2 mt-1 flex-shrink-0" />
              <span>{parking.address}</span>
            </div>

            <div className="flex items-center text-gray-600">
              <Euro className="size-4 mr-2" />
              <span>Tarif: {parking.hourlyRate.toFixed(2)} € / heure</span>
            </div>

            <div className="flex items-center text-gray-600">
              <Clock className="size-4 mr-2" />
              <span>
                {parking.isAlwaysOpen 
                  ? 'Ouvert 24h/24 et 7j/7' 
                  : `Horaires: ${parking.openingTime} - ${parking.closingTime}`}
              </span>
            </div>

            <p className="text-gray-600">{parking.description}</p>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <Card>
              <CardContent className="p-4">
                <p className="text-gray-600 mb-1">Places disponibles</p>
                <p className="text-gray-900">
                  {parking.availableSpaces} / {parking.totalSpaces}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-4">
                <p className="text-gray-600 mb-1">Taux d'occupation</p>
                <p className="text-gray-900">
                  {(((parking.totalSpaces - parking.availableSpaces) / parking.totalSpaces) * 100).toFixed(0)}%
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
                    <li>Vous serez facturé pour la totalité de la réservation</li>
                    <li>Vous devez entrer dans le parking pendant votre créneau</li>
                    <li>Pénalité de 20€ si vous dépassez votre créneau</li>
                    <li>Le temps additionnel sera facturé au tarif horaire</li>
                  </ul>
                </div>

                <Button onClick={handleReserve} className="w-full" size="lg">
                  <Calendar className="size-4 mr-2" />
                  Réserver maintenant
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
                            <span className="text-gray-900">{subscription.duration} jours</span>
                          </div>
                        </div>

                        <div>
                          <p className="text-gray-600 mb-2">Avantages:</p>
                          <ul className="space-y-1">
                            {subscription.benefits.map((benefit, index) => (
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
                        >
                          <CreditCard className="size-4 mr-2" />
                          Souscrire
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
