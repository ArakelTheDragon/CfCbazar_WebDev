<?php
/**
 * Renders a standardized project metadata bar for DIY tutorials.
 * 
 * @param array $config Options: 'status', 'build_time', 'learn_time', 'experience'
 */
function render_project_stats($config = []) {
    $status     = $config['status']     ?? 'Untested';
    $buildTime  = $config['build_time']  ?? '15-20 mins';
    $learnTime  = $config['learn_time']  ?? '30 mins';
    $experience = $config['experience']  ?? '1-2 years';

    // Status color selection
    $statusBg = '#ff9800'; // Default orange for Untested
    $statusLower = strtolower($status);
    
    if (strpos($statusLower, 'tested') !== false || strpos($statusLower, 'verified') !== false) {
        $statusBg = '#28a745'; // Green
    } elseif (strpos($statusLower, 'progress') !== false) {
        $statusBg = '#007bff'; // Blue
    }
    ?>
    <style>
        .project-stats-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0066cc;
            border-radius: 6px;
            padding: 12px 18px;
            margin: 20px 0;
            align-items: center;
            font-size: 14px;
        }
        .project-stats-bar .stat-item {
            display: flex;
            flex-direction: column;
        }
        .project-stats-bar .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 700;
        }
        .project-stats-bar .stat-value {
            font-weight: 600;
            color: #1e293b;
            margin-top: 2px;
        }
        .project-stats-bar .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            color: #ffffff;
            font-weight: bold;
            font-size: 12px;
            background-color: <?= $statusBg ?>;
        }
    </style>

    <div class="project-stats-bar">
        <div class="stat-item">
            <span class="stat-label">Status</span>
            <span class="stat-value"><span class="status-badge"><?= htmlspecialchars($status) ?></span></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Build Time</span>
            <span class="stat-value">⏱️ <?= htmlspecialchars($buildTime) ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Learn Time</span>
            <span class="stat-value">📚 <?= htmlspecialchars($learnTime) ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Experience Level</span>
            <span class="stat-value">🛠️ <?= htmlspecialchars($experience) ?></span>
        </div>
    </div>
    <?php
}
