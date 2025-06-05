import Echo from 'laravel-echo';
import ReverbConnector from './reverb-connector';

window.Echo = new Echo({
    broadcaster: ReverbConnector,
    key: '123456789abcdef', // ✅ исправили
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
    enabledTransports: ['ws'],
});
