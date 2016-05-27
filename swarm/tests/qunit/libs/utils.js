/* SWARM TEST UTILITIES */
swarm.test = {
    queue:      [],
    autoStart:  false,

    reset: function() {
        this.queue     = [];
        this.autoStart = false;
    },

    nextQueued: function() {
        this.queue.shift();

        if (this.queue.length) {
            this.queue[0]();
            return;
        }

        if (this.autoStart) {
            this.autoStart = false;
            start();
        }
    },

    push: function(callback, args) {
        var proxyArgs = [callback, QUnit.config.current].concat(args || []);
        this.queue.push($.proxy.apply($, proxyArgs));
    },

    start: function() {
        if (this.queue.length) {
            this.autoStart = true;
            this.queue[0]();
            return;
        }

        start();
    }
};

// expose for easy use in tests
window.queueStart = $.proxy(swarm.test.start, swarm.test);