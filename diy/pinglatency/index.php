<?php
// CfCbazar DIY Ping & Latency Monitor Tool
// /diy/pinglatency/index.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$title = 'CfCbazar DIY Ping & Latency Monitor Tool';

$reusablePath = __DIR__ . '/../../includes/reusable.php';

if (file_exists($reusablePath)) {
    require_once $reusablePath;

    if (function_exists('trackVisit')) {
        trackVisit("diy-pinglatency");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Ping &amp; Latency Monitor — CfCbazar DIY Tool</title>

<!-- Global Styles -->
<link rel="stylesheet" href="/css/styles.css">

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- SEO -->
<meta name="description" content="Real-time Ping & Latency Monitor by CfCbazar. Track network latency, router response, and connection stability with live charts and historical data.">

<meta name="keywords" content="ping monitor, latency monitor, network tools, internet speed, router latency, CfCbazar DIY, WorkToken tools, real-time ping test">

<meta name="author" content="CfCbazar">

<meta name="robots" content="index,follow">

<link rel="canonical" href="https://cfcbazar.42web.io/diy/pinglatency/">

<!-- Open Graph -->

<meta property="og:title"
      content="Ping &amp; Latency Monitor — CfCbazar DIY Tool">

<meta property="og:description"
      content="Monitor ping, latency, and router response in real time with live charts and historical tracking.">

<meta property="og:type"
      content="website">

<meta property="og:url"
      content="https://cfcbazar.42web.io/diy/pinglatency/">

<meta property="og:image"
      content="https://cfcbazar.42web.io/assets/cfcbazar-preview.png">

<!-- Twitter -->

<meta name="twitter:card"
      content="summary_large_image">

<meta name="twitter:title"
      content="Ping &amp; Latency Monitor — CfCbazar DIY">

<meta name="twitter:description"
      content="Real-time network latency tracking with live charts and router detection.">

<meta name="twitter:image"
      content="https://cfcbazar.42web.io/assets/cfcbazar-preview.png">

<!-- Structured Data -->

<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"WebApplication",
  "name":"Ping & Latency Monitor",
  "url":"https://cfcbazar.42web.io/diy/pinglatency/",
  "applicationCategory":"Utility",
  "operatingSystem":"All",
  "creator":{
    "@type":"Organization",
    "name":"CfCbazar",
    "url":"https://cfcbazar.42web.io"
  },
  "description":"Real-time ping and latency monitoring tool with live charts, router detection and historical tracking.",
  "keywords":"ping monitor, latency monitor, network tools, CfCbazar DIY, WorkToken"
}
</script>

<style>

/* --------------------------------------------------
   Ping & Latency Tool
   Uses the global CfCbazar stylesheet.
-------------------------------------------------- */

.tool-card{
    background:#fff;
    border-radius:18px;
    padding:25px;
    box-shadow:0 4px 20px rgba(0,0,0,.08);
}

.tool-card h2{
    text-align:center;
    color:#28a745;
    margin-bottom:25px;
    font-size:2rem;
}

.tool-controls{
    display:flex;
    flex-wrap:wrap;
    justify-content:center;
    align-items:center;
    gap:15px;
    margin-bottom:25px;
}

.tool-controls label{
    font-weight:600;
}

.tool-controls select{
    width:220px;
    padding:12px;
    border:1px solid #ccc;
    border-radius:6px;
    font-size:1rem;
}

.tool-controls button{
    width:auto;
    min-width:170px;
    margin-top:0;
}

#chart-container{
    background:#fafafa;
    border:1px solid #ddd;
    border-radius:12px;
    padding:15px;
}

canvas{
    width:100%;
}

.latency-stats{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:18px;
    margin-top:25px;
}

.stat-box{
    background:#fff;
    border:1px solid #e5e5e5;
    border-radius:12px;
    padding:18px;
    text-align:center;
    transition:.2s;
}

.stat-box:hover{
    transform:translateY(-2px);
    box-shadow:0 5px 14px rgba(0,0,0,.08);
}

.stat-label{
    display:block;
    color:#666;
    margin-bottom:8px;
    font-size:.9rem;
}

.stat-value{
    display:block;
    color:#28a745;
    font-size:1.8rem;
    font-weight:bold;
}

@media (max-width:768px){

    .tool-controls{
        flex-direction:column;
    }

    .tool-controls select,
    .tool-controls button{
        width:100%;
        max-width:350px;
    }

}

</style>

</head>

<body>

<div class="container">

<div class="tool-card">

<h2 id="title">
    Ping &amp; Latency Monitor — Real-Time Network Diagnostic Tool
</h2>

<div class="tool-controls">

<label for="timeRange">
    Select Time Range:
</label>

<select id="timeRange">
    <option value="5m">Last 5 minutes</option>
    <option value="1h">Last 1 hour</option>
    <option value="4h">Last 4 hours</option>
    <option value="1d">Last 1 day</option>
    <option value="1w">Last 1 week</option>
    <option value="1m">Last 1 month</option>
</select>

<button id="clearStats">
    Clear Stats
</button>

</div>

<div id="chart-container">
    <canvas id="latencyChart" height="350"></canvas>
</div>

<!-- Latency Statistics -->

<div class="latency-stats">

    <div class="stat-box">
        <span class="stat-label">Average Latency</span>
        <span id="avgLatency" class="stat-value">-- ms</span>
    </div>

    <div class="stat-box">
        <span class="stat-label">Minimum</span>
        <span id="minLatency" class="stat-value">-- ms</span>
    </div>

    <div class="stat-box">
        <span class="stat-label">Maximum</span>
        <span id="maxLatency" class="stat-value">-- ms</span>
    </div>

    <div class="stat-box">
        <span class="stat-label">Packet Loss</span>
        <span id="packetLoss" class="stat-value">--</span>
    </div>

</div>

</div>
</div>

<script>

/* --------------------------------------------------
   Ping & Latency Monitor
-------------------------------------------------- */

const targets = [
    '8.8.8.8',
    '1.1.1.1',
    '75.75.75.75',
    '75.75.76.76',
    '208.67.222.222',
    '127.0.0.1',
    '4.2.2.1',
    '4.2.2.2'
];

const localIPs = [
    '192.168.0.1',
    '192.168.1.1'
];

const historyKey = 'pingHistory';
const interval = 2000;

/* Chart colours */

const colors = [
    "#28a745",
    "#007bff",
    "#ff9800",
    "#dc3545",
    "#6f42c1",
    "#17a2b8",
    "#fd7e14",
    "#20c997"
];

function ping(ip){

    return new Promise(resolve=>{

        const img=new Image();

        const start=performance.now();

        const url=`http://${ip}/?cache_bust=${Math.random()}`;

        let done=false;

        const cleanup=()=>{

            if(done) return;

            done=true;

            resolve({
                ip,
                latency:Math.round(performance.now()-start)
            });

        };

        img.onload=cleanup;
        img.onerror=cleanup;

        img.src=url;

        setTimeout(()=>{

            if(!done){

                done=true;

                resolve({
                    ip,
                    latency:null
                });

            }

        },1500);

    });

}

function savePingResult(results){

    const history=JSON.parse(
        localStorage.getItem(historyKey) || '[]'
    );

    history.push({
        timestamp:Date.now(),
        results
    });

    const cutoff=
        Date.now()-31*24*60*60*1000;

    const filtered=
        history.filter(
            entry=>entry.timestamp>=cutoff
        );

    localStorage.setItem(
        historyKey,
        JSON.stringify(filtered)
    );

}

function getFilteredData(timeRange){

    const durations={
        "5m":5*60*1000,
        "1h":60*60*1000,
        "4h":4*60*60*1000,
        "1d":24*60*60*1000,
        "1w":7*24*60*60*1000,
        "1m":31*24*60*60*1000
    };

    const cutoff=
        Date.now()-durations[timeRange];

    return JSON.parse(
        localStorage.getItem(historyKey) || "[]"
    ).filter(
        entry=>entry.timestamp>=cutoff
    );

}

function getRouterIP(filteredData){

    const averages={};

    localIPs.forEach(
        ip=>averages[ip]=[]
    );

    filteredData.forEach(entry=>{

        entry.results.forEach(result=>{

            if(
                localIPs.includes(result.ip) &&
                result.latency!==null
            ){
                averages[result.ip].push(result.latency);
            }

        });

    });

    let router=null;
    let lowest=Infinity;

    for(const ip in averages){

        if(!averages[ip].length) continue;

        const avg=
            averages[ip].reduce((a,b)=>a+b,0) /
            averages[ip].length;

        if(avg<lowest){

            lowest=avg;
            router=ip;

        }

    }

    return router;

}
//--------------p3
function updateStats(filteredData) {

    const allLatencies = [];

    filteredData.forEach(entry => {

        entry.results.forEach(result => {

            if (result.latency !== null) {
                allLatencies.push(result.latency);
            }

        });

    });

    const totalTests = filteredData.reduce(
        (total, entry) => total + entry.results.length,
        0
    );

    const lostTests = filteredData.reduce(
        (loss, entry) =>
            loss + entry.results.filter(r => r.latency === null).length,
        0
    );

    const packetLoss =
        totalTests > 0
            ? Math.round((lostTests / totalTests) * 100)
            : 0;

    if (!allLatencies.length) {

        avgLatency.textContent = "-- ms";
        minLatency.textContent = "-- ms";
        maxLatency.textContent = "-- ms";
        packetLoss.textContent = packetLoss + "%";

        return;
    }

    avgLatency.textContent =
        Math.round(
            allLatencies.reduce((a, b) => a + b, 0) /
            allLatencies.length
        ) + " ms";

    minLatency.textContent =
        Math.min(...allLatencies) + " ms";

    maxLatency.textContent =
        Math.max(...allLatencies) + " ms";

    document.getElementById("packetLoss").textContent =
        packetLoss + "%";

}

function updateChart(filteredData) {

    const datasets = targets.map((ip, index) => ({

        label: ip,

        data: filteredData.map(entry => {

            const result =
                entry.results.find(r => r.ip === ip);

            return result
                ? result.latency
                : null;

        }),

        borderColor: colors[index % colors.length],
        backgroundColor: colors[index % colors.length],
        borderWidth: 2,
        pointRadius: 2,
        pointHoverRadius: 5,
        tension: 0.3,
        fill: false

    }));

    chart.data.labels = filteredData.map(entry =>
        new Date(entry.timestamp).toLocaleTimeString()
    );

    chart.data.datasets = datasets;

    chart.update();

    updateStats(filteredData);

    const router = getRouterIP(filteredData);

    title.textContent = router
        ? `Ping & Latency Monitor — Router IP: ${router}`
        : "Ping & Latency Monitor — Router IP: Not Detected";

}

async function runPingLoop() {

    const results =
        await Promise.all(
            targets.map(ping)
        );

    savePingResult(results);

    updateChart(
        getFilteredData(
            timeRange.value
        )
    );

    setTimeout(
        runPingLoop,
        interval
    );

}

/* -----------------------------
   Chart.js
----------------------------- */

const ctx =
    document
        .getElementById("latencyChart")
        .getContext("2d");

const chart = new Chart(ctx, {

    type: "line",

    data: {

        labels: [],
        datasets: []

    },

    options: {

        responsive: true,

        maintainAspectRatio: true,

        animation: false,

        interaction: {

            mode: "nearest",
            intersect: false

        },

        plugins: {

            legend: {

                position: "top"

            },

            title: {

                display: true,
                text: "Latency to Targets (ms)"

            }

        },

        scales: {

            y: {

                beginAtZero: true,

                title: {

                    display: true,
                    text: "Latency (ms)"

                }

            },

            x: {

                ticks: {

                    maxRotation: 90,
                    minRotation: 45

                }

            }

        }

    }

});

/* -----------------------------
   Events
----------------------------- */

const title = document.getElementById("title");
const timeRange = document.getElementById("timeRange");

const avgLatency = document.getElementById("avgLatency");
const minLatency = document.getElementById("minLatency");
const maxLatency = document.getElementById("maxLatency");
const packetLoss = document.getElementById("packetLoss");

timeRange.addEventListener("change", () => {

    updateChart(
        getFilteredData(timeRange.value)
    );

});

document
    .getElementById("clearStats")
    .addEventListener("click", () => {

        localStorage.removeItem(historyKey);

        chart.data.labels = [];
        chart.data.datasets = [];

        chart.update();

        avgLatency.textContent = "-- ms";
        minLatency.textContent = "-- ms";
        maxLatency.textContent = "-- ms";
        packetLoss.textContent = "--";

        title.textContent =
            "Ping & Latency Monitor — Router IP: Not Detected";

    });

/* -----------------------------
   Start
----------------------------- */

runPingLoop();

</script>

<?php
cfc_footer(
    "https://github.com/ArakelTheDragon/CfCbazar_WebDev/tree/main/diy/pinglatency",
    "Ping & Latency Tool GitHub Source Code"
);

include_footer();
?>

</body>
</html>
