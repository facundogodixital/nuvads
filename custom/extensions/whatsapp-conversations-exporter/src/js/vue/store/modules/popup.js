import Vue from 'vue';
import _ from 'lodash';

const state = {
};


const mutations = {
  set (state, { propName, prop }) {
    Vue.set(state, propName, prop);
  },
};


const actions = {
  showPageLoader({ state, commit, dispatch }) {
    commit('set', {propName: 'showPageLoader', prop: true});
  },
  hidePageLoader({ state, commit, dispatch }) {
    commit('set', {propName: 'showPageLoader', prop: false});
  },
};


const getters = {  
  showPageLoader (state, getters, rootState) {
    return state.showPageLoader;
  },
};


export default {
  state,
  actions,
  getters,
  mutations,
  namespaced: true,
}