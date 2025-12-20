import { apiClient } from './apiClient';

export interface LoginRequest {
  email: string;
  password: string;
}

export interface RegisterRequest {
  email: string;
  password: string;
  firstName: string;
  name: string;
  role?: 'user' | 'parking_owner';
}

export interface AuthResponse {
  status: string;
  data: {
    token: string;
    user: {
      id: string;
      firstName: string;
      name: string;
      email: string;
      role: string;
    };
  };
  message: string;
}

export class AuthService {
  async login(credentials: LoginRequest): Promise<{token: string, user: any}> {
    const response = await apiClient.post<AuthResponse>('/auth/login', credentials);
    
    // Stocker le token après connexion réussie
    apiClient.setToken(response.data.token);
    
    return {
      token: response.data.token,
      user: response.data.user
    };
  }

  async register(data: RegisterRequest): Promise<{token: string, user: any}> {
    const response = await apiClient.post<AuthResponse>('/auth/register', data);
    
    // Stocker le token après inscription réussie
    apiClient.setToken(response.data.token);
    
    return {
      token: response.data.token,
      user: response.data.user
    };
  }

  async logout(): Promise<void> {
    try {
      await apiClient.post('/auth/logout');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      this.clearToken();
      apiClient.clearToken();
    }
  }

  async getCurrentUser(): Promise<any> {
    const response = await apiClient.get<AuthResponse>('/auth/me');
    return response.data.user;
  }

  isAuthenticated(): boolean {
    const token = localStorage.getItem('auth_token');
    if (!token) return false;
    
    // Vérifier si le token n'est pas expiré (optionnel)
    try {
      // Pour l'instant, on fait une vérification simple
      // En production, on devrait décoder le JWT et vérifier l'expiration
      return token.length > 0;
    } catch {
      return false;
    }
  }

  getStoredToken(): string | null {
    return localStorage.getItem('auth_token');
  }

  forceLogout(): void {
    this.clearToken();
    // Éviter window.location.reload() qui peut causer des boucles
  }

  clearToken(): void {
    localStorage.removeItem('auth_token');
    sessionStorage.clear();
  }
}

export const authService = new AuthService();