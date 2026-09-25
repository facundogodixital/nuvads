<template>
  <section
    v-if="updatedProfileFields.length"
    class="flex gap-3 rounded-sm bg-success-soft p-4"
    aria-labelledby="updated-profile-fields-heading"
  >
    <svg
      class="mt-0.5 h-5 w-5 shrink-0 text-success"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <path
        d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM8 12.5l2.5 2.5L16 9.5"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
    </svg>
    <div class="min-w-0 flex-1">
      <h3
        id="updated-profile-fields-heading"
        class="text-sm font-medium text-success"
      >
        Actualizamos tu perfil de marca
      </h3>
      <p class="mt-1 text-sm leading-6">
        {{ description }} Revísalas y corrige lo que quieras.
      </p>
      <ul class="mt-3 flex flex-wrap gap-2">
        <li
          v-for="field in updatedProfileFields"
          :key="field"
          class="rounded-sm bg-surface-raised px-2 py-1 text-xs"
        >
          {{ field }}
        </li>
      </ul>
      <RouterLink
        to="/brand/profile"
        class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-success underline underline-offset-4 hover:text-text"
      >
        Ir a Perfil de marca
        <svg
          class="h-4 w-4"
          viewBox="0 0 20 20"
          fill="none"
          aria-hidden="true"
        ><path
          d="M4 10h12m-5-5 5 5-5 5"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        /></svg>
      </RouterLink>
    </div>
  </section>
</template>


<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';

const props = defineProps({
  analysis: { type: Object, required: true },
  // Qué se sumó al perfil, por ejemplo 'Sumamos lo que vimos en tus posteos a estas partes.'
  description: { type: String, required: true },
});

// Mismos nombres y orden que en Perfil de marca, para que el usuario los reconozca.
const profileFieldNames = {
  brand_offer_description: 'Productos y servicios',
  brand_differentiators_description: 'Qué te hace diferente',
  brand_history_description: 'Tu historia',
  brand_customers_description: 'Quiénes te compran',
  brand_customers_needs_description: 'Qué necesitan',
  brand_visual_style_description: 'Estilo visual',
  brand_tone_of_voice_description: 'Tu manera de hablar',
  brand_customers_valued_aspects_description: 'Lo que más valoran',
  brand_customers_faq_description: 'Preguntas y dudas frecuentes',
  brand_communication_topics_description: 'De qué hablas hoy',
  brand_content_opportunities_description: 'Oportunidades de contenido',
};

// Los campos que el modelo devolvió con texto ya quedaron guardados en la marca; los que no cambió vienen vacíos.
const updatedProfileFields = computed(() => {
  // Si la fuente es de otro negocio, no se guardó nada en la marca.
  const isFromAnotherBusiness = props.analysis.payload.matches_brand === false;
  if (isFromAnotherBusiness) {
    return [];
  }
  const mergedBrandFields = props.analysis.payload.brand ?? {};
  return Object.keys(profileFieldNames)
    .filter((field) => mergedBrandFields[field]?.trim())
    .map((field) => profileFieldNames[field]);
});
</script>
