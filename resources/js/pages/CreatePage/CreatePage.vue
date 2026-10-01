<template>
  <SystemLayout>
    <div class="max-w-[1296px] space-y-8">
      <SuggestedIdeasStep
        v-if="selectedContentType"
        :content-type="selectedContentType"
        :suggested-ideas="selectedIdeaGeneration.suggestedIdeas"
        :is-generating="selectedIdeaGeneration.isGenerating"
        :generation-error="selectedIdeaGeneration.error"
        @generate="generateSuggestedIdeas(selectedContentType)"
        @saved="removeSavedSuggestedIdea"
        @back="selectedContentType = null"
      />

      <template v-else>
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
          <template v-else>
            <!-- Sin ningún tipo disponible, las tarjetas se ven igual, apagadas, y el aviso lleva a cargar fuentes. -->
            <p
              v-if="!hasAvailableContentType"
              class="rounded-sm border border-border bg-surface-raised p-4 text-sm leading-6"
            >
              Todavía no tienes material para crear contenido.
              <RouterLink
                to="/brand"
                class="underline underline-offset-4"
              >
                Suma tus fuentes en Mi marca
              </RouterLink>
            </p>
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <li
                v-for="contentType in contentTypes"
                :key="contentType.id"
              >
                <!-- La tarjeta es el botón para elegir el tipo. Un tipo no disponible se ve apagado y dice por qué. -->
                <button
                  type="button"
                  :disabled="!contentType.is_available"
                  class="group flex h-full w-full flex-col gap-1 rounded-sm border border-border bg-surface-raised p-5
                    text-left transition enabled:cursor-pointer enabled:hover:bg-surface-selected
                    disabled:cursor-not-allowed disabled:border-dashed disabled:bg-surface"
                  @click="openSuggestedIdeas(contentType)"
                >
                  <span class="text-lg font-medium group-disabled:text-text-muted">{{ contentType.name }}</span>
                  <span class="text-sm text-text-muted">{{ contentType.description }}</span>
                  <span
                    v-if="!contentType.is_available"
                    class="mt-2 text-sm"
                  >{{ contentType.unavailable_reason }}</span>
                </button>
              </li>
            </ul>
          </template>
        </template>
      </template>
    </div>
  </SystemLayout>
</template>


<script setup>
import { RouterLink } from 'vue-router';
import { ref, computed, onMounted } from 'vue';
import IdeaService from '@/services/IdeaService';
import SystemLayout from '@/layouts/SystemLayout.vue';
import SuggestedIdeasStep from './SuggestedIdeasStep.vue';
import ContentTypeService from '@/services/ContentTypeService';

const loadError = ref('');
const isLoading = ref(true);
const hasLoaded = ref(false);
const contentTypes = ref([]);
// El tipo que eligió el usuario; null mientras ve las tarjetas.
const selectedContentType = ref(null);
// La generación de ideas de cada tipo, por su ID: ideas sugeridas, pedido en curso y error. Vive en la memoria
// de la página para no volver a pagar lo que ya se pidió; al salir de Crear se pierde.
const ideaGenerationsByContentTypeId = ref({});

const selectedIdeaGeneration = computed(() => ideaGenerationsByContentTypeId.value[selectedContentType.value.id]);
const hasAvailableContentType = computed(() => contentTypes.value.some((contentType) => contentType.is_available));

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

// La primera vez que se abre un tipo se piden sus ideas sugeridas; después se muestran las que ya tiene.
function openSuggestedIdeas(contentType) {
  const isFirstOpening = ideaGenerationsByContentTypeId.value[contentType.id] === undefined;
  if (isFirstOpening) {
    ideaGenerationsByContentTypeId.value[contentType.id] = { suggestedIdeas: [], isGenerating: false, error: '' };
    generateSuggestedIdeas(contentType);
  }

  selectedContentType.value = contentType;
}

// Pide ideas sugeridas nuevas del tipo y, si llegan, reemplazan a las que tenía; si el pedido falla, las anteriores
// se conservan, porque ya se pagaron. El modelo puede tardar hasta dos minutos.
async function generateSuggestedIdeas(contentType) {
  const ideaGeneration = ideaGenerationsByContentTypeId.value[contentType.id];
  // Cada pedido cuesta plata: mientras hay uno en curso no se dispara otro.
  if (ideaGeneration.isGenerating) {
    return;
  }

  ideaGeneration.error = '';
  ideaGeneration.isGenerating = true;

  try {
    ideaGeneration.suggestedIdeas = await IdeaService.generateSuggestedIdeas(contentType.id);
  } catch (error) {
    ideaGeneration.error = error.message;
  } finally {
    ideaGeneration.isGenerating = false;
  }
}

// La idea sugerida que se guardó sale de la lista de su tipo, para que no se pueda guardar dos veces.
function removeSavedSuggestedIdea(savedSuggestedIdea) {
  const ideaGeneration = ideaGenerationsByContentTypeId.value[savedSuggestedIdea.content_type_id];
  const unsavedSuggestedIdeas = ideaGeneration.suggestedIdeas
    .filter((suggestedIdea) => suggestedIdea !== savedSuggestedIdea);
  ideaGeneration.suggestedIdeas = unsavedSuggestedIdeas;
}
</script>
