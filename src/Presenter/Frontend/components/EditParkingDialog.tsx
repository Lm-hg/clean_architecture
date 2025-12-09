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
    address: {
      street: parking.address.street,
      city: parking.address.city,
      postalCode: parking.address.postalCode,
      country: parking.address.country
    },
    totalSpots: parking.totalSpots.toString(),
    tarifs: {
      hourly: parking.tarifs.hourly.toString(),
      daily: parking.tarifs.daily?.toString() || '',
      monthly: parking.tarifs.monthly?.toString() || ''
    },
    isAlwaysOpen: parking.isAlwaysOpen || false
  });

  useEffect(() => {
    setFormData({
      title: parking.title,
      description: parking.description || '',
      address: {
        street: parking.address.street,
        city: parking.address.city,
        postalCode: parking.address.postalCode,
        country: parking.address.country
      },
      totalSpots: parking.totalSpots.toString(),
      tarifs: {
        hourly: parking.tarifs.hourly.toString(),
        daily: parking.tarifs.daily?.toString() || '',
        monthly: parking.tarifs.monthly?.toString() || ''
      },
      isAlwaysOpen: parking.isAlwaysOpen || false
    });
  }, [parking]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const result = await execute(async () => {
      return await parkingService.updateParking({
        id: parking.id,
        title: formData.title,
        description: formData.description,
        address: formData.address,
        totalSpots: parseInt(formData.totalSpots) || parking.totalSpots,
        tarifs: {
          hourly: parseFloat(formData.tarifs.hourly) || parking.tarifs.hourly,
          daily: parseFloat(formData.tarifs.daily) || parking.tarifs.daily,
          monthly: parseFloat(formData.tarifs.monthly) || parking.tarifs.monthly
        },
        isAlwaysOpen: formData.isAlwaysOpen
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
              <Label htmlFor="edit-name">Nom du parking *</Label>
              <Input
                id="edit-title"
                value={formData.title}
                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="edit-totalSpaces">Nombre de places *</Label>
              <Input
                id="edit-totalSpaces"
                type="number"
                value={formData.totalSpots}
                onChange={(e) => setFormData({ ...formData, totalSpots: e.target.value })}
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="edit-street">Rue *</Label>
              <Input
                id="edit-street"
                value={formData.address.street}
                onChange={(e) => setFormData({ ...formData, address: { ...formData.address, street: e.target.value } })}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="edit-city">Ville *</Label>
              <Input
                id="edit-city"
                value={formData.address.city}
                onChange={(e) => setFormData({ ...formData, address: { ...formData.address, city: e.target.value } })}
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-2">
              <Label htmlFor="edit-hourlyRate">Tarif horaire (€) *</Label>
              <Input
                id="edit-hourlyRate"
                type="number"
                step="0.5"
                value={formData.tarifs.hourly}
                onChange={(e) => setFormData({ ...formData, tarifs: { ...formData.tarifs, hourly: e.target.value } })}
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="edit-openingTime">Heure d'ouverture *</Label>
              <Input
                id="edit-openingTime"
                type="time"
                value={formData.openingTime}
                onChange={(e) => setFormData({ ...formData, openingTime: e.target.value })}
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="edit-closingTime">Heure de fermeture *</Label>
              <Input
                id="edit-closingTime"
                type="time"
                value={formData.closingTime}
                onChange={(e) => setFormData({ ...formData, closingTime: e.target.value })}
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
