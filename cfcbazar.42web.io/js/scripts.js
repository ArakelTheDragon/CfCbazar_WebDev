document.addEventListener('DOMContentLoaded', () => {
  try {
    // Get CSRF token from meta tag or hidden input
    const csrfToken = document.querySelector('meta[name="csrf_token"]')?.content || 
                      document.querySelector('input[name="csrf_token"]')?.value || '';

    // -------------------------
    // Mobile menu toggle
    // -------------------------
    const menuToggle = document.querySelector('.menu-toggle');
    const navMenu = document.querySelector('.nav-menu');
    if (menuToggle && navMenu) {
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open menu');
        menuToggle.addEventListener('click', () => {
            const isActive = navMenu.classList.toggle('active');
            menuToggle.textContent = isActive ? '✕' : '☰';
            menuToggle.setAttribute('aria-expanded', String(isActive));
            menuToggle.setAttribute('aria-label', isActive ? 'Close menu' : 'Open menu');
            if (isActive) navMenu.querySelector('a').focus();
        });
        navMenu.addEventListener('click', (e) => {
            if (e.target.tagName.toLowerCase() === 'a') {
                navMenu.classList.remove('active');
                menuToggle.textContent = '☰';
                menuToggle.setAttribute('aria-expanded', 'false');
                menuToggle.setAttribute('aria-label', 'Open menu');
            }
        });
        navMenu.addEventListener('keydown', (e) => {
            if (e.key === 'Tab' && navMenu.classList.contains('active')) {
                const links = navMenu.querySelectorAll('a');
                const first = links[0];
                const last = links[links.length - 1];
                if (e.target === last && !e.shiftKey) {
                    e.preventDefault();
                    first.focus();
                } else if (e.target === first && e.shiftKey) {
                    e.preventDefault();
                    last.focus();
                }
            }
        });
    } else {
        fetch('/errors.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ error: 'Menu toggle or nav menu not found in DOM', csrf_token: csrfToken })
        });
    }

    // -------------------------
    // QR code generation
    // -------------------------
    const qrCanvas = document.getElementById('qr-canvas');
    if (qrCanvas) {
        const qrValue = qrCanvas.dataset.qrValue || '';
        if (qrValue && typeof QRious !== 'undefined') {
            new QRious({
                element: qrCanvas,
                size: 250,
                value: qrValue
            });
        } else if (!qrValue) {
            fetch('/errors.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: 'QR code value not found', csrf_token: csrfToken }) });
        } else {
            fetch('/errors.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: 'QRious library not loaded', csrf_token: csrfToken }) });
        }
    }

    // -------------------------
    // QR code download
    // -------------------------
    window.downloadQR = function () {
        const canvas = document.getElementById('qr-canvas');
        if (!canvas) {
            fetch('/errors.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: 'QR canvas not found for download', csrf_token: csrfToken }) });
            return;
        }
        const a = document.createElement('a');
        a.download = 'qr-code.png';
        a.href = canvas.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    // -------------------------
    // Ecosystem chart
    // -------------------------
    const tokenChart = document.getElementById('tokenChart');
    if (tokenChart && typeof Chart !== 'undefined') {
        const ctx = tokenChart.getContext('2d');
        new Chart(ctx, {
            type: 'flow',
            data: {
                nodes: [
                    { id: 'reserve', label: 'Blockchain Reserve', x: 50, y: 50 },
                    { id: 'send', label: 'Send WorkTokens/BNB\n0xFBd767...', x: 300, y: 50 },
                    { id: 'credits', label: 'Platform Credits', x: 550, y: 50 },
                    { id: 'use', label: 'Use Credits:\nGames, Features, Withdraw', x: 300, y: 150 },
                    { id: 'withdraw', label: 'Withdraw Request', x: 550, y: 150 },
                    { id: 'receive', label: 'Receive WorkTokens\n0xFBd767...', x: 800, y: 150 }
                ],
                links: [
                    { source: 'reserve', target: 'send' },
                    { source: 'send', target: 'credits' },
                    { source: 'credits', target: 'withdraw' },
                    { source: 'credits', target: 'use' },
                    { source: 'use', target: 'withdraw' },
                    { source: 'withdraw', target: 'receive' }
                ]
            },
            options: {
                layout: { padding: 20 },
                elements: {
                    node: { size: 100, font: '13px Arial', color: '#28a745', background: '#f0f8ff', borderColor: '#28a745' },
                    link: { stroke: '#28a745', strokeWidth: 2 }
                }
            }
        });
    } else if (tokenChart) {
        fetch('/errors.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ error: 'Chart.js not loaded or tokenChart not found', csrf_token: csrfToken }) });
    }

    // -------------------------
    // SPEED MEASUREMENT FUNCTION
    // -------------------------
    window.measureSpeedAndStoreCookie = function(options = {}) {
        const {
            duration = 10000,
            updateInterval = 100,
            elementId = "speed",
            maxHistory = 8,
            images = [
                { url: "https://cc.free.bg/dat.jpg", size: 1 },
                { url: "https://upload.wikimedia.org/wikipedia/commons/f/fd/Alaus%C3%AD_Inner_yards_east_of_Calle_Sim%C3%B3n_Bol%C3%ADvar_2.jpg", size: 11 },
                { url: "https://upload.wikimedia.org/wikipedia/commons/6/66/Grand-view-point-003.jpg", size: 59 },
                { url: "https://speed.cloudflare.com/__down?bytes=100000000", size: 100 }
            ]
        } = options;

        let totalBytes = 0;
        const startTime = performance.now();
        const controller = new AbortController();
        const signal = controller.signal;

        function getCookie(name) {
            const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            return match ? decodeURIComponent(match[2]) : null;
        }

        function setCookie(name, value, days = 365) {
            const expires = new Date(Date.now() + days * 864e5).toUTCString();
            document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires}; path=/`;
        }

        function updateCookieSpeed(finalSpeed) {
            let history = [];
            const stored = getCookie("speedHistory");
            if (stored) { try { history = JSON.parse(stored); } catch {} }
            history.push(finalSpeed);
            if (history.length > maxHistory) history = history.slice(-maxHistory);
            setCookie("speedHistory", JSON.stringify(history));
            const avg = history.reduce((a,b)=>a+b,0)/history.length;
            return { avg, count: history.length };
        }

        images.forEach(img => {
            fetch(img.url, { signal })
                .then(res => {
                    if (!res.ok) throw new Error("Network error");
                    const reader = res.body.getReader();
                    const readChunk = () => reader.read().then(({done,value}) => {
                        if (done) return;
                        totalBytes += value.byteLength;
                        return readChunk();
                    });
                    return readChunk();
                })
                .catch(() => {});
        });

        const interval = setInterval(() => {
            const elapsed = performance.now() - startTime;
            const speedMbps = (totalBytes*8)/(elapsed/1000)/1e6;
            const el = document.getElementById(elementId);
            if (el) el.textContent = `Speed: ${speedMbps.toFixed(2)} Mbps`;
            if (elapsed >= duration) {
                clearInterval(interval);
                controller.abort();
                const finalSpeed = (totalBytes*8)/(duration/1000)/1e6;
                const { avg, count } = updateCookieSpeed(finalSpeed);
                if (el) el.textContent =
                    `Average (this run): ${finalSpeed.toFixed(2)} Mbps\nOverall avg (max ${maxHistory}): ${avg.toFixed(2)} Mbps\nMeasurements stored: ${count}`;
            }
        }, updateInterval);
    };

    // -------------------------
    // Automatically run speed test if #speed exists
    // -------------------------
    const speedEl = document.getElementById('speed');
    if (speedEl) {
        measureSpeedAndStoreCookie({
            elementId: 'speed',
            maxHistory: 8,
            duration: 10000,
            updateInterval: 100
        });
    }

  } catch (error) {
    fetch('/errors.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ error: `scripts.js error: ${error.message}`, csrf_token: csrfToken })
    });
  }
});
