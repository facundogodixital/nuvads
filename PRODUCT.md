# Nuvads · Brief de producto

<!-- impeccable:product-schema 1 -->

> **Etapa MEGA MVP.** Este documento describe la dirección del producto. Casi cualquier feature
> puntual puede cambiar o descartarse; lo que se busca conservar es la esencia.
> Última actualización: 19/09/2026.

## Qué es

Nuvads es una app para quien necesita contenido de calidad para redes. El usuario carga su
negocio una vez, con poco esfuerzo, y recibe contenido terminado y de calidad, hecho por un
sistema que conoce a fondo su negocio y a su competencia, y que aprende de cada corrección.

## El problema y para quién

La orientación todavía está abierta: puede apuntar al emprendedor, quizás a agencias, o a
gente que usa mucho las redes para contenido orgánico y/o ads.

La referencia de hoy es el emprendedor hispano (Argentina primero), que dice:

- "No sé qué subir"
- "No me sale lindo"
- "Grabar me da paja"
- "Arranco el lunes y el jueves ya abandoné"
- "Una agencia no la puedo pagar"

Las apps del mercado hacen casi todas lo mismo: pegás tu web y te devuelven posteos genéricos.
Generar ya no vale nada; lo que falta es resultado terminado, con método, en español y con
calidad visual.

## La esencia

Los principios que definen el producto, más allá de las features puntuales:

1. **El cerebro: saber mucho del negocio.** El corazón no es generar: es conocer a fondo el
   negocio del cliente. Se llena con fuentes reales y de mínima fricción (Instagram, ficha de
   Google, un audio, chats de WhatsApp, fotos) y con análisis de fuentes externas: redes,
   posts, publicidad en redes, Google Maps y reseñas, web, etc. (mucho uso de Apify). Nunca se
   olvida: cada corrección queda para siempre. Ese conocimiento acumulado es el foso: el
   cliente se queda porque lo que aprendió su cerebro no se reemplaza con otra app.
2. **Saber mucho de la competencia.** El sistema investiga quién hace qué en la zona del
   cliente y qué le funciona, y eso alimenta ideas, formatos y decisiones de contenido.
3. **El método.** Lo propio del sistema, igual para todos: enfoques, formatos con nombre,
   hooks elegidos, prompts probados. Cada pieza tiene un porqué; es lo que separa el resultado
   de la IA genérica.
4. **Generación visual: "hacé el contenido que querés y no el que podés".** El sistema ayuda a
   generar contenidos visuales, intentando lo máximo posible que sean de buena calidad (donde
   el mercado hoy falla) y que queden como el usuario realmente quiere.
5. **Siempre elige el cliente.** El sistema propone versiones; el cliente elige y corrige, por
   clicks o charlando. Lo suyo gana siempre.
6. **La rueda.** Le das un dato → sale mejor → te dan ganas de darle más. El entrenamiento del
   cerebro se hace visible y motivador.
7. **Con su voz, real y local.** El contenido representa al negocio real: sus reseñas, sus
   frases, su manera de comunicarse. Castellano con voz local.

## Confirmado para el MVP

- Generación de contenido visual: imágenes en el MVP; videos más adelante.
- La lógica "hacé el contenido (visual) que querés y no el que podés", apuntando a buena
  calidad y a que quede como el usuario realmente quiere.
- Orientación fuerte a que el sistema sepa mucho del negocio del cliente y también mucho de su
  competencia.
- Análisis de fuentes externas: redes, posts, publicidad en redes, Google Maps y reseñas, web,
  etc., con mucho uso de Apify.

## Tentativo y para más adelante

- "El contenido del mes" como empaquetado de la entrega es un concepto tentativo, no es core.
- Sin generación de videos al arrancar; todo lo relacionado con grabación queda para más
  adelante.
- Programación automática de publicaciones: más adelante.
- Tomas de enganche (stock de aperturas virales para videos): idea para más adelante.
- En general, muchas ideas de los documentos fuente van a cambiar o no se van a hacer.

## El mercado en una pasada

Síntesis de la investigación de competencia (septiembre 2026, ~60 productos revisados):

- Nadie entrega contenido terminado, con método y en español: ese es el hueco.
- La calidad visual del mercado falla: contenido genérico, imágenes deformes, avatares que
  generan rechazo.
- La queja número uno de la categoría es el cobro: créditos opacos y bajas imposibles.
- Kalend (Mendoza) es la vara local: habla con voseo y cobra en pesos, pero solo entrega
  texto, no diseña.

## Platform

web

## Brand Commitments

- Existe un logo, provisorio (puede no ser el definitivo); se arranca con ese. La biblioteca
  de variantes vive en `custom/assets/logos/` (logo, wordmark y lockup, cada uno en PNG y SVG,
  transparente y sobre fondo blanco/negro); al frontend solo se copian las que se usan.
- La paleta de colores no está definida: se va a proponer a partir del logo.
- La aplicación debe tener light mode y dark mode.
- Los textos de la aplicación, en principio, en español neutro (ver AGENTS.md).

## Accessibility & Inclusion

Sin requisito definido por ahora (confirmado 19/09/2026).
