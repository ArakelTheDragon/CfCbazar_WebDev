<?php
// python-alarm-wifi/index.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Python Internet Drop Alarm</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>

<body>

<div class="container">

    <div class="header">
        <h1>🔔 Python Internet Drop Alarm</h1>
    </div>

    <h2 class="page-title">A Simple Wi‑Fi / Internet Loss Detector</h2>

    <div class="card">
        <p>
            This project is a lightweight <strong>Python-based alarm system</strong> that 
            plays a sound whenever your device loses Internet connectivity. <strong>The project is not tested, only coded!</strong>
            It continuously checks your connection and alerts you instantly if the network goes down.
        </p>
        <p>
            It’s ideal for monitoring unstable Wi‑Fi, servers, routers, or any setup where 
            connection uptime is important.
        </p>
    </div>

    <div class="card">
        <h3>📡 What This Project Does</h3>
        <ul>
            <li>Checks Internet connectivity every few seconds</li>
            <li>Detects when the connection drops</li>
            <li>Plays a sound alert using <strong>pygame</strong></li>
            <li>Runs on Windows, Linux, macOS, or Raspberry Pi</li>
            <li>Uses <strong>requests</strong> for fast connection checks</li>
        </ul>
    </div>

    <div class="card">
        <h3>🧰 Requirements</h3>
        <ul>
            <li>Python 3.x</li>
            <li><code>requests</code> library</li>
            <li><code>pygame</code> library</li>
            <li>An MP3 or WAV sound file</li>
        </ul>
    </div>

    <div class="card">
        <h3>📜 Full Python Code</h3>

<pre><code>
import time
import requests
import pygame

# Initialize pygame mixer
pygame.mixer.init()

# Path to your sound file
SOUND_FILE = "soundfile.mp3"

def is_connected():
    try:
        requests.get('https://www.google.com', timeout=5)
        return True
    except requests.ConnectionError:
        return False

def play_sound():
    try:
        pygame.mixer.music.load(SOUND_FILE)
        pygame.mixer.music.play()
        while pygame.mixer.music.get_busy():
            time.sleep(0.1)
    except Exception as e:
        print(f"Error playing sound: {e}")

def monitor_connection(check_interval=5):
    while True:
        if not is_connected():
            print("Internet connection dropped. Playing sound...")
            play_sound()
        else:
            print("Internet connection is up.")
        time.sleep(check_interval)

if __name__ == "__main__":
    monitor_connection()
</code></pre>

        <p class="note">
            You can replace the sound file with any alert tone you prefer.
        </p>
    </div>

    <div class="card">
        <h3>⚙️ How It Works</h3>
        <ul>
            <li>The script pings Google every 5 seconds</li>
            <li>If the request fails → it assumes the Internet is down</li>
            <li>It plays your chosen sound file using pygame</li>
            <li>Then continues monitoring indefinitely</li>
        </ul>
    </div>

    <div class="card">
        <h3>🚀 Optional Improvements</h3>
        <ul>
            <li>Change the check interval (default: 5 seconds)</li>
            <li>Use a WAV file for faster playback</li>
            <li>Send notifications instead of sound</li>
            <li>Log outages to a file</li>
            <li>Run as a background service (systemd / Task Scheduler)</li>
        </ul>
    </div>

    <div class="card">
        <h3>📦 Download the Project</h3>
        <p>Find this script and more in the main repository:</p>

        <a class="link-card" href="https://github.com/ArakelTheDragon/Library_Other" target="_blank">
            <span>📁 View on GitHub</span>
        </a>
    </div>

    <footer class="footer">
        <p>Made with ❤️ by Arak — Part of the DIY Python Tools Collection</p>
    </footer>

</div>

</body>
</html>

