# BUGS — qa/buggy.php

## Contexto

El `qa/buggy.php` entregado **ya traía corregidos los 5 bugs intencionales** (comentarios `FIX BUG 1` a `FIX BUG 5`) y este archivo venía con su tabla resuelta. En vez de presentar esas correcciones como propias, el trabajo de QA consistió en:

1. **Verificar** con pruebas automatizadas que cada una de las 5 correcciones funciona.
2. **Auditar** el archivo corregido: se encontraron **4 bugs más (6–9)** que esas correcciones dejaron pasar. Se corrigieron sin cambiar la lógica de negocio.
3. **Reportar** como observaciones (sin corregir) los problemas cuya solución sí cambiaría la lógica de negocio.

Las líneas citadas corresponden a la versión final de `qa/buggy.php`.

## Resumen

| # | Bug | Severidad | Estado |
|---|-----|-----------|--------|
| 1 | Inyección SQL en la búsqueda | Crítica | Ya corregido · verificado |
| 2 | XSS almacenado al listar usuarios | Alta | Ya corregido · verificado |
| 3 | Email sin validar al crear ticket | Media | Ya corregido · verificado |
| 4 | Condición de edad invertida | Alta | Ya corregido · verificado |
| 5 | Typo en el nombre de variable | Baja | Ya corregido · verificado |
| 6 | **XSS reflejado en el saludo** | **Alta** | **Encontrado y corregido** |
| 7 | **Parámetros enviados como arreglo** | **Media** | **Encontrado y corregido** |
| 8 | **Asunto y mensaje sin validar** | **Media** | **Encontrado y corregido** |
| 9 | **Edad convertida con `(int)` permisivo** | **Baja** | **Encontrado y corregido** |

## Bugs intencionales (1–5): verificación

| # | Bug | Ubicación | Causa | Fix aplicado | Verificación |
|---|-----|-----------|-------|--------------|--------------|
| 1 | Inyección SQL | líneas 10–11 | `$busqueda` se concatenaba dentro del SQL | Prepared statement con `LIKE :q`; los `%` se agregan al valor, no al SQL | `q=' OR '1'='1` → 0 usuarios; `UNION SELECT … password` no expone hashes |
| 2 | XSS almacenado | línea 16 | `nombre` y `email` se imprimían sin escapar | `e()` = `htmlspecialchars(ENT_QUOTES \| ENT_SUBSTITUTE, 'UTF-8')` | Un usuario llamado `<script>alert("xss")</script>` se muestra como `&lt;script&gt;…` |
| 3 | Email sin validar | líneas 26–27 | Se creaban tickets con cualquier email | `filter_var($email, FILTER_VALIDATE_EMAIL)` | `no-es-un-email` → "Email inválido" y 0 filas nuevas |
| 4 | Lógica invertida | línea 46 | `< 18` daba acceso a menores | `>= 18` | 17 → denegado · 18 → permitido (límite) · 30 → permitido · sin edad → denegado |
| 5 | Typo de variable | líneas 54–55 | Se imprimía `$usename`, que no existe | `$username` | `user=Ana` → "Hola, Ana"; sin parámetro → "Hola, invitado" |

## Bugs encontrados en la auditoría (6–9)

### 6 · XSS reflejado en el saludo · Alta
- **Ubicación:** línea 55.
- **Causa:** el fix del bug 5 corrigió el nombre de la variable, pero no escapó su valor, que viene de la URL (`$_GET['user']`). Mientras el typo existía se imprimía una variable vacía. **Al corregirlo, el XSS quedó activo**: un bug enmascaraba al otro.
- **Impacto:** el enlace `qa/buggy.php?user=<script>…</script>` ejecuta JavaScript en el mismo origen que el panel. Si un administrador lo abre, el script puede pedir una página del panel, leer el token CSRF y crear o borrar servicios en su nombre. La cookie `HttpOnly` impide robar la sesión, pero no evita esto.
- **Fix:** `echo "Hola, " . e($username);`
- **Verificación:** `<script>alert(1)</script>` y `"><img src=x onerror=alert(1)>` se imprimen escapados.

### 7 · Parámetros enviados como arreglo · Media
- **Ubicación:** líneas 8, 22–24 y 54. Antes se leía directamente `$_GET['q']`, `$_GET['user']`, `trim($_POST['email'])` y `$_POST['asunto']`.
- **Causa:** PHP convierte `?q[]=x` en un array y el código asumía texto.
- **Impacto:**
  - `POST email[]=…` provocaba un `TypeError` en `trim()`, que terminaba en un **error 500**.
  - En los demás campos aparecía el aviso *"Array to string conversion"*, que con los errores visibles revela la ruta del servidor (`C:\xampp\htdocs\…`).
  - Se guardaban tickets con el asunto literal `"Array"`.
- **Fix:** se leen los parámetros con `queryString()` (nuevo en `backend/helpers.php`, gemelo de `postString()`) y con `postString()`. Si el valor no es texto, se trata como ausente.
- **Verificación:** `q[]=`, `user[]=`, `edad[]=`, `email[]=` y `asunto[]=` responden 200, sin avisos y sin crear tickets basura.

### 8 · Asunto y mensaje sin validar · Media
- **Ubicación:** líneas 30–33.
- **Causa:** solo se validaba el email; el asunto y el mensaje se insertaban tal cual.
- **Impacto:**
  - Se creaban tickets vacíos.
  - Un asunto de más de 150 caracteres (`VARCHAR(150)`) se **truncaba en silencio** en XAMPP, porque su `sql_mode` no es estricto, y se perdían datos. En un servidor en modo estricto, el predeterminado en MySQL 8, el mismo envío da el error 1406 y termina en un **500**.
- **Fix:**
  - Ambos campos son obligatorios después de `trim()`.
  - Asunto de hasta 150 caracteres, medido con `mb_strlen`: `VARCHAR` cuenta caracteres y una "ñ" ocupa 2 bytes.
  - Mensaje de hasta 65 535 bytes, el límite de `TEXT`.
- **Verificación:** un asunto vacío, con solo espacios o de 151 caracteres no crea ticket. Un asunto de exactamente 150 "ñ" se guarda completo.

### 9 · Edad convertida con `(int)` permisivo · Baja
- **Ubicación:** líneas 43–46.
- **Causa:** `(int)` toma los dígitos iniciales del texto: `"18abc"` → 18 y `"1e3"` → 1000. Un dato inválido podía **conceder** el acceso.
- **Fix:** `filter_input(INPUT_GET, 'edad', FILTER_VALIDATE_INT)` con un rango de 0 a 150, y `is_int()` antes de comparar. Ante un dato inválido se deniega (*fail closed*).
- **Verificación:** `18abc`, `1e3`, `999` y `18.9` → denegado; `18` → permitido.

## Observaciones (no corregidas: cambiarían la lógica de negocio)

| # | Observación | Riesgo | Recomendación |
|---|-------------|--------|---------------|
| O1 | La búsqueda muestra el nombre y el correo de **todos** los usuarios, incluido el administrador, a cualquier visitante sin sesión | Exposición de datos personales y enumeración de cuentas (OWASP A01) | Restringir con `requireAdmin()` o no mostrar los correos |
| O2 | Todos los tickets se guardan con `usuario_id = 1` (el administrador) y el email validado no se guarda en ningún lado | Tickets atribuidos a la persona equivocada | Tomar el usuario de la sesión (`requireLogin()` + `$_SESSION['user_id']`) o guardar el email |
| O3 | El `POST` no lleva token CSRF ni redirige después de crear el ticket (patrón PRG) | Si se aplica O2, otro sitio podría crear tickets en nombre del usuario; recargar la página duplica el ticket | Agregar `csrfField()` / `verifyCsrf()` y redirigir con 303 |
| O4 | Los mensajes salen pegados y sin estructura HTML (`Acceso denegadoHola, invitado`) | Solo presentación | Envolver cada mensaje en `<p>` dentro de una plantilla |

## Cómo reproducir

Con Apache y MySQL encendidos, desde la raíz del proyecto (en Windows, con Git Bash):

```bash
bash qa/tests/buggy_test.sh                  # MySQL en el puerto 3306
DB_PORT=3307 bash qa/tests/buggy_test.sh     # si tu MySQL usa otro puerto
```

El script hace 42 comprobaciones con peticiones HTTP reales y consultas a la BD. Crea filas temporales y las borra al terminar.

| Versión | Resultado |
|---------|-----------|
| `buggy.php` entregado | 23 OK · 19 fallidas (bugs 6–9) |
| `buggy.php` final | **42 OK · 0 fallidas** |
