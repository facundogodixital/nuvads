import _ from 'lodash';
import MessagesSender from '@/js/helpers/MessagesSender';
import WhatsAppWebHelper from '@/js/helpers/WhatsAppWebHelper';
import WhatsAppSendingProcess from '@/js/helpers/WhatsAppSendingProcess';
import ClientyWhatsAppSendingService from '@/js/services/ClientyWhatsAppSendingService';


class WhatsAppContentController
{

  static #instance;


  constructor(secret)
  {
    if (secret != 'secret') {
      throw new Error('WhatsAppContentController cannot be called directly');
    }
  }

  static getInstance()
  {
    if (!WhatsAppContentController.#instance) {
      WhatsAppContentController.#instance = new WhatsAppContentController('secret');
    }
    return WhatsAppContentController.#instance;
  }


  // PUSHER CLIENTY MESSAGE
  async handleSendClientyPusherMessage(messagePayload)
  {
    // { userId, clientId, chatMessage, phoneNumber, fromPhoneNumber, attachment, wAutomationLogId, browserTrackingKey, whatsAppSendingMessageId, extensionUUID }
    const clientyDataObject = messagePayload.getData();
    console.log('WhatsAppContentController - handleSendClientyPusherMessage()', clientyDataObject);

    try {   
      const wapWebHelper = WhatsAppWebHelper.getInstance();
      await wapWebHelper.sendNewClientyPusherMessage(clientyDataObject);

      // await MessagesSender.toBackground.sendingProcessStarted();
    } catch(err) {
      console.log('WhatsAppWebHelper - ERROR', err);
      // await MessagesSender.toPopUp.errorOccurred(err);
    }
  }


  async handleGetChatMessages(messagePayload)
  {
    // { userId, clientId, phoneNumber, fromPhoneNumber }
    const clientyDataObject = messagePayload.getData();
    console.log('WhatsAppContentController - handleGetChatMessages()', clientyDataObject);

    try {   
      const wapWebHelper = WhatsAppWebHelper.getInstance();
      await wapWebHelper.getChatMessages(clientyDataObject);
    } catch(err) {
      console.log('WhatsAppWebHelper - ERROR', err);
    }
  }


  async handleGetChatMessageMedia(messagePayload)
  {
    // { userId, clientId, chatMessageId, fromPhoneNumber }
    const clientyDataObject = messagePayload.getData();
    console.log('WhatsAppContentController - handleGetChatMessageMedia()', clientyDataObject);

    try {   
      const wapWebHelper = WhatsAppWebHelper.getInstance();
      await wapWebHelper.getChatMessageMedia(clientyDataObject);
    } catch(err) {
      console.log('WhatsAppWebHelper - ERROR', err);
    }
  }


}


export default WhatsAppContentController;