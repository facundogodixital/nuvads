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

      <div class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-border px-4 py-3 text-xs leading-5 text-text-muted">
        <p><span class="font-medium text-text">Vista previa.</span> Los enlaces de tus fuentes se guardan. El resto de los datos, logo y colores siguen en vista previa.</p>
        <span>Análisis automático próximamente</span>
      </div>

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
          v-if="isLoadingSources"
          role="status"
          class="mb-4 text-sm text-text-muted"
        >
          Cargando tus enlaces…
        </p>
        <div
          v-if="sourcesError"
          role="alert"
          class="mb-4 flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
        >
          <span>{{ sourcesError }}</span>
          <button
            type="button"
            class="min-h-11 cursor-pointer underline underline-offset-4"
            @click="loadSources"
          >
            Volver a intentar
          </button>
        </div>
        <div class="grid gap-5 lg:grid-cols-3">
          <BrandResearchPanel
            v-for="source in researchSources"
            :key="source.id"
            :source="source"
            :saved-value="sources[source.field] ?? ''"
            :is-available="sourcesAreLoaded"
            @saved="sources[source.field] = $event"
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
          <span class="rounded-sm border border-border px-3 py-1.5 text-xs text-text-muted">{{ knowledge.business.name || 'Tu marca' }}</span>
        </header>
        <div class="grid items-start gap-5 xl:grid-cols-2">
          <BrandVisualIdentity class="xl:col-span-2" />
          <BrandKnowledgeSection
            v-for="section in brandSections"
            :key="section.id"
            :section="section"
            :values="knowledge[section.id]"
            :class="section.id === 'business' ? 'xl:col-span-2' : ''"
            @update="knowledge[section.id] = $event"
          />
        </div>
      </section>
    </div>
  </SystemLayout>
</template>


<script setup>
import { ref, reactive, onMounted } from 'vue';
import BrandService from '@/services/BrandService';
import SystemLayout from '@/layouts/SystemLayout.vue';
import { useSessionStore } from '@/stores/sessionStore';
import BrandResearchPanel from './BrandResearchPanel.vue';
import BrandVisualIdentity from './BrandVisualIdentity.vue';
import BrandKnowledgeSection from './BrandKnowledgeSection.vue';
import { brandSections, researchSources } from './brandSections';

const sessionStore = useSessionStore();

const sources = reactive({});
const sourcesError = ref('');
const isLoadingSources = ref(true);
const sourcesAreLoaded = ref(false);

const knowledge = reactive({
  business: { name: sessionStore.session?.brand?.name ?? '' },
  audience: {},
  identity: {},
  'customer-insights': {},
  communication: {},
});

onMounted(loadSources);

async function loadSources() {
  sourcesError.value = '';
  isLoadingSources.value = true;

  try {
    const brand = await BrandService.find();

    for (const source of researchSources) {
      sources[source.field] = brand[source.field] ?? '';
    }

    sourcesAreLoaded.value = true;
  } catch (error) {
    sourcesError.value = error.message;
  } finally {
    isLoadingSources.value = false;
  }
}
</script>
