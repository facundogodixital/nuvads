import MessagePayload from '@/js/helpers/MessagePayload';
import MessagesSender from '@/js/helpers/MessagesSender';
import WhatsAppWebContentController from '@/js/helpers/WhatsAppWebContentController';



function injectScript(file, node) {
  // Verificar si el script ya fue inyectado
  const scriptId = 'clienty-whatsapp-accessible-script';
  if (document.getElementById(scriptId)) {
    console.log('[WhatsApp Content] Script accessible ya existe, no reinyectar');
    return;
  }
  var th = document.getElementsByTagName(node)[0];
  var s = document.createElement('script');
  s.setAttribute('type', 'text/javascript');
  s.setAttribute('src', file);
  s.setAttribute('id', scriptId); // Agregar ID para poder verificar
  th.appendChild(s);
}
injectScript(chrome.runtime.getURL('/web.whatsapp.content.accessible.js'), 'body');



// Evento de respuesta al exportar chats
// El popup se destruye al cerrarlo; el estado vive mientras siga abierta esta pestaña.
let exportProgress = null;
let exportResult = null;

window.addEventListener('getSeedChatsComplete', function (data) {
  console.log('[WhatsApp Content] getSeedChatsComplete', data);
  const responseData = data.detail;
  exportResult = responseData;
  MessagesSender.toBackground.getSeedChatsComplete(responseData);
});

// Evento de progreso de exportación
window.addEventListener('exportProgress', function (data) {
  console.log('[WhatsApp Content] exportProgress', data.detail);
  exportProgress = data.detail;
  exportResult = null;
  MessagesSender.toBackground.exportProgress(data.detail);
});




console.log('[WhatsApp Content]', new Date().toISOString());

chrome.runtime.onMessage.addListener((payload, sender, sendResponse) => {
  console.log('[WhatsApp Content] Message received', payload);
  const messagePayload = MessagePayload.buildFromJson(payload);

  if (messagePayload.msgCode === 'getExportStatus') {
    sendResponse({ progress: exportProgress, result: exportResult });
    return;
  }

  if (messagePayload.msgCode == 'getSeedChats') {
    console.log('[WhatsApp Content] Dispatching getSeedChats event');
    window.dispatchEvent(new CustomEvent("getSeedChats", {detail: {}}));
    sendResponse({success: true});
    return true;
  }
  
  sendResponse({success: true});
  return true;
});
