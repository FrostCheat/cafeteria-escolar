<?php
require_once __DIR__ . '/../config/logger.php';

$db = getDB();

if ($resource === 'queue') {
    if ($method === 'GET' && $action === null && $id === null) {
        $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
        $currentOrder = null;
        if ($cfg && $cfg['current_order_id']) {
            $stmt = $db->prepare("SELECT o.*, u.full_name, u.grade, u.doc_type, u.doc_number FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?");
            $stmt->execute([$cfg['current_order_id']]);
            $currentOrder = $stmt->fetch() ?: null;
            if ($currentOrder) {
                $items = $db->prepare("SELECT * FROM order_items WHERE order_id=?");
                $items->execute([$currentOrder['id']]);
                $currentOrder['items'] = $items->fetchAll();
            }
        }
        jsonResponse([
            'enabled'       => $cfg ? (bool)$cfg['enabled'] : false,
            'current_turn'  => $cfg ? (int)$cfg['current_turn'] : 0,
            'current_order' => $currentOrder,
            'last_updated'  => $cfg ? $cfg['updated_at'] : null,
        ]);
    }

    if ($method === 'PUT' && $action === 'toggle') {
        requireAdmin();
        $cfg = $db->query("SELECT enabled FROM queue_config WHERE id=1")->fetch();
        $newState = $cfg ? !$cfg['enabled'] : true;
        if ($newState) {
            $db->exec("UPDATE queue_config SET enabled=1, current_turn=0, current_order_id=NULL, updated_at=NOW() WHERE id=1");
            $db->exec("UPDATE orders SET turn_number=NULL WHERE DATE(created_at)=CURDATE() AND status='pending'");
            $nextTurn = 1;
            $pendingOrders = $db->query("SELECT id FROM orders WHERE status='pending' AND DATE(created_at)=CURDATE() ORDER BY created_at ASC")->fetchAll();
            foreach ($pendingOrders as $o) {
                $db->prepare("UPDATE orders SET turn_number=? WHERE id=?")->execute([$nextTurn, $o['id']]);
                $nextTurn++;
            }
        } else {
            $db->exec("UPDATE queue_config SET enabled=0, updated_at=NOW() WHERE id=1");
        }
        $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
        jsonResponse(['enabled' => (bool)$cfg['enabled'], 'current_turn' => (int)$cfg['current_turn']]);
    }

    if ($method === 'PUT' && $action === 'next') {
        requireAdmin();
        $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
        if (!$cfg || !$cfg['enabled']) jsonError('Sistema de turnos desactivado');
        $nextTurn = (int)$cfg['current_turn'] + 1;
        $stmt = $db->prepare("SELECT id FROM orders WHERE turn_number=? AND DATE(created_at)=CURDATE()");
        $stmt->execute([$nextTurn]);
        $order = $stmt->fetch();
        $db->prepare("UPDATE queue_config SET current_turn=?, current_order_id=?, updated_at=NOW() WHERE id=1")
           ->execute([$nextTurn, $order ? $order['id'] : null]);
        $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
        jsonResponse(['current_turn' => (int)$cfg['current_turn'], 'order_id' => $order ? $order['id'] : null]);
    }

    if ($method === 'PUT' && $action === 'prev') {
        requireAdmin();
        $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
        if (!$cfg || !$cfg['enabled']) jsonError('Sistema de turnos desactivado');
        $prevTurn = max(0, (int)$cfg['current_turn'] - 1);
        $stmt = $db->prepare("SELECT id FROM orders WHERE turn_number=? AND DATE(created_at)=CURDATE()");
        $stmt->execute([$prevTurn]);
        $order = $stmt->fetch();
        $db->prepare("UPDATE queue_config SET current_turn=?, current_order_id=?, updated_at=NOW() WHERE id=1")
           ->execute([$prevTurn, $order ? $order['id'] : null]);
        $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
        jsonResponse(['current_turn' => (int)$cfg['current_turn'], 'order_id' => $order ? $order['id'] : null]);
    }

    if ($method === 'PUT' && $action === 'set' && $id !== null) {
        requireAdmin();
        $cfg = $db->query("SELECT * FROM queue_config WHERE id=1")->fetch();
        if (!$cfg || !$cfg['enabled']) jsonError('Sistema de turnos desactivado');
        $stmt = $db->prepare("SELECT id FROM orders WHERE turn_number=? AND DATE(created_at)=CURDATE()");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        $db->prepare("UPDATE queue_config SET current_turn=?, current_order_id=?, updated_at=NOW() WHERE id=1")
           ->execute([$id, $order ? $order['id'] : null]);
        jsonResponse(['current_turn' => $id, 'order_id' => $order ? $order['id'] : null]);
    }

    if ($method === 'POST' && $action === 'reset') {
        requireAdmin();
        $db->exec("UPDATE queue_config SET current_turn=0, current_order_id=NULL, updated_at=NOW() WHERE id=1");
        jsonResponse(['message' => 'Turnos reiniciados']);
    }

    jsonError('Ruta queue no encontrada', 404);
}