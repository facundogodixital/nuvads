import Pusher from 'pusher-js/worker';
import Store from '@/js/helpers/Store';
import TabsHelper from '@/js/helpers/TabsHelper';
import ClientyWhatsAppSendingService from '@/js/services/ClientyWhatsAppSendingService';


const PUSHER_CLUSTER = 'mt1';
const PUSHER_APP_KEY = 'abd4213d9d9cb13f680e';


let extensionUUID = null;
let pusherChannel = null;
let pusherInstance = null;
Pusher.logToConsole = true;


const getBackgroundStatusObject = async function () {
  const clientyTab = await TabsHelper.getClientyTab();
  const whatsAppTab = await TabsHelper.getWhatsAppTab();

  const store = await Store.build();

  const statusObj = {};
  statusObj.clientyTab = clientyTab;
  statusObj.whatsAppTab = whatsAppTab;
  statusObj.extensionUUID = extensionUUID;
  statusObj.clientyUrl = store.get('clientyUrl');
  statusObj.pusherChannelName = pusherChannel?.name;
  statusObj.clientyLoginData = store.get('clientyLoginData');
  statusObj.pusherChannelSubscribed = pusherChannel?.subscribed;

  return statusObj;
}


function setupAlarms() {
  chrome.alarms.clear('keepAlive1');
  chrome.alarms.clear('keepAlive2');
  chrome.alarms.clear('keepAlive3');
  
  // Crear nuevas alarmas con diferentes intervalos
  chrome.alarms.create('keepAlive1', { delayInMinutes: 0.3, periodInMinutes: 0.5 });
  chrome.alarms.create('keepAlive2', { delayInMinutes: 0.5, periodInMinutes: 0.75 });
  chrome.alarms.create('keepAlive3', { delayInMinutes: 0.67, periodInMinutes: 1 });
}


const generateAndSaveUUID = async function () {
  const store = await Store.build();
  let uuid = store.get('extensionUUID');
  if (!uuid) {
    uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
    await store.set('extensionUUID', uuid);
    await store.saveToStorage();
    console.log('[background.js] - UUID generado:', uuid);
  } else {
    console.log('[background.js] - UUID existente:', uuid);
  }
  extensionUUID = uuid;
}


const serializeError = function (err) {
  if (typeof err === 'string') {
    return err;
  }
  if (err instanceof Error) {
    return err.stack || err.message || err.toString();
  }
  try {
    return JSON.stringify(err);
  } catch (_) {
    return String(err);
  }
}


const ensureContentScriptLoaded = async function (tabId, scriptName) {
  try {
    // Intentar enviar un mensaje de ping
    await chrome.tabs.sendMessage(tabId, { type: 'ping' });
    return true;
  } catch (error) {
    // Si falla, inyectar el content script
    console.log(`[background.js] - Content script ${scriptName} no encontrado, inyectando...`);
    
    try {
      await chrome.scripting.executeScript({target: { tabId: tabId }, files: [scriptName]});
      
      // Esperar un momento para que se cargue
      await new Promise(resolve => setTimeout(resolve, 100));
      console.log(`[background.js] - Content script ${scriptName} inyectado`);
      return true;
    } catch (injectError) {
      console.error('[background.js] - Error inyectando content script:', injectError);
      return false;
    }
  }
}


const sendSuccessResponseToClienty = async function (browserTrackingKey, { whatsAppSendingMessageId, userId, clientId, extensionUUID })
{
  const ok = await ClientyWhatsAppSendingService.getInstance().sendClientyPusherMessageSuccessResponse({
    browserTrackingKey, whatsAppSendingMessageId, userId, clientId, extensionUUID
  });
  return ok;
}


const sendErrorResponseToClienty = async function (browserTrackingKey, error)
{
  const ok = await ClientyWhatsAppSendingService.getInstance().sendClientyPusherMessageErrorResponse({
    browserTrackingKey, errorCode: error
  });
  return ok;
}


const sendChatMessagesToClienty = async function ({ userId, clientId, phoneNumber, fromPhoneNumber, chatMessages }, error = null)
{
  const ok = await ClientyWhatsAppSendingService.getInstance().sendChatMessagesToClienty({
    userId, clientId, phoneNumber, fromPhoneNumber, chatMessages, error
  });
  return ok;
}


const sendChatMessageMediaToClienty = async function ({ userId, clientId, chatMessageId, fromPhoneNumber, chatMessageMedia }, error = null)
{
  const ok = await ClientyWhatsAppSendingService.getInstance().sendChatMessageMediaToClienty({
    userId, clientId, chatMessageId, fromPhoneNumber, chatMessageMedia, error
  });
  return ok;
}


const getPusherChannel = function () {
  return pusherChannel;
}

const getPusherInstance = function () {
  return pusherInstance;
}

const getExtensionUUID = function () {
  return extensionUUID;
}


const ensurePusherConnection = async function (bindPusherEventsCallback) {
  console.log('[background.js][ensurePusherConnection()] -------------------------------------------------');

  const store = await Store.build();
  const clientyLoginData = store.get('clientyLoginData');
  console.log('[background.js][ensurePusherConnection()] - clientyLoginData', clientyLoginData);
  
  const clientyClientId = clientyLoginData?.client?.id;
  const clientyUserWapSenderPhone = clientyLoginData?.user?.wap_sender_session_phone_number;
  console.log('[background.js][ensurePusherConnection()] - clientyClientId', clientyClientId);
  console.log('[background.js][ensurePusherConnection()] - clientyUserWapSenderPhone', clientyUserWapSenderPhone);
  if (!clientyClientId || !clientyUserWapSenderPhone) {
    console.log('[background.js][ensurePusherConnection()] - CLIENTY LOGIN DATA NOT FOUND');
    return;
  }
  
  console.log('[background.js][ensurePusherConnection()] - pusherInstance', pusherInstance);
  console.log('[background.js][ensurePusherConnection()] - pusherChannel', pusherChannel);
  if (!pusherInstance || !pusherChannel) {
    console.log('[background.js][ensurePusherConnection()] - INITIALIZING PUSHER');
    initializePusher(clientyClientId, clientyUserWapSenderPhone, bindPusherEventsCallback);
    return;
  }

  console.log('[background.js][ensurePusherConnection()] - pusherInstance.connection.state', pusherInstance?.connection?.state);
  if (pusherInstance?.connection?.state !== 'connected') {
    console.log('[background.js][ensurePusherConnection()] - INITIALIZING PUSHER');
    initializePusher(clientyClientId, clientyUserWapSenderPhone, bindPusherEventsCallback);
    return;
  }

  console.log('[background.js][ensurePusherConnection()] - pusherChannel.name', pusherChannel?.name);
  console.log('[background.js][ensurePusherConnection()] - pusherChannel.subscribed', pusherChannel?.subscribed);
  const pusherChannelName = pusherChannel?.name;
  const clientyChannelName = buildPusherChannelName(clientyClientId, clientyUserWapSenderPhone);
  const channelNameIsDifferent = pusherChannelName != clientyChannelName;
  if (channelNameIsDifferent || !pusherChannel?.subscribed) {
    console.log('[background.js][ensurePusherConnection()] - INITIALIZING PUSHER');
    initializePusher(clientyClientId, clientyUserWapSenderPhone, bindPusherEventsCallback);
    return;
  }

  console.log('[background.js][ensurePusherConnection()] - NO NEED TO INITIALIZE PUSHER');
}


const buildPusherChannelName = function (clientyClientId, clientyUserWapSenderPhone) {
  return `ClientyWAPSenderChannel-Client-${clientyClientId}-UserWAPPhone-${clientyUserWapSenderPhone}`;
}


const initializePusher = function (clientyClientId, clientyUserWapSenderPhone, bindPusherEventsCallback) {
  if (typeof Pusher === 'undefined') {
    console.error("[background.js] - Pusher library not available. Ensure it's correctly imported and bundled.");
    return;
  }

  // Desconectar si ya existe una instancia para evitar múltiples conexiones
  if (pusherInstance) {
    console.log("[background.js] - Desconectando instancia previa de Pusher...");
    pusherInstance.disconnect();
  }

  console.log(`[background.js] - Inicializando Pusher con App Key: ${PUSHER_APP_KEY} y Cluster: ${PUSHER_CLUSTER}`);
  pusherInstance = new Pusher(PUSHER_APP_KEY, {
    cluster: PUSHER_CLUSTER,
    // forceTLS: true, // Ya es true por defecto y recomendado
  });

  pusherInstance.connection.bind('error', function(err) {
    console.error("[background.js] - Error de conexión con Pusher:", err);
    if (err.error && err.error.data && err.error.data.code === 4004) {
      console.error("[background.js] - Error 4004: Límite de conexiones de Pusher o problema con app key/cluster.");
    }
    // Pusher intentará reconectar automáticamente según su propia lógica.
  });

  pusherInstance.connection.bind('connected', function() {
    console.log("[background.js] - Conectado a Pusher exitosamente!");
    subscribeToChannel(clientyClientId, clientyUserWapSenderPhone, bindPusherEventsCallback);
  });

  pusherInstance.connection.bind('disconnected', function() {
    console.warn("[background.js] - Desconectado de Pusher.");
    // Pusher intentará reconectar automáticamente.
  });

  pusherInstance.connection.bind('state_change', function(states) {
    // states = {previous: "oldState", current: "newState"}
    console.log("[background.js] - Estado de conexión de Pusher cambiado a", states.current);
  });
}



function subscribeToChannel(clientyClientId, clientyUserWapSenderPhone, bindPusherEventsCallback) {
  if (!pusherInstance || pusherInstance.connection.state !== 'connected') {
    console.warn("[background.js] - Pusher no está conectado. Suscripción al canal pospuesta.");
    return;
  }

  const pusherChannelName = buildPusherChannelName(clientyClientId, clientyUserWapSenderPhone);
  // Desuscribirse del canal si ya estábamos suscritos para evitar bindings duplicados
  if (pusherChannel) {
    console.log(`[background.js] - Desuscribiéndose del canal anterior: ${pusherChannel.name}`);
    pusherChannel.unbind_all(); // Quita todos los bindings del canal anterior
    pusherInstance.unsubscribe(pusherChannel.name); // Desuscribe del canal
  }

  console.log(`[background.js] - Suscribiéndose al canal: ${pusherChannelName}`);
  pusherChannel = pusherInstance.subscribe(pusherChannelName);

  pusherChannel.bind('pusher:subscription_succeeded', function() {
    console.log(`[background.js] - Suscripción al canal '${pusherChannelName}' exitosa.`);
    bindPusherEventsCallback(clientyClientId, clientyUserWapSenderPhone);
  });

  pusherChannel.bind('pusher:subscription_error', function(status) {
    console.error(`[background.js] - Error al suscribirse al canal '${pusherChannelName}':`, status);
  });
}






export {
  setupAlarms,
  serializeError,
  getPusherChannel,
  getExtensionUUID,
  initializePusher,
  getPusherInstance,
  subscribeToChannel,
  generateAndSaveUUID,
  buildPusherChannelName,
  ensurePusherConnection,
  getBackgroundStatusObject,
  ensureContentScriptLoaded,
  sendChatMessagesToClienty,
  sendErrorResponseToClienty,
  sendSuccessResponseToClienty,
  sendChatMessageMediaToClienty,
};