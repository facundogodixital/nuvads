<template>
  <section
    id="visual-identity"
    class="scroll-mt-6 rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
    aria-labelledby="visual-identity-heading"
  >
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h2
          id="visual-identity-heading"
          class="text-lg font-medium"
        >
          Tu identidad visual
        </h2>
        <p class="mt-1 text-sm text-text-muted">
          Los elementos que hacen reconocible a tu marca.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <span
          role="status"
          class="text-xs"
          :class="saveError ? 'text-danger' : 'text-text-muted'"
        >{{ saveError || saveMessage }}</span>
        <button
          v-if="hasChanges"
          type="button"
          :disabled="isSaving"
          class="min-h-11 shrink-0 rounded-sm border border-border px-3 text-sm enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
          @click="discardChanges"
        >
          Descartar
        </button>
        <button
          type="button"
          :disabled="!hasChanges || isSaving"
          class="min-h-11 shrink-0 rounded-sm border border-border px-3 text-sm font-medium enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
          @click="save"
        >
          {{ isSaving ? 'Guardando…' : 'Guardar' }}
        </button>
      </div>
    </header>
    <div class="grid gap-8 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
      <div>
        <p class="spec-label mb-3">
          Logos
        </p>
        <ul
          v-if="logos.length"
          class="flex flex-wrap gap-3"
        >
          <li
            v-for="(logo, index) in logos"
            :key="logo"
            class="w-32"
          >
            <button
              type="button"
              :aria-label="`Ver logo ${index + 1} en grande`"
              class="flex h-24 w-full cursor-pointer items-center justify-center overflow-hidden rounded-sm border border-border bg-surface p-2 hover:border-text"
              @click="brandLogoModalStore.open(logo)"
            >
              <img
                :src="logo"
                :alt="`Logo ${index + 1} de tu marca`"
                class="max-h-full max-w-full object-contain"
              >
            </button>
            <div
              v-if="logoPendingRemoval === index"
              class="flex min-h-11 items-center justify-center gap-2 text-xs"
            >
              <span>¿Estás seguro?</span>
              <button
                type="button"
                class="cursor-pointer font-medium text-danger"
                @click="removeLogo(index)"
              >
                Sí, quitar
              </button>
              <button
                type="button"
                class="cursor-pointer text-text-muted hover:text-text"
                @click="logoPendingRemoval = null"
              >
                No
              </button>
            </div>
            <button
              v-else
              type="button"
              :aria-label="`Quitar logo ${index + 1}`"
              class="min-h-11 w-full cursor-pointer text-xs text-text-muted hover:text-text"
              @click="logoPendingRemoval = index"
            >
              Quitar
            </button>
          </li>
        </ul>
        <p
          v-else
          class="text-sm leading-6 text-text-muted"
        >
          Cuando analicemos tu sitio, tus logos aparecerán aquí.
        </p>
      </div>
      <div>
        <p class="spec-label mb-3">
          Paleta de colores
        </p>
        <div class="flex flex-wrap gap-4">
          <div
            v-for="role in colorRoles"
            :key="role.key"
            class="relative w-28"
            :data-color-slot="role.key"
          >
            <button
              type="button"
              class="flex h-16 w-full cursor-pointer items-center justify-center rounded-sm border border-border"
              :class="colors[role.key] ? '' : 'border-dashed bg-surface hover:bg-surface-selected'"
              :style="{ backgroundColor: colors[role.key] ?? undefined }"
              :aria-label="`${role.label}: ${colors[role.key] ?? 'sin elegir'}`"
              :aria-expanded="openColorKey === role.key"
              @click="toggleColorPicker(role.key)"
            >
              <span
                v-if="!colors[role.key]"
                class="text-xl text-text-muted"
                aria-hidden="true"
              >+</span>
            </button>
            <p class="spec-label mt-2 text-center">
              {{ role.label }}
            </p>
            <p class="mt-0.5 text-center text-[11px] leading-4 text-text-muted">
              {{ role.hint }}
            </p>
            <BrandColorPicker
              v-if="openColorKey === role.key"
              v-model="colors[role.key]"
              :label="role.label"
              class="absolute left-0 top-full z-10 mt-2"
              @close="openColorKey = null"
            />
          </div>
        </div>
      </div>
    </div>
  </section>
</template>


<script setup>
import BrandService from '@/services/BrandService';
import BrandColorPicker from './BrandColorPicker.vue';
import { useBrandLogoModalStore } from '@/stores/brandLogoModalStore';
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  brand: { type: Object, required: true },
});

const emit = defineEmits(['saved']);

const brandLogoModalStore = useBrandLogoModalStore();

// El orden es el de la fila en pantalla; hint es la leyenda debajo de cada casillero.
const colorRoles = [
  { key: 'primary', label: 'Principal', hint: 'Logo y títulos' },
  { key: 'secondary', label: 'Secundario', hint: 'Acompaña al principal' },
  { key: 'background', label: 'Fondo', hint: 'La hoja donde va todo' },
  { key: 'text', label: 'Texto', hint: 'Las letras' },
  { key: 'accent', label: 'Destacados', hint: 'Botones, enlaces y ofertas' },
];

const logos = ref([]);
const colors = reactive({});
const isSaving = ref(false);
const saveError = ref('');
const saveMessage = ref('');
const openColorKey = ref(null);
const logoPendingRemoval = ref(null);

const savedLogos = computed(() => props.brand.brand_logos ?? []);
const savedColors = computed(() => props.brand.brand_colors ?? {});
const hasChanges = computed(() => {
  const logosHaveChanged = JSON.stringify(logos.value) !== JSON.stringify(savedLogos.value);
  const colorsHaveChanged = colorRoles.some((role) => (colors[role.key] ?? null) !== (savedColors.value[role.key] ?? null));
  return logosHaveChanged || colorsHaveChanged;
});

// Un análisis nuevo puede cambiar logos y colores mientras la sección está a la vista. Se comparan por
// contenido para no perder cambios sin guardar cuando se guarda otra sección.
watch(() => JSON.stringify([savedLogos.value, savedColors.value]), loadDraft);

onMounted(() => {
  loadDraft();
  document.addEventListener('pointerdown', closePickerOnOutsideClick);
});

onBeforeUnmount(() => document.removeEventListener('pointerdown', closePickerOnOutsideClick));

function loadDraft() {
  logos.value = [...savedLogos.value];
  logoPendingRemoval.value = null;
  for (const role of colorRoles) {
    colors[role.key] = savedColors.value[role.key] ?? null;
  }
}

function discardChanges() {
  loadDraft();
  openColorKey.value = null;
  saveError.value = '';
  saveMessage.value = '';
}

function removeLogo(index) {
  logos.value.splice(index, 1);
  logoPendingRemoval.value = null;
}

function toggleColorPicker(colorKey) {
  openColorKey.value = openColorKey.value === colorKey ? null : colorKey;
}

// El selector se cierra al hacer clic fuera de su casillero; el propio botón del casillero lo alterna.
function closePickerOnOutsideClick(event) {
  const clickedColorKey = event.target.closest('[data-color-slot]')?.dataset.colorSlot;
  if (clickedColorKey !== openColorKey.value) {
    openColorKey.value = null;
  }
}

async function save() {
  openColorKey.value = null;
  isSaving.value = true;
  saveError.value = '';
  saveMessage.value = '';

  try {
    const brand = await BrandService.update({ brand_logos: logos.value, brand_colors: { ...colors } });

    emit('saved', brand);
    saveMessage.value = 'Guardado.';
  } catch (error) {
    saveError.value = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
  } finally {
    isSaving.value = false;
  }
}
</script>
