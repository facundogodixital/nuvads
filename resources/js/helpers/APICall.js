import axios from 'axios';
import APIError from '@/classes/APIError';

// Dónde se guarda el token de sesión y a dónde se redirige si la API
// responde que no hay sesión. Ajustar cuando exista el login.
const AUTH_TOKEN_STORAGE_KEY = 'auth_token';
const LOGIN_PATH = '/login';

const METHODS_WITH_BODY = ['post', 'put', 'patch'];

const http = axios.create({
  headers: { Accept: 'application/json' },
});

// Si hay token guardado se envía como Bearer. Si no hay, no se envía nada.
http.interceptors.request.use((config) => {
  const token = getAuthToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});


// Llamada JSON. Devuelve el contenido de data.
export async function APICall(endpoint, method = 'get', params = {}, opts = {}) {
  const response = await send(buildRequest(endpoint, method, params));
  return response.data.data;
}


// Envía un archivo como multipart junto con params. Siempre POST.
// opts.fileFieldName: nombre del campo del archivo. Por defecto 'file'.
export async function APIUpload(endpoint, fileToUpload, params = {}, opts = {}) {
  const { fileFieldName = 'file' } = opts;

  const formData = new FormData();
  formData.append(fileFieldName, fileToUpload);
  for (const [key, value] of Object.entries(params)) {
    formData.append(key, value);
  }

  const response = await send({ url: endpoint, method: 'post', data: formData });
  return response.data.data;
}


// Devuelve el archivo como blob. El service decide qué hacer con él.
export async function APIDownload(endpoint, method = 'get', params = {}, opts = {}) {
  const response = await send(buildRequest(endpoint, method, params, { responseType: 'blob' }));
  return response.data;
}


// En GET y DELETE los params van a la query string; en POST, PUT y PATCH
// van al cuerpo como JSON.
function buildRequest(endpoint, method, params, extra = {}) {
  const normalizedMethod = method.toLowerCase();
  const sendsBody = METHODS_WITH_BODY.includes(normalizedMethod);

  return {
    url: endpoint,
    method: normalizedMethod,
    ...(sendsBody ? { data: params } : { params }),
    ...extra,
  };
}


async function send(config) {
  try {
    return await http.request(config);
  } catch (error) {
    const apiError = await toAPIError(error);

    if (apiError.status === 401 && apiError.code === 'unauthenticated') {
      redirectToLogin();
    }

    throw apiError;
  }
}


// Convierte el error de axios en un APIError con el formato de la API.
async function toAPIError(error) {
  const response = error.response;

  if (!response) {
    return new APIError({
      status: 0,
      code: 'network_error',
      message: 'No se pudo conectar con el servidor.',
    });
  }

  const body = await readErrorBody(response.data);

  return new APIError({
    status: response.status,
    code: body.code ?? 'http_error',
    message: body.message ?? 'No se pudo completar la solicitud.',
    errors: body.errors ?? null,
  });
}


// En una descarga (responseType blob) el cuerpo del error llega como Blob
// aunque sea JSON. Acá se lee para poder interpretarlo igual.
async function readErrorBody(data) {
  if (!(data instanceof Blob)) {
    return data ?? {};
  }

  try {
    return JSON.parse(await data.text());
  } catch {
    return {};
  }
}


function getAuthToken() {
  try {
    return localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);
  } catch {
    return null;
  }
}


function redirectToLogin() {
  if (window.location.pathname !== LOGIN_PATH) {
    window.location.assign(LOGIN_PATH);
  }
}
