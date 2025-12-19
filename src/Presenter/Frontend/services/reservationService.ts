import { apiClient } from './apiClient';

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
  createdAt: string;
  updatedAt: string;
  stationnementId?: string;
  entryTime?: string;
  exitTime?: string;
  penalty?: number;
  overstayDuration?: number;
}

export interface CreateReservationRequest {
  parkingId: string;
  startTime: string;
  endTime: string;
}

export interface UpdateReservationRequest {
  id: string;
  startTime?: string;
  endTime?: string;
  status?: 'active' | 'cancelled';
}

export interface ReservationListFilters {
  status?: 'pending' | 'active' | 'completed' | 'cancelled';
  startDate?: string;
  endDate?: string;
  parkingId?: string;
}

export class ReservationService {
  async getUserReservations(filters?: ReservationListFilters): Promise<Reservation[]> {
    const params = new URLSearchParams();
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined) {
          params.append(key, value.toString());
        }
      });
    }

    const queryString = params.toString();
    const endpoint = queryString ? `/user/reservations?${queryString}` : '/user/reservations';
    
    const response = await apiClient.get<{status: string, data: Reservation[], message: string}>(endpoint);
    return response.data || [];
  }

  // Alias pour compatibilité avec OverviewPage
  async getMyReservations(filters?: ReservationListFilters): Promise<Reservation[]> {
    return this.getUserReservations(filters);
  }

  async getParkingReservations(parkingId: string, filters?: ReservationListFilters): Promise<Reservation[]> {
    const params = new URLSearchParams();
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined) {
          params.append(key, value.toString());
        }
      });
    }

    const queryString = params.toString();
    const endpoint = queryString ? `/owner/parkings/${parkingId}/reservations?${queryString}` : `/owner/parkings/${parkingId}/reservations`;
    
    const response = await apiClient.get<{status: string, data: Reservation[], message: string}>(endpoint);
    return response.data || [];
  }

  async getReservationById(id: string): Promise<Reservation> {
    const response = await apiClient.get<{status: string, data: Reservation, message: string}>(`/reservations/${id}`);
    return response.data;
  }

  async createReservation(data: CreateReservationRequest): Promise<Reservation> {
    const response = await apiClient.post<{status: string, data: Reservation, message: string}>('/reservations', data);
    return response.data;
  }

  async updateReservation(data: UpdateReservationRequest): Promise<Reservation> {
    const { id, ...updateData } = data;
    const response = await apiClient.put<{status: string, data: Reservation, message: string}>(`/reservations/${id}`, updateData);
    return response.data;
  }

  async cancelReservation(id: string): Promise<void> {
    return apiClient.put<void>(`/reservations/${id}/cancel`);
  }

  async confirmReservation(id: string): Promise<Reservation> {
    const response = await apiClient.put<{status: string, data: Reservation, message: string}>(`/owner/reservations/${id}/confirm`);
    return response.data;
  }

  async generateInvoice(reservationId: string): Promise<any> {
    const response = await apiClient.get<{status: string, data: any, message: string}>(`/reservations/${reservationId}/invoice`);
    return response.data;
  }

  async checkReservationPrice(data: CreateReservationRequest): Promise<{ price: number; duration: number }> {
    const response = await apiClient.post<{status: string, data: { price: number; duration: number }, message: string}>('/reservations/check-price', data);
    return response.data;
  }
}

export const reservationService = new ReservationService();