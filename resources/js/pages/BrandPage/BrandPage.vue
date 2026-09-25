<template>
  <SystemLayout>
    <div class="max-w-[1296px] space-y-6">
      <header class="flex flex-wrap items-start justify-between gap-5">
        <div>
          <h1 class="text-3xl font-medium tracking-tight">
            Mi marca
          </h1>
          <p class="mt-2 text-sm text-text-muted">
            Lo que sabemos de tu negocio. Cuantas más fuentes sumes, mejor sale tu contenido.
          </p>
        </div>
        <RouterLink
          v-if="view !== 'sources'"
          to="/brand"
          class="inline-flex min-h-11 items-center gap-3 rounded-sm bg-accent px-5 py-3 text-sm font-medium text-text-on-accent transition hover:bg-accent-hover"
        >
          Sumar fuentes
        </RouterLink>
      </header>

      <nav
        class="flex gap-1 border-b border-border"
        aria-label="Secciones de tu marca"
      >
        <RouterLink
          v-for="tab in tabs"
          :key="tab.to"
          :to="tab.to"
          class="-mb-px border-b-2 px-3 py-2.5 text-sm"
          :class="tab.isActive ? 'border-text text-text' : 'border-transparent text-text-muted hover:text-text'"
          :aria-current="tab.isActive ? 'page' : undefined"
        >
          {{ tab.label }}
        </RouterLink>
      </nav>

      <p
        v-if="isLoadingBrand"
        role="status"
        class="text-sm text-text-muted"
      >
        Cargando tu marca…
      </p>
      <div
        v-if="brandError"
        role="alert"
        class="flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
      >
        <span>{{ brandError }}</span>
        <button
          type="button"
          class="min-h-11 cursor-pointer underline underline-offset-4"
          @click="loadBrand"
        >
          Volver a intentar
        </button>
      </div>

      <template v-if="brand">
        <div
          v-if="view === 'profile'"
          class="grid items-start gap-5 xl:grid-cols-2"
        >
          <BrandKnowledgeSection
            v-for="section in brandSections"
            :key="section.id"
            :section="section"
            :brand="brand"
            :class="section.id === 'business' ? 'xl:col-span-2' : ''"
            @saved="updateBrand"
          />
        </div>

        <BrandVisualIdentity
          v-if="view === 'identity'"
          :brand="brand"
          @saved="updateBrand"
        />

        <BrandSourceList
          v-if="view === 'sources'"
          :sources="researchSources"
        />

        <BrandSourceDetail
          v-if="view === 'source' && selectedSource"
          :key="selectedSource.id"
          :source="selectedSource"
          :brand="brand"
          @saved="updateBrand"
          @analyzed="loadBrand"
        />
        <p
          v-if="view === 'source' && !selectedSource"
          class="text-sm text-text-muted"
        >
          Esta fuente no existe.
          <RouterLink
            to="/brand"
            class="underline underline-offset-4"
          >
            Ver todas las fuentes
          </RouterLink>
        </p>
      </template>
      <BrandLogoModal />
      <BrandInstagramMediaModal />
      <BrandMetaAdsMediaModal />
    </div>
  </SystemLayout>
</template>


<script setup>
import { RouterLink } from 'vue-router';
import { ref, computed, onMounted } from 'vue';
import BrandLogoModal from './BrandLogoModal.vue';
import BrandSourceList from './BrandSourceList.vue';
import BrandService from '@/services/BrandService';
import BrandSourceDetail from './BrandSourceDetail.vue';
import SystemLayout from '@/layouts/SystemLayout.vue';
import { useSessionStore } from '@/stores/sessionStore';
import BrandVisualIdentity from './BrandVisualIdentity.vue';
import BrandKnowledgeSection from './BrandKnowledgeSection.vue';
import BrandMetaAdsMediaModal from './BrandMetaAdsMediaModal.vue';
import BrandInstagramMediaModal from './BrandInstagramMediaModal.vue';

const props = defineProps({
  view: { type: String, required: true },
  sourceId: { type: String, default: '' },
});

const sessionStore = useSessionStore();

// Cada sección edita un grupo de columnas de la marca. Los campos con group viven dentro de un JSON.
const brandSections = [
  {
    id: 'business',
    title: 'Tu negocio',
    description: 'Lo que haces, lo que ofreces y por qué te eligen.',
    fields: [
      { key: 'name', label: 'Nombre de la marca', type: 'text', hint: 'El nombre de tu negocio' },
      { key: 'brand_offer_description', label: 'Productos y servicios', hint: 'Qué vendes y qué servicios ofreces' },
      { key: 'brand_differentiators_description', label: 'Qué te hace diferente', hint: 'Por qué te eligen frente a otras opciones' },
      { key: 'brand_history_description', label: 'Tu historia', hint: 'Cómo empezaste y qué vale la pena contar' },
    ],
  },
  {
    id: 'audience',
    title: 'Tu público',
    description: 'A quién le hablamos y qué necesita resolver.',
    fields: [
      { key: 'brand_customers_description', label: 'Quiénes te compran', hint: 'Describe tus clientes y dónde están' },
      { key: 'brand_customers_needs_description', label: 'Qué necesitan', hint: 'Qué buscan resolver cuando te contactan' },
    ],
  },
  {
    id: 'identity',
    title: 'Estilo y voz',
    description: 'Cómo se ve tu marca y cómo suena cuando habla.',
    fields: [
      { key: 'heading', group: 'brand_fonts', label: 'Tipografía de títulos', type: 'text', hint: 'La fuente de tus títulos' },
      { key: 'body', group: 'brand_fonts', label: 'Tipografía de textos', type: 'text', hint: 'La fuente de tus textos' },
      { key: 'brand_visual_style_description', label: 'Estilo visual', hint: 'Fotos reales, fondos claros, diseños simples…' },
      { key: 'brand_tone_of_voice_description', label: 'Tu manera de hablar', hint: 'Cómo le hablas a tus clientes. Agrega alguna frase propia.' },
    ],
  },
  {
    id: 'customer-insights',
    title: 'Lo que dicen tus clientes',
    description: 'Sus palabras son una fuente de ideas para tu contenido.',
    fields: [
      { key: 'brand_customers_valued_aspects_description', label: 'Lo que más valoran', hint: 'Qué destacan en sus reseñas o conversaciones' },
      { key: 'brand_customers_faq_description', label: 'Preguntas y dudas frecuentes', hint: 'Las preguntas que respondes todos los días' },
    ],
  },
  {
    id: 'communication',
    title: 'Comunicación y oportunidades',
    description: 'Lo que ya estás contando y lo que podrías empezar a mostrar.',
    fields: [
      { key: 'brand_communication_topics_description', label: 'De qué hablas hoy', hint: 'Temas y formatos que ya publicas' },
      { key: 'brand_content_opportunities_description', label: 'Oportunidades de contenido', hint: 'Qué te gustaría contar y todavía no estás mostrando' },
    ],
  },
];

// Cada fuente tiene su página de detalle. Las que tienen field guardan un enlace en la marca, y las de inputType
// file suben un archivo con el pedido de análisis. Las fotos y los documentos tienen su propio panel, porque se
// suben de a varios y quedan a la vista. Las que no son analizables todavía no tienen backend y muestran un análisis
// de ejemplo.
const researchSources = [
  {
    id: 'website', group: 'links', field: 'website_url', title: 'Sitio web', description: 'Tu negocio en tus palabras',
    label: 'Dirección de tu sitio', inputType: 'url', placeholder: 'https://tumarca.com', isAnalyzable: true,
    resultLabel: 'Oferta e historia',
    icon: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18M12 3c4 5 4 13 0 18-4-5-4-13 0-18Z',
  },
  {
    id: 'instagram', group: 'links', field: 'instagram_username', title: 'Instagram', description: 'Tus posteos y lo que mejor funciona',
    label: 'Usuario o enlace del perfil', inputType: 'text', placeholder: '@tumarca', isAnalyzable: true,
    resultLabel: 'Contenido y estilo',
    icon: 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4ZM16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM17.5 6.5h.01',
  },
  {
    id: 'meta-ads', group: 'links', field: 'meta_ads_url', title: 'Anuncios de Instagram y Facebook', description: 'Los anuncios que corres en redes',
    label: 'Enlace de tu página de Facebook', inputType: 'url', placeholder: 'https://www.facebook.com/tumarca', isAnalyzable: true,
    resultLabel: 'Ofertas y anuncios',
    icon: 'M4 10v4h3l6 4V6L7 10H4ZM17 9a4 4 0 0 1 0 6',
  },
  {
    id: 'google-maps', group: 'links', field: 'google_maps_url', title: 'Google Maps', description: 'La voz de tus clientes en sus reseñas',
    label: 'Enlace de tu negocio', inputType: 'url', placeholder: 'https://maps.google.com/…', isAnalyzable: true,
    resultLabel: 'Reseñas y reputación',
    icon: 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  },
  {
    id: 'whatsapp', group: 'files', field: null, title: 'Chats de WhatsApp', description: 'Lo que te preguntan tus clientes',
    label: 'Archivo .zip con tus chats', inputType: 'file', isAnalyzable: true,
    resultLabel: 'Preguntas y clientes',
    icon: 'M4 20l1.5-4A8 8 0 1 1 8 18.5L4 20Z',
  },
  {
    id: 'audio', group: 'files', field: null, title: 'Audio', description: 'Cuéntanos tu negocio en dos minutos',
    icon: 'M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3ZM5 11a7 7 0 0 0 14 0M12 18v3',
  },
  {
    id: 'uploaded-files', group: 'files', field: null, title: 'Fotos y documentos', description: 'Productos, local, menú o catálogo',
    label: 'Tus fotos y documentos', isAnalyzable: true,
    resultLabel: 'Productos y estilo',
    icon: 'M4 5h16v14H4V5ZM4 15l4-4 4 4 3-3 5 5M15 9h.01',
  },
];

const brand = ref(null);
const brandError = ref('');
const isLoadingBrand = ref(true);

const tabs = computed(() => [
  { to: '/brand', label: 'Fuentes', isActive: props.view === 'sources' || props.view === 'source' },
  { to: '/brand/profile', label: 'Perfil de marca', isActive: props.view === 'profile' },
  { to: '/brand/identity', label: 'Identidad visual', isActive: props.view === 'identity' },
]);
const selectedSource = computed(() => researchSources.find((source) => source.id === props.sourceId) ?? null);

onMounted(loadBrand);

async function loadBrand() {
  brandError.value = '';
  isLoadingBrand.value = true;

  try {
    updateBrand(await BrandService.find());
  } catch (error) {
    brandError.value = error.message;
  } finally {
    isLoadingBrand.value = false;
  }
}

// El menú lateral muestra el nombre desde la sesión; se mantiene al día con lo guardado.
function updateBrand(savedBrand) {
  brand.value = savedBrand;
  sessionStore.session.brand = savedBrand;
}
</script>
