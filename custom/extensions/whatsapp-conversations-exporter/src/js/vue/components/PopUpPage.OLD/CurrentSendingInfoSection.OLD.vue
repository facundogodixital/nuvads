<template>
  <div class="current-sending-section mt-2" v-if="hasToBeShown">
    <b-alert show :variant="variant">
      <div class="current-sending-title">
        Envío en curso
      </div>
      <div>
        <b>
          Estado: 
          <span v-if="!this.isPaused">En proceso</span>
          <span v-if="this.isPaused">
            Pausado
            <i class="bi bi-pause-circle-fill" />
          </span>
        </b>
      </div>
      <div>
        Iniciado el {{ startedDateString }}
      </div>
      <div v-if="isPaused">
        Pausado el {{ pausedDateString }}
      </div>
      <div>
        Enviados {{ sentWapMessages.length }} de {{ wapMessages.length }} mensajes.
      </div>
      <div>
        <span class="sent-success">
          {{ successfullySentWapMessages.length }} envíos correctos.
        </span>
        <span class="sent-error">
          {{ unsuccessfullySentWapMessages.length }} envíos con error.
        </span>
      </div>
      
    </b-alert>
  </div>
</template>


<script>
  import { mapGetters } from 'vuex';
  import moment from 'moment-timezone';


  export default {
    name: 'CurrentSendingInfoSection',
    computed: {
      ...mapGetters({
        currentSending: 'popup/currentSending',
        clientyLoginClient: 'popup/clientyLoginClient',
      }),
      variant() {
        if (this.isPaused) {
          return 'warning';
        }
        return 'info';
      },
      isPaused() {
        return this.currentSending.paused_date ? true : false;
      },
      clientyClientTimezone() {
        return this.clientyLoginClient.timezone;
      },
      hasToBeShown() {
        return this.currentSending ? true : false;
      },
      wapMessages() {
        return this.currentSending.whatsAppSendingMessages;
      },
      sentWapMessages() {
        return this.currentSending.whatsAppSendingMessages.filter(w => w.sent_date);
      },
      unsentWapMessages() {
        return this.currentSending.whatsAppSendingMessages.filter(w => !w.sent_date);
      },
      successfullySentWapMessages() {
        return this.sentWapMessages.filter(w => w.success);
      },
      unsuccessfullySentWapMessages() {
        return this.sentWapMessages.filter(w => !w.success);
      },
      startedDateString() {
        const dateLegend = this.getDateLegend(this.currentSending.created_at);
        return dateLegend;
      },
      pausedDateString() {
        const dateLegend = this.getDateLegend(this.currentSending.paused_date);
        return dateLegend;
      },
    },
    methods: {
      getDateLegend(dateStr) {
        const date = moment(dateStr).tz(this.clientyClientTimezone);
        const legend = date.format('DD/MM') + ' a las ' + date.format('HH:mm') + 'hs';
        return legend;
      },
    },
  }
</script>


<style scoped>
  .current-sending-section {
    font-size: 0.9em;
    color: #207EBC;
  }
  .current-sending-title {
    text-decoration: underline;
  }
</style>

