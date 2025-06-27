<?php
/**
 * WHMCS GoEdge CDN服务平台对接插件
 *
 * @package    WHMCS
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 * @version    1.0.0
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/GoEdgeAPI.php';
require_once __DIR__ . '/lib/GoEdgeDatabase.php';
require_once __DIR__ . '/lib/GoEdgeLogger.php';
require_once __DIR__ . '/lib/GoEdgeTransaction.php';

/**
 * 生成随机密码
 */
function generateRandomPassword($length = 12)
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * 插件元信息
 */
function goedge_MetaData()
{
    return array(
        'DisplayName' => 'GoEdge CDN',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
        'ServiceSingleSignOn' => false,
    );
}

/**
 * 获取第一个可用的GoEdge服务器配置（直接从数据库获取）
 */
function getFirstGoEdgeServerConfig()
{
    try {
        global $CONFIG, $db_host, $db_name, $db_username, $db_password;

        // 获取数据库连接信息
        $host = $db_host ?? $CONFIG['db_host'] ?? 'localhost';
        $database = $db_name ?? $CONFIG['db_name'] ?? '';
        $username = $db_username ?? $CONFIG['db_username'] ?? '';
        $password = $db_password ?? $CONFIG['db_password'] ?? '';

        $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ));

        // 查询第一个可用的GoEdge服务器配置
        $stmt = $pdo->prepare("SELECT * FROM tblservers WHERE type = 'goedge' AND disabled = 0 LIMIT 1");
        $stmt->execute();
        $server = $stmt->fetch();

        if ($server) {
            return array(
                'id' => $server['id'],
                'name' => $server['name'],
                'endpoint' => $server['ipaddress'],
                'username' => $server['username'],
                'password' => $server['password']
            );
        }

        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * 插件配置字段 - 支持GoEdge planid绑定
 */
function goedge_ConfigOptions()
{
    // 获取可用的GoEdge套餐计划
    $availablePlans = array();
    try {
        // 从WHMCS服务器配置中获取第一个可用的GoEdge服务器配置
        $serverConfig = getFirstGoEdgeServerConfig();

        if ($serverConfig) {
            // 构建完整的API端点URL
            $apiEndpoint = $serverConfig['endpoint'];
            // 如果IP地址没有包含协议，添加http://
            if (!preg_match('/^https?:\/\//', $apiEndpoint)) {
                $apiEndpoint = 'http://' . $apiEndpoint;
            }
            // 如果没有端口号，添加默认端口9587
            if (!preg_match('/:\d+/', $apiEndpoint)) {
                $apiEndpoint .= ':9587';
            }

            // 解密服务器密码
            $decryptResult = localAPI('DecryptPassword', array('password2' => $serverConfig['password']));
            $decryptedPassword = ($decryptResult['result'] == 'success') ? $decryptResult['password'] : $serverConfig['password'];

            $apiParams = array(
                'serveraccesshash' => $apiEndpoint,
                'serverusername' => $serverConfig['username'],
                'serverpassword' => $decryptedPassword
            );

            $api = new GoEdgeAPI($apiParams);
            $plansResult = $api->getAvailablePlans();
            if ($plansResult['success']) {
                foreach ($plansResult['data'] as $plan) {
                    $availablePlans[$plan['id']] = $plan['name'] . ' (ID: ' . $plan['id'] . ')';
                }
            }
        } else {
            // 没有找到服务器配置
            $availablePlans = array(
                '' => '请先在WHMCS中配置GoEdge服务器',
            );
        }
    } catch (Exception $e) {
        // API调用失败时的默认选项
        $availablePlans = array(
            '' => '无法获取套餐列表: ' . $e->getMessage(),
        );
    }

    return array(
        'goedge_plan_id' => array(
            'FriendlyName' => 'GoEdge 套餐计划',
            'Type' => 'dropdown',
            'Options' => $availablePlans,
            'Description' => '选择对应的GoEdge套餐计划。API配置请在服务器设置中配置。',
        ),
    );
}

/**
 * 创建账户
 */
function goedge_CreateAccount($params)
{
    // 初始化组件（简化版）
    $logger = new GoEdgeLogger($params);
    $api = new GoEdgeAPI($params);
    $transaction = new GoEdgeTransaction($api, null, $logger); // 不再需要数据库

    try {
        // 开始事务
        $transaction->begin();

        $logger->log('开始创建 GoEdge CDN账户', $params['serviceid']);

        $userEmail = $params['clientsdetails']['email'];
        if (empty($userEmail)) {
            throw new Exception('用户邮箱不能为空');
        }

        $goedgeUserId = null;
        $isExistingUser = false;

        // 首先检查邮箱是否已存在于GoEdge平台
        $logger->log('检查邮箱是否已存在于GoEdge平台', $params['serviceid'], array('email' => $userEmail));
        $findResult = $api->findUserByEmail($userEmail);

        if ($findResult['success'] && $findResult['data']) {
            // 邮箱已存在，使用现有用户
            $goedgeUserId = $findResult['data']['user_id'];
            $isExistingUser = true;

            $logger->log('发现已存在的GoEdge用户', $params['serviceid'], array(
                'goedge_user_id' => $goedgeUserId,
                'username' => $findResult['data']['username'],
                'email' => $userEmail
            ));

            // 简化版本：不需要本地数据库存储，直接使用现有用户

        } else {
            // 邮箱不存在，创建新用户
            $logger->log('邮箱不存在，创建新的GoEdge用户', $params['serviceid']);

            // 简化版本：不获取默认集群信息，减少不必要的API调用

            // 准备账户数据
            $accountData = array(
                'username' => $params['username'] ?: $params['domain'],
                'password' => $params['password'] ?: generateRandomPassword(),
                'email' => $userEmail,
                'status' => 'active'
            );

            // 调用事务管理器创建账户
            $result = $transaction->createUser($accountData);

            if (!$result['success']) {
                throw new Exception('GoEdge 用户创建失败: ' . $result['error']);
            }

            $goedgeUserId = $result['data']['user_id'];

            // 记录创建结果
            $logger->log('GoEdge 新用户创建成功', $params['serviceid'], $result);
        }

        // 为用户开通套餐（直接从产品配置获取）
        if ($goedgeUserId) {
            // 直接从产品配置获取套餐计划ID
            $goedgePlanId = $params['configoption1']; // goedge_plan_id

            if (empty($goedgePlanId)) {
                throw new Exception('未配置GoEdge套餐计划ID。请在WHMCS产品配置中设置"GoEdge 套餐计划ID"。');
            }

            // 获取产品信息
            $product = localAPI('GetProducts', array('pid' => $params['pid']));
            $productName = $product['products']['product'][0]['name'] ?? $params['productname'];

            // 计算到期时间
            $expireDate = date('Y-m-d', strtotime($params['nextduedate'] ?: '+1 month'));

            // 准备用户套餐数据（使用预配置的planid）
            $userPlanData = array(
                'plan_id' => $goedgePlanId,
                'user_plan_name' => $productName . ' - ' . $params['clientsdetails']['firstname'] . ' ' . $params['clientsdetails']['lastname'],
                'expire_date' => $expireDate,
                'auto_renew' => true
            );

            $logger->log('开始为用户分配套餐计划', $params['serviceid'], array(
                'goedge_user_id' => $goedgeUserId,
                'plan_id' => $goedgePlanId,
                'user_plan_name' => $userPlanData['user_plan_name']
            ));

            // 调用事务管理器创建用户套餐
            $result = $transaction->createUserPlan($goedgeUserId, $userPlanData);

            if (!$result['success']) {
                throw new Exception('GoEdge 套餐开通失败: ' . $result['error']);
            }

            // 简化版本：不需要本地数据库存储套餐信息
            // 提交事务
            $transaction->commit();

            $message = $isExistingUser ?
                'GoEdge 现有用户套餐开通成功' :
                'GoEdge 新用户套餐开通成功';
            $logger->log($message, $params['serviceid'], $result);
            return 'success';
        } else {
            throw new Exception('无法获取GoEdge用户ID');
        }

    } catch (Exception $e) {
        // 回滚事务
        if ($transaction->isActive()) {
            $transaction->rollback();
        }

        $logger->log('创建账户异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '创建账户时发生异常: ' . $e->getMessage();
    }
}

/**
 * 删除账户 - 简化版本，仅记录日志
 */
function goedge_TerminateAccount($params)
{
    $logger = new GoEdgeLogger($params);

    try {
        $logger->info("GoEdge账户删除请求", $params['serviceid'], array(
            'domain' => $params['domain'],
            'username' => $params['username'],
            'email' => $params['clientsdetails']['email'],
            'note' => '删除操作已记录，GoEdge用户保留，可手动管理'
        ));

        return 'success';

    } catch (Exception $e) {
        $logger->error("记录删除操作失败", $params['serviceid'], array(
            'error' => $e->getMessage()
        ));
        return $e->getMessage();
    }
}

/**
 * 续费账户 - 延长GoEdge套餐期限
 */
function goedge_RenewAccount($params)
{
    $logger = new GoEdgeLogger($params);
    $api = new GoEdgeAPI($params);

    try {
        $logger->info("开始处理GoEdge服务续费", $params['serviceid'], array(
            'domain' => $params['domain'],
            'username' => $params['username'],
            'next_due_date' => $params['nextduedate']
        ));

        // 获取用户邮箱
        $userEmail = $params['clientsdetails']['email'];

        // 查找GoEdge用户
        $userResult = $api->findUserByEmail($userEmail);

        if (!$userResult['success'] || empty($userResult['data'])) {
            throw new Exception('未找到对应的GoEdge用户: ' . $userEmail);
        }

        $user = $userResult['data'][0];
        $userId = $user['user_id'];

        // 获取套餐计划ID
        $goedgePlanId = $params['configoption1'];
        if (empty($goedgePlanId)) {
            throw new Exception('未配置GoEdge套餐计划ID');
        }

        // 获取用户的套餐列表，找到对应的套餐
        $userPlansResult = $api->getUserPlans($userId);

        if (!$userPlansResult['success']) {
            throw new Exception('获取用户套餐列表失败: ' . $userPlansResult['error']);
        }

        $targetUserPlan = null;
        foreach ($userPlansResult['data'] as $userPlan) {
            if ($userPlan['plan_id'] == $goedgePlanId) {
                $targetUserPlan = $userPlan;
                break;
            }
        }

        if (!$targetUserPlan) {
            // 如果没有找到对应套餐，可能需要重新购买
            $logger->info("未找到对应套餐，尝试重新购买", $params['serviceid'], array(
                'user_id' => $userId,
                'plan_id' => $goedgePlanId
            ));

            // 计算续费期限（从当前时间到下次到期时间的月数）
            $currentTime = time();
            $nextDueTime = strtotime($params['nextduedate']);
            $monthsDiff = max(1, round(($nextDueTime - $currentTime) / (30 * 24 * 3600)));

            // 重新购买套餐
            $buyResult = $api->buyUserPlan($userId, $goedgePlanId, $monthsDiff);

            if ($buyResult['success']) {
                $logger->info("GoEdge套餐续费成功（重新购买）", $params['serviceid'], array(
                    'user_id' => $userId,
                    'plan_id' => $goedgePlanId,
                    'months' => $monthsDiff,
                    'user_plan_id' => $buyResult['data']['user_plan_id']
                ));
            } else {
                throw new Exception('续费失败（重新购买）: ' . $buyResult['error']);
            }
        } else {
            // 如果找到了套餐，使用renewUserPlan API延长期限
            $logger->info("找到现有套餐，使用API延长期限", $params['serviceid'], array(
                'user_plan_id' => $targetUserPlan['user_plan_id'],
                'current_expire' => $targetUserPlan['day_to'],
                'new_expire_date' => $params['nextduedate']
            ));

            // 使用renewUserPlan API延长套餐期限
            $renewResult = $api->renewUserPlan($targetUserPlan['user_plan_id'], array(
                'expire_date' => $params['nextduedate']
            ));

            if ($renewResult['success']) {
                $logger->info("GoEdge套餐续费成功（期限延长）", $params['serviceid'], array(
                    'user_plan_id' => $targetUserPlan['user_plan_id'],
                    'old_expire' => $targetUserPlan['day_to'],
                    'new_expire' => date('Ymd', strtotime($params['nextduedate']))
                ));
            } else {
                throw new Exception('续费失败（期限延长）: ' . $renewResult['error']);
            }
        }

        return 'success';

    } catch (Exception $e) {
        $logger->error("GoEdge服务续费失败", $params['serviceid'], array(
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ));
        return $e->getMessage();
    }
}

/**
 * 客户区域功能
 * 只提供控制面板访问，客户可在GoEdge官方界面进行所有操作
 */
function goedge_ClientAreaCustomButtonArray()
{
    return array(
        '进入控制面板' => 'controlPanel',
    );
}
