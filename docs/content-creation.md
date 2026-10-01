# Creación de contenido

Nota de diseño de producto iniciada el 27/09/2026 y actualizada el 01/10/2026. Se completa a medida que se decide: lo
que figura en "Por definir" sigue abierto. Estos acuerdos no autorizan crear tablas, campos ni migraciones nuevas.

El 30/09/2026 cambió el enfoque: Crear empieza por el tipo de contenido. Lo vigente está en "Enfoque nuevo", de forma
tentativa. El enfoque anterior, que partía de temas, quedó deprecado y se quitó de este documento el 01/10/2026 para
que no confunda. Sigue en el historial de git, en el commit 452c9db.

## Enfoque nuevo (tentativo)

### Cómo funciona

- El tipo de contenido es el primer paso de Crear.
- Por ahora, el usuario elige un tipo de contenido a la vez.
- Cada tipo está atado a lo que produce: trae sus instrucciones ya escritas. Por ejemplo, "Reseñas de clientes" indica
  que la pieza muestra de 1 a 3 reseñas de clientes, con sus estrellas, de una forma determinada.
- Después de elegir el tipo, la app le propone ideas para armar la pieza. El usuario elige una y continúa.
- Los tipos los definimos nosotros; el usuario no los crea.
- Una pieza junta cuatro textos: las `instructions` del tipo, un ángulo (si el tipo los tiene), una composición de
  `layouts` (si el tipo las tiene) y las `instructions` del estilo. Con qué material y con qué foto se llena eso se
  define con la idea y la pieza.

Se piensa para quien quiere hacer el contenido de sus redes y no es experto en Canva ni en otras herramientas de diseño.

### De dónde sale la variedad

- Entre ideas de un mismo tipo: material por ángulo. Cada idea se para sobre evidencia distinta (otro elogio, otra
  pregunta), y en los tipos que cuentan algo, un mismo material admite varias formas de contarlo. Un tipo con cinco
  preguntas y cuatro ángulos tiene veinte ideas posibles. Si un tipo genera ideas parecidas, le faltan ángulos o el
  cerebro tiene poco material, y eso se muestra en lugar de disimularse inventando.
- Entre piezas: estilo por composición. El estilo es el lenguaje visual, global, y vale para cualquier tipo. La
  composición es cómo se ordenan los elementos propios del tipo, y es del tipo. Cuatro estilos y cuatro
  composiciones dan dieciséis piezas que no se parecen aunque salgan de la misma receta. La edición del usuario
  queda para lo que no le gustó, no para salvar la monotonía.

### Modelo de `content_types`

Ya está programado, desde el 01/10/2026: la tabla, el modelo, `ContentTypeService`, el endpoint
`GET /api/content-types` y `ContentTypeSeeder`, que carga Reseñas de clientes y Educativo. Dudas antes de comprar
sigue siendo solo un ejemplo. La rotación todavía no está programada. El avance está en
[content-creation-progress.md](content-creation-progress.md).

| Campo | Para qué sirve |
| --- | --- |
| `key` | El nombre interno y fijo del tipo, por ejemplo `customer_reviews`. Es lo que no cambia cuando el tipo se rota: la fila nueva nace con la misma `key` y otro `id`. |
| `name` | El título de la tarjeta que ve el usuario al entrar a Crear. |
| `description` | La bajada de esa tarjeta, debajo del título. |
| `instructions` | Texto plano: la receta del tipo, para el modelo. Dice qué es una idea de este tipo, qué material usa, qué tiene prohibido y cómo se arma la pieza. El usuario no la ve. |
| `inputs` | JSON: los nombres de las entradas del cerebro que lee el tipo. Son su material y deciden si la tarjeta aparece prendida. Ver abajo. |
| `angles` | JSON, nullable: formas de contar, para que dos ideas sobre lo mismo no digan lo mismo. El modelo elige una por idea. Null en los tipos que solo muestran, como las reseñas. Ver abajo. |
| `layouts` | JSON, nullable: formas de mostrar los elementos del tipo dentro de una placa, para que dos piezas del mismo tipo no se vean iguales. Se usa una por imagen. Ver abajo. |
| `deleted_at_ts` | 0 mientras la fila está activa, y el timestamp del borrado cuando se rota. Existe para que la base impida dos filas activas con la misma `key`. |

Lleva además timestamps y soft deletes, como todas las tablas; `deleted_at` se llena cuando el tipo se rota. No lleva
`client_id` ni `brand_id`, por decisión explícita del usuario del 01/10/2026: es nuestra e igual para todas las
marcas. Si `styles` sigue el mismo criterio se decide en su tramo.

- Para cambiar un tipo, se rota: la fila vigente se borra con soft delete y se crea una nueva con la misma `key`. Las
  piezas ya generadas siguen apuntando a la fila vieja; las nuevas usan la nueva.
- La `key` es única junto con `deleted_at_ts`: `unique(key, deleted_at_ts)`. Así no puede haber dos tipos activos con
  la misma `key`, y los rotados no chocan. Con `deleted_at` no alcanza, porque en MySQL los null no chocan entre sí.
  `deleted_at_ts` se completa junto con `deleted_at` al borrar.
- `instructions` es la parte que no cambia: qué es una idea del tipo, qué material usa, qué tiene prohibido y qué
  elementos lleva la pieza. Vale igual para todas las ideas del tipo. Lo que varía no va ahí: va en `angles` y en
  `layouts`. Hoy mezcla dos momentos, qué es una idea (se usa al proponer ideas) y cómo se arma la pieza (se usa al
  producirla); está bien mientras lo lea un solo prompt, y vuelve a verse al modelar la pieza.
- `inputs`: qué partes del cerebro lee el tipo. La generación de ideas de un tipo recibe solo esas entradas más el
  perfil de la marca como contexto de fondo, que reciben todos los tipos y por eso no se declara. Cada nombre
  corresponde a una entrada de un catálogo fijo en código, con una sola responsabilidad: leer su parte del cerebro y
  devolverla como texto para el prompt, con los IDs de respaldo. Sumar un tipo es una fila más, sin código, mientras
  use entradas que existen; sumar una entrada es código, y es la excepción. Cada entrada sabe si está vacía: un tipo
  cuyas entradas están vacías no se ofrece, o se muestra apagado con el aviso de qué fuente falta. Así el modelo no
  inventa reseñas ni dudas que no existen.
- Tentativo: cada entrada es un algoritmo puntual, encapsulado en su propia clase, que interpreta su nombre y sabe
  dónde buscar en el cerebro, cómo elegir qué manda y cómo devolverlo. El tipo no tiene algoritmo: solo nombra
  entradas, y varios tipos comparten las mismas. Un tipo nuevo es una fila; una entrada nueva es una clase, y solo
  hace falta cuando un tipo necesita leer algo que ninguna entrada lee todavía. Los detalles de cada entrada, qué lee
  exactamente y cuánto manda, se definen en la implementación.
- Tentativo: cuando un tipo no tiene el material que necesita, se le puede pedir al usuario. Cómo, está por resolver.
- `angles`: cada texto es la instrucción de una forma de contar, por ejemplo paso a paso, error común, comparación o
  mito. Sirven para que dos ideas sobre lo mismo no digan lo mismo. Los escribimos nosotros, como las `instructions`,
  y son internos: el usuario no los ve. Van en null en los tipos donde no aplican, como Reseñas de clientes, que
  muestra lo que dijeron los clientes: dos elogios distintos ya son dos piezas distintas.
- `layouts`: cada texto es una composición, una manera de mostrar y ordenar los elementos propios del tipo dentro de
  una placa, por ejemplo la reseña en una píldora o en una tarjeta grande. Es lo mismo que `angles` pero para la
  forma. Internos, escritos por nosotros, null en los tipos que no los necesiten.
- Regla de escritura de los `layouts`: un layout nunca dice cómo se reparte el contenido entre placas, que es del
  formato y del guión; así vale igual para una placa única que para cada placa de un carrusel. Una placa que no trae
  esos elementos, como el cierre de un carrusel, mantiene la misma estética sin ellos. Tampoco fija fondos ni fotos,
  que son del estilo, ni agrega datos que no existen, como un contador de likes.
- La competencia no es material de ninguno de los tipos de ejemplo; hoy llega por los campos `competitors_*` del
  perfil de fondo. Cuando aparezca un tipo que la use como base, se suma como una entrada más del catálogo.

Catálogo de entradas, con lo que hoy existe en el cerebro:

| Entrada | Qué lee |
| --- | --- |
| `google_reviews` | Las fuentes `google_review` de 4 y 5 estrellas con texto. |
| `google_review_strengths` | Las conclusiones `google_reviews_strength`, con sus menciones y reseñas destacadas. |
| `google_review_products` | Los productos que nombran las reseñas, con sus reseñas: `products` del análisis `google_reviews_brand_analysis`. |
| `google_review_staff` | Las personas del equipo que nombran las reseñas, con sus reseñas: `staff` del mismo análisis. |
| `google_review_score` | El puntaje y la cantidad de reseñas de la ficha de Google: `google_total_score` y `google_reviews_count` de `google_reviews_metrics`. Es un dato de apoyo para la placa. |
| `whatsapp_questions` | Las conclusiones `whatsapp_conversations_question`, con su `owner_answer`. |
| `whatsapp_objections` | Las conclusiones `whatsapp_conversations_objection`, con su `owner_answer`. |
| `audio_insights` | Las conclusiones `audio_insight`. |
| `brand_faq` | El campo `brand_customers_faq_description` del perfil. |
| `audio_transcripts` | La transcripción de los audios del dueño, no solo sus conclusiones: las fuentes `audio`. |
| `uploaded_documents` | Lo que dicen los documentos que subió el dueño, como catálogos o guías: `content` de las fuentes `document`. |
| `website_pages` | Las páginas leídas del sitio web: las fuentes `web_page`. |
| `whatsapp_purposes` | Para qué compran los clientes: `purposes` del análisis `whatsapp_conversations_brand_analysis`. Sirve como fuente de temas. |

Reseñas de clientes toma las cinco entradas de Google: las reseñas, y sus agrupamientos por elogio, por producto y por
persona del equipo, más el puntaje. Educativo toma las preguntas de los chats, que dicen qué enseñar, y los lugares
donde vive lo que el negocio sabe: el audio, los documentos y el sitio. Las frases textuales de clientes de WhatsApp
no entran como reseñas: son mensajes privados, y publicarlos pide permiso del cliente. Todas las entradas son por
ahora solo nombres: sus clases se escriben en el tramo de las entradas.

### Ejemplos de carga de `content_types`

```json
{
  "id": 1,
  "key": "customer_reviews",
  "name": "Reseñas de clientes",
  "description": "Lo que ya dijeron de ti",
  "instructions": "Cada idea muestra de 1 a 3 reseñas de Google que elogian lo mismo. El título dice qué tienen en común, por ejemplo \"Lo que más repiten: que te atienden bien\". Usá solo reseñas de 4 o 5 estrellas y con texto, y preferí las más recientes. Las palabras del cliente van tal cual: podés cortar una reseña larga con puntos suspensivos, pero nunca cambiar lo que dice. Cada reseña lleva sus estrellas y el nombre de pila de quien la escribió, sin apellido; como avatar, la inicial del nombre o un ícono, nunca una cara. Con una reseña, la pieza es una placa; con dos o tres, un carrusel con una reseña por placa y una placa final con una invitación acorde al negocio.",
  "inputs": ["google_reviews", "google_review_strengths", "google_review_products", "google_review_staff", "google_review_score"],
  "angles": null,
  "layouts": [
    "La reseña dentro de una píldora redondeada, con el avatar a un lado, el nombre y las estrellas arriba y el texto debajo.",
    "La reseña en una tarjeta grande, con el nombre arriba y las estrellas debajo del texto.",
    "La reseña como si fuera un posteo de Instagram, con el nombre como usuario arriba y corazones de adorno.",
    "La reseña como una burbuja de chat, con el nombre y las estrellas en el encabezado."
  ],
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

```json
{
  "id": 2,
  "key": "educational",
  "name": "Educativo",
  "description": "Lo que tus clientes necesitan saber",
  "instructions": "Cada idea enseña una sola cosa concreta y útil para elegir, comprar o usar bien lo que vende el negocio. Sale de lo que preguntan en los chats o de lo que el dueño cuenta en su audio; si existe, usá la respuesta del negocio (owner_answer). Podés completar con conocimiento general del rubro, pero nunca inventes datos propios del negocio: precios, medidas, stock o plazos. El título promete lo que se aprende, por ejemplo \"Qué sustrato elegir según la etapa del cultivo\". La pieza es un carrusel: una placa de gancho, de 2 a 4 placas con una idea cada una y una placa de cierre que invite a consultar. Si alcanza con un solo consejo, una placa.",
  "inputs": ["whatsapp_questions", "audio_insights", "brand_faq", "audio_transcripts", "uploaded_documents", "website_pages", "whatsapp_purposes"],
  "angles": [
    "Explicá en pasos cortos cómo hacer algo.",
    "Mostrá los errores más comunes y cómo evitarlos.",
    "Compará dos opciones y decí cuándo conviene cada una.",
    "Tomá una creencia equivocada del rubro y desarmala."
  ],
  "layouts": [
    "El texto en grande, con un número bien visible al lado cuando la placa es parte de una secuencia.",
    "Como una tarjeta: un ícono simple del tema arriba y el texto abajo.",
    "El texto en una mitad de la placa y una imagen del tema en la otra.",
    "Una frase corta y grande arriba y la explicación más chica debajo."
  ],
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

```json
{
  "id": 3,
  "key": "purchase_doubts",
  "name": "Dudas antes de comprar",
  "description": "Lo que frena a tus clientes",
  "instructions": "Cada idea responde una duda o un freno real que aparece en los chats antes de comprar, por ejemplo el costo del envío o \"no tengo tiempo para implementarlo\". La respuesta sale de lo que contesta el negocio (owner_answer): si no la respondió por escrito, no hagas la idea. No prometas descuentos, plazos ni condiciones que no estén ahí. Nombrá la duda como la diría el cliente y respondela con calma, sin ponerte a la defensiva ni hablar de la competencia. La pieza es una placa con la duda y una respuesta corta, o un carrusel si la respuesta necesita más.",
  "inputs": ["whatsapp_objections"],
  "angles": [
    "Respondé la duda de frente, en pocas palabras.",
    "Mostrá con un caso o un dato del negocio por qué no es un problema.",
    "Compará lo que cuesta ahora con lo que ahorra o evita después.",
    "Contá el proceso paso a paso para que se vea simple."
  ],
  "layouts": [
    "La duda como una pregunta en grande y la respuesta en más chico, separadas por una línea o un cambio de color cuando comparten placa.",
    "Como un chat: la duda en un globo del cliente y la respuesta en un globo del negocio.",
    "Dos colores: uno para lo que piensa el cliente y otro para lo que pasa en realidad.",
    "La duda tachada y la respuesta en grande."
  ],
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

### Modelo tentativo de `styles`

El estilo es el lenguaje visual de una pieza: vidrio 3D, ilustración plana, tipografía gigante, foto real. Es
global: no cuelga de un tipo, y `content_types` no apunta a estilos, porque la misma estética sirve para una reseña,
un consejo o una duda. Si más adelante aparece un tipo que solo tiene sentido con ciertos estilos, ahí se ve cómo
restringirlo; hoy no hay ninguno que lo pida.

Hermana de `content_types`, con las mismas reglas: la escribimos nosotros, se cambia rotando la fila con soft delete y
la misma `key`, y las piezas ya generadas siguen apuntando a la fila que usaron. Mismos campos, sin `inputs`,
`angles` ni `layouts`:

| Campo | Para qué sirve |
| --- | --- |
| `key` | Identificador del estilo, por ejemplo `glass_3d`. |
| `name` | El nombre del estilo. |
| `description` | Una bajada corta. |
| `instructions` | Texto plano: la receta de la estética, qué hay de fondo, cómo va el texto, con qué tipografía y color, dónde el logo. |
| `deleted_at_ts` | 0 mientras el estilo está activo; el timestamp del borrado cuando se borra. |

Lleva además timestamps y soft deletes. `unique(key, deleted_at_ts)`, como en `content_types`.

- `instructions` habla de la estética, no de los elementos de la pieza: eso lo dice el tipo. Los dos se juntan en el
  prompt de la pieza. Lo que es propio de un tipo, como las estrellas y el nombre en una reseña, va en las
  `instructions` del tipo y funciona con cualquier estilo.
- `name` y `description` quedan por si algún día el estilo se muestra al usuario. Si se decide que nunca se ve, sobran.
- Hay un estilo libre, `free`, que es una fila más y no un caso especial en el código: sus `instructions` le dan la
  libertad al modelo y le marcan los límites. Entra en la rotación como cualquier otro.
- Qué estilo toma cada pieza y cómo se rota se define con la pieza.

### Ejemplos de carga de `styles`

```json
{
  "id": 1,
  "key": "glass_3d",
  "name": "Vidrio 3D",
  "description": "Objetos translúcidos que flotan",
  "instructions": "Objetos 3D translúcidos, como de vidrio, que flotan sobre un fondo claro y suave. Formas redondeadas, sombras blandas. El texto en negro, con una palabra o el título en el color principal de la marca, en su tipografía. El logo chico, en una esquina.",
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

```json
{
  "id": 2,
  "key": "flat_illustration",
  "name": "Ilustración plana",
  "description": "Dibujo simple, colores lisos",
  "instructions": "Ilustración plana, sin sombras ni degradados, con los colores de la marca como paleta. Un objeto grande del rubro ambienta la escena. Texto en la tipografía de la marca, en un color liso que contraste. El logo abajo.",
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

```json
{
  "id": 3,
  "key": "big_typography",
  "name": "Tipografía gigante",
  "description": "Una palabra enorme de fondo",
  "instructions": "Una o dos palabras del tema en tamaño gigante detrás de todo, en degradado con los colores de la marca, recortadas por los bordes. Adelante, una tarjeta blanca con el contenido. Fondo neutro claro. Logo en la tarjeta.",
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

```json
{
  "id": 4,
  "key": "photo_real",
  "name": "Foto real",
  "description": "Una foto tuya de fondo",
  "instructions": "Una foto real de la marca como fondo completo, elegida entre las que mejor se relacionan con lo que dice la pieza. Oscurecida lo justo para que el texto se lea. Texto en blanco, en la tipografía de la marca. El logo chico en una esquina.",
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

```json
{
  "id": 5,
  "key": "free",
  "name": "Libre",
  "description": "Que la app proponga algo distinto",
  "instructions": "Componé la pieza como te parezca mejor para lo que dice, con la identidad de la marca: su logo, sus colores, sus tipografías y, si suman, sus fotos reales. Buscá algo que no se parezca a los otros estilos del catálogo ni a las últimas piezas de la marca. Lo único fijo es que el texto se tiene que leer bien.",
  "created_at": "2026-09-30 12:00:00",
  "updated_at": "2026-09-30 12:00:00",
  "deleted_at": null,
  "deleted_at_ts": 0
}
```

### Qué ve el usuario en Crear (tentativo)

Los textos que ve el usuario van en español neutro, de tú, como el resto de la app y como pide `AGENTS.md`. Las
`instructions`, los `angles` y los `layouts` van en voseo: son instrucciones para el modelo, como los prompts que ya
existen. El contenido de ejemplo de una marca, como los guiones de Up!, va en la voz de esa marca.

Paso 1. Entra y ve la pregunta "¿De qué quieres hablar?" y una tarjeta por fila de `content_types`, con `name` y
`description`, todas a la vista sin scroll. La tarjeta es el botón: toca una y sigue. Como elige un tipo por vez, no
hay tildes múltiples ni barra de progreso. Los tipos sin material aparecen igual, apagados y sin poder elegirse, con
una línea que dice qué fuente falta; así ve el menú completo desde el primer día. Si ninguno tiene material, es la
misma pantalla con todo apagado más un aviso que lo lleva a cargar fuentes. Ya está programada la primera parte: la
pregunta y las tarjetas, todas prendidas y sin acción al tocarlas. Prender y apagar llega con las entradas, y la
acción, con el paso 2.

Paso 2. Al elegir un tipo, se generan ideas de ese tipo y se muestran en una lista de un renglón cada una, con un
renglón chico que dice de dónde salen. Elige una y sigue. Los detalles se ven después.

Paso 3. Al elegir una idea, la app escribe la pieza y el usuario la lee y la corrige antes de que se dibuje nada.
Cuatro conceptos, que no se mezclan:

- Placa: una imagen de Instagram. No es una entidad: es cada elemento del guión.
- Formato: cómo se publica la pieza. Placa única o carrusel (varias placas que se pasan deslizando) por ahora;
  historia y reel después. Es un dato de cada pieza, no del tipo: un tipo admite los dos y una misma idea puede
  dar una pieza en placa única y otra en carrusel. Hoy las `instructions` de cada tipo dicen en prosa cuándo va
  cada uno, y se deja así: no hace falta un campo aparte mientras el modelo lo lea y el usuario lo pueda cambiar.
- Guión: el texto que va escrito dentro de cada placa. Una lista ordenada de textos, uno por placa. Una placa única
  tiene un guión de un solo texto.
- Copy: el texto del posteo, el que va debajo de la imagen en Instagram, fuera de la placa. No es el guión.

Al entrar, una sola llamada al modelo recibe la idea (`title` y `angle`), las `instructions` de su tipo, el
material de respaldo leído por los IDs que la idea guardó y el perfil de la marca, y devuelve los dos guiones, el de
placa única y el de carrusel, más el copy. Mientras responde, la pantalla dice "Escribiendo tu pieza".

La pantalla tiene tres zonas: arriba, el `title` de la idea con el tipo y el ángulo en chico, sin editar; en el
medio, el selector de formato y una caja de texto por placa, numeradas; abajo, el copy en una caja más grande,
"Texto del posteo". Y el botón "Generar la pieza", que recién ahí dibuja. Estilo y composición no aparecen: se
resuelven al generar.

Cómo elige el usuario:

- Formato: el selector viene marcado con el que eligió el modelo. Cambiarlo es instantáneo, porque los dos guiones
  ya vinieron; no llama al modelo.
- Guión: una sola propuesta, no varias; leer tres guiones es más trabajo que corregir uno. Corrige a mano en cada
  caja. Cada caja tiene "Otra versión", que pide al modelo solo ese texto de nuevo, manteniendo el resto;
  "Reescribir todo" pide el guión completo. En carrusel puede sumar o sacar placas.
- Copy: igual: una propuesta, se corrige a mano, "Otra versión" lo vuelve a pedir entero.
- Tentativo: "Otra versión" lleva un campo opcional donde el usuario dice qué quiere distinto ("más corto", "sin
  hablar de precios"). Vacío, el modelo simplemente reescribe.

Las variantes quedan para la imagen, en el paso siguiente: mirar tres imágenes es rápido y corregir una es difícil.

```text
Qué sustrato elegir según la etapa del cultivo
Educativo · Compará dos opciones y decí cuándo conviene cada una

Formato   (•) Carrusel   ( ) Placa única

 1  Sustrato para germinar y sustrato para florecer no son lo mismo
 2  Para germinar: liviano y aireado, que no retenga de más
 3  En vegetativo: más nutrientes, que aguante riegos seguidos
 4  En floración: bajá el nitrógeno, subí fósforo y potasio
 5  ¿Dudas con tu etapa? Escribinos y te decimos cuál llevar
    + Sumar placa

Texto del posteo
 Cada etapa pide un sustrato distinto. Te contamos cuál va en cada una para que no pierdas plantas por una
 mezcla equivocada. ...

                                              [ Generar la pieza → ]
```

Paso 4. Al tocar "Generar la pieza" pasan dos cosas en orden. Primero nace la pieza en la base, con lo que quedó en
pantalla: formato, guión y copy ya corregidos. Segundo, se encola un job que dibuja. Lo que dibuja, en dos tiempos:

- Primero, tres alternativas de la placa 1 sola, cada una con una combinación distinta de estilo (una fila de
  `styles`) y composición (un texto de `layouts` del tipo). El usuario elige una mirando la portada, que es lo que
  se ve en el feed. Tres es un número de arranque, en configuración.
- Después, las placas restantes con el mismo estilo y la misma composición de la elegida, pasándole al modelo la
  placa 1 elegida como referencia para que el carrusel quede coherente. En placa única este segundo tiempo no
  existe.

El prompt de cada imagen junta las `instructions` del tipo, las `instructions` del estilo, la composición, el
texto de esa placa y la identidad de la marca: logo, colores, fuentes y, si el estilo lo pide, sus fotos.

Todo lo que se dibuja queda en S3 y con su fila en la base, elegido o no. Cómo se ajusta la imagen elegida se ve
después.

Qué se guarda y cuándo:

- La idea nace cuando el usuario la elige con "Seguir con esta". Las propuestas que no eligió no se guardan.
- La pieza nace con "Generar la pieza", con el formato, el guión y el copy que quedaron después de las correcciones.
  Los dos guiones y las ediciones del paso 3 no se persisten antes de eso.
- Cada imagen dibujada se guarda al dibujarse, elegida o no.

### Modelo tentativo de `ideas`

Todo lo de la idea es tentativo: es lo mínimo que necesita el paso 2, y se completa cuando lleguen los pasos que
lo pidan. Primera tabla por marca: lleva `client_id`, `brand_id`, timestamps y soft deletes.

| Campo | Para qué sirve |
| --- | --- |
| `content_type_id` | La fila exacta del tipo con la que nació. Si el tipo se rota, la idea sigue sabiendo su receta. |
| `title` | El renglón que ve el usuario. |
| `angle` | El texto del ángulo usado, copiado, para que la idea se lea sola. Null en los tipos sin ángulos. |
| `knowledge_insight_ids` | JSON: las conclusiones en que se apoya. |
| `knowledge_source_ids` | JSON: las fuentes concretas que muestra o usa, por ejemplo las reseñas. |
| `status` | Por ahora solo `proposed`. Los demás estados, cuando hagan falta. |
| `model` | Modelo de IA que la escribió. |

- La idea se guarda cuando el usuario la elige, con su respaldo de IDs. Las propuestas que no eligió no se guardan,
  así que `proposed` deja de ser el primer estado; cuál es, se ve con los estados.
- El ángulo va en la idea, no en la pieza.
- El renglón chico de la pantalla sale de qué entradas aportaron los IDs de respaldo.

Ejemplo, las tres ideas de Up! de arriba:

```json
[
  {
    "id": 21, "client_id": 3, "brand_id": 7, "content_type_id": 1,
    "title": "Lo que más repiten: que te atienden bien",
    "angle": null,
    "knowledge_insight_ids": [71],
    "knowledge_source_ids": [512, 587, 601],
    "status": "proposed", "model": "gpt-6-luna"
  },
  {
    "id": 22, "client_id": 3, "brand_id": 7, "content_type_id": 1,
    "title": "Te eligen por el asesoramiento",
    "angle": null,
    "knowledge_insight_ids": [74],
    "knowledge_source_ids": [533, 598],
    "status": "proposed", "model": "gpt-6-luna"
  },
  {
    "id": 23, "client_id": 3, "brand_id": 7, "content_type_id": 1,
    "title": "\"Llegó al otro día\": lo que dicen de tus envíos",
    "angle": null,
    "knowledge_insight_ids": [76],
    "knowledge_source_ids": [520, 544, 590],
    "status": "proposed", "model": "gpt-6-luna"
  }
]
```

Timestamps y soft deletes omitidos.

### Modelo tentativo de `pieces`

Una fila por pieza. Lleva `client_id`, `brand_id`, timestamps y soft deletes.

| Campo | Para qué sirve |
| --- | --- |
| `idea_id` | La idea de la que sale. El ángulo no se repite: se llega por acá. |
| `format` | `single` o `carousel`. |
| `script` | JSON: el guión, una lista ordenada de textos, uno por placa, ya corregido por el usuario. |
| `copy` | El texto del posteo, ya corregido. |
| `status` | Por definir. |

Lo que escribió el modelo antes de la corrección no se guarda. El estilo y la composición no van acá: van en cada
imagen, porque cada alternativa se dibujó con una combinación distinta. Las imágenes finales de la pieza son sus
filas de `piece_images` en `chosen`, ordenadas por `position`.

```json
{
  "id": 1,
  "client_id": 3,
  "brand_id": 7,
  "idea_id": 24,
  "format": "carousel",
  "script": [
    "Sustrato para germinar y sustrato para florecer no son lo mismo",
    "Para germinar: liviano y aireado, que no retenga de más",
    "En vegetativo: más nutrientes, que aguante riegos seguidos",
    "En floración: bajá el nitrógeno, subí fósforo y potasio",
    "¿Dudas con tu etapa? Escribinos y te decimos cuál llevar"
  ],
  "copy": "Cada etapa pide un sustrato distinto. Te contamos cuál va en cada una para que no pierdas plantas por una mezcla equivocada. ..."
}
```

### Modelo tentativo de `piece_images`

Una fila por cada imagen que se dibujó, elegida o no. Lleva `client_id`, `brand_id`, timestamps y soft deletes.

| Campo | Para qué sirve |
| --- | --- |
| `piece_id` | La pieza a la que pertenece. |
| `position` | Qué placa es: 1 la portada, 2 la segunda, etc. En placa única siempre 1. |
| `style_id` | La fila de `styles` con la que se dibujó. |
| `layout` | El texto de la composición usada, copiado de `layouts` del tipo, como `angle` en la idea. |
| `s3_path` | Dónde quedó la imagen. |
| `status` | `proposed` recién dibujada, `chosen` la que eligió el usuario, `discarded` las otras. Nada se borra. |
| `model` | Modelo de IA que la dibujó. |

Las placas restantes que se dibujan después de elegir la portada nacen directamente en `chosen`, con el `style_id` y
el `layout` de la elegida.

```json
[
  { "id": 1, "piece_id": 1, "position": 1, "style_id": 1, "layout": "El texto en grande, con un número bien visible al lado cuando la placa es parte de una secuencia.", "s3_path": "7/pieces/1/images/1.png", "status": "discarded", "model": "gpt-image-2" },
  { "id": 2, "piece_id": 1, "position": 1, "style_id": 3, "layout": "Como una tarjeta: un ícono simple del tema arriba y el texto abajo.", "s3_path": "7/pieces/1/images/2.png", "status": "chosen", "model": "gpt-image-2" },
  { "id": 3, "piece_id": 1, "position": 1, "style_id": 4, "layout": "El texto en una mitad de la placa y una imagen del tema en la otra.", "s3_path": "7/pieces/1/images/3.png", "status": "discarded", "model": "gpt-image-2" },
  { "id": 4, "piece_id": 1, "position": 2, "style_id": 3, "layout": "Como una tarjeta: un ícono simple del tema arriba y el texto abajo.", "s3_path": "7/pieces/1/images/4.png", "status": "chosen", "model": "gpt-image-2" },
  { "id": 5, "piece_id": 1, "position": 3, "style_id": 3, "layout": "Como una tarjeta: un ícono simple del tema arriba y el texto abajo.", "s3_path": "7/pieces/1/images/5.png", "status": "chosen", "model": "gpt-image-2" }
]
```

`client_id`, `brand_id`, timestamps y soft deletes omitidos en el ejemplo.

Ejemplo con Up!, que tiene reseñas de Google, chats de WhatsApp y un audio: en el paso 1 ve las tres tarjetas
prendidas. Elige Reseñas de clientes y en el paso 2 ve, por ejemplo:

```text
Reseñas de clientes · Estas son las ideas que te salen a ti

( ) Lo que más repiten: que te atienden bien          sale de Google · 3 reseñas
( ) Te eligen por el asesoramiento                    sale de Google · 2 reseñas
( ) "Llegó al otro día": lo que dicen de tus envíos   sale de Google · 3 reseñas
```

### Por definir

- Cómo se usa el análisis de la competencia.
- Los estados de la idea y cómo se articula con los pasos que siguen.
- Los estados de la pieza.
- Que el usuario pueda marcar en la lista qué idea no le gusta, para tener en cuenta y aprender.
- Cómo se ajusta la imagen elegida.
- Cómo se eligen las tres combinaciones de estilo y composición para que no repitan lo reciente de la marca.
- Si un tipo se prende con al menos una entrada con material o necesita todas. Se propuso "al menos una", sin decidir.
- Qué lee exactamente cada entrada y cuánto manda al modelo: se define en la implementación.
- Si el tema sigue existiendo, cómo se lleva adelante Inspiración y cómo se organizan las ideas.
- El modelo de la pieza, y cómo se articula con los tipos y los estilos: qué estilo y qué composición toma y cómo se
  rotan.
- Cómo se le pide al usuario el material que le falta a un tipo.
- Ajustar las `instructions` de Reseñas de clientes y de Educativo a las entradas que se sumaron el 01/10/2026: hoy
  no dicen qué hacer con el puntaje de Google, ni nombran los documentos y el sitio como origen de las ideas.
