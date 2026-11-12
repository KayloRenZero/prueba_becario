<?php

// 'development' muestra errores en pantalla; 'production' solo los registra en el log.
define('APP_ENV', 'development');

// Base de datos (MySQL / MariaDB)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'albardi_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// URL pública del proyecto, sin "/" final (se usa en las URLs de retorno de Stripe).
// Si se deja vacía, se deduce de la petición actual.
define('APP_URL', 'http://localhost/entrega_becario');

// Stripe Checkout en modo prueba (opcional). Vacía = solo se ofrece el pago simulado.
// Llave secreta de PRUEBA: dashboard de Stripe → Developers → API keys (empieza con "sk_test_").
// Nunca subas una llave real al repositorio: este archivo de ejemplo debe quedar vacío.
define('STRIPE_SECRET_KEY', '');
