import _ from 'lodash';
import ScriptIdentifier from '@/js/helpers/ScriptIdentifier';
import { NOT_OPENED_TAB_EXCEPTION_CODE } from '@/js/constants';


class MessagesSenderException
{

  constructor({code, message, payload})
  {
    this.code = code;
    this.payload = payload;
  }


  static buildUnreachableContentType({payload})
  {
    const code = 'UNREACHABLE_CONTENT_EXCEPTION_CODE';
    const exception = new MessagesSenderException({payload, code});
    return exception;
  }


  static buildNotOpenedTabType({payload})
  {
    const code = NOT_OPENED_TAB_EXCEPTION_CODE;
    const exception = new MessagesSenderException({payload, code});
    return exception;
  }


  isNotOpenedTabType()
  {
    return this.code == NOT_OPENED_TAB_EXCEPTION_CODE;
  }


  isUnreachableContentType()
  {
    return this.code == 'UNREACHABLE_CONTENT_EXCEPTION_CODE';
  }

  isMessageToClientyContent()
  {
    return this.payload.isMessageToClientyContent();
  }

  isMessageToWhatsAppContent()
  {
    return this.payload.isMessageToWhatsAppContent();
  }

}

export default MessagesSenderException;
