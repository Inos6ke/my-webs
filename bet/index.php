<?php
require_once 'functions.php';

// Инициализация или загрузка данных
$bets = loadBets();
$total = calculateTotal($bets);
$stats = calculateStats($bets, $total['initial']);

// Получаем последний выбранный вид спорта из куки или используем футбол по умолчанию
$selectedSport = $_COOKIE['selected_sport'] ?? 'cybersport';

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_bet') {
        $newBet = [
            'date' => $_POST['date'] ?? date('Y-m-d'),
            'initial' => floatval($_POST['initial'] ?? 0),
            'amount' => floatval($_POST['amount'] ?? 0),
            'coefficient' => floatval($_POST['coefficient'] ?? 1),
            'type' => $_POST['type'] ?? 'single',
            'sport' => $_POST['sport'] ?? 'football',
            'is_freebet' => isset($_POST['is_freebet']),
            'result' => $_POST['result'] ?? 'pending',
            'note' => $_POST['note'] ?? ''
        ];
        
        $bets[] = $newBet;
        saveBets($bets);
        
        // Сохраняем выбранный вид спорта в куки на 30 дней
        setcookie('selected_sport', $newBet['sport'], time() + (86400 * 30), "/");
        $selectedSport = $newBet['sport'];
        
        // Возвращаем JSON для AJAX
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'bets' => $bets,
                'total' => calculateTotal($bets),
                'stats' => calculateStats($bets, $total['initial'])
            ]);
            exit;
        }
        
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($action === 'delete_bet') {
        $index = $_POST['index'] ?? null;
        if ($index !== null && isset($bets[$index])) {
            array_splice($bets, $index, 1);
            saveBets($bets);
            $total = calculateTotal($bets);
            $stats = calculateStats($bets, $total['initial']);
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'bets' => $bets,
                    'total' => $total,
                    'stats' => $stats
                ]);
                exit;
            }
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } elseif ($action === 'update_bet_result') {
        $index = $_POST['index'] ?? null;
        $result = $_POST['result'] ?? null;
        
        if ($index !== null && $result !== null && isset($bets[$index])) {
            $bets[$index]['result'] = $result;
            saveBets($bets);
            $total = calculateTotal($bets);
            $stats = calculateStats($bets, $total['initial']);
            
            header('Content-Type: application/json');
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
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Трекер ставок PRO</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-4 mb-5">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="m-0 fs-4 fs-md-3">
                <i class="fas fa-chart-line text-primary"></i> Трекер ставок PRO
            </h1>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-question-circle"></i> <span class="d-none d-md-inline">Помощь</span>
            </button>
        </header>
        
        <!-- Форма для добавления ставки -->
        <div class="card mb-4 animate__animated animate__fadeIn">
            <div class="card-header bg-dark text-white">
                <h5 class="m-0"><i class="fas fa-plus-circle"></i> Новая ставка</h5>
            </div>
            <div class="card-body">
                <form id="betForm" method="POST">
                    <input type="hidden" name="action" value="add_bet">
                    <input type="hidden" name="result" value="pending">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-2">
                            <label class="form-label"><i class="fas fa-calendar-alt"></i> <span class="d-none d-md-inline">Дата</span></label>
                            <input type="text" class="form-control datepicker" name="date" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label"><i class="fas fa-wallet"></i> <span class="d-none d-md-inline">Начальная сумма</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₽</span>
                                <input type="number" step="0.01" class="form-control" id="initialAmount" name="initial" value="<?= $total['current'] ?? 0 ?>" required>
                            </div>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label"><i class="fas fa-money-bill-wave"></i> <span class="d-none d-md-inline">Сумма ставки</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₽</span>
                                <input type="number" step="0.01" class="form-control" id="betAmount" name="amount" required>
                            </div>
                            <small class="text-muted" id="recommendedBet">Следующая ставка: <?= number_format(($total['current'] ?? 0) * 0.1, 2) ?> ₽ (10%)</small>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label"><i class="fas fa-percentage"></i> <span class="d-none d-md-inline">Коэффициент</span></label>
                            <input type="number" step="0.01" class="form-control" name="coefficient" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label"><i class="fas fa-running"></i> <span class="d-none d-md-inline">Вид спорта</span></label>
                            <select class="form-select sport-select" name="sport" required>
                                <option value="football" data-icon="fas fa-futbol" <?= $selectedSport === 'football' ? 'selected' : '' ?>>Футбол</option>
                                <option value="hockey" data-icon="fas fa-hockey-puck" <?= $selectedSport === 'hockey' ? 'selected' : '' ?>>Хоккей</option>
                                <option value="basketball" data-icon="fas fa-basketball-ball" <?= $selectedSport === 'basketball' ? 'selected' : '' ?>>Баскетбол</option>
                                <option value="tennis" data-icon="fas fa-table-tennis" <?= $selectedSport === 'tennis' ? 'selected' : '' ?>>Теннис</option>
                                <option value="cybersport" data-icon="fas fa-gamepad" <?= $selectedSport === 'cybersport' ? 'selected' : '' ?>>Киберспорт</option>
                                <option value="other" data-icon="fas fa-question" <?= $selectedSport === 'other' ? 'selected' : '' ?>>Другой</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label"><i class="fas fa-tag"></i> <span class="d-none d-md-inline">Тип ставки</span></label>
                            <select class="form-select type-select" name="type">
                                <option value="single">Одиночная</option>
                                <option value="express">Экспресс</option>
                                <option value="system">Система</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label"><i class="fas fa-sticky-note"></i> <span class="d-none d-md-inline">Примечание</span></label>
                            <input type="text" class="form-control" name="note" placeholder="Описание ставки">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" name="is_freebet" id="is_freebet">
                                <label class="form-check-label" for="is_freebet"><i class="fas fa-gift"></i> <span class="d-none d-md-inline">Фрибет</span></label>
                            </div>
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-save"></i> <span class="d-none d-md-inline">Добавить ставку</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="card mb-4 animate__animated animate__fadeIn">
            <div class="card-header bg-dark text-white">
                <h5 class="m-0"><i class="fas fa-chart-pie"></i> Статистика</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-6 col-md-3 mb-3">
                        <div class="stat-card bg-primary text-white p-2 p-md-3 rounded shadow">
                            <h6 class="fs-6"><i class="fas fa-piggy-bank"></i> <span class="d-none d-md-inline">Начальный банк</span></h6>
                            <h4 class="fs-5 fs-md-4"><?= number_format($total['initial'] ?? 0, 2) ?> ₽</h4>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="stat-card bg-success text-white p-2 p-md-3 rounded shadow">
                            <h6 class="fs-6"><i class="fas fa-wallet"></i> <span class="d-none d-md-inline">Текущий банк</span></h6>
                            <h4 class="fs-5 fs-md-4"><?= number_format($total['current'] ?? 0, 2) ?> ₽</h4>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="stat-card <?= ($stats['profit'] >= 0) ? 'bg-success' : 'bg-danger' ?> text-white p-2 p-md-3 rounded shadow">
                            <h6 class="fs-6"><i class="fas fa-money-bill-trend-up"></i> <span class="d-none d-md-inline">Прибыль/Убыток</span></h6>
                            <h4 class="fs-5 fs-md-4"><?= number_format($stats['profit'] ?? 0, 2) ?> ₽</h4>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <div class="stat-card <?= ($stats['roi'] >= 0) ? 'bg-success' : 'bg-danger' ?> text-white p-2 p-md-3 rounded shadow">
                            <h6 class="fs-6"><i class="fas fa-percent"></i> <span class="d-none d-md-inline">Доходность (ROI)</span></h6>
                            <h4 class="fs-5 fs-md-4"><?= number_format($stats['roi'] ?? 0, 2) ?>%</h4>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <canvas id="bankrollChart" height="250"></canvas>
                    </div>
                    <div class="col-md-6">
                        <canvas id="resultsChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Таблица ставок -->
        <div class="card animate__animated animate__fadeIn">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-history"></i> История ставок</h5>
                <div>
                    <button class="btn btn-sm btn-outline-light" id="exportBtn">
                        <i class="fas fa-file-export"></i> <span class="d-none d-md-inline">Экспорт</span>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="betsTable" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Дата</th>
                                <th>Сумма</th>
                                <th class="d-none d-md-table-cell">Коэф.</th>
                                <th class="d-none d-md-table-cell">Выигрыш</th>
                                <th>Спорт</th>
                                <th class="d-none d-md-table-cell">Тип</th>
                                <th class="d-none d-md-table-cell">Фрибет</th>
                                <th>Результат</th>
                                <th>Прибыль</th>
                                <th class="d-none d-md-table-cell">Примечание</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bets as $index => $bet): ?>
                                <tr class="<?= $bet['result'] === 'win' ? 'table-success' : ($bet['result'] === 'lose' ? 'table-danger' : 'table-warning') ?>">
                                    <td><?= htmlspecialchars($bet['date']) ?></td>
                                    <td><?= number_format($bet['amount'], 2) ?> ₽</td>
                                    <td class="d-none d-md-table-cell"><?= number_format($bet['coefficient'], 2) ?></td>
                                    <td class="d-none d-md-table-cell"><?= number_format($bet['amount'] * $bet['coefficient'], 2) ?> ₽</td>
                                    <td>
                                        <?php 
                                            $sportIcons = [
                                                'football' => 'fas fa-futbol',
                                                'hockey' => 'fas fa-hockey-puck',
                                                'basketball' => 'fas fa-basketball-ball',
                                                'tennis' => 'fas fa-table-tennis',
                                                'cybersport' => 'fas fa-gamepad',
                                                'other' => 'fas fa-question'
                                            ];
                                            $icon = $sportIcons[$bet['sport']] ?? 'fas fa-question';
                                        ?>
                                        <i class="<?= $icon ?>"></i> <span class="d-none d-md-inline"><?= ucfirst($bet['sport']) ?></span>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php 
                                            $typeLabels = [
                                                'single' => 'Одиночная',
                                                'express' => 'Экспресс',
                                                'system' => 'Система'
                                            ];
                                            echo $typeLabels[$bet['type']] ?? $bet['type'];
                                        ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php if ($bet['is_freebet']): ?>
                                            <span class="badge bg-info"><i class="fas fa-gift"></i> Да</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Нет</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm dropdown-toggle result-btn 
                                                <?= $bet['result'] === 'win' ? 'btn-success' : 
                                                   ($bet['result'] === 'lose' ? 'btn-danger' : 'btn-warning') ?>" 
                                                type="button" id="resultDropdown<?= $index ?>" 
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                <?php if ($bet['result'] === 'win'): ?>
                                                    <i class="fas fa-trophy"></i> <span class="d-none d-md-inline">Выигрыш</span>
                                                <?php elseif ($bet['result'] === 'lose'): ?>
                                                    <i class="fas fa-times"></i> <span class="d-none d-md-inline">Проигрыш</span>
                                                <?php else: ?>
                                                    <i class="fas fa-clock"></i> <span class="d-none d-md-inline">Ожидание</span>
                                                <?php endif; ?>
                                            </button>
                                            <ul class="dropdown-menu" aria-labelledby="resultDropdown<?= $index ?>">
                                                <li>
                                                    <a class="dropdown-item change-result" href="#" data-index="<?= $index ?>" data-result="win">
                                                        <i class="fas fa-trophy text-success"></i> Выигрыш
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item change-result" href="#" data-index="<?= $index ?>" data-result="lose">
                                                        <i class="fas fa-times text-danger"></i> Проигрыш
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item change-result" href="#" data-index="<?= $index ?>" data-result="pending">
                                                        <i class="fas fa-clock text-warning"></i> Ожидание
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                    <td class="fw-bold <?= calculateBetProfit($bet) >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format(calculateBetProfit($bet), 2) ?> ₽
                                    </td>
                                    <td class="d-none d-md-table-cell"><?= htmlspecialchars($bet['note']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger delete-btn" data-index="<?= $index ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bets)): ?>
                                <tr>
                                    <td colspan="11" class="text-center text-muted py-4">Нет данных о ставках</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно помощи -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="helpModalLabel"><i class="fas fa-question-circle"></i> Помощь</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6><i class="fas fa-info-circle text-primary"></i> Как пользоваться трекером:</h6>
                    <ol>
                        <li>Заполните форму "Новая ставка"</li>
                        <li>Укажите сумму ставки и коэффициент</li>
                        <li>Отметьте, если это фрибет</li>
                        <li>Нажмите "Добавить ставку"</li>
                        <li>После добавления можно указать результат в таблице через выпадающее меню</li>
                        <li>Можно добавить несколько ставок подряд, а затем указать их результаты</li>
                    </ol>
                    <hr>
                    <h6><i class="fas fa-lightbulb text-warning"></i> Советы:</h6>
                    <ul>
                        <li>Фрибеты не уменьшают ваш банк при проигрыше</li>
                        <li>При выигрыше фрибета прибыль рассчитывается без учета суммы ставки</li>
                        <li>Используйте примечания для удобного поиска ставок</li>
                        <li>Система рекомендует ставить 10% от текущего банка (можно кликнуть на подсказку для автозаполнения)</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Подключение JavaScript библиотек -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ru.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- FileSaver.js для экспорта -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Инициализация Flatpickr
            $('.datepicker').flatpickr({
                dateFormat: "Y-m-d",
                defaultDate: "today",
                locale: "ru"
            });
            
            // Инициализация Select2 с иконками
            $('.sport-select').select2({
                minimumResultsForSearch: Infinity,
                width: '100%',
                templateResult: formatSportIcon,
                templateSelection: formatSportIcon
            });
            
            function formatSportIcon(sport) {
                if (!sport.id) return sport.text;
                const $icon = $('<i>').addClass($(sport.element).data('icon')).addClass('me-2');
                const $wrapper = $('<span>').append($icon).append(sport.text);
                return $wrapper;
            }
            
            // Инициализация Select2 для типа ставки
            $('.type-select').select2({
                minimumResultsForSearch: Infinity,
                width: '100%'
            });
            
            // Инициализация DataTable с адаптивностью
            $('#betsTable').DataTable({
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/ru.json'
                },
                order: [[0, 'desc']],
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                responsive: true,
                columnDefs: [
                    { responsivePriority: 1, targets: 0 }, // Дата
                    { responsivePriority: 2, targets: 1 }, // Сумма
                    { responsivePriority: 3, targets: 7 }, // Результат
                    { responsivePriority: 4, targets: 8 }, // Прибыль
                    { responsivePriority: 5, targets: 10 } // Действия
                ]
            });
            
            // Обработка удаления ставки
            $(document).on('click', '.delete-btn', function() {
                const index = $(this).data('index');
                
                Swal.fire({
                    title: 'Удалить ставку?',
                    text: "Вы не сможете восстановить эту запись!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Да, удалить!',
                    cancelButtonText: 'Отмена'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('', {
                            action: 'delete_bet',
                            index: index
                        }, function(response) {
                            if (response && response.success) {
                                Swal.fire(
                                    'Удалено!',
                                    'Ваша ставка была удалена.',
                                    'success'
                                ).then(() => {
                                    location.reload();
                                });
                            } else {
                                location.reload();
                            }
                        }, 'json').fail(function() {
                            location.reload();
                        });
                    }
                });
            });
            
            // Инициализация графиков
            const bankrollCtx = document.getElementById('bankrollChart').getContext('2d');
            const resultsCtx = document.getElementById('resultsChart').getContext('2d');
            
            // График банкролла
            const bankrollChart = new Chart(bankrollCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($bets, 'date')) ?>,
                    datasets: [{
                        label: 'Банкролл',
                        data: <?= json_encode(getBankrollHistory($bets, $total['initial'])) ?>,
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Банк: ' + context.parsed.y.toFixed(2) + ' ₽';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
            
            // График результатов
            const resultsChart = new Chart(resultsCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Выигрыши', 'Проигрыши', 'В ожидании'],
                    datasets: [{
                        data: [<?= $stats['wins'] ?>, <?= $stats['losses'] ?>, <?= $stats['pending'] ?? 0 ?>],
                        backgroundColor: [
                            'rgba(40, 167, 69, 0.8)',
                            'rgba(220, 53, 69, 0.8)',
                            'rgba(255, 193, 7, 0.8)'
                        ],
                        borderColor: [
                            'rgba(40, 167, 69, 1)',
                            'rgba(220, 53, 69, 1)',
                            'rgba(255, 193, 7, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Экспорт данных
            $('#exportBtn').click(function() {
                const data = <?= json_encode($bets) ?>;
                const headers = ['Дата', 'Сумма', 'Коэф.', 'Выигрыш', 'Спорт', 'Тип', 'Фрибет', 'Результат', 'Прибыль', 'Примечание'];
                
                let csv = headers.join(';') + '\n';
                
                data.forEach(bet => {
                    const row = [
                        bet.date,
                        bet.amount,
                        bet.coefficient,
                        bet.amount * bet.coefficient,
                        bet.sport,
                        bet.type,
                        bet.is_freebet ? 'Да' : 'Нет',
                        bet.result === 'win' ? 'Выигрыш' : (bet.result === 'lose' ? 'Проигрыш' : 'Ожидание'),
                        calculateBetProfit(bet),
                        bet.note
                    ];
                    
                    csv += row.join(';') + '\n';
                });
                
                const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                saveAs(blob, 'ставки_' + new Date().toISOString().slice(0, 10) + '.csv');
                
                Swal.fire(
                    'Экспорт завершен!',
                    'Ваши данные были сохранены в CSV файл.',
                    'success'
                );
            });
            
            // AJAX отправка формы
            $('#betForm').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const formData = form.serialize();
                
                $.post('', formData, function(response) {
                    if (response && response.success) {
                        // Показываем уведомление об успешном добавлении
                        Swal.fire({
                            icon: 'success',
                            title: 'Ставка добавлена',
                            text: 'Вы можете изменить результат в таблице',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            // Перезагружаем страницу для обновления данных
                            location.reload();
                        });
                    }
                }, 'json').fail(function() {
                    location.reload();
                });
            });
            
            // Обработчик изменения результата ставки
            $(document).on('click', '.change-result', function(e) {
                e.preventDefault();
                const index = $(this).data('index');
                const result = $(this).data('result');
                
                $.post('', {
                    action: 'update_bet_result',
                    index: index,
                    result: result
                }, function(response) {
                    if (response && response.success) {
                        // Обновляем строку в таблице
                        const row = $(`#betsTable tbody tr:nth-child(${index + 1})`);
                        
                        // Обновляем класс строки
                        row.removeClass('table-success table-danger table-warning');
                        if (result === 'win') {
                            row.addClass('table-success');
                        } else if (result === 'lose') {
                            row.addClass('table-danger');
                        } else {
                            row.addClass('table-warning');
                        }
                        
                        // Обновляем кнопку результата
                        const resultBtn = row.find('.result-btn');
                        resultBtn.removeClass('btn-success btn-danger btn-warning');
                        
                        if (result === 'win') {
                            resultBtn.addClass('btn-success');
                            resultBtn.html('<i class="fas fa-trophy"></i> <span class="d-none d-md-inline">Выигрыш</span>');
                        } else if (result === 'lose') {
                            resultBtn.addClass('btn-danger');
                            resultBtn.html('<i class="fas fa-times"></i> <span class="d-none d-md-inline">Проигрыш</span>');
                        } else {
                            resultBtn.addClass('btn-warning');
                            resultBtn.html('<i class="fas fa-clock"></i> <span class="d-none d-md-inline">Ожидание</span>');
                        }
                        
                        // Обновляем прибыль
                        row.find('td:nth-child(9)').html(response.profit + ' ₽')
                            .removeClass('text-success text-danger')
                            .addClass(parseFloat(response.profit) >= 0 ? 'text-success' : 'text-danger');
                        
                        // Обновляем статистику
                        $('.stat-card:nth-child(1) h4').text(response.total.initial.toFixed(2) + ' ₽');
                        $('.stat-card:nth-child(2) h4').text(response.total.current.toFixed(2) + ' ₽');
                        $('.stat-card:nth-child(3) h4').text(response.total.profit.toFixed(2) + ' ₽');
                        $('.stat-card:nth-child(4) h4').text(response.stats.roi.toFixed(2) + '%');
                        
                        // Обновляем графики
                        bankrollChart.data.datasets[0].data = response.bankrollHistory;
                        bankrollChart.update();
                        
                        resultsChart.data.datasets[0].data = [
                            response.stats.wins,
                            response.stats.losses,
                            response.stats.pending
                        ];
                        resultsChart.update();
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Результат обновлен',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }
                }, 'json');
            });
            
            // Анимация при наведении на карточки
            $('.stat-card').hover(
                function() {
                    $(this).addClass('animate__animated animate__pulse');
                },
                function() {
                    $(this).removeClass('animate__animated animate__pulse');
                }
            );
            
            // Автоматический расчет рекомендуемой ставки (10% от банка)
            function updateRecommendedBet() {
                const currentBank = parseFloat($('#initialAmount').val()) || 0;
                const recommendedAmount = (currentBank * 0.1).toFixed(2);
                $('#recommendedBet').text(`Следующая ставка: ${recommendedAmount} ₽ (10%)`);
            }
            
            // Обновляем при изменении начальной суммы
            $('#initialAmount').on('input', updateRecommendedBet);
            
            // При клике на подсказку - подставляем значение в поле ставки
            $('#recommendedBet').click(function() {
                const recommendedText = $(this).text();
                const recommendedAmount = parseFloat(recommendedText.match(/[\d.]+/)[0]);
                $('#betAmount').val(recommendedAmount.toFixed(2));
            });
            
            // Инициализация при загрузке
            updateRecommendedBet();
            
            // Адаптация таблицы при изменении размера окна
            $(window).on('resize', function() {
                bankrollChart.resize();
                resultsChart.resize();
            });
        });
        
        // Функция для расчета прибыли (для использования в JS)
        function calculateBetProfit(bet) {
            if (bet.result === 'win') {
                if (bet.is_freebet) {
                    return bet.amount * (bet.coefficient - 1);
                }
                return bet.amount * bet.coefficient - bet.amount;
            } else if (bet.result === 'lose') {
                return bet.is_freebet ? 0 : -bet.amount;
            }
            return 0;
        }
    </script>
</body>
</html>