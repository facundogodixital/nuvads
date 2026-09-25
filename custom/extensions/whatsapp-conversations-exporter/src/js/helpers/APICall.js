import _ from 'lodash';
import axios from 'axios';
import Store from '@/js/helpers/Store';
import { APP_VERSION } from '@/js/constants';
import fetchAdapter from '@vespaiach/axios-fetch-adapter';
import RESTException from '@/js/exceptions/RESTException';


const handleResponse = (response) => {
  const error = _.get(response, 'data.error');
  const success = _.get(response, 'data.success');

  if (success === undefined || !success) {
    handleError({success, error});
  }
  return _.get(response, 'data');
};


const handleError = ({ success, error }) => {
  if (!success && error) {
      const { code, message } = error;
      throw new RESTException({code, message});
  }
  throw new RESTException({code: -1, message: 'unknown_rest_error'});
};


const throwErrorException = (errResponse) => {
  const error = _.get(errResponse, 'response.data.error');
  if (error) {
    const { code, message } = error;
    throw new RESTException({code, message});
  }

  throw new RESTException({code: -1, message: 'unknown_rest_error'});
};


const getAxiosHeaders = (bearerToken) => {
  const manifest = chrome.runtime.getManifest();
  const version = manifest.version;

  const headers = {
    'app-version': version,
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  };

  headers.Authorization = `Bearer ${bearerToken}`;
  return headers;
};



const APICall = async (
  endpoint,
  method = 'get',
  {
    file = null,
    bodyParams = {},
    isFileDownload = false,
  } = {}
) => {
  const store = await Store.build();
  const clientyLoginData = store.get('clientyLoginData');
  const bearerToken = _.get(clientyLoginData, 'token', null);
  if (!bearerToken) {
    throw new RESTException({code: -1, message: 'no_clienty_bearer_token'});
  }

  try {
    const headers = getAxiosHeaders(bearerToken);
    const axiosData = {
      headers,
      url: endpoint,
      data: bodyParams,
      adapter: fetchAdapter,
      method: method.toLowerCase(),
    };

    // // To download files
    if (isFileDownload) {
      axiosData.responseType = 'blob';
      const response = await axios(axiosData);
      return response;
    }
    
    if (!file) {
      const response = await axios(axiosData);
      return handleResponse(response);
    }

    // If it is uploading file
    const formData = new FormData();
    formData.append('file', file);
    headers['Content-Type'] = 'multipart/form-data';
    const response = await axios.post(endpoint, formData, {headers});
    return handleResponse(response);
  } catch (errResponse) {
    console.log({errResponse})
    throwErrorException(errResponse);
  }
};


export default APICall;