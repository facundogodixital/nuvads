import { POPUP_INFO_SERVICE_UNAVAILABLE_EXCEPTION_CODE } from '@/js/constants';


class APIConnectionException
{

  constructor({code})
  {
    this.code = code;
  }


  static buildPopUpInfoServiceUnavailableType()
  {
    const code = POPUP_INFO_SERVICE_UNAVAILABLE_EXCEPTION_CODE;
    const exception = new APIConnectionException({code});
    return exception;
  }

  isPopUpInfoServiceUnavailableType()
  {
    return this.code == POPUP_INFO_SERVICE_UNAVAILABLE_EXCEPTION_CODE;
  }


}

export default APIConnectionException;
