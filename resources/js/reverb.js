import Echo from 'laravel-echo';
import ReverbConnector from './reverb-connector';

window.Echo = new Echo({
    broadcaster: ReverbConnector,
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
    disableStats: true,
});
