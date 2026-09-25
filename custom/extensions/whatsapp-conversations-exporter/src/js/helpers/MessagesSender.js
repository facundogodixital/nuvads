import _ from 'lodash';
import TabsHelper from '@/js/helpers/TabsHelper';
import MessagePayload from '@/js/helpers/MessagePayload';
import ScriptIdentifier from '@/js/helpers/ScriptIdentifier';
import MessagesSenderException from '@/js/exceptions/MessagesSenderException';


const sendMsgToContent = async (tab, payload) => {
  return new Promise((resolve, reject) => {
    if (!tab || !tab.id) {
      const exception = MessagesSenderException.buildNotOpenedTabType({payload});
      reject(exception);
    }
    chrome.tabs.sendMessage(tab.id, payload.toJson(), (response) => {
      if (response != undefined) {
        resolve(response);
      } else {
        const exception = MessagesSenderException.buildUnreachableContentType({payload});
        reject(exception);
      }
    });
  });
};

const sendMsgToWapContent = async (msgCode, data) => {
  const sender = ScriptIdentifier.identifyCurrent();
  const receiver = ScriptIdentifier.getContentIdentifier();
  const receiverName = ScriptIdentifier.getWhatsAppContentIdentifier();
  const payload = new MessagePayload(sender, receiver, msgCode, data, receiverName);
  
  const wapTab = await TabsHelper.getWhatsAppTab();
  const response = await sendMsgToContent(wapTab, payload);
  return response;
};

const sendMsgToClientyContent = async (msgCode, data) => {
  const sender = ScriptIdentifier.identifyCurrent();
  const receiver = ScriptIdentifier.getContentIdentifier();
  const receiverName = ScriptIdentifier.getClientyContentIdentifier();
  const payload = new MessagePayload(sender, receiver, msgCode, data, receiverName);
  
  const clientyTab = await TabsHelper.getClientyTab();
  const response = await sendMsgToContent(clientyTab, payload);
  return response;
};


const sendMsgToPopUp = async (msgCode, data) => {
  const sender = ScriptIdentifier.identifyCurrent();
  const receiver = ScriptIdentifier.getPopUpIdentifier();
  const payload = new MessagePayload(sender, receiver, msgCode, data);
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(payload.toJson(), (response) => {
      resolve(response);
    });
  });
};
const sendMsgToBackground = async (msgCode, data) => {
  const sender = ScriptIdentifier.identifyCurrent();
  const receiver = ScriptIdentifier.getBackgroundIdentifier();
  const payload = new MessagePayload(sender, receiver, msgCode, data);
  return new Promise((resolve, reject) => {
    chrome.runtime.sendMessage(payload.toJson(), (response) => {
      resolve(response);
    });
  });
};


const MessagesSender = {
  toWapContent: {
    getSeedChats: async (data) => {
      await sendMsgToWapContent('getSeedChats', data);
    },
  },
  toClientyContent: {
    // requestClientyLoginData: async () => {
    //   const response = await sendMsgToClientyContent(REQUEST_CLIENTY_CLIENT_DATA_MESSAGE_CODE, {});
    //   return response;
    // },
  },
  toPopUp: {
    // test: async () => (await sendMsgToPopUp('TEST', {})),
  },
  toBackground: {
    getSeedChatsComplete: async (responseData) => {
      console.log('MessagesSender - getSeedChatsComplete()', responseData);
      await sendMsgToBackground('getSeedChatsComplete', responseData);
    },
    exportProgress: async (progressData) => {
      console.log('MessagesSender - exportProgress()', progressData);
      await sendMsgToBackground('exportProgress', progressData);
    },
    getStatusFromBackground: async () => {
      return await sendMsgToBackground('getStatusFromBackground', {});
    },
    startExportChats: async () => {
      return await sendMsgToBackground('startExportChats', {});
    },
  },
};



export default MessagesSender;