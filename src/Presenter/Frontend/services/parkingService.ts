import { apiClient } from './apiClient';

export interface Parking {
  id: string;
  ownerId: string;
  title: string;
  description?: string;
  address: {
    street: string;
    city: string;
    postalCode: string;
    country: string;
  };
  coordinates: {
    latitude: number;
    longitude: number;
  };
  totalSpots: number;
  availableSpaces: number;
  tarifs: {
    hourly: number;
    daily?: number;
    monthly?: number;
  };
  openingHours: {
    monday?: { open: string; close: string; };
    tuesday?: { open: string; close: string; };
    wednesday?: { open: string; close: string; };
    thursday?: { open: string; close: string; };
    friday?: { open: string; close: string; };
    saturday?: { open: string; close: string; };
    sunday?: { open: string; close: string; };
  };
  isAlwaysOpen?: boolean;
  createdAt: string;
  updatedAt: string;
}

export interface CreateParkingRequest {
  title: string;
  description?: string;
  address: {
    street: string;
    city: string;
    postalCode: string;
    country: string;
  };
  coordinates: {
    latitude: number;
    longitude: number;
  };
  totalSpots: number;
  tarifs: {
    hourly: number;
    daily?: number;
    monthly?: number;
  };
  openingHours: {
    monday?: { open: string; close: string; };
    tuesday?: { open: string; close: string; };
    wednesday?: { open: string; close: string; };
    thursday?: { open: string; close: string; };
    friday?: { open: string; close: string; };
    saturday?: { open: string; close: string; };
    sunday?: { open: string; close: string; };
  };
  isAlwaysOpen?: boolean;
}

export interface UpdateParkingRequest extends Partial<CreateParkingRequest> {
  id: string;
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
  async getAllParkings(): Promise<Parking[]> {
    return apiClient.get<Parking[]>('/parkings');
  }

  async searchParkings(criteria: SearchParkingsRequest): Promise<Parking[]> {
    const params = new URLSearchParams();
    
    Object.entries(criteria).forEach(([key, value]) => {
      if (value !== undefined) {
        params.append(key, value.toString());
      }
    });

    const queryString = params.toString();
    const endpoint = queryString ? `/parkings/search?${queryString}` : '/parkings/search';
    
    return apiClient.get<Parking[]>(endpoint);
  }

  async getParkingById(id: string): Promise<Parking> {
    const response = await apiClient.get<{status: string, data: Parking, message: string}>(`/parkings/${id}`);
    return response.data;
  }

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
    return apiClient.get<{ available: boolean; availableSpaces: number }>(
      `/parkings/${id}/availability?startTime=${encodeURIComponent(startTime)}&endTime=${encodeURIComponent(endTime)}`
    );
  }
}

export const parkingService = new ParkingService();