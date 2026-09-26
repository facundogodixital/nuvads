<template>
  <SystemLayout>
    <div class="max-w-[1296px] space-y-6">
      <RouterLink
        :to="view === 'source' ? `/competitors/${competitorId}` : '/competitors'"
        class="inline-flex min-h-11 items-center text-sm text-text-muted hover:text-text"
      >
        {{ view === 'source' ? `← ${competitor?.name ?? 'Competidor'}` : '← Competencia' }}
      </RouterLink>

      <p
        v-if="isLoadingFirstTime"
        role="status"
        class="text-sm text-text-muted"
      >
        Cargando el competidor…
      </p>
      <div
        v-if="loadError"
        role="alert"
        class="flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
      >
        <span>{{ loadError }}</span>
        <button
          v-if="!isCompetitorMissing"
          type="button"
          class="min-h-11 cursor-pointer underline underline-offset-4"
          @click="loadCompetitor"
        >
          Volver a intentar
        </button>
      </div>

      <template v-if="competitor">
        <header class="flex flex-wrap items-start justify-between gap-5">
          <h1 class="text-3xl font-medium tracking-tight">
            {{ competitor.name }}
          </h1>
          <div
            v-if="!isConfirmingDelete"
            class="flex flex-wrap gap-3"
          >
            <button
              type="button"
              class="inline-flex min-h-11 cursor-pointer items-center rounded-sm border border-border px-4 text-sm hover:bg-surface-selected"
              @click="competitorModalStore.openToEdit(competitor)"
            >
              Editar
            </button>
            <button
              type="button"
              class="inline-flex min-h-11 cursor-pointer items-center rounded-sm border border-border px-4 text-sm text-danger hover:bg-surface-selected"
              @click="isConfirmingDelete = true"
            >
              Borrar
            </button>
          </div>
          <div
            v-else
            role="alert"
            class="flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm"
          >
            <span>¿Borrar a {{ competitor.name }}? Se pierde lo que aprendimos de este competidor.</span>
            <button
              type="button"
              :disabled="isDeleting"
              class="inline-flex min-h-11 items-center rounded-sm border border-danger px-4 text-danger enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed"
              @click="deleteCompetitor"
            >
              {{ isDeleting ? 'Borrando…' : 'Sí, borrar' }}
            </button>
            <button
              type="button"
              :disabled="isDeleting"
              class="inline-flex min-h-11 items-center rounded-sm border border-border px-4 enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed"
              @click="isConfirmingDelete = false"
            >
              Cancelar
            </button>
            <span
              v-if="deleteError"
              class="text-danger"
            >{{ deleteError }}</span>
          </div>
        </header>

        <template v-if="view === 'overview'">
          <CompetitorKnowledgeSection
            :competitor="competitor"
            @saved="updateCompetitor"
          />
          <CompetitorSourceList
            :competitor="competitor"
            :sources="competitorSources"
            :research-statuses="researchStatuses"
            @add-link="openToAddLink"
          />
        </template>

        <CompetitorSourceDetail
          v-if="showsSourceDetail"
          :key="selectedSource.id"
          :source="selectedSource"
          :competitor="competitor"
          @analyzed="loadCompetitor"
        />
        <p
          v-if="showsMissingSource"
          class="text-sm text-text-muted"
        >
          Esta fuente no existe o todavía no tiene su enlace.
          <RouterLink
            :to="`/competitors/${competitorId}`"
            class="underline underline-offset-4"
          >
            Ver las fuentes del competidor
          </RouterLink>
        </p>
      </template>
    </div>
    <CompetitorModal @saved="updateCompetitor" />
  </SystemLayout>
</template>


<script setup>
import { ref, computed, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import SystemLayout from '@/layouts/SystemLayout.vue';
import CompetitorService from '@/services/CompetitorService';
import CompetitorSourceList from './CompetitorSourceList.vue';
import CompetitorModal from '@/components/CompetitorModal.vue';
import CompetitorSourceDetail from './CompetitorSourceDetail.vue';
import { useCompetitorModalStore } from '@/stores/competitorModalStore';
import CompetitorKnowledgeSection from './CompetitorKnowledgeSection.vue';

const props = defineProps({
  view: { type: String, required: true },
  competitorId: { type: String, required: true },
  sourceId: { type: String, default: '' },
});

const router = useRouter();

const competitorModalStore = useCompetitorModalStore();

// Las cuatro fuentes que se pueden analizar de un competidor. field es el enlace que guarda el competidor;
// researchType, el tipo de investigación en la API, y addLabel, el botón para cargar el enlace que falta.
const competitorSources = [
  {
    id: 'website', researchType: 'website', field: 'website_url', title: 'Sitio web',
    description: 'Lo que ofrece y cómo se presenta', addLabel: 'Sumar sitio web',
    icon: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM3 12h18M12 3c4 5 4 13 0 18-4-5-4-13 0-18Z',
  },
  {
    id: 'instagram', researchType: 'instagram', field: 'instagram_username', title: 'Instagram',
    description: 'Sus posteos y cuáles le funcionan', addLabel: 'Sumar Instagram',
    icon: 'M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4ZM16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM17.5 6.5h.01',
  },
  {
    id: 'meta-ads', researchType: 'meta_ads', field: 'meta_ads_url', title: 'Anuncios de Instagram y Facebook',
    description: 'Los anuncios que corre y cuánto los sostiene', addLabel: 'Sumar anuncios',
    icon: 'M4 10v4h3l6 4V6L7 10H4ZM17 9a4 4 0 0 1 0 6',
  },
  {
    id: 'google-maps', researchType: 'google_reviews', field: 'google_maps_url', title: 'Reseñas de Google',
    description: 'Lo que elogian y lo que le reclaman sus clientes', addLabel: 'Sumar reseñas de Google',
    icon: 'M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0ZM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
  },
];

const loadError = ref('');
const competitor = ref(null);
const isLoading = ref(true);
const isDeleting = ref(false);
const deleteError = ref('');
const isConfirmingDelete = ref(false);
// Un competidor borrado o de otra marca no se puede volver a cargar.
const isCompetitorMissing = ref(false);
// El estado de las cuatro fuentes del competidor, por tipo de investigación.
const researchStatuses = ref({});

// Solo se abre el detalle de una fuente que tiene su enlace guardado.
const selectedSource = computed(() => {
  const source = competitorSources.find((competitorSource) => competitorSource.id === props.sourceId);
  const sourceHasLink = Boolean(source && competitor.value?.[source.field]);
  return sourceHasLink ? source : null;
});
// Al recargar se sigue mostrando el competidor; el aviso de carga es solo para la primera vez.
const isLoadingFirstTime = computed(() => isLoading.value && competitor.value === null);
const isSourceView = computed(() => props.view === 'source');
const showsSourceDetail = computed(() => isSourceView.value && selectedSource.value !== null);
const showsMissingSource = computed(() => isSourceView.value && selectedSource.value === null);

// Se recarga también al cambiar de vista, así el resumen muestra el estado de un análisis pedido en el detalle.
watch(() => [props.competitorId, props.view], loadCompetitor, { immediate: true });

async function loadCompetitor() {
  loadError.value = '';
  isLoading.value = true;

  try {
    const competitorWithStatuses = await CompetitorService.find(props.competitorId);
    competitor.value = competitorWithStatuses.competitor;
    researchStatuses.value = competitorWithStatuses.research_statuses;
  } catch (error) {
    isCompetitorMissing.value = error.code === 'not_found';
    loadError.value = isCompetitorMissing.value ? 'Este competidor no existe.' : error.message;
  } finally {
    isLoading.value = false;
  }
}

function updateCompetitor(savedCompetitor) {
  competitor.value = savedCompetitor;
}

function openToAddLink(source) {
  competitorModalStore.openToEdit(competitor.value, source.field);
}

async function deleteCompetitor() {
  deleteError.value = '';
  isDeleting.value = true;

  try {
    await CompetitorService.delete(competitor.value.id);
    router.push('/competitors');
  } catch (error) {
    deleteError.value = error.message;
  } finally {
    isDeleting.value = false;
  }
}
</script>
