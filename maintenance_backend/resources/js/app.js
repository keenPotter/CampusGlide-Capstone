import { createApp } from 'vue';
import axios from 'axios';
import MaintenanceDashboard from './components/MaintenanceDashboard.vue';
import './style.css';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
createApp(MaintenanceDashboard).mount('#app');
