document.addEventListener('DOMContentLoaded', () => {
  try {
    // Get CSRF token from meta tag or hidden input
    const csrfToken = document.querySelector('meta[name="csrf_token"]')?.content || 
                      document.querySelector('input[name="csrf_token"]')?.value || '';

    // Mobile menu toggle
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
            if (isActive) {
                navMenu.querySelector('a').focus();
            }
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

    // QR code generation
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
            fetch('/errors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ error: 'QR code value not found', csrf_token: csrfToken })
            });
        } else {
            fetch('/errors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ error: 'QRious library not loaded', csrf_token: csrfToken })
            });
        }
    }

    // QR code download
    window.downloadQR = function () {
        const canvas = document.getElementById('qr-canvas');
        if (!canvas) {
            fetch('/errors.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ error: 'QR canvas not found for download', csrf_token: csrfToken })
            });
            return;
        }
        const a = document.createElement('a');
        a.download = 'qr-code.png';
        a.href = canvas.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    // Ecosystem chart
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
        fetch('/errors.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ error: 'Chart.js not loaded or tokenChart not found', csrf_token: csrfToken })
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