import ScriptIdentifier from '@/js/helpers/ScriptIdentifier';


class MessagePayload
{

  data;
  sender;
  msgCode;
  receiver;
  receiverName;

  constructor(sender, receiver, msgCode, data, receiverName = null)
  {
    this.data = data;
    this.sender = sender;
    this.msgCode = msgCode;
    this.receiver = receiver;
    this.receiverName = receiverName;
  }

  toJson()
  {
    return {
      data: this.data,
      sender: this.sender,
      msgCode: this.msgCode,
      receiver: this.receiver,
      receiverName: this.receiverName,
    }
  }

  static buildFromJson(json)
  {
    const { sender, receiver, msgCode, data, receiverName } = json;
    return new MessagePayload(sender, receiver, msgCode, data, receiverName);
  }


  isMessageToPopUp()
  {
    return this.receiver == ScriptIdentifier.getPopUpIdentifier();
  }
  isMessageToContent()
  {
    return this.receiver == ScriptIdentifier.getContentIdentifier();
  }
  isMessageToBackground()
  {
    return this.receiver == ScriptIdentifier.getBackgroundIdentifier();
  }
  isMessageToClientyContent()
  {
    return this.isMessageToContent() && this.receiverName == ScriptIdentifier.getClientyContentIdentifier();
  }
  isMessageToWhatsAppContent()
  {
    return this.isMessageToContent() && this.receiverName == ScriptIdentifier.getWhatsAppContentIdentifier();
  }
  isMessageFromBackground()
  {
    return this.sender == ScriptIdentifier.getBackgroundIdentifier();
  }


  isRequestClientyClientDataMsg() {
    return this.msgCode == 'REQUEST_CLIENTY_CLIENT_DATA_MESSAGE_CODE';
  }


  getData() {
    return this.data;
  }

}


export default MessagePayload;