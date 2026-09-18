---
name: testing-backend
description: "Convenciones de Nuvads para crear, modificar y ejecutar tests del backend: PHPUnit, factories, dobles y aislamiento de MySQL. Cargar antes de trabajar en tests PHP, sus datos o su configuración. No cubre tests del frontend."
---

# Tests del backend de Nuvads

Aplicar junto con AGENTS.md y los skills de las capas de producción involucradas.
Las herramientas acordadas son PHPUnit 12.5, Mockery 1.6 y Faker 1.24 como
dependencias de desarrollo. Los comandos se ejecutan desde la raíz del proyecto,
con los contenedores Docker levantados y las dependencias de Composer instaladas.

## Ubicación y alcance

- `tests/Unit/<Domain>/`: lógica aislada, extendiendo `PHPUnit\Framework\TestCase`.
  No arrancar Laravel ni consultar bases o servicios externos.
- `tests/Feature/<Domain>/`: HTTP, sesiones, persistencia, services e integraciones
  simuladas, extendiendo `Tests\TestCase`. Las pruebas de integración también van aquí.
- Organizar por dominio del negocio: `Auth`, `Clients`, `Users`, `Errors` y los que
  aparezcan al crecer el producto. Crear subdivisiones cuando faciliten encontrar
  los escenarios; no crear carpetas vacías para funcionalidades futuras.
- Smoke, regresión y seguridad describen objetivos de pruebas. Usar grupos cuando
  resulte útil ejecutarlas juntas; el grupo `smoke` ya existe.

Probar resultados observables: respuesta, sesión, datos persistidos, permisos y
efectos externos esperados. Elegir el nivel que compruebe el comportamiento sin
duplicar el mismo escenario en todas las capas. En funcionalidades por cliente,
incluir los casos de acceso a datos de otro cliente que correspondan.

## Escritura y datos

- Encabezar cada método de test con un comentario breve en castellano, antes de
  `#[Test]`, que explique el escenario y el resultado esperado. Usar una o dos
  líneas; incluir el motivo cuando aporte contexto y describir solo lo que las
  comprobaciones realmente verifican. No narrar cada instrucción del cuerpo.
- Usar `#[Test]`, nombres descriptivos en inglés y snake_case para los métodos de
  test, como exige Pint. Usar `#[DataProvider]` con proveedores públicos y estáticos
  para variaciones del mismo comportamiento; sus datos no dependen de Laravel.
- Hacer visibles la preparación, la operación y las comprobaciones, separadas por
  líneas en blanco. Compartir preparación únicamente cuando tenga un propósito
  claro. `Tests\TestCase` contiene solo la configuración común.
- Las factories viven en `database/factories/`. Usarlas mediante
  `ClientFactory::new()` y `UserFactory::new()`. No hace falta agregar `HasFactory`
  a los modelos para esta forma de uso.
- `owner()` genera un titular con identidad de Google y `disabled()` deshabilita
  usuarios o clientes. `UserFactory::new()->for($client)` permite indicar el cliente.
- Faker completa datos secundarios. Declarar explícitamente los valores que
  determinan el resultado esperado; no depender de valores aleatorios para decidir
  si una prueba pasa.
- Excepción aprobada: los tests y factories pueden preparar y comprobar datos
  directamente con Eloquent. La operación bajo prueba entra por la ruta, service
  o pieza que corresponda a su alcance. Las reglas de producción no cambian.
- Las propiedades heredadas sin tipo, como `$model` de `Factory`, se mantienen sin
  tipo PHP y se documentan cuando lo requiere PHPCS.

## Aislamiento y dobles

- Usar `RefreshDatabase` en las clases que persisten datos. No ejecutar migraciones
  o vaciados manuales contra otra conexión para preparar escenarios. En cada
  ejecución, el primer test que usa BD recrea las tablas mediante las migraciones.
  Cada test con BD abre una transacción y la revierte al terminar: los datos se
  deshacen, pero la base y las tablas permanecen. Los unitarios no usan este ciclo.
- La única base aprobada es `nuvads_testing`, con usuario `nuvads_testing`, en
  `mysql:3306` dentro de Docker. `make test-setup` prepara el entorno y genera las
  credenciales locales en `.env.testing`. No imprimir ese archivo ni el SQL que
  genera `tests/setup.php`: contiene la contraseña.
- `.env.testing` no se versiona y tiene permisos `600`; `.env.testing.example`
  documenta sus campos. La preparación genera una clave de aplicación y una
  contraseña aleatorias. Repetir `make test-setup` conserva credenciales y datos,
  y restablece los permisos del usuario exclusivamente sobre la base de tests.
- `tests/bootstrap.php` reemplaza las variables de desarrollo inyectadas por Docker
  con `.env.testing`. Mantener las comprobaciones de conexión de `Tests\TestCase`
  antes de `RefreshDatabase`; no saltarlas para hacer pasar un test.
- Si falta el entorno, ejecutar `make test-setup`. Si hay configuración cacheada,
  limpiar la configuración con `php artisan config:clear` dentro de `make web`.
- Sesiones y caché usan memoria. Vite se omite y los logs de las pruebas se descartan.
- Simular comunicaciones externas. `Http::preventStrayRequests()` está activo:
  cada petición del cliente HTTP de Laravel debe tener su respuesta simulada.
- Para Google, reutilizar `Tests\Feature\Auth\GoogleOAuthTestCase`: conserva
  Socialite y reemplaza su transporte por Guzzle `MockHandler`. Una cola vacía
  falla sin salir a la red. `Http::fake()` no intercepta ese cliente Guzzle.
- Mantener las piezas internas reales cuando se pruebe su integración. Usar
  Mockery para respuestas controladas o fallos concretos, como una escritura que
  falla a mitad del registro y debe provocar rollback.
- Si un mock corre dentro de una petición cuyo error se espera, registrar lo
  observado y comprobarlo después de la petición. Una aserción dentro del mock
  puede convertirse en la respuesta de error esperada y ocultar el fallo del test.
- Activar `Http::fake()` antes de usar aserciones sobre peticiones registradas,
  incluido `assertNothingSent()`. `preventStrayRequests()` no activa ese registro.
- Para verificar un dato de vista nulo, comprobar que exista la clave y usar un
  callback que exija `=== null`; `assertViewHas($key, null)` solo comprueba la clave.
- No depender del orden de ejecución ni de registros preexistentes. La base es
  compartida por los procesos de tests: no ejecutar suites simultáneas. El soporte
  para ejecución en paralelo requiere acordar y preparar bases independientes.

## Ejecución y cierre

Los comandos de preparación, ejecución, filtros y estilo se mantienen en
[Tests del backend del README](../../../README.md#tests-del-backend).

Ejecutar las pruebas afectadas y las comprobaciones necesarias para el cambio.
Al cambiar el entorno común, comprobar también la suite completa y su aislamiento.
Pint, PHPCS y los hooks comprueban el estilo de tests y factories; actualmente
los tests se ejecutan a demanda.

No hay medición ni umbral de cobertura configurados. El smoke comprueba Laravel,
no el despliegue. Laravel deshabilita CSRF durante sus pruebas HTTP: estas pruebas
no demuestran la protección CSRF real. Completar la revisión de cierre indicada
por AGENTS.md.
