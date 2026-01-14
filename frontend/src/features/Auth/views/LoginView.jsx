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

    const selectedDocType = documentTypes.find(d => d.value === formData.tipo_doc) || documentTypes[0];

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
                <div className="space-y-8">
                   {/* Logo */}
                    <div className="flex items-center gap-3">
                        <img src={logo} alt="Logo" className="w-20 h-20 rounded-xl object-contain bg-primary/10 p-1" />
                        <div>
                            <h1 className="text-3xl font-bold text-foreground">SanDi•Med</h1>
                            <p className="text-base text-muted-foreground">Portal del Paciente</p>
                        </div>
                    </div>

                    {/* Tagline */}
                    <div className="space-y-4">
                        <h2 className="text-3xl lg:text-4xl font-bold text-foreground leading-tight text-balance">
                            Tu salud, <span className="text-primary">organizada</span> en un solo lugar
                        </h2>
                        <p className="text-lg text-muted-foreground text-pretty">
                            Accede a tus citas médicas, resultados y recordatorios desde cualquier dispositivo.
                        </p>
                    </div>

                    {/* Features */}
                    <div className="space-y-4">
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

                    {/* Doctor illustration */}
                    <div className="hidden lg:block relative h-48">
                         <div className="absolute bottom-0 left-0 w-48 h-48 bg-card/50 rounded-2xl border border-border/50 backdrop-blur-sm flex items-center justify-center overflow-hidden">
                            <img src={doctorImg} alt="Doctor" className="w-full h-full object-cover opacity-90" />
                        </div>
                    </div>
                </div>

                {/* Right side - Login form */}
                <div className="w-full max-w-md mx-auto lg:mx-0 lg:ml-auto">
                    <div className="bg-card/80 backdrop-blur-xl rounded-2xl border border-border/50 p-8 shadow-2xl">
                        <div className="space-y-6">
                            <div className="text-center space-y-2">
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

                            <form onSubmit={handleSubmit} className="space-y-5">
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
                                <button className="w-full text-sm text-muted-foreground hover:text-primary transition-colors text-center">
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
        </div>
    );
}
