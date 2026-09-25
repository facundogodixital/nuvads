import {
  APP_VERSION_OUTDATED_EXCEPTION_CODE,
  CLIENTY_NOT_ENABLED_EXTENSION_EXCEPTION_CODE
} from '@/js/constants';


class ClientyDataException
{

  constructor({ code })
  {
    this.code = code;
  }


  static buildNotEnabledExtensionType()
  {
    const code = CLIENTY_NOT_ENABLED_EXTENSION_EXCEPTION_CODE;
    const exception = new ClientyDataException({code});
    return exception;
  }

  static buildOutdatedAppVersionType()
  {
    const code = APP_VERSION_OUTDATED_EXCEPTION_CODE;
    const exception = new ClientyDataException({code});
    return exception;
  }


  isNotEnabledExtensionType()
  {
    return this.code == CLIENTY_NOT_ENABLED_EXTENSION_EXCEPTION_CODE;
  }


  isOutdatedAppVersionType()
  {
    return this.code == APP_VERSION_OUTDATED_EXCEPTION_CODE;
  }

}

export default ClientyDataException;
