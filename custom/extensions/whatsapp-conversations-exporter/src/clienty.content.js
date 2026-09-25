import Store from '@/js/helpers/Store';
import MessagePayload from '@/js/helpers/MessagePayload';


const today = new Date().toISOString();
if (process.env.NODE_ENV != 'production') {
  console.log('[clienty.content.js]', today);
}

const getClientyStoredLoginObject = () => {
  if (!_.has(localStorage, 'login')) {
    return null;
  }
  try {
    const loginObj = JSON.parse(localStorage.login);
    return loginObj ? loginObj : null;
  } catch(e) {
    return null;
  }
}


(async () => {
  let clientyLoginData = getClientyStoredLoginObject();
  console.log('[clienty.content.js] clientyLoginData', clientyLoginData);

  if (clientyLoginData) {
    const store = await Store.build();
    await store.set('clientyUrl', location.href);
    await store.set('clientyLoginData', clientyLoginData);
    await store.saveToStorage();
    
    console.log('clientyUrl', location.href);
  }
})();





chrome.runtime.onMessage.addListener((payload, sender, sendResponse) => {
  // console.log('CLIENTY SCRIPT LISTENER: Message received', payload);
  const messagePayload = MessagePayload.buildFromJson(payload);
  if (messagePayload.isMessageToClientyContent()) {

    if (messagePayload.isRequestClientyClientDataMsg()) {
      (async () => {
        const loginData = getClientyStoredLoginObject();
        sendResponse({success: true, data: loginData});
      })();
      return true;
    }
  }
  sendResponse({success: true});
});


// Para enviar evento desde Clienty:
// var event = document.createEvent('Event');
// event.initEvent('ClientyWebToClientyWapSenderMessage');
// window.dispatchEvent(event);

// Para recibir evento enviado desde Clienty:
// window.addEventListener("ClientyWebToClientyWapSenderMessage", (event) => {
//   console.log("Content SCRIPT EXTENSION received: ", event);
// }, false);