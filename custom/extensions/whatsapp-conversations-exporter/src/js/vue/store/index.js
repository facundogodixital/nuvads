import Vue from 'vue';
import Vuex from 'vuex';

// Register Vuex
Vue.use(Vuex);

import popup from './modules/popup';


// Vuex Store
export default new Vuex.Store({
  modules: {
    popup,
  }
});
