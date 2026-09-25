<template>
  <div class="last-sending-section mt-2" v-if="hasToBeShown">
    <b-alert show :variant="variant">
      <div class="last-sending-title">
        Último envío realizado
      </div>
      <div>
        <b>Estado: {{ statusLegend }}</b>
      </div>
      <div>
        Iniciado el {{ startedDateString }}
      </div>
      <div v-if="isFinished">
        Finalizado el {{ finishedDateString }}
      </div>
      <div v-if="isCancelled">
        Cancelado el {{ cancelledDateString }}
      </div>
      <div v-if="isClosed">
        Cancelado el {{ closedDateString }}
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
    name: 'LastSendingInfoSection',
    computed: {
      ...mapGetters({
        lastSending: 'popup/lastSending',
        currentSending: 'popup/currentSending',
        clientyLoginClient: 'popup/clientyLoginClient',
      }),
      hasToBeShown() {
        if (this.currentSending) {
          return false;
        }
        return this.lastSending ? true : false;
      },
      variant() {
        if (this.isCancelled || this.isClosed) {
          return 'danger';
        }
        return 'success';
      },
      isClosed() {
        return this.lastSending.closed_date ? true : false;
      },
      isCancelled() {
        return this.lastSending.cancelled_date ? true : false;
      },
      isFinished() {
        return this.lastSending.finished_date ? true : false;
      },
      clientyClientTimezone() {
        return this.clientyLoginClient.timezone;
      },
      wapMessages() {
        return this.lastSending.whatsAppSendingMessages;
      },
      sentWapMessages() {
        return this.lastSending.whatsAppSendingMessages.filter(w => w.sent_date);
      },
      unsentWapMessages() {
        return this.lastSending.whatsAppSendingMessages.filter(w => !w.sent_date);
      },
      successfullySentWapMessages() {
        return this.sentWapMessages.filter(w => w.success);
      },
      unsuccessfullySentWapMessages() {
        return this.sentWapMessages.filter(w => !w.success);
      },
      statusLegend() {
        if (this.isCancelled) {
          return 'Cancelado manualmente';
        }
        if (this.isClosed) {
          return 'Cancelado por el sistema';
        }
        return 'Finalizado';
      },
      startedDateString() {
        return this.getDateLegend(this.lastSending.created_at);
      },
      finishedDateString() {
        return this.getDateLegend(this.lastSending.finished_date);
      },
      cancelledDateString() {
        return this.getDateLegend(this.lastSending.cancelled_date);
      },
      closedDateString() {
        return this.getDateLegend(this.lastSending.closed_date);
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
  .last-sending-section {
    font-size: 0.9em;
    color: #207EBC;
  }
  .last-sending-title {
    text-decoration: underline;
  }
</style>

