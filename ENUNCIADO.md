# Enunciado de la Prueba

## Módulo 1 — Frontend (25 pts) · ~90 min
**Objetivo:** Maquetar el "Portal de Clientes" de Grupo Albardi.

Crea `frontend/index.html`, `frontend/css/styles.css` y `frontend/js/app.js`.

**Requisitos:**
1. Página responsiva (mobile-first) con:
   - Header con logo/nombre "Grupo Albardi" y menú.
   - Hero con título y CTA.
   - Sección de servicios (tarjetas).
   - Formulario de contacto.
   - Footer.
2. El formulario debe validar en JS:
   - Nombre: requerido, min 3 caracteres.
   - Email: formato válido.
   - Teléfono: exactamente 10 dígitos.
   - Mensaje: requerido, min 10 caracteres.
   - Mostrar errores inline y no enviar si hay errores.
3. Consumir `backend/api/servicios.php` (GET) y renderizar tarjetas dinámicamente.
4. Usar al menos una animación CSS (hover/fade).

**Entrega esperada:** HTML semántico, CSS sin frameworks (o con Bootstrap si prefieres, justificar), JS vanilla.

## Módulo 2 — Backend PHP + MySQL (30 pts) · ~120 min
**Objetivo:** CRUD de servicios + autenticación.

1. Diseñar BD `albardi_portal` (ver `database.sql` base, puedes ampliar).
2. `backend/config.php`: credenciales y constantes.
3. `backend/db.php`: conexión PDO con manejo de errores.
4. `backend/auth/login.php`: login con `password_verify`, sesión, redirección.
5. `backend/auth/logout.php`.
6. CRUD de servicios en `backend/servicios/` (index/create/update/delete) con prepared statements.
7. `backend/api/servicios.php`: endpoint JSON (GET lista, POST crear con token de sesión).
8. Protección: SQLi (prepared statements), XSS (`htmlspecialchars`), CSRF token básico en formularios.

**Credenciales semilla:** usuario `admin@albardi.com` / password `Admin123!`.

## Módulo 3 — QA / Debugging (15 pts) · ~45 min
Se entrega `qa/buggy.php` con 5 bugs intencionales. Debes:
1. Detectarlos.
2. Corregirlos.
3. Documentar en `qa/BUGS.md`: bug, ubicación, causa, fix aplicado.

No modifiques la lógica de negocio, solo corrige los bugs.

## Módulo 4 — Git (10 pts) · ~30 min
1. `git init`, `.gitignore` correcto (ignorar config con credenciales, vendor, etc.).
2. Rama `feature/login` para el módulo de auth; commits atómicos con mensajes convencionales (`feat:`, `fix:`, `docs:`).
3. Merge a `main` (o `master`) con mensaje descriptivo.
4. Al menos 8 commits en total.

## Módulo 5 — Integración API / Pagos (15 pts) · ~60 min
**Objetivo:** Simular compra de un servicio.

1. `backend/pagos/checkout.php`: recibe `servicio_id`, crea registro en `pagos` con estado `pendiente`.
2. Simular confirmación (botón "Simular pago exitoso") que actualiza estado a `pagado` y genera `referencia`.
3. Mostrar historial de pagos del usuario logueado.
4. (Deseable) Integrar Stripe Checkout en modo test; si no tienes cuenta, documenta los pasos.

## Módulo 6 — Documentación (5 pts) · ~15 min
- `README.md` completo (instalación, estructura, credenciales de prueba, endpoints).
- Bitácora en `docs/bitacora.md` (qué hiciste, problemas y soluciones).
- Comentarios solo donde aporten valor.

## Reglas
- No uses frameworks pesados (Laravel, React) salvo que justifiques.
- Código en español o inglés, consistente.
- No subas credenciales reales.
