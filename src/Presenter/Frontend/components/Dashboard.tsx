import { useState } from 'react';
import { Sidebar } from './Sidebar';
import { OverviewPage } from './OverviewPage';
import { ParkingsPage } from './ParkingsPage';
import { ParkingDetailsPage } from './ParkingDetailsPage';

interface User {
  id: string;
  firstName: string;
  name: string;
  email: string;
  role: string;
}

interface DashboardProps {
  onLogout: () => void;
  user: User | null;
}

export type PageType = 'overview' | 'parkings' | 'parking-details';

export function Dashboard({ onLogout, user }: DashboardProps) {
  const [currentPage, setCurrentPage] = useState<PageType>('overview');
  const [selectedParkingId, setSelectedParkingId] = useState<string | null>(null);

  const handleViewParkingDetails = (parkingId: string) => {
    setSelectedParkingId(parkingId);
    setCurrentPage('parking-details');
  };

  const handleBackToParkings = () => {
    setSelectedParkingId(null);
    setCurrentPage('parkings');
  };

  return (
    <div className="flex h-screen bg-gray-50">
      <Sidebar 
        currentPage={currentPage}
        user={user} 
        onNavigate={setCurrentPage}
        onLogout={onLogout}
      />
      
      <main className="flex-1 overflow-y-auto">
        {currentPage === 'overview' && <OverviewPage />}
        {currentPage === 'parkings' && (
          <ParkingsPage onViewDetails={handleViewParkingDetails} />
        )}
        {currentPage === 'parking-details' && selectedParkingId && (
          <ParkingDetailsPage 
            parkingId={selectedParkingId}
            onBack={handleBackToParkings}
          />
        )}
      </main>
    </div>
  );
}
