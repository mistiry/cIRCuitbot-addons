<?php
// Rate-limit state for mute notices (nick => last-sent timestamp)
$channelModeration_rateLimits = [];

// ---- Entry point --------------------------------------------------------

function channelMod($data) {
    global $config, $socket;

    if (!isBotOwnerOrAdmin($data)) {
        logEntry("channelModeration: denied {$data['usernickname']}@{$data['userhostname']}", 'DEBUG');
        return;
    }

    $firstword = trim($data['messagearray'][1]);
    $command   = strtolower(ltrim($firstword, $config['command_flag']));
    $args      = trim($data['commandargs']);
    $argparts  = $args !== '' ? preg_split('/\s+/', $args) : [];

    $target       = (isset($argparts[0]) && $argparts[0] !== '') ? $argparts[0] : '';
    $duration_str = null;
    $reason_start = 1;
    if (isset($argparts[1]) && preg_match('/^(\d+[mhdwMHDW]|0|perm(anent)?)$/i', $argparts[1])) {
        $duration_str = $argparts[1];
        $reason_start = 2;
    }
    $reason = implode(' ', array_slice($argparts, $reason_start));

    switch ($command) {
        case 'op':
            if ($target === '') $target = $data['usernickname'];
            setMode('+', 'o', $target);
            logEntry("channelModeration: {$data['usernickname']} opped {$target}", 'INFO');
            break;

        case 'deop':
            if ($target === '') $target = $data['usernickname'];
            setMode('-', 'o', $target);
            logEntry("channelModeration: {$data['usernickname']} deopped {$target}", 'INFO');
            break;

        case 'voice':
        case 'v':
            if ($target === '') $target = $data['usernickname'];
            setMode('+', 'v', $target);
            logEntry("channelModeration: {$data['usernickname']} voiced {$target}", 'INFO');
            break;

        case 'devoice':
        case 'dv':
            if ($target === '') $target = $data['usernickname'];
            setMode('-', 'v', $target);
            logEntry("channelModeration: {$data['usernickname']} devoiced {$target}", 'INFO');
            break;

        case 'quiet':
        case 'q':
            if ($target === '') {
                fputs($socket, "NOTICE {$data['usernickname']} :Usage: !quiet <nick> [duration] [reason]\r\n");
                return;
            }
            $default_dur  = !empty($config['moderation_quiet_default']) ? $config['moderation_quiet_default'] : '1h';
            $duration_str = $duration_str ?? $default_dur;
            channelModeration_applyQuiet($data['usernickname'], $target, $duration_str, $reason);
            break;

        case 'unquiet':
        case 'uq':
            if ($target === '') {
                fputs($socket, "NOTICE {$data['usernickname']} :Usage: !unquiet <nick>\r\n");
                return;
            }
            channelModeration_removeQuiet($data['usernickname'], $target);
            break;

        case 'kick':
            if ($target === '') {
                fputs($socket, "NOTICE {$data['usernickname']} :Usage: !kick <nick> [reason]\r\n");
                return;
            }
            $kick_reason = $reason !== '' ? $reason : "Kicked at the request of {$data['usernickname']}";
            fputs($socket, "KICK {$config['channel']} {$target} :{$kick_reason}\r\n");
            logEntry("channelModeration: {$data['usernickname']} kicked {$target}: {$kick_reason}", 'INFO');
            break;

        case 'ban':
            if ($target === '') {
                fputs($socket, "NOTICE {$data['usernickname']} :Usage: !ban <nick> [duration] [reason]\r\n");
                return;
            }
            $default_dur  = !empty($config['moderation_ban_default']) ? $config['moderation_ban_default'] : '1w';
            $duration_str = $duration_str ?? $default_dur;
            channelModeration_applyBan($data['usernickname'], $target, $duration_str, $reason, false, false);
            break;

        case 'unban':
            if ($target === '') {
                fputs($socket, "NOTICE {$data['usernickname']} :Usage: !unban <nick>\r\n");
                return;
            }
            channelModeration_removeBan($data['usernickname'], $target);
            break;

        case 'kickban':
        case 'kb':
            if ($target === '') {
                fputs($socket, "NOTICE {$data['usernickname']} :Usage: !kb <nick> [duration] [reason]\r\n");
                return;
            }
            $default_dur  = !empty($config['moderation_ban_default']) ? $config['moderation_ban_default'] : '1w';
            $duration_str = $duration_str ?? $default_dur;
            channelModeration_applyBan($data['usernickname'], $target, $duration_str, $reason, true, false);
            break;

        case 'redirect':
            if ($target === '') {
                fputs($socket, "NOTICE {$data['usernickname']} :Usage: !redirect <nick> [reason]\r\n");
                return;
            }
            if (empty($config['moderation_redirect_channel'])) {
                fputs($socket, "NOTICE {$data['usernickname']} :moderation_redirect_channel is not configured.\r\n");
                return;
            }
            channelModeration_applyBan($data['usernickname'], $target, null, $reason, false, true);
            break;

        case 'actions':
            if ($data['location'] !== $config['nickname']) {
                return;
            }
            channelModeration_dumpActiveActions($data['usernickname']);
            break;
    }
}

// ---- Quiet --------------------------------------------------------------

function channelModeration_applyQuiet($by, $target, $duration_str, $reason) {
    global $socket, $config, $channelMembers;

    $mask = channelModeration_getMask($target, false, $by);
    if ($mask === null) {
        if (!isset($channelMembers[$target])) {
            fputs($socket, "NOTICE {$by} :{$target} not found in channel members.\r\n");
        }
        return;
    }

    $secs = channelModeration_parseDuration($duration_str);
    if ($secs === false) {
        fputs($socket, "NOTICE {$by} :Duration too large — use 'perm' or '0' for a permanent action.\r\n");
        return;
    }
    $dur_text = $secs !== null ? channelModeration_formatDuration($secs) : 'permanent';

    $existing = channelModeration_getActiveAction($target, 'quiet');
    if ($existing) {
        if ($existing['target_mask'] !== $mask) {
            // Mask changed (e.g. user identified since last quiet) — remove old mask from IRC
            fputs($socket, "MODE {$config['channel']} -q {$existing['target_mask']}\r\n");
        }
        // Lift old DB record without a redundant MODE change, then fall through to re-apply
        channelModeration_liftAction($existing['id'], $by);
        fputs($socket, "NOTICE {$by} :Updated existing quiet for {$target} — now {$dur_text}.\r\n");
        logEntry("channelModeration: {$by} updated quiet for {$target} ({$mask}) to {$dur_text}" . ($reason !== '' ? ": {$reason}" : ''), 'INFO');
    } else {
        logEntry("channelModeration: {$by} quieted {$target} ({$mask}) for {$dur_text}" . ($reason !== '' ? ": {$reason}" : ''), 'INFO');
    }

    fputs($socket, "MODE {$config['channel']} +q {$mask}\r\n");
    channelModeration_recordAction('quiet', $target, $mask, $secs, $reason, $by);

    $notice = "You have been muted in {$config['channel']} for {$dur_text}.";
    if ($reason !== '') $notice .= " Reason: {$reason}";
    fputs($socket, "NOTICE {$target} :{$notice}\r\n");
}

function channelModeration_removeQuiet($by, $target) {
    global $socket, $config;

    $action = channelModeration_getActiveAction($target, 'quiet');
    if (!$action) {
        fputs($socket, "NOTICE {$by} :No active quiet found for {$target}.\r\n");
        return;
    }

    $mask = $action['target_mask'];
    fputs($socket, "MODE {$config['channel']} -q {$mask}\r\n");
    channelModeration_liftAction($action['id'], $by);
    fputs($socket, "NOTICE {$target} :Your mute in {$config['channel']} has been lifted.\r\n");
    logEntry("channelModeration: {$by} unquieted {$target} ({$mask})", 'INFO');
}

// ---- Ban / Redirect -----------------------------------------------------

function channelModeration_applyBan($by, $target, $duration_str, $reason, $kick_first, $redirect) {
    global $socket, $config, $channelMembers;

    $mask = channelModeration_getMask($target, $redirect, $by);
    if ($mask === null) {
        if (!isset($channelMembers[$target])) {
            fputs($socket, "NOTICE {$by} :{$target} not found in channel members.\r\n");
        }
        return;
    }

    // Redirect bans are always permanent
    if (!$redirect) {
        $secs = channelModeration_parseDuration($duration_str);
        if ($secs === false) {
            fputs($socket, "NOTICE {$by} :Duration too large — use 'perm' or '0' for a permanent action.\r\n");
            return;
        }
    } else {
        $secs = null;
    }
    $dur_text = $secs !== null ? channelModeration_formatDuration($secs) : 'permanent';
    $type     = $redirect ? 'redirect' : 'ban';

    $existing = channelModeration_getActiveAction($target, 'ban') ??
                channelModeration_getActiveAction($target, 'redirect');
    if ($existing) {
        if ($existing['target_mask'] !== $mask) {
            // Mask changed — remove old mask from IRC
            fputs($socket, "MODE {$config['channel']} -b {$existing['target_mask']}\r\n");
        }
        channelModeration_liftAction($existing['id'], $by);
        fputs($socket, "NOTICE {$by} :Updated existing ban for {$target} — now {$dur_text}.\r\n");
        logEntry("channelModeration: {$by} updated ban for {$target} ({$mask}) to {$dur_text}" . ($reason !== '' ? ": {$reason}" : ''), 'INFO');
    } else {
        logEntry("channelModeration: {$by} banned {$target} ({$mask}) for {$dur_text}" . ($kick_first ? ' (kickban)' : '') . ($redirect ? ' (redirect)' : '') . ($reason !== '' ? ": {$reason}" : ''), 'INFO');
    }

    if ($kick_first) {
        $kick_reason = $reason !== '' ? $reason : "Banned at the request of {$by}";
        fputs($socket, "KICK {$config['channel']} {$target} :{$kick_reason}\r\n");
    }

    fputs($socket, "MODE {$config['channel']} +b {$mask}\r\n");
    channelModeration_recordAction($type, $target, $mask, $secs, $reason, $by);
}

function channelModeration_removeBan($by, $target) {
    global $socket, $config;

    $action = channelModeration_getActiveAction($target, 'ban');
    if (!$action) {
        $action = channelModeration_getActiveAction($target, 'redirect');
    }
    if (!$action) {
        fputs($socket, "NOTICE {$by} :No active ban found for {$target}.\r\n");
        return;
    }

    $mask = $action['target_mask'];
    fputs($socket, "MODE {$config['channel']} -b {$mask}\r\n");
    channelModeration_liftAction($action['id'], $by);
    logEntry("channelModeration: {$by} unbanned {$target} ({$mask})", 'INFO');
}

// ---- Active action dump (PM debug command) ------------------------------

function channelModeration_dumpActiveActions($replyNick) {
    global $dbconnection, $socket;

    $result = mysqli_query($dbconnection,
        "SELECT * FROM moderation_actions
         WHERE lifted_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY applied_at ASC"
    );

    $count  = $result ? mysqli_num_rows($result) : 0;
    $header = "=== Active moderation actions ({$count}) ===";
    fputs($socket, "NOTICE {$replyNick} :{$header}\r\n");

    if ($count === 0) {
        fputs($socket, "NOTICE {$replyNick} :  (none)\r\n");
    } else {
        while ($row = mysqli_fetch_assoc($result)) {
            $expires = !empty($row['expires_at']) ? $row['expires_at'] . ' UTC' : 'permanent';
            $reason  = !empty($row['reason'])     ? $row['reason']              : '(no reason)';
            $line    = "[{$row['action_type']}] target={$row['target_nick']} mask={$row['target_mask']} by={$row['applied_by']} at={$row['applied_at']} UTC expires={$expires} reason={$reason}";
            fputs($socket, "NOTICE {$replyNick} :{$line}\r\n");
            usleep(200000);
        }
    }

    fputs($socket, "NOTICE {$replyNick} :=== end ===\r\n");
    logEntry("channelModeration: {$replyNick} ran !actions dump", 'DEBUG');
}

// ---- Expiry timer -------------------------------------------------------

function channelModeration_checkExpired($ircdata = null) {
    global $dbconnection, $socket, $config, $timerArray;

    $result = mysqli_query($dbconnection,
        "SELECT * FROM moderation_actions
         WHERE lifted_at IS NULL AND expires_at IS NOT NULL AND expires_at <= NOW()"
    );

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $mask = $row['target_mask'];
            $nick = $row['target_nick'];
            $type = $row['action_type'];

            if ($type === 'quiet') {
                fputs($socket, "MODE {$config['channel']} -q {$mask}\r\n");
                logEntry("channelModeration: expired quiet lifted for {$nick} ({$mask})", 'INFO');
            } elseif ($type === 'ban' || $type === 'redirect') {
                fputs($socket, "MODE {$config['channel']} -b {$mask}\r\n");
                logEntry("channelModeration: expired ban lifted for {$nick} ({$mask})", 'INFO');
            }

            channelModeration_liftAction($row['id'], 'expired');
        }
    }

    // Schedule next check: fire at the soonest upcoming expiry, capped at 60s
    $next = mysqli_query($dbconnection,
        "SELECT MIN(UNIX_TIMESTAMP(expires_at)) AS next_expiry
         FROM moderation_actions
         WHERE lifted_at IS NULL AND expires_at IS NOT NULL AND expires_at > NOW()"
    );
    $next_row    = $next ? mysqli_fetch_assoc($next) : null;
    $next_expiry = ($next_row && $next_row['next_expiry']) ? (int)$next_row['next_expiry'] : time() + 60;
    $timerArray['channelModeration_checkExpired'] = min($next_expiry, time() + 60);
}

// ---- Database helpers ---------------------------------------------------

function channelModeration_recordAction($type, $nick, $mask, $duration_secs, $reason, $by_nick) {
    global $dbconnection, $timerArray;

    $type_esc   = mysqli_real_escape_string($dbconnection, $type);
    $nick_esc   = mysqli_real_escape_string($dbconnection, $nick);
    $mask_sql   = $mask !== null ? "'" . mysqli_real_escape_string($dbconnection, $mask) . "'" : 'NULL';
    $reason_esc = mysqli_real_escape_string($dbconnection, $reason ?? '');
    $by_esc     = mysqli_real_escape_string($dbconnection, $by_nick);
    $dur_sql    = $duration_secs !== null ? (int)$duration_secs : 'NULL';
    $exp_sql    = $duration_secs !== null ? "DATE_ADD(NOW(), INTERVAL {$duration_secs} SECOND)" : 'NULL';

    mysqli_query($dbconnection,
        "INSERT INTO moderation_actions
             (action_type, target_nick, target_mask, applied_by, reason, duration_seconds, applied_at, expires_at)
         VALUES
             ('{$type_esc}', '{$nick_esc}', {$mask_sql}, '{$by_esc}', '{$reason_esc}', {$dur_sql}, NOW(), {$exp_sql})"
    );

    // If this action expires sooner than the next scheduled check, reschedule now
    if ($duration_secs !== null) {
        $expiry_time    = time() + $duration_secs;
        $current_timer  = $timerArray['channelModeration_checkExpired'] ?? time() + 60;
        if ($expiry_time < $current_timer) {
            $timerArray['channelModeration_checkExpired'] = $expiry_time;
        }
    }

    return mysqli_insert_id($dbconnection);
}

function channelModeration_liftAction($id, $lifted_by) {
    global $dbconnection;
    $id_val = (int)$id;
    $by_esc = mysqli_real_escape_string($dbconnection, $lifted_by);
    mysqli_query($dbconnection,
        "UPDATE moderation_actions SET lifted_at = NOW(), lifted_by = '{$by_esc}' WHERE id = {$id_val}"
    );
}

function channelModeration_getActiveAction($nick, $type) {
    global $dbconnection;
    $nick_esc = mysqli_real_escape_string($dbconnection, $nick);
    $type_esc = mysqli_real_escape_string($dbconnection, $type);
    $result   = mysqli_query($dbconnection,
        "SELECT * FROM moderation_actions
         WHERE target_nick = '{$nick_esc}' AND action_type = '{$type_esc}'
           AND lifted_at IS NULL AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY applied_at DESC LIMIT 1"
    );
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    return null;
}

// ---- Mask / duration helpers --------------------------------------------

function channelModeration_getMask($target_nick, $redirect = false, $requestedBy = null) {
    global $channelMembers, $config, $socket;

    if (!isset($channelMembers[$target_nick])) return null;

    $hostname = $channelMembers[$target_nick]['hostname'] ?? '';
    $account  = $channelMembers[$target_nick]['account']  ?? '';

    if (empty($hostname)) {
        // Nick is tracked but hostname not yet populated — request it via WHO
        fputs($socket, "WHO {$target_nick}\r\n");
        if ($requestedBy !== null) {
            fputs($socket, "NOTICE {$requestedBy} :Hostname for {$target_nick} not yet known — WHO request sent. Please retry in a moment.\r\n");
        }
        return null;
    }

    if ($redirect && !empty($config['moderation_redirect_channel'])) {
        return "*!*@{$hostname}\${$config['moderation_redirect_channel']}";
    }

    if (!empty($config['moderation_account_ban']) && $config['moderation_account_ban'] == true && !empty($account)) {
        return "\$a:{$account}";
    }

    return "*!*@{$hostname}";
}

function channelModeration_parseDuration($str) {
    if (empty($str)) return null;
    $str = strtolower(trim($str));
    if ($str === '0' || $str === 'perm' || $str === 'permanent') return null;
    if (!preg_match('/^(\d+)([mhdw])$/', $str, $m)) return null;
    $n    = (int)$m[1];
    $mult = ['m' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800];
    $secs = $n * $mult[$m[2]];
    $max  = mktime(0, 0, 0, 12, 31, 9999) - time();
    if ($secs > $max) return false;
    return $secs;
}

function channelModeration_formatDuration($secs) {
    if ($secs <= 0) return '0s';
    $units  = [['w', 604800], ['d', 86400], ['h', 3600], ['m', 60], ['s', 1]];
    $parts  = [];
    foreach ($units as [$label, $size]) {
        if ($secs >= $size) {
            $parts[] = (int)floor($secs / $size) . $label;
            $secs   %= $size;
        }
    }
    return implode(' ', array_slice($parts, 0, 2));
}

// ---- Op-targeted message handler ----------------------------------------
// Registered into $opHandlers so channelModeration can intercept ops-targeted
// messages from quieted users before the core opNotice fallback fires.
// Returns true if the event was handled (consumed), false otherwise.

function channelMod_handleOpMessage($ircdata) {
    global $config, $socket, $channelModeration_rateLimits;

    $sender = $ircdata['usernickname'];
    $action = channelModeration_getActiveAction($sender, 'quiet');
    if (!$action) return false;

    $cooldown = !empty($config['moderation_quiet_notice_cooldown']) ? (int)$config['moderation_quiet_notice_cooldown'] : 60;
    $lastSent = $channelModeration_rateLimits[$sender] ?? 0;
    if ((time() - $lastSent) < $cooldown) {
        logEntry("Rate-limited quiet notice for {$sender}", 'DEBUG');
        return true;
    }

    $channelModeration_rateLimits[$sender] = time();
    $extra = '';
    if (!empty($action['expires_at'])) {
        $secs = strtotime($action['expires_at']) - time();
        if ($secs > 0) {
            $extra = ' Time remaining: ' . channelModeration_formatDuration($secs) . '.';
        }
    }
    fputs($socket, "NOTICE {$sender} :You are muted in this channel.{$extra}\r\n");
    logEntry("Sent quiet notice to {$sender}", 'DEBUG');
    return true;
}

// ---- Register handlers and schedule initial expiry check ----------------
global $opHandlers, $timerArray;
$opHandlers[] = 'channelMod_handleOpMessage';
$timerArray['channelModeration_checkExpired'] = time() + 60;
