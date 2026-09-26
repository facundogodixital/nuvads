# Competencia

Nota de diseño del 25/09/2026. Estos acuerdos no autorizan crear tablas, campos ni migraciones nuevas.

La marca carga a sus competidores con los enlaces que tenga de cada uno, y el sistema investiga esas fuentes para
saber qué hacen, qué les funciona y dónde fallan. Un competidor puede tener una sola fuente o las cuatro: sitio web,
Instagram, anuncios de Meta y reseñas de Google.

Es un módulo aparte de la investigación de la marca: tiene sus tablas, sus services, sus jobs y sus pantallas, y no
reusa los services ni los componentes de la marca. Comparte solo lo genérico: los helpers de los proveedores
(`ApifyHelper`, `FirecrawlHelper`, `OpenAIHelper`), `ResearchDispatcherService` y `BrandService`. La lectura de cada
fuente (llamar a Apify, transcribir imágenes, calcular métricas) está escrita de nuevo en cada service de
competencia, para poder cambiar un lado sin romper el otro.

## Tablas

- `competitors`: cada competidor de una marca, con `client_id`, `brand_id`, `name`, los cuatro enlaces
  (`website_url`, `instagram_username`, `meta_ads_url` y `google_maps_url`, todos nullable) y lo que sabemos de él
  en seis campos de texto. Ver "Lo que sabemos del competidor".
- `competitor_sources`: el material leído, como `knowledge_sources`. `type` es `web_page`, `instagram_post`,
  `meta_ad` o `google_review`, y `payload` tiene la misma forma que en las fuentes de la marca (ver
  [knowledge-model.md](knowledge-model.md#tipos-de-fuente)), salvo que las reseñas no guardan `detailed_rating` ni
  `context`. Tiene `s3_path`, como las fuentes de la marca, para cuando los payloads pesados pasen a S3.
- `competitor_research_runs`: cada investigación, como `research_runs`, con `competitor_id` en lugar de `brand_id`
  y `competitor_source_ids` en lugar de `knowledge_source_ids`. `type` es `website`, `instagram`, `meta_ads` o
  `google_reviews`, y los estados son los mismos, incluido `empty`.
- `competitor_insights`: las conclusiones, como `knowledge_insights`, con `competitor_id`, `competitor_source_ids`
  y `competitor_research_run_id`. Mismos estados (`active`, `outdated`, `superseded`, `rejected`) y nivel 1.

Todas tienen timestamps y soft deletes. Borrar un competidor lo saca con soft delete; sus fuentes, investigaciones y
conclusiones quedan, sin verse.

## Lo que sabemos del competidor

Seis campos de `competitors`, que cada investigación mezcla con lo que ya tenían, igual que los campos de la marca:
el modelo recibe su texto actual y lo reescribe completo, y lo que devuelve vacío no borra nada. El usuario los
puede editar en la pantalla del competidor.

- `competitor_offer_description`: qué vende, con precios y promociones cuando aparecen.
- `competitor_differentiators_description`: qué dice que lo hace distinto.
- `competitor_customers_description`: a quién le habla.
- `competitor_communication_description`: de qué habla, con qué tono, en qué formatos y con qué estilo.
- `competitor_strengths_description`: qué le funciona, con la evidencia que lo respalda.
- `competitor_weaknesses_description`: dónde falla.

Las cuatro fuentes reciben y pueden mejorar los seis campos; el prompt de cada una dice qué evidencia sirve para
cada uno. La lista vive en `CompetitorService::KNOWLEDGE_FIELDS`. Como en la marca, el análisis responde en
`matches_competitor` si la fuente parece de ese competidor; si es claramente de otro negocio, no se guarda ningún
campo y la pantalla lo avisa.

## Cómo corre una investigación

El recorrido es el de las investigaciones de la marca (ver [research-runs.md](research-runs.md#cómo-corre-una-investigación)):

- Se pide con `POST /api/competitors/{id}/research-runs`, body `{"type":"<tipo>"}`. `CompetitorResearchRunService`
  la rechaza con 422 (`competitor_link_missing`) si el competidor no tiene el enlace de esa fuente, y con 409
  (`research_already_running`) si ya hay una activa para ese competidor y ese tipo. La entrada sale del competidor y
  del bloque `competitors` de `config/research.php`, que tiene su propia configuración aunque hoy repita la de la
  marca: 6 posteos, 6 anuncios, 1000 reseñas y `gpt-6-luna`.
- La investigación y su job se guardan en la misma transacción. Los jobs están en `App\Jobs\Research\Competitors`,
  corren en `research_queue` con un intento, y cada uno llama a `research()` de su service:
  `ResearchCompetitorWebsiteJob` a `CompetitorWebsiteResearchService`, `ResearchCompetitorInstagramJob` a
  `CompetitorInstagramResearchService`, `ResearchCompetitorMetaAdsJob` a `CompetitorMetaAdsResearchService` y
  `ResearchCompetitorGoogleReviewsJob` a `CompetitorGoogleReviewsResearchService`. El de sitio web tiene un timeout
  de 600 segundos; los otros, el `retry_after` de la conexión menos 60.
- Si el competidor se borró mientras la investigación esperaba en la queue, el job lo registra en su log y termina
  sin llamar a ningún proveedor.
- Cada job escribe sus logs `storage/logs/<Job>Info.log` y `storage/logs/<Job>Errors.log` con un UUID de
  correlación. Si falla, la investigación queda en `failed` con un mensaje genérico.
- Si no hay nada para analizar, la investigación termina en `empty` con un `status_message`, sin guardar ni
  invalidar nada.
- Cuando termina bien, `CompetitorResearchRunService::complete()` la cierra y, en la misma transacción, encola el
  cruce con la competencia de la marca.

### Sitio web

Lee la portada con Firecrawl; si no tiene texto, termina en `empty`. Si tiene enlaces internos, una primera
consulta al modelo elige hasta dos para leer. Una segunda analiza todas las páginas juntas y devuelve los seis
campos mezclados, un resumen y las conclusiones. A diferencia de la marca, no busca identidad visual. Deja un
`website_analysis` con la respuesta completa en `payload` y un `website_insight` por conclusión.

### Instagram

Igual que en la marca: Apify trae los últimos posteos, cada uno pasa por el modelo con sus imágenes (un posteo que
falla se saltea), PHP calcula las métricas y un análisis final devuelve los campos, un resumen y las conclusiones.
Si el perfil no tiene posteos, termina en `empty`. Deja un `instagram_analysis`, con las métricas en
`payload.metrics`, y un `instagram_insight` por conclusión.

### Anuncios de Meta

Igual que en la marca: Apify trae los anuncios más nuevos de la Biblioteca de anuncios, cada uno pasa por el modelo
con sus imágenes y portadas de video, PHP calcula las métricas y un análisis final usa los días corriendo como señal
de lo que le funciona. Si la página no tiene anuncios, termina en `empty`. Deja un `meta_ads_analysis`, con las
métricas en `payload.metrics`, y un `meta_ads_insight` por conclusión.

### Reseñas de Google

Más simple que el de la marca: busca solo quejas y elogios, sin tramos de tiempo ni tendencias.

1. Apify trae hasta 1000 reseñas, las más nuevas primero. Sin reseñas, o solo con estrellas, termina en `empty`
   antes de guardar nada.
2. Guarda cada reseña como fuente y calcula las métricas en PHP: puntaje y cantidad de la ficha, reseñas leídas y
   con texto, promedio y distribución de estrellas, qué parte responde el dueño y la mediana de días que tarda.
3. El modelo lee las reseñas con texto en tandas de 200 y devuelve las quejas (`pains`) y los elogios
   (`strengths`) con los IDs de las reseñas que los mencionan y una destacada. PHP descarta los IDs que no son de la
   tanda. Si hubo más de una tanda, el modelo unifica los temas por nombre y PHP junta los IDs.
4. PHP cuenta las menciones, sin un mínimo: el peso de cada tema está en `mentions_share`. Elige hasta tres
   destacadas, las más nuevas entre las que marcó el modelo.
5. Un análisis final recibe las métricas, los temas con ejemplos, las 30 respuestas más recientes del dueño y los
   seis campos, y devuelve los campos mezclados, un resumen y las conclusiones que tengan respaldo.
6. En una transacción, las filas activas anteriores pasan a `outdated`, se guardan las nuevas y se borran las
   reseñas de investigaciones anteriores del competidor.

Deja un `google_reviews_analysis`, con las métricas en `payload.metrics`; un `google_reviews_pain` y un
`google_reviews_strength` por tema, con `highlight_ids`, `mentions_count` y `mentions_share` en `payload`; y un
`google_reviews_insight` por conclusión, que apunta a todas las reseñas leídas.

## Cruce con la competencia

`ResearchBrandCompetitionJob` llama a `BrandCompetitionResearchService` con el ID de la marca. Se encola cuando
termina bien una investigación de un competidor y cuando se borra un competidor. Corre en `research_queue`, con un
intento y 600 segundos de timeout. No tiene una fila de investigación: si falla, queda en sus logs y los campos
siguen como estaban.

1. Toma los competidores analizados de la marca: los que tienen algún campo de "Lo que sabemos" con texto. Si no
   hay ninguno, deja en null los tres campos de la competencia sin consultar al modelo.
2. Manda al modelo nueve campos de la marca y, de cada competidor analizado, su nombre y sus seis campos.
3. Guarda en `brands`, enteros, `competitors_strengths_description` (qué les funciona a los competidores),
   `competitors_weaknesses_description` (dónde fallan) y `competitors_opportunities_description` (qué puede hacer
   la marca con eso). Se guardan como llegan, también vacíos, así un competidor borrado desaparece de la síntesis.
   No se editan en la pantalla.
4. Mezcla `brand_content_opportunities_description` con las ideas de contenido que salen del cruce. Ese campo lo
   llenan también las fuentes de la marca, así que un valor vacío del modelo no lo borra.

Editar a mano lo que sabemos de un competidor no dispara el cruce: se ve en la próxima investigación que termine
bien.

## API

- `GET /api/competitors`: `competitors`, los de la marca, y `research_statuses`, el estado de las cuatro fuentes de
  cada uno por su ID y por tipo.
- `POST /api/competitors`: da de alta un competidor con `name` y los enlaces que tenga. Los enlaces se normalizan
  como los de la marca: el usuario de Instagram sale del enlace del perfil, y la página de Facebook queda como
  `https://www.facebook.com/<nombre>`.
- `GET /api/competitors/{id}`: `competitor` y `research_statuses`, por tipo.
- `PATCH /api/competitors/{id}`: edita el nombre, los enlaces y lo que sabemos de él.
- `DELETE /api/competitors/{id}`: lo borra y encola el cruce.
- `POST /api/competitors/{id}/research-runs`: pide una investigación.
- `GET /api/competitors/{id}/research-runs/<fuente>/status`: `active`, `latest` y `last_completed`.
- `GET /api/competitors/{id}/insights/<fuente>`: `analysis` e `insights`; Instagram suma `posts`, los anuncios
  `ads`, y las reseñas `pains`, `strengths` y `reviews`, solo las destacadas.

`<fuente>` es `website`, `instagram`, `meta-ads` o `google-reviews`. Los competidores de otra marca o de otro
cliente responden 404.

## Pantallas

- Competencia (`/competitors`): arriba "Tu marca frente a la competencia", con los tres campos del cruce; abajo una
  tarjeta por competidor, con las fuentes que tienen enlace y su estado. "Sumar competidor" abre `CompetitorModal`,
  con su store `competitorModalStore`, y al guardar lleva a la página del competidor.
- Competidor (`/competitors/:competitorId`): "Lo que sabemos", editable, y la lista de sus fuentes con enlace. Las
  que no tienen enlace se ofrecen para sumar, y abren el mismo modal con el foco en ese campo. Editar y Borrar van en
  el encabezado.
- Fuente (`/competitors/:competitorId/sources/<website|instagram|meta-ads|google-maps>`): el panel con el enlace, el
  botón para analizar y el estado, que se consulta cada diez segundos mientras hay una investigación activa; y "Lo
  que aprendimos", con el resumen, las conclusiones y, según la fuente, métricas, posteos, anuncios o quejas y
  elogios con sus reseñas.

Los componentes están en `resources/js/pages/CompetitorsPage/` y `resources/js/pages/CompetitorPage/`. El modal y
`CompetitorSourceStateChip`, el chip con el estado de una fuente, están en `resources/js/components/` porque los usan
las dos páginas. No importan nada de `BrandPage`.
