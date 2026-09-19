export function getLoginError(code) {
  if (!code) {
    return '';
  }

  const messages = {
    account_disabled: 'El acceso a esta cuenta está deshabilitado.',
    google_access_denied: 'No se completó el acceso con Google.',
    google_session_expired: 'El intento de acceso expiró. Vuelve a intentarlo.',
    google_response_invalid: 'La respuesta de acceso no es válida. Vuelve a intentarlo.',
    login_code_invalid: 'El intento de acceso expiró o no es válido. Vuelve a intentarlo.',
  };

  return Object.hasOwn(messages, code) ? messages[code] : 'No pudimos completar el acceso. Vuelve a intentarlo.';
}
