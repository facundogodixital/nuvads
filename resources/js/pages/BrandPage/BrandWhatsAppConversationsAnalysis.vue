<template>
  <div class="mt-8 space-y-10">
    <section
      v-if="updatedProfileFields.length"
      class="flex gap-3 rounded-sm bg-success-soft p-4"
      aria-labelledby="whatsapp-profile-heading"
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
          id="whatsapp-profile-heading"
          class="text-sm font-medium text-success"
        >
          Actualizamos tu perfil de marca
        </h3>
        <p class="mt-1 text-sm leading-6">
          Sumamos lo que te preguntan tus clientes a estas partes. Revísalas y corrige lo que quieras.
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

    <section
      v-if="insights.length"
      aria-labelledby="whatsapp-insights-heading"
    >
      <h3
        id="whatsapp-insights-heading"
        class="spec-label mb-3"
      >
        Conclusiones
      </h3>
      <ul class="divide-y divide-border border-y border-border">
        <li
          v-for="insight in insightsWithConversations"
          :key="insight.id"
          class="py-4"
        >
          <p class="max-w-prose text-sm leading-6">
            {{ insight.text }}
          </p>
          <details
            v-if="insight.conversations.length"
            class="group mt-2"
          >
            <summary class="inline-flex min-h-8 cursor-pointer list-none items-center gap-1 text-xs font-medium text-text-muted hover:text-text">
              <svg
                class="h-3.5 w-3.5 transition-transform duration-200 group-open:rotate-90"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
              >
                <path
                  d="m9 6 6 6-6 6"
                  stroke="currentColor"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
              {{ formatCount(insight.conversations.length, 'chat que lo respalda', 'chats que lo respaldan') }}
            </summary>
            <ul class="mt-1 divide-y divide-border pl-5">
              <li
                v-for="conversation in insight.conversations"
                :key="conversation.id"
              >
                <BrandWhatsAppConversationQuote :conversation="conversation" />
              </li>
            </ul>
          </details>
        </li>
      </ul>
    </section>

    <!-- Las preguntas y los frenos se muestran igual: lo que se repite a la vista y el resto plegado. -->
    <section
      v-for="topicSection in topicSections"
      :key="topicSection.key"
      :aria-labelledby="topicSection.headingId"
    >
      <h3
        :id="topicSection.headingId"
        class="spec-label mb-1"
      >
        {{ topicSection.title }}
      </h3>
      <p class="mb-3 text-sm text-text-muted">
        {{ topicSection.description }}
      </p>
      <template v-if="topicSection.topics.length">
        <ul class="divide-y divide-border border-y border-border">
          <BrandWhatsAppConversationsTopic
            v-for="topic in getShownTopics(topicSection)"
            :key="topic.id"
            :topic="topic"
            :kind="topicSection.kind"
            :conversations-by-id="conversationsById"
            :customer-conversations-count="customerConversationsCount"
            :max-mentions-count="getMaxMentionsCount(topicSection.topics)"
          />
        </ul>
        <button
          v-if="topicSection.foldedCount"
          type="button"
          class="mt-2 inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-text-muted underline underline-offset-4 hover:text-text"
          :aria-expanded="isListExpanded(topicSection.key)"
          @click="toggleList(topicSection.key)"
        >
          {{ getFoldButtonLabel(topicSection) }}
        </button>
      </template>
      <p
        v-else
        class="rounded-sm border border-dashed border-border p-6 text-center text-sm text-text-muted"
      >
        {{ topicSection.emptyMessage }}
      </p>
    </section>

    <section
      v-if="supportingGroups.length"
      aria-labelledby="whatsapp-supporting-heading"
    >
      <h3
        id="whatsapp-supporting-heading"
        class="spec-label mb-3"
      >
        Qué más cuentan tus clientes
      </h3>
      <div class="grid gap-6 sm:grid-cols-2">
        <div
          v-for="supportingGroup in supportingGroups"
          :key="supportingGroup.key"
          :class="supportingGroup.isSentenceList ? 'sm:col-span-2' : ''"
        >
          <h4 class="text-sm font-medium">
            {{ supportingGroup.title }}
          </h4>
          <!-- Para qué lo quieren y cómo lo dicen son oraciones: van en lista y no en chips. -->
          <ul :class="supportingGroup.isSentenceList ? 'mt-2 space-y-1.5' : 'mt-2 flex flex-wrap gap-2'">
            <li
              v-for="supportingTopic in getShownTopics(supportingGroup)"
              :key="supportingTopic.topic"
              :class="supportingGroup.isSentenceList ? 'max-w-prose text-sm leading-6' : 'rounded-sm border border-border px-2 py-1 text-sm'"
              :title="formatCount(supportingTopic.mentions_count, 'chat lo menciona', 'chats lo mencionan')"
            >
              {{ formatSupportingTopic(supportingGroup.key, supportingTopic.topic) }}
              <span class="text-text-muted tabular-nums">· {{ supportingTopic.mentions_count.toLocaleString('es') }}</span>
            </li>
          </ul>
          <button
            v-if="supportingGroup.foldedCount"
            type="button"
            class="mt-1 inline-flex min-h-11 cursor-pointer items-center text-sm font-medium text-text-muted underline underline-offset-4 hover:text-text"
            :aria-expanded="isListExpanded(supportingGroup.key)"
            @click="toggleList(supportingGroup.key)"
          >
            {{ getFoldButtonLabel(supportingGroup) }}
          </button>
        </div>
      </div>
    </section>

    <section aria-labelledby="whatsapp-metrics-heading">
      <h3
        id="whatsapp-metrics-heading"
        class="spec-label mb-3"
      >
        Tus chats
      </h3>
      <dl class="grid border-y border-border sm:grid-cols-3">
        <div
          v-for="(tile, index) in numberTiles"
          :key="tile.label"
          class="py-3 sm:px-4 sm:first:pl-0"
          :class="index > 0 ? 'border-t border-border sm:border-t-0 sm:border-l' : ''"
        >
          <dt class="text-xs text-text-muted">
            {{ tile.label }}
          </dt>
          <dd class="mt-1 text-lg font-medium tabular-nums">
            {{ tile.value }}
          </dd>
          <dd
            v-if="tile.detail"
            class="text-xs text-text-muted"
          >
            {{ tile.detail }}
          </dd>
        </div>
      </dl>
      <p class="mt-3 max-w-prose text-sm leading-6 text-text-muted">
        {{ readConversationsDescription }}
        <template v-if="metrics.voice_notes_share > 0">
          El {{ formatShare(metrics.voice_notes_share) }} de los mensajes son audios, que todavía no podemos escuchar.
        </template>
      </p>
    </section>

    <section aria-labelledby="whatsapp-schedule-heading">
      <h3
        id="whatsapp-schedule-heading"
        class="spec-label mb-1"
      >
        Cuándo te escriben
      </h3>
      <p class="mb-3 text-sm text-text-muted">
        Los mensajes de tus clientes, en la hora de tu teléfono. Te ayuda a elegir cuándo publicar.
      </p>
      <div class="grid gap-8 sm:grid-cols-2">
        <div
          v-for="chart in scheduleCharts"
          :key="chart.title"
        >
          <h4 class="text-sm font-medium">
            {{ chart.title }}
          </h4>
          <!-- Una barra por hora o por día; el título de cada una da el número exacto. -->
          <ol
            class="mt-3 flex h-20 items-end gap-0.5"
            aria-hidden="true"
          >
            <li
              v-for="bar in chart.bars"
              :key="bar.key"
              class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
              :title="bar.title"
            >
              <span
                class="w-full rounded-t-sm"
                :class="bar.count ? 'bg-text' : 'bg-border'"
                :style="{ height: bar.height }"
              />
              <span class="h-4 text-xs text-text-muted">{{ bar.label }}</span>
            </li>
          </ol>
          <p class="sr-only">
            {{ chart.description }}
          </p>
        </div>
      </div>
    </section>

    <section
      v-if="analysis.payload.owner_voice"
      aria-labelledby="whatsapp-voice-heading"
    >
      <h3
        id="whatsapp-voice-heading"
        class="spec-label mb-3"
      >
        Cómo les escribes
      </h3>
      <p class="max-w-prose text-sm leading-6">
        {{ analysis.payload.owner_voice }}
      </p>
      <p
        v-if="metrics.owner_top_emojis.length"
        class="mt-3 text-sm text-text-muted"
      >
        Tus emojis más usados:
        <span class="ml-1 text-lg tracking-widest">{{ metrics.owner_top_emojis.join(' ') }}</span>
      </p>
    </section>
  </div>
</template>


<script setup>
import { ref, computed } from 'vue';
import { RouterLink } from 'vue-router';
import BrandWhatsAppConversationQuote from './BrandWhatsAppConversationQuote.vue';
import BrandWhatsAppConversationsTopic from './BrandWhatsAppConversationsTopic.vue';

const props = defineProps({
  analysis: { type: Object, required: true },
  metricsInsight: { type: Object, required: true },
  questions: { type: Array, required: true },
  objections: { type: Array, required: true },
  insights: { type: Array, required: true },
  conversations: { type: Array, required: true },
});

// Mismos nombres que en Perfil de marca, para que el usuario los reconozca.
const profileFieldNames = {
  brand_customers_description: 'Quiénes te compran',
  brand_customers_needs_description: 'Qué necesitan',
  brand_customers_faq_description: 'Preguntas y dudas frecuentes',
  brand_content_opportunities_description: 'Oportunidades de contenido',
};
const supportingGroupTitles = {
  products: 'Lo que piden',
  purposes: 'Para qué lo quieren',
  acquisition: 'Cómo te conocieron',
  customer_phrases: 'Cómo lo dicen',
};
// Son oraciones: se muestran en lista y no en chips.
const SENTENCE_GROUP_KEYS = ['purposes', 'customer_phrases'];
// Se ve lo que aparece en dos o más chats, y al menos cinco temas, para que una lista sin repetidos no quede vacía.
const MIN_VISIBLE_TOPICS = 5;
const WEEKDAY_LABELS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
// Debajo de las 24 barras de horas solo entran algunas marcas.
const LABELED_HOURS = [0, 6, 12, 18];

// Las listas plegadas que el usuario abrió, por su key.
const expandedListKeys = ref([]);

const metrics = computed(() => props.metricsInsight.payload);
const customerConversationsCount = computed(() => metrics.value.contact_kinds.customer);
const conversationsById = computed(() => Object.fromEntries(
  props.conversations.map((conversation) => [conversation.id, conversation]),
));
// Los campos que el modelo devolvió con texto ya quedaron guardados en la marca.
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
const numberTiles = computed(() => {
  const tiles = [
    {
      label: 'Chats de clientes',
      value: customerConversationsCount.value.toLocaleString('es'),
      detail: `de ${formatCount(metrics.value.analyzed_conversations_count, 'chat leído', 'chats leídos')}`,
    },
  ];
  const medianOwnerResponseMinutes = metrics.value.median_owner_response_minutes;
  if (medianOwnerResponseMinutes !== null) {
    tiles.push({
      label: 'Tardas en responder',
      value: formatMinutes(medianOwnerResponseMinutes),
      detail: 'en una respuesta típica',
    });
  }
  const answeredWithinFiveMinutesShare = metrics.value.answered_within_5_minutes_share;
  if (answeredWithinFiveMinutesShare !== null) {
    tiles.push({
      label: 'Respondes en 5 minutos o menos',
      value: formatShare(answeredWithinFiveMinutesShare),
      detail: 'de tus respuestas',
    });
  }
  return tiles;
});
// Cuenta qué se leyó y qué quedó afuera, para que los números de arriba se entiendan.
const readConversationsDescription = computed(() => {
  const contactKinds = metrics.value.contact_kinds;
  const discardedCount = contactKinds.supplier + contactKinds.personal + contactKinds.other;
  const fileDescription = `Tu archivo traía ${formatCount(metrics.value.conversations_count, 'chat', 'chats')}.`;
  const readDescription = `Leímos ${formatCount(metrics.value.analyzed_conversations_count, 'chat', 'chats')} donde tu contacto escribió algo`;
  if (discardedCount === 0) {
    return `${fileDescription} ${readDescription}, y todos son de clientes.`;
  }
  return `${fileDescription} ${readDescription}, y dejamos afuera ${formatCount(discardedCount, 'que no es de un cliente', 'que no son de clientes')}.`;
});
const scheduleCharts = computed(() => [
  {
    title: 'Por hora del día',
    ...getScheduleBars(metrics.value.customer_messages_by_hour, (hour) => ({
      label: LABELED_HOURS.includes(hour) ? `${hour} h` : '',
      name: `De ${hour} a ${hour + 1} h`,
    })),
  },
  {
    title: 'Por día de la semana',
    ...getScheduleBars(metrics.value.customer_messages_by_weekday, (weekdayIndex) => ({
      label: WEEKDAY_LABELS[weekdayIndex],
      name: WEEKDAY_LABELS[weekdayIndex],
    })),
  },
]);
const insightsWithConversations = computed(() => props.insights.map((insight) => ({
  id: insight.id,
  // La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
  text: insight.user_body ?? insight.body,
  conversations: (insight.payload?.highlight_ids ?? [])
    .map((knowledgeSourceId) => conversationsById.value[knowledgeSourceId])
    .filter(Boolean),
})));
// Cada lista plegable trae key, topics, topicsShownWhenFolded y foldedCount, y el texto de su botón en
// foldedSingular y foldedPlural.
const topicSections = computed(() => [
  {
    key: 'questions',
    kind: 'question',
    headingId: 'whatsapp-questions-heading',
    title: 'Lo que te preguntan',
    description: 'Ordenado por cuántos chats lo preguntan. Cada una es una idea de contenido: toca una para ver qué respondes.',
    emptyMessage: 'No encontramos preguntas en tus chats con clientes.',
    foldedSingular: 'pregunta más',
    foldedPlural: 'preguntas más',
    ...buildFoldableList(props.questions, (question) => question.payload.mentions_count),
  },
  {
    key: 'objections',
    kind: 'objection',
    headingId: 'whatsapp-objections-heading',
    title: 'Lo que frena la compra',
    description: 'Lo que tienes que explicar una y otra vez. Contarlo en tus redes te ahorra repetirlo.',
    emptyMessage: 'No encontramos dudas que frenen la compra en tus chats.',
    foldedSingular: 'freno más',
    foldedPlural: 'frenos más',
    ...buildFoldableList(props.objections, (objection) => objection.payload.mentions_count),
  },
]);
const supportingGroups = computed(() => Object.entries(supportingGroupTitles)
  .map(([key, title]) => ({
    key,
    title,
    isSentenceList: SENTENCE_GROUP_KEYS.includes(key),
    foldedSingular: 'más',
    foldedPlural: 'más',
    ...buildFoldableList(props.analysis.payload[key] ?? [], (supportingTopic) => supportingTopic.mentions_count),
  }))
  .filter((supportingGroup) => supportingGroup.topics.length));

// Las alturas son relativas a la barra más alta; una barra sin mensajes queda como una base fina. getNames recibe el
// índice y devuelve el rótulo visible (label) y el nombre completo para el título y la descripción (name).
function getScheduleBars(counts, getNames) {
  const maxCount = Math.max(0, ...counts);
  const bars = counts.map((count, index) => {
    const { label, name } = getNames(index);
    return {
      key: index,
      count,
      label,
      height: count ? `max(4px, ${count / maxCount * 100}%)` : '2px',
      title: `${name}: ${formatCount(count, 'mensaje', 'mensajes')}`,
    };
  });

  return { bars, description: bars.map((bar) => bar.title).join('; ') };
}

// Los temas llegan de más a menos mencionados. Devuelve topics, topicsShownWhenFolded (lo que se repite, y al menos
// MIN_VISIBLE_TOPICS) y foldedCount, cuántos quedan plegados.
function buildFoldableList(topics, getMentionsCount) {
  const repeatedTopicsCount = topics.filter((topic) => getMentionsCount(topic) > 1).length;
  const topicsShownWhenFolded = topics.slice(0, Math.max(repeatedTopicsCount, MIN_VISIBLE_TOPICS));
  return { topics, topicsShownWhenFolded, foldedCount: topics.length - topicsShownWhenFolded.length };
}

function isListExpanded(listKey) {
  return expandedListKeys.value.includes(listKey);
}

function toggleList(listKey) {
  if (isListExpanded(listKey)) {
    expandedListKeys.value = expandedListKeys.value.filter((expandedListKey) => expandedListKey !== listKey);
    return;
  }
  expandedListKeys.value = [...expandedListKeys.value, listKey];
}

function getShownTopics(list) {
  return isListExpanded(list.key) ? list.topics : list.topicsShownWhenFolded;
}

// Lo plegado aparece siempre en un solo chat, porque lo repetido queda a la vista.
function getFoldButtonLabel(list) {
  if (isListExpanded(list.key)) {
    return 'Ver menos';
  }
  return `Ver ${formatCount(list.foldedCount, list.foldedSingular, list.foldedPlural)} de un solo chat`;
}

// Las frases de los clientes van entre comillas; el modelo a veces ya las trae con las suyas.
function formatSupportingTopic(supportingGroupKey, topic) {
  const isCustomerPhrase = supportingGroupKey === 'customer_phrases';
  if (!isCustomerPhrase) {
    return topic;
  }
  const phrase = topic.replace(/^["'“”«»]+|["'“”«»]+$/g, '');
  return `“${phrase}”`;
}

function getMaxMentionsCount(topics) {
  return Math.max(1, ...topics.map((topic) => topic.payload.mentions_count));
}

function formatMinutes(minutes) {
  if (minutes < 60) {
    return `${Math.round(minutes)} min`;
  }
  if (minutes < 60 * 24) {
    return `${(minutes / 60).toLocaleString('es', { maximumFractionDigits: 1 })} h`;
  }
  return formatCount(Math.round(minutes / 60 / 24), 'día', 'días');
}

function formatShare(share) {
  return share.toLocaleString('es', { style: 'percent', maximumFractionDigits: 0 });
}

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}
</script>


<style scoped>
/* Safari dibuja su propio triángulo en summary; la flecha ya la pone el componente. */
summary::-webkit-details-marker {
  display: none;
}
</style>
