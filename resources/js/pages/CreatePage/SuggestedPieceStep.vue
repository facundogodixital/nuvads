<template>
  <div class="space-y-6">
    <button
      type="button"
      class="inline-flex min-h-11 cursor-pointer items-center text-sm text-text-muted hover:text-text"
      @click="emit('back')"
    >
      ← Volver
    </button>

    <header>
      <h1 class="text-3xl font-medium tracking-tight">
        {{ idea.title }}
      </h1>
      <p
        v-if="contentTypeName"
        class="mt-1 text-sm text-text-muted"
      >
        {{ contentTypeName }}
      </p>
    </header>

    <!-- Hasta que llega la primera pieza, en su lugar van la espera o el error del pedido. -->
    <template v-if="!hasSuggestedPiece">
      <p
        v-if="isGenerating"
        role="status"
        class="rounded-sm border border-dashed border-border p-8 text-center text-sm leading-6 text-text-muted"
      >
        Escribiendo tu pieza… Puede tardar un rato, a veces hasta dos minutos.
      </p>
      <div
        v-if="generationError"
        role="alert"
        class="flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
      >
        <span>{{ generationError }}</span>
        <button
          type="button"
          class="min-h-11 cursor-pointer underline underline-offset-4"
          @click="requestSuggestedPiece"
        >
          Volver a intentar
        </button>
      </div>
    </template>

    <template v-else>
      <div class="text-sm leading-6">
        <p v-if="hasCarousel">
          Escribimos los textos de tu pieza con las {{ pieceReviews.length }} reseñas de esta idea.
        </p>
        <p v-else>
          Escribimos los textos de tu pieza con la reseña de esta idea.
        </p>
        <p>Revísalos antes de generar la pieza.</p>
      </div>

      <!-- Mientras se escriben otras opciones no se puede elegir ni editar nada. -->
      <fieldset
        :disabled="isGenerating"
        class="min-w-0 space-y-6"
      >
        <fieldset
          v-if="hasCarousel"
          class="min-w-0"
        >
          <legend class="font-medium">
            Formato
          </legend>
          <label class="mt-2 flex cursor-pointer items-start gap-3 text-sm leading-6">
            <input
              v-model="format"
              type="radio"
              name="format"
              value="carousel"
              class="mt-1.5 shrink-0"
            >
            <span>Carrusel: {{ carouselImagesCount }} imágenes. Una por reseña y una final con una invitación.</span>
          </label>
          <label class="mt-1 flex cursor-pointer items-start gap-3 text-sm leading-6">
            <input
              v-model="format"
              type="radio"
              name="format"
              value="single"
              class="mt-1.5 shrink-0"
            >
            <span>Una sola imagen: lleva una sola reseña.</span>
          </label>
        </fieldset>
        <div v-else>
          <h2 class="font-medium">
            Formato
          </h2>
          <p class="mt-2 text-sm leading-6">
            Tu pieza va en una sola imagen, porque esta idea tiene una sola reseña.
          </p>
        </div>

        <!-- Carrusel: una imagen por cada reseña que queda y, al final, la invitación. -->
        <div
          v-if="isCarouselChosen"
          class="space-y-5 border-t border-border pt-6"
        >
          <div
            v-for="(review, reviewIndex) in carouselReviews"
            :key="review.id"
            class="flex flex-col gap-1 sm:flex-row sm:gap-6"
          >
            <p class="text-sm font-medium sm:w-48 sm:shrink-0">
              Imagen {{ reviewIndex + 1 }} · Reseña
            </p>
            <div class="flex min-w-0 flex-1 items-start gap-4">
              <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-x-2 text-xs text-text-muted">
                  <span
                    role="img"
                    :aria-label="`${review.stars} de 5 estrellas`"
                  >{{ formatStars(review.stars) }}</span>
                  <span
                    v-if="review.name"
                    class="font-medium text-text"
                  >{{ review.name }}</span>
                </p>
                <blockquote class="mt-1 max-w-prose text-sm leading-6 whitespace-pre-line">
                  {{ review.text }}
                </blockquote>
              </div>
              <button
                v-if="canRemoveCarouselReviews"
                type="button"
                :aria-label="`Quitar la imagen ${reviewIndex + 1}`"
                class="inline-flex min-h-11 shrink-0 items-center rounded-sm px-3 text-sm text-text-muted
                  enabled:cursor-pointer enabled:hover:bg-surface-selected enabled:hover:text-text
                  disabled:cursor-not-allowed"
                @click="removeCarouselReview(review)"
              >
                Quitar
              </button>
            </div>
          </div>
          <p class="text-xs text-text-muted sm:ml-54">
            Son las palabras de tus clientes: no se editan.
          </p>

          <div class="flex flex-col gap-1 sm:flex-row sm:gap-6">
            <!-- La invitación es la última imagen del carrusel. -->
            <p
              id="closing-image-label"
              class="text-sm font-medium sm:w-48 sm:shrink-0"
            >
              Imagen {{ carouselImagesCount }} · Invitación
            </p>
            <div
              role="radiogroup"
              aria-labelledby="closing-image-label"
              class="min-w-0 flex-1"
            >
              <p class="text-sm text-text-muted">
                Elige el texto:
              </p>
              <label
                v-for="(closingTextOption, closingTextIndex) in suggestedPiece.closing_texts"
                :key="closingTextIndex"
                class="mt-2 flex cursor-pointer items-start gap-3 text-sm leading-6"
              >
                <input
                  type="radio"
                  name="closing-text"
                  :checked="isClosingTextOptionChecked(closingTextIndex)"
                  class="mt-1.5 shrink-0"
                  @change="chooseClosingText(closingTextIndex)"
                >
                <span>{{ closingTextOption }}</span>
              </label>
              <label class="mt-2 flex cursor-pointer items-start gap-3 text-sm leading-6">
                <input
                  type="radio"
                  name="closing-text"
                  :checked="isManualClosingTextChosen"
                  class="mt-1.5 shrink-0"
                  @change="chooseManualClosingText"
                >
                <span>Escribir manualmente</span>
              </label>
              <textarea
                v-if="isManualClosingTextChosen"
                v-model="manualClosingText"
                rows="2"
                aria-label="Tu texto de la invitación"
                class="mt-2 block w-full resize-y rounded-sm border border-border bg-surface-raised px-3.5 py-2.5
                  text-sm leading-6 text-text disabled:cursor-not-allowed disabled:text-text-muted"
              />
            </div>
          </div>
        </div>

        <!-- Una sola imagen, con una idea de varias reseñas: el usuario elige cuál va. -->
        <div
          v-else-if="canChooseSingleImageReview"
          class="flex flex-col gap-1 border-t border-border pt-6 sm:flex-row sm:gap-6"
        >
          <p
            id="single-image-label"
            class="text-sm font-medium sm:w-48 sm:shrink-0"
          >
            Imagen · Reseña
          </p>
          <div
            role="radiogroup"
            aria-labelledby="single-image-label"
            class="min-w-0 flex-1"
          >
            <p class="text-sm text-text-muted">
              ¿Qué reseña va en la imagen?
            </p>
            <label
              v-for="review in pieceReviews"
              :key="review.id"
              class="mt-2 flex cursor-pointer items-start gap-3"
            >
              <input
                v-model="chosenSingleImageReviewId"
                type="radio"
                name="single-image-review"
                :value="review.id"
                class="mt-1 shrink-0"
              >
              <span class="min-w-0">
                <span class="flex flex-wrap items-center gap-x-2 text-xs text-text-muted">
                  <span
                    role="img"
                    :aria-label="`${review.stars} de 5 estrellas`"
                  >{{ formatStars(review.stars) }}</span>
                  <span
                    v-if="review.name"
                    class="font-medium text-text"
                  >{{ review.name }}</span>
                </span>
                <span class="mt-1 block max-w-prose text-sm leading-6 whitespace-pre-line">{{ review.text }}</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Una idea de una sola reseña: esa reseña va en la imagen, sin elegir. -->
        <div
          v-else
          class="flex flex-col gap-1 border-t border-border pt-6 sm:flex-row sm:gap-6"
        >
          <p class="text-sm font-medium sm:w-48 sm:shrink-0">
            Imagen · Reseña
          </p>
          <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-center gap-x-2 text-xs text-text-muted">
              <span
                role="img"
                :aria-label="`${singleScriptReview.stars} de 5 estrellas`"
              >{{ formatStars(singleScriptReview.stars) }}</span>
              <span
                v-if="singleScriptReview.name"
                class="font-medium text-text"
              >{{ singleScriptReview.name }}</span>
            </p>
            <blockquote class="mt-1 max-w-prose text-sm leading-6 whitespace-pre-line">
              {{ singleScriptReview.text }}
            </blockquote>
            <p class="mt-2 text-xs text-text-muted">
              Son las palabras de tus clientes: no se editan.
            </p>
          </div>
        </div>

        <div class="flex flex-col gap-1 border-t border-border pt-6 sm:flex-row sm:gap-6">
          <p
            id="copy-label"
            class="text-sm font-medium sm:w-48 sm:shrink-0"
          >
            Texto del posteo
          </p>
          <div
            role="radiogroup"
            aria-labelledby="copy-label"
            class="min-w-0 flex-1"
          >
            <p class="text-sm text-text-muted">
              Es lo que va escrito debajo de la imagen en Instagram. Elige uno:
            </p>
            <label
              v-for="(copyOption, copyIndex) in suggestedPiece.copies"
              :key="copyIndex"
              class="mt-2 flex cursor-pointer items-start gap-3 text-sm leading-6"
            >
              <input
                type="radio"
                name="copy"
                :checked="isCopyOptionChecked(copyIndex)"
                class="mt-1.5 shrink-0"
                @change="chooseCopy(copyIndex)"
              >
              <span class="whitespace-pre-line">{{ copyOption }}</span>
            </label>
            <label class="mt-2 flex cursor-pointer items-start gap-3 text-sm leading-6">
              <input
                type="radio"
                name="copy"
                :checked="isManualCopyChosen"
                class="mt-1.5 shrink-0"
                @change="chooseManualCopy"
              >
              <span>Escribir manualmente</span>
            </label>
            <textarea
              v-if="isManualCopyChosen"
              v-model="manualCopy"
              rows="6"
              aria-label="Tu texto del posteo"
              class="mt-2 block w-full resize-y rounded-sm border border-border bg-surface-raised px-3.5 py-2.5 text-sm
                leading-6 text-text disabled:cursor-not-allowed disabled:text-text-muted"
            />
          </div>
        </div>

        <section
          aria-labelledby="other-options-heading"
          class="border-t border-border pt-6"
        >
          <h2
            id="other-options-heading"
            class="font-medium"
          >
            ¿No te convence ninguna opción?
          </h2>
          <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <label
              for="suggested-piece-instructions"
              class="text-sm"
            >Qué cambiarías (opcional):</label>
            <input
              id="suggested-piece-instructions"
              v-model="instructions"
              type="text"
              maxlength="500"
              placeholder="Por ejemplo: más corto, sin hablar de precios"
              class="block min-h-11 w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5 text-sm
                text-text placeholder:text-text-muted disabled:cursor-not-allowed disabled:text-text-muted sm:w-80"
            >
            <button
              type="button"
              class="inline-flex min-h-11 shrink-0 items-center rounded-sm border border-border px-4 text-sm
                enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed
                disabled:text-text-muted"
              @click="requestSuggestedPiece"
            >
              {{ isGenerating ? 'Escribiendo…' : 'Escribir otras opciones' }}
            </button>
            <p
              v-if="isGenerating"
              role="status"
              class="text-sm text-text-muted"
            >
              Puede tardar un rato, a veces hasta dos minutos.
            </p>
          </div>
          <!-- Si falla, la pieza anterior se conserva y el error va junto al botón. -->
          <div
            v-if="generationError"
            role="alert"
            class="mt-3 flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
          >
            <span>{{ generationError }}</span>
            <button
              type="button"
              class="min-h-11 cursor-pointer underline underline-offset-4"
              @click="requestSuggestedPiece"
            >
              Volver a intentar
            </button>
          </div>
          <p
            v-if="hasCarousel"
            class="mt-2 text-sm text-text-muted"
          >
            Vuelve a escribir las opciones de la invitación y del texto del posteo.
          </p>
          <p
            v-else
            class="mt-2 text-sm text-text-muted"
          >
            Vuelve a escribir las opciones del texto del posteo.
          </p>
        </section>
      </fieldset>

      <div class="flex flex-wrap items-center gap-3 border-t border-border pt-6">
        <button
          type="button"
          disabled
          class="inline-flex min-h-11 items-center rounded-sm bg-accent px-5 text-sm font-medium text-text-on-accent
            enabled:cursor-pointer enabled:hover:bg-accent-hover disabled:cursor-not-allowed
            disabled:bg-surface-selected disabled:text-text-muted"
        >
          Generar la pieza
        </button>
        <p class="text-sm text-text-muted">
          Todavía no disponible.
        </p>
      </div>
    </template>
  </div>
</template>


<script setup>
import { ref, computed, onMounted } from 'vue';
import PieceService from '@/services/PieceService';

const props = defineProps({
  idea: { type: Object, required: true },
  // El nombre del tipo de la idea; vacío cuando su tipo no está entre los que se listan.
  contentTypeName: { type: String, default: '' },
});

const emit = defineEmits(['back']);

// La pieza sugerida, su pedido y las indicaciones para pedir otras opciones.
const instructions = ref('');
const isGenerating = ref(false);
const generationError = ref('');
const suggestedPiece = ref(null);
// Lo que eligió el usuario: el formato (carousel o single), las reseñas que quedan en el carrusel y la reseña de la
// imagen única; y de la invitación y del texto del posteo, la opción del modelo o el texto que escribió a mano.
const format = ref('');
const manualCopy = ref('');
const chosenCopyIndex = ref(0);
const carouselReviews = ref([]);
const manualClosingText = ref('');
const isManualCopyChosen = ref(false);
const chosenClosingTextIndex = ref(0);
const chosenSingleImageReviewId = ref(null);
const isManualClosingTextChosen = ref(false);

const hasSuggestedPiece = computed(() => suggestedPiece.value !== null);
// Una idea de una sola reseña no trae carrusel: solo admite una sola imagen.
const hasCarousel = computed(() => suggestedPiece.value.carousel_script !== null);
const isCarouselChosen = computed(() => format.value === 'carousel');
// Con una sola imagen y una idea de varias reseñas, el usuario elige cuál va en la imagen.
const canChooseSingleImageReview = computed(() => hasCarousel.value && !isCarouselChosen.value);
// Las reseñas con que se escribió la pieza: las del carrusel o, si la idea tiene una sola, la de la imagen única.
const pieceReviews = computed(() => {
  return hasCarousel.value ? suggestedPiece.value.carousel_script : suggestedPiece.value.single_script;
});
// La reseña de single_script: la que el modelo marcó como la más fuerte, o la única si la idea tiene una sola.
const singleScriptReview = computed(() => suggestedPiece.value.single_script[0]);
// El carrusel lleva una imagen por cada reseña que queda y, al final, la de la invitación.
const carouselImagesCount = computed(() => carouselReviews.value.length + 1);
// En el carrusel, cada reseña se puede quitar mientras queden más de dos.
const canRemoveCarouselReviews = computed(() => carouselReviews.value.length > 2);

// La página guarda el paso de cada idea con KeepAlive, así que se monta una sola vez por idea: la pieza sugerida se
// pide al entrar por primera vez, y al volver a entrar sigue lo que había.
onMounted(requestSuggestedPiece);

// Pide la pieza sugerida de la idea, con las indicaciones si el usuario escribió alguna. Si llega, reemplaza a la
// anterior; si falla, la anterior se conserva, porque ya se pagó. El modelo puede tardar hasta dos minutos.
async function requestSuggestedPiece() {
  // Cada pedido cuesta plata: mientras hay uno en curso no se dispara otro.
  if (isGenerating.value) {
    return;
  }

  const trimmedInstructions = instructions.value.trim();
  const hasInstructions = trimmedInstructions !== '';
  const instructionsToSend = hasInstructions ? trimmedInstructions : null;

  isGenerating.value = true;
  generationError.value = '';

  try {
    suggestedPiece.value = await PieceService.generateSuggestedPiece(props.idea.id, instructionsToSend);

    // El formato lo eligió el usuario y se conserva si la pieza nueva lo admite: una sola imagen siempre, carrusel
    // solo si la pieza nueva lo trae. En el primer pedido todavía no eligió nada, y va carrusel cuando lo hay.
    const isSingleChosen = format.value === 'single';
    const isCarouselFormat = hasCarousel.value && !isSingleChosen;
    format.value = isCarouselFormat ? 'carousel' : 'single';

    // La reseña elegida para la imagen única se conserva si sigue en la pieza; si no, va la que el modelo marcó como
    // la más fuerte.
    const isChosenReviewInPiece = pieceReviews.value.some((review) => review.id === chosenSingleImageReviewId.value);
    if (!isChosenReviewInPiece) {
      chosenSingleImageReviewId.value = singleScriptReview.value.id;
    }

    // Las opciones del modelo son nuevas: donde había una elegida, queda la primera. Lo escrito a mano se conserva y
    // sigue elegido si lo estaba. El carrusel vuelve con todas sus reseñas.
    chosenCopyIndex.value = 0;
    chosenClosingTextIndex.value = 0;
    carouselReviews.value = hasCarousel.value ? suggestedPiece.value.carousel_script : [];
  } catch (error) {
    // Si la API rechaza el pedido, el motivo llega como error de un campo; sin campo, vale el message.
    const fieldErrors = Object.values(error.errors ?? {});
    const firstFieldError = fieldErrors[0]?.[0];
    generationError.value = firstFieldError ?? error.message;
  } finally {
    isGenerating.value = false;
  }
}

// Una opción del modelo queda marcada si es la elegida y el usuario no pasó a escribir su propio texto.
function isClosingTextOptionChecked(closingTextIndex) {
  const isChosenClosingText = closingTextIndex === chosenClosingTextIndex.value;
  return isChosenClosingText && !isManualClosingTextChosen.value;
}

function chooseClosingText(closingTextIndex) {
  isManualClosingTextChosen.value = false;
  chosenClosingTextIndex.value = closingTextIndex;
}

// Si todavía no escribió nada, el campo arranca con la opción que tenía elegida, para que la pueda corregir. Lo que
// escribe se conserva aunque elija otra opción y vuelva.
function chooseManualClosingText() {
  const hasWrittenClosingText = manualClosingText.value !== '';
  if (!hasWrittenClosingText) {
    manualClosingText.value = suggestedPiece.value.closing_texts[chosenClosingTextIndex.value];
  }

  isManualClosingTextChosen.value = true;
}

// Una opción del modelo queda marcada si es la elegida y el usuario no pasó a escribir su propio texto.
function isCopyOptionChecked(copyIndex) {
  const isChosenCopy = copyIndex === chosenCopyIndex.value;
  return isChosenCopy && !isManualCopyChosen.value;
}

function chooseCopy(copyIndex) {
  isManualCopyChosen.value = false;
  chosenCopyIndex.value = copyIndex;
}

// Si todavía no escribió nada, el campo arranca con la opción que tenía elegida, para que la pueda corregir. Lo que
// escribe se conserva aunque elija otra opción y vuelva.
function chooseManualCopy() {
  const hasWrittenCopy = manualCopy.value !== '';
  if (!hasWrittenCopy) {
    manualCopy.value = suggestedPiece.value.copies[chosenCopyIndex.value];
  }

  isManualCopyChosen.value = true;
}

function removeCarouselReview(reviewToRemove) {
  const remainingCarouselReviews = carouselReviews.value.filter((review) => review.id !== reviewToRemove.id);
  carouselReviews.value = remainingCarouselReviews;
}

// Las estrellas de la reseña sobre cinco, llenas y vacías.
function formatStars(stars) {
  const filledStars = '★'.repeat(stars);
  const emptyStars = '☆'.repeat(5 - stars);
  return filledStars + emptyStars;
}
</script>
