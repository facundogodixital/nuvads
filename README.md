# Nuvads

Laravel 13 con Vue 3 en JavaScript, dentro del mismo proyecto. Vite 8 compila
el frontend mediante los plugins oficiales de Laravel y Vue.

La instalación incluye únicamente el framework Laravel, Vue, Vite, sus plugins
y las dependencias que necesitan. Las herramientas opcionales quedan pendientes
de decidir.

## Servicios

Los puertos publicados escuchan únicamente en `127.0.0.1`.

| Servicio | Versión | Desde la computadora | Desde los contenedores |
| --- | --- | --- | --- |
| Nginx | 1.30 | https://app.nuvads.test:8443 | nginx:443 |
| PHP-FPM | 8.4 | No publicado | php:9000 |
| Node | 24 LTS | 127.0.0.1:5280, con `make dev` | node:5280 |
| MySQL | 8.4 | 127.0.0.1:3310 | mysql:3306 |
| MongoDB | 8.0 | 127.0.0.1:27020 | mongodb:27017 |
| Redis | 8 | 127.0.0.1:6390 | redis:6379 |

PHP incluye Composer 2 y las extensiones PDO MySQL, MongoDB y Redis.
El código se monta en `/var/www/html`; PHP y Node trabajan con el UID/GID local
definido en `.env.docker`.

## Configuración local

`.env.docker` contiene los puertos y credenciales locales y está excluido de Git.
`.env.docker.example` documenta las variables, sin contraseñas.
Para reproducir el entorno, copiar el ejemplo a `.env.docker`, completar las
contraseñas y ajustar `LOCAL_UID` / `LOCAL_GID` al resultado de `id -u` / `id -g`.

`.env` contiene la configuración de Laravel y su `APP_KEY`; también está excluido
de Git. PHP recibe las conexiones y credenciales de las bases desde Compose,
por lo que no hace falta duplicar las contraseñas de `.env.docker` en `.env`.

La caché y las sesiones utilizan archivos dentro de `storage/`, según
`CACHE_STORE=file` y `SESSION_DRIVER=file` en `.env.example`.

La base y el usuario de aplicación se llaman `nuvads` tanto en MySQL como en
MongoDB. En MongoDB, el usuario de aplicación se autentica contra la base
`nuvads`; `nuvads_admin` es el administrador, que se autentica contra `admin`.
Redis utiliza la contraseña `REDIS_PASSWORD`.

Los usuarios y contraseñas de MySQL y MongoDB se inicializan cuando sus
carpetas de datos están vacías. Cambiar `.env.docker` no cambia automáticamente
los usuarios de una base ya inicializada.

## HTTPS local

La URL local es `https://app.nuvads.test:8443`. El puerto publicado se define con
`HTTPS_PORT=8443` en `.env.docker`; dentro del contenedor Nginx escucha en 443.
Clienty no necesita cambios ni tiene que estar encendido.

En Ubuntu, preparar el certificado con [mkcert](https://github.com/FiloSottile/mkcert):

```bash
sudo apt-get install -y mkcert libnss3-tools
mkcert -install
mkdir -p docker/nginx/certs
mkcert -cert-file docker/nginx/certs/nuvads-local.pem -key-file docker/nginx/certs/nuvads-local-key.pem '*.nuvads.test' '*.nuvads.ai'
```

Agregar una única entrada en `/etc/hosts`:

```text
127.0.0.1 app.nuvads.test
```

Los certificados quedan fuera de Git y del contexto de construcción de Docker.
Nginx monta `docker/nginx/certs/` en `/etc/nginx/certs/` en modo de solo lectura;
Vite usa el mismo certificado desde el proyecto. La autoridad local de mkcert
permanece en la computadora y no se copia al contenedor. Puede ser necesario
reiniciar el navegador para que reconozca la confianza instalada.

Después ejecutar `make up` y reiniciar `make dev` si estaba activo.
El HTTP existente sigue disponible en el puerto `WEB_PORT=8080`.

Para usar HTTPS sin puerto en la URL, primero liberar el puerto 443 de la
computadora. Cambiar `HTTPS_PORT=443` en `.env.docker` y
`APP_URL=https://app.nuvads.test` en `.env`; ejecutar `make up`,
`docker compose --env-file .env.docker --file compose.yaml exec -T php php artisan config:clear`
y reiniciar `make dev`. Para volver a 8443, restaurar ambos valores y repetir
estos pasos. Solo se publica el puerto HTTPS elegido.

El dominio local habitual es `app.nuvads.test`. El certificado compartido cubre
`*.nuvads.test` y `*.nuvads.ai` simultáneamente (un nivel de subdominio; no los
dominios raíz). Para probar otro subdominio, apuntarlo a `127.0.0.1` en
`/etc/hosts`, actualizar `APP_URL`, limpiar la caché de configuración de Laravel
y reiniciar `make dev`. Vite toma el dominio de `APP_URL`, sin editar su código.
No hace falta regenerar el certificado ni cambiar Nginx. CORS conserva la
configuración del plugin de Laravel; el certificado no agrega orígenes CORS.
Retirar la entrada `.ai` de `/etc/hosts` cuando se quiera acceder a producción.
Esta configuración y estos certificados son exclusivamente locales y no cambian
el DNS público. El dominio previsto para la web principal es `nuvads.ai`.

## Acceso del titular con Google

Configurar un cliente OAuth de tipo aplicación web en Google Console y registrar
exactamente este URI de redirección para el entorno local:

```text
https://app.nuvads.test:8443/auth/google/callback
```

Completar las credenciales en `.env`:

```dotenv
GOOGLE_CLIENT_ID=""
GOOGLE_CLIENT_SECRET=""
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

Si la configuración estaba cacheada, ejecutar `php artisan config:clear` dentro
de `make web`. Abrir la aplicación y elegir «Continuar con Google».

El primer acceso crea el cliente y su usuario titular en una transacción. El
identificador combina el usuario del email y la primera parte del dominio con
guiones: `pepito.perez@lala.co.uk` se convierte en `pepito-perez-lala`. Si está
ocupado se agrega `-2`, `-3`, etc. El identificador no cambia en accesos posteriores.

Las rutas OAuth están en `routes/web.php`: `/auth/google/redirect` y
`/auth/google/callback`, con sesión temporal y validación de `state`. El botón de
Google genera un secreto en `sessionStorage` y envía su hash como `challenge` al
iniciar el flujo. El callback devuelve a `/login/callback` con un código en el
fragmento de la URL; no incluye el token de acceso. El código dura 60 segundos,
solo puede usarse una vez y su canje exige el secreto del navegador original.
Se guarda temporalmente en la caché existente, sin una tabla propia.

### API y navegación

- `routes/api.php` contiene `/api/*`. La API devuelve JSON incluso en errores y
  no autentica mediante cookies o sesiones web.
- `POST /api/auth/exchange`: canjea `code` y `verifier` por `token` y `expires_at`.
- `GET /api/auth/me`: devuelve el usuario y cliente del Bearer token.
- `POST /api/auth/logout`: revoca el token utilizado y devuelve JSON.
- Los tokens son opacos y duran 24 horas. `users.api_token_hash` guarda su SHA-256
  y `users.api_token_expires_at` su vencimiento. Hay un token vigente por usuario:
  un nuevo login reemplaza al anterior. No hay refresh tokens ni renovación automática.
- Vue guarda el token en `localStorage`, según la decisión acordada. Esto permite
  mantener el acceso al recargar, con la contrapartida de exposición a JavaScript
  si existe una vulnerabilidad XSS.
- Vue Router controla `/login`, `/` y el retorno a la pantalla pendiente. Guarda
  ruta, query y fragmento en `sessionStorage` y solo admite destinos internos.
  Al agregar una pantalla protegida se usa `meta: { requiresAuth: true }`.
- Las URLs del frontend entregan la entrada de Vue; las rutas desconocidas de
  `/api` o `/auth` conservan su 404. Una pantalla inexistente muestra un 404 en Vue
  después de comprobar el acceso.

Los endpoints protegidos usan, en orden, `AuthenticateAccessToken` y
`ResolveClientContext`. Sus requests extienden `AuthenticatedRequest`, que expone
`$request->user` y `$request->client` como propiedades
tipadas obtenidas de atributos internos, no del cuerpo ni de la query. Si un
request redefine `prepareForValidation()`, debe llamar al método padre.
Los controllers pasan explícitamente el usuario o cliente a los services que lo
necesitan; disponer del contexto no reemplaza el filtrado por cliente en consultas.

### Revocar el acceso

Para revocar el acceso desde la base, establecer `users.api_token_hash` y
`users.api_token_expires_at` en `NULL`. El logout hace lo mismo para el token
utilizado; una petición de logout anterior no borra un token emitido posteriormente.
Deshabilitar `users.is_enabled` o `clients.is_enabled` bloquea tanto el acceso
existente como un nuevo login. La siguiente petición es rechazada y Vue limpia
la credencial y muestra el login. No se fuerza un cierre visual instantáneo en
una pestaña que no hace peticiones.

Esta etapa implementa únicamente el acceso del titular con Google; el formulario
con contraseña y el acceso de administradores siguen pendientes.

## Comandos

Ejecutarlos desde la raíz del proyecto. El Makefile utiliza `compose.yaml` y
`.env.docker` de Nuvads.

```bash
make              # Mostrar la ayuda; también disponible con make help.
make up           # Levantar todos los servicios y esperar a que estén listos.
make down         # Bajar y eliminar los contenedores y la red, conservando los datos.
make stop         # Detener los servicios conservando los contenedores.
make restart      # Reiniciar los servicios.
make build        # Construir la imagen de PHP; aplicar luego con make up.
make dev          # Iniciar Vite manualmente; detener con Ctrl+C.
make frontend-build # Compilar el frontend en public/build.
make ps           # Ver el estado de los servicios.
make logs         # Seguir los logs; salir con Ctrl+C.
make php-shell    # Abrir una consola en PHP, con PHP y Composer disponibles.
make node-shell   # Abrir una consola en Node, con Node y npm disponibles.
make redis-cli    # Abrir la consola de Redis.
make redis-clear  # Borrar todas las claves de todas las bases del Redis de Nuvads.
```

`make redis-clear` ejecuta `FLUSHALL SYNC` y usa la autenticación ya configurada
en el contenedor. El borrado incluye cualquier dato guardado en ese Redis.

Para desarrollar, ejecutar `make up` y luego `make dev`. La aplicación se abre
en https://app.nuvads.test:8443; Vite sirve los recursos y la recarga de cambios
por HTTPS en app.nuvads.test:5280, con el puerto tomado de `.env.docker`. `make dev` permanece en primer plano.

Con Vite detenido mediante Ctrl+C, Laravel utiliza la última compilación de
`make frontend-build`. El archivo temporal `public/hot` indica cuándo usar Vite.
El observador de Vite excluye `docker/`, `storage/` y `vendor/`.

## Instalación desde una copia del repositorio

Primero preparar `.env.docker`, el certificado y `/etc/hosts` como se indica arriba. Después:

```bash
cp .env.example .env
chmod 600 .env
make up
docker compose --env-file .env.docker --file compose.yaml exec -T php composer install
docker compose --env-file .env.docker --file compose.yaml exec -T php php artisan key:generate
docker compose --env-file .env.docker --file compose.yaml exec -T node npm ci
make frontend-build
```

`composer.lock` y `package-lock.json` fijan las versiones instaladas. La estructura
conserva las migraciones base de Laravel; todavía no se ejecutaron ni se cargaron
datos de ejemplo. MongoDB permanece disponible en Docker; su integración con
Laravel queda pendiente de decidir.

## Tests del backend

```bash
make test-setup # Una vez por entorno: crea .env.testing, la base y su usuario.
make test      # Ejecutar toda la suite.
make test-unit
make test-feature
make test ARGS='--filter=UserIsolationTest'
make test ARGS='tests/Feature/Auth'
make test ARGS='--group=smoke'
make test ARGS='--order-by=random'
make lint-php
```

Configuración, convenciones y ciclo de la base de datos:
[skill testing-backend](.claude/skills/testing-backend/SKILL.md).

## Investigación web de marca

**Pendiente: implementar el análisis real con IA** (proveedor/modelo, prompt e
interpretación y validación de los resultados). El estado `completed` actual
indica que terminó el recorrido con el mock, no que se haya realizado un análisis real.

El backend usa Apify para recopilar el sitio guardado en la marca. La etapa de IA
está simulada: guarda insights con `model: mock`, `prompt_version: mock-v1` y
`payload.is_mock: true`. No genera conclusiones reales ni modifica correcciones
anteriores. La futura llamada de IA está comentada en `ResearchRunService`.

Endpoints autenticados:

- `POST /api/research-runs`, body `{"type":"website"}`: crea la ejecución y encola el inicio.
- `GET /api/research-runs/{id}`: devuelve la ejecución con fuentes e insights.
- `GET /api/research-runs/website/status`: devuelve `active`, `latest` y `last_completed`.

Se admite una ejecución web activa por marca. `research_runs` conserva la URL
solicitada y los estados `pending`, `scraping`, `analyzing`, `completed`, `failed`.
El frontend todavía no está conectado a estos endpoints.

La tabla se crea con la migración `2026_09_21_000003_create_research_runs_table.php`.
Se reutilizan `knowledge_sources` y `knowledge_insights`; las fuentes sin cambios
se deduplican y sus IDs quedan registrados en la ejecución.

El helper limita el scraping a diez páginas por defecto y permite indicar otro
límite en cada llamada. `ResearchRun` conserva el límite utilizado. El seguimiento
espera hasta 600 segundos (`config/research.php`); comprueba Apify cada 15 segundos,
según el dispatcher. Las consultas fallidas tienen tres intentos y un backoff de
15 segundos, declarados en el job. El inicio tiene un solo intento. Requiere
`APIFY_API_KEY`. El límite de espera es local: no cancela automáticamente el actor.

Se mantiene `QUEUE_CONNECTION=database` y la conexión de queue en la misma base
de la aplicación. La ejecución, sus cambios de etapa y los despachos se guardan
en la misma transacción. Cambiar esa conexión requiere revisar esta garantía.

Cada comando se ejecuta en una terminal independiente; no se instaló un supervisor:

```bash
docker compose --env-file .env.docker --file compose.yaml exec php php artisan queue:work --queue=scraping_queue
docker compose --env-file .env.docker --file compose.yaml exec php php artisan queue:work --queue=analysis_queue
```

Los parámetros de ejecución están en los jobs; queue y demora, en
`ResearchDispatcherService`. Los logs llevan el nombre de cada job, sufijo `Info`
o `Errors` y UUID de correlación. El inicio de Apify no se reintenta automáticamente:
si su respuesta se pierde, revisar Apify antes de solicitar otra ejecución.

El contenido se valida según la salida del
[Website Content Crawler de Apify](https://apify.com/apify/website-content-crawler).
Los tests simulan todas las llamadas externas; ejecutarlos no inicia scrapers pagos.

## Datos locales

Las bases guardan sus archivos directamente en carpetas del proyecto mediante
montajes de directorios (`bind mounts`):

| Servicio | Carpeta del proyecto | Ruta dentro del contenedor |
| --- | --- | --- |
| MySQL | `docker/mysql/data/` | `/var/lib/mysql` |
| MongoDB | `docker/mongo/data/` | `/data/db` |
| MongoDB, configuración interna | `docker/mongo/config/` | `/data/configdb` |
| Redis | `docker/redis/data/` | `/data` |

Estas carpetas están excluidas de Git y del contexto de construcción de Docker.
Los datos sobreviven a la recreación de los contenedores y a `docker compose down`.
No se utilizan volúmenes con nombre para las bases. La red del proyecto es
`nuvads_default`.

Los archivos conservan los propietarios que necesita cada base. En esta
computadora, las carpetas tienen permisos ACL para que el usuario local pueda
recorrerlas y ver sus archivos.

Laravel guarda la caché en `storage/framework/cache/data/`, las sesiones en
`storage/framework/sessions/`, las vistas compiladas en `storage/framework/views/`
y los logs en `storage/logs/`. Los archivos generados están excluidos de Git;
los `.gitignore` internos conservan la estructura de carpetas.

## Configuraciones

- `Makefile`: atajos para las operaciones habituales del entorno.
- `compose.yaml`: servicios, conexiones, puertos y volúmenes.
- `docker/php/Dockerfile`: imagen de PHP con Composer y extensiones.
- `docker/php/development.ini`: configuración de PHP para desarrollo.
- `docker/nginx/default.conf`: servidor web y comunicación con PHP-FPM.
- `docker/mongodb/init-user.js`: creación del usuario de aplicación de MongoDB.
- `public/index.php`: entrada HTTP de Laravel.
- `routes/web.php`: ruta de la página base.
- `resources/views/app.blade.php`: HTML que carga Vue.
- `resources/js/app.js` y `resources/js/App.vue`: entrada y componente raíz de Vue.
- `vite.config.js`: compilación del frontend y servidor de desarrollo.
