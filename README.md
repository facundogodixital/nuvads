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
mkcert -cert-file docker/nginx/certs/app.nuvads.test.pem -key-file docker/nginx/certs/app.nuvads.test-key.pem app.nuvads.test
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

Los dominios futuros son `app.nuvads.ai` para la aplicación y `nuvads.ai` para
la web principal; esta configuración y estos certificados son exclusivamente locales.

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
