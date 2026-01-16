import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { Shield, Eye, EyeOff, Lock, CheckCircle, AlertCircle } from 'lucide-react';
import authService from '../services/authService';
import logo from '../../../assets/images/sandi_virtual.png';

export default function ResetPasswordView() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get('token');

    const [passwords, setPasswords] = useState({
        password: '',
        password_confirmation: ''
    });
    const [showPassword, setShowPassword] = useState(false);
    const [loading, setLoading] = useState(false);
    const [message, setMessage] = useState(null); // { type: 'success' | 'error', text: '' }

    useEffect(() => {
        if (!token) {
            setMessage({ type: 'error', text: 'Token inválido o expirado.' });
        }
    }, [token]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setMessage(null);

        if (passwords.password !== passwords.password_confirmation) {
            setMessage({ type: 'error', text: 'Las contraseñas no coinciden.' });
            return;
        }

        if (passwords.password.length < 6) {
            setMessage({ type: 'error', text: 'La contraseña debe tener al menos 6 caracteres.' });
            return;
        }

        setLoading(true);
        try {
            await authService.resetPassword({
                token,
                password: passwords.password,
                password_confirmation: passwords.password_confirmation
            });
            setMessage({ type: 'success', text: 'Contraseña actualizada correctamente. Redirigiendo...' });
            setTimeout(() => navigate('/login'), 3000);
        } catch (err) {
            setMessage({ type: 'error', text: err.response?.data?.message || 'Error al restablecer la contraseña.' });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen w-full flex bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900 via-slate-900 to-black overflow-hidden relative">
            {/* Background elements similiar to LoginView */}
            <div className="absolute inset-0 w-full h-full">
                <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] rounded-full bg-blue-500/10 blur-[120px]" />
                <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] rounded-full bg-cyan-500/10 blur-[120px]" />
            </div>

            <div className="w-full h-full flex items-center justify-center p-4 relative z-10">
                <div className="w-full max-w-md">
                    <div className="bg-card/95 backdrop-blur-xl border border-white/10 shadow-2xl rounded-2xl overflow-hidden">
                        <div className="p-8">
                            {/* Header */}
                            <div className="text-center mb-8">
                                <div className="inline-flex p-3 rounded-2xl bg-primary/10 mb-4 ring-1 ring-white/10 shadow-lg shadow-primary/5">
                                    <img src={logo} alt="Logo" className="h-10 w-auto" />
                                </div>
                                <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-white via-blue-100 to-blue-200">
                                    Restablecer Contraseña
                                </h1>
                                <p className="text-sm text-muted-foreground mt-2">
                                    Ingresa tu nueva contraseña para acceder.
                                </p>
                            </div>

                            {message && (
                                <div className={`mb-6 p-4 rounded-xl flex items-start gap-3 border ${
                                    message.type === 'success' 
                                        ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-500' 
                                        : 'bg-red-500/10 border-red-500/20 text-red-500'
                                }`}>
                                    {message.type === 'success' ? (
                                        <CheckCircle className="w-5 h-5 shrink-0 mt-0.5" />
                                    ) : (
                                        <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                                    )}
                                    <span className="text-sm font-medium">{message.text}</span>
                                </div>
                            )}

                            {!token && !message && (
                                <div className="text-center text-red-400 mb-4">
                                    No se proporcionó un token válido.
                                </div>
                            )}

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground ml-1">Nueva Contraseña</label>
                                    <div className="relative group">
                                        <div className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground group-focus-within:text-primary transition-colors">
                                            <Lock className="w-5 h-5" />
                                        </div>
                                        <input
                                            type={showPassword ? "text" : "password"}
                                            className="w-full pl-10 pr-10 py-2.5 bg-input/50 border border-input rounded-xl text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                                            placeholder="••••••••"
                                            value={passwords.password}
                                            onChange={(e) => setPasswords({...passwords, password: e.target.value})}
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                                        >
                                            {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                                        </button>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground ml-1">Confirmar Contraseña</label>
                                    <div className="relative group">
                                        <div className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground group-focus-within:text-primary transition-colors">
                                            <Lock className="w-5 h-5" />
                                        </div>
                                        <input
                                            type={showPassword ? "text" : "password"}
                                            className="w-full pl-10 pr-10 py-2.5 bg-input/50 border border-input rounded-xl text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all outline-none"
                                            placeholder="••••••••"
                                            value={passwords.password_confirmation}
                                            onChange={(e) => setPasswords({...passwords, password_confirmation: e.target.value})}
                                            required
                                        />
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    disabled={loading || !token || !!(message && message.type === 'success')}
                                    className="w-full bg-gradient-to-r from-primary to-blue-600 hover:from-primary/90 hover:to-blue-600/90 text-white py-2.5 rounded-xl font-medium shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all disabled:opacity-70 disabled:cursor-not-allowed mt-2"
                                >
                                    {loading ? 'Procesando...' : 'Restablecer Contraseña'}
                                </button>
                            </form>

                            <div className="flex items-center justify-center gap-2 pt-6 mt-4 border-t border-border">
                                <Shield className="w-4 h-4 text-accent" />
                                <Link to="/login" className="text-sm text-muted-foreground hover:text-primary transition-colors">
                                    Volver al inicio de sesión
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}