<?php
require_once __DIR__ . '/../config/logger.php';
$db = getDB();

if ($resource === 'stats') {
    requireAdmin();

    $now = new DateTime('now', new DateTimeZone('America/Bogota'));
    $today = $now->format('Y-m-d');
    $monthStart = $now->format('Y-m-01');
    $monthEnd = $now->format('Y-m-t');
    $yearStart = $now->format('Y-01-01');

    if ($method === 'GET' && $action === 'full') {
        try {
            $base = $db->query("
                SELECT o.id, o.total, o.status, o.created_at, o.turn_number, o.paid_at,
                       u.full_name, u.grade, u.email,
                       oi.product_name, oi.quantity, oi.subtotal, oi.product_id,
                       p.category, p.stock as current_stock
                FROM orders o
                JOIN users u ON u.id = o.user_id
                LEFT JOIN order_items oi ON oi.order_id = o.id
                LEFT JOIN products p ON p.id = oi.product_id
                ORDER BY o.created_at DESC
            ")->fetchAll();

            $orders = [];
            foreach ($base as $row) {
                $oid = $row['id'];
                if (!isset($orders[$oid])) {
                    $orders[$oid] = [
                        'id' => $oid, 'total' => (float)$row['total'],
                        'status' => $row['status'], 'created_at' => $row['created_at'],
                        'paid_at' => $row['paid_at'], 'turn_number' => $row['turn_number'],
                        'full_name' => $row['full_name'], 'grade' => $row['grade'],
                        'email' => $row['email'], 'items' => []
                    ];
                }
                if ($row['product_name']) {
                    $orders[$oid]['items'][] = [
                        'product_name' => $row['product_name'],
                        'quantity' => (int)$row['quantity'],
                        'subtotal' => (float)$row['subtotal'],
                        'product_id' => $row['product_id'],
                        'category' => $row['category'],
                        'current_stock' => (int)$row['current_stock']
                    ];
                }
            }
            $orders = array_values($orders);
            $paid = array_filter($orders, fn($o) => $o['status'] === 'paid');
            $paidArr = array_values($paid);

            $todayOrders = array_filter($orders, fn($o) => str_starts_with($o['created_at'], $today));
            $todayPaid = array_filter($paidArr, fn($o) => str_starts_with($o['created_at'], $today));
            $monthOrders = array_filter($orders, fn($o) => $o['created_at'] >= $monthStart && $o['created_at'] <= $monthEnd . ' 23:59:59');
            $monthPaid = array_filter($paidArr, fn($o) => $o['created_at'] >= $monthStart);

            $totalPaidRevenue = array_sum(array_column($paidArr, 'total'));
            $avgTicket = count($paidArr) > 0 ? $totalPaidRevenue / count($paidArr) : 0;

            $dailySalesMonth = [];
            foreach ($monthPaid as $o) {
                $day = substr($o['created_at'], 0, 10);
                $dailySalesMonth[$day] = ($dailySalesMonth[$day] ?? 0) + $o['total'];
            }
            $maxDay = $dailySalesMonth ? array_keys($dailySalesMonth, max($dailySalesMonth))[0] : null;
            $minDay = $dailySalesMonth ? array_keys($dailySalesMonth, min($dailySalesMonth))[0] : null;

            $productSales = [];
            $categorySales = [];
            foreach ($paidArr as $o) {
                foreach ($o['items'] as $item) {
                    $pn = $item['product_name'];
                    $cat = $item['category'] ?? 'general';
                    if (!isset($productSales[$pn])) $productSales[$pn] = ['qty' => 0, 'revenue' => 0, 'stock' => $item['current_stock'], 'id' => $item['product_id']];
                    $productSales[$pn]['qty'] += $item['quantity'];
                    $productSales[$pn]['revenue'] += $item['subtotal'];
                    $categorySales[$cat] = ($categorySales[$cat] ?? 0) + $item['subtotal'];
                }
            }
            arsort($categorySales);
            uasort($productSales, fn($a, $b) => $b['qty'] - $a['qty']);
            $topProducts = array_slice($productSales, 0, 10, true);
            $bottomProducts = array_slice(array_reverse($productSales, true), 0, 5, true);

            $rotationProducts = [];
            foreach ($productSales as $name => $data) {
                if ($data['stock'] > 0) $rotationProducts[$name] = ['ratio' => $data['qty'] / max($data['stock'], 1), 'qty' => $data['qty'], 'stock' => $data['stock']];
            }
            uasort($rotationProducts, fn($a, $b) => $b['ratio'] - $a['ratio']);
            $topRotation = array_slice($rotationProducts, 0, 5, true);

            $studentConsumption = [];
            foreach ($paidArr as $o) {
                $k = $o['full_name'];
                if (!isset($studentConsumption[$k])) $studentConsumption[$k] = ['name' => $o['full_name'], 'grade' => $o['grade'], 'total' => 0, 'orders' => 0];
                $studentConsumption[$k]['total'] += $o['total'];
                $studentConsumption[$k]['orders']++;
            }
            uasort($studentConsumption, fn($a, $b) => $b['total'] - $a['total']);
            $topStudents = array_slice(array_values($studentConsumption), 0, 10);

            $gradeConsumption = [];
            foreach ($paidArr as $o) {
                $g = $o['grade'];
                if (!isset($gradeConsumption[$g])) $gradeConsumption[$g] = ['grade' => $g, 'total' => 0, 'orders' => 0];
                $gradeConsumption[$g]['total'] += $o['total'];
                $gradeConsumption[$g]['orders']++;
            }
            foreach ($gradeConsumption as &$g) $g['avg_ticket'] = $g['orders'] > 0 ? $g['total'] / $g['orders'] : 0;
            unset($g);
            uasort($gradeConsumption, fn($a, $b) => $b['total'] - $a['total']);

            $waitTimes = [];
            foreach ($paidArr as $o) {
                if ($o['paid_at'] && $o['created_at']) {
                    $diff = strtotime($o['paid_at']) - strtotime($o['created_at']);
                    if ($diff > 0 && $diff < 7200) $waitTimes[] = $diff;
                }
            }
            $avgWait = $waitTimes ? array_sum($waitTimes) / count($waitTimes) : 0;

            $hourlyUsage = array_fill(0, 24, 0);
            foreach ($orders as $o) {
                $h = (int)date('G', strtotime($o['created_at']));
                $hourlyUsage[$h]++;
            }

            $morningRevenue = 0; $afternoonRevenue = 0;
            foreach ($paidArr as $o) {
                $h = (int)date('G', strtotime($o['created_at']));
                if ($h < 12) $morningRevenue += $o['total'];
                else $afternoonRevenue += $o['total'];
            }

            $turnsStmt = $db->query("SELECT DATE(updated_at) as day, MAX(current_turn) as turns FROM queue_config GROUP BY DATE(updated_at) ORDER BY day DESC LIMIT 30");
            $dailyTurns = $turnsStmt->fetchAll();

            $mostExpensive = $monthPaid ? array_reduce(array_values(array_filter($monthPaid)), fn($carry, $o) => (!$carry || $o['total'] > $carry['total']) ? $o : $carry, null) : null;

            $usersStmt = $db->query("SELECT COUNT(*) as blocked FROM users WHERE blocked=1 AND role='user'")->fetch();
            $newUsersStmt = $db->query("SELECT COUNT(*) as new_users FROM users WHERE role='user' AND created_at >= '$monthStart'")->fetch();
            $gradeDistStmt = $db->query("SELECT grade, COUNT(*) as count FROM users WHERE role='user' GROUP BY grade ORDER BY count DESC")->fetchAll();
            $totalUsers = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();

            $gradeDistribution = [];
            foreach ($gradeDistStmt as $row) {
                $gradeDistribution[] = ['grade' => $row['grade'], 'count' => (int)$row['count'], 'pct' => $totalUsers > 0 ? round($row['count'] / $totalUsers * 100, 1) : 0];
            }

            $sixMonths = [];
            for ($i = 5; $i >= 0; $i--) {
                $d = new DateTime('now', new DateTimeZone('America/Bogota'));
                $d->modify("-$i months");
                $m = $d->format('Y-m');
                $label = $d->format('M');
                $rev = array_sum(array_column(array_filter($paidArr, fn($o) => str_starts_with($o['created_at'], $m)), 'total'));
                $sixMonths[] = ['month' => $m, 'label' => $label, 'revenue' => $rev];
            }

            jsonResponse([
                'summary' => [
                    'today_revenue' => array_sum(array_column(array_values($todayPaid), 'total')),
                    'today_orders' => count($todayOrders),
                    'today_paid' => count($todayPaid),
                    'month_revenue' => array_sum(array_column(array_values($monthPaid), 'total')),
                    'month_orders' => count($monthOrders),
                    'total_revenue' => $totalPaidRevenue,
                    'total_orders' => count($orders),
                    'paid_orders' => count($paidArr),
                    'pending_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'pending')),
                    'cancelled_orders' => count(array_filter($orders, fn($o) => $o['status'] === 'cancelled')),
                    'avg_ticket' => round($avgTicket, 0),
                    'avg_wait_seconds' => round($avgWait, 0),
                    'morning_revenue' => $morningRevenue,
                    'afternoon_revenue' => $afternoonRevenue,
                    'blocked_users' => (int)$usersStmt['blocked'],
                    'new_users_month' => (int)$newUsersStmt['new_users'],
                    'total_users' => (int)$totalUsers,
                    'total_products' => $db->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn(),
                ],
                'best_day' => $maxDay ? ['date' => $maxDay, 'revenue' => $dailySalesMonth[$maxDay]] : null,
                'worst_day' => $minDay ? ['date' => $minDay, 'revenue' => $dailySalesMonth[$minDay]] : null,
                'daily_sales_month' => $dailySalesMonth,
                'top_products' => $topProducts,
                'bottom_products' => $bottomProducts,
                'top_rotation' => $topRotation,
                'category_sales' => $categorySales,
                'top_students' => $topStudents,
                'grade_consumption' => array_values($gradeConsumption),
                'grade_distribution' => $gradeDistribution,
                'hourly_usage' => $hourlyUsage,
                'daily_turns' => $dailyTurns,
                'most_expensive_order' => $mostExpensive,
                'six_months' => $sixMonths,
            ]);
        } catch (PDOException $e) {
            logDatabaseError('stats/full', $e);
            jsonError('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    jsonError('Ruta stats no encontrada', 404);
}