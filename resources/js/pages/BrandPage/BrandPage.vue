<template>
  <SystemLayout>
    <div class="max-w-[1296px] space-y-8">
      <header class="flex flex-wrap items-start justify-between gap-5">
        <div>
          <h1 class="text-3xl font-medium tracking-tight">
            Mi marca
          </h1>
          <p class="mt-2 text-sm text-text-muted">
            Investiga, revisa y construye lo que sabemos de tu negocio.
          </p>
        </div>
        <a
          href="#research"
          class="inline-flex min-h-11 items-center gap-3 rounded-sm bg-accent px-5 py-3 text-sm font-medium text-text-on-accent transition hover:bg-accent-hover"
        >
          Investigar mi marca
          <svg
            class="h-4 w-4"
            viewBox="0 0 20 20"
            fill="none"
            aria-hidden="true"
          ><path
            d="M10 4v12m-5-5 5 5 5-5"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          /></svg>
        </a>
      </header>

      <section
        id="research"
        class="scroll-mt-6"
        aria-labelledby="research-heading"
      >
        <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2
              id="research-heading"
              class="text-xl font-medium"
            >
              Investigaciones
            </h2>
            <p class="mt-1 text-sm text-text-muted">
              Cada fuente, su análisis y sus resultados.
            </p>
          </div>
          <a
            href="#brand-knowledge"
            class="py-2 text-sm underline decoration-border underline-offset-4 hover:decoration-text"
          >Ver información de la marca</a>
        </header>
        <p
          v-if="isLoadingBrand"
          role="status"
          class="mb-4 text-sm text-text-muted"
        >
          Cargando tu marca…
        </p>
        <div
          v-if="brandError"
          role="alert"
          class="mb-4 flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
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
        <div class="grid gap-5 lg:grid-cols-3">
          <BrandResearchPanel
            v-for="source in researchSources"
            :key="source.id"
            :source="source"
            :saved-value="brand?.[source.field] ?? ''"
            :is-available="brand !== null"
            @saved="updateBrand"
            @analyzed="loadBrand"
          />
        </div>
      </section>

      <section
        id="brand-knowledge"
        class="scroll-mt-6 border-t border-border pt-8"
        aria-labelledby="knowledge-heading"
      >
        <header class="mb-5 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2
              id="knowledge-heading"
              class="text-xl font-medium"
            >
              La información de tu marca
            </h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-text-muted">
              Lo que nos cuentas y lo que aprendemos de tus fuentes. Todo se puede revisar y modificar.
            </p>
          </div>
          <span class="rounded-sm border border-border px-3 py-1.5 text-xs text-text-muted">{{ brand?.name || 'Tu marca' }}</span>
        </header>
        <div
          v-if="brand"
          class="grid items-start gap-5 xl:grid-cols-2"
        >
          <BrandVisualIdentity
            class="xl:col-span-2"
            :brand="brand"
            @saved="updateBrand"
          />
          <BrandKnowledgeSection
            v-for="section in brandSections"
            :key="section.id"
            :section="section"
            :brand="brand"
            :class="section.id === 'business' ? 'xl:col-span-2' : ''"
            @saved="updateBrand"
          />
        </div>
      </section>
      <BrandLogoModal />
    </div>
  </SystemLayout>
</template>


<script setup>
import { ref, onMounted } from 'vue';
import BrandLogoModal from './BrandLogoModal.vue';
import BrandService from '@/services/BrandService';
import SystemLayout from '@/layouts/SystemLayout.vue';
import { useSessionStore } from '@/stores/sessionStore';
import BrandResearchPanel from './BrandResearchPanel.vue';
import BrandVisualIdentity from './BrandVisualIdentity.vue';
import BrandKnowledgeSection from './BrandKnowledgeSection.vue';

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

// Por ahora solo el sitio web se puede analizar; los demás paneles guardan su enlace.
const researchSources = [
  {
    id: 'google-maps', field: 'google_maps_url', title: 'Google Maps', description: 'La voz de tus clientes',
    label: 'Enlace de tu negocio', inputType: 'url', placeholder: 'https://maps.google.com/…', isAnalyzable: false,
    resultLabel: 'Reseñas y reputación',
    icon: 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  },
  {
    id: 'instagram', field: 'instagram_username', title: 'Instagram', description: 'Tu comunicación en acción',
    label: 'Usuario o enlace del perfil', inputType: 'text', placeholder: '@tumarca', isAnalyzable: false,
    resultLabel: 'Contenido y estilo',
    icon: 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4ZM16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM17.5 6.5h.01',
  },
  {
    id: 'website', field: 'website_url', title: 'Sitio web', description: 'Tu negocio en tus palabras',
    label: 'Dirección de tu sitio', inputType: 'url', placeholder: 'https://tumarca.com', isAnalyzable: true,
    resultLabel: 'Oferta e historia',
    icon: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18M12 3c4 5 4 13 0 18-4-5-4-13 0-18Z',
  },
];

const brand = ref(null);
const brandError = ref('');
const isLoadingBrand = ref(true);

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
