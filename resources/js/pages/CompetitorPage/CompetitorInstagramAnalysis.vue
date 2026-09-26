<template>
  <div class="mt-8 space-y-8">
    <section aria-labelledby="competitor-instagram-numbers-heading">
      <h3
        id="competitor-instagram-numbers-heading"
        class="spec-label mb-3"
      >
        En números
      </h3>
      <dl class="grid border-y border-border sm:grid-cols-2">
        <div class="py-3 sm:pr-4">
          <dt class="text-xs text-text-muted">
            Posteos analizados
          </dt>
          <dd class="mt-1 text-lg font-medium tabular-nums">
            {{ metrics.posts_count }}
          </dd>
        </div>
        <div class="border-t border-border py-3 sm:border-t-0 sm:border-l sm:px-4">
          <dt class="text-xs text-text-muted">
            Frecuencia
          </dt>
          <dd class="mt-1 text-lg font-medium tabular-nums">
            {{ frequencyLabel }}
          </dd>
        </div>
      </dl>

      <h4 class="mt-6 text-sm font-medium">
        Promedio por formato
      </h4>
      <ul class="mt-3 divide-y divide-border border-y border-border">
        <li
          v-for="format in formatRows"
          :key="format.key"
          class="flex flex-wrap items-baseline justify-between gap-x-3 py-3 text-sm"
        >
          <span>
            <span class="font-medium">{{ format.label }}</span>
            <span class="text-text-muted"> · {{ formatCount(format.posts, 'posteo', 'posteos') }}</span>
          </span>
          <span class="tabular-nums text-text-muted">
            {{ format.averageLikes === null ? 'Likes ocultos' : formatCount(format.averageLikes, 'like', 'likes') }}
            · {{ formatCount(format.averageComments, 'comentario', 'comentarios') }}
          </span>
        </li>
      </ul>
    </section>

    <section
      v-if="postsByLikes.length"
      aria-labelledby="competitor-instagram-posts-heading"
    >
      <h3
        id="competitor-instagram-posts-heading"
        class="spec-label mb-3"
      >
        Posteos analizados, de más a menos likes
      </h3>
      <ul class="divide-y divide-border border-y border-border">
        <li
          v-for="post in postsByLikes"
          :key="post.id"
          class="flex gap-3 py-4"
        >
          <!-- Las URLs de Instagram vencen a los pocos días; si la imagen no carga, queda el formato escrito. -->
          <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-surface-selected text-xs text-text-muted">
            <img
              v-if="canShowImage(post.payload.image_urls[0])"
              :src="post.payload.image_urls[0]"
              alt=""
              loading="lazy"
              referrerpolicy="no-referrer"
              class="h-full w-full object-cover"
              @error="markImageAsFailed(post.payload.image_urls[0])"
            >
            <template v-else>{{ formatNames[getPostFormat(post)] }}</template>
          </span>
          <div class="min-w-0 flex-1">
            <a
              :href="post.payload.url"
              target="_blank"
              rel="noopener noreferrer"
              class="line-clamp-2 text-sm leading-6 hover:underline hover:underline-offset-4"
            >
              {{ post.payload.caption || 'Posteo sin texto' }}<span class="sr-only"> (abre Instagram)</span>
            </a>
            <p class="mt-1 text-xs tabular-nums text-text-muted">
              {{ formatNames[getPostFormat(post)] }} · {{ formatDate(post.payload.raw.timestamp) }}
              · {{ getLikesLabel(post) }}
              · {{ formatCount(post.payload.raw.commentsCount, 'comentario', 'comentarios') }}
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
  posts: { type: Array, required: true },
});

const formatNames = { reel: 'Reel', carousel: 'Carrusel', image: 'Imagen' };
const formatPluralNames = { reel: 'Reels', carousel: 'Carruseles', image: 'Imágenes' };
// Las imágenes que no cargaron, por ejemplo porque venció el enlace de Instagram.
const failedImageUrls = ref(new Set());

const metrics = computed(() => props.analysis.payload.metrics);
// Con menos de dos posteos no hay frecuencia.
const frequencyLabel = computed(() => {
  const postsPerWeek = metrics.value.posts_per_week;
  return postsPerWeek === null ? 'Sin datos' : `${postsPerWeek.toLocaleString('es')} por semana`;
});
// Los que más likes tienen primero, porque muestran qué le funciona; los likes ocultos (-1) quedan al final.
const postsByLikes = computed(() => [...props.posts].sort(
  (first, second) => second.payload.raw.likesCount - first.payload.raw.likesCount,
));
const formatRows = computed(() => {
  const rows = Object.entries(metrics.value.formats).map(([format, formatMetrics]) => ({
    key: format,
    label: formatPluralNames[format],
    posts: formatMetrics.posts,
    averageLikes: formatMetrics.average_likes,
    averageComments: formatMetrics.average_comments,
  }));
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

// Apify devuelve -1 cuando la cuenta oculta los likes.
function getLikesLabel(post) {
  const likesCount = post.payload.raw.likesCount;
  return likesCount >= 0 ? formatCount(likesCount, 'like', 'likes') : 'Likes ocultos';
}

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}

function formatDate(isoDate) {
  return new Date(isoDate).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
