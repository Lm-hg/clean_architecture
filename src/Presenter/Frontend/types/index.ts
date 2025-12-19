export interface Parking {
  id: string;
  ownerId: string;
  title: string;
  description?: string;
  latitude: number;
  longitude: number;
  totalSpots: number;
  availableSpaces: number;
  pricePerHour: number;
  openingHours: Record<string, any>;
  createdAt: string;
  updatedAt: string;
}

export interface ParkingSchedule {
  dayOfWeek: number; // 0 = dimanche, 1 = lundi, etc.
  open: string;
  close: string;
}

export interface Reservation {
  id: string;
  parkingId: string;
  parkingName?: string;
  userId: string;
  userName?: string;
  userEmail?: string;
  startTime: string;
  endTime: string;
  status: 'pending' | 'confirmed' | 'active' | 'completed' | 'cancelled';
  totalPrice: number;
  penalty?: number;
  overstayDuration?: number;
  stationnementId?: string;
  entryTime?: string;
  exitTime?: string;
  createdAt: string;
  updatedAt: string;
}

export interface Stationnement {
  id: string;
  parkingId: string;
  parkingName?: string;
  userId: string;
  userName?: string;
  vehiclePlate: string;
  entryTime: string;
  exitTime?: string;
  reservationId?: string;
  abonnementId?: string;
  subscriptionId?: string;
  price?: {
    amount: number;
    currency: string;
  };
  totalPrice?: number;
  hasPenalty?: boolean;
  penaltyAmount?: number;
  penalty?: number;
  isAuthorized?: boolean;
  status: 'active' | 'completed' | 'violation';
  createdAt: string;
  updatedAt: string;
}

export interface SubscriptionType {
  id: string;
  parkingId: string;
  name: string;
  description?: string;
  benefits?: string[];  // Tableau d'avantages
  price: number;
  durationDays: number; // en jours
  duration?: number; // alias pour compatibilité
  timeSlots?: {
    dayOfWeek: number; // 0 = dimanche, 1 = lundi, etc.
    startTime: string;
    endTime: string;
  }[];
  isActive: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface Subscription {
  id: string;
  userId: string;
  userName?: string;
  subscriptionTypeId: string;
  subscriptionType?: SubscriptionType;
  startDate: string;
  endDate: string;
  status: 'active' | 'expired' | 'cancelled';
  price: number;
  createdAt: string;
  updatedAt: string;
}

export interface Invoice {
  invoice_number: string;
  date: string;
  reservation: {
    id: string;
    parking_id: string;
    start_time: string;
    end_time: string;
    status: string;
    duration_minutes: number;
  };
  customer: {
    id: string;
    name: string;
    email: string;
  };
  stationnement?: {
    entry_time: string;
    exit_time?: string;
    actual_duration_minutes?: number;
    has_penalty: boolean;
  };
  billing_details: {
    items: any[];
    subtotal: number;
    penalty_amount: number;
    tax_rate: number;
    tax_amount: number;
    total_ttc: number;
  };
  totals: {
    subtotal: number;
    penalty: number;
    tax_rate: number;
    tax_amount: number;
    total_ttc: number;
  };
  payment: {
    status: string;
    method: string;
    currency: string;
  };
  legal_mentions: {
    company_name: string;
    address: string;
    siret: string;
    tva_number: string;
    contact: string;
    mentions: string[];
  };
}

// Types pour les utilisateurs
export interface User {
  id: string;
  firstName: string;
  name: string;
  email: string;
  role: 'user' | 'parking_owner';
  createdAt: string;
  updatedAt: string;
}

export interface UserLocation {
  latitude: number;
  longitude: number;
}
