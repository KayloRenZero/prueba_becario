# BUGS — qa/buggy.php

| # | Bug | Ubicación | Causa | Fix aplicado |
|---|-----|-----------|-------|--------------|
| 1 | SQL injection (concatenación directa) | línea 9 | `$busqueda` interpolado en SQL | Prepared statement con `LIKE :q` |
| 2 | XSS sin escapar | línea 14 | echo directo de nombre/email | `e()` (htmlspecialchars) |
| 3 | Validación faltante (email) | línea 18-23 | no se valida email | `filter_var(..., FILTER_VALIDATE_EMAIL)` |
| 4 | Lógica invertida | línea 27-32 | condición `< 18` permitía acceso | cambiado a `>= 18` |
| 5 | Typo de variable | línea 35-36 | `$usename` inexistente | `$username` |
