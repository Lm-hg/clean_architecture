import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Textarea } from './ui/textarea';
import { toast } from 'sonner';
import { parkingService } from '../services';
import { useAsyncOperation } from '../hooks/useApi';

interface AddParkingDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSuccess?: () => void; // Callback appelé après création réussie
}

export function AddParkingDialog({ open, onOpenChange, onSuccess }: AddParkingDialogProps) {
  const { loading, error, execute } = useAsyncOperation();
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    address: {
      street: '',
      city: '',
      postalCode: '',
      country: 'France'
    },
    coordinates: {
      latitude: 0,
      longitude: 0
    },
    totalSpots: '',
    tarifs: {
      hourly: '',
      daily: '',
      monthly: ''
    },
    isAlwaysOpen: false
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const result = await execute(async () => {
      return await parkingService.createParking({
        title: formData.title,
        description: formData.description,
        address: formData.address,
        coordinates: formData.coordinates,
        totalSpots: parseInt(formData.totalSpots) || 10,
        tarifs: {
          hourly: parseFloat(formData.tarifs.hourly) || 2.0,
          daily: parseFloat(formData.tarifs.daily) || 20.0,
          monthly: parseFloat(formData.tarifs.monthly) || 150.0
        },
        openingHours: {},
        isAlwaysOpen: formData.isAlwaysOpen
      });
    });
    
    if (result) {
      toast.success('Parking ajouté avec succès !');
      onOpenChange(false);
      onSuccess?.(); 
      setFormData({
        title: '',
        description: '',
        address: {
          street: '',
          city: '',
          postalCode: '',
          country: 'France'
        },
        coordinates: {
          latitude: 0,
          longitude: 0
        },
        totalSpots: '',
        tarifs: {
          hourly: '',
          daily: '',
          monthly: ''
        },
        isAlwaysOpen: false
      });
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Ajouter un parking</DialogTitle>
          <DialogDescription>
            Remplissez les informations pour créer un nouveau parking
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="title">Nom du parking *</Label>
              <Input
                id="title"
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                placeholder="Parking Centre Ville"
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="totalSpots">Nombre de places *</Label>
              <Input
                id="totalSpots"
                type="number"
                value={formData.totalSpots}
                onChange={(e) => setFormData({ ...formData, totalSpots: e.target.value })}
                placeholder="50"
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="street">Rue *</Label>
              <Input
                id="street"
                value={formData.address.street}
                onChange={(e) => setFormData({ ...formData, address: { ...formData.address, street: e.target.value } })}
                placeholder="15 Rue de la République"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="city">Ville *</Label>
              <Input
                id="city"
                value={formData.address.city}
                onChange={(e) => setFormData({ ...formData, address: { ...formData.address, city: e.target.value } })}
                placeholder="Paris"
                required
              />
            </div>
          </div>
          
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="postalCode">Code postal *</Label>
              <Input
                id="postalCode"
                value={formData.address.postalCode}
                onChange={(e) => setFormData({ ...formData, address: { ...formData.address, postalCode: e.target.value } })}
                placeholder="75001"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="country">Pays</Label>
              <Input
                id="country"
                value={formData.address.country}
                onChange={(e) => setFormData({ ...formData, address: { ...formData.address, country: e.target.value } })}
                placeholder="France"
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-2">
              <Label htmlFor="hourlyRate">Tarif horaire (€) *</Label>
              <Input
                id="hourlyRate"
                type="number"
                step="0.5"
                value={formData.tarifs.hourly}
                onChange={(e) => setFormData({ ...formData, tarifs: { ...formData.tarifs, hourly: e.target.value } })}
                placeholder="3.50"
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="dailyRate">Tarif journalier (€)</Label>
              <Input
                id="dailyRate"
                type="number"
                step="0.5"
                value={formData.tarifs.daily}
                onChange={(e) => setFormData({ ...formData, tarifs: { ...formData.tarifs, daily: e.target.value } })}
                placeholder="25.00"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="monthlyRate">Tarif mensuel (€)</Label>
              <Input
                id="monthlyRate"
                type="number"
                step="1"
                value={formData.tarifs.monthly}
                onChange={(e) => setFormData({ ...formData, tarifs: { ...formData.tarifs, monthly: e.target.value } })}
                placeholder="200.00"
              />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea
              id="description"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              placeholder="Parking sécurisé au cœur du centre ville"
              rows={3}
            />
          </div>

          {error && (
            <div className="p-4 bg-red-50 border border-red-200 rounded-md">
              <p className="text-red-800 text-sm">{error}</p>
            </div>
          )}

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={loading}>
              {loading ? 'Création...' : 'Ajouter le parking'}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
