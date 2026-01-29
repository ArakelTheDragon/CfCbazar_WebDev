<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Speed Measurement</title>
<style>
  body { font-family: monospace; background: #111; color: #0f0; text-align: center; margin-top: 10%; white-space: pre-line; }
  #speed { font-size: 1.5em; }
</style>
</head>
<body>
<div id="speed">Speed: 0 Mbps</div>
<script>
const imageUrls = [
  { url: "https://cc.free.bg/dat.jpg", size: 1 },
  { url: "https://upload.wikimedia.org/wikipedia/commons/f/fd/Alaus%C3%AD_Inner_yards_east_of_Calle_Sim%C3%B3n_Bol%C3%ADvar_2.jpg", size: 11 },
  { url: "https://upload.wikimedia.org/wikipedia/commons/6/66/Grand-view-point-003.jpg", size: 59 },
  { url: "https://speed.cloudflare.com/__down?bytes=100000000", size: 100 },
  { url: "https://speed.cloudflare.com/__down?bytes=500000000", size: 500 },
  { url: "https://speed.cloudflare.com/__down?bytes=1000000000", size: 1000 },
  { url: "https://upload.wikimedia.org/wikipedia/commons/6/69/%22Houston%2C_Tranquility_base_here_%22_%28LROC563%29.tiff", size: 1200 },
  { url: "https://speed.cloudflare.com/__down?bytes=1500000000", size: 1500 }
];

const testDuration = 10000; // 10 s
const updateInterval = 100; // 0.1 s
let totalBytes = 0;
let startTime = performance.now();

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
  if (stored) {
    try { history = JSON.parse(stored); } catch {}
  }
  history.push(finalSpeed);
  if (history.length > 8) history = history.slice(-8);
  setCookie("speedHistory", JSON.stringify(history));

  const avg = history.reduce((a,b)=>a+b,0) / history.length;
  return { avg, count: history.length };
}

function measureSpeed() {
  const controller = new AbortController();
  const signal = controller.signal;

  imageUrls.forEach(img => {
    fetch(img.url, { signal })
      .then(res => {
        if (!res.ok) throw new Error("Network error");
        const reader = res.body.getReader();
        const readChunk = () => reader.read().then(({ done, value }) => {
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
    const speedMbps = (totalBytes * 8) / (elapsed / 1000) / 1e6;
    document.getElementById("speed").textContent = `Speed: ${speedMbps.toFixed(2)} Mbps`;
    if (elapsed >= testDuration) {
      clearInterval(interval);
      controller.abort();
      const finalSpeed = (totalBytes * 8) / (testDuration / 1000) / 1e6;
      const { avg, count } = updateCookieSpeed(finalSpeed);
      document.getElementById("speed").textContent =
        `Average (this run): ${finalSpeed.toFixed(2)} Mbps\nOverall avg (32 max): ${avg.toFixed(2)} Mbps\nMeasurements stored: ${count}`;
    }
  }, updateInterval);
}

window.addEventListener("load", measureSpeed);
</script>
</body>
</html>
