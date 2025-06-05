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
        'DisplayName' => 'GoEdge CDN服务',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
    );
}

/**
 * 插件配置字段 - 支持GoEdge planid绑定
 */
function goedge_ConfigOptions()
{
    // 获取可用的GoEdge套餐计划
    $availablePlans = array();
    try {
        // 尝试从GoEdge API获取可用套餐计划
        $tempParams = array(
            'serveraccesshash' => 'https://api.goedge.cn',
            'serverusername' => '',
            'serverpassword' => ''
        );
        $api = new GoEdgeAPI($tempParams);
        $plansResult = $api->getAvailablePlans();
        if ($plansResult['success']) {
            foreach ($plansResult['data'] as $plan) {
                $availablePlans[$plan['id']] = $plan['name'] . ' (ID: ' . $plan['id'] . ')';
            }
        }
    } catch (Exception $e) {
        // API调用失败时的默认选项
        $availablePlans = array(
            '' => '请先配置API信息后刷新页面',
        );
    }

    return array(
        'goedge_plan_id' => array(
            'FriendlyName' => 'GoEdge 套餐计划ID',
            'Type' => 'dropdown',
            'Options' => $availablePlans,
            'Description' => '选择对应的GoEdge套餐计划。如果列表为空，请先配置API信息。',
        ),
        'api_endpoint' => array(
            'FriendlyName' => 'API 端点',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'https://api.goedge.cn',
            'Description' => 'GoEdge API 服务器地址',
        ),
        'api_key' => array(
            'FriendlyName' => 'API 密钥',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'GoEdge API 访问密钥',
        ),
        'api_secret' => array(
            'FriendlyName' => 'API 密码',
            'Type' => 'password',
            'Size' => '50',
            'Description' => 'GoEdge API 访问密码',
        ),
        'auto_suspend' => array(
            'FriendlyName' => '自动暂停',
            'Type' => 'yesno',
            'Description' => '订单暂停时自动暂停 GoEdge CDN账户',
        ),
        'debug_mode' => array(
            'FriendlyName' => '调试模式',
            'Type' => 'yesno',
            'Description' => '启用详细日志记录',
        ),
    );
}

/**
 * 创建账户
 */
function goedge_CreateAccount($params)
{
    // 初始化组件
    $logger = new GoEdgeLogger($params);
    $api = new GoEdgeAPI($params);
    $db = new GoEdgeDatabase();
    $transaction = new GoEdgeTransaction($api, $db, $logger);

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
            $logger->log('GoEdge 新用户创建成功', $params['serviceid'], $result);
        }

        // 为用户开通套餐（使用预配置的planid）
        if ($goedgeUserId) {
            // 首先尝试从数据库绑定关系获取套餐计划ID
            $goedgePlanId = $db->getPlanIdByProductId($params['pid']);

            // 如果数据库中没有绑定关系，则尝试从产品配置获取
            if (empty($goedgePlanId)) {
                $goedgePlanId = $params['configoption1']; // goedge_plan_id
            }

            if (empty($goedgePlanId)) {
                throw new Exception('未找到GoEdge套餐计划ID绑定关系。请在管理员面板的"套餐计划绑定管理"中配置WHMCS产品与GoEdge套餐计划的绑定关系。');
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
 * 暂停账户
 */
function goedge_SuspendAccount($params)
{
    try {
        $logger = new GoEdgeLogger($params);
        $api = new GoEdgeAPI($params);
        $db = new GoEdgeDatabase();

        $logger->log('开始暂停 GoEdge 服务', $params['serviceid']);

        // 通过API查找用户
        $userEmail = $params['clientsdetails']['email'];
        $findResult = $api->findUserByEmail($userEmail);

        if (!$findResult['success'] || !$findResult['data']) {
            return '未找到对应的 GoEdge 账户';
        }

        $goedgeUserId = $findResult['data']['user_id'];

        // 获取当前产品的套餐绑定
        $binding = $db->getPlanBinding($params['pid']);
        if (!$binding) {
            return '未找到产品套餐绑定配置';
        }

        // 查找用户的对应套餐
        $userPlanResult = $api->findUserPlanByPlanId($goedgeUserId, $binding['plan_id']);
        if (!$userPlanResult['success']) {
            return '未找到对应的用户套餐';
        }

        $userPlanId = $userPlanResult['data']['id'];

        // 暂停用户套餐（而不是整个用户账户）
        $result = $api->suspendUserPlan($userPlanId);

        if ($result['success']) {
            $logger->log('GoEdge 服务暂停成功', $params['serviceid'], array('user_plan_id' => $userPlanId));
            return 'success';
        } else {
            $logger->log('GoEdge 服务暂停失败', $params['serviceid'], $result);
            return $result['error'] ?: '暂停服务时发生未知错误';
        }

    } catch (Exception $e) {
        $logger->log('暂停服务异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '暂停服务时发生异常: ' . $e->getMessage();
    }
}

/**
 * 恢复账户
 */
function goedge_UnsuspendAccount($params)
{
    try {
        $logger = new GoEdgeLogger($params);
        $api = new GoEdgeAPI($params);
        $db = new GoEdgeDatabase();

        $logger->log('开始恢复 GoEdge 服务', $params['serviceid']);

        // 通过API查找用户
        $userEmail = $params['clientsdetails']['email'];
        $findResult = $api->findUserByEmail($userEmail);

        if (!$findResult['success'] || !$findResult['data']) {
            return '未找到对应的 GoEdge 账户';
        }

        $goedgeUserId = $findResult['data']['user_id'];

        // 获取当前产品的套餐绑定
        $binding = $db->getPlanBinding($params['pid']);
        if (!$binding) {
            return '未找到产品套餐绑定配置';
        }

        // 查找用户的对应套餐
        $userPlanResult = $api->findUserPlanByPlanId($goedgeUserId, $binding['plan_id']);
        if (!$userPlanResult['success']) {
            return '未找到对应的用户套餐';
        }

        $userPlanId = $userPlanResult['data']['id'];

        // 恢复用户套餐（而不是整个用户账户）
        $result = $api->unsuspendUserPlan($userPlanId);

        if ($result['success']) {
            $logger->log('GoEdge 服务恢复成功', $params['serviceid'], array('user_plan_id' => $userPlanId));
            return 'success';
        } else {
            $logger->log('GoEdge 服务恢复失败', $params['serviceid'], $result);
            return $result['error'] ?: '恢复服务时发生未知错误';
        }

    } catch (Exception $e) {
        $logger->log('恢复服务异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '恢复服务时发生异常: ' . $e->getMessage();
    }
}

/**
 * 终止账户
 */
function goedge_TerminateAccount($params)
{
    try {
        $logger = new GoEdgeLogger($params);
        $api = new GoEdgeAPI($params);
        $db = new GoEdgeDatabase();

        $logger->log('开始终止 GoEdge 服务', $params['serviceid']);

        // 通过API查找用户
        $userEmail = $params['clientsdetails']['email'];
        $findResult = $api->findUserByEmail($userEmail);

        if (!$findResult['success'] || !$findResult['data']) {
            $logger->log('未找到对应的 GoEdge 账户，可能已被删除', $params['serviceid']);
            return 'success'; // 账户不存在视为成功
        }

        $goedgeUserId = $findResult['data']['user_id'];

        // 获取当前产品的套餐绑定
        $binding = $db->getPlanBinding($params['pid']);
        if (!$binding) {
            $logger->log('未找到产品套餐绑定配置，跳过套餐删除', $params['serviceid']);
            return 'success'; // 没有绑定配置视为成功
        }

        // 查找用户的对应套餐
        $userPlanResult = $api->findUserPlanByPlanId($goedgeUserId, $binding['plan_id']);
        if (!$userPlanResult['success']) {
            $logger->log('未找到对应的用户套餐，可能已被删除', $params['serviceid']);
            return 'success'; // 套餐不存在视为成功
        }

        $userPlanId = $userPlanResult['data']['id'];

        // 删除用户套餐（而不是整个用户账户）
        $result = $api->deleteUserPlan($userPlanId);

        if ($result['success']) {
            $logger->log('GoEdge 服务终止成功', $params['serviceid'], array('user_plan_id' => $userPlanId));

            // 检查用户是否还有其他活跃套餐
            $userPlansResult = $api->getUserPlans($goedgeUserId);
            if ($userPlansResult['success'] && empty($userPlansResult['data'])) {
                // 用户没有其他套餐，可以考虑删除用户账户
                $logger->log('用户没有其他套餐，保留用户账户以备将来使用', $params['serviceid']);
                // 注意：根据业务需求，这里可以选择是否删除用户账户
                // 建议保留用户账户，因为用户可能会重新购买服务
            }

            return 'success';
        } else {
            $logger->log('GoEdge 服务终止失败', $params['serviceid'], $result);
            return $result['error'] ?: '终止服务时发生未知错误';
        }

    } catch (Exception $e) {
        $logger->log('终止服务异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '终止服务时发生异常: ' . $e->getMessage();
    }
}

/**
 * 更改套餐
 */
function goedge_ChangePackage($params)
{
    try {
        $logger = new GoEdgeLogger($params);
        $api = new GoEdgeAPI($params);
        $db = new GoEdgeDatabase();

        $logger->log('开始更改 GoEdge 套餐', $params['serviceid']);

        // 简化版本：通过API查找用户
        $userEmail = $params['clientsdetails']['email'];
        $findResult = $api->findUserByEmail($userEmail);

        if (!$findResult['success'] || !$findResult['data']) {
            return '未找到对应的 GoEdge 账户';
        }

        $goedgeUserId = $findResult['data']['user_id'];

        // 获取新的套餐绑定
        $binding = $db->getPlanBinding($params['pid']);
        if (!$binding) {
            return '未找到产品套餐绑定配置';
        }

        // 调用 API 更改套餐
        $result = $api->changeUserPlan($goedgeUserId, $binding['plan_id']);

        if ($result['success']) {
            $logger->log('GoEdge 套餐更改成功', $params['serviceid'], array('new_plan_id' => $binding['plan_id']));
            return 'success';
        } else {
            $logger->log('GoEdge 套餐更改失败', $params['serviceid'], $result);
            return $result['error'] ?: '更改套餐时发生未知错误';
        }

    } catch (Exception $e) {
        $logger->log('更改套餐异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '更改套餐时发生异常: ' . $e->getMessage();
    }
}



/**
 * 管理员区域功能
 * 简化版本，专注核心管理功能
 */
function goedge_AdminCustomButtonArray()
{
    return array(
        '同步账户状态' => 'syncAccountStatus',
        '套餐绑定配置' => 'planBinding',
    );
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




