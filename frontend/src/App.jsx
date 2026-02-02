import React from 'react';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';

import { UserProvider, useUser } from './contexts/UserContext/UserContext';
import LoginPage from './features/Auth/pages/LoginPage';
import RegisterPage from './features/Auth/pages/RegisterPage';
import ResetPasswordView from './features/Auth/views/ResetPasswordView';
import ActivateAccountView from './features/Auth/views/ActivateAccountView';
import DashboardView from './features/Home/views/DashboardView';
import AdminSettingsView from './features/Admin/views/AdminSettingsView';

const Home = () => <DashboardView />;
const AdminDashboard = () => <AdminSettingsView />;

const ProtectedRoute = ({ children, adminOnly = false }) => {
  const { user } = useUser();
  if (!user) return <Navigate to="/login" replace />;
  if (adminOnly && !user.usuario?.sw_admin) return <Navigate to="/home" replace />;
  return children;
};

function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/reset-password" element={<ResetPasswordView />} />
      <Route path="/activar-cuenta" element={<ActivateAccountView />} />
      <Route
        path="/home"
        element={
          <ProtectedRoute>
            <Home />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/dashboard"
        element={
          <ProtectedRoute adminOnly={true}>
            <AdminDashboard />
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