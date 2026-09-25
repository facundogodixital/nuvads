import '@/popup.css';
import Vue from 'vue';
import jQuery from 'jquery';
import store from '@/js/vue/store';
import BootstrapVue from 'bootstrap-vue';
import 'bootstrap/dist/css/bootstrap.min.css';
import PopUpPage from '@/js/vue/components/PopUpPage.vue';

Vue.use(BootstrapVue);

// Vue.component(PopUpPage.name, PopUpPage);


// Pasar a true para production
Vue.config.devtools = false;
Vue.config.productionTip = true;


Vue.config.errorHandler = function (err, vm, info) {
  console.log('=============================- ERROR HANDLER -=============================');
  console.log({err, vm, info});
  console.log('=============================- /ERROR HANDLER -=============================');
  store.dispatch('popup/hidePageLoader');
  throw err;
}

const vm = new Vue({
  store,
  el: '#app',
  render: (createElement) => {
    return createElement(PopUpPage)
  },
});
