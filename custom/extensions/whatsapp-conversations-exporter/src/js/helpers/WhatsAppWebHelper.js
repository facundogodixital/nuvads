import _ from 'lodash';
import jQuery from 'jquery';


class WhatsAppWebHelper
{

  #isMocked;
  #testPhoneNumber;
  static #timeoutRs;
  static #instance;

  constructor(secret = undefined)
  {
    if (secret !== 'secret') {
      throw new Error('WhatsAppWebHelper cannot be called directly');
    }
    this.#isMocked = false;
  }


  static getInstance()
  {
    if (!WhatsAppWebHelper.#instance) {
      WhatsAppWebHelper.#instance = new WhatsAppWebHelper('secret');
    }
    return WhatsAppWebHelper.#instance;
  }


  async sendNewChatMessage(wapSending, wapSendingMessage, onSentChatCallbackFn)
  {
    if (process.env.NODE_ENV != 'production') {
      console.log('WhatsAppWebHelper sendNewChatMessage()');
    }

    const variables = _.get(wapSendingMessage, 'variables', null);
    const chatMessage = this.#replaceVariables(wapSending.message, variables);
    const phoneNumber = this.#testPhoneNumber ?? wapSendingMessage.phone_number;

    if (this.isMocked()) {
      WhatsAppWebHelper.#timeoutRs = setTimeout(async () => {
        if (!WhatsAppWebHelper.#timeoutRs) {
          return;
        }
        console.log(`MOCKED-SUCCESS - Phone: ${phoneNumber} - Msg: ${chatMessage} - Timestamp: ${Date.now()}`);
        await onSentChatCallbackFn({success: true, wapSending, wapSendingMessage});
      }, 1500);
      return true;
    }

    WhatsAppWebHelper.#timeoutRs = setTimeout(async () => {
      window.dispatchEvent(new CustomEvent(
        "ClientySendWapMessage", {detail: {phoneNumber, chatMessage}})
      );
      await onSentChatCallbackFn({success: true, wapSending, wapSendingMessage});
    }, 3500);
  }





  // PUSHER CLIENTY MESSAGE
  async sendNewClientyPusherMessage(clientyDataObject)
  {
    // { userId, clientId, chatMessage, phoneNumber, fromPhoneNumber, attachment, wAutomationLogId, browserTrackingKey, whatsAppSendingMessageId, extensionUUID }
    console.log('WhatsAppWebHelper - sendNewClientyPusherMessage()', clientyDataObject);
    window.dispatchEvent(new CustomEvent("sendNewClientyPusherMessage", {detail: clientyDataObject}));
  }

  async getChatMessages(clientyDataObject)
  {
    // { userId, clientId, phoneNumber, fromPhoneNumber }
    console.log('WhatsAppWebHelper - getChatMessages()', clientyDataObject);
    window.dispatchEvent(new CustomEvent("getChatMessages", {detail: clientyDataObject}));
  }

  async getChatMessageMedia(clientyDataObject)
  {
    // { userId, clientId, chatMessageId, fromPhoneNumber }
    console.log('WhatsAppWebHelper - getChatMessageMedia()', clientyDataObject);
    window.dispatchEvent(new CustomEvent("getChatMessageMedia", {detail: clientyDataObject}));
  }




  cancel() {
    clearTimeout(WhatsAppWebHelper.#timeoutRs);
    WhatsAppWebHelper.#timeoutRs = null;
  }


  isMocked(isMocked = undefined)
  {
    if (isMocked !== undefined) {
      this.#isMocked = isMocked;
      return this;
    } else {
      return this.#isMocked;
    }
  }

  sendOnlyToTestPhoneNumber(testPhoneNumber)
  {
    this.#testPhoneNumber = testPhoneNumber;
    return this;
  }


  #replaceVariables(chatMessage, variables)
  {
    if (!variables) {
      return chatMessage;
    }

    const varNames = ['nombre', 'apellido', 'empresa', 'nombre_usuario', 'apellido_usuario'];
    let hasVariables = varNames
      .map(varName => `{{${varName}}}`)
      .some(varName => chatMessage.includes(varName))
    ;
    if (!hasVariables) {
      return chatMessage;
    }

    let newMsg = chatMessage;
    for (const varName of varNames) {
      const varIdentifier = `{{${varName}}}`
      const varValue = _.get(variables, varName, '');
      console.log('varName', varName);
      console.log('varValue', varValue);
      console.log('varIdentifier', varIdentifier);
      if (!varValue || !varValue.length) {
        newMsg = _.replace(newMsg, ` ${varIdentifier}`, '');
      } else {
        newMsg = _.replace(newMsg, varIdentifier, varValue);
      }
      console.log('newMsg', newMsg)
    }
    return newMsg.trim();
  }

}


export default WhatsAppWebHelper;