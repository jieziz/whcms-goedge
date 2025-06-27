<?php
/**
 * GoEdge 数据库操作类
 * 
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

class GoEdgeDatabase
{
    private $pdo;
    
    public function __construct()
    {
        global $CONFIG, $db_host, $db_name, $db_username, $db_password;

        // 优先使用单独的变量，因为 $CONFIG 数组可能未正确填充
        $host = $db_host ?? $CONFIG['db_host'] ?? 'localhost';
        $database = $db_name ?? $CONFIG['db_name'] ?? '';
        $username = $db_username ?? $CONFIG['db_username'] ?? '';
        $password = $db_password ?? $CONFIG['db_password'] ?? '';

        // 调试信息（仅在开发环境）
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("GoEdge Database Debug - Host: $host, DB: $database, User: $username");
        }

        if (empty($database)) {
            throw new Exception("数据库配置错误: 数据库名称为空。请检查 WHMCS 配置文件。");
        }

        try {
            $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
            $this->pdo = new PDO($dsn, $username, $password, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10
            ));
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage() . " (Host: $host, DB: $database)");
        }
    }
    

    

    
    // 移除了日志和设置相关方法，改为纯文件日志和代码配置
    


    // 简化版：移除了所有套餐绑定相关方法
    // 套餐ID直接从WHMCS产品配置中获取
}
