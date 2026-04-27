<?php
// api/config/logger.php

function writeLog($message, $type = 'error', $data = null) {
    $logDir = __DIR__ . '/../logs/';
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'error.log';
    $date = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $uri = $_SERVER['REQUEST_URI'] ?? 'CLI';
    
    $logEntry = sprintf(
        "[%s] [%s] [IP: %s] [URI: %s] %s",
        $date,
        strtoupper($type),
        $ip,
        $uri,
        $message
    );
    
    if ($data !== null) {
        $logEntry .= "\nData: " . (is_array($data) || is_object($data) ? json_encode($data, JSON_PRETTY_PRINT) : $data);
    }
    
    $logEntry .= "\n" . str_repeat('-', 80) . "\n";
    
    error_log($logEntry, 3, $logFile);
    
    if (filesize($logFile) > 5242880) {
        rotateLog($logDir);
    }
}

function rotateLog($logDir) {
    $logFile = $logDir . 'error.log';
    if (file_exists($logFile)) {
        $backup = $logDir . 'error_' . date('Y-m-d_His') . '.log';
        rename($logFile, $backup);
        
        $files = glob($logDir . 'error_*.log');
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        while (count($files) > 5) {
            $oldest = array_shift($files);
            unlink($oldest);
        }
    }
}

function logError($message, $data = null) {
    writeLog($message, 'error', $data);
}

function logWarning($message, $data = null) {
    writeLog($message, 'warning', $data);
}

function logInfo($message, $data = null) {
    writeLog($message, 'info', $data);
}

function logDebug($message, $data = null) {
    if (getenv('DEBUG_MODE') === 'true') {
        writeLog($message, 'debug', $data);
    }
}

function logDatabaseError($action, $error, $query = null) {
    writeLog(
        "Database Error in {$action}: " . $error->getMessage(),
        'database',
        [
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'query' => $query
        ]
    );
}

function logControllerError($controller, $action, $error, $data = null) {
    writeLog(
        "Controller Error in {$controller}::{$action}: " . $error->getMessage(),
        'controller',
        [
            'trace' => $error->getTraceAsString(),
            'data' => $data
        ]
    );
}
?>