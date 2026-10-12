<?php
// ============================================================================
// Dashboard / Frontend Implementation Example
// File: /worktoken/tasks/index.php
// ============================================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/reusable.php';

// Example dynamic task array retrieved from DB for logged-in user
$userTasks = [
    [
        'id'       => 201,
        'title'    => 'Submit Daily Mining Performance Log',
        'reward'   => 25.00,
        'status'   => 'pending',
        'desc_url' => '/tasks/detail.php?id=201',
        'category' => 'Mining'
    ],
    [
        'id'       => 202,
        'title'    => 'Complete Platform UX Survey',
        'reward'   => 50.00,
        'status'   => 'accepted',
        'desc_url' => '/tasks/detail.php?id=202',
        'category' => 'Community'
    ],
    [
        'id'       => 203,
        'title'    => 'Verify External Node Setup',
        'reward'   => 15.00,
        'status'   => 'denied',
        'desc_url' => '/tasks/detail.php?id=203',
        'category' => 'Network'
    ]
];

// Call grid rendering directly via reusable.php imports
render_task_grid($userTasks, '⚙️ Available WorkToken Tasks');
?>
