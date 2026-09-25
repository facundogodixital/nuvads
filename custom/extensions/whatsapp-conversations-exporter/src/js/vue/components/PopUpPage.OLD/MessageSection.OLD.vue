<template>
  <div class="message-section mt-2">
    <b-row>
      <b-col cols="12">
        <i class="bi bi-chat-fill message-text-icon" />
        <span class="message-text-legend">
          Mensaje a enviar: 
        </span>
      </b-col>
    </b-row>
    <b-row>
      <b-col cols="12" :title="tooltipMessage">
        <b-form-textarea rows="3" v-model="chatMessage" :disabled="isDisabled" />
      </b-col>
    </b-row>

    <b-row>
      <b-col cols="12">
        <div class="quota-legend">
          Mensajes enviados hoy: {{ dailyUsedQuota }} de {{ dailyUserQuota }} ({{ dailyRemainingQuota}} restantes)
        </div>
      </b-col>
    </b-row>
  </div>
</template>


<script>
  import { mapGetters, mapActions } from 'vuex';


  export default {
    name: 'MessageSection',
    data() {
      return {
        chatMessage: '',
      };
    },
    computed: {
      ...mapGetters({
        storeChatMessage: 'popup/chatMessage',
        currentSending: 'popup/currentSending',
        dailyUserQuota: 'popup/dailyUserQuota',
        dailyUsedQuota: 'popup/dailyUsedQuota',
        dailyRemainingQuota: 'popup/dailyRemainingQuota',
      }),
      isDisabled() {
        return this.currentSending ? true : false;
      },
      tooltipMessage() {
        return this.isDisabled ? 'No puedes editar el mensaje mientras haya un envío en proceso' : '';
      },
    },
    methods: {
      ...mapActions({
        setChatMessage: 'popup/setChatMessage',
        saveChatMessageToStorage: 'popup/saveChatMessageToStorage',
      }),
      emitMessage() {
        this.setChatMessage(this.chatMessage);
        this.saveChatMessageToStorage();
      },
    },
    async mounted() {
      // this.emitMessage();
    },
    watch: {
      chatMessage(chatMessage) {
        this.emitMessage();
      },
      storeChatMessage: {
        immediate: true,
        async handler(storeChatMessage) {
          if (storeChatMessage != this.chatMessage) {
            this.chatMessage = storeChatMessage;
          }
        },
      }
    },
  }
</script>


<style scoped>
  .message-text-legend {
    font-size: 0.9em;
    color: #207EBC;
  }
  .message-text-icon {
    color: #207EBC;
    font-size: 1.1em;
  }
  .quota-legend {
    color: #777;
    font-size: 0.7em;
  }
</style>

