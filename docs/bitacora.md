# Bitácora de cambios

## Fecha: 2026-11-10
- Inicialización del proyecto.
- Módulo 1: maquetado portal.
- Módulo 2: CRUD servicios + auth.
- Módulo 3: QA bugs corregidos (5).
- Módulo 4: Git.
- Módulo 5: pagos simulados.
- Módulo 6: documentación.

## Problemas y soluciones
- Caracteres no se leían bien: archivos en UTF-8 sin BOM + `.htaccess` con `AddDefaultCharset UTF-8` y `default_charset`.
- config.php incompleto: se agregaron APP_URL, APP_CHARSET, CSRF_KEY.
- db.php: manejo de errores en conexión PDO.
- qa/buggy.php: 5 bugs corregidos (SQLi, XSS, validación, lógica, typo).
