const identifyCurrent = () => {
  if (chrome && chrome.extension && chrome.extension.getBackgroundPage) {
    return 'PopUpScript';
  }
  if (chrome && chrome.extension && chrome.tabs) {
    return 'BackgroundScript';
  }

  if (location.href.indexOf('web.whatsapp.com') !== -1) {
    return 'WebWhatsAppContentScript';
  }
  if (location.href.indexOf('.clienty.') !== -1) {
    return 'ClientyContentScript';
  }

  return 'ContentScript';
};

const isPopUp = () => {
  return identifyCurrent() == getPopUpIdentifier();
};
const isBackground = () => {
  return identifyCurrent() == getBackgroundIdentifier();
};
const isClientyContent = () => {
  return identifyCurrent() == getClientyContentIdentifier();
};
const isWhatsAppContent = () => {
  return identifyCurrent() == getWhatsAppContentIdentifier();
};
const isContent = () => {
  return identifyCurrent() == (getContentIdentifier() || getClientyContentIdentifier() || getWhatsAppContentIdentifier());
};

// Deprecados
const getPopUpIdentifier = () => 'PopUpScript';
const getContentIdentifier = () => 'ContentScript';
const getBackgroundIdentifier = () => 'BackgroundScript';

const getClientyContentIdentifier = () => 'ClientyContentScript';
const getWhatsAppContentIdentifier = () => 'WebWhatsAppContentScript';


const ScriptIdentifier = {
  isPopUp,
  isContent,
  isBackground,
  identifyCurrent,
  isClientyContent,
  isWhatsAppContent,
  getPopUpIdentifier,
  getContentIdentifier,
  getBackgroundIdentifier,
  getClientyContentIdentifier,
  getWhatsAppContentIdentifier,
};
export default ScriptIdentifier;