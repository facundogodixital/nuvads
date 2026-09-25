<template>
  <div class="space-y-8">
    <section
      v-for="group in groups"
      :key="group.id"
      :aria-labelledby="`${group.id}-heading`"
    >
      <h2
        :id="`${group.id}-heading`"
        class="spec-label mb-2"
      >
        {{ group.title }}
      </h2>
      <ul class="divide-y divide-border rounded-sm border border-border bg-surface-raised">
        <li
          v-for="source in group.sources"
          :key="source.id"
        >
          <RouterLink
            :to="`/brand/sources/${source.id}`"
            class="flex min-h-16 items-center gap-4 px-4 py-3 hover:bg-surface-selected"
          >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-surface-selected">
              <svg
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
              >
                <path
                  :d="source.icon"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block font-medium">{{ source.title }}</span>
              <span class="block text-xs text-text-muted">{{ source.description }}</span>
            </span>
            <span
              v-if="analyzedSourceIds.includes(source.id)"
              class="shrink-0 rounded-sm bg-success-soft px-2 py-1 text-xs text-success"
            >
              Analizado
            </span>
            <span
              v-else
              class="shrink-0 rounded-sm bg-warning-soft px-2 py-1 text-xs text-warning"
            >
              Por completar
            </span>
          </RouterLink>
        </li>
      </ul>
    </section>
  </div>
</template>


<script setup>
import { RouterLink } from 'vue-router';
import { ref, computed, onMounted } from 'vue';
import ResearchRunService from '@/services/ResearchRunService';

const props = defineProps({
  sources: { type: Array, required: true },
});

const analyzedSourceIds = ref([]);

const groups = computed(() => [
  { id: 'links', title: 'Enlaces que analizamos', sources: props.sources.filter((source) => source.group === 'links') },
  { id: 'files', title: 'Archivos que subes', sources: props.sources.filter((source) => source.group === 'files') },
]);

onMounted(loadAnalyzedSources);

// Una fuente está analizada si alguna vez terminó bien. Si falla la consulta, todas quedan como Por completar.
async function loadAnalyzedSources() {
  try {
    const [
      websiteStatus,
      instagramStatus,
      metaAdsStatus,
      googleReviewsStatus,
      whatsAppStatus,
      audioStatus,
      uploadedFilesStatus,
    ] = await Promise.all([
      ResearchRunService.getWebsiteResearchStatus(),
      ResearchRunService.getInstagramResearchStatus(),
      ResearchRunService.getMetaAdsResearchStatus(),
      ResearchRunService.getGoogleReviewsResearchStatus(),
      ResearchRunService.getWhatsAppConversationsResearchStatus(),
      ResearchRunService.getAudioResearchStatus(),
      ResearchRunService.getUploadedFilesResearchStatus(),
    ]);
    const websiteWasAnalyzed = Boolean(websiteStatus.last_completed);
    const instagramWasAnalyzed = Boolean(instagramStatus.last_completed);
    const metaAdsWereAnalyzed = Boolean(metaAdsStatus.last_completed);
    const googleReviewsWereAnalyzed = Boolean(googleReviewsStatus.last_completed);
    const whatsAppWasAnalyzed = Boolean(whatsAppStatus.last_completed);
    const audioWasAnalyzed = Boolean(audioStatus.last_completed);
    const uploadedFilesWereAnalyzed = Boolean(uploadedFilesStatus.last_completed);

    analyzedSourceIds.value = [];
    if (websiteWasAnalyzed) {
      analyzedSourceIds.value.push('website');
    }
    if (instagramWasAnalyzed) {
      analyzedSourceIds.value.push('instagram');
    }
    if (metaAdsWereAnalyzed) {
      analyzedSourceIds.value.push('meta-ads');
    }
    if (googleReviewsWereAnalyzed) {
      analyzedSourceIds.value.push('google-maps');
    }
    if (whatsAppWasAnalyzed) {
      analyzedSourceIds.value.push('whatsapp');
    }
    if (audioWasAnalyzed) {
      analyzedSourceIds.value.push('audio');
    }
    if (uploadedFilesWereAnalyzed) {
      analyzedSourceIds.value.push('uploaded-files');
    }
  } catch {
    analyzedSourceIds.value = [];
  }
}
</script>
