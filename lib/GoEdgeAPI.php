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
    private $accessKeyId;
    private $accessKey;
    private $debugMode;
    private $accessToken;
    private $tokenExpiresAt;

    public function __construct($params)
    {
        $this->apiEndpoint = rtrim($params['serveraccesshash'] ?: 'https://api.goedge.cn', '/');
        $this->accessKeyId = $params['serverusername'];
        $this->accessKey = $params['serverpassword'];
        $this->debugMode = $params['configoption9'] == 'on';
        $this->accessToken = null;
        $this->tokenExpiresAt = 0;
    }

    /**
     * 获取API访问令牌
     */
    private function getAccessToken()
    {
        // 检查当前token是否有效
        if ($this->accessToken && $this->tokenExpiresAt > time() + 300) { // 提前5分钟刷新
            return $this->accessToken;
        }

        try {
            // 直接调用获取token的API，不使用makeRequest避免循环调用
            $url = $this->apiEndpoint . '/APIAccessTokenService/getAPIAccessToken';
            $data = array(
                'type' => 'admin', // 默认使用管理员类型
                'accessKeyId' => $this->accessKeyId,
                'accessKey' => $this->accessKey
            );

            $headers = array(
                'Content-Type: application/json',
                'User-Agent: WHMCS-GoEdge-Plugin/1.1'
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new Exception('CURL错误: ' . $error);
            }

            if ($httpCode !== 200) {
                throw new Exception('HTTP错误: ' . $httpCode);
            }

            $result = json_decode($response, true);
            if (!$result) {
                throw new Exception('API响应格式错误');
            }

            if ($result['code'] !== 200) {
                throw new Exception('获取AccessToken失败: ' . ($result['message'] ?? '未知错误'));
            }

            if (!isset($result['data']['token'])) {
                throw new Exception('AccessToken响应格式错误');
            }

            $this->accessToken = $result['data']['token'];
            $this->tokenExpiresAt = $result['data']['expiresAt'] ?? (time() + 3600);

            return $this->accessToken;

        } catch (Exception $e) {
            throw new Exception('获取AccessToken失败: ' . $e->getMessage());
        }
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
            // 根据API文档调用PlanService获取所有可用的套餐计划
            $response = $this->makeRequest('POST', '/PlanService/findAllAvailablePlans', array());

            // 检查API响应格式 - GoEdge API返回格式为 {code: 200, data: {plans: [...]}, message: "ok"}
            $plans_data = array();
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['plans']) && is_array($response['data']['plans'])) {
                $plans_data = $response['data']['plans'];
            } elseif (isset($response['plans']) && is_array($response['plans'])) {
                // 兼容旧格式
                $plans_data = $response['plans'];
            }

            if (!empty($plans_data)) {
                $plans = array();
                foreach ($plans_data as $plan) {
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
                // 提供更详细的错误信息用于调试
                $errorDetails = array(
                    'response_structure' => array_keys($response ?? array()),
                    'code' => $response['code'] ?? 'missing',
                    'message' => $response['message'] ?? 'missing',
                    'has_data' => isset($response['data']),
                    'data_keys' => isset($response['data']) ? array_keys($response['data']) : array()
                );

                return array(
                    'success' => false,
                    'error' => '未找到可用的套餐计划或API响应格式错误',
                    'debug_info' => $this->debugMode ? $errorDetails : null
                );
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取可用的集群列表
     */
    public function getAvailableClusters()
    {
        try {
            // 调用NodeClusterService获取所有启用的集群
            $response = $this->makeRequest('POST', '/NodeClusterService/findAllEnabledNodeClusters', array());

            // 检查API响应格式 - GoEdge API返回格式为 {code: 200, data: {nodeClusters: [...]}, message: "ok"}
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['nodeClusters']) && is_array($response['data']['nodeClusters'])) {

                $clusters = array();
                foreach ($response['data']['nodeClusters'] as $cluster) {
                    $clusters[] = array(
                        'id' => $cluster['id'],
                        'name' => $cluster['name'],
                        'uniqueId' => $cluster['uniqueId'] ?? '',
                        'secret' => $cluster['secret'] ?? '',
                        'description' => $cluster['description'] ?? '',
                        'isOn' => $cluster['isOn'] ?? true
                    );
                }
                return array('success' => true, 'data' => $clusters);
            } else {
                // 提供更详细的错误信息用于调试
                $errorDetails = array(
                    'response_structure' => array_keys($response ?? array()),
                    'code' => $response['code'] ?? 'missing',
                    'message' => $response['message'] ?? 'missing',
                    'has_data' => isset($response['data']),
                    'data_keys' => isset($response['data']) ? array_keys($response['data']) : array()
                );

                return array(
                    'success' => false,
                    'error' => '未找到可用的集群或API响应格式错误',
                    'debug_info' => $this->debugMode ? $errorDetails : null
                );
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取默认集群ID
     */
    public function getDefaultClusterId()
    {
        try {
            $clustersResult = $this->getAvailableClusters();
            if (!$clustersResult['success'] || empty($clustersResult['data'])) {
                return array('success' => false, 'error' => '没有可用的集群');
            }

            // 返回第一个可用集群作为默认集群
            $defaultCluster = $clustersResult['data'][0];
            return array('success' => true, 'data' => array(
                'cluster_id' => $defaultCluster['id'],
                'cluster_name' => $defaultCluster['name']
            ));
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
            // 使用正确的GoEdge UserService API - listEnabledUsers
            $data = array(
                'keyword' => $email,
                'offset' => 0,
                'size' => 10
            );
            $response = $this->makeRequest('POST', '/UserService/listEnabledUsers', $data);

            // 检查API响应格式 - GoEdge API返回格式为 {code: 200, data: {users: [...]}, message: "ok"}
            $users = array();
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['users']) && is_array($response['data']['users'])) {
                $users = $response['data']['users'];
            } elseif (isset($response['users']) && is_array($response['users'])) {
                // 兼容旧格式
                $users = $response['users'];
            }

            if (count($users) > 0) {
                // 查找匹配的用户（精确匹配邮箱）
                foreach ($users as $user) {
                    if (isset($user['email']) && strtolower($user['email']) === strtolower($email)) {
                        return array('success' => true, 'data' => array(
                            'user_id' => $user['id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'fullname' => $user['fullname'] ?? '',
                            'mobile' => $user['mobile'] ?? '',
                            'status' => $user['isOn'] ? 'active' : 'suspended',
                            'is_verified' => isset($user['isVerified']) ? $user['isVerified'] : false,
                            'created_at' => $user['createdAt'] ?? 0,
                            'updated_at' => $user['updatedAt'] ?? 0
                        ));
                    }
                }
                // 如果没有精确匹配，返回用户不存在
                return array('success' => false, 'error' => '用户不存在');
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
            // 获取默认集群ID
            $defaultClusterResult = $this->getDefaultClusterId();
            $clusterId = null;
            if ($defaultClusterResult['success']) {
                $clusterId = $defaultClusterResult['data']['cluster_id'];
            }

            // 使用正确的GoEdge UserService API - 根据API文档构建请求
            $data = array(
                'username' => $accountData['username'],
                'password' => $accountData['password'],
                'email' => $accountData['email'],
                'fullname' => $accountData['fullname'] ?? '',
                'mobile' => $accountData['mobile'] ?? '',
                'tel' => $accountData['tel'] ?? '',
                'remark' => $accountData['remark'] ?? '',
                'source' => $accountData['source'] ?? 'WHMCS'
            );

            // 根据API文档，添加集群ID（单数字段）
            if ($clusterId) {
                $data['nodeClusterId'] = intval($clusterId);
            }

            $response = $this->makeRequest('POST', '/UserService/createUser', $data);

            // 添加详细的调试信息
            if ($this->debugMode) {
                error_log("CreateUser API Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
            }

            // 检查响应格式 - GoEdge API 可能返回不同的字段名
            $userId = null;

            // 尝试多种可能的响应格式
            if (isset($response['userId']) && $response['userId'] > 0) {
                $userId = $response['userId'];
            } elseif (isset($response['user']['id']) && $response['user']['id'] > 0) {
                $userId = $response['user']['id'];
            } elseif (isset($response['id']) && $response['id'] > 0) {
                $userId = $response['id'];
            } elseif (isset($response['data']['userId']) && $response['data']['userId'] > 0) {
                $userId = $response['data']['userId'];
            } elseif (isset($response['data']['id']) && $response['data']['id'] > 0) {
                $userId = $response['data']['id'];
            } elseif (isset($response['result']['userId']) && $response['result']['userId'] > 0) {
                $userId = $response['result']['userId'];
            } elseif (isset($response['result']['id']) && $response['result']['id'] > 0) {
                $userId = $response['result']['id'];
            }

            // 检查是否有 isOk 字段表示成功，但没有返回 userId
            $isSuccess = false;
            if (isset($response['isOk']) && $response['isOk'] === true) {
                $isSuccess = true;
            } elseif (isset($response['success']) && $response['success'] === true) {
                $isSuccess = true;
            } elseif (isset($response['code']) && $response['code'] == 200) {
                $isSuccess = true;
            }

            if ($userId && $userId > 0) {
                // 创建成功后获取完整用户信息
                $userInfo = $this->getUserInfo($userId);
                if ($userInfo['success']) {
                    return array('success' => true, 'data' => $userInfo['data']);
                } else {
                    // 如果获取用户信息失败，返回基本信息
                    return array('success' => true, 'data' => array(
                        'user_id' => $userId,
                        'username' => $accountData['username'],
                        'email' => $accountData['email'],
                        'status' => 'active'
                    ));
                }
            } elseif ($isSuccess) {
                // API 表示成功但没有返回 userId，尝试通过邮箱查找用户
                $findResult = $this->findUserByEmail($accountData['email']);
                if ($findResult['success']) {
                    return array('success' => true, 'data' => $findResult['data']);
                } else {
                    // 如果找不到用户，返回基本信息
                    return array('success' => true, 'data' => array(
                        'user_id' => null,
                        'username' => $accountData['username'],
                        'email' => $accountData['email'],
                        'status' => 'active',
                        'note' => '用户创建成功，但无法获取用户ID'
                    ));
                }
            } else {
                // 提供更详细的错误信息
                $errorDetails = array(
                    'response' => $response,
                    'expected_fields' => array('userId', 'user.id', 'id', 'data.userId', 'result.userId'),
                    'success_indicators' => array('isOk', 'success', 'code'),
                    'message' => $response['message'] ?? 'API响应格式错误'
                );

                return array(
                    'success' => false,
                    'error' => '用户创建失败：' . ($response['message'] ?? 'API响应格式错误'),
                    'debug_info' => $this->debugMode ? $errorDetails : null
                );
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
            $data = array('userId' => intval($userId));
            $response = $this->makeRequest('POST', '/UserService/findEnabledUser', $data);

            // 检查API响应格式 - GoEdge API返回格式为 {code: 200, data: {user: {...}}, message: "ok"}
            $user = null;
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['user']) && $response['data']['user']) {
                $user = $response['data']['user'];
            } elseif (isset($response['user']) && $response['user']) {
                // 兼容旧格式
                $user = $response['user'];
            }

            if ($user) {
                return array('success' => true, 'data' => array(
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'fullname' => $user['fullname'] ?? '',
                    'mobile' => $user['mobile'] ?? '',
                    'status' => $user['isOn'] ? 'active' : 'suspended',
                    'is_verified' => isset($user['isVerified']) ? $user['isVerified'] : false,
                    'created_at' => $user['createdAt'] ?? 0,
                    'updated_at' => $user['updatedAt'] ?? 0
                ));
            } else {
                return array('success' => false, 'error' => '用户不存在或已被禁用');
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
            $data = array('userId' => intval($userId));
            $response = $this->makeRequest('POST', '/UserService/deleteUser', $data);

            // 检查删除是否成功
            // GoEdge API 可能返回简单的 "ok" 字符串或包含 isOk 字段的对象
            if ($response === 'ok' || (isset($response['isOk']) && $response['isOk'])) {
                return array('success' => true, 'data' => array(
                    'user_id' => $userId,
                    'deleted' => true,
                    'message' => '用户删除成功'
                ));
            } else {
                $errorMsg = is_array($response) ? ($response['message'] ?? '删除用户失败') : $response;
                return array('success' => false, 'error' => $errorMsg);
            }
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

            // 根据GoEdge API文档，使用UserPlanService.buyUserPlan购买用户套餐
            // 根据错误信息 "invalid period 'month'"，尝试不传递 period 参数
            // 或者尝试其他可能的值

            // 根据提供的正确字段格式修正参数
            $data = array(
                'userId' => intval($userId),
                'planId' => intval($userPlanData['plan_id']), // 使用预配置的planid
                'name' => $userPlanData['user_plan_name'] ?? '',
                'dayTo' => date('Ymd', strtotime($userPlanData['expire_date'] ?? '+1 month')),
                'period' => $userPlanData['period'] ?? 'monthly', // 正确的值是 'monthly'
                'countMonths' => intval($userPlanData['count_months'] ?? 1) // 使用 countMonths 而不是 countPeriod
            );

            // 调用UserPlanService.buyUserPlan API
            $response = $this->makeRequest('POST', '/UserPlanService/buyUserPlan', $data);

            // 检查API响应格式 - GoEdge API返回格式为 {code: 200, data: {userPlanId: ...}, message: "ok"}
            $userPlanId = null;
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['userPlanId']) && $response['data']['userPlanId'] > 0) {
                $userPlanId = $response['data']['userPlanId'];
            } elseif (isset($response['userPlanId']) && $response['userPlanId'] > 0) {
                // 兼容旧格式
                $userPlanId = $response['userPlanId'];
            }

            if ($userPlanId && $userPlanId > 0) {
                // 创建成功，获取完整的用户套餐信息
                $userPlanInfo = $this->getUserPlanInfo($userPlanId);
                if ($userPlanInfo['success']) {
                    return array('success' => true, 'data' => $userPlanInfo['data']);
                } else {
                    // 如果获取详细信息失败，返回基本信息
                    return array(
                        'success' => true,
                        'data' => array(
                            'package_id' => $userPlanId,
                            'user_id' => $userId,
                            'plan_id' => $userPlanData['plan_id'],
                            'name' => $userPlanData['user_plan_name'] ?? '',
                            'day_to' => $data['dayTo'],
                            'is_on' => true
                        )
                    );
                }
            } else {
                return array('success' => false, 'error' => '套餐购买失败：' . ($response['message'] ?? 'API响应格式错误'));
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取单个用户套餐信息
     * 根据API文档：POST /UserPlanService/findEnabledUserPlan
     * 输入：FindEnabledUserPlanRequest {int64 userPlanId;}
     * 输出：FindEnabledUserPlanResponse {UserPlan userPlan;}
     */
    public function getUserPlanInfo($userPlanId)
    {
        try {
            $data = array('userPlanId' => intval($userPlanId));
            $response = $this->makeRequest('POST', '/UserPlanService/findEnabledUserPlan', $data);

            // 添加详细的调试信息
            if ($this->debugMode) {
                error_log("getUserPlanInfo API Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
            }

            // 检查API响应格式 - GoEdge API返回格式为 {code: 200, data: {userPlan: {...}}, message: "ok"}
            $userPlan = null;
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['userPlan']) && $response['data']['userPlan']) {
                $userPlan = $response['data']['userPlan'];
            } elseif (isset($response['userPlan']) && $response['userPlan']) {
                // 兼容旧格式
                $userPlan = $response['userPlan'];
            }

            if ($userPlan) {
                return array('success' => true, 'data' => array(
                    'package_id' => $userPlan['id'],
                    'user_id' => $userPlan['userId'],
                    'plan_id' => $userPlan['planId'],
                    'name' => $userPlan['name'],
                    'day_from' => $userPlan['dayFrom'] ?? '',
                    'day_to' => $userPlan['dayTo'] ?? '',
                    'is_on' => $userPlan['isOn'] ?? true,
                    'created_at' => $userPlan['createdAt'] ?? 0,
                    'updated_at' => $userPlan['updatedAt'] ?? 0
                ));
            } else {
                return array('success' => false, 'error' => '用户套餐不存在或已被禁用');
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取用户的套餐列表
     * 根据API文档：POST /UserPlanService/listEnabledUserPlans
     * 输入：ListEnabledUserPlansRequest {
     *   bool isAvailable;
     *   bool isExpired;
     *   int32 expiringDays;
     *   int64 userId; // 用户ID
     *   int64 offset; // 读取位置
     *   int64 size;   // 数量，通常不能小于0
     * }
     * 输出：ListEnabledUserPlansResponse {[]UserPlan userPlans;}
     */
    public function getUserPlans($userId, $options = array())
    {
        try {
            // 根据API文档构建请求数据
            $data = array(
                'userId' => intval($userId),
                'offset' => isset($options['offset']) ? intval($options['offset']) : 0,
                'size' => isset($options['size']) ? intval($options['size']) : 100
            );

            // 添加可选的过滤条件
            if (isset($options['is_available'])) {
                $data['isAvailable'] = (bool)$options['is_available'];
            }
            if (isset($options['is_expired'])) {
                $data['isExpired'] = (bool)$options['is_expired'];
            }
            if (isset($options['expiring_days'])) {
                $data['expiringDays'] = intval($options['expiring_days']);
            }

            // 添加详细的调试信息
            if ($this->debugMode) {
                error_log("getUserPlans API Request: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            }

            $response = $this->makeRequest('POST', '/UserPlanService/listEnabledUserPlans', $data);

            // 添加详细的调试信息
            if ($this->debugMode) {
                error_log("getUserPlans API Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
            }

            // 检查API响应格式 - GoEdge API返回格式为 {code: 200, data: {userPlans: [...]}, message: "ok"}
            $userPlans = array();
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['userPlans']) && is_array($response['data']['userPlans'])) {
                $userPlans = $response['data']['userPlans'];
            } elseif (isset($response['userPlans']) && is_array($response['userPlans'])) {
                // 兼容旧格式
                $userPlans = $response['userPlans'];
            }

            // 格式化返回数据，确保字段一致性
            $formattedPlans = array();
            foreach ($userPlans as $plan) {
                $formattedPlans[] = array(
                    'package_id' => $plan['id'],
                    'user_id' => $plan['userId'],
                    'plan_id' => $plan['planId'],
                    'name' => $plan['name'] ?? '',
                    'day_from' => $plan['dayFrom'] ?? '',
                    'day_to' => $plan['dayTo'] ?? '',
                    'is_on' => $plan['isOn'] ?? true,
                    'created_at' => $plan['createdAt'] ?? 0,
                    'updated_at' => $plan['updatedAt'] ?? 0
                );
            }

            return array('success' => true, 'data' => $formattedPlans);
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }



    /**
     * 更新用户套餐
     * 根据API文档：POST /UserPlanService/updateUserPlan
     * 输入：UpdateUserPlanRequest {
     *   int64 userPlanId;
     *   int64 planId;
     *   string dayTo;
     *   bool isOn; // 是否启用
     *   string name; // 名称
     * }
     * 输出：RPCSuccess {}
     */
    public function updateUserPlan($userPlanId, $updateData)
    {
        try {
            // 根据API文档构建请求数据
            $data = array(
                'userPlanId' => intval($userPlanId)
            );

            // 添加可选字段
            if (isset($updateData['plan_id'])) {
                $data['planId'] = intval($updateData['plan_id']);
            }
            if (isset($updateData['day_to'])) {
                $data['dayTo'] = $updateData['day_to'];
            }
            if (isset($updateData['is_on'])) {
                $data['isOn'] = (bool)$updateData['is_on'];
            }
            if (isset($updateData['name'])) {
                $data['name'] = $updateData['name'];
            }

            // 添加详细的调试信息
            if ($this->debugMode) {
                error_log("updateUserPlan API Request: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            }

            $response = $this->makeRequest('POST', '/UserPlanService/updateUserPlan', $data);

            // 添加详细的调试信息
            if ($this->debugMode) {
                error_log("updateUserPlan API Response: " . json_encode($response, JSON_UNESCAPED_UNICODE));
            }

            // 检查更新是否成功 - GoEdge API返回RPCSuccess格式
            if (isset($response['code']) && $response['code'] == 200) {
                return array('success' => true, 'data' => array(
                    'user_plan_id' => $userPlanId,
                    'updated' => true,
                    'message' => '用户套餐更新成功'
                ));
            } elseif (isset($response['isOk']) && $response['isOk']) {
                return array('success' => true, 'data' => array(
                    'user_plan_id' => $userPlanId,
                    'updated' => true,
                    'message' => '用户套餐更新成功'
                ));
            } else {
                return array('success' => false, 'error' => $response['message'] ?? '更新用户套餐失败');
            }
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
            // 先获取当前套餐信息，确保保持其他字段不变
            $planInfo = $this->getUserPlanInfo($userPlanId);
            if (!$planInfo['success']) {
                return array('success' => false, 'error' => '无法获取套餐信息: ' . $planInfo['error']);
            }

            $currentData = $planInfo['data'];

            // 计算新的到期时间
            $newDayTo = date('Ymd', strtotime($renewData['expire_date']));

            // 添加详细的调试信息
            if ($this->debugMode) {
                error_log("renewUserPlan: 完整套餐信息 - " . json_encode($currentData, JSON_UNESCAPED_UNICODE));
                error_log("renewUserPlan: 续费操作 - 原到期时间: {$currentData['day_to']}, 新到期时间: {$newDayTo}");
                error_log("renewUserPlan: planId: {$currentData['plan_id']}, name: {$currentData['name']}, isOn: " . ($currentData['is_on'] ? 'true' : 'false'));
            }

            // 传递完整的套餐信息进行更新，确保所有必需字段都有有效值
            return $this->updateUserPlan($userPlanId, array(
                'plan_id' => $currentData['plan_id'],     // 套餐计划ID
                'is_on' => $currentData['is_on'],         // 保持当前启用状态
                'day_to' => $newDayTo,                    // 更新到期时间
                'name' => $currentData['name']            // 保持套餐名称
            ));
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 测试不同 period 值的套餐购买（用于调试）
     */
    public function testBuyUserPlanWithPeriod($userId, $userPlanData, $testPeriod)
    {
        try {
            // 验证必要参数
            if (empty($userPlanData['plan_id'])) {
                throw new Exception('套餐计划ID不能为空');
            }

            // 使用正确的字段格式进行测试
            $data = array(
                'userId' => intval($userId),
                'planId' => intval($userPlanData['plan_id']),
                'name' => $userPlanData['user_plan_name'] ?? '',
                'dayTo' => date('Ymd', strtotime($userPlanData['expire_date'] ?? '+1 month')),
                'period' => !empty($testPeriod) ? $testPeriod : 'monthly', // 默认使用 monthly
                'countMonths' => 1 // 使用正确的字段名 countMonths
            );

            if ($this->debugMode) {
                error_log("测试 buyUserPlan API，period = '" . ($testPeriod ?: 'null') . "': " . json_encode($data, JSON_UNESCAPED_UNICODE));
            }

            // 调用UserPlanService.buyUserPlan API
            $response = $this->makeRequest('POST', '/UserPlanService/buyUserPlan', $data);

            // 检查API响应格式
            $userPlanId = null;
            if (isset($response['code']) && $response['code'] == 200 &&
                isset($response['data']['userPlanId']) && $response['data']['userPlanId'] > 0) {
                $userPlanId = $response['data']['userPlanId'];
            } elseif (isset($response['userPlanId']) && $response['userPlanId'] > 0) {
                $userPlanId = $response['userPlanId'];
            }

            if ($userPlanId && $userPlanId > 0) {
                return array(
                    'success' => true,
                    'data' => array(
                        'package_id' => $userPlanId,
                        'user_id' => $userId,
                        'plan_id' => $userPlanData['plan_id'],
                        'name' => $userPlanData['user_plan_name'] ?? '',
                        'period' => $testPeriod ?: 'null',
                        'day_to' => $data['dayTo']
                    )
                );
            } else {
                return array('success' => false, 'error' => '套餐购买失败：' . ($response['message'] ?? 'API响应格式错误'));
            }
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 发送HTTP请求
     */
    private function makeRequest($method, $endpoint, $data = null)
    {
        // 获取有效的AccessToken
        $accessToken = $this->getAccessToken();

        $url = $this->apiEndpoint . $endpoint;
        $headers = array(
            'Content-Type: application/json',
            'X-Edge-Access-Token: ' . $accessToken,
            'User-Agent: WHMCS-GoEdge-Plugin/1.1'
        );

        // 详细调试日志
        if ($this->debugMode) {
            error_log("=== GoEdge API Request Debug ===");
            error_log("Method: " . $method);
            error_log("URL: " . $url);
            error_log("Headers: " . json_encode($headers, JSON_UNESCAPED_UNICODE));
            if ($data) {
                error_log("Request Data: " . json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }

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
        $curlInfo = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        // 详细调试日志 - 响应信息
        if ($this->debugMode) {
            error_log("=== GoEdge API Response Debug ===");
            error_log("HTTP Code: " . $httpCode);
            error_log("Response Time: " . $curlInfo['total_time'] . "s");
            error_log("Response Size: " . strlen($response) . " bytes");
            error_log("Raw Response: " . $response);
        }

        if ($error) {
            if ($this->debugMode) {
                error_log("CURL Error: " . $error);
                error_log("CURL Info: " . json_encode($curlInfo, JSON_UNESCAPED_UNICODE));
            }
            throw new Exception("CURL Error: {$error}");
        }

        $decodedResponse = json_decode($response, true);

        // JSON解析错误检查
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            if ($this->debugMode) {
                error_log("JSON Decode Error: " . $jsonError);
                error_log("Raw Response for JSON Error: " . $response);
            }
            throw new Exception("JSON Decode Error: {$jsonError}");
        }

        if ($httpCode >= 400) {
            $errorMsg = isset($decodedResponse['message']) ? $decodedResponse['message'] : "HTTP Error {$httpCode}";
            if ($this->debugMode) {
                error_log("HTTP Error: " . $errorMsg);
                error_log("Error Response: " . json_encode($decodedResponse, JSON_UNESCAPED_UNICODE));
            }
            throw new Exception($errorMsg);
        }

        if ($this->debugMode) {
            error_log("Decoded Response: " . json_encode($decodedResponse, JSON_UNESCAPED_UNICODE));
            error_log("=== End GoEdge API Debug ===");
        }

        return $decodedResponse;
    }

}
