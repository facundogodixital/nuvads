import _ from 'lodash';
import axios from 'axios';
import RESTException from '@/js/exceptions/RESTException';


const handleResponse = response => {
  if (response && _.has(response, 'data.success') && _.has(response, 'data.error')) {
    if (!response.data.success) {
      throw new RESTException(response.data.error);
    }
    return response.data.data;
  }
  
  if (!response) {
    throw new RESTException({code: -1, message: 'unknown_rest_error'});
  }
  return null;
};


const handleErrorResponse = errResponse => {
  if (_.has(errResponse, 'response.data')) {
    const data = errResponse.response.data;
    const { code, message } = data.error;
    throw new RESTException({code, message});
  }

  throw new RESTException({code: -1, message: 'unknown_rest_error'});
};



const callGetEndpoint = async (endpoint, {params = {}} = {}) => {
  try {
    const response = await axios.get(endpoint, params);
    return handleResponse(response);
  } catch (errResponse) {
    handleErrorResponse(errResponse);
  }
};

const callPostEndpoint = async (endpoint, {params = {}} = {}) => {
  try {
    const response = await axios.post(endpoint, params);
    return handleResponse(response);
  } catch (errResponse) {
    handleErrorResponse(errResponse);
  }
};

const callPutEndpoint = async (endpoint, {params = {}} = {}) => {
  try {
    const response = await axios.put(endpoint, params);
    return handleResponse(response);
  } catch (errResponse) {
    handleErrorResponse(errResponse);
  }
};

const callDeleteEndpoint = async (endpoint, {params = {}} = {}) => {
  try {
    const response = await axios.delete(endpoint, params);
    return handleResponse(response);
  } catch (errResponse) {
    handleErrorResponse(errResponse);
  }
};

export {
  callGetEndpoint,
  callPostEndpoint,
  callPutEndpoint,
  callDeleteEndpoint,
};