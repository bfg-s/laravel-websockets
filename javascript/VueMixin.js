module.exports = (Echo, user_id) => {

    return {
        echo: null,
        computed: {
            $echo () { return Echo; },
            $uid () { return user_id; },
        },
        beforeMount () {
            if (this.$options.echo && typeof this.$options.echo === 'function') {
                this.$options.echo = this.$options.echo();
            }
        },
        mounted () {
            if (typeof this.$options.echo === 'object') {
                Object.keys(this.$options.echo).map(k => {
                    let mk = /([a-z]+)-([^:]+):(.+)/.exec(k);
                    if (mk) {
                        Echo[mk[1]](mk[2]).listen(mk[3], echos[k]);
                    }
                });
            }
        }
    };
};
