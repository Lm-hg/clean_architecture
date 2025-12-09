import { apiClient } from './apiClient';

export interface SubscriptionType {
  id: string;
  parkingId: string;
  name: string;
  description?: string;
  price: number;
  duration: number; // en jours
  timeSlots: {
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

export interface CreateSubscriptionTypeRequest {
  parkingId: string;
  name: string;
  description?: string;
  price: number;
  duration: number; // en jours
  timeSlots: {
    dayOfWeek: number;
    startTime: string;
    endTime: string;
  }[];
}

export interface CreateSubscriptionRequest {
  subscriptionTypeId: string;
  startDate?: string; // Si non fourni, commence immédiatement
}

export interface SubscriptionListFilters {
  status?: 'active' | 'expired' | 'cancelled';
  parkingId?: string;
  startDate?: string;
  endDate?: string;
}

export class SubscriptionService {
  // Gestion des types d'abonnement (parking owners)
  async getSubscriptionTypes(parkingId: string): Promise<SubscriptionType[]> {
    const response = await apiClient.get<{status: string, data: SubscriptionType[], message: string}>(`/owner/parkings/${parkingId}/subscription-types`);
    return response.data || [];
  }

  async createSubscriptionType(parkingId: string, data: CreateSubscriptionTypeRequest): Promise<SubscriptionType> {
    const response = await apiClient.post<{status: string, data: SubscriptionType, message: string}>(`/owner/parkings/${parkingId}/subscription-types`, data);
    return response.data;
  }

  async updateSubscriptionType(parkingId: string, id: string, data: Partial<CreateSubscriptionTypeRequest>): Promise<SubscriptionType> {
    return apiClient.put<SubscriptionType>(`/owner/parkings/${parkingId}/subscription-types/${id}`, data);
  }

  async deleteSubscriptionType(parkingId: string, id: string): Promise<void> {
    return apiClient.delete<void>(`/owner/parkings/${parkingId}/subscription-types/${id}`);
  }

  async toggleSubscriptionType(parkingId: string, id: string): Promise<SubscriptionType> {
    return apiClient.put<SubscriptionType>(`/owner/parkings/${parkingId}/subscription-types/${id}/toggle`);
  }

  // Gestion des abonnements utilisateurs
  async getAvailableSubscriptionTypes(parkingId: string): Promise<SubscriptionType[]> {
    return apiClient.get<SubscriptionType[]>(`/parkings/${parkingId}/subscription-types`);
  }

  async getUserSubscriptions(filters?: SubscriptionListFilters): Promise<Subscription[]> {
    const params = new URLSearchParams();
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined) {
          params.append(key, value.toString());
        }
      });
    }

    const queryString = params.toString();
    const endpoint = queryString ? `/user/subscriptions?${queryString}` : '/user/subscriptions';
    
    const response = await apiClient.get<{status: string, data: Subscription[], message: string}>(endpoint);
    return response.data;
  }

  async getParkingSubscriptions(parkingId: string, filters?: SubscriptionListFilters): Promise<Subscription[]> {
    const params = new URLSearchParams();
    
    if (filters) {
      Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined) {
          params.append(key, value.toString());
        }
      });
    }

    const queryString = params.toString();
    const endpoint = queryString ? `/owner/parkings/${parkingId}/subscriptions?${queryString}` : `/owner/parkings/${parkingId}/subscriptions`;
    
    const response = await apiClient.get<{status: string, data: Subscription[], message: string}>(endpoint);
    return response.data;
  }

  async createSubscription(data: CreateSubscriptionRequest): Promise<Subscription> {
    const response = await apiClient.post<{status: string, data: Subscription, message: string}>('/user/subscriptions', data);
    return response.data;
  }

  async getSubscription(id: string): Promise<Subscription> {
    const response = await apiClient.get<{status: string, data: Subscription, message: string}>(`/subscriptions/${id}`);
    return response.data;
  }

  async cancelSubscription(id: string): Promise<void> {
    return apiClient.put<void>(`/user/subscriptions/${id}/cancel`);
  }

  async checkSubscriptionAvailability(subscriptionTypeId: string, startDate?: string): Promise<{ available: boolean; message?: string }> {
    const params = new URLSearchParams();
    if (startDate) params.append('startDate', startDate);
    
    const queryString = params.toString();
    const endpoint = queryString ? `/subscription-types/${subscriptionTypeId}/check-availability?${queryString}` : `/subscription-types/${subscriptionTypeId}/check-availability`;
    
    return apiClient.get<{ available: boolean; message?: string }>(endpoint);
  }
}

export const subscriptionService = new SubscriptionService();