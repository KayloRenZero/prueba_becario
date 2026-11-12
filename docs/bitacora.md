# Bitácora

Registro del desarrollo de la prueba: qué se hizo en cada módulo, qué decisiones se tomaron y qué problemas aparecieron. Las fechas salen del historial de Git.

## Resumen

| Fecha | Módulo | Rama / PR | Resultado verificado |
|-------|--------|-----------|----------------------|
| 2026-11-10 | Inicio | `main` | Paquete de la prueba versionado (*first commit*) |
| 2026-11-11 | 1 · Frontend | `feature/frontend` | 30 pruebas unitarias y 44 comprobaciones en navegador |
| 2026-11-11 | Arreglo | `fix/db-port` | Conexión a MySQL con puerto configurable |
| 2026-11-11 | 2 · Backend | `feature/login`, `feature/servicios-crud` | 76 pruebas HTTP contra Apache y MariaDB |
| 2026-11-11 | 3 · QA | `fix/qa-buggy` (PR #5) | 42 pruebas HTTP: 23 pasaban antes y 42 después |
| 2026-11-11 | 5 · Pagos | `feature/pagos` (PR #6) | 64 + 36 pruebas HTTP, 11 en navegador y regresión del Módulo 2 |
| 2026-11-11 | 6 · Docs | `docs/documentacion` | README y bitácora |

---

## Módulo 1 · Frontend

**Qué se hizo**
- Maquetado semántico: `header`, `nav`, `main`, `section`, `footer`, enlace "Saltar al contenido" y plantillas `<template>` para las tarjetas.
- Diseño *mobile-first* con variables CSS (colores, espaciados, sombras), menú hamburguesa accesible y animaciones (*fade-up*, *hover*, *skeleton*) que respetan `prefers-reduced-motion`.
- Consumo de la API con estados de **carga, vacío y error** (con botón "Reintentar"). Además: límite de tiempo de red (`AbortSignal.timeout`), comprobación de `response.ok` y validación de la forma de la respuesta.
- Validación del formulario con reglas declarativas. Muestra errores *inline* enlazados con `aria-invalid` y `aria-describedby`. El campo se valida al salir de él, y una vez marcado con error se revalida mientras se escribe.
- JavaScript en módulos ES (`validators`, `services`, `contact-form`, `menu`) y pruebas con `node:test`, sin dependencias.

**Decisiones**
- **Sin Bootstrap:** el diseño es pequeño y las variables CSS cubren lo necesario, sin cargar ni sobrescribir una librería.
- **`textContent` en lugar de `innerHTML`** para los datos de la API: un nombre con `<script>` se muestra como texto.

**Problemas y soluciones**

| Problema | Causa | Solución |
|----------|-------|----------|
| En móvil, una palabra muy larga ensanchaba la columna y aparecía scroll horizontal | `1fr` no baja de lo que mide el contenido (*grid blowout*) | `minmax(0, 1fr)` y `overflow-wrap: anywhere` en las tarjetas |
| La prueba de desbordamiento pasaba aunque había desbordamiento | `innerWidth` crece junto con el contenido | Comparar `scrollWidth` contra el ancho fijo del dispositivo (375 px) |

## Arreglo · Conexión a MySQL

| Problema | Causa | Solución |
|----------|-------|----------|
| La API respondía error de conexión aunque las credenciales eran correctas | En el equipo había dos servidores: el servicio *MySQL80* de Windows ocupaba el 3306 y la MariaDB de XAMPP escuchaba en otro puerto | Constante opcional `DB_PORT` en `config.php` (3306 por defecto, para no afectar a otras instalaciones) |

## Módulo 2 · Backend

**Qué se hizo**
- **Base de datos:**
  - Restricciones `CHECK` (precio y monto ≥ 0), correo y referencia de pago únicos, índice por servicio activo y claves foráneas (`RESTRICT` en pagos para conservar el historial).
  - `DECIMAL` para el dinero.
  - Usuario semilla `cliente` para probar los roles.
  - Script reproducible (`DROP` + `CREATE`).
- **Configuración:** `config.example.php` versionado y `config.php` ignorado por git.
- **Autenticación:**
  - Login con `password_verify`, rehash automático y mensaje genérico.
  - Verificación contra un hash ficticio, para que la respuesta tarde lo mismo exista o no el correo.
  - `session_regenerate_id(true)`, cookie `HttpOnly` + `SameSite=Lax` y cierre por 30 min de inactividad.
  - Guardas por rol y logout solo por `POST` con CSRF.
- **CRUD:**
  - Validación y SQL en `lib/servicios.php`, compartidos por el panel y la API.
  - Un formulario único para crear y editar.
  - Patrón PRG con redirecciones `303` y mensajes flash.
  - 404 si el id no existe.
  - Eliminar un servicio con pagos se bloquea (error MySQL 1451) y se sugiere desactivarlo.
- **API:** códigos HTTP precisos (`201`, `400`, `401`, `403`, `405`, `415`, `422`, `500`) y CSRF en la cabecera `X-CSRF-Token`. El contrato del `GET` no cambió, así que el frontend siguió funcionando sin tocarlo.
- **Servidor:** `.htaccess` que responde 403 a `config.php`, `db.php`, `helpers.php`, `lib/` y `views/`. Manejador global de errores: el detalle va al log y el usuario ve un mensaje genérico.

**Problemas y soluciones**

| Problema | Causa | Solución |
|----------|-------|----------|
| En móvil, la página del panel medía 865 px de ancho | Los textos `.visually-hidden` (`position: absolute`) dentro de la tabla se posicionaban respecto a la página y escapaban del `overflow` del contenedor | `position: relative` en `.table-wrap` |
| El arreglo anterior "no funcionaba" | El navegador seguía usando el CSS viejo en caché | `asset()` agrega `?v=<filemtime>` a los archivos. `time()` habría anulado la caché en cada petición |
| La API respondía 400 a un servicio con tildes durante las pruebas | Git Bash en Windows pasaba los argumentos a `curl` en Windows-1252, no en UTF-8 | La API hacía lo correcto (rechaza texto que no es UTF-8). Se corrigió el script de prueba para enviar el cuerpo desde un archivo UTF-8 |
| "Cerrar sesión" se partía en dos líneas en móvil | Faltaba espacio en la barra superior | Ocultar la etiqueta "Portal" en pantallas de menos de 480 px |

## Módulo 3 · QA

**Hallazgo inicial:** el `qa/buggy.php` entregado **ya traía corregidos** los 5 bugs (comentarios `FIX BUG 1–5`), y `BUGS.md` y esta bitácora venían llenos. En lugar de presentar esas correcciones como propias:
1. Se **verificó** con pruebas que las 5 correcciones funcionan.
2. Se **auditó** el archivo corregido y se encontraron y corrigieron **4 bugs más**:
   - XSS reflejado en el saludo.
   - Parámetros enviados como arreglo, que provocaban un error 500 y revelaban la ruta del servidor.
   - Asunto y mensaje sin validar.
   - Edad convertida con `(int)` de forma permisiva.
3. Se **reportaron sin corregir** los problemas cuya solución cambiaría la lógica de negocio (por ejemplo, la lista de correos visible sin iniciar sesión), tal como pide el enunciado.

Detalle completo en [qa/BUGS.md](../qa/BUGS.md).

**Problemas y soluciones**

| Problema | Causa | Solución |
|----------|-------|----------|
| Al corregir el typo del bug 5 apareció un XSS | Mientras el typo existía se imprimía una variable vacía. Al corregirlo, el valor de la URL llegó sin escapar: un bug **enmascaraba** al otro | Escapar con `e()` y documentarlo como bug 6 |
| Un asunto de 151 caracteres se guardaba recortado en lugar de dar error | El `sql_mode` de XAMPP no es estricto, así que MariaDB trunca en silencio | Validar la longitud en PHP antes del `INSERT`. En MySQL 8, que es estricto, el mismo caso daba un 500 |
| El script de pruebas `.sh` podía romperse al clonar el repo en Windows | Con `core.autocrlf=true`, Git lo convertiría a CRLF y bash falla con `$'\r'` | `.gitattributes` con `*.sh text eol=lf` |

## Módulo 4 · Git

- Ramas por módulo, commits atómicos con Conventional Commits y merges con mensaje descriptivo. `main` tiene más de 30 commits sin contar los merges.
- **Problema:** los PRs de `fix/db-port` y del Módulo 2 no se podían crear ("There isn't anything to compare").
  - **Causa:** los merges se hicieron en local y se subió `main` antes de abrir los PRs, así que las ramas ya no tenían commits que `main` no tuviera.
  - **Solución:** mover temporalmente `main` en GitHub al estado posterior al Módulo 1 (`git push --force-with-lease origin <commit>:main`). Después se abrió cada PR y se volvió a avanzar `main` un merge a la vez. GitHub marcó cada PR como *Merged*, y los commits quedaron con los mismos hashes.
  - **Lección:** abrir el PR **antes** de fusionar. Desde el Módulo 3, el merge se hace en GitHub con *Create a merge commit*.

## Módulo 5 · Pagos

**Qué se hizo**
- **Máquina de estados** `pendiente → pagado | fallido`. Las transiciones son atómicas (`UPDATE … WHERE estado = 'pendiente'`), así que dos clics no pagan dos veces.
- `checkout.php` rehecho:
  - `GET ?servicio_id=` solo muestra la confirmación.
  - Crear, confirmar y rechazar van por `POST` con CSRF y patrón PRG.
  - Si ya hay un pago pendiente del mismo servicio, se reutiliza.
  - El precio se congela al comprar.
  - La referencia `SIM-` sale de `random_bytes` y se reintenta si choca con otra existente.
  - Incluye un historial con folio, estado, referencia y fecha.
- **Aislamiento entre usuarios (IDOR):** cada consulta filtra por el usuario de la sesión.
- **Stripe Checkout en modo prueba**, sin SDK: se activa solo con una llave `sk_test_`. El servidor verifica con Stripe el estado, el pago, el usuario, el monto en centavos y la moneda. Usa una cabecera `Idempotency-Key` y avisa al usuario si Stripe no responde. Detalle en [docs/stripe.md](stripe.md).
- **Flujo completo desde el sitio:** el botón "Contratar" lleva al checkout. Si no hay sesión, después del login se vuelve a esa compra. La URL se guarda en la sesión para evitar redirecciones abiertas.

**Problemas y soluciones**

| Problema | Causa | Solución |
|----------|-------|----------|
| El checkout original creaba pagos por `GET` | Un enlace o el *prefetch* del navegador podían crear pagos (CSRF) | GET solo muestra la confirmación; el pago se crea por POST con token |
| Un pago pagado podía "pagarse" de nuevo y cambiar su referencia | El `UPDATE` no exigía el estado `pendiente` | Condición de estado dentro del `UPDATE` y comprobación de `rowCount()` |
| El checkout usaba `$pdo->query()` | Consulta sin preparar (penalización en la rúbrica) | `listServices()` con consulta preparada. Una prueba verifica que no quede ningún `->query(` en `backend/` |
| Probar Stripe sin cuenta | No había llave de prueba disponible | Simulador local de la API (36 casos). Se verificó la conexión TLS real con `api.stripe.com` (responde 401 con una llave inválida) |
| El botón "Contratar" no aparecía al revisar | Se estaba viendo `main` antes de fusionar el PR: Apache sirve la rama que está activa en la carpeta | Probar en la rama del PR y recargar con `Ctrl + Shift + R` |
| En tarjetas angostas, "Me interesa" y "Contratar" se apilaban | Les faltaban unos 2 px para caber en la misma fila | `flex: 1 1 0` con `white-space: nowrap` en los botones. Verificado a 1280, 800, 375 y 320 px |

**Regresión:** antes de fusionar se volvieron a correr las pruebas del Módulo 2 (76/76) contra una **copia temporal** de la base de datos, para no tocar los datos existentes, y las del frontend (44/44).

## Mejoras pendientes

- **Login:** limitar los intentos fallidos por correo e IP (protección contra fuerza bruta).
- **Stripe:** webhook `checkout.session.completed` con verificación de firma, para confirmar pagos aunque el usuario cierre la pestaña.
- **QA:** decidir con el negocio las observaciones O1–O3 de `qa/BUGS.md` (acceso a la búsqueda de usuarios, tickets atribuidos al usuario 1 y CSRF en el formulario de tickets).
- **Pruebas:** llevar al repositorio las pruebas de integración de los Módulos 2 y 5, con una base de datos de pruebas propia, y ejecutarlas en CI.
