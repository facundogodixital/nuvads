<template>
  <SystemLayout>
    <div class="max-w-[1296px] space-y-8">
      <!-- El paso 3 de cada idea queda guardado por su ID mientras no se sale de Crear: al volver a entrar a una idea
           se ve lo que tenía, sin pedir de nuevo su pieza sugerida. -->
      <KeepAlive>
        <SuggestedPieceStep
          v-if="selectedIdea"
          :key="selectedIdea.id"
          :idea="selectedIdea"
          :content-type-name="getContentTypeName(selectedIdea)"
          @back="selectedIdea = null"
        />
      </KeepAlive>

      <SuggestedIdeasStep
        v-if="selectedContentType"
        :content-type="selectedContentType"
        :suggested-ideas="selectedIdeaGeneration.suggestedIdeas"
        :is-generating="selectedIdeaGeneration.isGenerating"
        :generation-error="selectedIdeaGeneration.error"
        @generate="generateSuggestedIdeas(selectedContentType)"
        @saved="continueWithSavedIdea"
        @back="selectedContentType = null"
      />

      <template v-if="isContentTypesViewVisible">
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
            @click="loadContentTypesAndSavedIdeas"
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

          <section
            v-if="savedIdeas.length"
            aria-labelledby="saved-ideas-heading"
          >
            <h2
              id="saved-ideas-heading"
              class="spec-label mb-3"
            >
              Tus ideas guardadas
            </h2>
            <ul class="divide-y divide-border rounded-sm border border-border bg-surface-raised">
              <li
                v-for="savedIdea in savedIdeas"
                :key="savedIdea.id"
              >
                <!-- Tocar una idea guardada abre su paso 3. -->
                <button
                  type="button"
                  class="flex w-full cursor-pointer flex-col px-4 py-3 text-left hover:bg-surface-selected"
                  @click="selectedIdea = savedIdea"
                >
                  <span class="font-medium">{{ savedIdea.title }}</span>
                  <span class="text-xs text-text-muted">{{ getSavedIdeaLine(savedIdea) }}</span>
                </button>
              </li>
            </ul>
          </section>
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
import SuggestedPieceStep from './SuggestedPieceStep.vue';
import ContentTypeService from '@/services/ContentTypeService';

const loadError = ref('');
// Las ideas guardadas de la marca, de la más nueva a la más vieja.
const savedIdeas = ref([]);
const isLoading = ref(true);
const hasLoaded = ref(false);
const contentTypes = ref([]);
// La página muestra una vista a la vez: el paso 3 de la idea elegida, las ideas sugeridas del tipo elegido o, si
// no hay ninguno elegido, los tipos y las ideas guardadas.
const selectedIdea = ref(null);
const selectedContentType = ref(null);
// La generación de ideas de cada tipo, por su ID: ideas sugeridas, pedido en curso y error. Vive en la memoria
// de la página para no volver a pagar lo que ya se pidió; al salir de Crear se pierde.
const ideaGenerationsByContentTypeId = ref({});

const selectedIdeaGeneration = computed(() => ideaGenerationsByContentTypeId.value[selectedContentType.value.id]);
const hasAvailableContentType = computed(() => contentTypes.value.some((contentType) => contentType.is_available));
const isContentTypesViewVisible = computed(() => {
  const hasSelectedIdea = selectedIdea.value !== null;
  const hasSelectedContentType = selectedContentType.value !== null;
  return !hasSelectedIdea && !hasSelectedContentType;
});

onMounted(loadContentTypesAndSavedIdeas);

// Los tipos y las ideas guardadas se piden juntos: si falla uno, se muestra el error y el reintento pide los dos.
async function loadContentTypesAndSavedIdeas() {
  loadError.value = '';
  isLoading.value = true;

  try {
    const savedIdeasRequest = IdeaService.list({ status: 'chosen' });
    const contentTypesRequest = ContentTypeService.list();
    const [loadedContentTypes, loadedSavedIdeas] = await Promise.all([contentTypesRequest, savedIdeasRequest]);
    savedIdeas.value = loadedSavedIdeas;
    contentTypes.value = loadedContentTypes;
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

// La idea sugerida que se guardó sale de la lista de su tipo, para que no se pueda guardar dos veces. La idea
// guardada se suma primera a las ideas guardadas y se pasa directo a su paso 3.
function continueWithSavedIdea(savedSuggestedIdea, savedIdea) {
  const ideaGeneration = ideaGenerationsByContentTypeId.value[savedSuggestedIdea.content_type_id];
  const unsavedSuggestedIdeas = ideaGeneration.suggestedIdeas
    .filter((suggestedIdea) => suggestedIdea !== savedSuggestedIdea);
  ideaGeneration.suggestedIdeas = unsavedSuggestedIdeas;

  savedIdeas.value.unshift(savedIdea);

  selectedIdea.value = savedIdea;
  selectedContentType.value = null;
}

// El nombre del tipo de una idea, cuando su tipo está entre los que se listan; si no, vacío.
function getContentTypeName(idea) {
  const contentType = contentTypes.value.find((listedContentType) => listedContentType.id === idea.content_type_id);
  const isListedContentType = contentType !== undefined;
  return isListedContentType ? contentType.name : '';
}

// El renglón chico de una idea guardada: el nombre de su tipo, si se conoce, y la fecha en que se guardó.
function getSavedIdeaLine(savedIdea) {
  const savedDate = formatDate(savedIdea.created_at);
  const contentTypeName = getContentTypeName(savedIdea);
  const hasContentTypeName = contentTypeName !== '';
  return hasContentTypeName ? `${contentTypeName} · ${savedDate}` : savedDate;
}

function formatDate(isoDate) {
  const date = new Date(isoDate);
  return date.toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
