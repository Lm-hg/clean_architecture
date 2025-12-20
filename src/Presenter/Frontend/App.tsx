import React, { useState, useEffect } from 'react';
import { LoginPage } from './components/LoginPage';
import { Dashboard } from './components/Dashboard';
import { UserDashboard } from './components/user/UserDashboard';
import { authService } from './services';

type UserType = 'parking_owner' | 'user' | null;

interface User {
  id: string;
  firstName: string;
  name: string;
  email: string;
  role: string;
}

export default function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [userType, setUserType] = useState<UserType>(null);
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Forcer la déconnexion au démarrage de l'application pour les tests
  // Retirer ceci en production
  // React.useEffect(() => {
  //   authService.forceLogout();
  // }, []);

  // Vérifier l'authentification au chargement
  useEffect(() => {
    const checkAuth = async () => {
      try {
        if (authService.isAuthenticated()) {
          const currentUser = await authService.getCurrentUser();
          setUser(currentUser);
          setUserType(currentUser.role as UserType);
          setIsAuthenticated(true);
        }
      } catch (error) {
        console.log('Auth check failed:', error);
        // Token invalide ou expiré - nettoyer sans reload pour éviter les boucles
        authService.clearToken();
        setIsAuthenticated(false);
        setUserType(null);
        setUser(null);
      } finally {
        setIsLoading(false);
      }
    };

    checkAuth();
  }, []);

  const handleLogin = ({ type, user: loggedUser }: { type: UserType; user: User }) => {
    setUserType(type);
    setUser(loggedUser);
    setIsAuthenticated(true);
  };

  const handleLogout = async () => {
    try {
      await authService.logout();
    } catch (error) {
      console.error('Erreur lors de la déconnexion:', error);
    }
    
    setIsAuthenticated(false);
    setUserType(null);
    setUser(null);
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-indigo-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Chargement...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <LoginPage onLogin={handleLogin} />;
  }

  if (userType === 'parking_owner') {
    return <Dashboard onLogout={handleLogout} user={user} />;
  }

  return <UserDashboard onLogout={handleLogout} user={user} />;
}
