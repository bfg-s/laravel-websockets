module.exports = (Echo, user_id) => {

    return {
        _channel_instance: null,
        default_user_event_channel: `App.Models.User.${user_id}`,
        channelType: 'private',
        channel: null,
        listen: null,
        listenForWhisper: null,
        echo: null,
        userListen: null,
        notificationListen: null,
        computed: {
            $uid () { return user_id; },
            $echo () { return Echo; },
            $channel () {
                let channelName = this.$channelName;
                return channelName ? (this.$options._channel_instance ? this.$options._channel_instance
                    : this.$options._channel_instance = this.$echo[this.$options.channelType](channelName)) : null;
            },
            $channelName () {
                let channel = this.$options.channel && this.$options.channelType
                    ? (typeof this.$options.channel === 'function' ? this.$options.channel() : this.$options.channel)
                    : null;
                return channel && Array.isArray(channel) ? channel.join('.') : channel;
            },
        },
        beforeMount () {
            if (typeof this.$options.default_user_event_channel === 'function') {
                this.$options.default_user_event_channel = this.$options.default_user_event_channel();
            }
            if (this.$options.userListen) {
                if (typeof this.$options.userListen === 'object') {
                    Object.keys(this.$options.userListen).map((k) => {
                        Echo.private(this.$options.default_user_event_channel)
                            .listen(k, this.$options.userListen[k]);
                    });
                }
            }
            if (this.$options.notificationListen) {
                if (typeof this.$options.notificationListen === 'function') {
                    Echo.private(this.$options.default_user_event_channel)
                        .notification(this.$options.notificationListen);
                }
            }
            if (this.$options.echo) {
                let echos = typeof this.$options.echo === 'function' ? this.$options.echo() : this.$options.echo;
                if (typeof echos === 'object') {
                    Object.keys(echos).map((k) => {
                        let mk = /([a-z]+)-([^:]+):(.+)/.exec(k);
                        if (mk) {
                            Echo[mk[1]](mk[2]).listen(mk[3], echos[k]);
                        }
                    });
                }
            }
            if (this.$channel) {
                if (this.$options.channelType === 'join') {
                    if (this.here && typeof this.here === 'function') {
                        this.$channel.here(this.here)
                    }
                    if (this.joining && typeof this.joining === 'function') {
                        this.$channel.joining(this.joining)
                    }
                    if (this.leaving && typeof this.leaving === 'function') {
                        this.$channel.leaving(this.leaving)
                    }
                }
                if (this.$options.listen) {
                    Object.keys(this.$options.listen).map((key) => {
                        this.$channel.listen(key, this.$options.listen[key])
                    });
                }
                if (this.$options.listenForWhisper) {
                    Object.keys(this.$options.listenForWhisper).map((key) => {
                        this.$channel.listenForWhisper(key, this.$options.listenForWhisper[key])
                    });
                }
            }
        },
        beforeDestroy () {
            if (this.$options.notificationListen) {
                this.$echo.leaveChannel(this.$options.default_user_event_channel);
            }
            if (this.$options.echo) {
                let echos = typeof this.$options.echo === 'function' ? this.$options.echo() : this.$options.echo;
                if (typeof echos === 'object') {
                    Object.keys(echos).map((k) => {
                        let mk = /([a-z]+)-([^:]+):(.+)/.exec(k);
                        if (mk) {
                            this.$echo.leaveChannel(mk[2]);
                        }
                    });
                }
            }
            if (this.$channel && this.$options.listen) {
                Object.keys(this.$options.listen).map((key) => {
                    this.wsStopListening(key)
                });
                this.wsLeaveChannel();
            }
        },
        methods: {
            wsWhisper (name, data) {
                if (this.$channel) {
                    this.$channel.whisper(name, data)
                    return true;
                }
                return false;
            },
            wsStopListening (name) {
                if (this.$channel) {
                    this.$channel.stopListening(name)
                    return true;
                }
                return false;
            },
            wsLeaveChannel () {
                if (this.$channelName) {
                    this.$echo.leaveChannel(this.$channelName)
                    return true;
                }
                return false;
            },
            wsLeave () {
                if (this.$channelName) {
                    this.$echo.leave(this.$channelName)
                    return true;
                }
                return false;
            }
        }
    };
};
