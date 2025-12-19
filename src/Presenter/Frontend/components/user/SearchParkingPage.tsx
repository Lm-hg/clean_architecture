import { useState, useEffect } from 'react';
import { Card, CardContent } from '../ui/card';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { parkingService } from '../../services';
import { useApi } from '../../hooks/useApi';
import type { Parking } from '../../types';
import { MapPin, Euro, Clock, Navigation } from 'lucide-react';
import { ParkingDetailsDialog } from './ParkingDetailsDialog';

interface SearchParkingPageProps {
  onViewDetails: (parkingId: string) => void;
}

export function SearchParkingPage({ onViewDetails }: SearchParkingPageProps) {
  const [latitude, setLatitude] = useState('48.8566');
  const [longitude, setLongitude] = useState('2.3522');
  const [radius, setRadius] = useState('5');
  const [filteredParkings, setFilteredParkings] = useState<Parking[]>([]);
  const [selectedParkingId, setSelectedParkingId] = useState<string | null>(null);

  const { data: allParkings, loading, execute: refreshParkings } = useApi(() => parkingService.getAvailableParkings());

  useEffect(() => {
    if (allParkings) {
      setFilteredParkings(allParkings);
    }
  }, [allParkings]);

  const handleSearch = () => {
    if (!allParkings) return;
    
    // Simulation de recherche par distance
    // En production, ceci utiliserait une vraie formule de distance haversine
    const userLat = parseFloat(latitude);
    const userLon = parseFloat(longitude);
    const searchRadius = parseFloat(radius);

    const parkings = allParkings || [];
    const results = parkings.filter((parking) => {
      // Calcul simplifié de distance (approximatif)
      const latDiff = Math.abs(parking.latitude - userLat);
      const lonDiff = Math.abs(parking.longitude - userLon);
      const distance = Math.sqrt(latDiff * latDiff + lonDiff * lonDiff) * 111; // Conversion approximative en km
      
      return distance <= searchRadius && parking.availableSpaces > 0;
    });

    setFilteredParkings(results);
  };

  const handleGetCurrentLocation = () => {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          setLatitude(position.coords.latitude.toString());
          setLongitude(position.coords.longitude.toString());
        },
        () => {
          // Utiliser la position par défaut (Paris)
          setLatitude('48.8566');
          setLongitude('2.3522');
        }
      );
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-8">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-2"></div>
          <p className="text-gray-600">Chargement des parkings...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="mb-8">
        <h1 className="text-gray-900 mb-2">Rechercher un parking</h1>
        <p className="text-gray-600">Trouvez une place de stationnement près de vous</p>
      </div>

      <Card className="mb-8">
        <CardContent className="p-6">
          <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label htmlFor="latitude">Latitude</Label>
                <Input
                  id="latitude"
                  type="number"
                  step="0.0001"
                  value={latitude}
                  onChange={(e) => setLatitude(e.target.value)}
                  placeholder="48.8566"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="longitude">Longitude</Label>
                <Input
                  id="longitude"
                  type="number"
                  step="0.0001"
                  value={longitude}
                  onChange={(e) => setLongitude(e.target.value)}
                  placeholder="2.3522"
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="radius">Rayon (km)</Label>
                <Input
                  id="radius"
                  type="number"
                  value={radius}
                  onChange={(e) => setRadius(e.target.value)}
                  placeholder="5"
                />
              </div>
            </div>

            <div className="flex gap-3">
              <Button onClick={handleGetCurrentLocation} variant="outline" className="flex-1">
                <Navigation className="size-4 mr-2" />
                Ma position
              </Button>
              <Button onClick={handleSearch} className="flex-1">
                <MapPin className="size-4 mr-2" />
                Rechercher
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <div className="mb-4">
        <h2 className="text-gray-900">Parkings disponibles ({filteredParkings.length})</h2>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {filteredParkings.map((parking) => (
          <Card key={parking.id} className="hover:shadow-lg transition-shadow">
            <CardContent className="p-6">
              <div className="mb-4">
                <h3 className="text-gray-900 mb-2">{parking.title}</h3>
                <div className="flex items-start text-gray-600">
                  <MapPin className="size-4 mr-1 mt-1 flex-shrink-0" />
                  <span className="line-clamp-2">{parking.latitude?.toFixed(4) ?? '0.0000'}°, {parking.longitude?.toFixed(4) ?? '0.0000'}°</span>
                </div>
              </div>

              <div className="space-y-3 mb-4">
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
                  <span className="text-gray-900">{(parking.pricePerHour || 0).toFixed(2)} €</span>
                </div>

                <div className="flex items-center justify-between">
                  <div className="flex items-center text-gray-600">
                    <Clock className="size-4 mr-1" />
                    <span>Horaires</span>
                  </div>
                  <span className="text-gray-900">
                    {parking.openingHours && Object.keys(parking.openingHours).length > 0 ? 'Voir détails' : '24/7'}
                  </span>
                </div>
              </div>

              <div className="mb-4">
                <div className="w-full bg-gray-200 rounded-full h-2">
                  <div
                    className="bg-indigo-600 h-2 rounded-full"
                    style={{
                      width: `${(((parking.totalSpots || 0) - (parking.availableSpaces || 0)) / (parking.totalSpots || 1)) * 100}%`,
                    }}
                  />
                </div>
                <p className="text-gray-500 mt-2">
                  {(((parking.totalSpots || 0) - (parking.availableSpaces || 0)) / (parking.totalSpots || 1) * 100).toFixed(0)}% occupé
                </p>
              </div>

              <Button 
                className="w-full" 
                onClick={() => setSelectedParkingId(parking.id)}
              >
                Voir les détails
              </Button>
            </CardContent>
          </Card>
        ))}
      </div>

      {filteredParkings.length === 0 && (
        <div className="text-center py-12">
          <p className="text-gray-500">Aucun parking disponible dans cette zone</p>
        </div>
      )}

      {selectedParkingId && (
        <ParkingDetailsDialog
          parkingId={selectedParkingId}
          open={!!selectedParkingId}
          onOpenChange={(open) => {
            if (!open) {
              setSelectedParkingId(null);
              // Rafraîchir les parkings pour mettre à jour les places disponibles
              refreshParkings();
            }
          }}
        />
      )}
    </div>
  );
}
