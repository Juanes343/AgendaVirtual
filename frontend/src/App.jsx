import React from 'react';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';

import { UserProvider, useUser } from './contexts/UserContext/UserContext';
import LoginPage from './features/Auth/pages/LoginPage';
import RegisterPage from './features/Auth/pages/RegisterPage';
import DashboardView from './features/Home/views/DashboardView';

const Home = () => <DashboardView />;

const ProtectedRoute = ({ children }) => {
  const { user } = useUser();
  if (!user) return <Navigate to="/login" replace />;
  return children;
};

function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route
        path="/home"
        element={
          <ProtectedRoute>
            <Home />
          </ProtectedRoute>
        }
      />

      {/* Por defecto */}
      <Route path="/" element={<Navigate to="/login" replace />} />

      {/* Catch-all */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
}

function App() {
  return (
    <HashRouter>
      <UserProvider>
        <AppRoutes />
      </UserProvider>
    </HashRouter>
  );
}

export default App;