export default class ReverbConnector {
    constructor(options) {
        this.options = options;
        this.channels = {};
        this.connect();
    }

    connect() {
        const protocol = this.options.forceTLS ? 'wss' : 'ws';
        const path = `/app/${this.options.key}`; // 🔥 это важно
        const url = `${protocol}://${this.options.wsHost}:${this.options.wsPort}${path}`;

        this.socket = new WebSocket(url);

        this.socket.onopen = () => {
            console.log('[ReverbConnector] Connected to', url);
        };

        this.socket.onerror = (e) => {
            console.error('[ReverbConnector] WebSocket error:', e);
        };

        this.socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                const { channel, event: eventName, payload } = data;

                const subscribed = this.channels[channel];
                if (subscribed && subscribed.events[eventName]) {
                    subscribed.events[eventName](payload);
                }
            } catch (e) {
                console.error('[ReverbConnector] Failed to parse message:', e);
            }
        };

        this.socket.onclose = () => {
            console.warn('[ReverbConnector] WebSocket closed');
        };
    }

    _formatChannelName(name, type = 'public') {
        if (type === 'private') return `private-${name}`;
        if (type === 'presence') return `presence-${name}`;
        return name;
    }

    _subscribe(name, type = 'public') {
        const channelName = this._formatChannelName(name, type);

        if (!this.channels[channelName]) {
            this.channels[channelName] = {
                name: channelName,
                events: {},
                listen: (event, callback) => {
                    this.channels[channelName].events[event] = callback;
                    return this.channels[channelName];
                },
            };
        }

        return this.channels[channelName];
    }

    channel(name) {
        return this._subscribe(name, 'public');
    }

    privateChannel(name) {
        return this._subscribe(name, 'private');
    }

    presenceChannel(name) {
        return this._subscribe(name, 'presence');
    }

    leave(name) {
        const channelName = this._formatChannelName(name);
        delete this.channels[channelName];
    }

    disconnect() {
        if (this.socket) {
            this.socket.close();
            this.socket = null;
        }
        this.channels = {};
    }
    socketId() {
        return this.socket && this.socket.id ? this.socket.id : null;
    }
}
