# Portal de Clientes — Grupo Albardi

Prueba técnica para Becario de Desarrollo Web. El portal incluye:

- Un **sitio público** que muestra el catálogo de servicios y un formulario de contacto validado.
- Un **panel** con inicio de sesión por roles: el administrador gestiona los servicios y el cliente contrata y paga.
- Un **flujo de pagos** simulado, con integración opcional de **Stripe Checkout** en modo prueba.

El enunciado está en [ENUNCIADO.md](ENUNCIADO.md) y los criterios de evaluación en [RUBRICA.md](RUBRICA.md).

| Módulo | Qué incluye | Dónde |
|--------|-------------|-------|
| 1 · Frontend | Página semántica, responsive y accesible; tarjetas cargadas desde la API; validación del formulario | `frontend/` |
| 2 · Backend | Login con roles y sesión segura, CRUD de servicios, API JSON, protección contra SQLi, XSS y CSRF | `backend/`, `database.sql` |
| 3 · QA | Verificación de los 5 bugs, 4 bugs adicionales corregidos y pruebas reproducibles | `qa/` |
| 4 · Git | Ramas por módulo, commits convencionales y PRs con merge commit | historial del repositorio |
| 5 · Pagos | Estados `pendiente → pagado / fallido`, historial y Stripe Checkout (opcional) | `backend/pagos/`, [docs/stripe.md](docs/stripe.md) |
| 6 · Docs | Este README y la bitácora | [docs/bitacora.md](docs/bitacora.md) |

## Stack

- **Frontend:** HTML5, CSS3 y JavaScript vanilla (módulos ES), sin frameworks. El diseño se construyó con variables CSS propias en lugar de Bootstrap: pesa menos y no obliga a reescribir los estilos de otra librería.
- **Backend:** PHP 8 con PDO y consultas preparadas, sin dependencias de Composer.
- **Base de datos:** MySQL o MariaDB.
- **Entorno:** XAMPP (Apache + MariaDB) en Windows.

## Requisitos

| Herramienta | Versión | Notas |
|-------------|---------|-------|
| PHP | 8.0 o superior (probado con 8.2.12) | Extensiones `pdo_mysql` y `mbstring`. `curl` solo hace falta para Stripe. |
| MySQL / MariaDB | MySQL 8.0.16+ o MariaDB 10.2+ (probado con MariaDB 10.4.32) | Requeridas para que se apliquen las restricciones `CHECK`. |
| Apache | 2.4 con `AllowOverride All` | Los `.htaccess` bloquean el acceso a los archivos internos. |
| Node.js | 20 o superior | Opcional: solo para las pruebas unitarias del frontend. |
| Git Bash | — | Opcional: solo para las pruebas de QA en Windows. |

## Instalación

1. **Clona el repositorio** dentro de `htdocs`. Cualquier nombre de carpeta funciona, porque las rutas son relativas; los ejemplos usan `entrega_becario`.
   ```bash
   git clone https://github.com/KayloRenZero/prueba_becario.git C:/xampp/htdocs/entrega_becario
   ```
2. **Inicia Apache y MySQL** desde el panel de XAMPP.
3. **Crea la base de datos** importando `database.sql`: en phpMyAdmin, *Importar*; o desde la terminal:
   ```bash
   mysql -u root -p < database.sql
   ```
   > El script **borra y recrea** las tablas de `albardi_portal` para partir siempre del mismo estado.
4. **Configura la conexión.** Copia la plantilla y ajusta tus credenciales:
   ```bash
   cp backend/config.example.php backend/config.php
   ```
   `config.php` está en `.gitignore`: tus credenciales nunca se suben al repositorio.

   | Constante | Para qué sirve |
   |-----------|----------------|
   | `APP_ENV` | `development` muestra los errores en pantalla; `production` solo los registra en el log. |
   | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Conexión a MySQL. |
   | `APP_URL` | URL pública del proyecto, por ejemplo `http://localhost/entrega_becario`. |
   | `STRIPE_SECRET_KEY` | Opcional. Llave `sk_test_…` para activar Stripe; ver [docs/stripe.md](docs/stripe.md). |

5. **Abre el sitio** en <http://localhost/entrega_becario/frontend/>.

### Solución de problemas

- **"No se pudo conectar a la base de datos"** con las credenciales correctas: puede haber otro MySQL ocupando el puerto 3306. Pasa, por ejemplo, con el servicio *MySQL80* de Windows. Revisa en qué puerto escucha el MySQL de XAMPP (botón *Config → my.ini*, clave `port`) y ponlo en `DB_PORT`.
- **Los cambios de CSS o JS no se ven:** recarga con `Ctrl + Shift + R`. El panel versiona sus archivos automáticamente, pero los módulos del sitio público pueden quedar en la caché del navegador.

## Credenciales de prueba

| Rol | Correo | Contraseña | Qué puede hacer |
|-----|--------|------------|-----------------|
| Administrador | `admin@albardi.com` | `Admin123!` | Gestionar servicios y comprar |
| Cliente | `cliente@albardi.com` | `Cliente123!` | Comprar servicios y ver su historial (un cliente que entra al panel de servicios recibe 403) |

Son datos semilla de `database.sql`: no son credenciales reales. Las contraseñas se guardan con `password_hash` (bcrypt).

## Estructura

```
├── frontend/                     Sitio público (Módulo 1)
│   ├── index.html                Maquetado semántico y plantillas <template> para las tarjetas
│   ├── css/styles.css            Diseño mobile-first con variables CSS
│   ├── css/admin.css             Estilos del panel (login, tablas, pagos)
│   ├── js/app.js                 Punto de entrada: conecta los módulos
│   ├── js/modules/               validators · services · contact-form · menu
│   ├── js/admin.js               Confirmaciones y foco en el primer campo con error del panel
│   └── tests/                    Pruebas unitarias con node:test
├── backend/
│   ├── config.example.php        Plantilla de configuración (config.php no se versiona)
│   ├── db.php                    Conexión PDO (excepciones, prepares reales, utf8mb4)
│   ├── helpers.php               Sesión, CSRF, guardas por rol, mensajes flash, escape y manejo de errores
│   ├── lib/                      Lógica y acceso a datos: servicios · usuarios · pagos · stripe
│   ├── views/                    Layout común, página de error y formulario de servicio
│   ├── auth/                     login.php · logout.php
│   ├── servicios/                index · create · update · delete (solo admin)
│   ├── api/servicios.php         API JSON
│   └── pagos/                    checkout.php · stripe-retorno.php
├── qa/
│   ├── buggy.php                 Archivo de QA corregido
│   ├── BUGS.md                   Bugs: ubicación, causa, corrección y verificación
│   └── tests/buggy_test.sh       42 pruebas HTTP reproducibles
├── docs/
│   ├── bitacora.md               Qué se hizo, problemas y soluciones
│   └── stripe.md                 Integración con Stripe Checkout
└── database.sql                  Esquema, restricciones y datos semilla
```

Las páginas del backend son delgadas: verifican el acceso, llaman a `lib/` y muestran una vista. La validación y el SQL de servicios viven en un solo lugar (`lib/servicios.php`), que comparten el panel y la API. `lib/` y `views/`, igual que `config.php`, `db.php` y `helpers.php`, responden **403** si se piden por HTTP.

## Páginas y endpoints

Las rutas son relativas a la carpeta del proyecto. Todo lo que modifica datos usa `POST` con token CSRF y termina en una redirección `303` (patrón PRG).

| Método | Ruta | Acceso | Descripción |
|--------|------|--------|-------------|
| GET | `/frontend/` | Público | Sitio: catálogo, contacto y botón "Contratar" |
| GET | `/backend/api/servicios.php` | Público | Lista los servicios activos (JSON) |
| POST | `/backend/api/servicios.php` | Admin + cabecera `X-CSRF-Token` | Crea un servicio (JSON) |
| GET, POST | `/backend/auth/login.php` | Público | Inicio de sesión; vuelve a la página que se pidió antes |
| POST | `/backend/auth/logout.php` | Sesión + CSRF | Cierra la sesión (`GET` responde 405) |
| GET | `/backend/servicios/index.php` | Admin | Listado de servicios |
| GET, POST | `/backend/servicios/create.php` | Admin + CSRF | Alta con validación en el servidor |
| GET, POST | `/backend/servicios/update.php?id=N` | Admin + CSRF | Edición (404 si el id no existe) |
| POST | `/backend/servicios/delete.php` | Admin + CSRF | Baja; si el servicio tiene pagos, se impide y se sugiere desactivarlo |
| GET | `/backend/pagos/checkout.php` | Sesión | Catálogo, pago en curso e historial del usuario |
| GET | `/backend/pagos/checkout.php?servicio_id=N` | Sesión | Confirmación de compra (no crea nada) |
| GET | `/backend/pagos/checkout.php?pago_id=N` | Sesión | Detalle de un pago propio |
| POST | `/backend/pagos/checkout.php` | Sesión + CSRF | `accion` = `crear` · `confirmar` · `rechazar` · `stripe` |
| GET | `/backend/pagos/stripe-retorno.php?session_id=…` | Sesión | Regreso desde Stripe: verifica el cobro con Stripe |
| GET, POST | `/qa/buggy.php` | Público | Archivo del módulo de QA |

### API de servicios

**`GET /backend/api/servicios.php`** → `200`
```json
[
  { "id": 4, "nombre": "QA y Testing", "descripcion": "Detección y corrección de bugs.", "precio": "2500.00" }
]
```

**`POST /backend/api/servicios.php`** necesita una sesión de administrador y el token CSRF de la sesión en la cabecera `X-CSRF-Token`. El token está en el `<meta name="csrf-token">` de cualquier página del panel.
```http
POST /backend/api/servicios.php
Content-Type: application/json
X-CSRF-Token: <token>

{ "nombre": "Consultoría", "descripcion": "Asesoría técnica.", "precio": "1250.50" }
```

| Código | Cuándo |
|--------|--------|
| `201` | Creado. Devuelve el servicio con el precio normalizado, por ejemplo `"1250.50"` |
| `400` | El cuerpo no es un JSON válido, no es un objeto o no está en UTF-8 |
| `401` | Sin sesión |
| `403` | El usuario no es administrador, o falta el token CSRF o no coincide |
| `405` | Método distinto de GET o POST (incluye la cabecera `Allow`) |
| `415` | El `Content-Type` no es `application/json` |
| `422` | Datos inválidos: `{ "error": "...", "campos": { "precio": "..." } }` |
| `500` | Error interno. El detalle se registra en el log y no se envía al cliente |

## Seguridad

| Riesgo | Medida |
|--------|--------|
| Inyección SQL | Consultas preparadas en **todas** las consultas, con `PDO::ATTR_EMULATE_PREPARES = false`. Los ids se validan con `FILTER_VALIDATE_INT`. |
| XSS | Toda salida HTML pasa por `e()` (`htmlspecialchars` con `ENT_QUOTES`). En el frontend, los datos se insertan con `textContent`, nunca con `innerHTML`. |
| CSRF | Token por sesión comparado con `hash_equals`: en un campo oculto en los formularios y en la cabecera `X-CSRF-Token` en la API. |
| Robo o fijación de sesión | `session_regenerate_id(true)` al iniciar sesión, cookie `HttpOnly` + `SameSite=Lax` (`Secure` en HTTPS), modo estricto y cierre tras 30 min de inactividad. |
| Enumeración de usuarios | Mensaje de error genérico y verificación contra un hash ficticio, para que la respuesta tarde lo mismo exista o no el correo. |
| Acceso indebido | Guardas `requireLogin()` y `requireAdmin()`. Cada consulta de pagos filtra por usuario, así que nadie puede ver ni pagar el pago de otro (IDOR). |
| Redirección abierta | La página a la que se vuelve tras el login se guarda en la sesión, no en un parámetro de la URL. |
| Fuga de información | Errores genéricos para el usuario y detalle solo en el log. Los archivos internos responden 403 y las credenciales quedan fuera del repositorio. |
| Integridad de datos | `DECIMAL` para el dinero, restricciones `CHECK`, `UNIQUE` y claves foráneas, precio congelado al comprar y transiciones de estado atómicas. |

## Pruebas

```bash
# Frontend: 33 pruebas unitarias (validadores, formato de precios, enlace al checkout)
cd frontend && npm test

# QA: 42 pruebas HTTP contra Apache + MySQL (en Windows, desde Git Bash)
bash qa/tests/buggy_test.sh
DB_PORT=3307 bash qa/tests/buggy_test.sh     # si tu MySQL usa otro puerto
BASE_URL=http://localhost/otra_carpeta bash qa/tests/buggy_test.sh   # si la carpeta tiene otro nombre
```

Cada módulo se verificó además con pruebas de integración contra Apache y MariaDB reales, y con recorridos en Chrome. Los resultados están en la descripción de cada PR y en [docs/bitacora.md](docs/bitacora.md).

## Flujo de trabajo con Git

- Una rama por módulo o arreglo: `feature/frontend`, `fix/db-port`, `feature/login`, `feature/servicios-crud`, `fix/qa-buggy`, `feature/pagos` y `docs/documentacion`.
- Commits atómicos con [Conventional Commits](https://www.conventionalcommits.org/es/): `feat`, `fix`, `test`, `docs`, `chore`.
- Integración a `main` mediante Pull Requests con *merge commit* (no *squash*) para conservar el historial de cada rama.

## Documentación adicional

- [docs/bitacora.md](docs/bitacora.md): qué se hizo en cada módulo, los problemas que surgieron y cómo se resolvieron.
- [docs/stripe.md](docs/stripe.md): cómo activar Stripe Checkout, tarjetas de prueba y decisiones de seguridad.
- [qa/BUGS.md](qa/BUGS.md): reporte de QA.
