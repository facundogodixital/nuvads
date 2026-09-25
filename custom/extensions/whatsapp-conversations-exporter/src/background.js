import TabsHelper from '@/js/helpers/TabsHelper';
import MessagePayload from '@/js/helpers/MessagePayload';
import MessagesSender from '@/js/helpers/MessagesSender';
import { 
  serializeError,
} from '@/js/helpers/BackgroundHelpers';



console.log("[background.js]");



chrome.runtime.onMessage.addListener((payload, sender, sendResponse) => {
  console.log('[background.js] - Message received', payload);

  const msgPayload = MessagePayload.buildFromJson(payload);
  console.log('[background.js] - msgPayload', msgPayload);

  if (msgPayload.isMessageToBackground()) {

    // Obtener estado para el popup
    if (msgPayload.msgCode == 'getStatusFromBackground') {
      (async () => {
        try {
          const wapTab = await TabsHelper.getWhatsAppTab();
          const exportStatus = wapTab
            ? await chrome.tabs.sendMessage(wapTab.id, { msgCode: 'getExportStatus' }).catch(() => null)
            : null;
          const statusData = {
            whatsAppTabOpen: !!wapTab,
            exportStatus,
          };
          sendResponse({ success: true, data: statusData });
        } catch (err) {
          sendResponse({ success: false, error: serializeError(err) });
        }
      })();
      return true;
    }

    // Iniciar exportación de chats
    if (msgPayload.msgCode == 'startExportChats') {
      MessagesSender.toWapContent.getSeedChats({})
        .then(() => sendResponse({ success: true }))
        .catch(err  => sendResponse({ success: false, error: serializeError(err) }))
      ;
      return true;
    }

    // Progreso de exportación (desde content script)
    if (msgPayload.msgCode == 'exportProgress') {
      console.log('[background.js] - Export progress', msgPayload.data);
      // Notificar al popup del progreso
      chrome.runtime.sendMessage({
        sender: 'background',
        receiver: 'popup',
        msgCode: 'exportProgress',
        data: msgPayload.data
      });
      sendResponse({ success: true });
      return true;
    }

    // Notificación de completado (desde content script)
    if (msgPayload.msgCode == 'getSeedChatsComplete') {
      console.log('[background.js] - Export completed', msgPayload.data);
      // Notificar a todos los listeners (incluyendo popup si está abierto)
      chrome.runtime.sendMessage({
        sender: 'background',
        receiver: 'popup',
        msgCode: 'exportCompleted',
        data: msgPayload.data
      });
      sendResponse({ success: true });
      return true;
    }
  }

  sendResponse({success: true});
  return true;
});
