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
    latitude: '',
    longitude: '',
    totalSpots: '',
    pricePerHour: ''
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const result = await execute(async () => {
      return await parkingService.createParking({
        title: formData.title,
        description: formData.description,
        coordinates: {
          latitude: parseFloat(formData.latitude) || 0,
          longitude: parseFloat(formData.longitude) || 0
        },
        totalSpots: parseInt(formData.totalSpots) || 10,
        tarifs: {
          hourly: parseFloat(formData.pricePerHour) || 2.0
        },
        openingHours: {}
      });
    });
    
    if (result) {
      toast.success('Parking ajouté avec succès !');
      onOpenChange(false);
      onSuccess?.(); 
      setFormData({
        title: '',
        description: '',
        latitude: '',
        longitude: '',
        totalSpots: '',
        pricePerHour: ''
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
              <Label htmlFor="latitude">Latitude *</Label>
              <Input
                id="latitude"
                type="number"
                step="0.000001"
                value={formData.latitude}
                onChange={(e) => setFormData({ ...formData, latitude: e.target.value })}
                placeholder="48.8566"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="longitude">Longitude *</Label>
              <Input
                id="longitude"
                type="number"
                step="0.000001"
                value={formData.longitude}
                onChange={(e) => setFormData({ ...formData, longitude: e.target.value })}
                placeholder="2.3522"
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="pricePerHour">Tarif horaire (€) *</Label>
              <Input
                id="pricePerHour"
                type="number"
                step="0.5"
                value={formData.pricePerHour}
                onChange={(e) => setFormData({ ...formData, pricePerHour: e.target.value })}
                placeholder="3.50"
                required
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
