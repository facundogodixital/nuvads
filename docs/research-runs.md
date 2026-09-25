# Investigaciones (`research_runs`)

Una investigación lee una fuente de la marca (su sitio web, su Instagram, sus anuncios de Meta, sus
reseñas de Google, un zip con sus conversaciones de WhatsApp o un audio en el que el usuario cuenta su negocio),
guarda lo leído como fuentes, saca conclusiones con IA y mezcla lo aprendido con el perfil de la marca. La tabla
`research_runs` registra cada una: cuándo se pidió, con qué entrada, en qué etapa está, qué fuentes usó y cómo
terminó.

El modelo de fuentes, conclusiones y campos de la marca está en
[knowledge-model.md](knowledge-model.md).

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
| `type` | Qué se investiga: `website`, `instagram`, `meta_ads`, `google_reviews`, `whatsapp_conversations` o `audio`. |
| `status` | Etapa actual. Ver "Estados". |
| `input` | Entrada con la que se hizo la investigación, congelada al crearla, siempre con el modelo de IA en `model`. `website` guarda `url`; `instagram`, `username` y `posts_limit`; `meta_ads`, `url` (la página de Facebook) y `ads_limit`; `google_reviews`, `url` (el enlace de Google Maps) y `reviews_limit`; `whatsapp_conversations`, `zip_path` (el zip subido, que el job borra al leerlo) y `conversations_limit`; `audio`, `audio_path` (el audio grabado, que el job borra al transcribirlo) y `transcription_model`. Cambiar después la marca o la configuración no altera investigaciones anteriores. |
| `knowledge_source_ids` | IDs de las fuentes usadas, por ejemplo `[41, 42, 43]`. No hay tabla puente ni claves foráneas; al leerlas se filtran por marca. |
| `started_at` | Cuándo empezó a trabajarse. |
| `finished_at` | Cuándo terminó, bien o mal. |
| `status_message` | Por qué terminó en `failed` o en `empty`, apto para mostrar al usuario. El detalle técnico de un fallo va a los logs. |
| `external_run_id`, `external_dataset_id`, `last_checked_at` | Para proveedores asincrónicos, a los que hay que consultar hasta que terminen. Instagram, los anuncios de Meta y las reseñas de Google guardan la ejecución y el dataset de Apify, y la hora de la última consulta. |
| `created_at`, `updated_at`, `deleted_at` | Timestamps y borrado lógico. |

Índice compuesto sobre `brand_id`, `type` y `status`, para encontrar rápido la
investigación activa o la última de una marca.

## Estados

- `pending`: creada, todavía no se trabajó.
- `scraping`: se está recopilando el material.
- `analyzing`: se está interpretando con IA.
- `completed`: terminó y sus conclusiones quedaron guardadas.
- `empty`: terminó sin nada para analizar, por ejemplo un lugar sin reseñas o un audio sin voz. No es un error:
  no guarda conclusiones ni fuentes, no invalida ni borra nada y no toca la marca, así lo anterior sigue
  vigente. `status_message` dice por qué.
- `failed`: terminó con error; `status_message` dice por qué.

`pending`, `scraping` y `analyzing` son estados activos. Solo puede haber una
investigación activa por marca y tipo.

## Relación con el resto del conocimiento

- Fuentes: `knowledge_source_ids` lista el material usado; cada página leída, posteo
  de Instagram, anuncio de Meta, reseña de Google, conversación de WhatsApp con un cliente o audio es una
  fuente. Cada investigación guarda sus propias fuentes. Las reseñas y las conversaciones son la
  excepción: una investigación que termina bien borra las de las anteriores.
- Conclusiones: `knowledge_insights.research_run_id` apunta a la investigación que las generó.
- Una investigación fallida puede haber dejado fuentes y conclusiones guardadas;
  siguen siendo válidas.

## Identificadores

- `id`: la fila; las conclusiones la referencian con `research_run_id`.
- `external_run_id`: la ejecución en el proveedor externo, cuando aplica.

## Cómo corre una investigación

Las seis investigaciones siguen el mismo recorrido:

- Se piden con `POST /api/research-runs`, body `{"type":"<tipo>"}`. La entrada sale de la marca y
  de `config/research.php`; si falta el dato de la fuente en la marca, el pedido se rechaza. Las
  conversaciones de WhatsApp se piden con un formulario multipart que suma el zip en `zip_file`, y el audio,
  con el archivo grabado en `audio_file`.
- La ejecución y su job se guardan en la misma transacción: la queue es `database`, en la misma
  base de la aplicación. El job corre en `research_queue`, con un intento.
- Cada tipo tiene un job y un service: el job carga la ejecución y llama a `research()` del
  service, que hace todo el trabajo. El service informa cada etapa y cada error manejado mediante
  closures; el job los escribe en sus logs, `storage/logs/<Job>Info.log` y
  `storage/logs/<Job>Errors.log`, con un UUID de correlación. Los errores van a los dos.
- Si el job falla, la ejecución queda en `failed` con un mensaje genérico para el usuario, y el
  error completo queda en los logs. Se repite creando otra.
- Cada investigación deja un análisis (`<tipo>_analysis`, o `website_brand_analysis`) y hasta
  siete conclusiones (`<tipo>_insight`; el audio, las que tengan respaldo), y las conclusiones activas
  anteriores del mismo tipo pasan a `outdated`. Las reseñas de Google y las conversaciones de WhatsApp dejan más tipos; ver
  sus secciones. Mezcla lo aprendido con los campos de la marca, como explica
  [knowledge-model.md](knowledge-model.md#campos-de-la-marca), salvo que el análisis diga que la
  fuente es de otro negocio: entonces no toca la marca.
- La pantalla de cada fuente usa `GET /api/research-runs/<fuente>/status`, que devuelve `active`,
  `latest` y `last_completed`, y `GET /api/knowledge-insights/<fuente>`, que devuelve `analysis`,
  el análisis vigente o `null`, e `insights`, las conclusiones vigentes (`active` y
  `superseded`). `<fuente>` es `website`, `instagram`, `meta-ads`, `google-reviews`,
  `whatsapp-conversations` o `audio`.
  `GET /api/research-runs/{id}` devuelve una ejecución con sus fuentes y conclusiones.
- Las consultas a OpenAI (`gpt-6-luna`, configurable en `config/research.php`) piden un JSON y
  lo validan solo en lo que el código lee. El log guarda el pedido completo y la respuesta. La transcripción del
  audio usa `gpt-transcribe`, también configurable ahí.

## Sitio web (`website`)

`ResearchWebsiteJob` llama a `WebsiteResearchService`. Requiere `website_url` en la marca,
`FIRECRAWL_API_KEY` y `OPENAI_API_KEY`. El job tiene un timeout de 600 segundos.

1. Lee la portada con Firecrawl y toma de ahí la identidad visual: logo, colores y fuentes.
2. Una primera consulta al modelo, solo con la portada, elige hasta dos enlaces internos más
   para leer y completa lo visual que Firecrawl no trajo. Si no hay enlaces ni datos visuales
   faltantes, esta consulta no se hace.
3. Lee esas páginas con Firecrawl. Máximo: tres páginas por investigación.
4. Una segunda consulta analiza todas las páginas juntas, con el markdown sin URLs ni
   imágenes y el texto actual de los once campos de texto de la marca. Devuelve esos campos
   mezclados, el nombre, un resumen, las conclusiones y, en `inferred_fields`, los campos a los
   que sumó deducciones.

## Instagram (`instagram`)

`ResearchInstagramJob` llama a `InstagramResearchService`. Requiere `instagram_username` en la
marca, `APIFY_API_KEY` y `OPENAI_API_KEY`. El timeout del job es el `retry_after` de la conexión
menos 60 segundos.

1. Arranca el actor de Apify `apify~instagram-post-scraper` y consulta la ejecución cada 10
   segundos hasta que termina.
2. Lee los últimos posteos, tantos como indique `instagram.posts_limit`.
3. Cada posteo pasa por el modelo con todas sus imágenes, que devuelve por cada una el texto que
   aparece (`transcription`) y qué muestra (`description`). Los reels van solo con su portada.
   Un posteo que falla se saltea; solo si fallan todos, falla la investigación.
4. Calcula las métricas en PHP: posteos por semana, sin contar los fijados, y promedios de likes
   y comentarios por formato. Apify devuelve -1 likes cuando la cuenta los oculta.
5. Un análisis final, solo con texto, recibe los posteos transcriptos, las métricas y el texto
   actual de cinco campos de la marca, y devuelve esos campos mezclados, un resumen y las
   conclusiones.

`GET /api/knowledge-insights/instagram` devuelve además `posts`, los posteos que leyó el
análisis vigente.

## Anuncios de Meta (`meta_ads`)

`ResearchMetaAdsJob` llama a `MetaAdsResearchService`. Requiere `meta_ads_url` en la marca (la
página de Facebook), `APIFY_API_KEY` y `OPENAI_API_KEY`. El timeout del job es el `retry_after`
de la conexión menos 60 segundos.

1. Arranca el actor de Apify `apify~facebook-ads-scraper` con
   `sorting: "relevancy_monthly_grouped"`, que en el actor es "Most recent", y consulta la
   ejecución cada 10 segundos hasta que termina.
2. Lee los anuncios más nuevos, tantos como indique `meta_ads.ads_limit`. Meta solo conserva los
   anuncios inactivos cuando son políticos o se publicaron en la Unión Europea; en LATAM llegan
   casi siempre los activos.
3. Si la página no tiene anuncios, Apify devuelve un solo ítem con los datos de la página, sin
   `adArchiveID`. La investigación termina en `empty`, sin consultar al modelo: si la página dejó de
   publicitar, el análisis de los anuncios que corrió sigue vigente.
4. Cada anuncio pasa por el modelo con sus imágenes: las tarjetas de un carrusel o de un anuncio
   dinámico, las imágenes y la portada de cada video. Devuelve lo mismo que en Instagram. Un
   anuncio que falla se saltea; solo si fallan todos, falla la investigación.
5. Calcula las métricas en PHP: cantidad, días del que más lleva corriendo, cantidad y promedio de
   días por formato, y anuncios por plataforma. En los activos, los días se cuentan hasta el
   momento del análisis.
6. Un análisis final, solo con texto, recibe los anuncios transcriptos, las métricas y el texto
   actual de ocho campos de la marca, y devuelve esos campos mezclados, un resumen y las
   conclusiones. No hay datos de resultados de los anuncios: los días corriendo se usan como
   señal de lo que funciona.

`GET /api/knowledge-insights/meta-ads` devuelve además `ads`, los anuncios que leyó el análisis
vigente.

## Reseñas de Google (`google_reviews`)

`ResearchGoogleReviewsJob` llama a `GoogleReviewsResearchService`. Requiere `google_maps_url` en la
marca (sirve también el enlace corto `maps.app.goo.gl`), `APIFY_API_KEY` y `OPENAI_API_KEY`. El
timeout del job es el `retry_after` de la conexión menos 60 segundos; `DB_QUEUE_RETRY_AFTER` está en
1200 para darle margen a las tandas.

1. Arranca el actor de Apify `compass~google-maps-reviews-scraper` con las reseñas más nuevas
   primero, `language: "es-419"` (las claves de contexto y de puntaje por aspecto llegan en
   castellano) y `personalData: true` (nombre del autor y enlace a cada reseña), y consulta la
   ejecución cada 10 segundos hasta que termina.
2. Lee hasta `google_reviews.reviews_limit` reseñas. Si no hay reseñas, o ninguna tiene texto, la
   investigación termina en `empty` antes de guardar nada: las reseñas y el análisis anteriores siguen
   vigentes. Si no, guarda cada reseña como fuente, sin el ítem de Apify.
3. Calcula las métricas en PHP. Para ver la evolución, parte las reseñas con texto en cuatro tramos
   de tiempo con la misma cantidad de reseñas: se adaptan solos al volumen, y en un negocio con
   mucho movimiento cubren semanas y en uno con poco, años. Con menos de cuatro reseñas con texto,
   hay un tramo por reseña.
4. El modelo lee las reseñas con texto en tandas de 200 (id, estrellas y texto) y devuelve, por
   categoría, los temas con los IDs de las reseñas que los mencionan y una destacada: `pains`,
   `strengths`, `facts`, `profiles`, `products` y `staff`. PHP descarta los IDs que no son de la
   tanda y los temas que se quedan sin IDs. Una tanda que falla se saltea; solo si fallan todas,
   falla la investigación.
5. Si hubo más de una tanda, el modelo unifica los temas: recibe solo los nombres, con una clave
   por tema (`b2_5`), y devuelve los grupos. PHP junta los IDs, y una clave que el modelo no agrupa
   queda como tema propio.
6. PHP cuenta las menciones y descarta los temas con menos de 2, o con menos del 1% de las reseñas
   con texto si es más. Las quejas quedan siempre con 2, porque suelen ser pocas y dispersas; su
   peso se ve en `mentions_share`, y el análisis final las presenta con sus números. Elige hasta
   tres destacadas, las más nuevas entre las que marcó el modelo, y calcula qué parte de cada tramo
   menciona cada queja y cada elogio.
7. Un análisis final recibe las métricas, los temas con su clave y sus ejemplos, las 30 respuestas
   más recientes del dueño y el texto actual de ocho campos de la marca. Devuelve los campos
   mezclados, un resumen y hasta 12 conclusiones, cada una con las claves de los temas en que se
   apoya; PHP las traduce a reseñas.
8. En una transacción, las filas activas anteriores de los cinco tipos pasan a `outdated`, se
   guardan las nuevas y se borran las reseñas de corridas anteriores. Después se mezclan los campos
   de la marca.

Qué cambia cuando una corrida termina bien:

- Se suman sus reseñas y sus filas de conocimiento: una de métricas, una de análisis de marca,
  una por queja, una por elogio y hasta 12 conclusiones.
- Se invalidan las filas `active` anteriores de los cinco tipos, que pasan a `outdated`. Las que el
  usuario corrigió (`superseded`) o rechazó (`rejected`) no se tocan.
- Se borran, con soft delete, las reseñas de corridas anteriores, también las que dejó una corrida
  fallida.
- Se mezclan ocho campos de la marca: el modelo recibe su texto actual y lo reescribe integrando lo
  que muestran las reseñas. Un campo que vuelve vacío no borra nada. Qué campos, en
  [knowledge-model.md](knowledge-model.md#campos-de-la-marca). Las cantidades de menciones van en el
  resumen y las conclusiones; en los campos, solo en las preguntas frecuentes, cuando se puede.

Si la corrida falla, queda en `failed` y sus reseñas quedan guardadas hasta la próxima corrida que
termine bien. No se invalida ni se borra nada, y la marca no se toca.

`GET /api/knowledge-insights/google-reviews` devuelve `metrics`, `analysis`, `pains`, `strengths`,
`insights` y `reviews`: solo las reseñas destacadas de esas filas. La pantalla muestra esas reseñas
debajo de cada queja, elogio o conclusión. Ver todas las reseñas de un tema queda pendiente: necesita
un listado paginado, y el botón "Ver las N reseñas" dice "Resta implementar".

## Conversaciones de WhatsApp (`whatsapp_conversations`)

`ResearchWhatsAppConversationsJob` llama a `WhatsAppConversationsResearchService`. Requiere el zip que
arma la extensión de WhatsApp y `OPENAI_API_KEY`. El zip pesa hasta 20 MB: ese es el límite de subida
de PHP (`docker/php/development.ini`) y de nginx (`docker/nginx/default.conf`). El timeout del job es
el `retry_after` de la conexión menos 60 segundos.

El zip trae un `.txt` por conversación: un encabezado con `# Teléfono` y `# Contacto`, y después un
mensaje por línea, como `[2026-06-29 13:52] Yo: Hola`. "Yo" es siempre el dueño del teléfono; cualquier
otro autor es el contacto. Una línea sin fecha sigue el mensaje anterior. `WhatsAppConversationsZipHelper`
lo lee.

1. Se guarda en `storage/app/private/whatsapp-conversations/`, con nombre aleatorio, y su ruta queda
   en `input.zip_path`. El job lo lee y lo borra enseguida, también si la lectura falla: trae todos los
   chats del teléfono, también los personales. Si no se puede crear la ejecución, se borra ahí mismo.
2. Toma las conversaciones en que el contacto escribió algo, de la más reciente a la más vieja, hasta
   `whatsapp_conversations.conversations_limit`. Si no hay ninguna, la investigación termina en
   `empty` sin consultar al modelo: las conversaciones y el análisis anteriores siguen vigentes.
3. El modelo lee las conversaciones en tandas de 30, cada una con una clave (`c12`), el contacto y sus
   mensajes: uno por línea, con quién escribe (`Negocio` o `Contacto`) y la fecha una sola vez por día,
   sin la hora, porque los tiempos los calcula PHP. En la misma consulta clasifica cada contacto
   (`customer`, `supplier`, `personal` u `other`) y saca, solo de las conversaciones de clientes, los
   temas por categoría con las claves que los mencionan: `questions` (preguntas), `objections` (lo que
   frena la compra), `products`, `purposes` (para qué los quieren), `acquisition` (cómo llegaron) y
   `customer_phrases` (frases textuales). En las preguntas y los frenos suma `owner_answer`, lo que
   responde el negocio. PHP descarta las claves que no son de clientes de la tanda y los temas que se
   quedan sin ninguna. Una tanda que falla se saltea; solo si fallan todas, falla la investigación. El
   log de cada tanda no guarda el texto de los mensajes, porque trae también chats personales: de cada
   conversación deja la clave, el contacto y la cantidad de mensajes.
4. Calcula las métricas en PHP. Si ninguna conversación es de un cliente, termina en `empty` como en el
   paso 2.
5. Guarda como fuentes solo las conversaciones de clientes, y traduce las claves a
   `knowledge_source_ids`.
6. Si hubo más de una tanda, el modelo unifica los temas como en las reseñas. De cada grupo queda la
   respuesta del negocio del tema con más conversaciones. No hay un mínimo de menciones: un tema de
   una sola conversación también queda.
7. Un análisis final recibe las métricas, los temas con su clave, los 30 mensajes más recientes del
   negocio y el texto actual de cuatro campos de la marca. Devuelve los campos mezclados, un resumen,
   `owner_voice` (cómo les escribe el negocio a sus clientes) y las conclusiones que tengan respaldo,
   sin un número fijo, cada una con las claves de los temas en que se apoya.
8. En una transacción, las filas activas anteriores de los cinco tipos pasan a `outdated`, se guardan
   las nuevas y se borran las conversaciones de corridas anteriores. Después se mezclan los campos de
   la marca.

El tiempo de respuesta se mide por turno: un turno del cliente empieza cuando abre la conversación o
escribe después del negocio, y termina con el próximo mensaje del negocio. No se descuentan las horas
fuera de horario, y los turnos sin respuesta no entran en la mediana. Las fechas son las del teléfono,
sin zona horaria.

Si la corrida falla, queda en `failed` y el zip ya está borrado: para repetirla, se sube uno nuevo.
Las conversaciones que alcanzó a guardar quedan hasta la próxima corrida que termine bien.

`GET /api/knowledge-insights/whatsapp-conversations` devuelve `metrics`, `analysis`, `questions`,
`objections`, `insights` y `conversations`: solo las conversaciones destacadas de esas filas.

## Audio (`audio`)

`ResearchAudioJob` llama a `AudioResearchService`. Requiere el audio que el usuario graba en la pantalla y
`OPENAI_API_KEY`. El navegador graba con `MediaRecorder`: webm en Chrome y Firefox, mp4 en Safari. El audio pesa
hasta 20 MB, el mismo límite de subida que el zip de WhatsApp. El timeout del job es el `retry_after` de la
conexión menos 60 segundos.

1. Se guarda en `storage/app/private/audios/`, con nombre aleatorio y la extensión que corresponde a su
   contenido, y su ruta queda en `input.audio_path`. Si no se puede crear la ejecución, se borra ahí mismo.
2. `OpenAIHelper::transcribeAudio()` lo manda a `/v1/audio/transcriptions` con `gpt-transcribe`, y el audio se
   borra enseguida, también si la transcripción falla: solo queda el texto. Si el audio no tiene voz, la
   investigación termina en `empty` y el análisis del audio anterior sigue vigente.
3. Guarda la transcripción como fuente `audio`.
4. Un análisis final recibe la transcripción, el nombre de la marca y el texto actual de seis campos. Devuelve
   `matches_brand`, los campos, un resumen y las conclusiones que tengan respaldo. Solo trae texto en los campos
   sobre los que el audio cuenta algo concreto y nuevo; el resto vuelve en null y queda como está.
5. En una transacción, el análisis y las conclusiones activas anteriores del audio pasan a `outdated` y se
   guardan los nuevos. Después se mezclan los campos de la marca.

Un audio nuevo funciona como volver a grabar: la pantalla muestra el último. Los audios anteriores quedan
guardados como fuentes, y lo que sumaron al perfil sigue ahí.

`GET /api/knowledge-insights/audio` devuelve `analysis`, `insights` y `audio`: la fuente con la transcripción que
leyó el análisis vigente, o null.
