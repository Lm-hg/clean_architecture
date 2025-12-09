import { useState } from 'react';
import { UserSidebar } from './UserSidebar';
import { SearchParkingPage } from './SearchParkingPage';
import { MyReservationsPage } from './MyReservationsPage';
import { MyStationnements } from './MyStationnements';
import { MySubscriptionsPage } from './MySubscriptionsPage';

interface User {
  id: string;
  firstName: string;
  name: string;
  email: string;
  role: string;
}

interface UserDashboardProps {
  onLogout: () => void;
  user: User | null;
}

export type UserPageType = 'search' | 'reservations' | 'stationnements' | 'subscriptions' | 'parking-details';

export function UserDashboard({ onLogout, user }: UserDashboardProps) {
  const [currentPage, setCurrentPage] = useState<UserPageType>('search');
  const [selectedParkingId, setSelectedParkingId] = useState<string | null>(null);

  const handleViewParkingDetails = (parkingId: string) => {
    setSelectedParkingId(parkingId);
    setCurrentPage('parking-details');
  };

  return (
    <div className="flex h-screen bg-gray-50">
      <UserSidebar 
        currentPage={currentPage} 
        onNavigate={setCurrentPage} 
        onLogout={onLogout}
        user={user}
      />      <main className="flex-1 overflow-y-auto">
        {currentPage === 'search' && (
          <SearchParkingPage onViewDetails={handleViewParkingDetails} />
        )}
        {currentPage === 'reservations' && <MyReservationsPage />}
        {currentPage === 'stationnements' && <MyStationnements />}
        {currentPage === 'subscriptions' && <MySubscriptionsPage />}
      </main>
    </div>
  );
}
