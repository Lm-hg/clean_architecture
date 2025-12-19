import { apiClient } from './apiClient';

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
  isAlwaysOpen?: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface CreateParkingRequest {
  title: string;
  description?: string;
  coordinates: {
    latitude: number;
    longitude: number;
  };
  totalSpots: number;
  tarifs: {
    hourly: number;
  };
  openingHours: Record<string, any>;
}

export interface UpdateParkingRequest {
  id: string;
  title?: string;
  description?: string;
  latitude?: number;
  longitude?: number;
  totalSpots?: number;
  pricePerHour?: number;
}

export interface SearchParkingsRequest {
  latitude?: number;
  longitude?: number;
  radius?: number; // en km
  startTime?: string;
  endTime?: string;
  maxPrice?: number;
}

export class ParkingService {
  // Pour utilisateurs: lister tous les parkings disponibles
  async getAvailableParkings(): Promise<Parking[]> {
    const response = await apiClient.get<{status: string, data: Parking[], message: string}>('/parkings');
    return response.data;
  }

  async searchParkings(criteria: SearchParkingsRequest): Promise<Parking[]> {
    const params = new URLSearchParams();
    
    Object.entries(criteria).forEach(([key, value]) => {
      if (value !== undefined) {
        params.append(key, value.toString());
      }
    });

    const queryString = params.toString();
    const endpoint = queryString ? `/parkings/search?${queryString}` : '/parkings';
    
    return apiClient.get<Parking[]>(endpoint);
  }

  async getParkingById(id: string): Promise<Parking> {
    const response = await apiClient.get<{status: string, data: Parking, message: string}>(`/parkings/${id}`);
    return response.data;
  }

  // Pour propriétaires: lister MES parkings
  async getMyParkings(): Promise<Parking[]> {
    const response = await apiClient.get<{status: string, data: Parking[], message: string}>('/owner/parkings');
    return response.data;
  }

  async createParking(data: CreateParkingRequest): Promise<Parking> {
    const response = await apiClient.post<{status: string, data: Parking, message: string}>('/owner/parkings', data);
    return response.data;
  }

  async updateParking(data: UpdateParkingRequest): Promise<Parking> {
    const { id, ...updateData } = data;
    const response = await apiClient.put<{status: string, data: Parking, message: string}>(`/owner/parkings/${id}`, updateData);
    return response.data;
  }

  async deleteParking(id: string): Promise<void> {
    return apiClient.delete<void>(`/owner/parkings/${id}`);
  }

  async getParkingAvailability(id: string, startTime: string, endTime: string): Promise<{ available: boolean; availableSpaces: number }> {
    const response = await apiClient.get<{status: string, data: { available: boolean; availableSpaces: number }, message: string}>(
      `/parkings/${id}/availability?startTime=${encodeURIComponent(startTime)}&endTime=${encodeURIComponent(endTime)}`
    );
    return response.data;
  }
}

export const parkingService = new ParkingService();