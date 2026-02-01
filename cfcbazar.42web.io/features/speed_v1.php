<?php
// features/speed.php
include(__DIR__ . '/../config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['max']) || !isset($data['avg'])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing 'max' or 'avg'"]);
        exit;
    }

    $ip = $_SERVER['REMOTE_ADDR'];
    $max_speed = floatval($data['max']);
    $avg_speed = floatval($data['avg']);
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);

    // Create table if not exists
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS speed_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45),
            max_speed FLOAT,
            avg_speed FLOAT,
            user_agent TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
    $conn->query($create_table_sql);

    $stmt = $conn->prepare("INSERT INTO speed_results (ip, max_speed, avg_speed, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdds", $ip, $max_speed, $avg_speed, $user_agent);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'DB error: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>CfCbazar Speed Test</title>
  <style>
    body {
      margin: 0; padding: 0;
      font-family: Arial, sans-serif;
      background: #f4f4f4;
      text-align: center;
    }
    .container {
      max-width: 700px;
      margin: 50px auto;
      padding: 0 20px;
    }
    h1 {
      font-size: 28px;
      color: #333;
    }
    button {
      margin: 20px 0;
      padding: 10px 20px;
      font-size: 16px;
      background: #4caf50;
      color: #fff;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      transition: background .3s;
    }
    button:disabled { background: #888; cursor: default; }
    button:hover:not(:disabled) { background: #45a049; }

    #progress-container {
      width: 100%; background: #ddd; height: 16px;
      border-radius: 8px; overflow: hidden;
      margin: 20px 0;
    }
    #progress-bar {
      width: 0; height: 100%; background: #4caf50;
      transition: width .3s;
    }

    #results, #console-output {
      background: #fff;
      border-radius: 4px;
      padding: 10px;
      box-shadow: 0 0 5px rgba(0,0,0,.1);
      margin: 10px 0;
      max-height: 200px;
      overflow-y: auto;
      text-align: left;
    }
    #console-output { background: #222; color: #0f0; font-family: monospace; }

    canvas { width: 100%; height: 300px; }

    .highlight { font-weight: bold; color: #4caf50; }
  </style>
</head>
<body>
  <div class="container">
    <h1>CfCbazar Speed Test by Parallel Groups</h1>
    <button id="startTest">Start Test</button>

    <div id="progress-container">
      <div id="progress-bar"></div>
    </div>

    <canvas id="speedChart"></canvas>

    <div id="results"></div>
    <div id="console-output"></div>

    <footer style="font-size:12px;color:#666;margin-top:20px;">
      Rev 7.0 | © CfCbazar Beta Test
    </footer>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // ── CONFIG ──────────────────────────────────────────────────────────────
    const TIMEOUT_SECS = 10;

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

    let testRunning = false;
    let speedData = [];    // { parallel: n, speed: Mbps }
    let chart;

    const startBtn       = document.getElementById("startTest");
    const progressBar    = document.getElementById("progress-bar");
    const resultsDiv     = document.getElementById("results");
    const consoleOutput  = document.getElementById("console-output");
    const ctx            = document.getElementById("speedChart").getContext("2d");

    startBtn.addEventListener("click", startSpeedTest);

    // ── MAIN TEST LOOP ──────────────────────────────────────────────────────
    async function startSpeedTest() {
      if (testRunning) return;
      testRunning = true;

      // reset UI & state
      startBtn.disabled = true;
      resultsDiv.innerHTML = "";
      consoleOutput.innerHTML = "";
      progressBar.style.width = "0%";
      speedData = [];
      initializeGraph();

      // run groups from 2 to 8 parallel downloads
      for (let p = 2; p <= 8; p++) {
        updateProgress(p);
        log(`→ Testing ${p} parallel streams…`);
        const imgs = selectImages(p);
        log(`  • URLs: ${imgs.map(i=>i.size+"MB").join(", ")}`);

        // measure each stream’s Mbps
        const speeds = await Promise.all(
          imgs.map(img => downloadAndMeasure(img.url, TIMEOUT_SECS))
        );

        // sum them (no rounding)
        const total = speeds.reduce((a,b)=>a+b, 0);
        speedData.push({ parallel: p, speed: total });

        // update graph & console
        updateGraph(p, total);
        log(`  → Group ${p} speed: ${total.toFixed(1)} Mbps\n`);
      }

      updateProgress(100);
      displayFinalResults();

      // Save results to server
      saveResultsToServer();

      // finish
      startBtn.disabled = false;
      testRunning = false;
    }

    // ── DOWNLOAD & MEASURE ──────────────────────────────────────────────────
    async function downloadAndMeasure(url, timeoutSecs) {
      let received = 0;
      const controller = new AbortController();
      const start = Date.now();
      const timeout = setTimeout(() => controller.abort(), timeoutSecs*1000);

      try {
        const resp = await fetch(url, { cache: "no-store", signal: controller.signal });
        const reader = resp.body.getReader();
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          received += value.length;
          if ((Date.now()-start)/1000 >= timeoutSecs) {
            controller.abort();
            break;
          }
        }
      } catch(e) {
        if (e.name !== "AbortError") console.error(e);
      }
      clearTimeout(timeout);

      const duration = (Date.now() - start)/1000 || 0.001;
      const mb = received/(1024*1024);
      const mbps = (mb*8)/duration;
      log(`    • ${mb.toFixed(2)} MB in ${duration.toFixed(2)} s → ${mbps.toFixed(1)} Mbps`);
      return mbps;
    }

    // ── IMAGE SELECTION ─────────────────────────────────────────────────────
    function selectImages(count) {
      // simple: pick smallest `count` sizes to ensure variety
      return imageUrls
        .slice()  // copy
        .sort((a,b)=>a.size - b.size)
        .slice(0, count);
    }

    // ── UI HELPERS ──────────────────────────────────────────────────────────
    function updateProgress(group) {
      progressBar.style.width = `${Math.min((group/8)*100,100)}%`;
    }

    function log(msg) {
      consoleOutput.innerHTML += `<p>${msg}</p>`;
      consoleOutput.scrollTop = consoleOutput.scrollHeight;
    }

    // ── CHART.JS ────────────────────────────────────────────────────────────
    function initializeGraph() {
      if (chart) chart.destroy();
      chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: [],
          datasets: [{
            label: 'Group Speed (Mbps)',
            data: [],
            borderColor: '#4caf50',
            borderWidth: 2,
            fill: false
          }]
        },
        options: {
          scales: {
            x: { title: { display:true, text:'Parallel Streams' } },
            y: { title: { display:true, text:'Mbps' } }
          }
        }
      });
    }

    function updateGraph(parallel, speed) {
      chart.data.labels.push(parallel);
      chart.data.datasets[0].data.push(speed);
      chart.update();
    }

    // ── FINAL RESULTS ───────────────────────────────────────────────────────
    function displayFinalResults() {
      // extract speeds and drop the lowest
      const speeds = speedData.map(d=>d.speed).sort((a,b)=>a-b);
      const dropped = speeds.shift();  // remove lowest
      const maxAchievable = Math.max(...speeds);
      const avgRemaining = speeds.reduce((a,b)=>a+b,0)/speeds.length;

      resultsDiv.innerHTML = `
        <p><span class="highlight">Max Achievable:</span> ${maxAchievable.toFixed(1)} Mbps</p>
        <p><span class="highlight">Remaining (avg):</span> ${avgRemaining.toFixed(1)} Mbps</p>
        <p><small>Dropped lowest group: ${dropped.toFixed(1)} Mbps</small></p>
      `;
    }

    // ── SAVE TO SERVER ──────────────────────────────────────────────────────
    function saveResultsToServer() {
      const speeds = speedData.map(d=>d.speed).sort((a,b)=>a-b);
      speeds.shift(); // drop lowest

      const maxAchievable = Math.max(...speeds);
      const avgRemaining = speeds.reduce((a,b)=>a+b,0)/speeds.length;

      fetch('speed.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ max: maxAchievable, avg: avgRemaining })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          log('Results saved to server.');
        } else {
          log('Failed to save results: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(e => log('Error sending results to server: ' + e.message));
    }
  </script>
</body>
</html>