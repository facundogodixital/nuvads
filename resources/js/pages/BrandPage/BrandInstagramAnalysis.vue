<template>
  <div class="mt-8 space-y-8">
    <section
      v-if="updatedProfileFields.length"
      class="flex gap-3 rounded-sm bg-success-soft p-4"
      aria-labelledby="instagram-profile-heading"
    >
      <svg
        class="mt-0.5 h-5 w-5 shrink-0 text-success"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM8 12.5l2.5 2.5L16 9.5"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
      <div class="min-w-0 flex-1">
        <h3
          id="instagram-profile-heading"
          class="text-sm font-medium text-success"
        >
          Actualizamos tu perfil de marca
        </h3>
        <p class="mt-1 text-sm leading-6">
          Sumamos lo que vimos en tus posteos a estas partes. Revísalas y corrige lo que quieras.
        </p>
        <ul class="mt-3 flex flex-wrap gap-2">
          <li
            v-for="field in updatedProfileFields"
            :key="field"
            class="rounded-sm bg-surface-raised px-2 py-1 text-xs"
          >
            {{ field }}
          </li>
        </ul>
        <RouterLink
          to="/brand/profile"
          class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-success underline underline-offset-4 hover:text-text"
        >
          Ir a Perfil de marca
          <svg
            class="h-4 w-4"
            viewBox="0 0 20 20"
            fill="none"
            aria-hidden="true"
          ><path
            d="M4 10h12m-5-5 5 5-5 5"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          /></svg>
        </RouterLink>
      </div>
    </section>

    <section aria-labelledby="instagram-numbers-heading">
      <h3
        id="instagram-numbers-heading"
        class="spec-label mb-3"
      >
        En números
      </h3>
      <dl class="grid border-y border-border sm:grid-cols-3">
        <div
          v-for="(tile, index) in numberTiles"
          :key="tile.label"
          class="py-3 sm:px-4 sm:first:pl-0"
          :class="index > 0 ? 'border-t border-border sm:border-t-0 sm:border-l' : ''"
        >
          <dt class="text-xs text-text-muted">
            {{ tile.label }}
          </dt>
          <dd class="mt-1 text-lg font-medium tabular-nums">
            {{ tile.value }}
          </dd>
        </div>
      </dl>

      <h4 class="mt-6 text-sm font-medium">
        Promedio por formato
      </h4>
      <ul class="mt-3 space-y-4">
        <li
          v-for="format in formatRows"
          :key="format.key"
        >
          <div class="flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
            <span>
              <span class="font-medium">{{ format.label }}</span>
              <span class="text-text-muted"> · {{ formatCount(format.posts, 'posteo', 'posteos') }}</span>
            </span>
            <span class="tabular-nums text-text-muted">
              {{ format.averageLikes === null ? 'Likes ocultos' : formatCount(format.averageLikes, 'like', 'likes') }}
              · {{ formatCount(format.averageComments, 'comentario', 'comentarios') }}
            </span>
          </div>
          <!-- La barra compara el promedio de likes; los números de arriba la cuentan en texto. -->
          <div
            class="mt-2 h-1.5"
            aria-hidden="true"
          >
            <div
              v-if="format.likesShare > 0"
              class="h-1.5 rounded-r bg-text"
              :style="{ width: `max(4px, ${format.likesShare}%)` }"
            />
          </div>
          <p
            v-if="format.pinnedPosts"
            class="mt-1.5 flex items-center gap-1 text-xs text-text-muted"
          >
            <svg
              class="h-3.5 w-3.5"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                :d="pinIcon"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            Incluye {{ formatCount(format.pinnedPosts, 'posteo fijado', 'posteos fijados') }}
          </p>
        </li>
      </ul>
      <p
        v-if="hasPinnedPosts"
        class="mt-4 text-xs leading-5 text-text-muted"
      >
        Los posteos fijados suelen ser los mejores de la cuenta, aunque sean viejos, y suben el promedio de su formato.
      </p>
    </section>

    <section
      v-if="sortedPosts.length"
      aria-labelledby="instagram-posts-heading"
    >
      <h3
        id="instagram-posts-heading"
        class="spec-label mb-3"
      >
        Posteos analizados
      </h3>
      <ul class="divide-y divide-border border-y border-border">
        <li
          v-for="post in sortedPosts"
          :key="post.id"
          class="flex gap-3 py-4"
        >
          <!-- Las URLs de Instagram vencen a los pocos días; si la imagen no carga, queda el ícono del formato. -->
          <button
            type="button"
            :aria-label="enlargeLabels[getPostFormat(post)]"
            class="relative flex h-16 w-16 shrink-0 cursor-zoom-in items-center justify-center overflow-hidden rounded-sm bg-surface-selected text-text-muted hover:opacity-90"
            @click="brandInstagramMediaModalStore.open(post)"
          >
            <img
              v-if="canShowImage(post.payload.image_urls?.[0])"
              :src="post.payload.image_urls[0]"
              alt=""
              loading="lazy"
              referrerpolicy="no-referrer"
              class="h-full w-full object-cover"
              @error="markImageAsFailed(post.payload.image_urls[0])"
            >
            <svg
              v-else
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                :d="formatIcons[getPostFormat(post)]"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <!-- Como en Instagram, la esquina avisa si es un reel o un carrusel. -->
            <span
              v-if="canShowImage(post.payload.image_urls?.[0]) && formatBadgeIcons[getPostFormat(post)]"
              class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-sm bg-surface-raised text-text"
            >
              <svg
                class="h-3 w-3"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
              >
                <path
                  :d="formatBadgeIcons[getPostFormat(post)]"
                  stroke="currentColor"
                  stroke-width="2"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </span>
          </button>

          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
              <a
                :href="post.payload.url"
                target="_blank"
                rel="noopener noreferrer"
                class="line-clamp-2 text-sm leading-6 hover:underline hover:underline-offset-4"
              >
                {{ post.payload.caption || 'Posteo sin texto' }}<span class="sr-only"> (abre Instagram)</span>
              </a>
              <dl class="flex shrink-0 items-center gap-3 pt-0.5 text-xs tabular-nums text-text-muted">
                <div
                  v-if="post.payload.raw.likesCount >= 0"
                  class="flex items-center gap-1"
                >
                  <dt>
                    <svg
                      class="h-4 w-4"
                      viewBox="0 0 24 24"
                      fill="none"
                    >
                      <title>Likes</title>
                      <path
                        :d="likeIcon"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </dt>
                  <dd>{{ post.payload.raw.likesCount.toLocaleString('es') }}</dd>
                </div>
                <div class="flex items-center gap-1">
                  <dt>
                    <svg
                      class="h-4 w-4"
                      viewBox="0 0 24 24"
                      fill="none"
                    >
                      <title>Comentarios</title>
                      <path
                        :d="commentIcon"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </dt>
                  <dd>{{ post.payload.raw.commentsCount.toLocaleString('es') }}</dd>
                </div>
              </dl>
            </div>

            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
              <span>{{ formatDate(post.payload.raw.timestamp) }}</span>
              <span aria-hidden="true">·</span>
              <span>{{ formatNames[getPostFormat(post)] }}</span>
              <span
                v-if="post.payload.raw.isPinned"
                class="inline-flex items-center gap-1 rounded-sm bg-surface-selected px-1.5 py-0.5 text-text"
              >
                <svg
                  class="h-3.5 w-3.5"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    :d="pinIcon"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
                Fijado
              </span>
            </p>

            <details
              v-if="post.payload.images?.length"
              class="group mt-2"
            >
              <summary class="inline-flex min-h-8 cursor-pointer list-none items-center gap-1 text-xs font-medium text-text-muted hover:text-text">
                <svg
                  class="h-3.5 w-3.5 transition-transform duration-200 group-open:rotate-90"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  <path
                    d="m9 6 6 6-6 6"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                </svg>
                Lo que vimos en el posteo
              </summary>
              <ol class="mt-3 space-y-4">
                <li
                  v-for="(image, index) in post.payload.images"
                  :key="index"
                  class="flex flex-col gap-3 sm:flex-row"
                >
                  <!-- El modelo puede devolver más entradas que imágenes: esas quedan sin imagen. -->
                  <button
                    v-if="canShowImage(post.payload.image_urls?.[index])"
                    type="button"
                    :aria-label="getPostFormat(post) === 'reel' ? enlargeLabels.reel : 'Ver esta imagen en grande'"
                    class="w-32 shrink-0 cursor-zoom-in self-start overflow-hidden rounded-sm hover:opacity-90 sm:w-24"
                    @click="brandInstagramMediaModalStore.open(post, index)"
                  >
                    <img
                      :src="post.payload.image_urls[index]"
                      alt=""
                      loading="lazy"
                      referrerpolicy="no-referrer"
                      class="w-full bg-surface-selected"
                      @error="markImageAsFailed(post.payload.image_urls[index])"
                    >
                  </button>
                  <div class="min-w-0 flex-1">
                    <p class="spec-label">
                      {{ getPostFormat(post) === 'reel' ? 'Portada' : `Imagen ${index + 1}` }}
                    </p>
                    <p class="mt-1 text-sm leading-6">
                      {{ image.description }}
                    </p>
                    <p
                      v-if="image.transcription"
                      class="mt-2 border-l border-border pl-3 text-xs leading-5 text-text-muted"
                    >
                      {{ image.transcription }}
                    </p>
                  </div>
                </li>
              </ol>
            </details>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>


<script setup>
import { ref, computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useBrandInstagramMediaModalStore } from '@/stores/brandInstagramMediaModalStore';

const props = defineProps({
  analysis: { type: Object, required: true },
  posts: { type: Array, required: true },
});

const brandInstagramMediaModalStore = useBrandInstagramMediaModalStore();

const likeIcon = 'M12 20s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 10c0 5.65-7 10-7 10Z';
const pinIcon = 'M9 4h6l-1 5 3 3v2H7v-2l3-3-1-5ZM12 14v6';
const commentIcon = 'M4 20l1.5-4A8 8 0 1 1 8 18.5L4 20Z';
const formatIcons = {
  reel: 'M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM10 9l5 3-5 3V9Z',
  carousel: 'M8 8h12v12H8V8ZM4 16V4h12',
  image: 'M4 5h16v14H4V5ZM4 15l4-4 4 4 3-3 5 5M15 9h.01',
};
const formatBadgeIcons = {
  reel: 'M8 5v14l11-7L8 5Z',
  carousel: 'M8 8h12v12H8V8ZM4 16V4h12',
};
const formatNames = { reel: 'Reel', carousel: 'Carrusel', image: 'Imagen' };
const enlargeLabels = {
  reel: 'Ver el reel en grande',
  carousel: 'Ver el carrusel en grande',
  image: 'Ver la imagen en grande',
};
const formatPluralNames = { reel: 'Reels', carousel: 'Carruseles', image: 'Imágenes' };
// Mismos nombres que en Perfil de marca, para que el usuario los reconozca.
const profileFieldNames = {
  brand_customers_description: 'Quiénes te compran',
  brand_customers_needs_description: 'Qué necesitan',
  brand_visual_style_description: 'Estilo visual',
  brand_tone_of_voice_description: 'Tu manera de hablar',
  brand_communication_topics_description: 'De qué hablas hoy',
};

// Las imágenes que no cargaron, por ejemplo porque venció el enlace de Instagram.
const failedImageUrls = ref(new Set());

const metrics = computed(() => props.analysis.payload.metrics);
const hasPinnedPosts = computed(() => props.posts.some((post) => post.payload.raw.isPinned));
// Los más nuevos primero; los fijados suelen ser viejos y quedan al final.
const sortedPosts = computed(() => [...props.posts].sort(
  (first, second) => new Date(second.payload.raw.timestamp) - new Date(first.payload.raw.timestamp),
));
// Los campos que el modelo devolvió con texto ya quedaron guardados en la marca.
const updatedProfileFields = computed(() => {
  const mergedBrandFields = props.analysis.payload.brand ?? {};
  return Object.keys(profileFieldNames)
    .filter((field) => mergedBrandFields[field]?.trim())
    .map((field) => profileFieldNames[field]);
});
const numberTiles = computed(() => {
  const tiles = [{ label: 'Posteos analizados', value: metrics.value.posts_count }];
  // Con menos de dos posteos no hay frecuencia.
  const hasPostsPerWeek = metrics.value.posts_per_week !== null;
  if (hasPostsPerWeek) {
    tiles.push({ label: 'Frecuencia', value: formatFrequency(metrics.value.posts_per_week) });
  }
  if (sortedPosts.value.length) {
    tiles.push({ label: 'Período', value: getPublishingPeriod() });
  }
  return tiles;
});
// Ordenados por promedio de likes; la barra de cada uno es relativa al que tiene más.
const formatRows = computed(() => {
  const formats = metrics.value.formats;
  const maxAverageLikes = Math.max(0, ...Object.values(formats).map((format) => format.average_likes ?? 0));

  const rows = Object.entries(formats).map(([format, formatMetrics]) => {
    const hasVisibleLikes = formatMetrics.average_likes !== null && maxAverageLikes > 0;
    const pinnedPosts = props.posts.filter((post) => {
      return getPostFormat(post) === format && post.payload.raw.isPinned;
    });
    return {
      key: format,
      label: formatPluralNames[format],
      posts: formatMetrics.posts,
      averageLikes: formatMetrics.average_likes,
      averageComments: formatMetrics.average_comments,
      likesShare: hasVisibleLikes ? formatMetrics.average_likes / maxAverageLikes * 100 : 0,
      pinnedPosts: pinnedPosts.length,
    };
  });
  return rows.sort((first, second) => (second.averageLikes ?? -1) - (first.averageLikes ?? -1));
});

function canShowImage(imageUrl) {
  return Boolean(imageUrl) && !failedImageUrls.value.has(imageUrl);
}

function markImageAsFailed(imageUrl) {
  failedImageUrls.value.add(imageUrl);
}

function getPostFormat(post) {
  const formatsByApifyType = { Video: 'reel', Sidecar: 'carousel', Image: 'image' };
  return formatsByApifyType[post.payload.raw.type];
}

// Menos de un posteo por semana se lee mejor como "1 cada N semanas".
function formatFrequency(postsPerWeek) {
  const weeksBetweenPosts = Math.round(1 / postsPerWeek);
  const postsLessThanWeekly = weeksBetweenPosts > 1;
  if (postsLessThanWeekly) {
    return `1 cada ${weeksBetweenPosts} semanas`;
  }
  return `${postsPerWeek.toLocaleString('es')} por semana`;
}

function getPublishingPeriod() {
  const newestMonth = formatMonth(sortedPosts.value[0].payload.raw.timestamp);
  const oldestMonth = formatMonth(sortedPosts.value.at(-1).payload.raw.timestamp);
  return newestMonth === oldestMonth ? newestMonth : `${oldestMonth} – ${newestMonth}`;
}

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}

function formatMonth(isoDate) {
  return new Date(isoDate).toLocaleDateString('es', { month: 'short', year: 'numeric' });
}

function formatDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>


<style scoped>
/* Safari dibuja su propio triángulo en summary; la flecha ya la pone el componente. */
summary::-webkit-details-marker {
  display: none;
}
</style>
