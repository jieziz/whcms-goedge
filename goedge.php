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

            // 获取默认集群信息（用于日志记录）
            $defaultClusterInfo = null;
            try {
                $defaultClusterResult = $api->getDefaultClusterId();
                if ($defaultClusterResult['success']) {
                    $defaultClusterInfo = $defaultClusterResult['data'];
                    $logger->log('获取默认集群信息', $params['serviceid'], $defaultClusterInfo);
                }
            } catch (Exception $e) {
                $logger->log('获取默认集群失败，将创建无集群关联的用户', $params['serviceid'], array('error' => $e->getMessage()));
            }

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

            // 记录创建结果，包含集群信息
            $logData = $result;
            if ($defaultClusterInfo) {
                $logData['default_cluster'] = $defaultClusterInfo;
            }
            $logger->log('GoEdge 新用户创建成功', $params['serviceid'], $logData);
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









// 简化版本：移除管理员按钮，使用独立的 admin_panel.php 进行管理

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




