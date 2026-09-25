<template>
  <div>
    <!-- Una guía corta para quien no sabe por dónde empezar. -->
    <div class="rounded-sm bg-surface p-4">
      <p class="spec-label mb-2">
        Algunas ideas para contar
      </p>
      <ul class="space-y-1 text-sm leading-6">
        <li
          v-for="question in guideQuestions"
          :key="question"
        >
          {{ question }}
        </li>
      </ul>
    </div>

    <div
      v-if="isRecording"
      class="mt-4 flex items-center justify-between gap-3"
    >
      <span class="flex items-center gap-2 text-sm">
        <span
          class="h-2.5 w-2.5 rounded-full bg-danger motion-safe:animate-pulse"
          aria-hidden="true"
        />
        Grabando
        <span class="tabular-nums text-text-muted">{{ formattedElapsedTime }}</span>
      </span>
      <button
        type="button"
        class="min-h-11 shrink-0 cursor-pointer rounded-sm border border-border px-4 text-sm font-medium hover:bg-surface-selected"
        @click="stopRecording"
      >
        Detener
      </button>
    </div>

    <div
      v-else-if="recordedAudio"
      class="mt-4 space-y-3"
    >
      <audio
        :src="audioUrl"
        controls
        class="w-full"
      />
      <button
        type="button"
        :disabled="isDisabled"
        class="min-h-11 rounded-sm border border-border px-3 text-sm font-medium enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
        @click="recordAgain"
      >
        Grabar de nuevo
      </button>
    </div>

    <button
      v-else
      type="button"
      :disabled="isDisabled"
      class="mt-4 flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-border px-3 text-sm font-medium enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
      @click="startRecording"
    >
      <span
        class="h-2.5 w-2.5 rounded-full bg-danger"
        aria-hidden="true"
      />
      Grabar
    </button>

    <p
      v-if="recorderError"
      role="alert"
      class="mt-2 text-xs text-danger"
    >
      {{ recorderError }}
    </p>
  </div>
</template>


<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';

const props = defineProps({
  recordedAudio: { type: File, default: null },
  isDisabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:recordedAudio']);

// El mismo límite de subida que PHP y nginx; un audio más grande ni se envía.
const MAX_AUDIO_FILE_SIZE_BYTES = 20 * 1024 * 1024;
const guideQuestions = [
  '¿Cómo empezaste?',
  '¿Qué vendes y a quién?',
  '¿Qué buscan tus clientes cuando te eligen?',
  '¿Qué te hace distinto?',
];

const audioUrl = ref('');
const isRecording = ref(false);
const recorderError = ref('');
const elapsedSeconds = ref(0);
let mediaRecorder = null;
let elapsedTimer = null;

const formattedElapsedTime = computed(() => {
  const minutes = Math.floor(elapsedSeconds.value / 60);
  const seconds = String(elapsedSeconds.value % 60).padStart(2, '0');
  return `${minutes}:${seconds}`;
});

// El audio grabado se escucha desde una URL local, que se libera cuando se reemplaza o se descarta.
watch(() => props.recordedAudio, (recordedAudio) => {
  URL.revokeObjectURL(audioUrl.value);
  audioUrl.value = recordedAudio ? URL.createObjectURL(recordedAudio) : '';
});

onBeforeUnmount(() => {
  URL.revokeObjectURL(audioUrl.value);
  clearInterval(elapsedTimer);

  // Si el usuario se va mientras graba, se suelta el micrófono y el audio se descarta.
  const isStillRecording = mediaRecorder?.state === 'recording';
  if (isStillRecording) {
    mediaRecorder.onstop = null;
    mediaRecorder.stop();
    mediaRecorder.stream.getTracks().forEach((track) => track.stop());
  }
});

async function startRecording() {
  recorderError.value = '';

  const canRecord = Boolean(navigator.mediaDevices?.getUserMedia) && typeof MediaRecorder !== 'undefined';
  if (!canRecord) {
    recorderError.value = 'Tu navegador no permite grabar audio. Prueba con otro navegador.';
    return;
  }

  let microphoneStream;
  try {
    microphoneStream = await navigator.mediaDevices.getUserMedia({ audio: true });
  } catch (error) {
    recorderError.value = getMicrophoneErrorMessage(error);
    return;
  }

  const audioChunks = [];
  mediaRecorder = new MediaRecorder(microphoneStream);
  mediaRecorder.ondataavailable = (event) => audioChunks.push(event.data);
  mediaRecorder.onstop = () => {
    microphoneStream.getTracks().forEach((track) => track.stop());
    emitRecordedAudio(audioChunks, mediaRecorder.mimeType);
  };
  mediaRecorder.start();

  isRecording.value = true;
  elapsedSeconds.value = 0;
  elapsedTimer = setInterval(() => {
    elapsedSeconds.value++;
  }, 1000);
}

function stopRecording() {
  mediaRecorder.stop();
  isRecording.value = false;
  clearInterval(elapsedTimer);
}

// Chrome y Firefox graban en webm y Safari en mp4. El servidor reconoce el formato por el contenido; el nombre solo
// acompaña.
function emitRecordedAudio(audioChunks, mimeType) {
  const extension = mimeType.includes('mp4') ? 'mp4' : 'webm';
  const audioFile = new File(audioChunks, `audio.${extension}`, { type: mimeType });

  const isTooLarge = audioFile.size > MAX_AUDIO_FILE_SIZE_BYTES;
  if (isTooLarge) {
    recorderError.value = 'El audio pesa más de 20 MB. Graba uno más corto.';
    return;
  }
  emit('update:recordedAudio', audioFile);
}

function recordAgain() {
  emit('update:recordedAudio', null);
  startRecording();
}

function getMicrophoneErrorMessage(error) {
  if (error.name === 'NotAllowedError') {
    return 'Permite el uso del micrófono en tu navegador para grabar.';
  }
  if (error.name === 'NotFoundError') {
    return 'No encontramos un micrófono en tu equipo.';
  }
  return `No pudimos usar tu micrófono: ${error.message}`;
}
</script>
