<template>
  <div class="space-y-5">
    <RouterLink
      to="/brand"
      class="inline-flex min-h-11 items-center text-sm text-text-muted hover:text-text"
    >
      ← Todas las fuentes
    </RouterLink>

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
      <BrandResearchPanel
        v-if="source.field"
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
            v-if="!isWebsite"
            class="rounded-sm border border-border px-2 py-1 text-xs text-text-muted"
          >Vista de ejemplo</span>
        </header>

        <template v-if="isWebsite">
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
            v-else-if="!websiteSummary"
            class="rounded-sm border border-dashed border-border p-6 text-center text-sm text-text-muted"
          >
            Cuando analicemos tu sitio, acá vas a ver lo que aprendimos de tu marca.
          </p>
          <template v-else>
            <p class="text-sm leading-6">
              {{ getInsightText(websiteSummary) }}
            </p>
            <ul
              v-if="websiteInsights.length"
              class="mt-5 space-y-3"
            >
              <li
                v-for="insight in websiteInsights"
                :key="insight.id"
                class="rounded-sm bg-surface p-3 text-sm leading-6"
              >
                {{ getInsightText(insight) }}
              </li>
            </ul>
          </template>
        </template>

        <dl
          v-else-if="exampleAnalysis.metrics.length"
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

        <ul
          v-if="!isWebsite"
          class="space-y-3"
        >
          <li
            v-for="finding in exampleAnalysis.findings"
            :key="finding"
            class="rounded-sm bg-surface p-3 text-sm leading-6"
          >
            {{ finding }}
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>


<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import BrandResearchPanel from './BrandResearchPanel.vue';
import KnowledgeInsightService from '@/services/KnowledgeInsightService';

const props = defineProps({
  source: { type: Object, required: true },
  brand: { type: Object, required: true },
});

const emit = defineEmits(['saved', 'analyzed']);

const insights = ref([]);
const insightsError = ref('');
const isLoadingInsights = ref(false);

// Datos de ejemplo para ver la estructura mientras se implementa el análisis real de cada fuente.
const exampleAnalyses = {
  'website': {
    metrics: [],
    findings: [
      'El análisis del sitio completa tu perfil de marca: oferta, historia y diferenciales.',
      'Lo que encontramos lo puedes revisar y editar en la pestaña Perfil de marca.',
    ],
  },
  'instagram': {
    metrics: [
      { label: 'Posteos analizados', value: '48' },
      { label: 'Formato que mejor rinde', value: 'Carrusel' },
      { label: 'Frecuencia', value: '2 por semana' },
    ],
    findings: [
      'Tono cercano, con humor suave y mucho uso de preguntas.',
      'Los posteos de antes y después tienen el doble de interacción que el resto.',
      'Nunca mostraste precios ni promociones.',
    ],
  },
  'ads': {
    metrics: [
      { label: 'Anuncios activos', value: '3' },
      { label: 'El más antiguo', value: '45 días' },
      { label: 'Formato', value: 'Video corto' },
    ],
    findings: [
      'Tus anuncios se enfocan en envíos gratis.',
      'El anuncio que más tiempo lleva activo muestra el producto en uso.',
    ],
  },
  'google-maps': {
    metrics: [
      { label: 'Calificación', value: '4,7' },
      { label: 'Reseñas', value: '212' },
      { label: 'Últimos 90 días', value: '31 reseñas' },
    ],
    findings: [
      'Lo más valorado: la atención y la rapidez.',
      '"Me resolvieron el pedido en el día" aparece de distintas formas en 18 reseñas.',
      'La queja más repetida: cuesta estacionar cerca.',
    ],
  },
  'whatsapp': {
    metrics: [
      { label: 'Chats analizados', value: '2' },
      { label: 'Mensajes', value: '640' },
    ],
    findings: [
      'Pregunta frecuente: ¿hacen envíos a domicilio?',
      'Pregunta frecuente: ¿cuánto demora un pedido personalizado?',
      'Frase tuya que se repite: "te lo dejo listo para el finde".',
    ],
  },
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

const isWebsite = computed(() => props.source.id === 'website');
const exampleAnalysis = computed(() => exampleAnalyses[props.source.id]);
// Hay un solo análisis vigente por marca; su texto es el resumen.
const websiteSummary = computed(() => insights.value.find((insight) => insight.type === 'website_brand_analysis'));
const websiteInsights = computed(() => insights.value.filter((insight) => insight.type === 'website_insight'));

onMounted(() => {
  if (isWebsite.value) {
    loadInsights();
  }
});

async function loadInsights() {
  insightsError.value = '';
  isLoadingInsights.value = true;

  try {
    insights.value = await KnowledgeInsightService.list({ types: ['website_brand_analysis', 'website_insight'] });
  } catch (error) {
    insightsError.value = error.message;
  } finally {
    isLoadingInsights.value = false;
  }
}

function handleAnalyzed() {
  emit('analyzed');
  if (isWebsite.value) {
    loadInsights();
  }
}

// La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
function getInsightText(insight) {
  return insight.user_body ?? insight.body;
}
</script>
