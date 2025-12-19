import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { toast } from 'sonner';
import { Plus, X } from 'lucide-react';
import { subscriptionService } from '../services';
import { useAsyncOperation } from '../hooks/useApi';

interface AddSubscriptionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  parkingId: string;
  onSuccess?: () => void;
}

export function AddSubscriptionDialog({ open, onOpenChange, parkingId, onSuccess }: AddSubscriptionDialogProps) {
  const { loading, error, execute } = useAsyncOperation();
  const [formData, setFormData] = useState({
    name: '',
    price: '',
    duration: '',
    benefits: [''],
  });

  const handleAddBenefit = () => {
    setFormData({
      ...formData,
      benefits: [...formData.benefits, ''],
    });
  };

  const handleRemoveBenefit = (index: number) => {
    setFormData({
      ...formData,
      benefits: formData.benefits.filter((_, i) => i !== index),
    });
  };

  const handleBenefitChange = (index: number, value: string) => {
    const newBenefits = [...formData.benefits];
    newBenefits[index] = value;
    setFormData({
      ...formData,
      benefits: newBenefits,
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    const result = await execute(async () => {
      return await subscriptionService.createSubscriptionType(parkingId, {
        name: formData.name,
        price: parseFloat(formData.price) || 0,
        duration: parseInt(formData.duration) || 30,
        benefits: formData.benefits.filter(b => b.trim()),
        isActive: true
      });
    });
    
    if (result) {
      toast.success('Type d\'abonnement créé avec succès !');
      onOpenChange(false);
      onSuccess?.();
      setFormData({ name: '', price: '', duration: '', benefits: [''] });
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Ajouter un type d'abonnement</DialogTitle>
          <DialogDescription>
            Créez un nouveau type d'abonnement pour ce parking
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="sub-name">Nom de l'abonnement *</Label>
            <Input
              id="sub-name"
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              placeholder="Abonnement Mensuel"
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="sub-price">Prix (€) *</Label>
              <Input
                id="sub-price"
                type="number"
                step="0.5"
                value={formData.price}
                onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                placeholder="120"
                required
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="sub-duration">Durée (jours) *</Label>
              <Input
                id="sub-duration"
                type="number"
                value={formData.duration}
                onChange={(e) => setFormData({ ...formData, duration: e.target.value })}
                placeholder="30"
                required
              />
            </div>
          </div>

          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label>Avantages</Label>
              <Button type="button" size="sm" variant="outline" onClick={handleAddBenefit}>
                <Plus className="size-4 mr-1" />
                Ajouter
              </Button>
            </div>

            <div className="space-y-2">
              {formData.benefits.map((benefit, index) => (
                <div key={index} className="flex gap-2">
                  <Input
                    value={benefit}
                    onChange={(e) => handleBenefitChange(index, e.target.value)}
                    placeholder="Avantage..."
                    required
                  />
                  {formData.benefits.length > 1 && (
                    <Button
                      type="button"
                      size="icon"
                      variant="ghost"
                      onClick={() => handleRemoveBenefit(index)}
                    >
                      <X className="size-4" />
                    </Button>
                  )}
                </div>
              ))}
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4">
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={loading}>
              {loading ? 'Création...' : 'Ajouter l\'abonnement'}
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
