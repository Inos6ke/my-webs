<?php
// Функция для загрузки ставок из файла
function loadBets() {
    if (file_exists('data.json')) {
        $data = file_get_contents('data.json');
        return json_decode($data, true) ?: [];
    }
    return [];
}

// Функция для сохранения ставок в файл
function saveBets($bets) {
    file_put_contents('data.json', json_encode($bets, JSON_PRETTY_PRINT));
}

// Расчет прибыли от одной ставки
function calculateBetProfit($bet) {
    if ($bet['result'] === 'win') {
        if ($bet['is_freebet']) {
            return $bet['amount'] * ($bet['coefficient'] - 1);
        }
        return $bet['amount'] * $bet['coefficient'] - $bet['amount'];
    } elseif ($bet['result'] === 'lose') {
        return $bet['is_freebet'] ? 0 : -$bet['amount'];
    }
    return 0;
}

// Расчет текущего банка и общего профита
function calculateTotal($bets) {
    $initial = empty($bets) ? 0 : (float)$bets[0]['initial'];
    $current = $initial;
    
    foreach ($bets as $bet) {
        if ($bet['result'] === 'win') {
            if ($bet['is_freebet']) {
                $current += $bet['amount'] * ($bet['coefficient'] - 1);
            } else {
                $current += $bet['amount'] * ($bet['coefficient'] - 1);
            }
        } elseif ($bet['result'] === 'lose' && !$bet['is_freebet']) {
            $current -= $bet['amount'];
        }
    }
    
    return [
        'initial' => $initial,
        'current' => $current,
        'profit' => $current - $initial
    ];
}

// Расчет статистики
function calculateStats($bets, $initialBank) {
    $wins = 0;
    $losses = 0;
    $pending = 0;
    $totalBets = count($bets);
    $profit = 0;
    
    foreach ($bets as $bet) {
        if ($bet['result'] === 'win') {
            $wins++;
            $profit += calculateBetProfit($bet);
        } elseif ($bet['result'] === 'lose') {
            $losses++;
            $profit += calculateBetProfit($bet);
        } else {
            $pending++;
        }
    }
    
    $winRate = ($wins + $losses) > 0 ? round($wins / ($wins + $losses) * 100, 2) : 0;
    $roi = $initialBank > 0 ? round($profit / $initialBank * 100, 2) : 0;
    
    return [
        'total_bets' => $totalBets,
        'wins' => $wins,
        'losses' => $losses,
        'pending' => $pending,
        'win_rate' => $winRate,
        'profit' => $profit,
        'roi' => $roi
    ];
}

// Получение истории банкролла для графика
function getBankrollHistory($bets, $initialBank) {
    $history = [];
    $current = $initialBank;
    
    // Добавляем начальную точку
    $history[] = $initialBank;
    
    foreach ($bets as $bet) {
        if ($bet['result'] === 'win') {
            if ($bet['is_freebet']) {
                $current += $bet['amount'] * ($bet['coefficient'] - 1);
            } else {
                $current += $bet['amount'] * ($bet['coefficient'] - 1);
            }
        } elseif ($bet['result'] === 'lose' && !$bet['is_freebet']) {
            $current -= $bet['amount'];
        }
        $history[] = $current;
    }
    
    return $history;
}
?>