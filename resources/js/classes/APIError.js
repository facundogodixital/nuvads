// Error que lanzan APICall, APIUpload y APIDownload cuando la API responde
// con un estado de error o cuando no responde. Refleja el formato de error
// de la API: code y message en la raíz, y errors solo en validación.

export default class APIError extends Error {

  constructor({ status, code, message, errors = null }) {
    super(message);
    this.name = 'APIError';
    this.status = status;   // Estado HTTP. 0 cuando el servidor no respondió.
    this.code = code;       // Identificador estable del problema, en snake_case.
    this.errors = errors;   // Errores por campo en validación, si no null.
  }

}
