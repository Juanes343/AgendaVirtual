import React, { useState, useEffect } from 'react';
import { useNavigate, Link, useSearchParams } from 'react-router-dom';
import { Calendar, FileText, Bell, Shield, Eye, EyeOff, Check, ChevronDown, User, ArrowRight, ArrowLeft, Mail } from 'lucide-react';
import { useUser } from '../../../contexts/UserContext/UserContext';
import authService from '../services/authService';
import api from '../../../services/api'; // Import instance
import logo from '../../../assets/images/sandi_virtual.png';

export default function RegisterView() {
    const { login } = useUser();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();

    // Estado local para tipos de documento
    const [documentTypes, setDocumentTypes] = useState([
        { value: "CC", label: "Cédula de Ciudadanía" }
    ]);

    // Steps: 1 = Validation, 2 = Personal Data
    const [step, setStep] = useState(1);
    
    const [formData, setFormData] = useState({
        tipo_doc: 'CC',
        usuario: '',
        email_step1: '', // Nuevo campo para validación inicial
        primer_nombre: '',
        segundo_nombre: '',
        primer_apellido: '',
        segundo_apellido: '',
        fecha_nacimiento: '',
        sexo: '', // M or F
        celular: '',
        email: '',
        passwd: '',
        confirm_passwd: ''
    });

    const [isDropdownOpen, setIsDropdownOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [isExistingPatient, setIsExistingPatient] = useState(false); // Si el paciente existe en DB
    const [showSuccessModal, setShowSuccessModal] = useState(false);
    const [showVerificationModal, setShowVerificationModal] = useState(false); // Modal "Correo enviado"
    const [showMismatchModal, setShowMismatchModal] = useState(false); // Modal "Correo no cumple"
    const [maskedEmail, setMaskedEmail] = useState('');

    // --- EFFECT: Verificación por Token ---
    useEffect(() => {
        const token = searchParams.get('token');
        if (token && step === 1) {
            handleVerifyToken(token);
        }
    }, [searchParams]);

    const handleVerifyToken = async (token) => {
        setLoading(true);
        try {
            const response = await authService.verifyRegistrationToken(token);
            if (response.success) {
                const p = response.paciente;
                setFormData(prev => ({
                    ...prev,
                    tipo_doc: p.tipo_doc,
                    usuario: p.usuario,
                    primer_nombre: p.primer_nombre || '',
                    segundo_nombre: p.segundo_nombre || '',
                    primer_apellido: p.primer_apellido || '',
                    segundo_apellido: p.segundo_apellido || '',
                    fecha_nacimiento: p.fecha_nacimiento || '',
                    sexo: p.sexo || '',
                    celular: p.celular || '',
                    email: p.email || ''
                }));
                setIsExistingPatient(true);
                // Si ya fue verificado, no necesitamos enmascarar en el paso 2
                setMaskedEmail(p.email); 
                setStep(2);
                // Limpiar parámetros para no re-ejecutar al cambiar estados
                window.history.replaceState({}, document.title, window.location.pathname + window.location.hash.split('?')[0]);
            }
        } catch (err) {
            setError(err.response?.data?.message || 'El enlace de verificación es inválido o expiró.');
        } finally {
            setLoading(false);
        }
    };

    // Enmascarar email (ej: simde***@gmail.com)
    const maskEmail = (email) => {
        if (!email) return '';
        const [user, domain] = email.split('@');
        if (user.length <= 3) return user[0] + '***@' + domain;
        return user.substring(0, 3) + '***@' + domain;
    };

    // Cargar tipos de documento
    useEffect(() => {
        const fetchDocTypes = async () => {
             try {
                 const response = await api.get('/document-types');
                 if (response.data && Array.isArray(response.data)) {
                     const types = response.data.map(t => ({
                         value: t.tipo_id_paciente,
                         label: t.descripcion || t.tipo_id_paciente
                     }));
                     setDocumentTypes(types);
                 }
             } catch (error) {
                 console.error("Error cargando tipos documento registro:", error);
             }
        };
        fetchDocTypes();
    }, []);

    const selectedDocType = documentTypes.find(d => d.value === formData.tipo_doc) || documentTypes[0] || { value: '', label: 'Seleccionar' };

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

    const handleValidation = async (e) => {
        e.preventDefault();
        setError('');
        setLoading(true);
        try {
            const response = await authService.checkPatient({
                tipo_doc: formData.tipo_doc,
                usuario: formData.usuario,
                email: formData.email_step1
            });

            if (response.success) {
                if (response.status === 'has_account') {
                    setError('Ya tiene una cuenta activa. Por favor inicie sesión.');
                } else if (response.status === 'needs_verification') {
                    // SE HA ENVIADO CORREO
                    setMaskedEmail(maskEmail(response.email));
                    setShowVerificationModal(true);
                } else if (response.status === 'not_found') {
                    // NO EXISTE EN DB, PROCEDER NORMAL
                    setIsExistingPatient(false);
                    setFormData(prev => ({ ...prev, email: formData.email_step1 }));
                    setStep(2);
                }
            }
        } catch (err) {
            console.error(err);
            if (err.response?.status === 403 && err.response?.data?.status === 'email_mismatch') {
                setShowMismatchModal(true);
            } else {
                setError(err.response?.data?.message || 'Error al validar el documento. Intente nuevamente.');
            }
        } finally {
            setLoading(false);
        }
    };

    const handleSuccessContinue = async () => {
         setLoading(true);
         try {
            const loginResponse = await authService.login({
                tipo_doc: formData.tipo_doc,
                usuario: formData.usuario,
                passwd: formData.passwd
            });
             if (loginResponse.success) {
                 login(loginResponse.data);
                 navigate('/home');
            }
        } catch (loginErr) {
             navigate('/login');
        }
    };

    // Step 2: Final Registration
    const handleRegister = async (e) => {
        e.preventDefault();
        setError('');

        if (formData.passwd !== formData.confirm_passwd) {
            setError('Las contraseñas no coinciden.');
            return;
        }

        setLoading(true);
        try {
            const registerResponse = await authService.register(formData);
            
            if (registerResponse.success) {
                setShowSuccessModal(true);
            }
        } catch (err) {
            console.error(err);
            setError(err.response?.data?.message || 'Error al registrar usuario.');
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
            
             <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/20 rounded-full blur-3xl pointer-events-none" />
             <div className="absolute bottom-1/4 right-1/4 w-64 h-64 bg-accent/10 rounded-full blur-3xl pointer-events-none" />

            <div className="w-full max-w-6xl grid lg:grid-cols-2 gap-8 lg:gap-16 items-start relative z-10">
                 {/* Left side - Branding (Same as Login) */}
                 <div className="hidden lg:block space-y-8 sticky top-8">
                    <div className="flex items-center gap-3">
                        <img src={logo} alt="Logo" className="w-20 h-20 rounded-xl object-contain bg-primary/10 p-1" />
                        <div>
                            <h1 className="text-3xl font-bold text-foreground">{import.meta.env.VITE_APP_TITLE || "SanDi•Med"}</h1>
                            <p className="text-base text-muted-foreground">Portal del Paciente</p>
                        </div>
                    </div>
                     <div className="space-y-4">
                        <h2 className="text-4xl font-bold text-foreground leading-tight">
                            Únete a nuestra <span className="text-primary">comunidad</span>
                        </h2>
                        <p className="text-lg text-muted-foreground">
                            Crea tu cuenta para acceder a todos los servicios digitales de salud.
                        </p>
                    </div>
                    <div className="space-y-4">
                         <div className="p-4 rounded-xl bg-card border border-border backdrop-blur-sm">
                            <h4 className="font-semibold mb-2 flex items-center gap-2">
                                <Shield className="w-4 h-4 text-primary" />
                                Seguridad garantizada
                            </h4>
                            <p className="text-sm text-muted-foreground">
                                Tus datos médicos están protegidos con los más altos estándares de seguridad.
                            </p>
                         </div>
                    </div>
                </div>

                {/* Right side - Form */}
                <div className="w-full max-w-xl mx-auto lg:mx-0 lg:ml-auto">
                     <div className="bg-card/80 backdrop-blur-xl rounded-2xl border border-border/50 p-8 shadow-2xl">
                        
                        {/* Header */}
                        <div className="mb-6">
                            <div className="flex items-center gap-3 p-3 bg-primary/10 border border-primary/20 rounded-xl mb-6">
                                <div className="p-2 bg-primary rounded-lg">
                                    <FileText className="w-5 h-5 text-primary-foreground" />
                                </div>
                                <div>
                                    <h3 className="font-semibold text-foreground">Registro de Usuario</h3>
                                    <p className="text-xs text-muted-foreground">Complete los datos para registrarse</p>
                                </div>
                            </div>

                            {/* Progress Steps */}
                            <div className="flex items-center justify-between relative mb-8">
                                <div className="absolute left-0 top-1/2 w-full h-0.5 bg-border -z-10"></div>
                                <div className={`flex items-center justify-center w-8 h-8 rounded-full border-2 transition-colors bg-card ${step >= 1 ? 'border-primary text-primary' : 'border-muted text-muted-foreground'}`}>
                                    <span className="text-sm font-bold">1</span>
                                </div>
                                <div className={`flex items-center justify-center w-8 h-8 rounded-full border-2 transition-colors bg-card ${step >= 2 ? 'border-primary text-primary' : 'border-muted text-muted-foreground'}`}>
                                    <span className="text-sm font-bold">2</span>
                                </div>
                            </div>
                            <div className="flex justify-between text-xs font-medium text-muted-foreground -mt-6 px-1">
                                <span>Validación</span>
                                <span>Datos Personales</span>
                            </div>
                        </div>

                        {error && (
                            <div className="mb-6 bg-destructive/10 border border-destructive/20 text-destructive text-sm p-3 rounded-lg flex items-start gap-2">
                                <span className="mt-0.5">⚠️</span>
                                {error}
                            </div>
                        )}

                        {/* STEP 1 FORM */}
                        {step === 1 && (
                            <form onSubmit={handleValidation} className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                <div className="space-y-4">
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
                                                placeholder="Ej: 1234567890"
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <label className="text-sm font-medium text-foreground">Correo electrónico registrado</label>
                                        <div className="relative">
                                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <Mail className="h-4 w-4 text-muted-foreground" />
                                            </div>
                                            <input
                                                type="email"
                                                name="email_step1"
                                                value={formData.email_step1}
                                                onChange={handleChange}
                                                className="w-full pl-10 pr-3 py-2 bg-input/50 border border-input rounded-lg text-foreground placeholder:text-muted-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                                                placeholder="Ej: usuario@correo.com"
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="submit"
                                    disabled={loading || !formData.usuario || !formData.email_step1}
                                    className="w-full bg-primary text-primary-foreground hover:bg-primary/90 py-3 rounded-xl font-bold shadow-lg shadow-primary/20 transition-all active:scale-[0.98] disabled:opacity-70 disabled:pointer-events-none flex justify-center items-center gap-2"
                                >
                                    {loading ? (
                                        <>
                                            <span className="w-5 h-5 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin"></span>
                                            Validando...
                                        </>
                                    ) : (
                                        <>
                                            Validar y Continuar
                                            <ArrowRight className="w-5 h-5" />
                                        </>
                                    )}
                                </button>
                                
                                <div className="text-center pt-2">
                                    <Link to="/login" className="text-sm text-muted-foreground hover:text-primary transition-colors">
                                        ¿Ya tienes cuenta? Iniciar Sesión
                                    </Link>
                                </div>
                            </form>
                        )}


                        {/* STEP 2 FORM */}
                        {step === 2 && (
                             <form onSubmit={handleRegister} className="space-y-5 animate-in fade-in slide-in-from-right-4 duration-300">
                                
                                <div className="grid grid-cols-2 gap-4">
                                     <div className="space-y-2">
                                        <label className="text-xs font-medium text-muted-foreground">Primer Nombre</label>
                                        <input
                                            type="text"
                                            name="primer_nombre"
                                            value={formData.primer_nombre}
                                            onChange={handleChange}
                                            readOnly={isExistingPatient}
                                            className={`w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none ${isExistingPatient ? 'opacity-70 cursor-not-allowed bg-muted/50' : ''}`}
                                            required
                                        />
                                    </div>
                                     <div className="space-y-2">
                                        <label className="text-xs font-medium text-muted-foreground">Segundo Nombre</label>
                                        <input
                                            type="text"
                                            name="segundo_nombre"
                                            value={formData.segundo_nombre}
                                            onChange={handleChange}
                                            readOnly={isExistingPatient}
                                            className={`w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none ${isExistingPatient ? 'opacity-70 cursor-not-allowed bg-muted/50' : ''}`}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-xs font-medium text-muted-foreground">Primer Apellido</label>
                                        <input
                                            type="text"
                                            name="primer_apellido"
                                            value={formData.primer_apellido}
                                            onChange={handleChange}
                                            readOnly={isExistingPatient}
                                            className={`w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none ${isExistingPatient ? 'opacity-70 cursor-not-allowed bg-muted/50' : ''}`}
                                            required
                                        />
                                    </div>
                                     <div className="space-y-2">
                                        <label className="text-xs font-medium text-muted-foreground">Segundo Apellido</label>
                                        <input
                                            type="text"
                                            name="segundo_apellido"
                                            value={formData.segundo_apellido}
                                            onChange={handleChange}
                                            readOnly={isExistingPatient}
                                            className={`w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none ${isExistingPatient ? 'opacity-70 cursor-not-allowed bg-muted/50' : ''}`}
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                     <div className="space-y-2">
                                        <label className="text-xs font-medium text-muted-foreground">Fecha Nacimiento</label>
                                        <input
                                            type="date"
                                            name="fecha_nacimiento"
                                            value={formData.fecha_nacimiento}
                                            onChange={handleChange}
                                            readOnly={isExistingPatient}
                                            className={`w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none ${isExistingPatient ? 'opacity-70 cursor-not-allowed bg-muted/50' : ''}`}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <label className="text-xs font-medium text-muted-foreground">Celular</label>
                                        <input
                                            type="tel"
                                            name="celular"
                                            value={formData.celular}
                                            onChange={handleChange}
                                            className="w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                                            required
                                        />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <label className="text-xs font-medium text-muted-foreground">Sexo</label>
                                        <select
                                            name="sexo"
                                            value={formData.sexo}
                                            onChange={handleChange}
                                            disabled={isExistingPatient}
                                            className={`w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none ${isExistingPatient ? 'opacity-70 cursor-not-allowed bg-muted/50' : ''}`}
                                            required
                                        >
                                            <option value="">Seleccione</option>
                                            <option value="M">Masculino</option>
                                            <option value="F">Femenino</option>
                                        </select>
                                    </div>
                                </div>

                                {/* Editable Fields */}
                                <div className="space-y-2">
                                    <label className="text-xs font-medium text-muted-foreground">Correo Electrónico</label>
                                    <input
                                        type="email"
                                        name="email"
                                        value={isExistingPatient ? maskedEmail : formData.email}
                                        onChange={handleChange}
                                        readOnly={isExistingPatient}
                                        disabled={isExistingPatient}
                                        className={`w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none ${isExistingPatient ? 'opacity-70 cursor-not-allowed bg-muted/50' : ''}`}
                                        required
                                    />
                                </div>
                                
                                <div className="space-y-2">
                                    <label className="text-xs font-medium text-muted-foreground">Contraseña</label>
                                    <div className="relative">
                                        <input
                                            type={showPassword ? "text" : "password"}
                                            name="passwd"
                                            value={formData.passwd}
                                            onChange={handleChange}
                                            className="w-full pl-3 pr-10 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                                            placeholder="Crear contraseña"
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
                                <div className="space-y-2">
                                    <label className="text-xs font-medium text-muted-foreground">Confirmar Contraseña</label>
                                    <input
                                        type="password"
                                        name="confirm_passwd"
                                        value={formData.confirm_passwd}
                                        onChange={handleChange}
                                        className="w-full px-3 py-2 bg-input/50 border border-input rounded-lg text-foreground focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none"
                                        placeholder="Repetir contraseña"
                                        required
                                    />
                                </div>

                                <div className="flex gap-3 pt-2">
                                    <button
                                        type="button"
                                        onClick={() => setStep(1)}
                                        className="px-4 py-2 border border-border rounded-lg text-foreground hover:bg-muted transition-colors"
                                    >
                                        <ArrowLeft className="w-4 h-4" />
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={loading}
                                        className="flex-1 bg-primary text-primary-foreground hover:bg-primary/90 py-2 rounded-lg font-medium shadow-lg shadow-primary/20 transition-all flex justify-center items-center gap-2"
                                    >
                                        {loading ? (
                                            <>
                                                <span className="w-4 h-4 border-2 border-primary-foreground border-t-transparent rounded-full animate-spin"></span>
                                                Registrando...
                                            </>
                                        ) : (
                                            "Completar Registro"
                                        )}
                                    </button>
                                </div>

                             </form>
                        )}
                     </div>
                </div>
            </div>

            {/* Success Modal */}
            {showSuccessModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 animate-in fade-in duration-300">
                    <div className="bg-card w-full max-w-md rounded-2xl border border-border shadow-2xl p-8 text-center relative overflow-hidden">
                        <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary via-accent to-primary"></div>
                        
                        <div className="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6 ring-8 ring-primary/5">
                            <Check className="w-10 h-10 text-primary" />
                        </div>
                        
                        <h3 className="text-2xl font-bold mb-2 text-foreground">¡Casi listo!</h3>
                        <p className="text-muted-foreground mb-8 text-balance">
                            Te has registrado exitosamente. Para activar tu cuenta, por favor revisa el correo enviado a <b className="text-primary">{formData.email}</b> y haz clic en el botón <b>"Activar mi cuenta"</b>.
                        </p>
                        
                        <button 
                            onClick={() => navigate('/login')}
                            className="w-full bg-primary text-primary-foreground py-3.5 rounded-xl font-medium hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 active:scale-[0.98] flex items-center justify-center gap-2"
                        >
                            Entendido, ir al Login
                        </button>
                    </div>
                </div>
            )}

            {/* Verification Sent Modal (Step 1 -> Email Sent) */}
            {showVerificationModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4 animate-in zoom-in-95 duration-300">
                    <div className="bg-card w-full max-w-md rounded-3xl border border-primary/20 shadow-2xl p-10 text-center relative overflow-hidden">
                        <div className="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-primary to-blue-500"></div>
                        
                        <div className="w-24 h-24 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-8 ring-8 ring-primary/5">
                            <Mail className="w-12 h-12 text-primary" />
                        </div>
                        
                        <h3 className="text-3xl font-black mb-4 text-foreground tracking-tight uppercase">Verifique su Identidad</h3>
                        <p className="text-muted-foreground text-lg mb-8 leading-relaxed">
                            Hemos detectado que ya es paciente de nuestra institución. Para continuar con su registro web, por favor revise el correo enviado a <b className="text-blue-500 select-all">{maskedEmail}</b> y haga clic en el enlace para validar su cuenta.
                        </p>
                        
                        <button 
                            onClick={() => setShowVerificationModal(false)}
                            className="w-full bg-primary text-primary-foreground py-4 rounded-2xl font-black text-lg hover:bg-primary/90 transition-all shadow-xl shadow-primary/30 active:scale-[0.95]"
                        >
                            Entendido
                        </button>
                    </div>
                </div>
            )}

            {/* Email Mismatch Modal */}
            {showMismatchModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-md p-4 animate-in zoom-in-95 duration-300">
                    <div className="bg-card w-full max-w-md rounded-3xl border border-destructive/20 shadow-2xl p-10 text-center relative overflow-hidden">
                        <div className="absolute top-0 left-0 w-full h-1.5 bg-destructive"></div>
                        
                        <div className="w-24 h-24 bg-destructive/10 rounded-full flex items-center justify-center mx-auto mb-8 ring-8 ring-destructive/5">
                            <span className="text-5xl">⚠️</span>
                        </div>
                        
                        <h3 className="text-3xl font-black mb-4 text-foreground tracking-tight uppercase">Datos no Coinciden</h3>
                        <p className="text-muted-foreground text-lg mb-8 leading-relaxed">
                            El correo ingresado <b>no coincide</b> con el que tenemos registrado en nuestra base de datos. 
                            <br/><br/>
                            Por favor, <b>acuda a una de nuestras sedes</b> para solicitar la actualización de su información personal y poder completar su registro.
                        </p>
                        
                        <button 
                            onClick={() => setShowMismatchModal(false)}
                            className="w-full bg-slate-800 text-white py-4 rounded-2xl font-black text-lg hover:bg-slate-700 transition-all shadow-xl active:scale-[0.95]"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
