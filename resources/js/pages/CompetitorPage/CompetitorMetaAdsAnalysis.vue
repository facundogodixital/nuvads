<template>
  <div class="mt-8 space-y-8">
    <section aria-labelledby="competitor-meta-ads-numbers-heading">
      <h3
        id="competitor-meta-ads-numbers-heading"
        class="spec-label mb-3"
      >
        En números
      </h3>
      <dl class="grid border-y border-border sm:grid-cols-3">
        <div class="py-3 sm:pr-4">
          <dt class="text-xs text-text-muted">
            Anuncios analizados
          </dt>
          <dd class="mt-1 text-lg font-medium tabular-nums">
            {{ metrics.ads_count }}
          </dd>
        </div>
        <div class="border-t border-border py-3 sm:border-t-0 sm:border-l sm:px-4">
          <dt class="text-xs text-text-muted">
            El que más lleva corriendo
          </dt>
          <dd class="mt-1 text-lg font-medium tabular-nums">
            {{ formatCount(metrics.longest_running_days, 'día', 'días') }}
          </dd>
        </div>
        <div class="border-t border-border py-3 sm:border-t-0 sm:border-l sm:px-4">
          <dt class="text-xs text-text-muted">
            Plataformas
          </dt>
          <dd class="mt-1 text-sm font-medium leading-7">
            {{ platformsLabel }}
          </dd>
        </div>
      </dl>

      <h4 class="mt-6 text-sm font-medium">
        Promedio por formato
      </h4>
      <ul class="mt-3 divide-y divide-border border-y border-border">
        <li
          v-for="(formatMetrics, format) in metrics.formats"
          :key="format"
          class="flex flex-wrap items-baseline justify-between gap-x-3 py-3 text-sm"
        >
          <span>
            <span class="font-medium">{{ formatNames[format] ?? 'Otro' }}</span>
            <span class="text-text-muted"> · {{ formatCount(formatMetrics.ads, 'anuncio', 'anuncios') }}</span>
          </span>
          <span class="tabular-nums text-text-muted">
            {{ formatCount(formatMetrics.average_days_running, 'día', 'días') }} corriendo en promedio
          </span>
        </li>
      </ul>
      <p class="mt-4 text-xs leading-5 text-text-muted">
        No hay datos de resultados: un anuncio que lleva mucho tiempo corriendo suele ser uno que le funciona.
      </p>
    </section>

    <section
      v-if="adsByDaysRunning.length"
      aria-labelledby="competitor-meta-ads-list-heading"
    >
      <h3
        id="competitor-meta-ads-list-heading"
        class="spec-label mb-3"
      >
        Anuncios analizados, de más a menos días corriendo
      </h3>
      <ul class="divide-y divide-border border-y border-border">
        <li
          v-for="ad in adsByDaysRunning"
          :key="ad.id"
          class="flex gap-3 py-4"
        >
          <!-- Las URLs de Meta vencen a los pocos días; si la imagen no carga, queda el formato escrito. -->
          <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-surface-selected text-xs text-text-muted">
            <img
              v-if="canShowImage(ad.payload.media[0]?.image_url)"
              :src="ad.payload.media[0].image_url"
              alt=""
              loading="lazy"
              referrerpolicy="no-referrer"
              class="h-full w-full object-cover"
              @error="markImageAsFailed(ad.payload.media[0].image_url)"
            >
            <template v-else>{{ formatNames[getAdFormat(ad)] ?? 'Anuncio' }}</template>
          </span>
          <div class="min-w-0 flex-1">
            <a
              :href="ad.payload.url"
              target="_blank"
              rel="noopener noreferrer"
              class="line-clamp-2 text-sm leading-6 hover:underline hover:underline-offset-4"
            >
              {{ ad.payload.copy || 'Anuncio sin texto' }}<span class="sr-only"> (abre la Biblioteca de anuncios)</span>
            </a>
            <p class="mt-1 text-xs tabular-nums text-text-muted">
              {{ formatNames[getAdFormat(ad)] ?? 'Otro' }}
              · {{ formatCount(ad.payload.days_running, 'día', 'días') }}
              · {{ ad.payload.raw.isActive ? 'Activo' : 'Terminado' }}
            </p>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>


<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  analysis: { type: Object, required: true },
  ads: { type: Array, required: true },
});

// El displayFormat de Meta en minúsculas: dco es un anuncio dinámico y dpa, uno de catálogo.
const formatNames = { image: 'Imagen', video: 'Video', carousel: 'Carrusel', dco: 'Dinámico', dpa: 'Catálogo' };
const platformNames = { facebook: 'Facebook', instagram: 'Instagram', messenger: 'Messenger', audience_network: 'Audience Network' };
// Las imágenes que no cargaron, por ejemplo porque venció el enlace de Meta.
const failedImageUrls = ref(new Set());

const metrics = computed(() => props.analysis.payload.metrics);
const platformsLabel = computed(() => {
  const platforms = Object.keys(metrics.value.platforms).map((platform) => platformNames[platform] ?? platform);
  return platforms.length ? platforms.join(', ') : 'Sin datos';
});
// Los que más tiempo lleva sosteniendo primero, porque suelen ser los que le funcionan.
const adsByDaysRunning = computed(() => [...props.ads].sort(
  (first, second) => second.payload.days_running - first.payload.days_running,
));

function canShowImage(imageUrl) {
  return Boolean(imageUrl) && !failedImageUrls.value.has(imageUrl);
}

function markImageAsFailed(imageUrl) {
  failedImageUrls.value.add(imageUrl);
}

function getAdFormat(ad) {
  return (ad.payload.raw.snapshot.displayFormat ?? 'unknown').toLowerCase();
}

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}
</script>
