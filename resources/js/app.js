import { createApp } from 'vue';
import App from './App.vue';
import '../css/app.css';

const appElement = document.getElementById('app');
const page = JSON.parse(appElement.dataset.page);

createApp(App, page).mount(appElement);
