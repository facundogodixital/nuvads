import _ from 'lodash';
import WapMessageDTO from '@/js/dto/WapMessageDTO';
import WhatsAppWebHelper from '@/js/helpers/WhatsAppWebHelper';
import ClientyWhatsAppSendingService from '@/js/services/ClientyWhatsAppSendingService';
import { SENDING_PROCESS_IS_MOCKED , SENDING_PROCESS_PHONE_NUMBER_TO_REDIRECT_ALL } from '@/js/constants';


class WhatsAppSendingProcess
{

  static #instance;
  static #timeoutRs;

  #wapWebHelper;
  #currentSending;
  #clientyWapService;
  #onFinishedCallbackFn;
  #lastDispatchedMessage;
  #onSentMessageCallbackFn;


  constructor(secret = undefined, clientyWapService, wapWebHelper)
  {
    if (secret !== 'secret') {
      throw new Error('WhatsAppSendingProcess cannot be called directly');
    }

    this.#wapWebHelper = wapWebHelper;
    this.#clientyWapService = clientyWapService;
  }

  static getInstance()
  {
    if (!WhatsAppSendingProcess.#instance) {
      const wapWebHelper = WhatsAppWebHelper.getInstance();
      
      if (process.env.NODE_ENV != 'production') {
        wapWebHelper.isMocked(SENDING_PROCESS_IS_MOCKED);
        const phoneToRedirect = SENDING_PROCESS_PHONE_NUMBER_TO_REDIRECT_ALL;
        if (phoneToRedirect) {
          wapWebHelper.sendOnlyToTestPhoneNumber(phoneToRedirect);
        }
      }

      const clientyWapService = ClientyWhatsAppSendingService.getInstance();
      WhatsAppSendingProcess.#instance = new WhatsAppSendingProcess('secret', clientyWapService, wapWebHelper);
    }

    return WhatsAppSendingProcess.#instance;
  }


  async startSending({ phonesMap, chatMessage })
  {
    this.#lastDispatchedMessage = null;
    this.#currentSending = await this.#clientyWapService.create({phonesMap, chatMessage});
    this.#bindProcess();
  }


  async cancelSending()
  {
    this.#unbindProcess();
    this.#wapWebHelper.cancel();
    await this.#clientyWapService.cancelCurrentSending();
    this.#lastDispatchedMessage = null;
  }


  async pauseSending()
  {
    this.#unbindProcess();
    this.#wapWebHelper.cancel();
    await this.#clientyWapService.pauseCurrentSending();
    
    if (this.#lastDispatchedMessage) {
      await this.#clientyWapService.unmarkWapMessageAsDispatched(this.#lastDispatchedMessage);
      this.#lastDispatchedMessage = null;
    }
  }


  async resumeSending()
  {
    this.#currentSending = await this.#clientyWapService.resumeCurrentSending();
    this.#bindProcess();
    this.#lastDispatchedMessage = null;
  }


  async #finishSending()
  {
    this.#unbindProcess();
    this.#currentSending = await this.#clientyWapService.finishCurrentSending();
    await this.#onFinishedCallbackFn(this.#currentSending);
    this.#lastDispatchedMessage = null;
  }


  async dispatchNextMessage()
  {
    if (!WhatsAppSendingProcess.#timeoutRs) {
      return;
    }

    const wapSendingMessage = this.#getNextWapSendingMessageToSend();
    if (!wapSendingMessage) {
      await this.#finishSending();
      return;
    }
    
    const dispatchedWapMsg = await this.#clientyWapService.markWapMessageAsDispatched(wapSendingMessage);
    this.#lastDispatchedMessage = dispatchedWapMsg;

    await this.#wapWebHelper.sendNewChatMessage(
      this.#currentSending, wapSendingMessage, this.handleSentChatMessage.bind(this)
    );
    this.#replaceCurrentSendingMessage(dispatchedWapMsg);
  }


  async handleSentChatMessage({ success, wapSending, wapSendingMessage })
  {
    const wapSentMessage = await this.#markWapMessageAsSent({wapSendingMessage, success});
    this.#replaceCurrentSendingMessage(wapSentMessage);
    this.#lastDispatchedMessage = null;

    if (!this.#getNextWapSendingMessageToSend()) {
      await this.#finishSending();
      return;
    }

    this.#onSentMessageCallbackFn(wapSentMessage);
    if (WhatsAppSendingProcess.#timeoutRs) {
      this.#bindProcess();
    }
  }

  
  #bindProcess()
  {
    const timeoutMs = _.random(3000, 7000);
    WhatsAppSendingProcess.#timeoutRs = setTimeout(this.dispatchNextMessage.bind(this), timeoutMs);
  }

  #unbindProcess()
  {
    clearTimeout(WhatsAppSendingProcess.#timeoutRs);
    WhatsAppSendingProcess.#timeoutRs = null;
  }


  async #markWapMessageAsSent({ wapSendingMessage, success })
  {
    const wapMsg = await this.#clientyWapService.markWapMessageAsSent(wapSendingMessage, {success, error: null});
    return wapMsg;
  }


  #getNextWapSendingMessageToSend()
  {
    const wapMsgs = _.get(this.#currentSending, 'whatsAppSendingMessages');
    if (!wapMsgs || !wapMsgs.length) {
      throw new Error('WhatsAppSendingProcess: Invalid or empty currentSending');
    }

    const nextWapMessage = wapMsgs
      .filter(wapMsg => !wapMsg.sent_date && !wapMsg.paused_date && !wapMsg.dispatched_date)
      .pop()
    ;
    return nextWapMessage ?? null;
  }


  async #replaceCurrentSendingMessage(wapSendingMessage)
  {
    this.#currentSending.whatsAppSendingMessages = this.#currentSending.whatsAppSendingMessages.map(wapMsg => {
      return (wapMsg.id != wapSendingMessage.id) ? wapMsg : wapSendingMessage;
    });
  }



  onFinished(callbackFn)
  {
    this.#onFinishedCallbackFn = callbackFn;
  }

  onSentMessageCallbackFn(callbackFn)
  {
    this.#onSentMessageCallbackFn = callbackFn;
  }

}

export default WhatsAppSendingProcess;