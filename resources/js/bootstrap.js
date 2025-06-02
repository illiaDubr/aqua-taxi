import Echo from 'laravel-echo';
import ReverbConnector from './reverb-connector';
import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.Echo = new Echo({
    broadcaster: ReverbConnector,
    key: 'aqua_key', // 👈 обязательно!
    wsHost: window.location.hostname,
    wsPort: 6001,
    wssPort: 6001,
    forceTLS: false,
    disableStats: true,
    enabledTransports: ['ws'],
});
