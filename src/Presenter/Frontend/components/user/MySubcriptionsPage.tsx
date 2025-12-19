import { Card, CardContent, CardHeader, CardTitle } from '../ui/card';
import { Badge } from '../ui/badge';
import { subscriptionService } from '../../services';
import { useApi } from '../../hooks/useApi';
import type { Subscription } from '../../types';
import { CreditCard, Calendar, CheckCircle, Loader2 } from 'lucide-react';

export function MySubscriptionsPage() {
  const { 
    data: userSubscriptions, 
    loading, 
    error 
  } = useApi<Subscription[]>(() => subscriptionService.getUserSubscriptions());
  
  const subscriptions = userSubscriptions || [];
  const activeSubscriptions = subscriptions.filter((s) => s.status === 'active');
  const expiredSubscriptions = subscriptions.filter((s) => s.status === 'expired');

  if (loading) {
    return (
      <div className="p-8 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="size-8 animate-spin mx-auto mb-4 text-indigo-600" />
          <p className="text-gray-600">Chargement des abonnements...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8">
        <div className="bg-red-50 border border-red-200 rounded-md p-4">
          <p className="text-red-800">Erreur lors du chargement des abonnements : {error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="mb-8">
        <h1 className="text-gray-900 mb-2">Mes abonnements</h1>
        <p className="text-gray-600">Gérez vos abonnements de parking</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-600 mb-1">Abonnements actifs</p>
                <p className="text-gray-900">{activeSubscriptions.length}</p>
              </div>
              <CreditCard className="size-8 text-green-600" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-gray-600 mb-1">Total des abonnements</p>
                <p className="text-gray-900">{userSubscriptions.length}</p>
              </div>
              <Calendar className="size-8 text-indigo-600" />
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="space-y-4">
        {userSubscriptions.length === 0 ? (
          <Card>
            <CardContent className="p-12 text-center">
              <CreditCard className="size-12 mx-auto mb-4 text-gray-400" />
              <p className="text-gray-500">Vous n'avez aucun abonnement</p>
            </CardContent>
          </Card>
        ) : (
          userSubscriptions.map((subscription) => {
            return (
              <Card key={subscription.id}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle>{subscription.subscriptionType?.name || 'Abonnement'}</CardTitle>
                    {subscription.status === 'active' ? (
                      <Badge className="bg-green-500">Actif</Badge>
                    ) : subscription.status === 'cancelled' ? (
                      <Badge className="bg-red-500">Annulé</Badge>
                    ) : (
                      <Badge className="bg-gray-500">Expiré</Badge>
                    )}
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <p className="text-gray-500 mb-1">Date de début</p>
                      <p className="text-gray-900">
                        {new Date(subscription.startDate).toLocaleDateString('fr-FR')}
                      </p>
                    </div>

                    <div>
                      <p className="text-gray-500 mb-1">Date de fin</p>
                      <p className="text-gray-900">
                        {new Date(subscription.endDate).toLocaleDateString('fr-FR')}
                      </p>
                    </div>
                  </div>

                  {subscription.subscriptionType?.description && (
                    <div>
                      <p className="text-gray-500 mb-2">Description:</p>
                      <p className="text-gray-700">{subscription.subscriptionType.description}</p>
                    </div>
                  )}

                  {subscription.status === 'active' && (
                    <div className="bg-green-50 rounded-lg p-3">
                      <p className="text-green-900">
                        ✓ Vous pouvez entrer et sortir librement pendant la période d'abonnement
                      </p>
                    </div>
                  )}

                  <div className="pt-4 border-t">
                    <div className="flex items-center justify-between">
                      <span className="text-gray-600">Prix payé</span>
                      <span className="text-gray-900">{(subscription.price || 0).toFixed(2)} €</span>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })
        )}
      </div>
    </div>
  );
}
