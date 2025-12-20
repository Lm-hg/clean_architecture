import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '../ui/dialog';
import { Button } from '../ui/button';
import { Download, FileText, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { reservationService } from '../../services';
import { useApi } from '../../hooks/useApi';
import type { Reservation } from '../../types';

interface InvoiceDialogProps {
  reservationId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function InvoiceDialog({ reservationId, open, onOpenChange }: InvoiceDialogProps) {
  const { data: reservation, loading } = useApi<Reservation>(
    () => reservationService.getReservationById(reservationId),
    [reservationId]
  );

  if (loading) {
    return (
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-2xl">
          <div className="flex items-center justify-center p-8">
            <Loader2 className="size-8 animate-spin text-indigo-600" />
          </div>
        </DialogContent>
      </Dialog>
    );
  }

  if (!reservation) return null;

  const handleDownload = async () => {
    try {
      // Pour l'instant, on télécharge les données de la facture en JSON
      // TODO: Implémenter la génération de PDF côté serveur
      const invoiceData = {
        invoiceId: `INV-${reservation.id}`,
        date: new Date(reservation.createdAt).toLocaleDateString('fr-FR'),
        reservation: {
          id: reservation.id,
          parkingName: reservation.parkingName || 'Parking',
          startTime: new Date(reservation.startTime).toLocaleString('fr-FR'),
          endTime: new Date(reservation.endTime).toLocaleString('fr-FR'),
          entryTime: reservation.entryTime ? new Date(reservation.entryTime).toLocaleString('fr-FR') : null,
          exitTime: reservation.exitTime ? new Date(reservation.exitTime).toLocaleString('fr-FR') : null,
          totalPrice: reservation.totalPrice || 0,
          penalty: reservation.penalty || 0,
          overstayDuration: reservation.overstayDuration || 0,
          status: reservation.status
        }
      };
      
      const blob = new Blob([JSON.stringify(invoiceData, null, 2)], { type: 'application/json' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `facture-${reservation.id}.json`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      
      toast.success('Facture téléchargée avec succès !');
    } catch (error) {
      toast.error('Erreur lors du téléchargement de la facture');
    }
  };

  const calculateTotalPrice = () => {
    const basePrice = reservation.totalPrice || 0;
    return basePrice;
  };

  const totalPrice = calculateTotalPrice();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Facture de réservation</DialogTitle>
          <DialogDescription>
            Détails et informations de facturation pour votre réservation
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-6">
          <div className="bg-indigo-50 rounded-lg p-6">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h3 className="text-indigo-900 mb-1">ParkShare</h3>
                <p className="text-indigo-700">Plateforme de parking partagé</p>
              </div>
              <FileText className="size-12 text-indigo-600" />
            </div>
            <div className="border-t border-indigo-200 pt-4">
              <p className="text-indigo-900">Facture #INV-{reservation.id}</p>
              <p className="text-indigo-700">
                Date: {new Date(reservation.createdAt).toLocaleDateString('fr-FR')}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-6">
            <div>
              <h4 className="text-gray-900 mb-2">Client</h4>
              <p className="text-gray-700">{reservation.userName || 'Utilisateur'}</p>
              <p className="text-gray-600">{reservation.userEmail || 'user@email.com'}</p>
            </div>

            <div>
              <h4 className="text-gray-900 mb-2">Parking</h4>
              <p className="text-gray-700">{reservation.parkingName || 'Parking'}</p>
              <p className="text-gray-600">Réservation #{reservation.id}</p>
            </div>
          </div>

          <div className="border-t pt-4">
            <h4 className="text-gray-900 mb-3">Détails de la réservation</h4>
            <div className="space-y-2">
              <div className="flex justify-between text-gray-700">
                <span>Date de début:</span>
                <span>{new Date(reservation.startTime).toLocaleString('fr-FR')}</span>
              </div>
              <div className="flex justify-between text-gray-700">
                <span>Date de fin:</span>
                <span>{new Date(reservation.endTime).toLocaleString('fr-FR')}</span>
              </div>
            </div>
          </div>

          <div className="border-t pt-4">
            <h4 className="text-gray-900 mb-3">Facturation</h4>
            <div className="space-y-2">
              <div className="flex justify-between text-gray-700">
                <span>Prix de la réservation:</span>
                <span>{(reservation.totalPrice || 0).toFixed(2)} €</span>
              </div>

              {reservation.penalty && reservation.penalty > 0 && (
                <div className="flex justify-between text-red-600">
                  <span>Pénalité (dépassement de {Math.round(reservation.overstayDuration || 0)} minutes):</span>
                  <span>+ {(reservation.penalty || 0).toFixed(2)} €</span>
                </div>
              )}

              <div className="border-t pt-2 mt-2">
                <div className="flex justify-between text-gray-900 font-semibold">
                  <span>Total:</span>
                  <span>{((reservation.totalPrice || 0) + (reservation.penalty || 0)).toFixed(2)} €</span>
                </div>
              </div>
            </div>
          </div>

          {reservation.status === 'completed' && (
            <div className="bg-green-50 rounded-lg p-4">
              <p className="text-green-900">
                ✓ Réservation terminée avec succès
              </p>
            </div>
          )}

          <div className="flex gap-3">
            <Button onClick={handleDownload} className="flex-1">
              <Download className="size-4 mr-2" />
              Télécharger la facture
            </Button>
            <Button variant="outline" onClick={() => onOpenChange(false)} className="flex-1">
              Fermer
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
