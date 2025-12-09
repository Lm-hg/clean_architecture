import { Dialog, DialogContent, DialogHeader, DialogTitle } from '../ui/dialog';
import { Button } from '../ui/button';
import { Download, FileText } from 'lucide-react';
import { toast } from 'sonner';

interface InvoiceDialogProps {
  reservationId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function InvoiceDialog({ reservationId, open, onOpenChange }: InvoiceDialogProps) {
  const reservation = mockReservations.find((r) => r.id === reservationId);
  const invoice = mockInvoices.find((i) => i.reservationId === reservationId);

  if (!reservation) return null;

  const handleDownload = () => {
    toast.success('Facture téléchargée avec succès !');
  };

  const calculateTotalPrice = () => {
    const basePrice = reservation.price;
    const penalty = reservation.penalty || 0;
    const overstayPrice = reservation.overstayDuration 
      ? (reservation.overstayDuration / 60) * 4.0 
      : 0;
    return basePrice + penalty + overstayPrice;
  };

  const totalPrice = calculateTotalPrice();

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Facture de réservation</DialogTitle>
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
              <p className="text-indigo-900">Facture #{invoice?.id || `INV-${reservation.id}`}</p>
              <p className="text-indigo-700">
                Date: {invoice?.createdAt 
                  ? new Date(invoice.createdAt).toLocaleDateString('fr-FR')
                  : new Date().toLocaleDateString('fr-FR')}
              </p>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-6">
            <div>
              <h4 className="text-gray-900 mb-2">Client</h4>
              <p className="text-gray-700">Utilisateur</p>
              <p className="text-gray-600">user@email.com</p>
            </div>

            <div>
              <h4 className="text-gray-900 mb-2">Parking</h4>
              <p className="text-gray-700">{reservation.parkingName}</p>
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
              {reservation.entryTime && (
                <div className="flex justify-between text-gray-700">
                  <span>Heure d'entrée:</span>
                  <span>{new Date(reservation.entryTime).toLocaleString('fr-FR')}</span>
                </div>
              )}
              {reservation.exitTime && (
                <div className="flex justify-between text-gray-700">
                  <span>Heure de sortie:</span>
                  <span>{new Date(reservation.exitTime).toLocaleString('fr-FR')}</span>
                </div>
              )}
            </div>
          </div>

          <div className="border-t pt-4">
            <h4 className="text-gray-900 mb-3">Facturation</h4>
            <div className="space-y-2">
              <div className="flex justify-between text-gray-700">
                <span>Prix de la réservation:</span>
                <span>{reservation.price.toFixed(2)} €</span>
              </div>
              
              {reservation.penalty && (
                <>
                  <div className="flex justify-between text-red-600">
                    <span>Pénalité de dépassement:</span>
                    <span>{reservation.penalty.toFixed(2)} €</span>
                  </div>
                  {reservation.overstayDuration && (
                    <div className="flex justify-between text-gray-600">
                      <span className="text-sm">Temps additionnel ({reservation.overstayDuration} min):</span>
                      <span className="text-sm">
                        {((reservation.overstayDuration / 60) * 4.0).toFixed(2)} €
                      </span>
                    </div>
                  )}
                </>
              )}

              <div className="border-t pt-2 mt-2">
                <div className="flex justify-between text-gray-900">
                  <span>Total:</span>
                  <span>{totalPrice.toFixed(2)} €</span>
                </div>
              </div>
            </div>
          </div>

          {reservation.penalty && (
            <div className="bg-red-50 rounded-lg p-4">
              <p className="text-red-900">
                ⚠️ Une pénalité de 20€ a été appliquée pour dépassement du créneau de réservation.
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
