<template>
  <div class="space-y-6">
    <button
      type="button"
      :disabled="isSavingIdea"
      class="inline-flex min-h-11 items-center text-sm text-text-muted enabled:cursor-pointer enabled:hover:text-text
        disabled:cursor-not-allowed"
      @click="emit('back')"
    >
      ← Volver a los tipos
    </button>

    <h1 class="text-3xl font-medium tracking-tight">
      {{ contentType.name }}
    </h1>

    <section
      v-if="savedIdea"
      class="space-y-4 rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
      aria-labelledby="saved-idea-heading"
    >
      <p
        role="status"
        class="text-sm text-text-muted"
      >
        Tu idea quedó guardada. El paso siguiente todavía no está disponible.
      </p>
      <h2
        id="saved-idea-heading"
        class="text-lg font-medium"
      >
        {{ savedIdea.title }}
      </h2>
      <IdeaReviewList :reviews="savedIdea.reviews" />
    </section>

    <p
      v-else-if="isGenerating"
      role="status"
      class="rounded-sm border border-dashed border-border p-8 text-center text-sm leading-6 text-text-muted"
    >
      Buscando ideas con lo que sabemos de tu marca… Puede tardar un rato, a veces hasta dos minutos.
    </p>

    <template v-else>
      <!-- Si falla un pedido, el error va arriba de las ideas sugeridas que ya había, que se conservan. -->
      <div
        v-if="generationError"
        role="alert"
        class="flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
      >
        <span>{{ generationError }}</span>
        <button
          type="button"
          class="min-h-11 cursor-pointer underline underline-offset-4"
          @click="requestOtherSuggestedIdeas"
        >
          Volver a intentar
        </button>
      </div>

      <div
        v-if="isNoSuggestedIdeasMessageVisible"
        class="rounded-sm border border-dashed border-border p-8 text-center text-sm leading-6 text-text-muted"
      >
        <p>Con el material actual de tu marca no salieron ideas.</p>
        <button
          type="button"
          class="mt-4 inline-flex min-h-11 cursor-pointer items-center rounded-sm border border-border px-4 text-sm
            text-text hover:bg-surface-selected"
          @click="requestOtherSuggestedIdeas"
        >
          Buscar otras ideas
        </button>
      </div>

      <template v-if="suggestedIdeas.length">
        <fieldset class="min-w-0">
          <legend class="spec-label mb-3">
            Elige una idea
          </legend>
          <ul class="divide-y divide-border rounded-sm border border-border bg-surface-raised">
            <li
              v-for="(suggestedIdea, index) in suggestedIdeas"
              :key="index"
            >
              <!-- Elegir una idea despliega sus reseñas; hay una sola elegida a la vez. -->
              <label class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-surface-selected">
                <input
                  v-model="chosenSuggestedIdea"
                  type="radio"
                  name="suggested-idea"
                  :value="suggestedIdea"
                  class="mt-1.5 shrink-0"
                >
                <span class="min-w-0">
                  <span class="block font-medium">{{ suggestedIdea.title }}</span>
                  <span class="block text-xs text-text-muted">{{ getSourceLine(suggestedIdea) }}</span>
                </span>
              </label>
              <IdeaReviewList
                v-if="suggestedIdea === chosenSuggestedIdea"
                :reviews="suggestedIdea.reviews"
                class="mx-4 mb-3 border-l border-border pl-4"
              />
            </li>
          </ul>
        </fieldset>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            :disabled="!canSaveChosenSuggestedIdea"
            class="inline-flex min-h-11 items-center rounded-sm bg-accent px-5 text-sm font-medium text-text-on-accent
              enabled:cursor-pointer enabled:hover:bg-accent-hover disabled:cursor-not-allowed
              disabled:bg-surface-selected disabled:text-text-muted"
            @click="saveChosenSuggestedIdea"
          >
            {{ isSavingIdea ? 'Guardando…' : 'Seguir con la idea elegida' }}
          </button>
          <button
            type="button"
            :disabled="isSavingIdea"
            class="inline-flex min-h-11 items-center rounded-sm border border-border px-4 text-sm
              enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed
              disabled:text-text-muted"
            @click="requestOtherSuggestedIdeas"
          >
            Buscar otras ideas
          </button>
        </div>
        <p
          v-if="saveIdeaError"
          role="alert"
          class="text-sm text-danger"
        >
          {{ saveIdeaError }}
        </p>
      </template>
    </template>
  </div>
</template>


<script setup>
import { ref, computed } from 'vue';
import IdeaService from '@/services/IdeaService';
import IdeaReviewList from './IdeaReviewList.vue';

// Las ideas sugeridas y el estado de su pedido llegan de la página, que los conserva al volver a los tipos.
const props = defineProps({
  contentType: { type: Object, required: true },
  isGenerating: { type: Boolean, required: true },
  suggestedIdeas: { type: Array, required: true },
  generationError: { type: String, required: true },
});

// saved lleva la idea sugerida que se guardó, para que la página la saque de la lista.
const emit = defineEmits(['generate', 'saved', 'back']);

// La idea guardada, con las reseñas de la idea sugerida; null hasta que el usuario sigue con una.
const savedIdea = ref(null);
const saveIdeaError = ref('');
const isSavingIdea = ref(false);
const chosenSuggestedIdea = ref(null);

// El pedido salió bien pero sin ideas; si falló, alcanza con el error.
const isNoSuggestedIdeasMessageVisible = computed(() => {
  const hasGenerationError = props.generationError !== '';
  const hasSuggestedIdeas = props.suggestedIdeas.length > 0;
  return !hasSuggestedIdeas && !hasGenerationError;
});
const canSaveChosenSuggestedIdea = computed(() => {
  const hasChosenSuggestedIdea = chosenSuggestedIdea.value !== null;
  return hasChosenSuggestedIdea && !isSavingIdea.value;
});

// Hoy las ideas salen de reseñas de Google: el renglón dice cuántas usa.
function getSourceLine(suggestedIdea) {
  const reviewsCount = suggestedIdea.reviews.length;
  const isSingleReview = reviewsCount === 1;
  const reviewsLabel = isSingleReview ? 'reseña' : 'reseñas';
  return `Sale de Google · ${reviewsCount} ${reviewsLabel}`;
}

// La página pide otra tanda, que reemplaza la lista si llega bien; la elección actual se descarta igual.
function requestOtherSuggestedIdeas() {
  saveIdeaError.value = '';
  chosenSuggestedIdea.value = null;
  emit('generate');
}

async function saveChosenSuggestedIdea() {
  // Se toma la idea sugerida antes del pedido, por si el usuario cambia la elección mientras se guarda.
  const suggestedIdea = chosenSuggestedIdea.value;

  saveIdeaError.value = '';
  isSavingIdea.value = true;

  try {
    const idea = await IdeaService.create({
      title: suggestedIdea.title,
      angle: suggestedIdea.angle,
      content_type_id: suggestedIdea.content_type_id,
      knowledge_source_ids: suggestedIdea.knowledge_source_ids,
      knowledge_insight_ids: suggestedIdea.knowledge_insight_ids,
    });
    // La idea guardada no trae sus reseñas: son las de la idea sugerida, en el orden de knowledge_source_ids.
    savedIdea.value = { ...idea, reviews: suggestedIdea.reviews };
    emit('saved', suggestedIdea);
  } catch (error) {
    // Cuando la API rechaza la idea, el motivo llega como error de un campo; sin campo, vale el message.
    const fieldErrors = Object.values(error.errors ?? {});
    const firstFieldError = fieldErrors[0]?.[0];
    saveIdeaError.value = firstFieldError ?? error.message;
  } finally {
    isSavingIdea.value = false;
  }
}
</script>
