<template>
  <b-container class="main-container">
    <errors-section />

    <div v-if="!existsPopUpBlockingError">
      <message-section />
      <phones-section />
      
      <buttons-section />
      
      <current-sending-info-section />
      <last-sending-info-section />
    </div>
    
    <page-loader />

    <div v-if="extensionUUID" class="uuid-display">
      ID: {{ extensionUUID }}
    </div>
  </b-container>
</template>


<script>
  import { mapActions, mapGetters } from 'vuex';
  import Store from '@/js/helpers/Store';
  import MessagePayload from '@/js/helpers/MessagePayload';
  import PageLoader from '@/js/vue/components/PageLoader.vue';
  import ErrorsSection from '@/js/vue/components/ErrorsSection.vue';
  import PhonesSection from '@/js/vue/components/PhonesSection.vue';
  import MessageSection from '@/js/vue/components/MessageSection.vue';
  import ButtonsSection from '@/js/vue/components/ButtonsSection.vue';
  import LastSendingInfoSection from '@/js/vue/components/LastSendingInfoSection.vue';
  import CurrentSendingInfoSection from '@/js/vue/components/CurrentSendingInfoSection.vue';

  console.log('process.env.NODE_ENV', process.env.NODE_ENV);

  export default {
    name: 'PopUpPage',
    data() {
      return {
        extensionUUID: null,
      };
    },
    components: {
      PageLoader,
      ErrorsSection,
      PhonesSection,
      ButtonsSection,
      MessageSection,
      LastSendingInfoSection,
      CurrentSendingInfoSection,
    },
    computed: {
      ...mapGetters({
        existsPopUpBlockingError: 'popup/existsPopUpBlockingError',
      }),
    },
    methods: {
      ...mapActions({
        loadAllInfo: 'popup/loadAllInfo',
        showPageLoader: 'popup/showPageLoader',
        hidePageLoader: 'popup/hidePageLoader',
      }),
      async loadExtensionUUID() {
        const store = await Store.build();
        this.extensionUUID = store.get('extensionUUID');
      },
    },
    async mounted() {
      this.showPageLoader();
      await this.loadAllInfo();
      await this.loadExtensionUUID();
      this.hidePageLoader();

      chrome.runtime.onMessage.addListener((payload, sender, sendResponse) => {
        // console.log('POPUP: Message received', payload);
        const msgPayload = MessagePayload.buildFromJson(payload);

        if (msgPayload.isMessageToPopUp()) {
          if (process.env.NODE_ENV != 'production') {
            console.log('POPUP: msgPayload', msgPayload);
          }

          this.hidePageLoader();

          if (msgPayload.isMessageToPopUp()) {
            (async () => {
              await this.loadAllInfo();
            })();
            sendResponse({success: true});
            return true;
          }
        }
        
        sendResponse({success: true});
      });
    },
  }
</script>


<style scoped>
  .send-button {
    font-size: 1em;
    padding: 0.3em 0.6em;
  }

  .uuid-display {
    position: fixed;
    bottom: 2px;
    right: 5px;
    font-size: 9px;
    color: #aaa;
    -webkit-user-select: all;
    -moz-user-select: all;
    -ms-user-select: all;
    user-select: all;
  }
</style>

