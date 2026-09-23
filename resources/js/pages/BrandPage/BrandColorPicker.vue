<template>
  <div
    class="w-64 rounded-sm border border-border bg-surface-raised p-3 shadow-lg"
    role="dialog"
    :aria-label="`Elegir ${label}`"
    @keydown.esc.stop.prevent="emit('close')"
  >
    <div
      ref="area"
      class="picker-area relative h-36 w-full cursor-crosshair rounded-sm"
      :style="{ backgroundColor: hueColor }"
      role="slider"
      tabindex="0"
      aria-label="Saturación y luminosidad"
      :aria-valuetext="hex"
      @pointerdown="startPicking"
      @pointermove="pick"
      @pointerup="stopPicking"
      @pointercancel="stopPicking"
      @keydown="moveWithKeys"
    >
      <span
        class="picker-marker"
        :style="{ left: `${saturation}%`, top: `${100 - value}%`, backgroundColor: hex }"
      />
    </div>
    <input
      v-model.number="hue"
      type="range"
      min="0"
      max="360"
      class="picker-hue mt-3 w-full cursor-pointer"
      aria-label="Tono"
      @input="applyPickedColor"
    >
    <div class="mt-3 flex items-center gap-2">
      <span
        class="h-9 w-9 shrink-0 rounded-sm border border-border"
        :style="{ backgroundColor: hex }"
        aria-hidden="true"
      />
      <input
        v-model="hexInput"
        type="text"
        maxlength="7"
        autocomplete="off"
        :spellcheck="false"
        aria-label="Código del color"
        class="min-h-9 w-full min-w-0 rounded-sm border border-border bg-surface px-2 font-mono text-sm uppercase"
        @input="applyTypedColor"
      >
      <button
        type="button"
        class="min-h-9 shrink-0 cursor-pointer rounded-sm border border-border px-3 text-sm font-medium hover:bg-surface-selected"
        @click="emit('close')"
      >
        Listo
      </button>
    </div>
  </div>
</template>


<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
  label: { type: String, required: true },
  modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'close']);

const hue = ref(0);
const hex = ref('');
const area = ref(null);
const value = ref(100);
const hexInput = ref('');
const saturation = ref(0);
const isPicking = ref(false);

const hueColor = computed(() => `hsl(${hue.value} 100% 50%)`);

onMounted(() => {
  loadHex(props.modelValue || '#FFFFFF');
  area.value.focus();
});

function loadHex(hexColor) {
  const hsv = hexToHsv(hexColor);
  hue.value = hsv.hue;
  saturation.value = hsv.saturation;
  value.value = hsv.value;
  hex.value = hexColor.toUpperCase();
  hexInput.value = hex.value;
}

function startPicking(event) {
  isPicking.value = true;
  event.currentTarget.setPointerCapture(event.pointerId);
  pick(event);
}

function stopPicking() {
  isPicking.value = false;
}

function pick(event) {
  if (!isPicking.value) {
    return;
  }

  const bounds = area.value.getBoundingClientRect();
  const x = Math.min(Math.max(event.clientX - bounds.left, 0), bounds.width);
  const y = Math.min(Math.max(event.clientY - bounds.top, 0), bounds.height);
  saturation.value = (x / bounds.width) * 100;
  value.value = (1 - y / bounds.height) * 100;
  applyPickedColor();
}

function moveWithKeys(event) {
  const step = event.shiftKey ? 10 : 1;
  const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
  const move = moves[event.key];
  if (!move) {
    return;
  }

  event.preventDefault();
  saturation.value = Math.min(Math.max(saturation.value + move[0], 0), 100);
  value.value = Math.min(Math.max(value.value + move[1], 0), 100);
  applyPickedColor();
}

function applyPickedColor() {
  hex.value = hsvToHex(hue.value, saturation.value, value.value);
  hexInput.value = hex.value;
  emit('update:modelValue', hex.value);
}

// El código escrito se aplica solo cuando está completo; mientras tanto no se toca el color.
function applyTypedColor() {
  const typedHex = hexInput.value.trim();
  const isCompleteHex = /^#[0-9a-f]{6}$/i.test(typedHex);
  if (!isCompleteHex) {
    return;
  }

  const hsv = hexToHsv(typedHex);
  hue.value = hsv.hue;
  saturation.value = hsv.saturation;
  value.value = hsv.value;
  hex.value = typedHex.toUpperCase();
  emit('update:modelValue', hex.value);
}

function hsvToHex(hue, saturation, value) {
  const chroma = (value / 100) * (saturation / 100);
  const sectorPosition = hue / 60;
  const secondary = chroma * (1 - Math.abs((sectorPosition % 2) - 1));
  const sectors = [
    [chroma, secondary, 0], [secondary, chroma, 0], [0, chroma, secondary],
    [0, secondary, chroma], [secondary, 0, chroma], [chroma, 0, secondary],
  ];
  const rgb = sectors[Math.floor(sectorPosition) % 6];
  const lift = value / 100 - chroma;

  return `#${rgb.map((channel) => Math.round((channel + lift) * 255).toString(16).padStart(2, '0')).join('')}`
    .toUpperCase();
}

function hexToHsv(hexColor) {
  const red = parseInt(hexColor.slice(1, 3), 16) / 255;
  const green = parseInt(hexColor.slice(3, 5), 16) / 255;
  const blue = parseInt(hexColor.slice(5, 7), 16) / 255;
  const max = Math.max(red, green, blue);
  const delta = max - Math.min(red, green, blue);

  let hue = 0;
  if (delta !== 0 && max === red) {
    hue = 60 * (((green - blue) / delta) % 6);
  } else if (delta !== 0 && max === green) {
    hue = 60 * ((blue - red) / delta + 2);
  } else if (delta !== 0) {
    hue = 60 * ((red - green) / delta + 4);
  }
  if (hue < 0) {
    hue += 360;
  }
  const saturation = max === 0 ? 0 : (delta / max) * 100;

  return { hue, saturation, value: max * 100 };
}
</script>


<style scoped>
/* Los colores literales son la física del selector, no el tema: blanco a la izquierda, negro abajo y el
   arcoíris del tono se ven igual en light y dark. */
.picker-area {
  touch-action: none;
  background-image: linear-gradient(to top, #000, transparent), linear-gradient(to right, #fff, transparent);
}

.picker-marker {
  position: absolute;
  width: 14px;
  height: 14px;
  border-radius: 9999px;
  border: 2px solid #fff;
  box-shadow: 0 0 0 1px rgb(0 0 0 / 0.4);
  transform: translate(-50%, -50%);
  pointer-events: none;
}

.picker-hue {
  appearance: none;
  height: 12px;
  border-radius: 8px;
  background: linear-gradient(to right, #f00, #ff0, #0f0, #0ff, #00f, #f0f, #f00);
}

.picker-hue::-webkit-slider-thumb {
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 9999px;
  border: 2px solid #fff;
  background: transparent;
  box-shadow: 0 0 0 1px rgb(0 0 0 / 0.4);
}

.picker-hue::-moz-range-thumb {
  width: 14px;
  height: 14px;
  border-radius: 9999px;
  border: 2px solid #fff;
  background: transparent;
  box-shadow: 0 0 0 1px rgb(0 0 0 / 0.4);
}
</style>
