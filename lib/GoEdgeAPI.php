<?php
/**
 * GoEdge API 客户端类
 * 
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

class GoEdgeAPI
{
    private $apiEndpoint;
    private $apiKey;
    private $apiSecret;
    private $debugMode;
    
    public function __construct($params)
    {
        $this->apiEndpoint = rtrim($params['serveraccesshash'] ?: 'https://api.goedge.cn', '/');
        $this->apiKey = $params['serverusername'];
        $this->apiSecret = $params['serverpassword'];
        $this->debugMode = $params['configoption9'] == 'on';
    }
    
    /**
     * 测试API连接
     */
    public function testConnection()
    {
        try {
            // 使用正确的GoEdge API端点测试连接
            $response = $this->makeRequest('POST', '/APINodeService/findAllEnabledAPINodes', array());
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取可用的套餐计划列表（管理员身份）
     */
    public function getAvailablePlans()
    {
        try {
            // 调用PlanService获取所有启用的套餐计划
            $response = $this->makeRequest('POST', '/PlanService/findAllEnabledPlans', array());

            if (isset($response['plans']) && is_array($response['plans'])) {
                $plans = array();
                foreach ($response['plans'] as $plan) {
                    $plans[] = array(
                        'id' => $plan['id'],
                        'name' => $plan['name'],
                        'type' => $plan['type'] ?? 'cdn',
                        'description' => $plan['description'] ?? '',
                        'bandwidth_limit' => $plan['bandwidthLimit']['count'] ?? 0,
                        'traffic_limit' => $plan['trafficLimit']['count'] ?? 0,
                        'price_type' => $plan['priceType'] ?? 'month'
                    );
                }
                return array('success' => true, 'data' => $plans);
            } else {
                return array('success' => false, 'error' => '未找到可用的套餐计划');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 根据邮箱查找用户
     */
    public function findUserByEmail($email)
    {
        try {
            // 使用正确的GoEdge UserService API
            $data = array('keyword' => $email);
            $response = $this->makeRequest('POST', '/UserService/findEnabledUserWithKeyword', $data);

            if (isset($response['user']) && $response['user']) {
                return array('success' => true, 'data' => array(
                    'user_id' => $response['user']['id'],
                    'username' => $response['user']['username'],
                    'email' => $response['user']['email'],
                    'status' => $response['user']['isOn'] ? 'active' : 'suspended'
                ));
            } else {
                return array('success' => false, 'error' => '用户不存在');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 创建用户账户
     */
    public function createAccount($accountData)
    {
        try {
            // 使用正确的GoEdge UserService API
            $data = array(
                'username' => $accountData['username'],
                'password' => $accountData['password'],
                'email' => $accountData['email'],
                'isOn' => true
            );

            $response = $this->makeRequest('POST', '/UserService/createUser', $data);

            if (isset($response['user'])) {
                return array('success' => true, 'data' => array(
                    'user_id' => $response['user']['id'],
                    'username' => $response['user']['username'],
                    'email' => $response['user']['email'],
                    'status' => $response['user']['isOn'] ? 'active' : 'suspended'
                ));
            } else {
                return array('success' => false, 'error' => '用户创建失败：API响应格式错误');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    


    /**
     * 获取用户信息
     */
    public function getUserInfo($userId)
    {
        try {
            $data = array('userId' => $userId);
            $response = $this->makeRequest('POST', '/UserService/findEnabledUser', $data);

            if (isset($response['user'])) {
                return array('success' => true, 'data' => array(
                    'user_id' => $response['user']['id'],
                    'username' => $response['user']['username'],
                    'email' => $response['user']['email'],
                    'status' => $response['user']['isOn'] ? 'active' : 'suspended'
                ));
            } else {
                return array('success' => false, 'error' => '用户不存在');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 删除用户账户（仅用于事务回滚）
     */
    public function deleteUser($userId)
    {
        try {
            $data = array('userId' => $userId);
            $response = $this->makeRequest('POST', '/UserService/deleteUser', $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 为用户分配套餐计划（管理员身份调用UserPlanService）
     */
    public function createPackageForUser($userId, $userPlanData)
    {
        try {
            // 验证必要参数
            if (empty($userPlanData['plan_id'])) {
                throw new Exception('套餐计划ID不能为空');
            }

            // 根据GoEdge API文档，使用UserPlanService创建用户套餐
            $data = array(
                'userId' => $userId,
                'planId' => $userPlanData['plan_id'], // 使用预配置的planid
                'name' => $userPlanData['user_plan_name'],
                'dayFrom' => date('Ymd'),
                'dayTo' => date('Ymd', strtotime($userPlanData['expire_date'])),
                'isOn' => true
            );

            // 调用UserPlanService.createUserPlan API
            $response = $this->makeRequest('POST', '/UserPlanService/createUserPlan', $data);

            if (isset($response['userPlan'])) {
                return array(
                    'success' => true,
                    'data' => array(
                        'package_id' => $response['userPlan']['id'],
                        'user_id' => $userId,
                        'plan_id' => $response['userPlan']['planId'],
                        'name' => $response['userPlan']['name'],
                        'bandwidth_limit' => $defaultConfig['bandwidth_limit'] ?? 100,
                        'traffic_limit' => $defaultConfig['traffic_limit'] ?? 1000,
                        'max_nodes' => $defaultConfig['max_nodes'] ?? 10,
                        'node_group' => $defaultConfig['node_group'] ?? 'default',
                        'day_from' => $response['userPlan']['dayFrom'],
                        'day_to' => $response['userPlan']['dayTo'],
                        'is_on' => $response['userPlan']['isOn']
                    )
                );
            } else {
                return array('success' => false, 'error' => '套餐创建失败：API响应格式错误');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取或创建套餐计划ID
     */
    private function getOrCreatePlanId($packageType, $defaultConfig)
    {
        try {
            // 首先尝试查找现有的套餐计划
            $response = $this->makeRequest('POST', '/PlanService/findAllEnabledPlans', array());

            if (isset($response['plans'])) {
                foreach ($response['plans'] as $plan) {
                    if ($plan['type'] == $packageType) {
                        return $plan['id'];
                    }
                }
            }

            // 如果没有找到，创建新的套餐计划
            return $this->createPlan($packageType, $defaultConfig);

        } catch (Exception $e) {
            // 如果查找失败，尝试创建默认计划
            return $this->createPlan($packageType, $defaultConfig);
        }
    }

    /**
     * 创建套餐计划
     */
    private function createPlan($packageType, $defaultConfig)
    {
        $planData = array(
            'name' => ucfirst($packageType) . ' Plan',
            'type' => $packageType,
            'isOn' => true,
            'trafficLimit' => array(
                'count' => $defaultConfig['traffic_limit'] ?? 1000,
                'unit' => 'gb'
            ),
            'bandwidthLimit' => array(
                'count' => $defaultConfig['bandwidth_limit'] ?? 100,
                'unit' => 'mbps'
            )
        );

        $response = $this->makeRequest('POST', '/PlanService/createPlan', $planData);

        if (isset($response['plan']['id'])) {
            return $response['plan']['id'];
        } else {
            throw new Exception('创建套餐计划失败');
        }
    }

    /**
     * 获取用户的套餐列表
     */
    public function getUserPlans($userId)
    {
        try {
            $data = array('userId' => $userId);
            $response = $this->makeRequest('POST', '/UserPlanService/findAllEnabledUserPlans', $data);
            return array('success' => true, 'data' => $response['userPlans'] ?? array());
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 根据用户ID和套餐计划ID查找用户套餐
     */
    public function findUserPlanByPlanId($userId, $planId)
    {
        try {
            $userPlansResult = $this->getUserPlans($userId);
            if (!$userPlansResult['success']) {
                return $userPlansResult;
            }

            foreach ($userPlansResult['data'] as $userPlan) {
                if ($userPlan['planId'] == $planId) {
                    return array('success' => true, 'data' => $userPlan);
                }
            }

            return array('success' => false, 'error' => '未找到对应的用户套餐');
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 更新用户套餐
     */
    public function updateUserPlan($userPlanId, $updateData)
    {
        try {
            $data = array(
                'userPlanId' => $userPlanId,
                'name' => $updateData['name'] ?? '',
                'dayFrom' => $updateData['day_from'] ?? date('Ymd'),
                'dayTo' => $updateData['day_to'] ?? date('Ymd', strtotime('+1 month')),
                'isOn' => $updateData['is_on'] ?? true
            );

            $response = $this->makeRequest('POST', '/UserPlanService/updateUserPlan', $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 删除用户套餐
     */
    public function deleteUserPlan($userPlanId)
    {
        try {
            $data = array('userPlanId' => $userPlanId);
            $response = $this->makeRequest('POST', '/UserPlanService/deleteUserPlan', $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 暂停用户套餐
     */
    public function suspendUserPlan($userPlanId)
    {
        try {
            return $this->updateUserPlan($userPlanId, array('is_on' => false));
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 恢复用户套餐
     */
    public function unsuspendUserPlan($userPlanId)
    {
        try {
            return $this->updateUserPlan($userPlanId, array('is_on' => true));
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 续费用户套餐
     */
    public function renewUserPlan($userPlanId, $renewData)
    {
        try {
            $newDayTo = date('Ymd', strtotime($renewData['expire_date']));
            return $this->updateUserPlan($userPlanId, array('day_to' => $newDayTo));
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 发送HTTP请求
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        $url = $this->apiEndpoint . $endpoint;
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->generateAuthToken(),
            'User-Agent: WHMCS-GoEdge-Plugin/1.0'
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: {$error}");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = isset($decodedResponse['message']) ? $decodedResponse['message'] : "HTTP Error {$httpCode}";
            throw new Exception($errorMsg);
        }
        
        if ($this->debugMode) {
            error_log("GoEdge API Request: {$method} {$url}");
            error_log("GoEdge API Response: " . $response);
        }
        
        return $decodedResponse;
    }
    
    /**
     * 生成认证令牌
     */
    private function generateAuthToken()
    {
        $timestamp = time();
        $nonce = uniqid();
        $signature = hash_hmac('sha256', $this->apiKey . $timestamp . $nonce, $this->apiSecret);
        
        return base64_encode(json_encode(array(
            'key' => $this->apiKey,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature
        )));
    }
}
