<?php

// set correct path to config.php to create a database connection 

require_once '/home/Path_to_your_config.php'; // config.php

$KORAPAY_SECRET_KEY = 'YOUR_KORAPAY_SECRET_KEY'; // Replace with your Korapay secret key

$logFile     = __DIR__ . '/korapay_log.txt';
$pendingFile = __DIR__ . '/pending_korapay.json';

// basic logger
function logMsg($msg) {
    global $logFile;
    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') . " - " . $msg . "\n",
        FILE_APPEND
    );
}

// file should run via cron only, no browser execution 
if (php_sapi_name() !== 'cli') {
    die("Cron only");
}

logMsg("Rectifier started");

// DB connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    logMsg("DB connect error: " . $conn->connect_error);
    die();
}
$conn->set_charset('utf8mb4');

// load pending refs
$pending = file_exists($pendingFile)
    ? json_decode(file_get_contents($pendingFile), true)
    : [];

if (!is_array($pending)) $pending = [];

// get waiting korapay transactions with transaction status = 0 (waiting)
$stmt = $conn->prepare("
    SELECT id, amount, transaction_id 
    FROM general_transaction_logs 
    WHERE type = 'korapay' AND status = 0
");
$stmt->execute();
$result = $stmt->get_result();

logMsg("Found " . $result->num_rows . " waiting transactions");

// process each transaction
while ($row = $result->fetch_assoc()) {

    $transId = $row['id'];
    $amount  = $row['amount'];
    $korapayRef = trim($row['transaction_id']);

    if (!$korapayRef) {
        logMsg("Skip {$transId}: no reference");
        continue;
    }

    // verify transaction via Korapay API
    $url = "https://api.korapay.com/merchant/api/v1/charges/{$korapayRef}";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$KORAPAY_SECRET_KEY}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    logMsg("Check {$transId}: HTTP {$httpCode}");

    // not found yet
    if ($httpCode == 404) {
        $pending[$transId] = ['ref' => $korapayRef, 'timestamp' => time()];
        continue;
    }

    // skip on api issue
    if ($httpCode != 200) {
        continue;
    }

    $data = json_decode($response, true);
    $korapayStatus = $data['data']['status'] ?? '';

    // cancel failed or expired payment and set transaction status = -1 (cancelled)
    if ($korapayStatus === 'expired' || $korapayStatus === 'failed') {

        $update = $conn->prepare("
            UPDATE general_transaction_logs 
            SET status = -1 
            WHERE id = ? AND status = 0
        ");
        $update->bind_param("i", $transId);
        $update->execute();
        $update->close();

        logMsg("Cancelled {$transId}");

        unset($pending[$transId]);
        continue;
    }

    // if payment is successful, credit user wallet and set transaction status = 1 (paid)
    if ($korapayStatus === 'success') {

        $conn->begin_transaction();

        $stmtT = $conn->prepare("
            SELECT uid, amount, txn_fee 
            FROM general_transaction_logs 
            WHERE id = ? AND status = 0
            FOR UPDATE
        ");
        $stmtT->bind_param("i", $transId);
        $stmtT->execute();
        $txn = $stmtT->get_result()->fetch_assoc();
        $stmtT->close();

        if (!$txn) {
            $conn->rollback();
            unset($pending[$transId]);
            continue;
        }

        $credit = $txn['amount'] - ($txn['txn_fee'] ?? 0);

        $stmtU = $conn->prepare("
            UPDATE general_users 
            SET balance = balance + ?
            WHERE id = ?
        ");
        $stmtU->bind_param("di", $credit, $txn['uid']);
        $stmtU->execute();
        $stmtU->close();

        $stmtP = $conn->prepare("
            UPDATE general_transaction_logs 
            SET status = 1 
            WHERE id = ? AND status = 0
        ");
        $stmtP->bind_param("i", $transId);
        $stmtP->execute();
        $stmtP->close();

        $conn->commit();

        logMsg("Approved {$transId} ₦{$credit}");

        unset($pending[$transId]);
    } else {

        // still pending
        if (!isset($pending[$transId])) {
            $pending[$transId] = [
                'ref' => $korapayRef,
                'timestamp' => time()
            ];
        }
    }

    usleep(500000);
}

// retry old pending transactions after 90 mins
foreach ($pending as $transId => $info) {

    if (time() - $info['timestamp'] > 5400) {

        $url = "https://api.korapay.com/merchant/api/v1/charges/" . $info['ref'];
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer " . $KORAPAY_SECRET_KEY]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($httpCode == 200) {

            $data = json_decode($response, true);
            $korapayStatus = $data['data']['status'] ?? '';

            if ($korapayStatus === 'success') {

                $conn->begin_transaction();

                $stmtT = $conn->prepare("
                    SELECT uid, amount, txn_fee 
                    FROM general_transaction_logs 
                    WHERE id = ? AND status = 0
                    FOR UPDATE
                ");
                $stmtT->bind_param("i", $transId);
                $stmtT->execute();
                $txn = $stmtT->get_result()->fetch_assoc();
                $stmtT->close();

                if (!$txn) {
                    $conn->rollback();
                    unset($pending[$transId]);
                    continue;
                }

                $credit = $txn['amount'] - ($txn['txn_fee'] ?? 0);

                $stmtU = $conn->prepare("
                    UPDATE general_users 
                    SET balance = balance + ?
                    WHERE id = ?
                ");
                $stmtU->bind_param("di", $credit, $txn['uid']);
                $stmtU->execute();
                $stmtU->close();

                $stmtP = $conn->prepare("
                    UPDATE general_transaction_logs 
                    SET status = 1 
                    WHERE id = ? AND status = 0
                ");
                $stmtP->bind_param("i", $transId);
                $stmtP->execute();
                $stmtP->close();

                $conn->commit();

                logMsg("Late approved {$transId}");
            } else {

                $update = $conn->prepare("
                    UPDATE general_transaction_logs 
                    SET status = -1 
                    WHERE id = ? AND status = 0
                ");
                $update->bind_param("i", $transId);
                $update->execute();
                $update->close();
            }
        }

        unset($pending[$transId]);
    }
}

file_put_contents($pendingFile, json_encode($pending));

logMsg("Rectifier done");

$conn->close();

echo "Done\n";