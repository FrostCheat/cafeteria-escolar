const SSE = (() => {
    let es = null, handlers = {}, reconnectTimer = null, reconnectDelay = 8000, active = false, connected = false;

    const on  = (event, fn) => { if (!handlers[event]) handlers[event] = []; handlers[event].push(fn); };
    const off = (event, fn) => { if (handlers[event]) handlers[event] = handlers[event].filter(h => h !== fn); };

    function emit(event, data) {
        (handlers[event] || []).forEach(fn => { try { fn(data); } catch(e) {} });
    }

    function connect() {
        const token = localStorage.getItem('token');
        if (!token) return;
        if (es) { es.close(); es = null; }
        active = true; connected = false;
        const url = '/api/sse?token=' + encodeURIComponent(token);
        try { es = new EventSource(url); } catch(e) { scheduleReconnect(); return; }

        const listen = (evt) => es.addEventListener(evt, (e) => { try { emit(evt, JSON.parse(e.data)); } catch(err) {} });

        es.addEventListener('connected', (e) => {
            reconnectDelay = 8000; connected = true;
            try { emit('connected', JSON.parse(e.data)); } catch(err) {}
        });

        ['queue','orders_updated','my_orders_updated','stats_updated','products_updated','admin_products_updated'].forEach(listen);

        es.addEventListener('reconnect', () => scheduleReconnect());
        es.onerror = () => {
            connected = false;
            if (!active) return;
            if (es) { es.close(); es = null; }
            emit('disconnected', {});
            scheduleReconnect();
        };
    }

    function scheduleReconnect() {
        if (!active) return;
        clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(() => {
            reconnectDelay = Math.min(reconnectDelay * 1.5, 60000);
            connect();
        }, reconnectDelay);
    }

    function disconnect() {
        active = false; connected = false;
        clearTimeout(reconnectTimer);
        if (es) { es.close(); es = null; }
        handlers = {};
    }

    const isConnected = () => connected && es && es.readyState === EventSource.OPEN;

    return { connect, disconnect, on, off, isConnected };
})();