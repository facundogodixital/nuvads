<template>
  <div class="space-y-5">
    <RouterLink
      to="/brand"
      class="inline-flex min-h-11 items-center text-sm text-text-muted hover:text-text"
    >
      ← Todas las fuentes
    </RouterLink>

    <div class="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
      <!-- En escritorio el panel acompaña el scroll, porque lo aprendido puede ser largo. -->
      <BrandResearchPanel
        v-if="source.isAnalyzable"
        class="lg:sticky lg:top-0"
        :source="source"
        :saved-value="brand[source.field] ?? ''"
        :is-available="true"
        @saved="emit('saved', $event)"
        @analyzed="handleAnalyzed"
      />
      <section
        v-else
        class="rounded-sm border border-border bg-surface-raised p-5"
        :aria-labelledby="`${source.id}-heading`"
      >
        <h2
          :id="`${source.id}-heading`"
          class="font-medium"
        >
          {{ source.title }}
        </h2>
        <p class="mt-1 text-sm text-text-muted">
          {{ source.description }}
        </p>
        <div class="mt-5 rounded-sm border border-dashed border-border p-6 text-center text-sm text-text-muted">
          Muy pronto vas a poder cargar esta fuente.
        </div>
      </section>

      <section
        class="rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
        aria-labelledby="source-analysis-heading"
      >
        <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
          <h2
            id="source-analysis-heading"
            class="text-lg font-medium"
          >
            Lo que aprendimos
          </h2>
          <span
            v-if="!source.isAnalyzable"
            class="rounded-sm border border-border px-2 py-1 text-xs text-text-muted"
          >Vista de ejemplo</span>
        </header>

        <template v-if="source.isAnalyzable">
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
            {{ emptyAnalysisMessage }}
          </p>
          <template v-else>
            <!-- El análisis se guardó igual, pero el perfil de la marca no se tocó. -->
            <p
              v-if="analysis.payload?.matches_brand === false"
              role="status"
              class="mb-4 rounded-sm bg-warning-soft p-4 text-sm leading-6 text-warning"
            >
              Esta fuente no parece de {{ brand.name }}, así que no tocamos tu perfil de marca. Revisa que hayas elegido la
              marca correcta.
            </p>
            <p class="rounded-sm bg-accent-soft p-4 leading-7">
              {{ getInsightText(analysis) }}
            </p>

            <!-- Las reseñas de Google y los chats de WhatsApp muestran sus conclusiones junto a lo que las respalda. -->
            <section
              v-if="insights.length && !isGoogleMaps && !isWhatsApp"
              class="mt-8"
              aria-labelledby="source-insights-heading"
            >
              <h3
                id="source-insights-heading"
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

            <BrandInstagramAnalysis
              v-if="isInstagram"
              :analysis="analysis"
              :posts="posts"
            />
            <!-- Sin anuncios, el resumen ya lo dice y no hay números ni anuncios que mostrar. -->
            <BrandMetaAdsAnalysis
              v-if="isMetaAds && ads.length"
              :analysis="analysis"
              :ads="ads"
            />
            <!-- Sin reseñas, el resumen ya lo dice. -->
            <BrandGoogleReviewsAnalysis
              v-if="isGoogleMaps && metricsInsight?.payload.reviews_count"
              :analysis="analysis"
              :metrics-insight="metricsInsight"
              :pains="pains"
              :strengths="strengths"
              :insights="insights"
              :reviews="reviews"
            />
            <!-- Sin chats de clientes, el resumen ya lo dice. -->
            <BrandWhatsAppConversationsAnalysis
              v-if="isWhatsApp && metricsInsight?.payload.contact_kinds.customer"
              :analysis="analysis"
              :metrics-insight="metricsInsight"
              :questions="questions"
              :objections="objections"
              :insights="insights"
              :conversations="conversations"
            />
          </template>
        </template>

        <template v-else>
          <dl
            v-if="exampleAnalysis.metrics.length"
            class="mb-5 grid gap-3 sm:grid-cols-3"
          >
            <div
              v-for="metric in exampleAnalysis.metrics"
              :key="metric.label"
              class="rounded-sm bg-surface p-3"
            >
              <dt class="text-xs text-text-muted">
                {{ metric.label }}
              </dt>
              <dd class="mt-1 text-lg font-medium">
                {{ metric.value }}
              </dd>
            </div>
          </dl>
          <ul class="space-y-3">
            <li
              v-for="finding in exampleAnalysis.findings"
              :key="finding"
              class="rounded-sm bg-surface p-3 text-sm leading-6"
            >
              {{ finding }}
            </li>
          </ul>
        </template>
      </section>
    </div>
  </div>
</template>


<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import BrandResearchPanel from './BrandResearchPanel.vue';
import BrandMetaAdsAnalysis from './BrandMetaAdsAnalysis.vue';
import BrandInstagramAnalysis from './BrandInstagramAnalysis.vue';
import BrandGoogleReviewsAnalysis from './BrandGoogleReviewsAnalysis.vue';
import BrandWhatsAppConversationsAnalysis from './BrandWhatsAppConversationsAnalysis.vue';
import KnowledgeInsightService from '@/services/KnowledgeInsightService';

const props = defineProps({
  source: { type: Object, required: true },
  brand: { type: Object, required: true },
});

const emit = defineEmits(['saved', 'analyzed']);

const ads = ref([]);
const pains = ref([]);
const posts = ref([]);
const reviews = ref([]);
const insights = ref([]);
const questions = ref([]);
const strengths = ref([]);
const objections = ref([]);
const conversations = ref([]);
const metricsInsight = ref(null);
const analysis = ref(null);
const insightsError = ref('');
const isLoadingInsights = ref(false);

// Datos de ejemplo para ver la estructura de las fuentes que todavía no tienen análisis real.
const exampleAnalyses = {
  'audio': {
    metrics: [],
    findings: [
      'Del audio sacamos tu historia, cómo trabajas y lo que te hace distinto.',
    ],
  },
  'files': {
    metrics: [
      { label: 'Fotos', value: '24' },
      { label: 'Documentos', value: '1' },
    ],
    findings: [
      'Tus fotos de producto son sobre fondo claro y con luz natural.',
      'El catálogo tiene 36 productos en 5 categorías.',
    ],
  },
};

const emptyAnalysisMessages = {
  website: 'Cuando analicemos tu sitio, acá vas a ver lo que aprendimos de tu marca.',
  instagram: 'Cuando analicemos tu perfil, acá vas a ver lo que aprendimos de tus posteos.',
  'meta-ads': 'Cuando analicemos tu página, acá vas a ver lo que aprendimos de tus anuncios.',
  'google-maps': 'Cuando analicemos tus reseñas, acá vas a ver lo que dicen tus clientes.',
  whatsapp: 'Cuando analicemos tus chats, acá vas a ver lo que te preguntan tus clientes.',
};
const insightsLoaders = {
  website: KnowledgeInsightService.getWebsiteInsights,
  instagram: KnowledgeInsightService.getInstagramInsights,
  'meta-ads': KnowledgeInsightService.getMetaAdsInsights,
  'google-maps': KnowledgeInsightService.getGoogleReviewsInsights,
  whatsapp: KnowledgeInsightService.getWhatsAppConversationsInsights,
};

const isInstagram = computed(() => props.source.id === 'instagram');
const isMetaAds = computed(() => props.source.id === 'meta-ads');
const isGoogleMaps = computed(() => props.source.id === 'google-maps');
const isWhatsApp = computed(() => props.source.id === 'whatsapp');
const exampleAnalysis = computed(() => exampleAnalyses[props.source.id]);
const emptyAnalysisMessage = computed(() => emptyAnalysisMessages[props.source.id]);

onMounted(() => {
  if (props.source.isAnalyzable) {
    loadInsights();
  }
});

async function loadInsights() {
  insightsError.value = '';
  isLoadingInsights.value = true;

  try {
    const sourceInsights = await insightsLoaders[props.source.id]();
    analysis.value = sourceInsights.analysis;
    insights.value = sourceInsights.insights;
    // Instagram trae los posteos que leyó; los anuncios de Meta, los anuncios; las reseñas de Google, sus métricas,
    // quejas, fortalezas y las reseñas destacadas; y los chats de WhatsApp, sus métricas, preguntas, frenos y las
    // conversaciones destacadas.
    posts.value = sourceInsights.posts ?? [];
    ads.value = sourceInsights.ads ?? [];
    pains.value = sourceInsights.pains ?? [];
    reviews.value = sourceInsights.reviews ?? [];
    strengths.value = sourceInsights.strengths ?? [];
    metricsInsight.value = sourceInsights.metrics ?? null;
    questions.value = sourceInsights.questions ?? [];
    objections.value = sourceInsights.objections ?? [];
    conversations.value = sourceInsights.conversations ?? [];
  } catch (error) {
    insightsError.value = error.message;
  } finally {
    isLoadingInsights.value = false;
  }
}

function handleAnalyzed() {
  emit('analyzed');
  if (props.source.isAnalyzable) {
    loadInsights();
  }
}

// La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
function getInsightText(insight) {
  return insight.user_body ?? insight.body;
}
</script>
