#!/usr/bin/env bash
# Pruebas de QA de qa/buggy.php: peticiones HTTP reales (curl) contra Apache + MySQL.

BASE_URL=${BASE_URL:-http://localhost/entrega_becario}
if [ -z "$MYSQL_BIN" ]; then
  if [ -x /c/xampp/mysql/bin/mysql.exe ]; then MYSQL_BIN=/c/xampp/mysql/bin/mysql.exe; else MYSQL_BIN=mysql; fi
fi
export MYSQL_PWD=${DB_PASS:-}

URL="$BASE_URL/qa/buggy.php"
MYSQL=("$MYSQL_BIN" -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USER:-root}" "${DB_NAME:-albardi_portal}" -N -B)
XSS_EMAIL="qa-xss@example.test"

pass=0; fail=0
ok() { printf '  OK     %s\n' "$1"; pass=$((pass + 1)); }
ko() { printf '  FALLA  %s\n' "$1"; fail=$((fail + 1)); }

req() {
  local out
  out=$(curl -s -g -w $'\n%{http_code}' "$@")
  CODE=${out##*$'\n'}
  BODY=${out%$'\n'*}
}
get()  { req "$URL?$1"; }
post() { req -X POST --data-raw "$1" "$URL"; }

contains() { [[ "$BODY" == *"$1"* ]]; }
not()      { ! "$@"; }
eq()       { [ "$1" = "$2" ]; }
clean()    { [ "$CODE" = 200 ] && ! contains "Warning" && ! contains "Fatal" && ! contains "Notice" && ! contains "Deprecated" && ! contains "Uncaught" && ! contains "htdocs"; }
listed()   { grep -o '<p>Usuario:' <<<"$BODY" | wc -l | tr -d ' '; }
sql()      { "${MYSQL[@]}" -e "$1"; }
created()  { sql "SELECT COUNT(*) FROM tickets WHERE id > $START"; }

t() {
  local desc=$1; shift
  if "$@"; then ok "$desc"; else ko "$desc   [HTTP $CODE] $(head -c 220 <<<"$BODY" | tr '\n' ' ')"; fi
}

if ! START=$(sql "SELECT COALESCE(MAX(id), 0) FROM tickets"); then
  echo "No se pudo conectar a MySQL. Revisa DB_PORT / DB_USER / DB_PASS / MYSQL_BIN." >&2
  exit 2
fi
cleanup() {
  sql "DELETE FROM tickets WHERE id > $START; DELETE FROM usuarios WHERE email = '$XSS_EMAIL'; ALTER TABLE tickets AUTO_INCREMENT = 1; ALTER TABLE usuarios AUTO_INCREMENT = 1;"
}
trap cleanup EXIT

echo "== Bug 1 · Inyección SQL en la búsqueda"
get "q=";      t "búsqueda vacía lista a los 2 usuarios semilla" eq "$(listed)" 2
get "q=Admin"; t "q=Admin lista exactamente 1 usuario" eq "$(listed)" 1
req -G --data-urlencode "q=' OR '1'='1" "$URL"
t "q=' OR '1'='1 no devuelve usuarios (se busca como texto)" eq "$(listed)" 0
t "  …y no rompe la consulta" clean
req -G --data-urlencode "q=%' UNION SELECT 1, password, 3 FROM usuarios -- " "$URL"
t "UNION SELECT no expone hashes de contraseñas" not contains '$2y$'

echo "== Bug 2 · XSS almacenado al listar usuarios"
sql "INSERT INTO usuarios (nombre, email, password, rol) VALUES ('<script>alert(\"xss\")</script>', '$XSS_EMAIL', 'x', 'cliente');"
get "q=script"
t "un nombre con <script> se imprime escapado" contains '&lt;script&gt;'
t "  …y no llega como etiqueta ejecutable" not contains '<script>alert'
sql "DELETE FROM usuarios WHERE email = '$XSS_EMAIL';"

echo "== Bug 3 · Validación del email al crear ticket"
before=$(created)
post "email=no-es-un-email&asunto=QA&mensaje=Prueba"
t "email inválido responde 'Email inválido'" contains "Email inválido"
t "  …y no crea ticket" eq "$(created)" "$before"
post "email=qa%40example.test&asunto=QA-TEST&mensaje=Prueba%20de%20QA"
t "email válido + asunto + mensaje crea el ticket" contains "Ticket creado"
t "  …y queda guardado en la BD" eq "$(created)" "$((before + 1))"

echo "== Bug 4 · Regla de mayoría de edad (>= 18)"
get "edad=17"; t "edad=17 → denegado" contains "Acceso denegado"
get "edad=18"; t "edad=18 → permitido (límite incluido)" contains "Acceso permitido"
get "edad=30"; t "edad=30 → permitido" contains "Acceso permitido"
get "";        t "sin edad → denegado" contains "Acceso denegado"
get "edad=-5"; t "edad=-5 → denegado" contains "Acceso denegado"

echo "== Bug 5 · Saludo"
get "";         t "sin parámetro saluda a 'invitado'" contains "Hola, invitado"
get "user=Ana"; t "user=Ana → 'Hola, Ana'" contains "Hola, Ana"

echo "== Bug 6 · XSS reflejado en el saludo"
req -G --data-urlencode "user=<script>alert(1)</script>" "$URL"
t "user=<script>… no se ejecuta" not contains "<script>alert(1)"
t "  …se muestra escapado" contains "&lt;script&gt;"
req -G --data-urlencode 'user="><img src=x onerror=alert(1)>' "$URL"
t "inyección de etiqueta <img onerror> escapada" not contains "<img"

echo "== Bug 7 · Parámetros que llegan como arreglo (q[]=, user[]=, email[]=)"
get "q[]=x";     t "q[]=x no genera avisos ni error" clean
                 t "  …se trata como búsqueda vacía" eq "$(listed)" 2
get "user[]=x";  t "user[]=x no genera avisos" clean
                 t "  …saluda a 'invitado'" contains "Hola, invitado"
get "edad[]=30"; t "edad[]=30 no genera avisos" clean
                 t "  …y no concede acceso" contains "Acceso denegado"
before=$(created)
post "email[]=qa%40example.test&asunto=QA&mensaje=QA"
t "POST email[]=… no provoca error 500 (TypeError en trim)" clean
t "  …responde 'Email inválido'" contains "Email inválido"
post "email=qa%40example.test&asunto[]=QA&mensaje=QA"
t "POST asunto[]=… no genera avisos" clean
t "  …y no crea un ticket con asunto 'Array'" eq "$(created)" "$before"

echo "== Bug 8 · Asunto y mensaje sin validar"
before=$(created)
post "email=qa%40example.test&asunto=&mensaje=Prueba"
t "asunto vacío no crea ticket" eq "$(created)" "$before"
post "email=qa%40example.test&asunto=%20%20%20&mensaje=Prueba"
t "asunto con solo espacios no crea ticket" eq "$(created)" "$before"
post "email=qa%40example.test&asunto=QA&mensaje="
t "mensaje vacío no crea ticket" eq "$(created)" "$before"
post "email=qa%40example.test&asunto=$(printf 'a%.0s' {1..151})&mensaje=QA"
t "asunto de 151 caracteres no se trunca en silencio (no crea ticket)" eq "$(created)" "$before"
t "  …responde sin error 500" clean
post "email=qa%40example.test&asunto=$(printf '%%C3%%B1%.0s' {1..150})&mensaje=QA"
t "asunto de exactamente 150 'ñ' (multibyte) sí se acepta" contains "Ticket creado"
t "  …y se guarda completo (150 caracteres)" eq "$(sql "SELECT CHAR_LENGTH(asunto) FROM tickets WHERE id > $START ORDER BY id DESC LIMIT 1")" 150

echo "== Bug 9 · Edad que no es un entero válido"
get "edad=18abc"; t "edad=18abc → denegado (no se convierte a 18)" contains "Acceso denegado"
get "edad=1e3";   t "edad=1e3 → denegado (no se convierte a 1000)" contains "Acceso denegado"
get "edad=999";   t "edad=999 → denegado (fuera de rango)" contains "Acceso denegado"
get "edad=18.9";  t "edad=18.9 → denegado (se esperan años enteros)" contains "Acceso denegado"

echo
echo "Resultado: $pass OK · $fail fallidas"
[ "$fail" -eq 0 ]
