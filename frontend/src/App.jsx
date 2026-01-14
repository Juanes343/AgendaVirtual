import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { UserProvider, useUser } from './contexts/UserContext/UserContext';
import LoginPage from './features/Auth/pages/LoginPage';
import RegisterPage from './features/Auth/pages/RegisterPage';

import DashboardView from './features/Home/views/DashboardView';

const Home = () => {
    return <DashboardView />;
};

// Wrapper para proteger rutas
const ProtectedRoute = ({ children }) => {
    const { user } = useUser();
    if (!user) {
        return <Navigate to="/login" replace />;
    }
    return children;
};

function App() {
  return (
    <UserProvider>
        <Router>
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
                <Route path="/" element={<Navigate to="/login" replace />} />
            </Routes>
        </Router>
    </UserProvider>
  );
}

export default App;

