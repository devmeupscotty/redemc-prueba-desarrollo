# RedEMC — Prueba de desarrollo

Formulario de registro de personas (nombre, email y teléfono) con validación en cliente y servidor, guardado por AJAX en MySQL y listado paginado dinámicamente, sin recargar la página.

**Stack:** HTML, CSS, Bootstrap 5.3, JavaScript (ES modules) + jQuery 4, PHP 8.3 y MySQL 8.4.

## Funcionalidades

- **Formulario** con Nombre\*, Email\* y Teléfono (opcional), con estilos propios en los inputs y estados válido/inválido.
- **Validación en vivo** al salir de cada campo y mientras se corrige; el servidor vuelve a validar todo (es la fuente de verdad).
  - Nombre: obligatorio (solo espacios cuenta como vacío), solo letras y espacios, admite tildes y ñ.
  - Email: obligatorio, formato estricto y único sin distinguir mayúsculas/minúsculas.
  - Teléfono: opcional; si se completa, celular uruguayo de 9 dígitos que empieza con `09` (ej: `097680102`) y único.
  - Cada error muestra un mensaje puntual sin borrar lo ya cargado en los demás campos.
- **Guardado por AJAX** con fecha de inserción (`created_at`) generada por la base.
- **Listado** de 8 registros por página con paginado dinámico; tras guardar se vuelve a la página 1 y el registro nuevo aparece primero, sin refrescar.
- **Responsive** y maquetado 100 % con `div` (sin tablas): grilla en escritorio y tarjetas en mobile.

## Estructura

```
public/            Única carpeta expuesta a la web (DocumentRoot)
  index.php        Front controller (2 líneas)
  .htaccess        Ruteo al front controller + headers de seguridad
  css/app.css      Estilos propios (tokens en variables CSS)
  js/validator.js  Validación del cliente, construida con las reglas del servidor
  js/app.js        API, formulario, listado y paginado
src/               Código PHP (fuera del DocumentRoot)
  App.php          Ruteo, armado de dependencias, respuestas JSON y manejo de errores
  Constants.php    Regex, mensajes, límites, códigos HTTP, rutas: todo valor con nombre
  Database.php     Conexión PDO a partir de variables de entorno
  Person.php       Objeto de valor inmutable
  PersonValidator.php   Normalización + validación
  PersonRepository.php  Acceso a datos (consultas preparadas)
  PersonController.php  Casos de uso: listar paginado y crear
templates/home.php Página HTML
database/schema.sql Estructura de la tabla
database/setup.php  Crea la base y la tabla (npm run db:setup)
tests/             PHPUnit (unit, integration, api) y Vitest
```

### Decisiones principales

- **Una sola fuente de verdad para las reglas.** Las expresiones regulares, mensajes y límites viven en `Constants.php`. La página los recibe embebidos como JSON y el JavaScript arma sus validaciones a partir de ellos, sin duplicarlos.
- **Unicidad garantizada por la base.** Índices `UNIQUE` en `email` y `phone`. El email se guarda en minúsculas y el teléfono vacío como `NULL` (MySQL admite múltiples `NULL` en un índice único). El controlador verifica duplicados antes de insertar para dar un mensaje claro, y si dos envíos simultáneos chocan, el error de clave duplicada de MySQL se traduce al mismo mensaje.
- **Paginado en el servidor** con `LIMIT/OFFSET` tipados y orden por `id DESC`; el listado no carga toda la tabla.
- **Fechas en UTC** en la base; el navegador las muestra en hora de Uruguay.

## Seguridad

- Solo `public/` es accesible desde la web; el código, las plantillas, `vendor/` y `.env` quedan fuera.
- Consultas preparadas con PDO (sin emulación) contra inyección SQL.
- Los datos se insertan en el DOM únicamente con `.text()`; además hay Content-Security-Policy.
- Credenciales solo en variables de entorno.
- Errores inesperados: se registran en el log y el cliente recibe un mensaje genérico.
- Bootstrap y jQuery se cargan desde CDN con Subresource Integrity.
- Headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`; `php.ini` de producción.

## Desarrollo local

**Requisitos:** PHP 8.3 (con `pdo_mysql`, `mbstring` e `intl`), Composer, Node.js y un servidor MySQL 8 accesible (local o, como en este proyecto, el de Railway mediante su acceso público).

```bash
git clone https://github.com/devmeupscotty/redemc-prueba-desarrollo.git
cd redemc-prueba-desarrollo
npm install          # instala dependencias de JS y, automáticamente, las de PHP (composer install)
cp .env.example .env # completar DB_HOST, DB_PORT, DB_USER y DB_PASSWORD
npm run db:setup     # crea la base indicada en DB_NAME (si no existe) y la tabla person
npm run dev          # http://localhost:8000
```

| Script | Descripción |
|---|---|
| `npm install` | Dependencias de Node y de Composer |
| `npm run db:setup` | Crea la base de `DB_NAME` y aplica `database/schema.sql` |
| `npm run dev` | Servidor de desarrollo en `localhost:8000` |
| `npm test` | Todos los tests (PHP y JavaScript) |

La instancia de MySQL tiene tres bases: `railway` (producción), `redemc_dev` (desarrollo local) y `redemc_test` (tests; se crea sola al correr los tests).

## Tests

```bash
npm test
```

Ejecuta PHPUnit y Vitest:

- **Unit (PHP):** validador (casos válidos e inválidos por campo, normalización, errores combinados) y controlador con repositorio simulado.
- **Integración (PHP):** repositorio contra MySQL real (orden, paginado, unicidad sin distinguir mayúsculas, teléfonos `NULL`, colisiones de clave).
- **API (PHP):** levanta el servidor embebido y prueba por HTTP: alta → el total aumenta en uno y el registro queda primero, cada error devuelve 422 con su mensaje, 405/404, paginado y que no se expongan archivos privados.
- **JavaScript (Vitest + jsdom):** paridad del validador con las reglas del servidor, renderizado seguro, paginado y comportamiento del formulario.

Los casos de validación están en `tests/fixtures/validation-cases.json` y los usan tanto los tests de PHP como los de JavaScript. Los tests de base de datos usan `DB_TEST_NAME` y se niegan a correr si el nombre no termina en `_test`, para no tocar datos reales.

## Deploy en Railway

La aplicación corre en un contenedor (`Dockerfile`: PHP 8.3 + Apache) y la base es un servicio MySQL del mismo proyecto.

1. **Crear el proyecto y la base:** en Railway, *New Project → Database → MySQL*.
2. **Crear el servicio de la app:** en el mismo proyecto, *New → GitHub Repo* y elegir este repositorio. En *Settings* se configura la rama **`main`**; Railway detecta el `Dockerfile` y lo usa para construir.
3. **Variables del servicio de la app** (referencias al servicio MySQL, conexión por red privada):

   | Variable | Valor |
   |---|---|
   | `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
   | `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
   | `DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
   | `DB_USER` | `${{MySQL.MYSQLUSER}}` |
   | `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |

4. **Dominio público:** en *Settings → Networking → Generate Domain*.
5. **Crear la tabla:** ejecutar `database/schema.sql` sobre la base `railway`. Para hacerlo desde la PC, se habilita *Public Access* en el servicio MySQL y se conecta un cliente (por ejemplo DBeaver) con el host y puerto públicos.
6. Cada push a `main` dispara un nuevo deploy.

### Flujo de trabajo

`main` (producción) ← `develop` ← ramas `feature/*` y `fix/*`, integradas con merges `--no-ff`.
