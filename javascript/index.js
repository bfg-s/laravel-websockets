import EchoClass from 'laravel-echo';

window.Pusher = require('pusher-js');

const dispatchEvent = (name, detail) => {
    document.dispatchEvent(new CustomEvent(`lws:${name}`, {detail}));
};

window.WebSocketConnect = (options) => {
    if (window.Echo) {
        throw "Echo listener already exists!";
    }
    window.UID = options.user_id;
    let token = document.head.querySelector('meta[name=csrf-token]');
    let ssl = options.ssl !== undefined ? options.ssl : location.protocol === 'https:';
    window.Echo = new EchoClass({
        broadcaster: 'pusher',
        key: options.key ? options.key : null,
        cluster: options.cluster ? options.cluster : 'mt1',
        authEndpoint: "http" +(ssl ? 's://':'://') + (options.host ? options.host : window.location.host)
            + (options.authEndpoint ? options.authEndpoint : '/broadcasting/auth'),
        wsHost: options.host ? options.host : window.location.host,
        wsPort: options.port ? options.port : 6001,
        wssPort: options.port ? options.port : 6001,
        forceTLS: ssl,
        csrfToken: options.token ? options.token : (token ? token.content : null),
        disableStats: options.disable_stats !== undefined ? options.disable_stats : true,
        encrypted: options.encrypted !== undefined ? options.encrypted : true,
        enabledTransports: ['wss', 'ws'],
        disabledTransports: ['sockjs', 'xhr_polling', 'xhr_streaming'],
        namespace: options.namespace ? options.namespace : 'App\\Events',
        auth: {
            params: options.params ? options.params : {},
            headers: options.headers ? options.headers : {}
        }
    });

    delete window.WebSocketConnect;
    window.VueEchoMutator = require('./VueMutator')(window.Echo, options.user_id);
    window.VueEchoMixin = require('./VueMixin')(window.Echo, options.user_id);
    dispatchEvent("created", Echo);
    return window.Echo;
};
