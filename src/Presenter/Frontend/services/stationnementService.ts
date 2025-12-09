import { apiClient } from './apiClient';

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
  price?: {
    amount: number;
    currency: string;
  };
  hasPenalty?: boolean;
  penaltyAmount?: number;
  status: 'active' | 'completed' | 'violation';
  createdAt: string;
  updatedAt: string;
}

export interface StartStationnementRequest {
  parkingId: string;
  vehiclePlate: string;
  reservationId?: string;
}

export interface EndStationnementRequest {
  stationnementId: string;
}

export interface StationnementListFilters {
  status?: 'active' | 'completed' | 'violation';
  startDate?: string;
  endDate?: string;
  parkingId?: string;
  hasViolation?: boolean;
}

export interface StationnementStats {
  totalSessions: number;
  totalDuration: number; // en minutes
  totalRevenue: number;
  averageDuration: number;
  occupancyRate: number;
  violationsCount: number;
}

export class StationnementService {
  async getUserStationnements(filters?: StationnementListFilters): Promise<Stationnement[]> {
    const params = new URLSearchParams();
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined) {
          params.append(key, value.toString());
        }
      });
    }

    const queryString = params.toString();
    const endpoint = queryString ? `/user/stationnements?${queryString}` : '/user/stationnements';
    
    const response = await apiClient.get<{status: string, data: Stationnement[], message: string}>(endpoint);
    return response.data || [];
  }

  async getParkingStationnements(parkingId: string, filters?: StationnementListFilters): Promise<Stationnement[]> {
    const params = new URLSearchParams();
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined) {
          params.append(key, value.toString());
        }
      });
    }

    const queryString = params.toString();
    const endpoint = queryString ? `/owner/parkings/${parkingId}/stationnements?${queryString}` : `/owner/parkings/${parkingId}/stationnements`;
    
    const response = await apiClient.get<{status: string, data: Stationnement[], message: string}>(endpoint);
    return response.data || [];
  }

  async getStationnementById(id: string): Promise<Stationnement> {
    return apiClient.get<Stationnement>(`/stationnements/${id}`);
  }

  async startStationnement(data: StartStationnementRequest): Promise<Stationnement> {
    return apiClient.post<Stationnement>('/stationnements/start', data);
  }

  async endStationnement(data: EndStationnementRequest): Promise<Stationnement> {
    return apiClient.post<Stationnement>('/stationnements/end', data);
  }

  async getCurrentStationnement(userId?: string): Promise<Stationnement | null> {
    try {
      return await apiClient.get<Stationnement>('/user/current-stationnement');
    } catch (error) {
      // Si pas de stationnement actuel, retourner null
      return null;
    }
  }

  async getStationnementStats(parkingId?: string, startDate?: string, endDate?: string): Promise<StationnementStats> {
    const params = new URLSearchParams();
    
    if (parkingId) params.append('parkingId', parkingId);
    if (startDate) params.append('startDate', startDate);
    if (endDate) params.append('endDate', endDate);

    const queryString = params.toString();
    const endpoint = queryString ? `/owner/stationnements/stats?${queryString}` : '/owner/stationnements/stats';
    
    return apiClient.get<StationnementStats>(endpoint);
  }

  async getViolations(parkingId?: string): Promise<Stationnement[]> {
    const endpoint = parkingId ? `/owner/parkings/${parkingId}/violations` : '/owner/violations';
    return apiClient.get<Stationnement[]>(endpoint);
  }
}

export const stationnementService = new StationnementService();