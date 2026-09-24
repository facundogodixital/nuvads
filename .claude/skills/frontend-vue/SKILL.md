---
name: frontend-vue
description: "Convenciones de Nuvads para el frontend: componentes Vue, layouts, estilos y temas con Tailwind CSS 4, modales con Pinia y llamadas a la API. Cargar antes de crear o modificar componentes .vue, hojas de estilo, configuración de Tailwind, stores de Pinia o services del frontend."
---

# Frontend Vue de Nuvads

Estas convenciones aplican junto con las reglas generales de AGENTS.md (legibilidad, idioma, indentación de 2 espacios en JS y .vue).

## 1. Manejo de modales

Decisión: todos los modales utilizan la misma convención: un store específico de Pinia, compartido por cada tipo de modal.

Store del modal:
- Mantiene la apertura, los datos del formulario, los indicadores de carga y los errores.
- Expone las acciones para abrir, cerrar, cargar y guardar.
- `open()` siempre reinicia el estado del store (formulario, errores, indicadores) antes de cargar, para que no se filtre el estado de una apertura anterior. La excepción es cuando `open()` recibe parámetros específicos destinados a mantener un estado.
- Cualquier componente que necesite utilizar el modal llama a las acciones de ese mismo store.

Modal que abre otro modal: cuando un modal abre otro modal, el de atrás queda abierto.

Componente del modal: muestra los datos del store y llama a sus acciones para responder a las interacciones del usuario.

Montaje:
- Si el modal se necesita en toda la aplicación, se monta en la estructura principal.
- Si se necesita en determinadas páginas, se monta en esas páginas.
- En ambos casos utiliza el mismo store y la misma forma de apertura.
- Para cada tipo de modal, se mantiene una sola instancia montada a la vez.

Ejemplo de uso:

```js
const leadModalStore = useLeadModalStore();
leadModalStore.open(15);
```

## 2. Estructura de los componentes Vue

Decisión: los componentes se escriben con Composition API y `<script setup>`, en archivos .vue ordenados en template, script y style, con dos líneas en blanco entre bloque y bloque. No se usa Options API. Se utilizan nombres explícitos y se mantiene una estructura uniforme. El acceso a Pinia conserva la nomenclatura acordada con el sufijo Store.

Orden fijo dentro de `<script setup>`:
1. Imports.
2. defineProps y defineEmits.
3. Stores.
4. Estado local (ref, reactive).
5. computed.
6. watch.
7. Ciclo de vida (onMounted, etc.).
8. Funciones.

Props: se declaran siempre con type y required o default. Nunca la forma corta `defineProps(['lead'])`.

Stores: el store se usa entero: `leadModalStore.open()`, `leadModalStore.isOpen`. Si hace falta desestructurarlo, se hace con `storeToRefs()`, porque desestructurar directo (`const { isOpen } = leadModalStore`) pierde la reactividad. Cada uso de `storeToRefs()` lleva un comentario corto que explique por qué está ahí.

Ejemplo:

```vue
<template>
  <tr :class="{ 'is-selected': selected }">
    <td>{{ lead.name }}</td>
    <td>{{ fullPhone }}</td>
    <td>
      <button @click="edit">Editar</button>
      <button @click="select">Seleccionar ({{ clicks }})</button>
    </td>
  </tr>
</template>


<script setup>
import { ref, computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useLeadModalStore } from '@/stores/leadModalStore';

const props = defineProps({
  lead: { type: Object, required: true },
  selected: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const leadModalStore = useLeadModalStore();
// storeToRefs mantiene la reactividad al desestructurar el store.
const { isOpen } = storeToRefs(leadModalStore);

const clicks = ref(0);

const fullPhone = computed(() => `${props.lead.phone_prefix} ${props.lead.phone}`);

onMounted(() => {
  console.log('LeadRow montado', props.lead.id);
});

function edit() {
  leadModalStore.open(props.lead.id);
}

function select() {
  clicks.value++;
  emit('select', props.lead.id);
}
</script>


<style scoped>
.is-selected {
  background: var(--surface-selected);
}
</style>
```

## 3. Llamadas a la API desde el frontend

Decisión: todas las llamadas HTTP a la API pasan por el helper APICall (`resources/js/helpers/APICall.js`). Ningún componente ni store usa axios o fetch directamente.

APICall lo usan únicamente los services del frontend (`resources/js/services/`), uno por dominio y con sufijo Service: `LeadService.js`, `TagService.js`.

Todo lo anterior es el comportamiento por defecto. Apartarse de él solo cuando convenga y con aprobación explícita del usuario.

Services del frontend:
- Son un objeto exportado con funciones, sin clase, sin `new` y sin `getInstance()`. Se usan como `TagService.find(15)`. Si alguno necesita estado propio, se convierte en clase en ese momento.
- Usan los mismos verbos que el backend: `create`, `update`, `delete`, `find`, `list`.
- Las rutas de la API van literales dentro de cada método. No se usa un archivo de constantes para las rutas.
- Devuelven lo que devuelve APICall: el contenido de `data`, ya interpretado.

Quién llama al service:
- Un componente lo llama directo cuando el resultado es solo suyo, por ejemplo las opciones de un select.
- Pasa por un store cuando el estado lo comparten varios componentes o tiene que sobrevivir a la navegación, como el modal de lead.

APICall (`resources/js/helpers/APICall.js`):
- Exporta tres funciones: APICall (JSON), APIUpload (archivo, multipart) y APIDownload (blob). Firmas:
  - `APICall(endpoint, method, params, opts)`
  - `APIUpload(endpoint, fileToUpload, params, opts)`
  - `APIDownload(endpoint, method, params, opts)`
- `params` va a la query string en GET y DELETE, y al cuerpo en POST, PUT y PATCH. Si un POST necesita query string, va literal en el endpoint.
- En error lanzan APIError (`resources/js/classes/APIError.js`) con status, code, message y errors. Nunca devuelven null ni false.
- `opts` no recibe claves nuevas sin aprobación del usuario.
- El detalle de comportamiento está en el archivo.

Ejemplo (`resources/js/services/TagService.js`):

```js
import { APICall, APIUpload, APIDownload } from '@/helpers/APICall';

export default {

  async find(tagId) {
    return APICall(`/api/tags/${tagId}`);
  },

  async list({ page = 1, filters = {} } = {}) {
    return APICall('/api/tags', 'get', { page, filters });
  },

  async create({ tag }) {
    return APICall('/api/tags', 'post', tag);
  },

  async uploadAttachment({ tagId, fileToUpload }) {
    return APIUpload(`/api/tags/${tagId}/attachment`, fileToUpload, {}, {
      fileFieldName: 'attachment',
    });
  },

  async export(tagId) {
    return APIDownload(`/api/tags/${tagId}/export`);
  },

};
```

## 4. Organización de layouts, páginas y componentes

- `resources/js/layouts/`: estructuras compartidas que reciben el contenido de las páginas.
- `resources/js/pages/`: cada página tiene un directorio con su mismo nombre y, dentro, el componente principal con ese nombre: `pages/DashboardPage/DashboardPage.vue`. Crear el directorio aunque la página todavía no tenga subcomponentes.
- Los componentes exclusivos de una página (tablas, filas, filtros, etc.) viven en ese mismo directorio, al mismo nivel que el componente principal.
- `resources/js/components/`: piezas compartidas entre páginas o usadas por los layouts. Agruparlas en subcarpetas cuando exista una necesidad concreta.

Ejemplo de ubicación cuando esos componentes sean necesarios:

```text
pages/DashboardPage/
  DashboardPage.vue
  DashboardTableBody.vue
  DashboardTableHeader.vue
  DashboardTableRow.vue
  UsersFilters.vue
```

Esta estructura define dónde ubicar los componentes; no obliga a descomponer una página ni a crear los componentes del ejemplo.

El layout organiza las áreas compartidas de la pantalla; cada componente organiza su interior. Las páginas se integran en el área de contenido del layout, sin repetir su estructura ni compensarla con márgenes propios.

Crear componentes cuando representen una pieza con sentido o eviten una repetición real. Mantener una organización sencilla, sin subdivisiones anticipadas.

Todo lo que define una página vive en su archivo .vue: estructura, textos y datos de configuración incluidos. Está totalmente prohibido sacarlos a un archivo aparte, como un `brandSections.js` con las secciones y campos de la página: no hay motivo más que sumar carga cognitiva.

## 5. Organización de estilos y temas

Decisión: usar Tailwind CSS 4 con la integración de Vite `@tailwindcss/vite`. La configuración del proyecto vive en CSS mediante `@theme`, sin `tailwind.config.js`.

### Hojas de estilo

- `resources/css/app.css`: entrada de estilos, importaciones y reglas globales del documento.
- `resources/css/variables.css`: valores compartidos, configuración de Tailwind y definiciones de light/dark.
- `<style scoped>` de cada componente: CSS particular de esa pieza.

En `app.css`, importar Tailwind y luego las variables compartidas, antes de las reglas globales:

```css
@import 'tailwindcss';
@import './variables.css';
```

Centralizar los valores recurrentes de espaciado, tamaños y puntos de corte. Usar las escalas compartidas de Tailwind y definir sus ajustes en `variables.css`, evitando valores arbitrarios repetidos por las pantallas.

### Dark y light mode

Ambos temas usan las mismas variables semánticas, con nombres que expresan su función: `--surface`, `--text`, `--border`, `--accent`, etc. Para un estado particular, usar una variable con significado, como `--surface-selected` en el ejemplo de componente.

En `variables.css`:

- `:root` define los valores de light y garantiza que existan incluso sin atributo de tema.
- `:root[data-theme='dark']` sobrescribe esos mismos valores para dark.
- `data-theme='light'` en `<html>` utiliza los valores de `:root`.
- `@theme inline` conecta las variables semánticas con las utilidades de Tailwind.

Ejemplo de conexión, una vez definida `--surface` en ambos temas:

```css
/* La utilidad consume la misma variable de tema que el CSS de los componentes. */
@theme inline {
  --color-surface: var(--surface);
}
```

El template usa `bg-surface` y el CSS local usa `var(--surface)`: ambos acompañan el cambio de tema. Aplicar el mismo patrón a las demás variables semánticas.

Los valores concretos de los colores se definen en `variables.css`. Los componentes consumen variables semánticas, sin colores literales ni utilidades de paleta fija como `bg-white` o `text-gray-900`. El cambio de tema se resuelve en las variables compartidas.

Referencia del mecanismo: [colores que referencian otras variables en Tailwind](https://tailwindcss.com/docs/colors#referencing-other-variables).

### Estilos locales y comentarios

- Las utilidades de Tailwind van en el template.
- En `<style scoped>`, usar CSS plano y las variables compartidas. No usar `@apply`.
- Agregar comentarios breves y orientativos en castellano cuando una decisión o un comportamiento no resulte evidente. Explicar el motivo, sin repetir lo que ya expresa el código.

## 6. Adaptación a tamaños de pantalla

- Mobile-first: los estilos base corresponden a pantallas pequeñas; los breakpoints agregan cambios hacia tamaños mayores.
- Usar puntos de corte compartidos. Los ajustes a los breakpoints de Tailwind se definen en `variables.css` mediante `@theme`.
- Resolver los cambios simples de distribución con los breakpoints (utilidades responsive o media queries), sin separar componentes.
- Cuando el diseño requiera estructuras sustancialmente distintas y se evalúe que conviene separar componentes por tamaño de pantalla, consultarlo con el usuario antes de hacerlo. Los componentes separados comparten su lógica y estado. Evitar árboles llenos de bloques alternativos ocultos y la duplicación de pantallas completas.

## 7. Storage del navegador

- Solo `helpers/authStorage.js` (login y token) y `helpers/preferencesStorage.js` (lo que el navegador recuerda, como el tema o la marca elegida) usan `localStorage` y `sessionStorage`. ESLint lo exige.
- Cada clave se declara como constante arriba de su helper: esas constantes son la lista de todo lo que se guarda.
