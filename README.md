# Prueba Técnica — Becario Desarrollo Web (Grupo Albardi)

Bienvenido/a. Esta prueba evalúa tus conocimientos en frontend (HTML/CSS/JS), backend (PHP + MySQL), Git, QA/debugging, integración de APIs/pagos y documentación.

## Stack requerido
- Frontend: HTML5, CSS3 (responsive), JavaScript (vanilla)
- Backend: PHP 8+ con PDO
- Base de datos: MySQL / MariaDB
- Control de versiones: Git
- Entorno: XAMPP (Apache + MySQL) en localhost

## Duración
6 horas (puedes dividirla en 2 sesiones de 3h). Tiempo sugerido por módulo está indicado en `ENUNCIADO.md`.

## Entregables
1. Repositorio Git (GitHub/GitLab) con historial de commits.
2. Archivo `database.sql` importado y funcionando.
3. `README.md` con instrucciones de instalación y bitácora.
4. Archivo `qa/BUGS.md` con los bugs detectados y su corrección.
5. Demo funcional en `http://localhost/prueba_becario`.

## Estructura del proyecto
```
prueba_becario/
├── frontend/        # Módulo 1
│   ├── index.html
│   ├── css/styles.css
│   └── js/app.js
├── backend/         # Módulo 2 y 5
│   ├── config.php
│   ├── db.php
│   ├── helpers.php
│   ├── api/servicios.php
│   ├── auth/{login,logout}.php
│   ├── servicios/{index,create,update,delete}.php
│   └── pagos/checkout.php
├── qa/              # Módulo 3
│   ├── buggy.php
│   └── BUGS.md
├── docs/            # Bitácora
│   └── bitacora.md
├── database.sql     # Esquema + datos semilla
├── .htaccess
├── .gitignore
├── ENUNCIADO.md
├── MANIFEST.md
└── RUBRICA.md
```

## Instalación rápida
1. Copia la carpeta a `C:\xampp\htdocs\prueba_becario`.
2. Inicia Apache y MySQL en XAMPP.
3. Importa `database.sql` en phpMyAdmin (BD: `albardi_portal`).
4. Edita `backend/config.php` con tus credenciales (no subas el real al repo).
5. Abre `http://localhost/prueba_becario/frontend/`.

## Credenciales de prueba
- Email: `admin@albardi.com`
- Password: `Admin123!`

## Endpoints
- `GET  /backend/api/servicios.php` — lista servicios activos (JSON)
- `POST /backend/api/servicios.php` — crea servicio (requiere sesión, JSON body)
- `GET  /backend/servicios/index.php` — listado (auth)
- `GET/POST /backend/servicios/create.php` — crear (auth)
- `GET/POST /backend/servicios/update.php?id=` — editar (auth)
- `POST /backend/servicios/delete.php` — eliminar (auth, CSRF)
- `GET/POST /backend/auth/login.php` — login
- `GET  /backend/auth/logout.php` — logout
- `GET/POST /backend/pagos/checkout.php` — pagos (auth)

## Criterios de evaluación
Ver `RUBRICA.md`. Total: 100 puntos.

## Notas
- Caracteres en UTF-8 (`.htaccess` con `AddDefaultCharset UTF-8`).
- Todos los SQL usan prepared statements; salidas escapadas con `e()`.
# Prueba_albardi
