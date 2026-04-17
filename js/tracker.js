(function() {
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            var r = Math.random() * 16 | 0,
                v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    let sessionId = localStorage.getItem('analytics_session_id');
    if (!sessionId) {
        sessionId = generateUUID();
        localStorage.setItem('analytics_session_id', sessionId);
    }
    
    window.SESSION_ID = sessionId;

    function sendEvent(action, payload = {}) {
        payload.session_id = window.SESSION_ID;
        payload.action = action;
        
        // Use keepalive if it's a ping just in case of page unload
        fetch('/api/track.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload),
            keepalive: action === 'ping'
        }).catch(err => console.error('Tracker error:', err));
    }

    // Ping mechanism
    let currentPage = 'unknown';
    if (window.location.pathname.includes('/checkout')) {
        currentPage = 'checkout';
    } else if (window.location.pathname.includes('/escolher-chip')) {
        currentPage = 'chip';
    } else if (window.location.pathname === '/' || window.location.pathname.includes('/index.html')) {
        currentPage = 'index';
    }
    
    // Initial ping and event log
    sendEvent('ping', { page: currentPage });
    if (currentPage !== 'unknown') {
        sendEvent('log_event', { event_type: currentPage });
    }

    // Heartbeat every 10 seconds
    setInterval(() => {
        sendEvent('ping', { page: currentPage });
    }, 10000);

    // If we are on checkout, attach lead tracking
    if (currentPage === 'checkout') {
        let leadDebounceTimer;
        
        function handleLeadInput() {
            clearTimeout(leadDebounceTimer);
            leadDebounceTimer = setTimeout(() => {
                const nameNode = document.getElementById('name');
                const emailNode = document.getElementById('email');
                const docNode = document.getElementById('cpf');
                const phoneNode = document.getElementById('phone');
                
                sendEvent('update_lead', {
                    name: nameNode ? nameNode.value : '',
                    email: emailNode ? emailNode.value : '',
                    document: docNode ? docNode.value : '',
                    phone: phoneNode ? phoneNode.value : ''
                });
            }, 1000); // 1s debounce
        }

        document.addEventListener('DOMContentLoaded', () => {
            const inputs = ['name', 'email', 'cpf', 'phone'];
            inputs.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', handleLeadInput);
                    el.addEventListener('blur', handleLeadInput);
                }
            });
        });
    }

    window.Tracker = {
        sendEvent,
        sessionId: window.SESSION_ID
    };
})();
