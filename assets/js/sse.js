const SSE = (() => {
    let es = null, handlers = {}, reconnectTimer = null, reconnectDelay = 5000, active = false, connected = false;

    const on  = (event, fn) => { if (!handlers[event]) handlers[event] = []; handlers[event].push(fn); };
    const off = (event, fn) => { if (handlers[event]) handlers[event] = handlers[event].filter(h => h !== fn); };

    function emit(event, data) {
        (handlers[event] || []).forEach(fn => { try { fn(data); } catch(e) { console.error('[SSE] Handler error en "' + event + '":', e); } });
    }

    function connect() {
        const token = localStorage.getItem('token');
        if (!token) { console.warn('[SSE] Sin token, no se conecta'); return; }
        if (es) { es.close(); es = null; }
        active = true; connected = false;
        const url = '/api/sse?token=' + encodeURIComponent(token);
        try { es = new EventSource(url); } catch(e) { console.error('[SSE] Error al crear EventSource:', e); scheduleReconnect(); return; }

        const listen = (evt, key) => es.addEventListener(evt, (e) => { try { emit(key || evt, JSON.parse(e.data)); } catch(err) { console.error('[SSE] Error parseando ' + evt + ':', err); } });

        es.addEventListener('connected', (e) => {
            reconnectDelay = 2000; connected = true;
            try { emit('connected', JSON.parse(e.data)); } catch(err) {}
        });

        listen('queue');
        listen('orders_updated');
        listen('my_orders_updated');
        listen('stats_updated');
        listen('products_updated');
        listen('admin_products_updated');

        es.addEventListener('reconnect', () => scheduleReconnect());
        es.onerror = () => { connected = false; if (!active) return; if (es) { es.close(); es = null; } emit('disconnected', {}); scheduleReconnect(); };
        es.onopen  = () => {};
    }

    function scheduleReconnect() {
        if (!active) return;
        clearTimeout(reconnectTimer);
        reconnectTimer = setTimeout(() => { reconnectDelay = Math.min(reconnectDelay * 1.5, 30000); connect(); }, reconnectDelay);
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