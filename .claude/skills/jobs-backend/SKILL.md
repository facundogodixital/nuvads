---
name: jobs-backend
description: "Convenciones de Nuvads para jobs y queues: dispatchers por dominio, queue y demora en el dispatcher, parámetros de ejecución en el job, payloads con IDs y logs con UUID. Cargar antes de crear o modificar jobs, su despacho, workers o su configuración de logging."
---

# Jobs del backend de Nuvads

Aplicar junto con AGENTS.md y [capas-backend](../capas-backend/SKILL.md).

## Organización y queues

- Los nombres de las clases terminan en `Job` y describen la operación.
- Agrupar los jobs por dominio y, cuando corresponda, por fuente. Para investigación web, el namespace acordado es `App\Jobs\Research\Website`.
- Los nombres de las queues terminan siempre en `_queue`. Para investigación web se usa `research_queue`.
- Los jobs coordinan el recorrido mediante services obtenidos con `resolve()` cerca de su uso. El acceso a repositories y helpers sigue las reglas de capas; no copiar accesos directos de ejemplos de otros proyectos.

## Despacho mediante services

- Centralizar los despachos en un dispatcher por dominio, ubicado en `App\Services\Dispatchers`, con nombre `<Domain>DispatcherService`.
- Cada job tiene un método explícito `dispatch<NombreDelJob>()`. Para investigación se usa `ResearchDispatcherService`, con `dispatchResearchWebsiteJob()`, que recibe el ID de `ResearchRun`.
- El service del dominio decide cuándo corresponde despachar. El dispatcher define cómo encolarlo: prepara los parámetros, selecciona la queue y aplica la demora. No trasladar reglas de negocio al dispatcher.
- La queue y la demora se definen en el dispatcher, no dentro del job ni mediante llamadas dispersas a `dispatch()`. Si la demora depende de la operación, el método del dispatcher puede recibirla explícitamente y aplicarla allí.
- Las etapas posteriores y las comprobaciones diferidas también se despachan por esta vía. Los jobs delegan esa coordinación en los services.
- Obtener los dispatchers con `resolve()` cerca de su uso y registrarlos como scoped, siguiendo las convenciones de services.

## Parámetros de ejecución dentro del job

- Toda configuración específica de ejecución se declara dentro de la clase del job, mediante las propiedades correspondientes de Laravel: por ejemplo, `$tries`, `$timeout`, `$backoff` o `$failOnTimeout`.
- No es obligatorio declarar esas propiedades. Agregar únicamente las que necesite la operación; no copiar valores por defecto ni inventar límites.
- No definir esa política en las opciones del comando del worker, como `--tries`, `--timeout` o `--backoff`.
- El comando del worker puede seleccionar qué queues consumir mediante `--queue`; eso no reemplaza la configuración del job.
- Distinguir la demora de despacho (`delay`, definida por el dispatcher) de la espera entre reintentos por fallo (`$backoff`, definida en el job).
- Usar la forma clásica de Laravel, sin atributos PHP `#[...]`. Los tiempos, reintentos y demás parámetros de infraestructura se acuerdan con el usuario antes de configurarlos.

## Datos transportados

- Preferir IDs como parámetros del constructor y cargar los registros mediante services durante la ejecución.
- Evitar transportar modelos, colecciones, contenido scrapeado, respuestas externas o estructuras grandes en el payload de la queue. Los datos voluminosos se guardan y el job recibe su referencia.
- Se pueden recibir escalares pequeños que la operación necesite. El constructor conserva los parámetros y la configuración; no realiza consultas ni llamadas externas.
- Cargar por ID implica trabajar con el estado actual. Si la operación requiere conservar la entrada original, persistirla con la ejecución y recuperarla desde allí; por ejemplo, la URL solicitada en `ResearchRun`.
- Si el registro ya no existe o su estado impide continuar, registrar el motivo y aplicar el comportamiento que corresponda a la operación.

## Logs por job y correlación

- Cada job deja información precisa sobre inicio, IDs relevantes, etapas completadas, resultados, salidas anticipadas y fallos. Evitar mensajes genéricos que no permitan reconstruir qué ocurrió.
- Los canales y archivos llevan el nombre exacto de la clase, seguido de `Info` o `Errors`. Por ejemplo:
  - Canal `ResearchWebsiteJobInfo`, archivo `storage/logs/ResearchWebsiteJobInfo.log`.
  - Canal `ResearchWebsiteJobErrors`, archivo `storage/logs/ResearchWebsiteJobErrors.log`.
- Configurar esos canales en `config/logging.php` al implementar el job. La rotación puede agregar la fecha al nombre del archivo; conservar el nombre del job como base.
- Todas las entradas, incluidas las de error, usan el prefijo `[uuid] | mensaje`.
- Generar el UUID de correlación una vez en el constructor y conservarlo como propiedad serializada del job. Mantenerlo durante los reintentos y registrar también el número de intento. No generarlo únicamente en `handle()`: Laravel reconstruye el job desde el payload para llamar a `failed()`, por lo que los cambios hechos en `handle()` no están disponibles allí.
- Cada nuevo job tiene su propio UUID. Para seguir un proceso que involucra varios jobs, registrar además su identificador de dominio, como `researchRunId`.
- Centralizar el formato en métodos pequeños del job, como `logInfo()` y `logError()`, evitando repetir el prefijo en cada llamada. No hace falta crear una infraestructura de logging adicional para empezar.
- Registrar el fallo definitivo en `failed(Throwable $exception)`. Cuando se captura un error durante la ejecución para agregar contexto, no ocultarlo: relanzarlo si la operación debe fallar o reintentarse.
- Registrar IDs, cantidades, etapas y contexto útil, sin volcar credenciales ni payloads completos. Los errores deben permitir identificar el problema sin exponer datos sensibles.

Ejemplo del formato dentro del método de logging:

```php
Log::channel('ResearchWebsiteJobInfo')->info("[{$this->logUuid}] | {$message}");
```

Ejemplo de salida:

```text
[6ae12166-0b35-43b3-96d4-c8cf4832bd29] | Starting ResearchWebsiteJob. researchRunId: 42. attempt: 1.
[6ae12166-0b35-43b3-96d4-c8cf4832bd29] | Finished execution. status: completed. knowledgeSourceIds: [3, 4].
```
