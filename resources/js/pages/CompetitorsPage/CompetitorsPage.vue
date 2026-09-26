<template>
  <SystemLayout>
    <div class="max-w-[1296px] space-y-8">
      <header class="flex flex-wrap items-start justify-between gap-5">
        <div>
          <h1 class="text-3xl font-medium tracking-tight">
            Competencia
          </h1>
          <p class="mt-2 text-sm text-text-muted">
            Qué hacen tus competidores, qué les funciona y dónde fallan.
          </p>
        </div>
        <button
          type="button"
          class="inline-flex min-h-11 cursor-pointer items-center gap-3 rounded-sm bg-accent px-5 py-3 text-sm font-medium text-text-on-accent transition hover:bg-accent-hover"
          @click="competitorModalStore.openToCreate()"
        >
          Sumar competidor
        </button>
      </header>

      <p
        v-if="isLoading"
        role="status"
        class="text-sm text-text-muted"
      >
        Cargando tu competencia…
      </p>
      <div
        v-if="loadError"
        role="alert"
        class="flex flex-wrap items-center gap-3 rounded-sm border border-danger p-3 text-sm text-danger"
      >
        <span>{{ loadError }}</span>
        <button
          type="button"
          class="min-h-11 cursor-pointer underline underline-offset-4"
          @click="loadCompetition"
        >
          Volver a intentar
        </button>
      </div>

      <template v-if="hasLoaded">
        <p
          v-if="!competitors.length"
          class="rounded-sm border border-dashed border-border p-8 text-center text-sm leading-6 text-text-muted"
        >
          Todavía no sumaste competidores. Suma el primero con los enlaces que tengas: su sitio web, su Instagram, su
          página de Facebook o su ficha de Google Maps.
        </p>

        <template v-else>
          <section
            class="rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
            aria-labelledby="competition-heading"
          >
            <h2
              id="competition-heading"
              class="text-lg font-medium"
            >
              Tu marca frente a la competencia
            </h2>
            <p class="mt-1 text-sm text-text-muted">
              Se actualiza automáticamente cada vez que analizamos a un competidor.
            </p>
            <dl
              v-if="hasCompetitionAnalysis"
              class="mt-5 grid gap-4 lg:grid-cols-3"
            >
              <div
                v-for="field in competitionFields"
                :key="field.key"
                class="min-w-0 rounded-sm bg-surface p-4"
              >
                <dt class="spec-label mb-2">
                  {{ field.label }}
                </dt>
                <dd
                  v-if="brand[field.key]"
                  class="whitespace-pre-wrap break-words text-sm leading-6"
                >
                  {{ brand[field.key] }}
                </dd>
                <dd
                  v-else
                  class="text-sm leading-6 text-text-muted"
                >
                  Sin información todavía
                </dd>
              </div>
            </dl>
            <p
              v-else
              class="mt-5 rounded-sm border border-dashed border-border p-6 text-center text-sm text-text-muted"
            >
              Cuando analicemos a tus competidores, acá vas a ver qué les funciona, dónde fallan y qué puedes hacer con
              eso.
            </p>
          </section>

          <section aria-labelledby="competitors-heading">
            <h2
              id="competitors-heading"
              class="spec-label mb-3"
            >
              Competidores
            </h2>
            <ul class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <li
                v-for="competitor in competitors"
                :key="competitor.id"
              >
                <RouterLink
                  :to="`/competitors/${competitor.id}`"
                  class="flex h-full flex-col gap-4 rounded-sm border border-border bg-surface-raised p-5 hover:bg-surface-selected"
                >
                  <span class="font-medium">{{ competitor.name }}</span>
                  <ul
                    v-if="getLinkedSources(competitor).length"
                    class="flex flex-wrap gap-2"
                  >
                    <li
                      v-for="source in getLinkedSources(competitor)"
                      :key="source.researchType"
                    >
                      <CompetitorSourceStateChip
                        :research-status="researchStatuses[competitor.id][source.researchType]"
                        :source-title="source.title"
                      />
                    </li>
                  </ul>
                  <span
                    v-else
                    class="text-xs text-text-muted"
                  >
                    Sin enlaces cargados
                  </span>
                </RouterLink>
              </li>
            </ul>
          </section>
        </template>
      </template>
    </div>
    <CompetitorModal @saved="goToCompetitor" />
  </SystemLayout>
</template>


<script setup>
import { ref, computed, onMounted } from 'vue';
import BrandService from '@/services/BrandService';
import { RouterLink, useRouter } from 'vue-router';
import SystemLayout from '@/layouts/SystemLayout.vue';
import CompetitorService from '@/services/CompetitorService';
import CompetitorModal from '@/components/CompetitorModal.vue';
import { useCompetitorModalStore } from '@/stores/competitorModalStore';
import CompetitorSourceStateChip from '@/components/CompetitorSourceStateChip.vue';

const router = useRouter();

const competitorModalStore = useCompetitorModalStore();

// Lo que la marca sabe de su competencia en conjunto; lo recalcula el cruce, no se edita.
const competitionFields = [
  { key: 'competitors_strengths_description', label: 'Qué les funciona' },
  { key: 'competitors_weaknesses_description', label: 'Dónde fallan' },
  { key: 'competitors_opportunities_description', label: 'Oportunidades para ti' },
];
// Las cuatro fuentes que se pueden analizar de un competidor; cada tarjeta muestra solo las que tienen enlace.
const competitorSources = [
  { researchType: 'website', field: 'website_url', title: 'Sitio web' },
  { researchType: 'instagram', field: 'instagram_username', title: 'Instagram' },
  { researchType: 'meta_ads', field: 'meta_ads_url', title: 'Anuncios' },
  { researchType: 'google_reviews', field: 'google_maps_url', title: 'Reseñas' },
];

const brand = ref(null);
const loadError = ref('');
const isLoading = ref(true);
const hasLoaded = ref(false);
const competitors = ref([]);
// El estado de las cuatro fuentes de cada competidor, por su ID y por tipo.
const researchStatuses = ref({});

const hasCompetitionAnalysis = computed(() => competitionFields.some((field) => brand.value[field.key]));

onMounted(loadCompetition);

async function loadCompetition() {
  loadError.value = '';
  isLoading.value = true;

  try {
    const [loadedBrand, competitorsWithStatuses] = await Promise.all([BrandService.find(), CompetitorService.list()]);
    brand.value = loadedBrand;
    competitors.value = competitorsWithStatuses.competitors;
    researchStatuses.value = competitorsWithStatuses.research_statuses;
    hasLoaded.value = true;
  } catch (error) {
    loadError.value = error.message;
  } finally {
    isLoading.value = false;
  }
}

function getLinkedSources(competitor) {
  return competitorSources.filter((source) => competitor[source.field]);
}

// Un competidor recién sumado se abre para analizar sus fuentes.
function goToCompetitor(competitor) {
  router.push(`/competitors/${competitor.id}`);
}
</script>
