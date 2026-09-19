---
name: Nuvads
description: El manual de marca vivo — página specimen editorial, tinta sobre papel, un solo degradé.
colors:
  text: "#10151d"
  text-muted: "#5b6472"
  text-on-accent: "#ffffff"
  surface: "#f7f8fa"
  surface-raised: "#ffffff"
  surface-selected: "#e8edf4"
  border: "#d7dde6"
  scrim: "rgb(16 21 29 / 0.45)"
  accent: "#1e5fe0"
  accent-hover: "#1a4fc0"
  accent-soft: "#e9effc"
  danger: "#c73a2e"
  brand-cyan: "#25dcea"
  brand-mid: "#2abeff"
  brand-blue: "#2576ff"
typography:
  body:
    fontFamily: "Archivo, Helvetica Neue, Helvetica, Arial, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Archivo, Helvetica Neue, Helvetica, Arial, system-ui, sans-serif"
    fontSize: "0.6875rem"
    fontWeight: 500
    lineHeight: 1.5
    letterSpacing: "0.14em"
rounded:
  sm: "8px"
spacing:
  xs: "6px"
  sm: "12px"
  md: "20px"
  lg: "24px"
  xl: "32px"
  module: "72px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.text-on-accent}"
    rounded: "{rounded.sm}"
    padding: "12px 24px"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
  button-disabled:
    backgroundColor: "{colors.surface-selected}"
    textColor: "{colors.text-muted}"
    rounded: "{rounded.sm}"
    padding: "10px 24px"
  input:
    backgroundColor: "{colors.surface-raised}"
    textColor: "{colors.text}"
    rounded: "{rounded.sm}"
    padding: "10px 14px"
---

# Design System: Nuvads

## Overview

**Creative North Star: "El Manual de Marca"**

Cada pantalla de Nuvads es una página del manual de marca vivo del producto: una página
specimen editorial que demuestra calidad visual antes de pedir nada. El mundo es papel claro,
hairlines de 1px como única estructura, tinta casi negra y un azul de acción derivado del
logo. El degradé de marca (#25DCEA → #2ABEFF → #2576FF) es un material escaso: vive solo en
el lockup y en una regla fina. Rechazo confirmado: la tarjeta SaaS centrada flotando sobre un
fondo gris.

El sistema es plano y semántico. Los colores se nombran por su rol en el manual — tinta,
papel, hairline — y el modo oscuro es el manual en negativo: misma semántica, tinta clara
sobre papel oscuro (`:root[data-theme='dark']` en `resources/css/variables.css`). El tema
elegido por el usuario persiste en localStorage y manda en toda la app; sin elección guardada
rige la preferencia del sistema, salvo el login, que arranca en dark por decisión de producto.

**Key Characteristics:**
- Hairlines de 1px como único mecanismo de estructura y separación; cero sombras.
- Etiquetas specimen: versalitas espaciadas (0.14em) en Archivo medium, color tinta atenuada.
- Un solo degradé de marca, reservado a lockup y regla.
- Tokens semánticos tinta/papel con negativo exacto en dark.
- Módulo de 72px como convención invisible de anchos estructurales (columna 432, bloque 864, sidebar 216/72).

## Colors

Paleta de manual de imprenta: tinta sobre papel, un azul de acción con contraste AA, y los
tres stops del degradé del logo como constantes de marca.

### Primary
- **Azul de Acción** (`--accent`, #1e5fe0): el único color interactivo; botón primario,
  focus ring, caret de inputs, `::selection` y hover de links. Derivado del azul del logo
  pero oscurecido para dar contraste AA con texto blanco encima.
- **Azul de Acción Presionado** (`--accent-hover`, #1a4fc0): estado hover del botón primario.
- **Azul Suave** (`--accent-soft`, #e9effc): fondo tenue de apoyo del acento; en el shell es
  el fondo del ítem de navegación activo.

### Neutral
- **Tinta** (`--text`, #10151d): texto principal; casi negra, nunca #000 puro.
- **Tinta Atenuada** (`--text-muted`, #5b6472): notas al pie, ayudas y etiquetas specimen.
- **Papel** (`--surface`, #f7f8fa): fondo de página.
- **Papel Elevado** (`--surface-raised`, #ffffff): fondo de inputs y superficies destacadas.
- **Papel Seleccionado** (`--surface-selected`, #e8edf4): estados seleccionados, hover de navegación y botones deshabilitados.
- **Hairline** (`--border`, #d7dde6): todas las líneas de 1px del manual; también el scrollbar (`scrollbar-color`).
- **Velo de Tinta** (`--scrim`, rgb(16 21 29 / 0.45) en light, rgb(2 5 10 / 0.6) en dark):
  exclusivamente detrás de paneles superpuestos (el drawer mobile). No es una sombra ni un
  fondo de estado: es la página apoyada sobre la anterior.

### Tertiary
- **Rojo de Error** (`--danger`, #c73a2e): mensajes de error, texto y borde.
- **Degradé de Marca** (`--brand-cyan` #25dcea → `--brand-mid` #2abeff → `--brand-blue` #2576ff): constantes idénticas en ambos temas; solo en el lockup y la regla de marca.

### Named Rules
**La Regla del Único Degradé.** El degradé del logo aparece únicamente en el lockup y en la
regla de marca de 3px. Nunca en botones, fondos ni texto.

**La Regla del Negativo.** El modo oscuro no introduce colores nuevos: cada token semántico
tiene su valor en negativo bajo `[data-theme='dark']`; las constantes de marca no cambian.

**La Regla del Acento Único.** El acento va en ≤ un elemento primario por vista, contado en
el área de contenido. Excepciones documentadas en el chrome persistente: el avatar del header
(círculo relleno de acento) y el estado activo de navegación en azul suave.

## Typography

**Body Font:** Archivo (variable 100–900, auto-hospedada en `resources/fonts/archivo-latin.woff2`; fallback Helvetica Neue, Helvetica, Arial, system-ui)
**Label Font:** la misma Archivo, en versalitas espaciadas

**Character:** una sola grotesca variable para todo el manual; la jerarquía se construye con
peso, tamaño y tracking, no con familias. Auto-hospedada para que el manual se vea igual en
todos los sistemas operativos.

### Hierarchy
- **Body** (400, 1rem, 1.5): texto corriente y la línea de bienvenida.
- **Body Small** (400, 0.875rem, 1.5): notas al pie, mensajes de error, link de acceso alternativo, ítems de navegación.
- **Caption** (400, 0.75rem, 1.5): ayudas de campos en tinta atenuada.
- **Label / Spec** (500, 0.6875rem, tracking 0.14em, MAYÚSCULAS): la etiqueta specimen `.spec-label`, ahora global en `resources/css/app.css`; folios de cabezal y pie, labels de formularios, hex de las muestras, chip de créditos. Siempre en tinta atenuada.

### Named Rules
**La Regla de la Etiqueta Specimen.** Todo rótulo del sistema (folios, labels de campos,
valores hex) usa la etiqueta specimen: 0.6875rem, peso 500, mayúsculas, tracking 0.14em,
tinta atenuada. No se inventan otros estilos de rótulo.

Nota de divergencia: el contrato de dirección pedía labels specimen en mono; el build las
resolvió en la misma Archivo. El build manda: la etiqueta specimen es Archivo, no mono.

## Layout

El espacio se registra a un módulo de 72px como convención invisible: la columna de contenido
del login mide 6 módulos exactos (432px, `max-w-[432px]`), los folios se alinean al bloque de
12 módulos (864px, `max-w-[864px]`), y el shell autenticado registra la sidebar al mismo
módulo: expandida 216px (3 módulos), rail de íconos 72px (1 módulo).

El shell autenticado es una columna de altura completa (`h-dvh`): regla de marca de 3px
arriba, header hairline de 56px, y debajo una fila con la sidebar hairline a la izquierda y
el papel de contenido desplazable a la derecha (padding 24–32px). El login conserva su
columna vertical centrada (`min-h-dvh`). Ritmo interno con la escala de Tailwind
(6, 12, 20, 24, 32px; secciones a 40–56px).

**La Regla del Módulo.** Anchos estructurales en múltiplos de 72px: columna 432px (6),
bloque 864px (12), sidebar 216px (3) y rail 72px (1).

## Elevation & Depth

Sistema completamente plano: no existe ninguna sombra en el build. La profundidad se expresa
con capas tonales de papel (`surface` → `surface-raised` → `surface-selected`) y con
hairlines de 1px. Verificado bajo presión en el shell: el menú del usuario, el drawer mobile
y el rail se resuelven con hairline y papel tonal, sin una sola sombra. Los paneles
superpuestos (drawer) no proyectan sombra: apoyan sobre el velo de tinta (`--scrim`).

**La Regla del Hairline.** La separación es siempre una línea de 1px en `--border`; nunca
una sombra, nunca un borde grueso.

## Shapes

Geometría de imprenta suavizada: rectángulos con esquinas redondeadas de 8px (`rounded-sm`,
`--radius-sm: 8px`, decisión durable del 19/09/2026) en botones, inputs, ítems de navegación,
menús y avisos. Existen exactamente dos formas redondas sancionadas: el avatar del usuario
(círculo completo de 32px, relleno de acento con la inicial) y el chip de créditos (píldora
con borde hairline y etiqueta specimen). Fuera de esas dos, ningún radio mayor a 8px y ningún
círculo decorativo. Las líneas — la regla de marca de 3px con degradé y los hairlines de
1px — siguen siendo la forma recurrente del mundo.

## Components

### Buttons
- **Shape:** esquinas redondeadas (8px).
- **Primary:** azul de acción sobre texto blanco (`{colors.accent}` / `{colors.text-on-accent}`), padding 12px 24px, peso 500, ancho de columna cuando es la acción principal; ícono inline de 20px en SVG blanco con opacidades escalonadas.
- **Hover / Focus:** hover a `{colors.accent-hover}` con `transition`; active baja 1px (`translate-y-px`); focus con outline de 2px en acento y offset 2px (regla global).
- **Disabled:** papel seleccionado con tinta atenuada, `cursor-not-allowed` (estado del acceso con usuario mientras el backend está pendiente).
- **Text-link button:** texto en tinta con subrayado offset 4px; hover a acento; chevron SVG de 14px que rota 180° al abrir.

### Chips
- **Chip de Créditos:** píldora (`rounded-full`) con borde hairline sobre el papel del header,
  etiqueta specimen, padding 14px 6px. Una de las dos formas redondas sancionadas del sistema.
  Hoy es informativo, sin acción al click.

### Cards / Containers
- **Corner Style:** 8px cuando el contenedor lleva borde (aviso de error, menú desplegable).
- **Background:** el papel de la página; los paneles flotantes usan papel elevado.
- **Shadow Strategy:** ninguna (ver Elevation & Depth).
- **Border:** hairline 1px; el aviso de error usa borde y texto en `--danger`.
- **Internal Padding:** 12–16px (menús 6px con ítems de 10px).

### Inputs / Fields
- **Style:** hairline 1px sobre papel elevado, 8px de radio, padding 10px 14px; label specimen arriba, ayuda en caption de tinta atenuada abajo.
- **Focus:** outline global de 2px en acento con offset 2px; caret en acento.
- **Error:** el mensaje va en un aviso con borde y texto `--danger` (no hay estado de borde por campo todavía).

### Navigation
- **Shell autenticado** (`resources/js/layouts/SystemLayout.vue`): regla de marca de 3px, y
  header hairline de 56px con el lockup a la izquierda (logo solo en mobile) y, a la derecha,
  chip de créditos + switch de tema + avatar con menú.
- **Sidebar izquierda** registrada al módulo de 72px: expandida 216px (3 módulos), colapsable
  a rail de íconos de 72px (1 módulo) con botón al pie (doble chevron que rota); el estado
  colapsado persiste en localStorage (`sidebar_collapsed`).
- **Ítems:** texto small con ícono SVG de trazo 1.5 (20px), radio 8px; activo en azul suave
  con texto en acento y peso 500; hover en papel seleccionado; en el rail, ícono centrado con
  `title` como rótulo.
- **Mobile:** la sidebar se vuelve drawer de 216px sobre el contenido, detrás un velo
  `--scrim`; entra deslizando con el ease exponencial del manual
  (`cubic-bezier(0.16, 1, 0.3, 1)`, 0.35s), gestiona el foco (entra al botón de cerrar,
  vuelve al de abrir), cierra con Escape, click en el velo o al navegar, y bloquea el scroll
  del fondo.
- **Folios del login:** cabezal y pie como folios de manual, hairline de 1px y etiquetas
  specimen en los extremos del bloque de 864px.

### Avatar de Usuario
Círculo de 32px relleno de acento con la inicial del usuario en blanco (peso 500); hover a
`--accent-hover`. La otra forma redonda sancionada. Abre el menú de cuenta: panel de 208px en
papel elevado con hairline, radio 8px, que aparece como una nota que se asienta (opacidad +
caída de 4px, 0.18s ease-out). Ítem "Cerrar sesión" con ícono de trazo 1.5.

### Switch de Tema
Componente compartido (`resources/js/components/ThemeToggle.vue`): botón de 32×32px, hairline
1px y radio 8px (sigue el sistema), con ícono SVG de trazo 1.5 (sol en dark, luna en light)
en tinta atenuada; hover a acento. Alterna `data-theme` en `<html>` y persiste la elección en
localStorage (`theme`): la elección guardada manda en toda la app; sin elección, rige la
preferencia del sistema, y el login arranca en dark.

### Despliegue "Página que se Abre" (signature)
El acceso con usuario se despliega como nota al pie que se abre: `grid-template-rows`
animada de `0fr` a `1fr` con `cubic-bezier(0.16, 1, 0.3, 1)` en 0.45s y opacidad en 0.3s
ease-out. Al abrir, el foco pasa al primer campo. El drawer mobile reutiliza el mismo ease.

## Do's and Don'ts

### Do:
- **Do** registrar anchos estructurales al módulo de 72px (columna 432, bloque 864, sidebar 216, rail 72).
- **Do** usar la etiqueta specimen (0.6875rem, 500, mayúsculas, tracking 0.14em, tinta atenuada) para todo rótulo.
- **Do** consumir siempre los tokens semánticos (`--text`, `--surface`, `--border`…); el modo oscuro sale gratis.
- **Do** mantener el acento en ≤ un elemento primario por vista en el área de contenido; las únicas excepciones son el avatar del header y el estado activo de navegación en azul suave.
- **Do** apoyar los paneles superpuestos sobre el velo de tinta (`--scrim`), nunca sobre una sombra.

### Don't:
- **Don't** usar sombras: la profundidad es tonal y de hairlines.
- **Don't** usar el degradé de marca fuera del lockup y la regla de 3px.
- **Don't** introducir radios mayores a 8px ni círculos decorativos; las únicas formas redondas sancionadas son el avatar (círculo de 32px) y el chip de créditos (píldora hairline). Nada de tarjetas flotantes centradas sobre fondo gris.
- **Don't** hardcodear colores de tema; solo las constantes de marca (#25dcea, #2abeff, #2576ff) son literales legítimos.
