import { useState } from 'react';
import { Button } from '../ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';
import { Calendar, Clock, CreditCard, AlertCircle, Loader2 } from 'lucide-react';
import { subscriptionService } from '../../services';
import { useApi } from '../../hooks/useApi';
import { formatDate, formatPrice } from '../../utils';
import type { Subscription } from '../../types';

export function MySubscriptionsPage() {
  const { 
    data: subscriptions, 
    loading, 
    error, 
    execute: refetchSubscriptions 
  } = useApi<Subscription[]>(() => subscriptionService.getUserSubscriptions());

  const handleCancelSubscription = async (subscriptionId: string) => {
    try {
      await subscriptionService.cancelSubscription(subscriptionId);
      refetchSubscriptions();
    } catch (error) {
      console.error('Erreur lors de l\'annulation:', error);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="text-center">
          <Loader2 className="size-8 animate-spin mx-auto mb-4 text-indigo-600" />
          <p className="text-gray-600">Chargement de vos abonnements...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <div className="bg-red-50 border border-red-200 rounded-md p-4 flex items-center gap-3">
          <AlertCircle className="size-5 text-red-600 flex-shrink-0" />
          <div>
            <h3 className="text-red-800 font-medium">Erreur de chargement</h3>
            <p className="text-red-700 text-sm">{error}</p>
            <Button 
              variant="outline" 
              size="sm" 
              className="mt-2"
              onClick={() => refetchSubscriptions()}
            >
              Réessayer
            </Button>
          </div>
        </div>
      </div>
    );
  }

  if (!subscriptions || subscriptions.length === 0) {
    return (
      <div className="text-center py-12">
        <div className="bg-gray-100 rounded-full p-6 w-24 h-24 mx-auto mb-4 flex items-center justify-center">
          <CreditCard className="size-8 text-gray-400" />
        </div>
        <h3 className="text-gray-900 text-lg font-medium mb-2">Aucun abonnement</h3>
        <p className="text-gray-600">Vous n'avez pas encore souscrit d'abonnement</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-gray-900 mb-2">Mes Abonnements</h2>
        <p className="text-gray-600">Gérez vos abonnements de parking</p>
      </div>

      <div className="grid gap-6">
        {subscriptions.map((subscription) => (
          <Card key={subscription.id} className="overflow-hidden">
            <CardHeader className="pb-4">
              <div className="flex items-start justify-between">
                <div>
                  <CardTitle className="text-lg">
                    {subscription.subscriptionType?.name || 'Abonnement'}
                  </CardTitle>
                  <p className="text-gray-600 mt-1">
                    {subscription.subscriptionType?.description}
                  </p>
                </div>
                <Badge 
                  variant={
                    subscription.status === 'active' ? 'default' : 
                    subscription.status === 'expired' ? 'destructive' : 
                    'secondary'
                  }
                >
                  {subscription.status === 'active' ? 'Actif' :
                   subscription.status === 'expired' ? 'Expiré' :
                   'Annulé'}
                </Badge>
              </div>
            </CardHeader>
            
            <CardContent className="pt-0">
              <div className="grid md:grid-cols-2 gap-4">
                <div className="space-y-3">
                  <div className="flex items-center gap-2 text-sm">
                    <Calendar className="size-4 text-gray-500" />
                    <span className="text-gray-600">Période :</span>
                    <span className="font-medium">
                      {formatDate(subscription.startDate)} - {formatDate(subscription.endDate)}
                    </span>
                  </div>
                  
                  <div className="flex items-center gap-2 text-sm">
                    <CreditCard className="size-4 text-gray-500" />
                    <span className="text-gray-600">Prix :</span>
                    <span className="font-medium">{formatPrice(subscription.price)}</span>
                  </div>

                  {subscription.subscriptionType?.timeSlots && subscription.subscriptionType.timeSlots.length > 0 && (
                    <div className="flex items-start gap-2 text-sm">
                      <Clock className="size-4 text-gray-500 mt-0.5" />
                      <div>
                        <span className="text-gray-600">Créneaux :</span>
                        <div className="mt-1 space-y-1">
                          {subscription.subscriptionType.timeSlots.map((slot, index) => (
                            <div key={index} className="text-xs bg-gray-100 px-2 py-1 rounded">
                              {getDayName(slot.dayOfWeek)} : {slot.startTime} - {slot.endTime}
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                <div className="flex items-center justify-end">
                  {subscription.status === 'active' && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleCancelSubscription(subscription.id)}
                      className="text-red-600 border-red-200 hover:bg-red-50"
                    >
                      Annuler l'abonnement
                    </Button>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}

function getDayName(dayOfWeek: number): string {
  const days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
  return days[dayOfWeek] || 'Inconnu';
}