<?php
/**
 * GoEdge 日志记录类
 * 
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

class GoEdgeLogger
{
    private $debugMode;
    private $db;
    private $logFile;
    
    public function __construct($params = null)
    {
        $this->debugMode = ($params && isset($params['configoption9'])) ? $params['configoption9'] == 'on' : false;
        $this->db = new GoEdgeDatabase();
        $this->logFile = __DIR__ . '/../logs/goedge.log';
        
        // 确保日志目录存在
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * 记录日志
     */
    public function log($message, $serviceId = null, $data = null, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = array(
            'timestamp' => $timestamp,
            'level' => $level,
            'service_id' => $serviceId,
            'message' => $message,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        );
        
        // 写入数据库
        try {
            $this->db->addLog($serviceId, $level, $message, $data);
        } catch (Exception $e) {
            // 如果数据库写入失败，至少写入文件
            error_log("GoEdge Logger DB Error: " . $e->getMessage());
        }
        
        // 写入文件日志
        $this->writeToFile($logEntry);
        
        // 调试模式下输出到错误日志
        if ($this->debugMode) {
            error_log("GoEdge Plugin [{$level}]: {$message}" . ($data ? ' Data: ' . json_encode($data) : ''));
        }
    }
    
    /**
     * 记录信息日志
     */
    public function info($message, $serviceId = null, $data = null)
    {
        $this->log($message, $serviceId, $data, 'INFO');
    }
    
    /**
     * 记录警告日志
     */
    public function warning($message, $serviceId = null, $data = null)
    {
        $this->log($message, $serviceId, $data, 'WARNING');
    }
    
    /**
     * 记录错误日志
     */
    public function error($message, $serviceId = null, $data = null)
    {
        $this->log($message, $serviceId, $data, 'ERROR');
    }
    
    /**
     * 记录调试日志
     */
    public function debug($message, $serviceId = null, $data = null)
    {
        if ($this->debugMode) {
            $this->log($message, $serviceId, $data, 'DEBUG');
        }
    }
    
    /**
     * 写入文件日志
     */
    private function writeToFile($logEntry)
    {
        $logLine = sprintf(
            "[%s] [%s] [Service:%s] %s%s%s",
            $logEntry['timestamp'],
            $logEntry['level'],
            $logEntry['service_id'] ?: 'N/A',
            $logEntry['message'],
            $logEntry['data'] ? ' | Data: ' . json_encode($logEntry['data']) : '',
            PHP_EOL
        );
        
        try {
            file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log("GoEdge Logger File Error: " . $e->getMessage());
        }
    }
    
    /**
     * 获取日志文件内容
     */
    public function getLogFileContent($lines = 100)
    {
        if (!file_exists($this->logFile)) {
            return array();
        }
        
        try {
            $content = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return array_slice($content, -$lines);
        } catch (Exception $e) {
            return array("Error reading log file: " . $e->getMessage());
        }
    }
    
    /**
     * 清理旧日志
     */
    public function cleanOldLogs($days = 30)
    {
        try {
            // 清理数据库中的旧日志
            $sql = "DELETE FROM `mod_goedge_logs` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->pdo->prepare($sql);
            $deletedRows = $stmt->execute(array($days));
            
            $this->info("清理了 {$deletedRows} 条旧日志记录");
            
            // 清理日志文件（保留最近的记录）
            $this->rotateLogFile();
            
        } catch (Exception $e) {
            $this->error("清理旧日志失败", null, array('error' => $e->getMessage()));
        }
    }
    
    /**
     * 轮转日志文件
     */
    private function rotateLogFile()
    {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        $fileSize = filesize($this->logFile);
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if ($fileSize > $maxSize) {
            $backupFile = $this->logFile . '.' . date('Y-m-d-H-i-s') . '.bak';
            rename($this->logFile, $backupFile);
            
            // 只保留最近5个备份文件
            $this->cleanBackupFiles();
        }
    }
    
    /**
     * 清理备份文件
     */
    private function cleanBackupFiles()
    {
        $logDir = dirname($this->logFile);
        $pattern = basename($this->logFile) . '.*.bak';
        $backupFiles = glob($logDir . '/' . $pattern);
        
        if (count($backupFiles) > 5) {
            // 按修改时间排序
            usort($backupFiles, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // 删除最旧的文件
            $filesToDelete = array_slice($backupFiles, 0, count($backupFiles) - 5);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * 获取日志统计信息
     */
    public function getLogStats($serviceId = null, $days = 7)
    {
        try {
            $stats = array();
            $whereClause = $serviceId ? "WHERE service_id = ? AND" : "WHERE";
            
            // 按级别统计
            $sql = "SELECT action as level, COUNT(*) as count 
                    FROM mod_goedge_logs 
                    {$whereClause} created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                    GROUP BY action";
            
            $stmt = $this->db->pdo->prepare($sql);
            $params = $serviceId ? array($serviceId, $days) : array($days);
            $stmt->execute($params);
            
            $levelStats = array();
            while ($row = $stmt->fetch()) {
                $levelStats[$row['level']] = $row['count'];
            }
            $stats['by_level'] = $levelStats;
            
            // 按日期统计
            $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                    FROM mod_goedge_logs 
                    {$whereClause} created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                    GROUP BY DATE(created_at) 
                    ORDER BY date DESC";
            
            $stmt = $this->db->pdo->prepare($sql);
            $stmt->execute($params);
            
            $dateStats = array();
            while ($row = $stmt->fetch()) {
                $dateStats[$row['date']] = $row['count'];
            }
            $stats['by_date'] = $dateStats;
            
            return $stats;
            
        } catch (Exception $e) {
            $this->error("获取日志统计失败", $serviceId, array('error' => $e->getMessage()));
            return array();
        }
    }
    
    /**
     * 导出日志
     */
    public function exportLogs($serviceId = null, $startDate = null, $endDate = null, $format = 'csv')
    {
        try {
            $whereConditions = array();
            $params = array();
            
            if ($serviceId) {
                $whereConditions[] = "service_id = ?";
                $params[] = $serviceId;
            }
            
            if ($startDate) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $endDate;
            }
            
            $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
            
            $sql = "SELECT * FROM mod_goedge_logs {$whereClause} ORDER BY created_at DESC";
            $stmt = $this->db->pdo->prepare($sql);
            $stmt->execute($params);
            
            $logs = $stmt->fetchAll();
            
            if ($format === 'csv') {
                return $this->exportToCsv($logs);
            } else {
                return $logs;
            }
            
        } catch (Exception $e) {
            $this->error("导出日志失败", $serviceId, array('error' => $e->getMessage()));
            return false;
        }
    }
    
    /**
     * 导出为CSV格式
     */
    private function exportToCsv($logs)
    {
        $output = fopen('php://temp', 'r+');
        
        // 写入CSV头部
        fputcsv($output, array('ID', 'Service ID', 'Action', 'Message', 'Data', 'IP Address', 'User Agent', 'Created At'));
        
        // 写入数据
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['id'],
                $log['service_id'],
                $log['action'],
                $log['message'],
                $log['data'],
                $log['ip_address'],
                $log['user_agent'],
                $log['created_at']
            ));
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
