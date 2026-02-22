import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { CheckCircle2, XCircle, Loader2, ArrowRight, ShieldCheck } from 'lucide-react';
import authService from '../services/authService';
import logo from '../../../assets/images/sandi_virtual.png';

export default function ActivateAccountView() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get('token');
    
    const [status, setStatus] = useState('loading'); // loading, success, error
    const [message, setMessage] = useState('');

    useEffect(() => {
        const activate = async () => {
            if (!token) {
                setStatus('error');
                setMessage('Token de activación no encontrado o inválido.');
                return;
            }

            try {
                const response = await authService.activateAccount(token);
                if (response.success) {
                    setStatus('success');
                    setMessage(response.message || '¡Cuenta activada exitosamente!');
                } else {
                    setStatus('error');
                    setMessage(response.message || 'No se pudo activar la cuenta.');
                }
            } catch (error) {
                console.error('Error activating account:', error);
                setStatus('error');
                setMessage(error.response?.data?.message || 'Error en el servidor al activar la cuenta.');
            }
        };

        activate();
    }, [token]);

    return (
        <div className="min-h-screen bg-background flex items-center justify-center p-4 relative overflow-hidden font-sans text-foreground">
            {/* Background pattern */}
            <div className="absolute inset-0 opacity-10 pointer-events-none">
                <svg className="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" strokeWidth="0.5" className="text-primary" />
                        </pattern>
                    </defs>
                    <rect width="100" height="100" fill="url(#grid)" />
                </svg>
            </div>

            <div className="w-full max-w-md relative z-10">
                <div className="bg-card/80 backdrop-blur-xl rounded-2xl border border-border/50 p-8 shadow-2xl text-center">
                    <div className="flex justify-center mb-6">
                        <img src={logo} alt="Logo" className="w-16 h-16 rounded-xl object-contain bg-primary/10 p-1" />
                    </div>

                    <h2 className="text-2xl font-bold text-foreground mb-6">Activación de Cuenta</h2>

                    {status === 'loading' && (
                        <div className="space-y-4 py-8 animate-in fade-in duration-500">
                            <div className="flex justify-center">
                                <Loader2 className="w-12 h-12 text-primary animate-spin" />
                            </div>
                            <p className="text-muted-foreground">Verificando tu token de seguridad...</p>
                        </div>
                    )}

                    {status === 'success' && (
                        <div className="space-y-6 py-4 animate-in zoom-in-95 duration-500">
                            <div className="flex justify-center">
                                <div className="p-3 bg-green-500/10 rounded-full">
                                    <CheckCircle2 className="w-16 h-16 text-green-500" />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <h3 className="text-xl font-semibold text-foreground">¡Todo listo!</h3>
                                <p className="text-muted-foreground">{message}</p>
                            </div>
                            <button
                                onClick={() => navigate('/login')}
                                className="w-full bg-primary text-primary-foreground hover:bg-primary/90 py-3 rounded-xl font-medium shadow-lg shadow-primary/20 transition-all active:scale-[0.98] flex items-center justify-center gap-2"
                            >
                                Iniciar Sesión ahora
                                <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    )}

                    {status === 'error' && (
                        <div className="space-y-6 py-4 animate-in zoom-in-95 duration-500">
                            <div className="flex justify-center">
                                <div className="p-3 bg-destructive/10 rounded-full">
                                    <XCircle className="w-16 h-16 text-destructive" />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <h3 className="text-xl font-semibold text-foreground">Oops, algo salió mal</h3>
                                <p className="text-destructive/80">{message}</p>
                            </div>
                            <p className="text-xs text-muted-foreground px-4">
                                El enlace de activación pudo haber expirado (24h) o ya fue utilizado.
                            </p>
                            <div className="pt-2">
                                <Link to="/login" className="text-sm font-medium text-primary hover:underline flex items-center justify-center gap-1">
                                    Regresar al inicio
                                </Link>
                            </div>
                        </div>
                    )}

                    <div className="mt-8 pt-6 border-t border-border/50">
                        <div className="flex items-center justify-center gap-2 text-xs text-muted-foreground">
                            <ShieldCheck className="w-3 h-3" />
                            <span>Sistema de Seguridad {import.meta.env.VITE_APP_TITLE || "SanDi•Med"}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}