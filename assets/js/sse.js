const SSE = (() => {
    let es = null;
    let handlers = {};
    let reconnectTimer = null;
    let reconnectDelay = 2000;
    let active = false;

    function on(event, fn) {
        if (!handlers[event]) handlers[event] = [];
        handlers[event].push(fn);
    }

    function off(event, fn) {
        if (!handlers[event]) return;
        handlers[event] = handlers[event].filter(h => h !== fn);
    }

    function emit(event, data) {
        (handlers[event] || []).forEach(fn => {
            try { fn(data); } catch(e) { console.error('[SSE] Handler error:', e); }
        });
    }

    function connect() {
        if (es) { es.close(); es = null; }

        const token = localStorage.getItem('token');
        if (!token) return;

        active = true;
        const url = `/api/sse?token=${encodeURIComponent(token)}`;
        es = new EventSource(url);

        es.addEventListener('connected', e => {
            reconnectDelay = 2000;
            emit('connected', JSON.parse(e.data));
        });

        es.addEventListener('queue', e => emit('queue', JSON.parse(e.data)));
        es.addEventListener('orders_updated', e => emit('orders_updated', JSON.parse(e.data)));
        es.addEventListener('my_orders_updated', e => emit('my_orders_updated', JSON.parse(e.data)));
        es.addEventListener('stats_updated', e => emit('stats_updated', JSON.parse(e.data)));
        es.addEventListener('products_updated', e => emit('products_updated', JSON.parse(e.data)));
        es.addEventListener('reconnect', () => scheduleReconnect());

        es.onerror = () => {
            if (!active) return;
            es.close();
            es = null;
            scheduleReconnect();
        };
    }

    function scheduleReconnect() {
        if (!active) return;
        clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(() => {
            reconnectDelay = Math.min(reconnectDelay * 1.5, 30000);
            connect();
        }, reconnectDelay);
    }

    function disconnect() {
        active = false;
        clearTimeout(reconnectTimer);
        if (es) { es.close(); es = null; }
        handlers = {};
    }

    return { connect, disconnect, on, off };
})();