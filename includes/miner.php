<?php

function update_miner_shares($email, $shares)
{
    global $conn;

    $shares = intval($shares);

    if ($shares <= 0) {
        return false;
    }

    $stmt = $conn->prepare(
        "UPDATE workers 
         SET accepted_shares = accepted_shares + ?
         WHERE email = ?"
    );

    $stmt->bind_param(
        "is",
        $shares,
        $email
    );

    return $stmt->execute();
}


function reward_miner_workthr($email)
{
    global $conn;

    // Get stored accepted shares
    $stmt = $conn->prepare(
        "SELECT accepted_shares 
         FROM workers 
         WHERE email = ? 
         LIMIT 1"
    );

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($shares);
    $stmt->fetch();
    $stmt->close();


    if ($shares <= 0) {
        return false;
    }


    $reward_per_share = 0.01;

    $reward = $shares * $reward_per_share;


    // Add WorkTHR and clear shares
    $stmt = $conn->prepare(
        "UPDATE workers
         SET mintme = mintme + ?, accepted_shares = 0
         WHERE email = ?"
    );

    $stmt->bind_param(
        "ds",
        $reward,
        $email
    );

    return $stmt->execute();
}
