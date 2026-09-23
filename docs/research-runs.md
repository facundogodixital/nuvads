# Tabla `research_runs`

Registra cada investigación que se hace sobre una marca: cuándo se pidió, con qué
entrada, en qué etapa está, qué fuentes usó y cómo terminó.

## Qué representa una fila

Una fila es una investigación de una marca, desde que se solicita hasta que termina,
bien o mal. Guarda el seguimiento y las referencias. El material recopilado vive en
`knowledge_sources` y las conclusiones en `knowledge_insights`.

Volver a investigar crea otra fila. Las anteriores quedan como historial.

## Campos

| Campo | Para qué sirve |
| --- | --- |
| `id` | Identificador de la fila. |
| `client_id`, `brand_id` | Cliente y marca investigados. |
| `type` | Qué se investiga. Hoy solo `website`; a futuro, otras fuentes como Instagram o Google Maps. |
| `status` | Etapa actual. Ver "Estados". |
| `input` | Entrada con la que se hizo la investigación, congelada al crearla: por ejemplo la URL del sitio y el modelo de IA. Cambiar después la marca o la configuración no altera investigaciones anteriores. |
| `knowledge_source_ids` | IDs de las fuentes usadas, por ejemplo `[41, 42, 43]`. No hay tabla puente ni claves foráneas; al leerlas se filtran por marca. |
| `started_at` | Cuándo empezó a trabajarse. |
| `finished_at` | Cuándo terminó, bien o mal. |
| `error_message` | Motivo del fallo, apto para mostrar al usuario. El detalle técnico va a los logs. |
| `external_run_id`, `external_dataset_id`, `last_checked_at` | Reservados para proveedores asincrónicos, a los que hay que consultar hasta que terminen. Hoy sin uso. |
| `created_at`, `updated_at`, `deleted_at` | Timestamps y borrado lógico. |

Índice compuesto sobre `brand_id`, `type` y `status`, para encontrar rápido la
investigación activa o la última de una marca.

## Estados

- `pending`: creada, todavía no se trabajó.
- `scraping`: se está recopilando el material.
- `analyzing`: se está interpretando con IA.
- `completed`: terminó y sus conclusiones quedaron guardadas.
- `failed`: terminó con error; `error_message` dice por qué.

`pending`, `scraping` y `analyzing` son estados activos. Solo puede haber una
investigación activa por marca y tipo.

## Relación con el resto del conocimiento

- Fuentes: `knowledge_source_ids` lista el material usado; cada página leída es una
  fuente. Cada investigación guarda sus propias fuentes.
- Conclusiones: `knowledge_insights.research_run_id` apunta a la investigación que las generó.
- Una investigación fallida puede haber dejado fuentes y conclusiones guardadas;
  siguen siendo válidas.

## Identificadores

- `id`: la fila; las conclusiones la referencian con `research_run_id`.
- `external_run_id`: la ejecución en el proveedor externo, cuando aplica.
