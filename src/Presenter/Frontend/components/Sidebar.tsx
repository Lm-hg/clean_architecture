import { LayoutDashboard, ParkingSquare, LogOut } from 'lucide-react';
import { Button } from './ui/button';
import { PageType } from './Dashboard';

interface SidebarProps {
  currentPage: PageType;
  onNavigate: (page: PageType) => void;
  onLogout: () => void;
}

export function Sidebar({ currentPage, onNavigate, onLogout }: SidebarProps) {
  const menuItems = [
    { id: 'overview' as PageType, label: 'Vue d\'ensemble', icon: LayoutDashboard },
    { id: 'parkings' as PageType, label: 'Mes Parkings', icon: ParkingSquare },
  ];

  return (
    <div className="w-64 bg-white border-r border-gray-200 flex flex-col">
      <div className="p-6 border-b border-gray-200">
        <div className="flex items-center">
          <div className="bg-indigo-600 p-2 rounded-lg">
            <ParkingSquare className="size-6 text-white" />
          </div>
          <span className="ml-3 text-indigo-900">ParkShare</span>
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
