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
     * 根据用户名查找用户
     */
    public function findUserByUsername($username)
    {
        try {
            $response = $this->makeRequest('GET', '/api/v1/users/search?username=' . urlencode($username));
            return array('success' => true, 'data' => $response);
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
     * 暂停用户账户
     */
    public function suspendAccount($userId)
    {
        try {
            $data = array('userId' => $userId, 'isOn' => false);
            $response = $this->makeRequest('POST', '/UserService/updateUser', $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 恢复用户账户
     */
    public function unsuspendAccount($userId)
    {
        try {
            $data = array('userId' => $userId, 'isOn' => true);
            $response = $this->makeRequest('POST', '/UserService/updateUser', $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 终止用户账户
     */
    public function terminateAccount($userId)
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
     * 更新用户套餐
     */
    public function updatePackage($userId, $packageData)
    {
        try {
            $data = array(
                'plan' => array(
                    'bandwidth_limit' => intval($packageData['bandwidth_limit']),
                    'traffic_limit' => intval($packageData['traffic_limit']),
                    'max_nodes' => intval($packageData['max_nodes']),
                    'node_group' => $packageData['node_group']
                )
            );
            
            $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}/plan", $data);
            return array('success' => true, 'data' => $response);
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
     * 获取用户使用统计
     */
    public function getUserStats($userId, $startDate = null, $endDate = null)
    {
        try {
            $params = array();
            if ($startDate) $params['start_date'] = $startDate;
            if ($endDate) $params['end_date'] = $endDate;
            
            $queryString = !empty($params) ? '?' . http_build_query($params) : '';
            $response = $this->makeRequest('GET', "/api/v1/users/{$userId}/stats{$queryString}");
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * 重置用户密码
     */
    public function resetPassword($userId, $newPassword)
    {
        try {
            $data = array('userId' => $userId, 'password' => $newPassword);
            $response = $this->makeRequest('POST', '/UserService/updateUser', $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * 获取默认套餐模板
     */
    public function getDefaultPackageTemplate($packageType = 'cdn')
    {
        try {
            // 尝试从GoEdge获取套餐计划模板
            $response = $this->makeRequest('POST', '/PlanService/findAllEnabledPlans', array());

            if (isset($response['plans'])) {
                foreach ($response['plans'] as $plan) {
                    if (isset($plan['type']) && $plan['type'] == $packageType) {
                        return array('success' => true, 'data' => array(
                            'bandwidth_limit' => $plan['bandwidthLimit']['count'] ?? 100,
                            'traffic_limit' => $plan['trafficLimit']['count'] ?? 1000,
                            'max_nodes' => 10,
                            'node_group' => 'default',
                            'features' => $this->getFeaturesByType($packageType)
                        ));
                    }
                }
            }
        } catch (Exception $e) {
            // API调用失败，使用内置默认配置
        }

        // 返回内置默认配置
        $defaultTemplates = array(
            'cdn' => array(
                'bandwidth_limit' => 100,
                'traffic_limit' => 1000,
                'max_nodes' => 10,
                'node_group' => 'default',
                'features' => array('basic_cdn', 'cache_optimization')
            ),
            'security' => array(
                'bandwidth_limit' => 200,
                'traffic_limit' => 2000,
                'max_nodes' => 15,
                'node_group' => 'security',
                'features' => array('ddos_protection', 'waf', 'ssl_certificate')
            ),
            'acceleration' => array(
                'bandwidth_limit' => 500,
                'traffic_limit' => 5000,
                'max_nodes' => 20,
                'node_group' => 'premium',
                'features' => array('global_cdn', 'image_optimization', 'compression')
            )
        );

        $template = $defaultTemplates[$packageType] ?? $defaultTemplates['cdn'];
        return array('success' => true, 'data' => $template);
    }

    /**
     * 根据套餐类型获取特性
     */
    private function getFeaturesByType($packageType)
    {
        $features = array(
            'cdn' => array('basic_cdn', 'cache_optimization'),
            'security' => array('ddos_protection', 'waf', 'ssl_certificate'),
            'acceleration' => array('global_cdn', 'image_optimization', 'compression')
        );

        return $features[$packageType] ?? $features['cdn'];
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
     * 更新套餐状态
     */
    public function updatePackageStatus($userId, $packageId, $status)
    {
        try {
            $data = array('status' => $status);
            $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}/packages/{$packageId}/status", $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 暂停套餐
     */
    public function suspendPackage($userId, $packageId)
    {
        return $this->updatePackageStatus($userId, $packageId, 'suspended');
    }

    /**
     * 恢复套餐
     */
    public function unsuspendPackage($userId, $packageId)
    {
        return $this->updatePackageStatus($userId, $packageId, 'active');
    }

    /**
     * 终止套餐
     */
    public function terminatePackage($userId, $packageId)
    {
        try {
            $response = $this->makeRequest('DELETE', "/api/v1/users/{$userId}/packages/{$packageId}");
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 续费套餐
     */
    public function renewPackage($userId, $packageId, $renewData)
    {
        try {
            $data = array(
                'expire_date' => $renewData['expire_date'],
                'payment_method' => $renewData['payment_method'] ?: 'whmcs',
                'amount' => $renewData['amount'] ?: 0,
                'currency' => $renewData['currency'] ?: 'USD'
            );

            $response = $this->makeRequest('POST', "/api/v1/users/{$userId}/packages/{$packageId}/renew", $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 升级/降级套餐
     */
    public function upgradePackage($userId, $packageId, $newPackageData)
    {
        try {
            $data = array(
                'new_package_name' => $newPackageData['package_name'],
                'bandwidth_limit' => intval($newPackageData['bandwidth_limit']),
                'traffic_limit' => intval($newPackageData['traffic_limit']),
                'max_nodes' => intval($newPackageData['max_nodes']),
                'node_group' => $newPackageData['node_group'],
                'features' => $newPackageData['features'] ?: array(),
                'effective_date' => $newPackageData['effective_date'] ?: date('Y-m-d H:i:s')
            );

            $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}/packages/{$packageId}/upgrade", $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取套餐使用统计
     */
    public function getPackageStats($userId, $packageId, $startDate = null, $endDate = null)
    {
        try {
            $params = array();
            if ($startDate) $params['start_date'] = $startDate;
            if ($endDate) $params['end_date'] = $endDate;

            $queryString = !empty($params) ? '?' . http_build_query($params) : '';
            $response = $this->makeRequest('GET', "/api/v1/users/{$userId}/packages/{$packageId}/stats{$queryString}");
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 延长账户到期时间
     */
    public function extendAccount($userId, $newExpiryDate)
    {
        try {
            $data = array('expire_date' => $newExpiryDate);
            $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}/extend", $data);
            return array('success' => true, 'data' => $response);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 修改用户密码
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        try {
            $data = array(
                'current_password' => $currentPassword,
                'new_password' => $newPassword
            );
            $response = $this->makeRequest('PUT', "/api/v1/users/{$userId}/change-password", $data);
            return array('success' => true, 'data' => $response);
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
