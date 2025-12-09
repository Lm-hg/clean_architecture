import { useState } from 'react';
import { Button } from './ui/button';
import { Card, CardContent } from './ui/card';
import { Plus, MapPin, Clock, Euro, AlertCircle, Loader2 } from 'lucide-react';
import { AddParkingDialog } from './AddParkingDialog';
import { parkingService } from '../services';
import { useApi } from '../hooks/useApi';
import type { Parking } from '../types';

interface ParkingsPageProps {
  onViewDetails: (parkingId: string) => void;
}

export function ParkingsPage({ onViewDetails }: ParkingsPageProps) {
  const [showAddDialog, setShowAddDialog] = useState(false);
  
  const { 
    data: parkings, 
    loading, 
    error, 
    execute: refetchParkings 
  } = useApi<Parking[]>(() => parkingService.getMyParkings());



  if (loading) {
    return (
      <div className="p-8 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="size-8 animate-spin mx-auto mb-4 text-indigo-600" />
          <p className="text-gray-600">Chargement des parkings...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-8">
        <div className="bg-red-50 border border-red-200 rounded-md p-4 flex items-center gap-3">
          <AlertCircle className="size-5 text-red-600 flex-shrink-0" />
          <div>
            <h3 className="text-red-800 font-medium">Erreur de chargement</h3>
            <p className="text-red-700 text-sm">{error}</p>
            <Button 
              variant="outline" 
              size="sm" 
              className="mt-2"
              onClick={() => refetchParkings()}
            >
              Réessayer
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-gray-900 mb-2">Mes Parkings</h1>
          <p className="text-gray-600">Gérez vos parkings et leurs paramètres</p>
        </div>
        <Button onClick={() => setShowAddDialog(true)}>
          <Plus className="size-4 mr-2" />
          Ajouter un parking
        </Button>
      </div>

      {parkings && parkings.length === 0 ? (
        <div className="text-center py-12">
          <div className="bg-gray-100 rounded-full p-6 w-24 h-24 mx-auto mb-4 flex items-center justify-center">
            <Plus className="size-8 text-gray-400" />
          </div>
          <h3 className="text-gray-900 text-lg font-medium mb-2">Aucun parking</h3>
          <p className="text-gray-600 mb-6">Commencez par ajouter votre premier parking</p>
          <Button onClick={() => setShowAddDialog(true)}>
            <Plus className="size-4 mr-2" />
            Ajouter un parking
          </Button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {parkings?.map((parking) => (
          <Card key={parking.id} className="hover:shadow-lg transition-shadow cursor-pointer" onClick={() => onViewDetails(parking.id)}>
            <CardContent className="p-6">
              <div className="flex items-start justify-between mb-4">
                <div>
                  <h3 className="text-gray-900 mb-1">{parking.title}</h3>
                  <div className="flex items-center text-gray-600">
                    <MapPin className="size-4 mr-1" />
                    <span>
                      {parking.address.street}, {parking.address.city}
                    </span>
                  </div>
                </div>
              </div>

              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-gray-600">Places disponibles</span>
                  <span className="text-gray-900">
                    {parking.availableSpaces}/{parking.totalSpots}
                  </span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center text-gray-600">
                    <Euro className="size-4 mr-1" />
                    <span>Tarif horaire</span>
                  </div>
                  <span className="text-gray-900">{parking.tarifs.hourly.toFixed(2)} €</span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center text-gray-600">
                    <Clock className="size-4 mr-1" />
                    <span>Horaires</span>
                  </div>
                  <span className="text-gray-900">
                    {parking.isAlwaysOpen ? '24h/24' : 'Variables'}
                  </span>
                </div>
              </div>

              <div className="mt-4 pt-4 border-t">
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div
                    className="bg-indigo-600 h-2 rounded-full"
                    style={{
                      width: `${((parking.totalSpots - parking.availableSpaces) / parking.totalSpots) * 100}%`,
                    }}
                  />
                </div>
                <p className="text-gray-500 mt-2">
                  {(((parking.totalSpots - parking.availableSpaces) / parking.totalSpots) * 100).toFixed(0)}% occupé
                </p>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>
      )}

      <AddParkingDialog 
        open={showAddDialog} 
        onOpenChange={setShowAddDialog}
        onSuccess={refetchParkings}
      />
    </div>
  );
}
