import Vue from 'vue';
import _ from 'lodash';
import axios from 'axios';
import Store from '@/js/helpers/Store';
import TabsHelper from '@/js/helpers/TabsHelper';
import StorageHelper from '@/js/helpers/StorageHelper';
import TabException from '@/js/exceptions/TabException';
import { MAX_PHONES_PER_SENDING } from '@/js/constants';
import MessagesSender from '@/js/helpers/MessagesSender';
import ScriptIdentifier from '@/js/helpers/ScriptIdentifier';
import ClientyDataHelper from '@/js/helpers/ClientyDataHelper';
import ClientyDataException from '@/js/exceptions/ClientyDataException';
import APIConnectionException from '@/js/exceptions/APIConnectionException';
import MessagesSenderException from '@/js/exceptions/MessagesSenderException';
import ClientyWhatsAppSendingService from '@/js/services/ClientyWhatsAppSendingService';


const state = {
  phonesMap: {},
  chatMessage: '',
  lastSending: null,
  dailyUsedQuota: 0,
  dailyUserQuota: 0,
  currentSending: null,
  showPageLoader: false,
  dailyRemainingQuota: 0,
  isVersionUpToDate: true,
  clientyTabIsOpened: false,
  whatsAppTabIsOpened: false,
  clientyUrlIsNotUnique: false,
  quotaPerSending: MAX_PHONES_PER_SENDING,
  errors: [
    // {hasToDisableSendButton, hasToBlockPopUp, message}
  ],

  // Store.js shared data
  clientyUrl: null,
  clientyLoginData: null,
};


const mutations = {
  set (state, { propName, prop }) {
    Vue.set(state, propName, prop);
  },
  addError (state, { hasToDisableSendButton, hasToBlockPopUp, message }) {
    const error = {hasToDisableSendButton, hasToBlockPopUp, message};
    state.errors.push(error);
  },
  resetErrors(state) {
    Vue.set(state, 'errors', []);
  },
};


const actions = {
  showPageLoader({ commit }) {
    commit('set', {propName: 'showPageLoader', prop: true});
  },
  hidePageLoader({ commit }) {
    commit('set', {propName: 'showPageLoader', prop: false});
  },

  async loadAllInfo({ state, commit, dispatch }) {
    commit('resetErrors');
    // Ojo: correr en orden.
    try {
      await dispatch('validateOpenedTabs');
      await dispatch('validateReachableWhatsAppWeb');
      await dispatch('validateClientyLoginData');
      await dispatch('saveStoreSharedData');
      await dispatch('loadStateDataFromServer');
      await dispatch('validateVersionUpToDate');
      await dispatch('loadPhonesAndMessageFromStorage');
    } catch(err) {
      dispatch('handleExceptions', err);
    }
  },

  async loadStateDataFromServer({ state, commit, dispatch }) {
    try {
      const service = ClientyWhatsAppSendingService.getInstance();
      const popUpInfo = await service.getPopUpInfo();
      commit('set', {propName: 'lastSending', prop: _.get(popUpInfo, 'lastSending')});
      commit('set', {propName: 'currentSending', prop: _.get(popUpInfo, 'currentSending')});
      commit('set', {propName: 'dailyUsedQuota', prop: _.get(popUpInfo, 'dailyUsedQuota', 0)});
      commit('set', {propName: 'dailyUserQuota', prop: _.get(popUpInfo, 'dailyUserQuota', 0)});
      commit('set', {propName: 'dailyRemainingQuota', prop: _.get(popUpInfo, 'dailyRemainingQuota', 0)});
      commit('set', {propName: 'isVersionUpToDate', prop: _.get(popUpInfo, 'isVersionUpToDate', false)});
      commit('set', {propName: 'quotaPerSending', prop: _.get(popUpInfo, 'quotaPerSending', MAX_PHONES_PER_SENDING)});
    } catch(err) {
      throw APIConnectionException.buildPopUpInfoServiceUnavailableType();
    }
  },

  // No se está usando.
  async loadStateDataFromStorage({ state, commit, dispatch }) {
    const store = await Store.build();
    for (const propName in store.state) {
      commit('set', {propName, prop: store.state[propName]});
    }
  },

  async loadClientyLoginData({ state, commit, dispatch}) {
    const clientyLoginData = await ClientyDataHelper.getLoginData();
    commit('set', {propName: 'clientyLoginData', prop: clientyLoginData});
  },


  async validateClientyLoginData({ getters, dispatch}) {
    await dispatch('loadClientyLoginData');
    if (!getters.isExtensionEnabled) {
      throw ClientyDataException.buildNotEnabledExtensionType();
    }
  },

  async validateVersionUpToDate({ state, dispatch}) {
    if (!state.isVersionUpToDate) {
      throw ClientyDataException.buildOutdatedAppVersionType();
    }
  },

  async validateOpenedTabs({ state, dispatch}) {
    await dispatch('loadOpenedTabsInfo');
    
    if (state.clientyUrlIsNotUnique) {
      throw TabException.buildClientyUrlNotUniqueType();
    }
    if (!state.clientyTabIsOpened) {
      throw TabException.buildClientyNotOpenedTabType();
    }
    if (!state.whatsAppTabIsOpened) {
      throw TabException.buildWhatsAppNotOpenedTabType();
    }
  },

  async validateReachableWhatsAppWeb({ state, dispatch}) {
    // Tira MessagesSenderException (isUnreachableContentType) si no puede comunicar con wap web.
    await MessagesSender.toWapContent.test();
  },

  async loadOpenedTabsInfo({ state, commit, dispatch}) {
    const differentUrlClientyTabs = await TabsHelper.getClientyTabs({sortedById: true, uniqueDomain: true});
    const clientyUrlIsNotUnique = differentUrlClientyTabs.length > 1;
    commit('set', {propName: 'clientyUrlIsNotUnique', prop: clientyUrlIsNotUnique});

    const clientyTab = _.head(differentUrlClientyTabs) ?? null;
    const clientyTabIsOpened = clientyTab ? true : false;
    const whatsAppTabIsOpened = await TabsHelper.whatsAppTabIsOpened();
    
    commit('set', {propName: 'clientyTabIsOpened', prop: clientyTabIsOpened});
    commit('set', {propName: 'whatsAppTabIsOpened', prop: whatsAppTabIsOpened});
    const clientyUrl = clientyTabIsOpened ? clientyTab.url : null;
    commit('set', {propName: 'clientyUrl', prop: clientyUrl});
  },

  async saveStoreSharedData({ state, commit, dispatch }) {
    const store = await Store.build();
    store.set('clientyUrl', state.clientyUrl);
    store.set('clientyLoginData', state.clientyLoginData);
    await store.saveToStorage();
  },

  async savePhonesMapToStorage({ state, commit, dispatch }) {
    const store = await Store.build();
    store.set('phonesMap', state.phonesMap);
    await store.saveToStorage();
  },
  async saveChatMessageToStorage({ state, commit, dispatch }) {
    const store = await Store.build();
    store.set('chatMessage', state.chatMessage);
    await store.saveToStorage();
  },
  async removeChatMessageFromStorage({ state, commit, dispatch }) {
    const store = await Store.build();
    store.remove('chatMessage');
    await store.saveToStorage();
  },
  async removePhonesMapFromStorage({ state, commit, dispatch }) {
    const store = await Store.build();
    store.remove('phonesMap');
    await store.saveToStorage();
  },
  async loadPhonesAndMessageFromStorage({ state, commit, dispatch }) {
    const store = await Store.build();
    const phonesMap = store.get('phonesMap');
    const chatMessage = store.get('chatMessage');
    if (phonesMap) {
      dispatch('setPhonesMap', phonesMap);
    }
    if (chatMessage) {
      dispatch('setChatMessage', chatMessage);
    }
  },

  async startSending({ getters, commit, dispatch }) {
    const validated = await dispatch('validateSending', {isNewSending: true});
    if (!validated) {
      return false;
    }
    // const phoneNumbers = Object.values(getters.phonesMap);
    const chatMessage = getters.chatMessage;
    await MessagesSender.toWapContent.startSendingProcess({phonesMap: getters.phonesMap, chatMessage});
    await dispatch('removePhonesMapFromStorage');
    return true;
  },

  async resumeSending({ state, commit, dispatch }) {
    const validated = await dispatch('validateSending', {isNewSending: false});
    if (!validated) {
      return false;
    }
    await MessagesSender.toWapContent.resumeSendingProcess();
  },

  async cancelSending({ state, commit, dispatch }) {
    await MessagesSender.toWapContent.cancelSendingProcess();
  },

  async pauseSending({ state, commit, dispatch }) {
    await MessagesSender.toWapContent.pauseSendingProcess();
  },

  async validateSending({ state, getters, dispatch, commit }, { isNewSending }) {
    commit('resetErrors');

    await dispatch('loadOpenedTabsInfo');
    if (!state.whatsAppTabIsOpened) {
      dispatch('addError', {message: 'No se puede enviar: debes tener abierto WhatsApp Web en una pestaña para poder hacer un envío.'});
      return false;
    }
    if (!state.clientyTabIsOpened) {
      dispatch('addError', {message: 'No se puede enviar: debes tener abierto Clienty en una pestaña para poder hacer un envío.'});
      return false;
    }

    await dispatch('loadClientyLoginData');
    const clientyLoginClientSettings = getters.clientyLoginClientSettings;
    if (!clientyLoginClientSettings) {
      dispatch('addError', {message: 'No se puede enviar: debes estar logueado en Clienty para poder hacer un envío.'});
      return false;
    }

    if (!getters.isExtensionEnabled) {
      dispatch('addError', {message: 'No se puede enviar: tu cuenta de Clienty no tiene habilitado este plugin.'});
      return false;
    }

    if (isNewSending && getters.currentSending) {
      dispatch('addError', {message: 'No se puede enviar: ya hay un envío en curso.'});
      return false;
    }
    const phoneNumbers = [...Object.keys(getters.phonesMap)];
    if (!phoneNumbers || !phoneNumbers.length) {
      dispatch('addError', {message: 'No se puede enviar: debes ingresar al menos un número de teléfono destinatario válido.'});
      return false;
    }

    if (!getters.chatMessage || !getters.chatMessage.length) {
      dispatch('addError', {message: 'No se puede enviar: debes ingresar el mensaje.'});
      return false;
    }

    const phonesCount = phoneNumbers.length;
    if (phonesCount > getters.dailyRemainingQuota) {
      dispatch(
        'addError',
        {message: `No se puede enviar: estás intentando realizar un envío de ${phonesCount} mensajes, lo cual supera los ${getters.dailyRemainingQuota} restantes habilitados para el día de hoy`}
      );
      return false;
    }
    if (phonesCount > getters.quotaPerSending) {
      dispatch(
        'addError',
        {message: `No se puede enviar: estás intentando realizar un envío de ${phonesCount} mensajes, el máximo permitido por envío es de ${getters.quotaPerSending}`}
      );
      return false;
    }

    try {
      await dispatch('validateReachableWhatsAppWeb');
    } catch(err) {
      dispatch('handleExceptions', err);
      return false;
    }

    return true;
  },


  handleExceptions({ state, commit, dispatch }, exception) {
    console.log('store.popup.handleExceptions.exception', exception)

    if (exception instanceof MessagesSenderException) {
      if (exception.isMessageToClientyContent()) {
        if (exception.isNotOpenedTabType()) {
          const message = 'Debes tener abierto Clienty en una pestaña para poder hacer un envío';
          const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
          dispatch('addError', error);
        }

        if (exception.isUnreachableContentType()) {
          const message = 'No se puede conectar con Clienty. Intenta actualizar la pestaña donde está abierto.';
          const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
          dispatch('addError', error);
        }
      }

      if (exception.isMessageToWhatsAppContent()) {
        if (exception.isUnreachableContentType()) {
          const message = 'No se puede conectar con WhatsApp Web. Intenta actualizar la pestaña donde está abierto.';
          const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
          dispatch('addError', error);
        }
      }
      return true;
    }

    if (exception instanceof TabException) {
      if (exception.isClientyUrlNotUniqueTabType()) {
        const message = 'Clienty está abierto con más de un cliente (debes dejar solo uno).';
        const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
        dispatch('addError', error);
      }

      if (exception.isClientyNotOpenedTabType()) {
        const message = 'Debes tener abierto Clienty en una pestaña para poder hacer un envío';
        const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
        dispatch('addError', error);
      }

      if (exception.isWhatsAppNotOpenedTabType()) {
        const message = 'Debes tener abierto WhatsApp Web en una pestaña para poder hacer un envío';
        const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
        dispatch('addError', error);
      }
      return true;
    }

    if (exception instanceof ClientyDataException) {
      if (exception.isNotEnabledExtensionType()) {
        const message = 'Tu cuenta de Clienty no tiene habilitado este plugin.';
        const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
        dispatch('addError', error);
      }

      if (exception.isOutdatedAppVersionType()) {
        const message = 'La versión del plugin ha quedado obsoleta: por favor actualizalo para poder utilizarlo.';
        const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
        dispatch('addError', error);
      }
      return true;
    }

    if (exception instanceof APIConnectionException) {
      if (exception.isPopUpInfoServiceUnavailableType()) {
        const message = 'No es posible conectarse a Clienty. Por favor da aviso a soporte.';
        const error = {hasToDisableSendButton: true, hasToBlockPopUp: true, message};
        dispatch('addError', error);
      }
      return true;
    }

    throw exception;
  },

  addError({ state, commit }, { message, hasToDisableSendButton = false, hasToBlockPopUp = false } = {}) {
    commit('addError', {message, hasToDisableSendButton, hasToBlockPopUp});
  },

  clearPhones({ state, commit, dispatch }) {
    commit('set', {propName: 'phonesMap', prop: {}});
  },
  setPhonesMap({ state, commit, dispatch }, phonesMap) {
    commit('set', {propName: 'phonesMap', prop: {...phonesMap}});
  },
  setChatMessage({ state, commit, dispatch }, chatMessage) {
    commit('set', {propName: 'chatMessage', prop: chatMessage});
  },
};


const getters = {  
  errors (state, getters, rootState) {
    return state.errors;
  },
  
  showPageLoader (state, getters, rootState) {
    return state.showPageLoader;
  },
  clientyLoginUser (state, getters, rootState) {
    return _.get(state, 'clientyLoginData.user', null);
  },
  clientyLoginClient (state, getters, rootState) {
    return _.get(state, 'clientyLoginData.client', null);
  },
  clientyLoginClientSettings (state, getters, rootState) {
    return _.get(state, 'clientyLoginData.clientSettings', null);
  },
  isExtensionEnabled (state, getters, rootState) {
    return _.get(getters.clientyLoginClientSettings, 'enable_whatsapp_sender_extension', null);
  },
  existsSendButtonBlockingError (state, getters, rootState) {
    const exists = state.errors.filter(e => e.hasToDisableSendButton).length ? true : false;
    return exists;
  },
  existsPopUpBlockingError (state, getters, rootState) {
    const exists = state.errors.filter(e => e.hasToBlockPopUp).length ? true : false;
    return exists;
  },

  currentSending (state, getters, rootState) {
    return state.currentSending ? state.currentSending : null;
  },

  lastSending (state, getters, rootState) {
    return state.lastSending ? state.lastSending : null;
  },
  dailyUsedQuota (state, getters, rootState) {
    return _.get(state, 'dailyUsedQuota', null);
  },
  dailyUserQuota (state, getters, rootState) {
    return _.get(state, 'dailyUserQuota', null);
  },
  dailyRemainingQuota (state, getters, rootState) {
    return _.get(state, 'dailyRemainingQuota', null);
  },
  quotaPerSending (state, getters, rootState) {
    return _.get(state, 'quotaPerSending', null);
  },
  
  currentSendingPhonesMap(state, getters) {
    if (!getters.currentSending) {
      return [];
    }
    const phonesMap = {};
    for (const wapMsg of getters.currentSending.whatsAppSendingMessages) {
      const leadId = wapMsg.lead_id; 
      const phoneNumber = wapMsg.phone_number; 
      phonesMap[phoneNumber] = {phoneNumber, leadId};
    }
    return phonesMap;
  },
  currentSendingChatMessage(state, getters) {
    if (!getters.currentSending) {
      return '';
    }
    return getters.currentSending.message;
  },

  phonesMap (state, getters, rootState) {
    return getters.currentSending ? getters.currentSendingPhonesMap : state.phonesMap;
  },
  chatMessage (state, getters, rootState) {
    return getters.currentSendingChatMessage ? getters.currentSendingChatMessage : state.chatMessage;
  },
};


export default {
  state,
  actions,
  getters,
  mutations,
  namespaced: true,
}