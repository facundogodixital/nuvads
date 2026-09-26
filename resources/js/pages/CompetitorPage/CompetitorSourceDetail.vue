<template>
  <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
    <!-- En escritorio el panel acompaña el scroll, porque lo aprendido puede ser largo. -->
    <CompetitorResearchPanel
      class="lg:sticky lg:top-0"
      :source="source"
      :competitor="competitor"
      @analyzed="handleAnalyzed"
    />

    <section
      class="rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
      aria-labelledby="competitor-source-analysis-heading"
    >
      <header class="mb-5">
        <h2
          id="competitor-source-analysis-heading"
          class="text-lg font-medium"
        >
          Lo que aprendimos
        </h2>
      </header>

      <p
        v-if="isLoadingInsights"
        role="status"
        class="text-sm text-text-muted"
      >
        Cargando lo que aprendimos…
      </p>
      <p
        v-else-if="insightsError"
        role="alert"
        class="text-sm text-danger"
      >
        {{ insightsError }}
      </p>
      <p
        v-else-if="!analysis"
        class="rounded-sm border border-dashed border-border p-6 text-center text-sm text-text-muted"
      >
        {{ emptyAnalysisMessages[source.id] }}
      </p>
      <template v-else>
        <!-- El análisis se guardó igual, pero lo que sabemos del competidor no se tocó. -->
        <p
          v-if="analysis.payload.matches_competitor === false"
          role="status"
          class="mb-4 rounded-sm bg-warning-soft p-4 text-sm leading-6 text-warning"
        >
          Esta fuente no parece de {{ competitor.name }}, así que no tocamos lo que sabemos del competidor. Revisa
          el enlace.
        </p>
        <p class="rounded-sm bg-accent-soft p-4 leading-7">
          {{ getInsightText(analysis) }}
        </p>

        <!-- Las reseñas muestran sus conclusiones después de las quejas y los elogios. -->
        <section
          v-if="showsInsightsList"
          class="mt-8"
          aria-labelledby="competitor-source-insights-heading"
        >
          <h3
            id="competitor-source-insights-heading"
            class="spec-label mb-3"
          >
            Conclusiones
          </h3>
          <ul class="divide-y divide-border border-y border-border">
            <li
              v-for="insight in insights"
              :key="insight.id"
              class="py-3 text-sm leading-6"
            >
              {{ getInsightText(insight) }}
            </li>
          </ul>
        </section>

        <CompetitorInstagramAnalysis
          v-if="isInstagram"
          :analysis="analysis"
          :posts="posts"
        />
        <CompetitorMetaAdsAnalysis
          v-if="isMetaAds"
          :analysis="analysis"
          :ads="ads"
        />
        <CompetitorGoogleReviewsAnalysis
          v-if="isGoogleMaps"
          :analysis="analysis"
          :pains="pains"
          :strengths="strengths"
          :insights="insights"
          :reviews="reviews"
        />
      </template>
    </section>
  </div>
</template>


<script setup>
import { ref, computed, onMounted } from 'vue';
import CompetitorResearchPanel from './CompetitorResearchPanel.vue';
import CompetitorMetaAdsAnalysis from './CompetitorMetaAdsAnalysis.vue';
import CompetitorInsightService from '@/services/CompetitorInsightService';
import CompetitorInstagramAnalysis from './CompetitorInstagramAnalysis.vue';
import CompetitorGoogleReviewsAnalysis from './CompetitorGoogleReviewsAnalysis.vue';

const props = defineProps({
  source: { type: Object, required: true },
  competitor: { type: Object, required: true },
});

const emit = defineEmits(['analyzed']);

const emptyAnalysisMessages = {
  website: 'Cuando analicemos su sitio, acá vas a ver lo que aprendimos del competidor.',
  instagram: 'Cuando analicemos su perfil, acá vas a ver lo que aprendimos de sus posteos.',
  'meta-ads': 'Cuando analicemos su página, acá vas a ver lo que aprendimos de sus anuncios.',
  'google-maps': 'Cuando analicemos sus reseñas, acá vas a ver lo que dicen sus clientes.',
};
const insightsLoaders = {
  website: CompetitorInsightService.getWebsiteInsights,
  instagram: CompetitorInsightService.getInstagramInsights,
  'meta-ads': CompetitorInsightService.getMetaAdsInsights,
  'google-maps': CompetitorInsightService.getGoogleReviewsInsights,
};

const ads = ref([]);
const pains = ref([]);
const posts = ref([]);
const reviews = ref([]);
const insights = ref([]);
const strengths = ref([]);
const analysis = ref(null);
const insightsError = ref('');
const isLoadingInsights = ref(false);

const isInstagram = computed(() => props.source.id === 'instagram');
const isMetaAds = computed(() => props.source.id === 'meta-ads');
const isGoogleMaps = computed(() => props.source.id === 'google-maps');
const showsInsightsList = computed(() => insights.value.length > 0 && !isGoogleMaps.value);

onMounted(loadInsights);

async function loadInsights() {
  insightsError.value = '';
  isLoadingInsights.value = true;

  try {
    const sourceInsights = await insightsLoaders[props.source.id](props.competitor.id);
    analysis.value = sourceInsights.analysis;
    insights.value = sourceInsights.insights;
    // Instagram trae los posteos que leyó; los anuncios, los anuncios; y las reseñas, sus quejas, elogios y las
    // reseñas destacadas.
    posts.value = sourceInsights.posts ?? [];
    ads.value = sourceInsights.ads ?? [];
    pains.value = sourceInsights.pains ?? [];
    strengths.value = sourceInsights.strengths ?? [];
    reviews.value = sourceInsights.reviews ?? [];
  } catch (error) {
    insightsError.value = error.message;
  } finally {
    isLoadingInsights.value = false;
  }
}

function handleAnalyzed() {
  emit('analyzed');
  loadInsights();
}

// La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
function getInsightText(insight) {
  return insight.user_body ?? insight.body;
}
</script>
