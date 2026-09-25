import {
  CLIENTY_NOT_OPENED_TAB_EXCEPTION_CODE,
  WHATSAPP_NOT_OPENED_TAB_EXCEPTION_CODE,
  CLIENTY_URL_IS_NOT_UNIQUE_TAB_EXCEPTION_CODE
} from '@/js/constants';


class TabException
{

  constructor({ code })
  {
    this.code = code;
  }


  static buildClientyNotOpenedTabType()
  {
    const code = CLIENTY_NOT_OPENED_TAB_EXCEPTION_CODE;
    const exception = new TabException({code});
    return exception;
  }

  static buildWhatsAppNotOpenedTabType()
  {
    const code = WHATSAPP_NOT_OPENED_TAB_EXCEPTION_CODE;
    const exception = new TabException({code});
    return exception;
  }

  static buildClientyUrlNotUniqueType()
  {
    const code = CLIENTY_URL_IS_NOT_UNIQUE_TAB_EXCEPTION_CODE;
    const exception = new TabException({code});
    return exception;
  }


  isClientyNotOpenedTabType()
  {
    return this.code == CLIENTY_NOT_OPENED_TAB_EXCEPTION_CODE;
  }

  isWhatsAppNotOpenedTabType()
  {
    return this.code == WHATSAPP_NOT_OPENED_TAB_EXCEPTION_CODE;
  }

  isClientyUrlNotUniqueTabType()
  {
    return this.code == CLIENTY_URL_IS_NOT_UNIQUE_TAB_EXCEPTION_CODE;
  }

}

export default TabException;
