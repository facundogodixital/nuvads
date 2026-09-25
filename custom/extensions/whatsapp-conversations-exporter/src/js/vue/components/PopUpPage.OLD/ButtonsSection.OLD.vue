<template>
  <div class="send-button-section mt-1">
    <b-row>
      <b-col cols="10">
        <b-button
          variant="success"
          class="send-button"
          v-if="showStartButton"
          @click="handleStartSendingButtonClick"
          :disabled="existsSendButtonBlockingError"
        >
          <i class="bi bi-whatsapp" />
          Iniciar envío
        </b-button>

        <b-button
          variant="success"
          class="send-button"
          v-if="showResumeButton"
          @click="handleResumeSendingButtonClick"
        >
          <i class="bi bi-whatsapp" />
          Reanudar envío
        </b-button>

        <b-button
          variant="warning"
          class="pause-button"
          v-if="showPauseButton"
          @click="handlePauseSendingButtonClick"
        >
          <i class="bi bi-pause-circle-fill" />
          Pausar envío
        </b-button>

        <b-button
          variant="danger"
          class="cancel-button"
          v-if="showCancelButton"
          @click="handleCancelSendingButtonClick"
        >
          <i class="bi bi-stop-circle" />
          Cancelar envío
        </b-button>
      </b-col>

      <b-col cols="2" class="text-right">
        <b-button
          variant="danger"
          class="cancel-button"
          v-if="showClearPhonesButton"
          title="Borrar todos los números"
          @click="handleDeletePhonesButtonClick"
        >
          <i class="bi bi-eraser-fill" />
        </b-button>
      </b-col>
    </b-row>
  </div>
</template>


<script>
  import _ from 'lodash';
  import { mapActions, mapGetters } from 'vuex';


  export default {
    name: 'ButtonsSection',
    computed: {
      ...mapGetters({
        phonesMap: 'popup/phonesMap',
        currentSending: 'popup/currentSending',
        existsSendButtonBlockingError: 'popup/existsSendButtonBlockingError',
      }),
      showClearPhonesButton() {
        if (this.currentSending) {
          return false;
        }
        const phones = Object.values(this.phonesMap);
        if (!phones || !phones.length) {
          return false;
        }
        return true;
      },
      showStartButton() {
        return !this.currentSending;
      },
      showCancelButton() {
        return this.currentSending;
      },
      showPauseButton() {
        return this.currentSending && !this.currentSending.paused_date;
      },
      showResumeButton() {
        return this.currentSending && this.currentSending.paused_date;
      },
    },
    methods: {
      ...mapActions({
        clearPhones: 'popup/clearPhones',
        startSending: 'popup/startSending',
        pauseSending: 'popup/pauseSending',
        cancelSending: 'popup/cancelSending',
        resumeSending: 'popup/resumeSending',
        showPageLoader: 'popup/showPageLoader',
        hidePageLoader: 'popup/hidePageLoader',
        savePhonesMapToStorage: 'popup/savePhonesMapToStorage',
      }),
      async handleStartSendingButtonClick() {
        this.showPageLoader();

        const started = await this.startSending();
        if (!started) {
          this.hidePageLoader();
        }
      },
      async handleResumeSendingButtonClick() {
        this.showPageLoader();
        const resumed = await this.resumeSending();
      },
      async handlePauseSendingButtonClick() {
        this.showPageLoader();
        await this.pauseSending();
      },
      async handleCancelSendingButtonClick() {
        this.showPageLoader();
        await this.cancelSending();
      },
      async handleDeletePhonesButtonClick() {
        this.clearPhones();
        this.savePhonesMapToStorage();
      },
    },
  }
</script>


<style scoped>
  .send-button {
    font-size: 1em;
    padding: 0.3em 0.6em;
  }
</style>

