<template>
  <b-container class="main-container">
    <img :src="logoUrl" alt="WhatsApp Logo" class="app-logo">
    <h3 class="title">WhatsApp conversations exporter</h3>

    <PageLoader />

    <div v-if="statusProcessed">
      <!-- Error State -->
      <b-alert :show="!whatsAppTabOpen" variant="danger" class="status-alert">
        <h4 class="alert-heading">¡Atención!</h4>
        <p class="mb-0">Debes tener WhatsApp Web abierto en una pestaña para poder exportar los chats.</p>
      </b-alert>

      <!-- Success State -->
      <b-card v-if="whatsAppTabOpen" class="status-card" no-body>
        <b-card-header>
          <h5 class="mb-0 text-success d-flex align-items-center justify-content-center">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="status-icon">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
            </svg>
            <span>WhatsApp Web detectado</span>
          </h5>
        </b-card-header>
        <b-card-body>
          <p class="card-text">Presiona el botón para exportar tus conversaciones.</p>
          
          <b-button
            variant="success"
            block
            size="lg"
            @click="exportChats"
            :disabled="isExporting"
            class="export-button"
          >
            <span v-if="!isExporting">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="button-icon">
                <path d="M19 12v7H5v-7H3v7c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-7h-2zm-6 .67l2.59-2.58L17 11.5l-5 5-5-5 1.41-1.41L11 12.67V3h2z" fill="currentColor"/>
              </svg>
              Exportar Chats
            </span>
            <span v-else>
              <b-spinner small></b-spinner>
              Exportando...
            </span>
          </b-button>

          <!-- Progress Bar -->
          <div v-if="isExporting" class="progress-section mt-3">
            <p class="progress-label mb-2" role="status">{{ progressLabel }}</p>
            <template v-if="progressTotal > 0">
              <p class="progress-count mb-2">
                <strong>{{ progressCurrent }} de {{ progressTotal }}</strong> chats procesados
              </p>
              <b-progress :max="progressTotal" height="1.5rem" class="progress-bar-container">
                <b-progress-bar :value="progressCurrent" :label="`${progressPercentage}%`" aria-label="Chats procesados"></b-progress-bar>
              </b-progress>
            </template>
            <p class="progress-detail mt-2 mb-0">{{ progressDetail }}</p>
          </div>

          <!-- Success Message -->
          <b-alert :show="exportSuccess" variant="success" class="mt-3 mb-0" dismissible @dismissed="exportSuccess = false">
            <strong>¡Éxito!</strong> Se exportaron {{ exportedCount }} conversaciones.
          </b-alert>

          <!-- Error Message -->
          <b-alert :show="exportError" variant="danger" class="mt-3 mb-0" dismissible @dismissed="exportError = false">
            <strong>Error:</strong> {{ exportErrorMessage }}
          </b-alert>
        </b-card-body>
      </b-card>
    </div>
  </b-container>
</template>


<script>
  import { mapActions } from 'vuex';
  import MessagesSender from '@/js/helpers/MessagesSender';
  import PageLoader from '@/js/vue/components/PageLoader.vue';

  export default {
    name: 'PopUpPage',
    components: {
      PageLoader,
    },
    data() {
      return {
        logoUrl: '',
        statusProcessed: false,
        whatsAppTabOpen: false,
        isExporting: false,
        exportSuccess: false,
        exportError: false,
        exportErrorMessage: '',
        exportedCount: 0,
        // Progress tracking
        progressCurrent: 0,
        progressTotal: 0,
        progressPhase: '',
      };
    },
    computed: {
      progressPercentage() {
        if (this.progressTotal === 0) return 0;
        return Math.floor((this.progressCurrent / this.progressTotal) * 100);
      },
      progressLabel() {
        const labels = {
          'loading_chats': 'Cargando chats...',
          'exporting_chats': 'Exportando conversaciones...',
          'generating_zip': 'Generando archivo ZIP...',
        };
        return labels[this.progressPhase] || 'Procesando...';
      },
      progressDetail() {
        if (this.progressPhase === 'loading_chats') {
          return 'Contando las conversaciones disponibles...';
        }
        if (this.progressPhase === 'generating_zip') {
          return 'Los chats ya se procesaron. Preparando la descarga...';
        }
        if (this.progressCurrent < this.progressTotal) {
          return `Procesando chat ${this.progressCurrent + 1} de ${this.progressTotal}...`;
        }
        return 'Preparando el archivo ZIP...';
      }
    },
    methods: {
      ...mapActions({
        showPageLoader: 'popup/showPageLoader',
        hidePageLoader: 'popup/hidePageLoader',
      }),
      async checkStatus() {
        try {
          const response = await MessagesSender.toBackground.getStatusFromBackground();
          console.log('[PopUpPage.vue] backgroundStatus:', response);
          
          if (response && response.success && response.data) {
            this.whatsAppTabOpen = response.data.whatsAppTabOpen;
            const exportStatus = response.data.exportStatus;
            // Un evento en vivo puede llegar mientras esperamos la respuesta del estado.
            const hasLiveExportUpdate = this.isExporting || this.exportSuccess || this.exportError;
            if (exportStatus && !hasLiveExportUpdate) {
              if (exportStatus.progress) {
                this.updateExportProgress(exportStatus.progress);
              }
              if (exportStatus.result) {
                this.showExportResult(exportStatus.result);
              }
            }
          } else {
            this.whatsAppTabOpen = false;
          }
          
          this.statusProcessed = true;
        } catch (error) {
          console.error('[PopUpPage.vue] Error al obtener el estado:', error);
          this.whatsAppTabOpen = false;
          this.statusProcessed = true;
        }
      },
      async exportChats() {
        this.isExporting = true;
        this.exportSuccess = false;
        this.exportError = false;
        // Reset progress
        this.progressCurrent = 0;
        this.progressTotal = 0;
        this.progressPhase = 'loading_chats';
        
        try {
          console.log('[PopUpPage.vue] Iniciando exportación...');
          const response = await MessagesSender.toBackground.startExportChats();
          
          if (!response || !response.success) {
            this.exportError = true;
            this.exportErrorMessage = 'No se pudo iniciar la exportación.';
            this.isExporting = false;
          }
          // Si es exitoso, esperamos la notificación del background vía onMessage listener
        } catch (error) {
          console.error('[PopUpPage.vue] Error al exportar:', error);
          this.exportError = true;
          this.exportErrorMessage = error.message || 'Error al exportar los chats.';
          this.isExporting = false;
        }
      },
      updateExportProgress(progress) {
        this.isExporting = true;
        this.exportSuccess = false;
        this.exportError = false;
        this.progressCurrent = progress.current;
        this.progressTotal = progress.total;
        this.progressPhase = progress.phase;
      },
      showExportResult(result) {
        this.isExporting = false;
        this.exportSuccess = result.success;
        this.exportError = !result.success;
        this.exportedCount = result.count || 0;
        this.exportErrorMessage = result.error || 'Error desconocido al exportar';
      },
      onExportMessage(message, sender, sendResponse) {
        if (message.msgCode === 'exportProgress') {
          this.updateExportProgress(message.data);
          sendResponse({ received: true });
        }
        if (message.msgCode === 'exportCompleted') {
          this.showExportResult(message.data);
          sendResponse({ received: true });
        }
      }
    },
    async mounted() {
      this.logoUrl = chrome.runtime.getURL('icons/icon_128.png');
      chrome.runtime.onMessage.addListener(this.onExportMessage);
      this.showPageLoader();
      await this.checkStatus();
      this.hidePageLoader();

    },
    beforeDestroy() {
      chrome.runtime.onMessage.removeListener(this.onExportMessage);
    },
  }
</script>


<style>
/* General Style */
body {
  background-color: #f8f9fa;
}
</style>

<style scoped>
.main-container {
  max-width: 400px;
  padding: 20px 15px;
  background-color: #f8f9fa;
  width: 400px;
}

.app-logo {
  display: block;
  margin: 0 auto 15px auto;
  width: 80px;
}

.title {
  text-align: center;
  color: #25D366;
  font-weight: 600;
  margin-bottom: 20px;
  font-size: 1.35rem;
}

/* Status Card */
.status-card {
  border: 1px solid #dee2e6;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
  text-align: center;
}

.status-card .card-header {
  background-color: #fff;
  border-bottom: 1px solid #dee2e6;
  padding: 0.75rem 1.25rem;
}

.status-icon {
  vertical-align: middle;
  margin-right: 8px;
}

.card-text {
  font-size: 0.95rem;
  color: #6c757d;
  margin-bottom: 1rem;
}

/* Export Button */
.export-button {
  margin-top: 0.5rem;
  font-weight: 600;
  padding: 0.75rem;
  background-color: #25D366;
  border-color: #25D366;
}

.export-button:hover:not(:disabled) {
  background-color: #20BA5A;
  border-color: #20BA5A;
}

.export-button:disabled {
  background-color: #7ED69B;
  border-color: #7ED69B;
}

.button-icon {
  vertical-align: middle;
  margin-right: 6px;
}

/* Error Alert */
.status-alert {
  text-align: left;
  font-size: 0.9rem;
}

.status-alert p {
  margin-bottom: 0;
}

/* Progress Section */
.progress-section {
  text-align: center;
}

.progress-label {
  font-size: 0.9rem;
  color: #495057;
  font-weight: 500;
}

.progress-bar-container {
  border-radius: 0.5rem;
}

.progress-bar-container .progress-bar {
  background-color: #25D366;
  transition: width 0.3s ease;
}

.progress-detail {
  font-size: 0.8rem;
  color: #6c757d;
}

.progress-count {
  font-size: 0.95rem;
  color: #495057;
  font-variant-numeric: tabular-nums;
}
</style>
