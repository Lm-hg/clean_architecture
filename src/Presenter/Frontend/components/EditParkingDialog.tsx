import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Textarea } from './ui/textarea';
import { toast } from 'sonner';
import { parkingService } from '../services';
import { useAsyncOperation } from '../hooks/useApi';
import type { Parking } from '../types';

interface EditParkingDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  parking: Parking;
  onSuccess?: () => void;
}

export function EditParkingDialog({ open, onOpenChange, parking, onSuccess }: EditParkingDialogProps) {
  const { loading, error, execute } = useAsyncOperation();
  const [formData, setFormData] = useState({
    title: parking.title,
    description: parking.description || '',
    latitude: (parking.latitude || 0).toString(),
    longitude: (parking.longitude || 0).toString(),
    totalSpots: parking.totalSpots.toString(),
    pricePerHour: (parking.pricePerHour || 0).toString()
  });

  useEffect(() => {
    setFormData({
      title: parking.title,
      description: parking.description || '',
      latitude: (parking.latitude || 0).toString(),
      longitude: (parking.longitude || 0).toString(),
      totalSpots: parking.totalSpots.toString(),
      pricePerHour: (parking.pricePerHour || 0).toString()
    });
  }, [parking]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const result = await execute(async () => {
      return await parkingService.updateParking({
        id: parking.id,
        title: formData.title,
        description: formData.description,
        latitude: parseFloat(formData.latitude) || parking.latitude || 0,
        longitude: parseFloat(formData.longitude) || parking.longitude || 0,
        totalSpots: parseInt(formData.totalSpots) || parking.totalSpots,
        pricePerHour: parseFloat(formData.pricePerHour) || parking.pricePerHour || 0
      });
    });
    
    if (result) {
      toast.success('Parking modifié avec succès !');
      onOpenChange(false);
      onSuccess?.();
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Modifier le parking</DialogTitle>
          <DialogDescription>
            Modifiez les informations du parking
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="edit-title">Nom du parking *</Label>
              <Input
                id="edit-title"
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="edit-totalSpots">Nombre de places *</Label>
              <Input
                id="edit-totalSpots"
                type="number"
                value={formData.totalSpots}
                onChange={(e) => setFormData({ ...formData, totalSpots: e.target.value })}
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="edit-latitude">Latitude *</Label>
              <Input
                id="edit-latitude"
                type="number"
                step="0.000001"
                value={formData.latitude}
                onChange={(e) => setFormData({ ...formData, latitude: e.target.value })}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-longitude">Longitude *</Label>
              <Input
                id="edit-longitude"
                type="number"
                step="0.000001"
                value={formData.longitude}
                onChange={(e) => setFormData({ ...formData, longitude: e.target.value })}
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="edit-pricePerHour">Tarif horaire (€) *</Label>
              <Input
                id="edit-pricePerHour"
                type="number"
                step="0.5"
                value={formData.pricePerHour}
                onChange={(e) => setFormData({ ...formData, pricePerHour: e.target.value })}
                required
              />
            </div>
            
          </div>

          <div className="space-y-2">
            <Label htmlFor="edit-description">Description</Label>
            <Textarea
              id="edit-description"
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              rows={3}
            />
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit">Enregistrer</Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
