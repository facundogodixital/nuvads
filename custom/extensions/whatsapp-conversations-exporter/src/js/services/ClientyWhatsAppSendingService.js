import Store from '@/js/helpers/Store';
import APICall from '@/js/helpers/APICall';


let instance = null;
let clientyEndpoint = null;


export default class ClientyWhatsAppSendingService
{

  static getInstance()
  {
    if (!instance) {
      instance = new ClientyWhatsAppSendingService();
    }
    return instance;
  }


  async getClientyAPIEndpoint()
  {
    const store = await Store.build();
    console.log('store', store)
    const clientyUrl = store.get('clientyUrl');

    const urlArr = clientyUrl.split('/');
    let baseUrl = urlArr[0] + '//' + urlArr[2];
    if (baseUrl.indexOf('.test') !== -1) {
      baseUrl = baseUrl.replace('https:', 'http:');
    }
    clientyEndpoint = baseUrl + '/api';
    return clientyEndpoint;
  }


  async create({ phonesMap, chatMessage })
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending';
    const bodyParams = {chatMessage, phonesMap};
    const response = await APICall(endpoint, 'post', { bodyParams });
    const lastSending = response.data;
    return lastSending;
  }


  async sendClientyPusherSyncStatus({ success, error, pusherChannelName, extensionUUID, extensionVersion })
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/pusher-sync-status';
    const bodyParams = { success, error, pusherChannelName, extensionUUID, extensionVersion };
    const response = await APICall(endpoint, 'post', { bodyParams });
    return response.data;
  }


  async sendClientyPusherMessageErrorResponse({ browserTrackingKey, errorCode })
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/pusher-message-response/error';
    const bodyParams = { browserTrackingKey, errorCode };
    const response = await APICall(endpoint, 'post', { bodyParams });
    return response.data;
  }

  async sendClientyPusherMessageSuccessResponse({browserTrackingKey, whatsAppSendingMessageId, userId, clientId, extensionUUID })
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/pusher-message-response/success';
    const bodyParams = { browserTrackingKey, whatsAppSendingMessageId, userId, clientId, extensionUUID };
    const response = await APICall(endpoint, 'post', {bodyParams});
    return response.data;
  }


  async sendChatMessagesToClienty({ userId, clientId, phoneNumber, fromPhoneNumber, chatMessages }, error = null)
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/pusher-chat-messages-response';
    const bodyParams = { userId, clientId, phoneNumber, fromPhoneNumber, chatMessages, error };
    const response = await APICall(endpoint, 'post', { bodyParams });
    return response.data;
  }


  async sendChatMessageMediaToClienty({ userId, clientId, chatMessageId, fromPhoneNumber, chatMessageMedia }, error = null)
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/pusher-chat-message-media-response';
    const bodyParams = { userId, clientId, chatMessageId, fromPhoneNumber, chatMessageMedia, error };
    const response = await APICall(endpoint, 'post', { bodyParams });
    return response.data;
  }


  async getPopUpInfo()
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending/popup';
    const response = await APICall(endpoint, 'get');
    return response.data;
  }


  async getLastSending()
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending/last';
    const response = await APICall(endpoint, 'get');
    const lastSending = response.data;
    return lastSending;
  }


  async getCurrentSending()
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending/current';
    const response = await APICall(endpoint, 'get');    
    const lastSending = response.data;
    return lastSending;
  }


  async cancelCurrentSending()
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending/current/cancel';
    const response = await APICall(endpoint, 'put');    
    const cancelledSending = response.data;
    return cancelledSending;
  }


  async pauseCurrentSending({pauseReason = null} = {})
  {
    const bodyParams = {pause_reason: pauseReason};
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending/current/pause';
    const response = await APICall(endpoint, 'put', {bodyParams});    
    const pausedSending = response.data;
    return pausedSending;
  }


  async resumeCurrentSending()
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending/current/resume';
    const response = await APICall(endpoint, 'put');    
    const pausedSending = response.data;
    return pausedSending;
  }


  async finishCurrentSending()
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = clientyEndpoint + '/whatsapp-sender-extension/sending/current/finish';
    const response = await APICall(endpoint, 'put');    
    const pausedSending = response.data;
    return pausedSending;
  }


  async markWapMessageAsDispatched(wapSendingMsg)
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = `${clientyEndpoint}/whatsapp-sender-extension/sending/current/message/${wapSendingMsg.id}/mark-dispatched`;
    const response = await APICall(endpoint, 'put');    
    const dispatchedWapSendingMsg = response.data;
    return dispatchedWapSendingMsg;
  }


  async unmarkWapMessageAsDispatched(wapSendingMsg)
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const endpoint = `${clientyEndpoint}/whatsapp-sender-extension/sending/current/message/${wapSendingMsg.id}/unmark-dispatched`;
    const response = await APICall(endpoint, 'put');    
    const dispatchedWapSendingMsg = response.data;
    return dispatchedWapSendingMsg;
  }


  async markWapMessageAsSent(wapSendingMsg, { success, error })
  {
    const clientyEndpoint = await this.getClientyAPIEndpoint();
    const bodyParams = {success, error};
    const endpoint = `${clientyEndpoint}/whatsapp-sender-extension/sending/current/message/${wapSendingMsg.id}/mark-sent`;
    const response = await APICall(endpoint, 'put', {bodyParams});    
    const sentWapSendingMsg = response.data;
    return sentWapSendingMsg;
  }

}
