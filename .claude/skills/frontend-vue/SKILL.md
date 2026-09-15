---
name: frontend-vue
description: "Convenciones de Nuvede para el frontend: manejo de modales con stores de Pinia, estructura de los componentes Vue (Composition API, script setup) y llamadas a la API mediante APICall y los services JS. Cargar siempre antes de crear o modificar componentes .vue, stores de Pinia o services del frontend."
---

# Frontend Vue de Nuvede

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
  background: #eef;
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
