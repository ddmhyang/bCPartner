<?php
include 'auth_check.php';
include 'db_connect.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=utf-8");
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['member_id']) || !isset($input['game_id']) || !isset($input['bet_amount'])) { exit; }
$member_id = $input['member_id'];
$game_id = (int)$input['game_id'];
$bet_amount = (int)$input['bet_amount'];

try {
    $pdo->beginTransaction();

    $sql_member = "SELECT points, member_name FROM youth_members WHERE member_id = ?";
    $stmt_member = $pdo->prepare($sql_member);
    $stmt_member->execute([$member_id]);
    $member = $stmt_member->fetch();

    if (!$member) { throw new Exception("존재하지 않는 회원입니다."); }
    if ($member['points'] < $bet_amount) { throw new Exception("베팅 금액보다 보유 포인트가 적습니다."); }
    
    $member_name = $member['member_name'];

    $sql_game = "SELECT game_name, outcomes FROM youth_gambling_games WHERE game_id = ?";
    $stmt_game = $pdo->prepare($sql_game);
    $stmt_game->execute([$game_id]);
    $game = $stmt_game->fetch();
    if (!$game) { throw new Exception("존재하지 않는 게임입니다."); }

    $pdo->prepare("UPDATE youth_members SET points = points - ? WHERE member_id = ?")->execute([$bet_amount, $member_id]);
    $pdo->prepare("INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)")->execute([$member_id, -$bet_amount, "{$game['game_name']} 베팅"]);

    $outcomes_array = explode(',', $game['outcomes']);
    $multiplier = (float)$outcomes_array[array_rand($outcomes_array)];
    $winnings = floor($bet_amount * $multiplier);
    $point_change = $winnings;
    $message = "";

    if ($point_change > 0) {
        $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")->execute([$point_change, $member_id]);
        $pdo->prepare("INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)")->execute([$member_id, $point_change, "{$game['game_name']} 당첨 ({$multiplier}배)"]);
        $message = "잭팟! [{$member_name}] 님이 [{$game['game_name']}]({$multiplier}배)로 {$point_change}P 획득!";
    
    } else if ($point_change < 0) {
        $pdo->prepare("UPDATE youth_members SET points = points + ? WHERE member_id = ?")->execute([$point_change, $member_id]);
        $pdo->prepare("INSERT INTO youth_point_logs (member_id, point_change, reason) VALUES (?, ?, ?)")->execute([$member_id, $point_change, "{$game['game_name']} 파산 ({$multiplier}배)"]);
        $message = "꽝! [{$member_name}] 님이 [{$game['game_name']}]({$multiplier}배)로 {$point_change}P 손해...";
    } else {
        $message = "본전... [{$member_name}] 님이 [{$game['game_name']}]({$multiplier}배)로 변동 없습니다.";
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $message,
        'multiplier' => $multiplier,
        'winnings' => $point_change
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([ 'status' => 'error', 'message' => '도박 실패: ' . $e->getMessage() ]);
}
?>