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
        global $CONFIG;
        
        try {
            $dsn = "mysql:host={$CONFIG['db_host']};dbname={$CONFIG['db_name']};charset=utf8mb4";
            $this->pdo = new PDO($dsn, $CONFIG['db_username'], $CONFIG['db_password'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ));
        } catch (PDOException $e) {
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
        
        $this->createTables();
    }
    
    /**
     * 创建必要的数据表
     */
    private function createTables()
    {
        $tables = array(
            'mod_goedge_plan_bindings' => "
                CREATE TABLE IF NOT EXISTS `mod_goedge_plan_bindings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `product_id` int(11) NOT NULL,
                    `product_name` varchar(255) NOT NULL,
                    `plan_id` varchar(50) NOT NULL,
                    `plan_name` varchar(255) NOT NULL,
                    `status` enum('active','disabled') DEFAULT 'active',
                    `created_at` datetime NOT NULL,
                    `updated_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `product_id` (`product_id`),
                    KEY `plan_id` (`plan_id`),
                    KEY `status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            "
        );

        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                error_log("创建表 {$tableName} 失败: " . $e->getMessage());
            }
        }
    }
    

    

    
    // 移除了日志和设置相关方法，改为纯文件日志和代码配置
    


    /**
     * 保存套餐计划绑定关系
     */
    public function savePlanBinding($productId, $planId, $productName = '', $planName = '')
    {
        // 如果没有提供产品名称，尝试从WHMCS获取
        if (empty($productName)) {
            try {
                $result = localAPI('GetProducts', array('pid' => $productId));
                if ($result['result'] == 'success' && isset($result['products']['product'][0])) {
                    $productName = $result['products']['product'][0]['name'];
                }
            } catch (Exception $e) {
                $productName = 'Product #' . $productId;
            }
        }

        // 检查是否已存在绑定
        $sql = "SELECT id FROM `mod_goedge_plan_bindings` WHERE product_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array($productId));
        $existing = $stmt->fetch();

        if ($existing) {
            // 更新现有绑定
            $sql = "UPDATE `mod_goedge_plan_bindings`
                    SET plan_id = ?, plan_name = ?, product_name = ?, updated_at = NOW()
                    WHERE product_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array($planId, $planName, $productName, $productId));
        } else {
            // 创建新绑定
            $sql = "INSERT INTO `mod_goedge_plan_bindings`
                    (product_id, product_name, plan_id, plan_name, status, created_at)
                    VALUES (?, ?, ?, ?, 'active', NOW())";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array($productId, $productName, $planId, $planName));
        }
    }

    /**
     * 获取所有套餐计划绑定关系
     */
    public function getAllPlanBindings()
    {
        $sql = "SELECT * FROM `mod_goedge_plan_bindings` ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * 根据产品ID获取绑定的套餐计划ID
     */
    public function getPlanIdByProductId($productId)
    {
        $sql = "SELECT plan_id FROM `mod_goedge_plan_bindings` WHERE product_id = ? AND status = 'active'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array($productId));
        return $stmt->fetchColumn();
    }

    /**
     * 删除套餐计划绑定
     */
    public function deletePlanBinding($bindingId)
    {
        $sql = "DELETE FROM `mod_goedge_plan_bindings` WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array($bindingId));
    }
}
