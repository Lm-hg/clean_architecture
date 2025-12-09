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
  status: 'pending' | 'active' | 'completed' | 'cancelled';
  totalPrice: number;
  createdAt: string;
  updatedAt: string;
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
    return apiClient.get<Reservation>(`/reservations/${id}`);
  }

  async createReservation(data: CreateReservationRequest): Promise<Reservation> {
    return apiClient.post<Reservation>('/reservations', data);
  }

  async updateReservation(data: UpdateReservationRequest): Promise<Reservation> {
    const { id, ...updateData } = data;
    return apiClient.put<Reservation>(`/reservations/${id}`, updateData);
  }

  async cancelReservation(id: string): Promise<void> {
    return apiClient.put<void>(`/reservations/${id}/cancel`);
  }

  async generateInvoice(reservationId: string): Promise<any> {
    return apiClient.get<any>(`/reservations/${reservationId}/invoice`);
  }

  async checkReservationPrice(data: CreateReservationRequest): Promise<{ price: number; duration: number }> {
    return apiClient.post<{ price: number; duration: number }>('/reservations/check-price', data);
  }
}

export const reservationService = new ReservationService();