# Nuvede

Entorno Docker para desarrollo local. Laravel y Vue todavía no están instalados.

## Servicios

Los puertos publicados escuchan únicamente en `127.0.0.1`.

| Servicio | Versión | Desde la computadora | Desde los contenedores |
| --- | --- | --- | --- |
| Nginx | 1.30 | http://localhost:8080 | nginx:80 |
| PHP-FPM | 8.4 | No publicado | php:9000 |
| Node | 24 LTS | 5280, reservado para el frontend | node:5280 |
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

La base y el usuario de aplicación se llaman `nuvede` tanto en MySQL como en
MongoDB. En MongoDB, el usuario de aplicación se autentica contra la base
`nuvede`; `nuvede_admin` es el administrador, que se autentica contra `admin`.
Redis utiliza la contraseña `REDIS_PASSWORD`.

Los usuarios y contraseñas de MySQL y MongoDB se inicializan cuando sus
carpetas de datos están vacías. Cambiar `.env.docker` no cambia automáticamente
los usuarios de una base ya inicializada.

## Comandos

Ejecutarlos desde la raíz del proyecto. El Makefile utiliza `compose.yaml` y
`.env.docker` de Nuvede.

```bash
make              # Mostrar la ayuda; también disponible con make help.
make up           # Levantar todos los servicios y esperar a que estén listos.
make down         # Bajar y eliminar los contenedores y la red, conservando los datos.
make stop         # Detener los servicios conservando los contenedores.
make restart      # Reiniciar los servicios.
make build        # Construir la imagen de PHP; aplicar luego con make up.
make ps           # Ver el estado de los servicios.
make logs         # Seguir los logs; salir con Ctrl+C.
make php-shell    # Abrir una consola en PHP, con PHP y Composer disponibles.
make node-shell   # Abrir una consola en Node, con Node y npm disponibles.
make redis-cli    # Abrir la consola de Redis.
make redis-clear  # Borrar todas las claves de todas las bases del Redis de Nuvede.
```

`make redis-clear` ejecuta `FLUSHALL SYNC` y usa la autenticación ya configurada
en el contenedor. El borrado incluye cualquier dato guardado en ese Redis.

Node queda disponible para ejecutar npm. Todavía no hay un servidor de frontend
escuchando: al instalar Vue, debe configurarse para escuchar en `0.0.0.0:5280`
dentro del contenedor.

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
`nuvede_default`.

Los archivos conservan los propietarios que necesita cada base. En esta
computadora, las carpetas tienen permisos ACL para que el usuario local pueda
recorrerlas y ver sus archivos.

## Configuraciones

- `Makefile`: atajos para las operaciones habituales del entorno.
- `compose.yaml`: servicios, conexiones, puertos y volúmenes.
- `docker/php/Dockerfile`: imagen de PHP con Composer y extensiones.
- `docker/php/development.ini`: configuración de PHP para desarrollo.
- `docker/nginx/default.conf`: servidor web y comunicación con PHP-FPM.
- `docker/mongodb/init-user.js`: creación del usuario de aplicación de MongoDB.
- `public/index.php`: página temporal, que se reemplazará al instalar Laravel.
