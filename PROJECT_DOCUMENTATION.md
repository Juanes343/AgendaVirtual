# Documentación Técnica del Proyecto - AgendaVirtual (SanDi•Med)

## 1. Visión General
AgendaVirtual es una plataforma web ("Portal del Paciente") diseñada para que los usuarios gestionen sus servicios médicos. El sistema permite autenticación segura, validación de pacientes contra bases de datos legadas y un dashboard interactivo.

La arquitectura se divide en dos componentes principales:
- **Frontend:** SPA (Single Page Application) construida con React, Vite y Tailwind CSS v4.
- **Backend:** API REST construida con Laravel 11.

---

## 2. Frontend

### Tecnologías
- **Framework:** React 19.2
- **Build Tool:** Vite 7.2
- **Estilos:** Tailwind CSS v4.1 (con `@tailwindcss/vite`).
- **Navegación:** React Router DOM v7.
- **Iconos:** Lucide React.
- **Cliente HTTP:** Axios.
- **Estado Global:** React Context API (`UserContext`).

### Estructura de Directorios Clave (`frontend/src`)
```
src/
├── assets/          # Imágenes (logo, ilustraciones)
├── components/      # Componentes reutilizables (Botones, Modales, Inputs)
├── contexts/        # Contextos globales
│   └── UserContext/ # Manejo de sesión de usuario (login, logout, user data)
├── features/        # Módulos funcionales (Vertical Slicing)
│   ├── Auth/        # Lógica de Autenticación
│   │   ├── services/ # Llamadas API específicas de auth
│   │   └── views/    # Vistas: LoginView.jsx, RegisterView.jsx
│   └── Home/        # Módulos principales tras login
│       └── views/    # DashboardView.jsx
├── services/        # Configuración global de API (Axios instance)
└── App.jsx          # Configuración de rutas
```

### Flujos Principales
#### A. Login (`LoginView.jsx`)
- Diseño "Glassmorphism" con modo oscuro por defecto.
- Validación de campos locales.
- Conexión con `POST /api/login`.
- Almacenamiento de sesión en `UserContext` y LocalStorage.

#### B. Registro Inteligente (`RegisterView.jsx`)
Se implementó un patrón "Wizard" de 2 pasos para mejorar la UX y la integridad de datos:

1.  **Paso 1: Validación Previa**
    - El usuario ingresa *Tipo de Documento* y *Número*.
    - Se consulta `POST /api/check-patient`.
    - **Casos:**
        - **Tiene Cuenta:** Se bloquea el registro y redirige al Login.
        - **Existe en Legacy:** Se pasa al Paso 2 con nombre, fecha y sexo pre-cargados (campos bloqueados para consistencia).
        - **Nuevo Usuario:** Se pasa al Paso 2 con formulario vacío.

2.  **Paso 2: Completar Datos**
    - Formulario con validación de contraseñas.
    - Envío final a `POST /api/register`.
    - **Modal de Éxito:** Confirmación visual y botón para auto-login inmediato.

---

## 3. Backend (API)

### Tecnologías
- **Framework:** Laravel 11.
- **Autenticación (API):** Laravel Sanctum (Token-based).
- **Base de Datos:** MySQL (con integración a tablas legacy de pacientes).

### Definición de Endpoints (`routes/api.php`)

| Método | Endpoint | Controlador | Descripción |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/login` | `AuthController@login` | Valida credenciales y emite token de acceso. |
| `POST` | `/api/check-patient` | `AuthController@checkPatient` | Verifica existencia del paciente por documento. Retorna estado (`exists`, `has_account`, `null`) y datos básicos. |
| `POST` | `/api/register` | `AuthController@register` | Crea el usuario en el sistema y lo vincula al paciente si existe. |
| `POST` | `/api/recover-password`| `AuthController@recoverPassword`| Inicia flujo de recuperación de contraseña. |

#### Detalle de Payloads

**1. Login**
```json
// Request
{
  "tipo_doc": "CC",
  "usuario": "12345678",
  "passwd": "password123"
}
```

**2. Check Patient**
```json
// Request
{
  "tipo_doc": "CC",
  "usuario": "100200300"
}

// Response (Ejemplo: Paciente Existe sin cuenta)
{
  "success": true,
  "status": "exists",
  "data": {
    "primer_nombre": "Juan",
    "primer_apellido": "Perez",
    ...
  }
}
```

---

## 4. Guía de Despliegue y Ejecución Local

### Requisitos Previos
- Node.js v18+
- PHP 8.2+
- Composer
- Servidor MySQL

### Pasos
1.  **Backend:**
    ```bash
    cd backend
    composer install
    cp .env.example .env # Configurar DB
    php artisan key:generate
    php artisan migrate
    php artisan serve
    ```

2.  **Frontend:**
    ```bash
    cd frontend
    npm install
    npm run dev
    ```

## 5. Notas de Estilo y Diseño
- **Paleta de Colores:** Basada en variables CSS (`--color-primary`, `--color-background`) definidas en `src/index.css`.
- **Responsive:** Diseño Mobile-first utilizando las clases de utilidad de Tailwind (`lg:grid-cols-2`, `w-full`).
- **Glassmorphism:** Uso intensivo de `backdrop-blur`, bordes semitransparentes (`border-white/10`) y sombras suaves.
