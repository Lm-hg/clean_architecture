// Configuration d'environnement pour le frontend
export const config = {
  api: {
    baseUrl: process.env.REACT_APP_API_BASE_URL || 'http://localhost:8000/api',
    timeout: 30000, // 30 secondes
  },
  auth: {
    tokenKey: 'auth_token',
    refreshTokenKey: 'refresh_token',
  },
  pagination: {
    defaultPageSize: 10,
    maxPageSize: 100,
  },
  maps: {
    defaultCenter: {
      latitude: 48.8566,
      longitude: 2.3522, // Paris
    },
    defaultZoom: 12,
  },
  parking: {
    searchRadius: 5, // km
    bookingAdvanceLimit: 30, // jours
  },
};

export default config;