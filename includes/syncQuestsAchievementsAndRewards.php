<?php
/**
 * CfCbazar Quest & Achievement Helper Library
 * File: /includes/syncQuestsAchievementsAndRewards.php
 *
 * Seeds available quests for a user, evaluates progress based on worker stats (solves, XP),
 * completes quests, logs achievements, awards token rewards, and returns active quest and achievement lists.
 */

declare(strict_types=1);

if (!function_exists('syncQuestsAchievementsAndRewards')) {
    /**
     * Synchronizes user quests, achievements, and rewards based on worker stats.
     *
     * @param string $email User's account email address
     * @return array Array containing 'quests' and 'achievements' formatted lists
     */
    function syncQuestsAchievementsAndRewards(string $email): array
    {
        global $conn;

        if (!$conn) {
            error_log("syncQuestsAchievementsAndRewards: Database connection not available");
            return ['quests' => [], 'achievements' => []];
        }

        $quests_out = [];
        $achievements_out = [];

        $user = getWorkerStats($email);
        $xp = (int)($user['exp'] ?? 0);
        $solved = (int)floor($xp / 10);

        // Fetch global seed quests
        $seedStmt = $conn->prepare("SELECT quest_name, description, target, reward FROM quests WHERE email IS NULL OR email = ''");

        if (!$seedStmt) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for seed quests: " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }

        $seedStmt->execute();
        $seeds = $seedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $seedStmt->close();

        // Assign unassigned seed quests to user if not already present or completed
        foreach ($seeds as $s) {
            $qname = $s['quest_name'] ?? '';

            $chkA = $conn->prepare("SELECT COUNT(*) AS cnt FROM achievements WHERE email = ? AND achievement_name = ?");
            if (!$chkA) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for achievements check: " . $conn->error);
                continue;
            }
            $chkA->bind_param('ss', $email, $qname);
            $chkA->execute();
            $cntA = (int)$chkA->get_result()->fetch_assoc()['cnt'];
            $chkA->close();

            if ($cntA > 0) {
                continue;
            }

            $chkQ = $conn->prepare("SELECT COUNT(*) AS cnt FROM quests WHERE email = ? AND quest_name = ?");
            if (!$chkQ) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests check: " . $conn->error);
                continue;
            }
            $chkQ->bind_param('ss', $email, $qname);
            $chkQ->execute();
            $cntQ = (int)$chkQ->get_result()->fetch_assoc()['cnt'];
            $chkQ->close();

            if ($cntQ > 0) {
                continue;
            }

            $ins = $conn->prepare("INSERT INTO quests (email, quest_name, description, target, reward, progress, completed) VALUES (?, ?, ?, ?, ?, 0, 0)");
            if (!$ins) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests insert: " . $conn->error);
                continue;
            }
            $ins->bind_param('sssdi', $email, $qname, $s['description'], $s['target'], $s['reward']);
            $ins->execute();
            $ins->close();
        }

        // Process and evaluate user's active quests
        $q = $conn->prepare("SELECT id, quest_name, description, target, reward, progress, completed FROM quests WHERE email = ?");
        if (!$q) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests select: " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }
        $q->bind_param('s', $email);
        $q->execute();
        $allQuests = $q->get_result()->fetch_all(MYSQLI_ASSOC);
        $q->close();

        foreach ($allQuests as $quest) {
            $id = (int)$quest['id'];
            $target = (int)$quest['target'];
            $currentProgress = (int)($quest['progress'] ?? 0);
            $questName = $quest['quest_name'] ?? '';
            $desc = strtolower($quest['description'] ?? '');

            if (strpos($desc, 'solve') !== false) {
                $progress = $solved;
            } elseif (strpos($desc, 'xp') !== false) {
                $progress = $xp;
            } else {
                $progress = $currentProgress;
            }

            $progress = min($progress, $target);
            $completedNow = $progress >= $target ? 1 : 0;

            $u = $conn->prepare("UPDATE quests SET progress = ?, completed = ? WHERE id = ?");
            if (!$u) {
                error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests update: " . $conn->error);
                continue;
            }
            $u->bind_param('iii', $progress, $completedNow, $id);
            $u->execute();
            $u->close();

            // Handle new completions: add achievement entry & payout reward
            if ($completedNow && !$quest['completed']) {
                $ins = $conn->prepare("INSERT INTO achievements (email, achievement_name, description, target, reward, completed, updated_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
                if (!$ins) {
                    error_log("syncQuestsAchievementsAndRewards: Prepare failed for achievements insert: " . $conn->error);
                    continue;
                }
                $ins->bind_param('sssdi', $email, $questName, $quest['description'], $target, $quest['reward']);
                $ins->execute();
                $ins->close();

                if ($quest['reward'] > 0) {
                    addTokens($email, (float)$quest['reward']);
                }
            }
        }

        // Fetch active uncompleted quests output
        $a = $conn->prepare("SELECT quest_name, description FROM quests WHERE email = ? AND completed = 0");
        if (!$a) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for quests select (active): " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }
        $a->bind_param('s', $email);
        $a->execute();
        $res = $a->get_result();

        while ($r = $res->fetch_assoc()) {
            $quests_out[] = $r['quest_name'] . (!empty($r['description']) ? " — " . $r['description'] : "");
        }
        $a->close();

        // Fetch recent completed achievements output
        $b = $conn->prepare("SELECT achievement_name, description FROM achievements WHERE email = ? AND completed = 1 ORDER BY updated_at DESC LIMIT 10");
        if (!$b) {
            error_log("syncQuestsAchievementsAndRewards: Prepare failed for achievements select: " . $conn->error);
            return ['quests' => [], 'achievements' => []];
        }
        $b->bind_param('s', $email);
        $b->execute();
        $res = $b->get_result();

        while ($r = $res->fetch_assoc()) {
            $achievements_out[] = $r['achievement_name'] . (!empty($r['description']) ? " — " . $r['description'] : "");
        }
        $b->close();

        return ['quests' => $quests_out, 'achievements' => $achievements_out];
    }
}
