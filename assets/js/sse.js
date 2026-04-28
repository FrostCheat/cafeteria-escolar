const SSE = (() => {
    let es = null;
    let handlers = {};
    let reconnectTimer = null;
    let reconnectDelay = 5000;
    let active = false;
    let connected = false;

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
            try { fn(data); } catch(e) { console.error('[SSE] Handler error en "' + event + '":', e); }
        });
    }

    function connect() {
        const token = localStorage.getItem('token');
        if (!token) {
            console.warn('[SSE] Sin token, no se conecta');
            return;
        }

        if (es) {
            es.close();
            es = null;
        }

        active = true;
        connected = false;

        const url = '/api/sse?token=' + encodeURIComponent(token);
        console.log('[SSE] Conectando a', url);

        try {
            es = new EventSource(url);
        } catch(e) {
            console.error('[SSE] Error al crear EventSource:', e);
            scheduleReconnect();
            return;
        }

        es.addEventListener('connected', (e) => {
            reconnectDelay = 2000;
            connected = true;
            console.log('[SSE] Conectado:', e.data);
            try { emit('connected', JSON.parse(e.data)); } catch(err) {}
        });

        es.addEventListener('queue', (e) => {
            console.log('[SSE] Evento queue recibido');
            try { emit('queue', JSON.parse(e.data)); } catch(err) { console.error('[SSE] Error parseando queue:', err); }
        });

        es.addEventListener('orders_updated', (e) => {
            console.log('[SSE] Evento orders_updated recibido');
            try { emit('orders_updated', JSON.parse(e.data)); } catch(err) { console.error('[SSE] Error parseando orders_updated:', err); }
        });

        es.addEventListener('my_orders_updated', (e) => {
            console.log('[SSE] Evento my_orders_updated recibido');
            try { emit('my_orders_updated', JSON.parse(e.data)); } catch(err) { console.error('[SSE] Error parseando my_orders_updated:', err); }
        });

        es.addEventListener('stats_updated', (e) => {
            console.log('[SSE] Evento stats_updated recibido');
            try { emit('stats_updated', JSON.parse(e.data)); } catch(err) { console.error('[SSE] Error parseando stats_updated:', err); }
        });

        es.addEventListener('products_updated', (e) => {
            console.log('[SSE] Evento products_updated recibido');
            try { emit('products_updated', JSON.parse(e.data)); } catch(err) { console.error('[SSE] Error parseando products_updated:', err); }
        });

        es.addEventListener('reconnect', () => {
            console.log('[SSE] Servidor pidió reconexión');
            scheduleReconnect();
        });

        es.addEventListener('error', () => {
            console.log('[SSE] Evento error del servidor');
        });

        es.onerror = (err) => {
            console.warn('[SSE] Error de conexión, estado readyState:', es ? es.readyState : 'N/A');
            connected = false;
            if (!active) return;
            if (es) { es.close(); es = null; }
            emit('disconnected', {});
            scheduleReconnect();
        };

        es.onopen = () => {
            console.log('[SSE] Conexión abierta');
        };
    }

    function scheduleReconnect() {
        if (!active) return;
        clearTimeout(reconnectTimer);
        console.log('[SSE] Reconectando en', reconnectDelay, 'ms...');
        reconnectTimer = setTimeout(() => {
            reconnectDelay = Math.min(reconnectDelay * 1.5, 30000);
            connect();
        }, reconnectDelay);
    }

    function disconnect() {
        console.log('[SSE] Desconectando');
        active = false;
        connected = false;
        clearTimeout(reconnectTimer);
        if (es) { es.close(); es = null; }
        handlers = {};
    }

    function isConnected() {
        return connected && es && es.readyState === EventSource.OPEN;
    }

    return { connect, disconnect, on, off, isConnected };
})();