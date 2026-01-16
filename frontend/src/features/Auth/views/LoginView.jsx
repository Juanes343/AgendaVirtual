import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { Calendar, FileText, Bell, Shield, Eye, EyeOff, Check, ChevronDown, User } from 'lucide-react';
import { useUser } from '../../../contexts/UserContext/UserContext';
import authService from '../services/authService';
import doctorImg from '../../../assets/images/doctor_illustration.png';
import logo from '../../../assets/images/sandi_virtual.png';

const documentTypes = [
  { value: "CC", label: "Cédula de Ciudadanía" },
  { value: "TI", label: "Tarjeta de Identidad" },
  { value: "CE", label: "Cédula de Extranjería" },
  { value: "PAS", label: "Pasaporte" },
];

export default function LoginView() {
    const { login } = useUser();
    const navigate = useNavigate();

    const [formData, setFormData] = useState({
        tipo_doc: 'CC',
        usuario: '',
        passwd: ''
    });
    const [isDropdownOpen, setIsDropdownOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    // Estados para Recuperación de Contraseña
    const [isRecoverModalOpen, setIsRecoverModalOpen] = useState(false);
    const [recoverData, setRecoverData] = useState({ tipo_doc: 'CC', usuario: '' });
    const [recoverLoading, setRecoverLoading] = useState(false);
    const [recoverMessage, setRecoverMessage] = useState(null); // { type: 'success' | 'error', text: '' }

    const selectedDocType = documentTypes.find(d => d.value === formData.tipo_doc) || documentTypes[0];

    // Manejo del formulario de recuperación
    const handleRecoverSubmit = async (e) => {
        e.preventDefault();
        setRecoverLoading(true);
        setRecoverMessage(null);
        try {
            const res = await authService.recoverPassword(recoverData);
            setRecoverMessage({ type: 'success', text: res.message });
        } catch (err) {
            setRecoverMessage({ type: 'error', text: err.response?.data?.message || 'Error al intentar recuperar contraseña.' });
        } finally {
            setRecoverLoading(false);
        }
    };

    const handleChange = (e) => {
        setFormData({
            ...formData,
            [e.target.name]: e.target.value
        });
    };

    const handleDocTypeSelect = (value) => {
        setFormData({ ...formData, tipo_doc: value });
        setIsDropdownOpen(false);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);

        try {
            const response = await authService.login({
                tipo_doc: formData.tipo_doc,
                usuario: formData.usuario,
                passwd: formData.passwd
            });

            if (response.success) {
                login(response.data);
                navigate('/home'); 
            }
        } catch (err) {
            console.error(err);
            setError(err.response?.data?.message || 'Error al iniciar sesión. Verifique sus credenciales.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-background flex items-center justify-center p-4 relative overflow-y-auto font-sans text-foreground">
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

            {/* Glowing orb effect */}
            <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/20 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute bottom-1/4 right-1/4 w-64 h-64 bg-accent/10 rounded-full blur-3xl pointer-events-none" />

            <div className="w-full max-w-6xl grid lg:grid-cols-2 gap-8 lg:gap-16 items-center relative z-10">
                {/* Left side - Branding */}
                <div className="space-y-6">
                   {/* Logo */}
                    <div className="flex items-center gap-3">
                        <img src={logo} alt="Logo" className="w-16 h-16 rounded-xl object-contain bg-primary/10 p-1" />
                        <div>
                            <h1 className="text-3xl font-bold text-foreground">SanDi•Med</h1>
                            <p className="text-base text-muted-foreground">Portal del Paciente</p>
                        </div>
                    </div>

                    {/* Tagline */}
                    <div className="space-y-4">
                        <h2 className="text-3xl font-bold text-foreground leading-tight text-balance">
                            Tu salud, <span className="text-primary">organizada</span> en un solo lugar
                        </h2>
                        <p className="text-base text-muted-foreground text-pretty">
                            Accede a tus citas médicas, resultados y recordatorios desde cualquier dispositivo.
                        </p>
                    </div>

                    {/* Features */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                                <Calendar className="w-4 h-4 text-primary" />
                            </div>
                            <span className="text-foreground">
                                <strong>Agenda</strong> y gestiona tus citas
                            </span>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                                <FileText className="w-4 h-4 text-primary" />
                            </div>
                            <span className="text-foreground">
                                <strong>Consulta</strong> tu historial médico
                            </span>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-8 rounded-full bg-accent/20 flex items-center justify-center">
                                <Bell className="w-4 h-4 text-accent" />
                            </div>
                            <span className="text-foreground">
                                <strong>Recibe</strong> recordatorios automáticos
                            </span>
                        </div>
                    </div>

                    {/* Footer: Manual & Doctor Image Side by Side */}
                    <div className="flex items-end justify-between pt-4 pr-4">
                        {/* Manual de Usuario Link - Left */}
                        <a 
                            href={`${import.meta.env.VITE_API_URL}/manual`} 
                            target="_blank"
                            rel="noopener noreferrer"
                            className="bg-card/30 backdrop-blur min-w-[120px] p-2 rounded-xl border border-white/5 flex items-center gap-3 group hover:bg-card/50 transition-all cursor-pointer"
                        >
                            <div className="relative group-hover:-translate-y-1 transition-transform duration-300">
                                <div className="absolute inset-0 bg-red-500/30 blur-md rounded-lg"></div>
                                <div className="flex items-center justify-center w-8 h-10 bg-gradient-to-br from-red-500 to-red-600 rounded shadow-lg relative border border-white/10">
                                    <span className="text-white text-[8px] font-bold">PDF</span>
                                    <div className="absolute top-0 right-0 border-t-[5px] border-r-[5px] border-t-white/30 border-r-transparent"></div>
                                </div>
                            </div>
                            <div className="flex flex-col">
                                <span className="text-[10px] font-bold text-muted-foreground uppercase tracking-wider group-hover:text-primary transition-colors">Manual de</span>
                                <span className="text-xs font-bold text-foreground">Usuario</span>
                            </div>
                        </a>

                        {/* Doctor illustration - Right (Moved as requested) */}
                        <div className="hidden lg:block relative w-32 h-32">
                             <div className="absolute bottom-0 right-0 w-full h-full bg-card/60 rounded-2xl border border-white/10 backdrop-blur-sm flex items-center justify-center overflow-hidden shadow-2xl">
                                <img src={doctorImg} alt="Doctor" className="w-full h-full object-cover opacity-90 scale-105" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Right side - Login form */}
                <div className="w-full max-w-md mx-auto lg:mx-0 lg:ml-auto">
                    <div className="bg-card/80 backdrop-blur-xl rounded-2xl border border-border/50 p-6 shadow-2xl">
                        <div className="space-y-4">
                            <div className="text-center space-y-1">
                                <h3 className="text-2xl font-bold text-foreground">Iniciar sesión</h3>
                                <p className="text-sm text-muted-foreground">
                                    Acceso seguro al <a href="#" className="text-primary hover:underline">portal del paciente</a>
                                </p>
                            </div>

                            {error && (
                                <div className="bg-destructive/10 border border-destructive/20 text-destructive text-sm p-3 rounded-lg">
                                    {error}
                                </div>
                            )}

                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Tipo de documento</label>
                                    <div className="relative">
                                        <button
                                            type="button"
                                            onClick={() => setIsDropdownOpen(!isDropdownOpen)}
                                            className="w-full flex items-center justify-between px-3 py-2 bg-input/50 border border-input rounded-lg text-sm text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all text-left"
                                        >
                                            <span className="block truncate">{selectedDocType.label}</span>
                                            <ChevronDown className="w-4 h-4 text-muted-foreground" />
                                        </button>

                                        {isDropdownOpen && (
                                            <div className="absolute z-50 w-full mt-1 bg-popover border border-border rounded-lg shadow-lg max-h-60 overflow-auto">
                                                {documentTypes.map((type) => (
                                                    <button
                                                        key={type.value}
                                                        type="button"
                                                        className={`w-full flex items-center justify-between px-3 py-2 text-sm text-left hover:bg-muted transition-colors ${
                                                            formData.tipo_doc === type.value ? "bg-muted font-medium text-primary" : "text-foreground"
                                                        }`}
                                                        onClick={() => handleDocTypeSelect(type.value)}
                                                    >
                                                        {type.label}
                                                        {formData.tipo_doc === type.value && <Check className="w-4 h-4" />}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-foreground">Número de documento</label>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <User className="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <input
                                            type="text"
                                            name="usuario"
                                            value={formData.usuario}
                                            onChange={handleChange}
                                            className="w-full pl-10 pr-3 py-2 bg-input/50 border border-input rounded-lg text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                                            placeholder="Ingresa tu número"
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <label className="text-sm font-medium text-foreground">Contraseña</label>
                                    </div>
                                    <div className="relative">
                                        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <Shield className="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <input
                                            type={showPassword ? "text" : "password"}
                                            name="passwd"
                                            value={formData.passwd}
                                            onChange={handleChange}
                                            className="w-full pl-10 pr-10 py-2 bg-input/50 border border-input rounded-lg text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                                            placeholder="••••••••"
                                            required
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword(!showPassword)}
                                            className="absolute inset-y-0 right-0 pr-3 flex items-center text-muted-foreground hover:text-foreground"
                                        >
                                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    disabled={loading}
                                    className="w-full bg-primary text-primary-foreground hover:bg-primary/90 py-2.5 rounded-lg font-medium shadow-lg shadow-primary/20 transition-all active:scale-[0.98] disabled:opacity-70 disabled:pointer-events-none flex justify-center items-center gap-2"
                                >
                                    {loading ? (
                                        <>
                                            <span className="w-4 h-4 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin"></span>
                                            Ingresando...
                                        </>
                                    ) : (
                                        "Ingresar"
                                    )}
                                </button>
                            </form>

                            <div className="space-y-3 pt-2">
                                <button
                                    onClick={() => setIsRecoverModalOpen(true)}
                                    className="w-full text-sm text-muted-foreground hover:text-primary transition-colors text-center"
                                >
                                    ¿Olvidaste tu contraseña?
                                </button>
                                <Link to="/register" className="block w-full text-sm text-foreground hover:text-primary transition-colors text-center font-medium border border-border rounded-lg py-2 hover:bg-muted/50">
                                    Crear cuenta nueva
                                </Link>
                            </div>

                            <div className="flex items-center justify-center gap-2 pt-4 border-t border-border">
                                <Shield className="w-4 h-4 text-accent" />
                                <span className="text-xs text-muted-foreground">Tus datos están protegidos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal de Recuperación */}
            {isRecoverModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
                    <div className="bg-card w-full max-w-md rounded-2xl shadow-2xl border border-border p-6 relative">
                        <button 
                            onClick={() => { setIsRecoverModalOpen(false); setRecoverMessage(null); }}
                            className="absolute top-4 right-4 text-muted-foreground hover:text-foreground"
                        >
                            <span className="sr-only">Cerrar</span>
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>

                        <div className="text-center mb-6">
                            <h3 className="text-xl font-bold text-foreground">Recuperar Contraseña</h3>
                            <p className="text-sm text-muted-foreground">Ingresa tus datos para recibir un enlace de restablecimiento.</p>
                        </div>

                        {recoverMessage && (
                            <div className={`mb-4 p-3 rounded-lg text-sm ${recoverMessage.type === 'success' ? 'bg-green-500/10 text-green-500 border border-green-500/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'}`}>
                                {recoverMessage.text}
                            </div>
                        )}

                        <form onSubmit={handleRecoverSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Tipo de documento</label>
                                <select 
                                    className="w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary outline-none"
                                    value={recoverData.tipo_doc}
                                    onChange={(e) => setRecoverData({...recoverData, tipo_doc: e.target.value})}
                                >
                                    {documentTypes.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">Número de documento</label>
                                <input
                                    type="text"
                                    className="w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary outline-none"
                                    placeholder="Ingresa tu número"
                                    value={recoverData.usuario}
                                    onChange={(e) => setRecoverData({...recoverData, usuario: e.target.value})}
                                    required
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={recoverLoading}
                                className="w-full bg-primary text-primary-foreground hover:bg-primary/90 py-2.5 rounded-lg font-medium transition-all disabled:opacity-70"
                            >
                                {recoverLoading ? 'Enviando...' : 'Enviar enlace'}
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
