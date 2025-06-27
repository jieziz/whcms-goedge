<?php
/**
 * GoEdge 事务管理器
 * 
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

class GoEdgeTransaction
{
    private $api;
    private $db;
    private $logger;
    private $rollbackActions = array();
    private $isActive = false;
    
    public function __construct($api, $db, $logger)
    {
        $this->api = $api;
        $this->db = $db; // 可以为null（简化版不需要数据库）
        $this->logger = $logger;
    }
    
    /**
     * 开始事务
     */
    public function begin()
    {
        $this->isActive = true;
        $this->rollbackActions = array();
        $this->logger->log('事务开始', null, array('transaction_id' => $this->getTransactionId()));
    }
    
    /**
     * 提交事务
     */
    public function commit()
    {
        if (!$this->isActive) {
            throw new Exception('没有活跃的事务可以提交');
        }
        
        $this->isActive = false;
        $this->rollbackActions = array();
        $this->logger->log('事务提交成功', null, array('transaction_id' => $this->getTransactionId()));
    }
    
    /**
     * 回滚事务
     */
    public function rollback()
    {
        if (!$this->isActive) {
            $this->logger->warning('尝试回滚非活跃事务');
            return;
        }
        
        $this->logger->log('开始事务回滚', null, array(
            'transaction_id' => $this->getTransactionId(),
            'rollback_actions_count' => count($this->rollbackActions)
        ));
        
        // 按相反顺序执行回滚操作
        $rollbackActions = array_reverse($this->rollbackActions);
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($rollbackActions as $action) {
            try {
                $this->executeRollbackAction($action);
                $successCount++;
                $this->logger->log('回滚操作成功', null, $action);
            } catch (Exception $e) {
                $errorCount++;
                $this->logger->error('回滚操作失败', null, array(
                    'action' => $action,
                    'error' => $e->getMessage()
                ));
            }
        }
        
        $this->isActive = false;
        $this->rollbackActions = array();
        
        $this->logger->log('事务回滚完成', null, array(
            'transaction_id' => $this->getTransactionId(),
            'success_count' => $successCount,
            'error_count' => $errorCount
        ));
    }
    
    /**
     * 添加回滚操作
     */
    public function addRollbackAction($type, $data)
    {
        if (!$this->isActive) {
            return;
        }
        
        $action = array(
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        );
        
        $this->rollbackActions[] = $action;
    }
    
    /**
     * 创建用户（带回滚）
     */
    public function createUser($userData)
    {
        $result = $this->api->createAccount($userData);
        
        if ($result['success']) {
            // 添加回滚操作：删除用户
            $this->addRollbackAction('delete_user', array(
                'user_id' => $result['data']['user_id']
            ));
        }
        
        return $result;
    }
    
    /**
     * 创建用户套餐（带回滚）
     */
    public function createUserPlan($userId, $packageData)
    {
        $result = $this->api->createPackageForUser($userId, $packageData);
        
        if ($result['success']) {
            // 添加回滚操作：删除用户套餐
            $this->addRollbackAction('delete_user_plan', array(
                'user_plan_id' => $result['data']['package_id']
            ));
        }
        
        return $result;
    }
    
    /**
     * 保存账户到数据库（带回滚）
     */
    public function saveAccount($accountData)
    {
        $result = $this->db->saveAccount($accountData);
        
        if ($result) {
            // 添加回滚操作：删除数据库记录
            $this->addRollbackAction('delete_account_record', array(
                'service_id' => $accountData['service_id']
            ));
        }
        
        return $result;
    }
    
    /**
     * 保存套餐到数据库（带回滚）
     */
    public function savePackage($packageData)
    {
        $result = $this->db->savePackage($packageData);
        
        if ($result) {
            // 添加回滚操作：删除数据库记录
            $this->addRollbackAction('delete_package_record', array(
                'service_id' => $packageData['service_id']
            ));
        }
        
        return $result;
    }
    
    /**
     * 执行回滚操作
     */
    private function executeRollbackAction($action)
    {
        switch ($action['type']) {
            case 'delete_user':
                return $this->api->deleteUser($action['data']['user_id']);

            case 'delete_user_plan':
                return $this->api->deleteUserPlan($action['data']['user_plan_id']);

            case 'delete_account_record':
                return $this->db->deleteAccountByServiceId($action['data']['service_id']);

            case 'delete_package_record':
                return $this->db->deletePackageByServiceId($action['data']['service_id']);

            default:
                throw new Exception('未知的回滚操作类型: ' . $action['type']);
        }
    }
    
    /**
     * 获取事务ID
     */
    private function getTransactionId()
    {
        return substr(md5(microtime(true) . mt_rand()), 0, 8);
    }
    
    /**
     * 检查事务是否活跃
     */
    public function isActive()
    {
        return $this->isActive;
    }
    
    /**
     * 获取回滚操作数量
     */
    public function getRollbackActionsCount()
    {
        return count($this->rollbackActions);
    }
}
