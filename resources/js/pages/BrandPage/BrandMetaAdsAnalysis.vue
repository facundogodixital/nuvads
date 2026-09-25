<template>
  <div class="mt-8 space-y-8">
    <section
      v-if="updatedProfileFields.length"
      class="flex gap-3 rounded-sm bg-success-soft p-4"
      aria-labelledby="meta-ads-profile-heading"
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
          id="meta-ads-profile-heading"
          class="text-sm font-medium text-success"
        >
          Actualizamos tu perfil de marca
        </h3>
        <p class="mt-1 text-sm leading-6">
          Sumamos lo que vimos en tus anuncios a estas partes. Revísalas y corrige lo que quieras.
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

    <section aria-labelledby="meta-ads-numbers-heading">
      <h3
        id="meta-ads-numbers-heading"
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
              <span class="text-text-muted"> · {{ formatCount(format.ads, 'anuncio', 'anuncios') }}</span>
            </span>
            <span class="tabular-nums text-text-muted">
              {{ formatDays(format.averageDaysRunning) }} corriendo
            </span>
          </div>
          <!-- La barra compara el promedio de días; los números de arriba la cuentan en texto. -->
          <div
            class="mt-2 h-1.5"
            aria-hidden="true"
          >
            <div
              v-if="format.daysShare > 0"
              class="h-1.5 rounded-r bg-text"
              :style="{ width: `max(4px, ${format.daysShare}%)` }"
            />
          </div>
        </li>
      </ul>
      <p class="mt-4 text-xs leading-5 text-text-muted">
        Meta no muestra los resultados de los anuncios. Que uno siga corriendo muchos días es la mejor pista de que funciona.
      </p>
    </section>

    <section
      v-if="sortedAds.length"
      aria-labelledby="meta-ads-list-heading"
    >
      <h3
        id="meta-ads-list-heading"
        class="spec-label mb-3"
      >
        Anuncios analizados
      </h3>
      <ul class="divide-y divide-border border-y border-border">
        <li
          v-for="ad in sortedAds"
          :key="ad.id"
          class="flex gap-3 py-4"
        >
          <!-- Las URLs de Meta vencen a los pocos días; si la imagen no carga, queda el ícono del formato. -->
          <button
            type="button"
            aria-label="Ver el anuncio en grande"
            :disabled="!ad.payload.media.length"
            class="relative flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-surface-selected text-text-muted enabled:cursor-zoom-in enabled:hover:opacity-90"
            @click="brandMetaAdsMediaModalStore.open(ad)"
          >
            <img
              v-if="canShowImage(ad.payload.media[0]?.image_url)"
              :src="ad.payload.media[0].image_url"
              alt=""
              loading="lazy"
              referrerpolicy="no-referrer"
              class="h-full w-full object-cover"
              @error="markImageAsFailed(ad.payload.media[0].image_url)"
            >
            <svg
              v-else
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                :d="formatIcons[getAdFormat(ad)] ?? formatIcons.image"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <!-- La esquina avisa si es un video o un carrusel. -->
            <span
              v-if="canShowImage(ad.payload.media[0]?.image_url) && formatBadgeIcons[getAdFormat(ad)]"
              class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-sm bg-surface-raised text-text"
            >
              <svg
                class="h-3 w-3"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
              >
                <path
                  :d="formatBadgeIcons[getAdFormat(ad)]"
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
                :href="ad.payload.url"
                target="_blank"
                rel="noopener noreferrer"
                class="line-clamp-2 text-sm leading-6 hover:underline hover:underline-offset-4"
              >
                {{ ad.payload.copy || 'Anuncio sin texto' }}<span class="sr-only"> (abre la Biblioteca de anuncios de Meta)</span>
              </a>
              <span
                v-if="ad.payload.raw.isActive"
                class="shrink-0 rounded-sm bg-success-soft px-2 py-1 text-xs text-success"
              >
                Activo
              </span>
              <span
                v-else
                class="shrink-0 rounded-sm border border-border px-2 py-1 text-xs text-text-muted"
              >
                Finalizado
              </span>
            </div>

            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
              <span>Desde el {{ formatDate(ad.payload.raw.startDate) }}</span>
              <span aria-hidden="true">·</span>
              <span class="tabular-nums">{{ formatDays(ad.payload.days_running) }}</span>
              <span aria-hidden="true">·</span>
              <span>{{ formatNames[getAdFormat(ad)] ?? 'Otro formato' }}</span>
              <template v-if="getAdPlatforms(ad).length">
                <span aria-hidden="true">·</span>
                <span>{{ getAdPlatforms(ad).join(', ') }}</span>
              </template>
            </p>

            <details
              v-if="ad.payload.images?.length"
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
                Lo que vimos en el anuncio
              </summary>
              <ol class="mt-3 space-y-4">
                <li
                  v-for="(image, index) in ad.payload.images"
                  :key="index"
                  class="flex flex-col gap-3 sm:flex-row"
                >
                  <!-- El modelo puede devolver más entradas que imágenes: esas quedan sin imagen. -->
                  <button
                    v-if="canShowImage(ad.payload.media[index]?.image_url)"
                    type="button"
                    :aria-label="isVideo(ad, index) ? 'Ver el video en grande' : 'Ver esta imagen en grande'"
                    class="w-32 shrink-0 cursor-zoom-in self-start overflow-hidden rounded-sm hover:opacity-90 sm:w-24"
                    @click="brandMetaAdsMediaModalStore.open(ad, index)"
                  >
                    <img
                      :src="ad.payload.media[index].image_url"
                      alt=""
                      loading="lazy"
                      referrerpolicy="no-referrer"
                      class="w-full bg-surface-selected"
                      @error="markImageAsFailed(ad.payload.media[index].image_url)"
                    >
                  </button>
                  <div class="min-w-0 flex-1">
                    <p class="spec-label">
                      {{ isVideo(ad, index) ? 'Portada del video' : `Imagen ${index + 1}` }}
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
import { useBrandMetaAdsMediaModalStore } from '@/stores/brandMetaAdsMediaModalStore';

const props = defineProps({
  analysis: { type: Object, required: true },
  ads: { type: Array, required: true },
});

const brandMetaAdsMediaModalStore = useBrandMetaAdsMediaModalStore();

const formatIcons = {
  image: 'M4 5h16v14H4V5ZM4 15l4-4 4 4 3-3 5 5M15 9h.01',
  video: 'M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1ZM10 9l5 3-5 3V9Z',
  carousel: 'M8 8h12v12H8V8ZM4 16V4h12',
};
const formatBadgeIcons = {
  video: 'M8 5v14l11-7L8 5Z',
  carousel: 'M8 8h12v12H8V8ZM4 16V4h12',
};
// Los formatos son el displayFormat de Meta en minúsculas; dco es el anuncio dinámico y dpa, el de catálogo.
const formatNames = { image: 'Imagen', video: 'Video', carousel: 'Carrusel', dco: 'Dinámico', dpa: 'Catálogo' };
const formatPluralNames = {
  image: 'Imágenes',
  video: 'Videos',
  carousel: 'Carruseles',
  dco: 'Dinámicos',
  dpa: 'De catálogo',
};
const platformNames = {
  facebook: 'Facebook',
  instagram: 'Instagram',
  messenger: 'Messenger',
  threads: 'Threads',
  whatsapp: 'WhatsApp',
  audience_network: 'Audience Network',
};
// Mismos nombres que en Perfil de marca, para que el usuario los reconozca.
const profileFieldNames = {
  brand_offer_description: 'Productos y servicios',
  brand_differentiators_description: 'Qué te hace diferente',
  brand_customers_description: 'Quiénes te compran',
  brand_customers_needs_description: 'Qué necesitan',
  brand_visual_style_description: 'Estilo visual',
  brand_tone_of_voice_description: 'Tu manera de hablar',
  brand_communication_topics_description: 'De qué hablas hoy',
  brand_content_opportunities_description: 'Oportunidades de contenido',
};

// Las imágenes que no cargaron, por ejemplo porque venció el enlace de Meta.
const failedImageUrls = ref(new Set());

const metrics = computed(() => props.analysis.payload.metrics);
// Los más nuevos primero.
const sortedAds = computed(() => [...props.ads].sort(
  (first, second) => second.payload.raw.startDate - first.payload.raw.startDate,
));
// Los campos que el modelo devolvió con texto ya quedaron guardados en la marca.
const updatedProfileFields = computed(() => {
  // Si la fuente es de otro negocio, no se guardó nada en la marca.
  const isFromAnotherBusiness = props.analysis.payload.matches_brand === false;
  if (isFromAnotherBusiness) {
    return [];
  }
  const mergedBrandFields = props.analysis.payload.brand ?? {};
  return Object.keys(profileFieldNames)
    .filter((field) => mergedBrandFields[field]?.trim())
    .map((field) => profileFieldNames[field]);
});
const numberTiles = computed(() => {
  const tiles = [
    { label: 'Anuncios analizados', value: metrics.value.ads_count },
    { label: 'Más tiempo corriendo', value: formatDays(metrics.value.longest_running_days) },
  ];
  // Primero la plataforma donde salen más anuncios.
  const platforms = Object.entries(metrics.value.platforms)
    .sort(([, firstCount], [, secondCount]) => secondCount - firstCount)
    .map(([platform]) => platformNames[platform] ?? platform);
  if (platforms.length) {
    tiles.push({ label: 'Plataformas', value: new Intl.ListFormat('es').format(platforms) });
  }
  return tiles;
});
// Ordenados por promedio de días; la barra de cada uno es relativa al que tiene más.
const formatRows = computed(() => {
  const formats = metrics.value.formats;
  const maxAverageDays = Math.max(0, ...Object.values(formats).map((format) => format.average_days_running));

  const rows = Object.entries(formats).map(([format, formatMetrics]) => ({
    key: format,
    label: formatPluralNames[format] ?? 'Otros formatos',
    ads: formatMetrics.ads,
    averageDaysRunning: formatMetrics.average_days_running,
    daysShare: maxAverageDays > 0 ? formatMetrics.average_days_running / maxAverageDays * 100 : 0,
  }));
  return rows.sort((first, second) => second.averageDaysRunning - first.averageDaysRunning);
});

function canShowImage(imageUrl) {
  return Boolean(imageUrl) && !failedImageUrls.value.has(imageUrl);
}

function markImageAsFailed(imageUrl) {
  failedImageUrls.value.add(imageUrl);
}

function isVideo(ad, mediaIndex) {
  return ad.payload.media[mediaIndex]?.type === 'video';
}

function getAdFormat(ad) {
  return (ad.payload.raw.snapshot.displayFormat ?? 'unknown').toLowerCase();
}

function getAdPlatforms(ad) {
  const platforms = ad.payload.raw.publisherPlatform ?? [];
  return platforms.map((platform) => platformNames[platform.toLowerCase()] ?? platform);
}

function formatDays(days) {
  if (days === 0) {
    return 'Menos de un día';
  }
  return formatCount(days, 'día', 'días');
}

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}

// Meta da las fechas en segundos desde 1970.
function formatDate(unixSeconds) {
  return new Date(unixSeconds * 1000).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>


<style scoped>
/* Safari dibuja su propio triángulo en summary; la flecha ya la pone el componente. */
summary::-webkit-details-marker {
  display: none;
}
</style>
