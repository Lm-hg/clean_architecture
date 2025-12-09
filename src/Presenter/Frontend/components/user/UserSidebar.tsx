import { Search, Calendar, Car, CreditCard, LogOut, ParkingSquare } from 'lucide-react';
import { Button } from '../ui/button';
import { UserPageType } from './UserDashboard';

interface User {
  id: string;
  firstName: string;
  name: string;
  email: string;
  role: string;
}

interface UserSidebarProps {
  currentPage: UserPageType;
  onNavigate: (page: UserPageType) => void;
  onLogout: () => void;
  user: User | null;
}

export function UserSidebar({ currentPage, onNavigate, onLogout, user }: UserSidebarProps) {
  const menuItems = [
    { id: 'search' as UserPageType, label: 'Rechercher', icon: Search },
    { id: 'reservations' as UserPageType, label: 'Mes réservations', icon: Calendar },
    { id: 'stationnements' as UserPageType, label: 'Mes stationnements', icon: Car },
    { id: 'subscriptions' as UserPageType, label: 'Mes abonnements', icon: CreditCard },
  ];

  const displayName = user ? `${user.firstName} ${user.name}` : 'Utilisateur';

  return (
    <div className="w-64 bg-white border-r border-gray-200 flex flex-col">
      <div className="p-6 border-b border-gray-200">
        <div className="flex items-center mb-4">
          <div className="bg-indigo-600 p-2 rounded-lg">
            <ParkingSquare className="size-6 text-white" />
          </div>
          <span className="ml-3 text-indigo-900">ParkShare</span>
        </div>
        <div className="bg-gray-50 rounded-lg p-3">
          <p className="text-gray-500">Bienvenue</p>
          <p className="text-gray-900">{displayName}</p>
          {user && <p className="text-sm text-gray-600">{user.email}</p>}
        </div>
      </div>

      <nav className="flex-1 p-4">
        <div className="space-y-1">
          {menuItems.map((item) => {
            const Icon = item.icon;
            const isActive = currentPage === item.id;
            
            return (
              <button
                key={item.id}
                onClick={() => onNavigate(item.id)}
                className={`w-full flex items-center px-4 py-3 rounded-lg transition-colors ${
                  isActive
                    ? 'bg-indigo-50 text-indigo-600'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
              >
                <Icon className="size-5 mr-3" />
                {item.label}
              </button>
            );
          })}
        </div>
      </nav>

      <div className="p-4 border-t border-gray-200">
        <Button
          variant="ghost"
          className="w-full justify-start"
          onClick={onLogout}
        >
          <LogOut className="size-5 mr-3" />
          Déconnexion
        </Button>
      </div>
    </div>
  );
}
