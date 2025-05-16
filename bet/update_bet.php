<?php
require_once 'functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $index = $_POST['index'] ?? null;
    $result = $_POST['result'] ?? null;
    
    if ($index !== null && $result !== null && in_array($result, ['win', 'lose', 'pending'])) {
        $bets = loadBets();
        
        if (isset($bets[$index])) {
            $bets[$index]['result'] = $result;
            saveBets($bets);
            
            // Пересчитываем статистику
            $total = calculateTotal($bets);
            $stats = calculateStats($bets, $total['initial']);
            
            echo json_encode([
                'success' => true,
                'profit' => number_format(calculateBetProfit($bets[$index]), 2),
                'total' => $total,
                'stats' => $stats,
                'bankrollHistory' => getBankrollHistory($bets, $total['initial'])
            ]);
            exit;
        }
    }
}

echo json_encode(['success' => false]);
?>