import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from './ui/card';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Parking } from '../types';
import { Calendar } from 'lucide-react';

interface AvailabilityCheckerProps {
  parking: Parking;
}

export function AvailabilityChecker({ parking }: AvailabilityCheckerProps) {
  const [selectedDate, setSelectedDate] = useState('');
  const [selectedTime, setSelectedTime] = useState('');
  const [availableSpaces, setAvailableSpaces] = useState<number | null>(null);

  const handleCheck = () => {
    // Simulation de vérification de disponibilité
    // En production, ceci ferait une requête API avec le timestamp
    const randomAvailable = Math.floor(Math.random() * parking.totalSpots);
    setAvailableSpaces(randomAvailable);
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Vérifier la disponibilité</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="check-date">Date</Label>
              <Input
                id="check-date"
                type="date"
                value={selectedDate}
                onChange={(e) => setSelectedDate(e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="check-time">Heure</Label>
              <Input
                id="check-time"
                type="time"
                value={selectedTime}
                onChange={(e) => setSelectedTime(e.target.value)}
              />
            </div>
          </div>

          <Button 
            onClick={handleCheck} 
            disabled={!selectedDate || !selectedTime}
            className="w-full"
          >
            <Calendar className="size-4 mr-2" />
            Vérifier la disponibilité
          </Button>

          {availableSpaces !== null && (
            <div className="mt-6 p-6 bg-indigo-50 rounded-lg border border-indigo-200">
              <div className="text-center">
                <p className="text-gray-600 mb-2">Places disponibles le</p>
                <p className="text-gray-900 mb-4">
                  {new Date(selectedDate + 'T' + selectedTime).toLocaleString('fr-FR')}
                </p>
                <div className="text-indigo-600">
                  {availableSpaces} / {parking.totalSpots} places
                </div>
                <div className="mt-4 w-full bg-gray-200 rounded-full h-3">
                  <div
                    className="bg-indigo-600 h-3 rounded-full transition-all"
                    style={{
                      width: `${(availableSpaces / parking.totalSpots) * 100}%`,
                    }}
                  />
                </div>
              </div>
            </div>
          )}

          <div className="mt-6 p-4 bg-gray-50 rounded-lg">
            <h4 className="text-gray-900 mb-3">Informations du parking</h4>
            <div className="space-y-2 text-gray-600">
              <div className="flex justify-between">
                <span>Capacité totale:</span>
                <span className="text-gray-900">{parking.totalSpots} places</span>
              </div>
              <div className="flex justify-between">
                <span>Tarif horaire:</span>
                <span className="text-gray-900">{(parking.pricePerHour || 0).toFixed(2)} €/h</span>
              </div>
              <div className="flex justify-between">
                <span>Horaires d'ouverture:</span>
                <span className="text-gray-900">
                  {parking.openingHours && Object.keys(parking.openingHours).length > 0 ? 'Voir détails' : '24h/24'}
                </span>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
