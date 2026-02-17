/* frontend/src/contexts/UserContext/UserContext.jsx */
import { createContext, useState, useContext, useEffect } from 'react';

const UserContext = createContext(null);

export const UserProvider = ({ children }) => {
    // Inicializar estado leyendo localStorage para persistencia
    const [user, setUser] = useState(() => {
        const storedUser = localStorage.getItem('user');
        return storedUser ? JSON.parse(storedUser) : null;
    });

    const login = (inputData) => {
        // Normalizamos la respuesta: Si viene dentro de una propiedad 'data' (común en Laravel/Axios), la extraemos.
        const data = (inputData.data && (inputData.data.paciente || inputData.data.usuario)) 
                     ? inputData.data 
                     : inputData;

        // Ahora 'data' debería ser { token: "...", usuario: {...}, paciente: {...} }
        
        // Guardamos todo el objeto 'data' en el estado para tener acceso a data.paciente, data.usuario, etc.
        const userData = data; 
        
        const token = data.token || data.access_token;

        setUser(userData);
        localStorage.setItem('user', JSON.stringify(userData));
        
        if (token) {
            localStorage.setItem('token', token);
        } else {
            console.warn("Advertencia: No se recibió token en la respuesta de login");
        }
    };

    const logout = () => {
        setUser(null);
        localStorage.removeItem('user');
        localStorage.removeItem('token');
        // Opcional: Redirigir
        window.location.href = '#/login';
    };

    const updateUser = (newPacienteData) => {
        setUser(prevUser => {
            if (!prevUser) return null;
            const updatedUser = {
                ...prevUser,
                paciente: {
                    ...prevUser.paciente,
                    ...newPacienteData
                }
            };
            localStorage.setItem('user', JSON.stringify(updatedUser));
            return updatedUser;
        });
    };

    return (
        <UserContext.Provider value={{ user, login, logout, updateUser }}>
            {children}
        </UserContext.Provider>
    );
};

export const useUser = () => useContext(UserContext);