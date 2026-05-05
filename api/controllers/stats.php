<?php
$db = getDB();

if ($resource === 'stats') {
    requireAdmin();

    $tz = new DateTimeZone('America/Bogota');
    $now = new DateTime('now', $tz);
    $today = $now->format('Y-m-d');
    $monthStart = $now->format('Y-m-01');
    $yearStart = $now->format('Y-01-01');

    if ($method === 'GET' && $action === 'full') {
        try {
            $summary = $db->query("
                SELECT
                    COALESCE(SUM(CASE WHEN status='paid' AND DATE(created_at)='{$today}' THEN total ELSE 0 END), 0) as today_revenue,
                    COUNT(CASE WHEN DATE(created_at)='{$today}' THEN 1 END) as today_orders,
                    COUNT(CASE WHEN status='paid' AND DATE(created_at)='{$today}' THEN 1 END) as today_paid,
                    COALESCE(SUM(CASE WHEN status='paid' AND created_at>='{$monthStart}' THEN total ELSE 0 END), 0) as month_revenue,
                    COUNT(CASE WHEN created_at>='{$monthStart}' THEN 1 END) as month_orders,
                    COALESCE(SUM(CASE WHEN status='paid' THEN total ELSE 0 END), 0) as total_revenue,
                    COUNT(*) as total_orders,
                    COUNT(CASE WHEN status='paid' THEN 1 END) as paid_orders,
                    COUNT(CASE WHEN status='pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status='cancelled' THEN 1 END) as cancelled_orders,
                    COALESCE(AVG(CASE WHEN status='paid' THEN total END), 0) as avg_ticket,
                    COALESCE(SUM(CASE WHEN status='paid' AND HOUR(created_at)<12 THEN total ELSE 0 END), 0) as morning_revenue,
                    COALESCE(SUM(CASE WHEN status='paid' AND HOUR(created_at)>=12 THEN total ELSE 0 END), 0) as afternoon_revenue
                FROM orders
            ")->fetch();

            $usersStats = $db->query("
                SELECT
                    COUNT(CASE WHEN role='user' THEN 1 END) as total_users,
                    COUNT(CASE WHEN role='user' AND blocked=1 THEN 1 END) as blocked_users,
                    COUNT(CASE WHEN role='user' AND created_at>='{$monthStart}' THEN 1 END) as new_users_month
                FROM users
            ")->fetch();

            $summary = array_merge($summary, $usersStats);
            $summary['total_products'] = $db->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();

            $avgWaitRow = $db->query("
                SELECT COALESCE(AVG(TIMESTAMPDIFF(SECOND, created_at, paid_at)), 0) as avg_wait
                FROM orders
                WHERE status='paid' AND paid_at IS NOT NULL
                  AND TIMESTAMPDIFF(SECOND, created_at, paid_at) BETWEEN 1 AND 7200
            ")->fetch();
            $summary['avg_wait_seconds'] = round((float)$avgWaitRow['avg_wait'], 0);

            $topProducts = $db->query("
                SELECT oi.product_name as name, SUM(oi.quantity) as qty, SUM(oi.subtotal) as revenue
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE o.status = 'paid'
                GROUP BY oi.product_name
                ORDER BY qty DESC
                LIMIT 10
            ")->fetchAll();

            $topProductsMap = [];
            foreach ($topProducts as $p) $topProductsMap[$p['name']] = ['qty' => (int)$p['qty'], 'revenue' => (float)$p['revenue']];

            $bottomProductsMap = [];
            foreach (array_reverse($topProducts) as $p) {
                if (count($bottomProductsMap) >= 5) break;
                $bottomProductsMap[$p['name']] = ['qty' => (int)$p['qty'], 'revenue' => (float)$p['revenue']];
            }

            $categorySalesRaw = $db->query("
                SELECT p.category, SUM(oi.subtotal) as revenue
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                JOIN products p ON p.id = oi.product_id
                WHERE o.status = 'paid'
                GROUP BY p.category
                ORDER BY revenue DESC
            ")->fetchAll();
            $categorySales = [];
            foreach ($categorySalesRaw as $r) $categorySales[$r['category']] = (float)$r['revenue'];

            $topStudentsRaw = $db->query("
                SELECT u.full_name as name, u.grade, SUM(o.total) as total, COUNT(o.id) as orders
                FROM orders o JOIN users u ON u.id = o.user_id
                WHERE o.status = 'paid'
                GROUP BY o.user_id, u.full_name, u.grade
                ORDER BY total DESC
                LIMIT 10
            ")->fetchAll();

            $gradeConsumption = $db->query("
                SELECT u.grade, SUM(o.total) as total, COUNT(o.id) as orders,
                       AVG(o.total) as avg_ticket
                FROM orders o JOIN users u ON u.id = o.user_id
                WHERE o.status = 'paid'
                GROUP BY u.grade
                ORDER BY total DESC
                LIMIT 15
            ")->fetchAll();

            $totalUsers = (int)$summary['total_users'];
            $gradeDistRaw = $db->query("
                SELECT grade, COUNT(*) as count
                FROM users WHERE role='user'
                GROUP BY grade ORDER BY count DESC LIMIT 15
            ")->fetchAll();
            $gradeDistribution = array_map(fn($r) => [
                'grade' => $r['grade'],
                'count' => (int)$r['count'],
                'pct'   => $totalUsers > 0 ? round($r['count'] / $totalUsers * 100, 1) : 0
            ], $gradeDistRaw);

            $hourlyRaw = $db->query("
                SELECT HOUR(created_at) as h, COUNT(*) as cnt
                FROM orders
                GROUP BY HOUR(created_at)
            ")->fetchAll();
            $hourlyUsage = array_fill(0, 24, 0);
            foreach ($hourlyRaw as $r) $hourlyUsage[(int)$r['h']] = (int)$r['cnt'];

            $sixMonths = [];
            for ($i = 5; $i >= 0; $i--) {
                $d = clone $now;
                $d->modify("-{$i} months");
                $m = $d->format('Y-m');
                $label = $d->format('M');
                $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as rev FROM orders WHERE status='paid' AND DATE_FORMAT(created_at,'%Y-%m')=?");
                $stmt->execute([$m]);
                $sixMonths[] = ['month' => $m, 'label' => $label, 'revenue' => (float)$stmt->fetchColumn()];
            }

            $bestDay = $db->query("
                SELECT DATE(created_at) as date, SUM(total) as revenue
                FROM orders WHERE status='paid' AND created_at>='{$monthStart}'
                GROUP BY DATE(created_at) ORDER BY revenue DESC LIMIT 1
            ")->fetch();

            $worstDay = $db->query("
                SELECT DATE(created_at) as date, SUM(total) as revenue
                FROM orders WHERE status='paid' AND created_at>='{$monthStart}'
                GROUP BY DATE(created_at) ORDER BY revenue ASC LIMIT 1
            ")->fetch();

            $mostExpensive = $db->query("
                SELECT o.total, u.full_name
                FROM orders o JOIN users u ON u.id=o.user_id
                WHERE o.status='paid' AND o.created_at>='{$monthStart}'
                ORDER BY o.total DESC LIMIT 1
            ")->fetch();

            $topRotation = $db->query("
                SELECT oi.product_name as name, SUM(oi.quantity) as qty, p.stock,
                       SUM(oi.quantity)/GREATEST(p.stock,1) as ratio
                FROM order_items oi
                JOIN orders o ON o.id=oi.order_id
                JOIN products p ON p.id=oi.product_id
                WHERE o.status='paid' AND p.stock>0
                GROUP BY oi.product_name, p.stock
                ORDER BY ratio DESC LIMIT 5
            ")->fetchAll();
            $topRotationMap = [];
            foreach ($topRotation as $r) $topRotationMap[$r['name']] = ['ratio' => (float)$r['ratio'], 'qty' => (int)$r['qty'], 'stock' => (int)$r['stock']];

            jsonResponse([
                'summary' => array_map(fn($v) => is_numeric($v) ? (float)$v : $v, $summary),
                'best_day' => $bestDay ?: null,
                'worst_day' => $worstDay ?: null,
                'daily_sales_month' => [],
                'top_products' => $topProductsMap,
                'bottom_products' => $bottomProductsMap,
                'top_rotation' => $topRotationMap,
                'category_sales' => $categorySales,
                'top_students' => $topStudentsRaw,
                'grade_consumption' => $gradeConsumption,
                'grade_distribution' => $gradeDistribution,
                'hourly_usage' => $hourlyUsage,
                'daily_turns' => [],
                'most_expensive_order' => $mostExpensive ?: null,
                'six_months' => $sixMonths,
            ]);

        } catch (PDOException $e) {
            jsonError('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    jsonError('Ruta stats no encontrada', 404);
}