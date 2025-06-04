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

            // 检查是否已经在本地数据库中存在记录
            $existingAccount = $db->getAccountByGoEdgeUserId($goedgeUserId);
            if (!$existingAccount) {
                // 保存现有用户信息到本地数据库
                $accountData = array(
                    'service_id' => $params['serviceid'],
                    'goedge_user_id' => $goedgeUserId,
                    'username' => $findResult['data']['username'],
                    'email' => $userEmail,
                    'status' => $findResult['data']['status'] ?: 'active',
                    'created_at' => date('Y-m-d H:i:s'),
                    'bandwidth_limit' => 100,
                    'traffic_limit' => 1000,
                    'max_nodes' => 10,
                    'node_group' => 'default'
                );

                if (!$transaction->saveAccount($accountData)) {
                    throw new Exception('保存现有用户信息到数据库失败');
                }

                $logger->log('已保存现有GoEdge用户信息到本地数据库', $params['serviceid']);
            } else {
                $logger->log('GoEdge用户已存在于本地数据库', $params['serviceid'], $existingAccount);
            }

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

            // 保存账户信息到数据库
            $dbAccountData = array(
                'service_id' => $params['serviceid'],
                'goedge_user_id' => $goedgeUserId,
                'username' => $accountData['username'],
                'email' => $userEmail,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'bandwidth_limit' => 100,
                'traffic_limit' => 1000,
                'max_nodes' => 10,
                'node_group' => 'default'
            );

            if (!$transaction->saveAccount($dbAccountData)) {
                throw new Exception('保存账户信息到数据库失败');
            }

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

            // 保存套餐信息到数据库
            $actualPackageData = $result['data'];
            $packageDbData = array(
                'service_id' => $params['serviceid'],
                'goedge_user_id' => $goedgeUserId,
                'goedge_package_id' => $actualPackageData['package_id'],
                'package_name' => $packageData['package_name'],
                'package_type' => $packageData['package_type'],
                'bandwidth_limit' => $actualPackageData['bandwidth_limit'] ?? 100,
                'traffic_limit' => $actualPackageData['traffic_limit'] ?? 1000,
                'max_nodes' => $actualPackageData['max_nodes'] ?? 10,
                'node_group' => $actualPackageData['node_group'] ?? 'default',
                'features' => $packageData['features'],
                'status' => 'active',
                'expire_date' => $packageData['expire_date'],
                'auto_renew' => $packageData['auto_renew'],
                'created_at' => date('Y-m-d H:i:s')
            );

            if (!$transaction->savePackage($packageDbData)) {
                throw new Exception('保存套餐信息到数据库失败');
            }

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

        $logger->log('开始暂停 GoEdge 账户', $params['serviceid']);

        // 获取账户信息
        $account = $db->getAccountByServiceId($params['serviceid']);
        if (!$account) {
            return '未找到对应的 GoEdge 账户';
        }

        // 获取套餐信息
        $package = $db->getPackageByServiceId($params['serviceid']);

        // 调用 API 暂停账户
        $result = $api->suspendAccount($account['goedge_user_id']);

        if ($result['success']) {
            // 更新账户状态
            $db->updateAccountStatus($params['serviceid'], 'suspended');

            // 如果有套餐，也暂停套餐
            if ($package) {
                $packageResult = $api->suspendUserPlan($package['goedge_package_id']);
                if ($packageResult['success']) {
                    $db->updatePackageStatus($params['serviceid'], 'suspended');
                    $logger->log('GoEdge 套餐暂停成功', $params['serviceid']);
                } else {
                    $logger->log('GoEdge 套餐暂停失败', $params['serviceid'], $packageResult);
                }
            }

            $logger->log('GoEdge 账户暂停成功', $params['serviceid']);
            return 'success';
        } else {
            $logger->log('GoEdge 账户暂停失败', $params['serviceid'], $result);
            return $result['error'] ?: '暂停账户时发生未知错误';
        }

    } catch (Exception $e) {
        $logger->log('暂停账户异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '暂停账户时发生异常: ' . $e->getMessage();
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

        $logger->log('开始恢复 GoEdge 账户', $params['serviceid']);

        // 获取账户信息
        $account = $db->getAccountByServiceId($params['serviceid']);
        if (!$account) {
            return '未找到对应的 GoEdge 账户';
        }

        // 获取套餐信息
        $package = $db->getPackageByServiceId($params['serviceid']);

        // 调用 API 恢复账户
        $result = $api->unsuspendAccount($account['goedge_user_id']);

        if ($result['success']) {
            // 更新账户状态
            $db->updateAccountStatus($params['serviceid'], 'active');

            // 如果有套餐，也恢复套餐
            if ($package) {
                $packageResult = $api->unsuspendUserPlan($package['goedge_package_id']);
                if ($packageResult['success']) {
                    $db->updatePackageStatus($params['serviceid'], 'active');
                    $logger->log('GoEdge 套餐恢复成功', $params['serviceid']);
                } else {
                    $logger->log('GoEdge 套餐恢复失败', $params['serviceid'], $packageResult);
                }
            }

            $logger->log('GoEdge 账户恢复成功', $params['serviceid']);
            return 'success';
        } else {
            $logger->log('GoEdge 账户恢复失败', $params['serviceid'], $result);
            return $result['error'] ?: '恢复账户时发生未知错误';
        }

    } catch (Exception $e) {
        $logger->log('恢复账户异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '恢复账户时发生异常: ' . $e->getMessage();
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

        $logger->log('开始终止 GoEdge 账户', $params['serviceid']);

        // 获取账户信息
        $account = $db->getAccountByServiceId($params['serviceid']);
        if (!$account) {
            return '未找到对应的 GoEdge 账户';
        }

        // 获取套餐信息
        $package = $db->getPackageByServiceId($params['serviceid']);

        // 先终止套餐
        if ($package) {
            $packageResult = $api->deleteUserPlan($package['goedge_package_id']);
            if ($packageResult['success']) {
                $db->updatePackageStatus($params['serviceid'], 'terminated');
                $logger->log('GoEdge 套餐终止成功', $params['serviceid']);
            } else {
                $logger->log('GoEdge 套餐终止失败', $params['serviceid'], $packageResult);
                // 继续终止账户，即使套餐终止失败
            }
        }

        // 调用 API 终止账户
        $result = $api->terminateAccount($account['goedge_user_id']);

        if ($result['success']) {
            // 更新账户状态
            $db->updateAccountStatus($params['serviceid'], 'terminated');
            $logger->log('GoEdge 账户终止成功', $params['serviceid']);
            return 'success';
        } else {
            $logger->log('GoEdge 账户终止失败', $params['serviceid'], $result);
            return $result['error'] ?: '终止账户时发生未知错误';
        }

    } catch (Exception $e) {
        $logger->log('终止账户异常', $params['serviceid'], array('error' => $e->getMessage()));
        return '终止账户时发生异常: ' . $e->getMessage();
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

        // 获取账户信息
        $account = $db->getAccountByServiceId($params['serviceid']);
        if (!$account) {
            return '未找到对应的 GoEdge 账户';
        }

        // 获取当前套餐信息
        $currentPackage = $db->getPackageByServiceId($params['serviceid']);
        if (!$currentPackage) {
            return '未找到对应的 GoEdge 套餐';
        }

        // 获取新产品信息
        $productResult = localAPI('GetClientsProducts', array(
            'serviceid' => $params['serviceid'],
            'stats' => false
        ));

        $newPackageName = 'Updated Package';
        $newPackageType = $currentPackage['package_type'];
        $newFeatures = $currentPackage['features'] ?: array();

        if ($productResult['result'] == 'success' && isset($productResult['products']['product'][0])) {
            $product = $productResult['products']['product'][0];
            $newPackageName = $product['name'] ?: $newPackageName;

            // 根据产品名称更新套餐类型和特性
            if (stripos($product['name'], 'security') !== false) {
                $newPackageType = 'security';
                $newFeatures = array('ddos_protection', 'waf', 'ssl_certificate');
            } elseif (stripos($product['name'], 'acceleration') !== false) {
                $newPackageType = 'acceleration';
                $newFeatures = array('global_cdn', 'image_optimization', 'compression');
            } else {
                $newPackageType = 'cdn';
                $newFeatures = array('basic_cdn', 'cache_optimization');
            }
        }

        // 准备新的套餐数据（使用GoEdge默认配置）
        $newPackageData = array(
            'package_name' => $newPackageName,
            'package_type' => $newPackageType,
            // 移除WHMCS配置选项，让GoEdge使用对应套餐类型的默认配置
            'features' => $newFeatures
        );

        // 调用 API 升级套餐
        $result = $api->upgradePackage($account['goedge_user_id'], $currentPackage['goedge_package_id'], $newPackageData);

        if ($result['success']) {
            // 更新数据库中的套餐信息（使用API返回的实际配置）
            $actualUpgradeData = $result['data'];
            $updateData = array_merge($newPackageData, array(
                'bandwidth_limit' => $actualUpgradeData['bandwidth_limit'] ?? 100,
                'traffic_limit' => $actualUpgradeData['traffic_limit'] ?? 1000,
                'max_nodes' => $actualUpgradeData['max_nodes'] ?? 10,
                'node_group' => $actualUpgradeData['node_group'] ?? 'default'
            ));

            $db->updatePackage($params['serviceid'], $updateData);

            // 同时更新账户的基础信息
            $db->updateAccountPackage($params['serviceid'], array(
                'bandwidth_limit' => $actualUpgradeData['bandwidth_limit'] ?? 100,
                'traffic_limit' => $actualUpgradeData['traffic_limit'] ?? 1000,
                'max_nodes' => $actualUpgradeData['max_nodes'] ?? 10,
                'node_group' => $actualUpgradeData['node_group'] ?? 'default'
            ));

            $logger->log('GoEdge 套餐更改成功', $params['serviceid'], $newPackageData);
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




