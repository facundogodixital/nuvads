<template>
  <SystemLayout>
    <div class="max-w-[1296px] space-y-8">
      <h1 class="text-3xl font-medium tracking-tight">
        ¿De qué quieres hablar?
      </h1>

      <p
        v-if="isLoading"
        role="status"
        class="text-sm text-text-muted"
      >
        Cargando los tipos de contenido…
      </p>
      <div
        v-if="loadError"
        role="alert"
        class="flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
      >
        <span>{{ loadError }}</span>
        <button
          type="button"
          class="min-h-11 cursor-pointer underline underline-offset-4"
          @click="loadContentTypes"
        >
          Volver a intentar
        </button>
      </div>

      <template v-if="hasLoaded">
        <p
          v-if="!contentTypes.length"
          class="rounded-sm border border-dashed border-border p-8 text-center text-sm leading-6 text-text-muted"
        >
          Todavía no hay tipos de contenido para elegir.
        </p>
        <ul
          v-else
          class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
          <li
            v-for="contentType in contentTypes"
            :key="contentType.id"
          >
            <!-- La tarjeta es el botón para elegir el tipo; lo que pasa al elegirlo llega con el paso 2. -->
            <button
              type="button"
              class="flex h-full w-full cursor-pointer flex-col gap-1 rounded-sm border border-border bg-surface-raised
                p-5 text-left transition hover:bg-surface-selected"
            >
              <span class="text-lg font-medium">{{ contentType.name }}</span>
              <span class="text-sm text-text-muted">{{ contentType.description }}</span>
            </button>
          </li>
        </ul>
      </template>
    </div>
  </SystemLayout>
</template>


<script setup>
import { ref, onMounted } from 'vue';
import SystemLayout from '@/layouts/SystemLayout.vue';
import ContentTypeService from '@/services/ContentTypeService';

const loadError = ref('');
const isLoading = ref(true);
const hasLoaded = ref(false);
const contentTypes = ref([]);

onMounted(loadContentTypes);

async function loadContentTypes() {
  loadError.value = '';
  isLoading.value = true;

  try {
    contentTypes.value = await ContentTypeService.list();
    hasLoaded.value = true;
  } catch (error) {
    loadError.value = error.message;
  } finally {
    isLoading.value = false;
  }
}
</script>
