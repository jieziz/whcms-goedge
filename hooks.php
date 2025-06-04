<?php
/**
 * WHMCS GoEdge CDN插件钩子文件
 *
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/GoEdgeAPI.php';
require_once __DIR__ . '/lib/GoEdgeDatabase.php';
require_once __DIR__ . '/lib/GoEdgeLogger.php';

/**
 * 订单支付完成后自动开通服务
 */
add_hook('InvoicePaid', 1, function($vars) {
    try {
        $invoiceId = $vars['invoiceid'];
        
        // 获取发票相关的订单
        $result = localAPI('GetInvoice', array('invoiceid' => $invoiceId));
        
        if ($result['result'] == 'success') {
            foreach ($result['items']['item'] as $item) {
                if ($item['type'] == 'Hosting') {
                    $serviceId = $item['relid'];
                    
                    // 检查是否为GoEdge产品
                    $serviceResult = localAPI('GetClientsProducts', array(
                        'serviceid' => $serviceId,
                        'stats' => false
                    ));
                    
                    if ($serviceResult['result'] == 'success' && 
                        isset($serviceResult['products']['product'][0]) &&
                        $serviceResult['products']['product'][0]['servertype'] == 'goedge') {
                        
                        $logger = new GoEdgeLogger();
                        $logger->info("检测到GoEdge CDN服务支付完成，准备自动开通", $serviceId);
                        
                        // 自动开通服务
                        $moduleResult = localAPI('ModuleCreate', array('serviceid' => $serviceId));
                        
                        if ($moduleResult['result'] == 'success') {
                            $logger->info("GoEdge服务自动开通成功", $serviceId);
                        } else {
                            $logger->error("GoEdge服务自动开通失败", $serviceId, $moduleResult);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理订单支付钩子异常", null, array('error' => $e->getMessage()));
    }
});

/**
 * 服务续费后延长GoEdge账户期限
 */
add_hook('AfterModuleRenew', 1, function($vars) {
    try {
        if ($vars['producttype'] == 'hostingaccount' && $vars['servertype'] == 'goedge') {
            $logger = new GoEdgeLogger();
            $db = new GoEdgeDatabase();
            $api = new GoEdgeAPI($vars);

            $logger->info("检测到GoEdge服务续费", $vars['serviceid']);

            // 获取账户信息
            $account = $db->getAccountByServiceId($vars['serviceid']);
            if ($account) {
                // 获取套餐信息并续费
                $package = $db->getPackageByServiceId($vars['serviceid']);
                if ($package) {
                    // 计算新的到期时间
                    $newExpiryDate = date('Y-m-d', strtotime($vars['nextduedate']));
                    $renewData = array('expire_date' => $newExpiryDate);

                    // 调用API续费套餐
                    $packageResult = $api->renewUserPlan($package['goedge_package_id'], $renewData);

                    if ($packageResult['success']) {
                        // 更新数据库中的套餐到期时间
                        $db->renewPackage($vars['serviceid'], $newExpiryDate);
                        $logger->info("GoEdge套餐续费成功", $vars['serviceid'], array('new_expiry' => $newExpiryDate));
                    } else {
                        $logger->error("GoEdge套餐续费失败", $vars['serviceid'], $packageResult);
                    }
                } else {
                    $logger->warning("未找到对应的GoEdge套餐", $vars['serviceid']);
                }
            }
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理服务续费钩子异常", $vars['serviceid'], array('error' => $e->getMessage()));
    }
});

/**
 * 服务暂停时自动暂停GoEdge账户
 */
add_hook('AfterModuleSuspend', 1, function($vars) {
    try {
        if ($vars['producttype'] == 'hostingaccount' && $vars['servertype'] == 'goedge') {
            $logger = new GoEdgeLogger();
            $logger->info("检测到GoEdge服务暂停", $vars['serviceid']);
            
            // 这里实际的暂停操作已经在模块的SuspendAccount函数中处理
            // 这个钩子主要用于记录和通知
            
            // 发送暂停通知邮件给客户
            sendMessage('GoEdge Service Suspended', $vars['userid'], array(
                'service_id' => $vars['serviceid'],
                'domain' => $vars['domain'],
                'suspend_reason' => $vars['suspendreason'] ?: '账户暂停'
            ));
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理服务暂停钩子异常", $vars['serviceid'], array('error' => $e->getMessage()));
    }
});

/**
 * 服务恢复时自动恢复GoEdge账户
 */
add_hook('AfterModuleUnsuspend', 1, function($vars) {
    try {
        if ($vars['producttype'] == 'hostingaccount' && $vars['servertype'] == 'goedge') {
            $logger = new GoEdgeLogger();
            $logger->info("检测到GoEdge服务恢复", $vars['serviceid']);
            
            // 发送恢复通知邮件给客户
            sendMessage('GoEdge Service Unsuspended', $vars['userid'], array(
                'service_id' => $vars['serviceid'],
                'domain' => $vars['domain']
            ));
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理服务恢复钩子异常", $vars['serviceid'], array('error' => $e->getMessage()));
    }
});

/**
 * 服务终止时删除GoEdge账户
 */
add_hook('AfterModuleTerminate', 1, function($vars) {
    try {
        if ($vars['producttype'] == 'hostingaccount' && $vars['servertype'] == 'goedge') {
            $logger = new GoEdgeLogger();
            $logger->info("检测到GoEdge服务终止", $vars['serviceid']);
            
            // 发送终止通知邮件给客户
            sendMessage('GoEdge Service Terminated', $vars['userid'], array(
                'service_id' => $vars['serviceid'],
                'domain' => $vars['domain']
            ));
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理服务终止钩子异常", $vars['serviceid'], array('error' => $e->getMessage()));
    }
});

/**
 * 套餐升级/降级处理
 */
add_hook('AfterModuleChangePackage', 1, function($vars) {
    try {
        if ($vars['producttype'] == 'hostingaccount' && $vars['servertype'] == 'goedge') {
            $logger = new GoEdgeLogger();
            $logger->info("检测到GoEdge套餐变更", $vars['serviceid'], array(
                'old_package' => $vars['oldproductname'],
                'new_package' => $vars['productname']
            ));
            
            // 发送套餐变更通知邮件
            sendMessage('GoEdge Package Changed', $vars['userid'], array(
                'service_id' => $vars['serviceid'],
                'domain' => $vars['domain'],
                'old_package' => $vars['oldproductname'],
                'new_package' => $vars['productname']
            ));
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理套餐变更钩子异常", $vars['serviceid'], array('error' => $e->getMessage()));
    }
});

/**
 * 客户端区域页面添加自定义内容
 */
add_hook('ClientAreaPageProductDetails', 1, function($vars) {
    try {
        if ($vars['servertype'] == 'goedge') {
            $db = new GoEdgeDatabase();
            $account = $db->getAccountByServiceId($vars['serviceid']);
            
            if ($account) {
                // 添加GoEdge账户信息到页面
                return array(
                    'goedge_account' => $account,
                    'goedge_control_panel_url' => $vars['serveraccesshash'] . '/login'
                );
            }
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理客户端页面钩子异常", $vars['serviceid'], array('error' => $e->getMessage()));
    }
    
    return array();
});

/**
 * 管理员区域添加GoEdge管理链接
 */
add_hook('AdminAreaPage', 1, function($vars) {
    if ($vars['filename'] == 'clientsservices' && isset($_GET['id'])) {
        $serviceId = $_GET['id'];
        
        try {
            // 检查是否为GoEdge服务
            $result = localAPI('GetClientsProducts', array(
                'serviceid' => $serviceId,
                'stats' => false
            ));
            
            if ($result['result'] == 'success' && 
                isset($result['products']['product'][0]) &&
                $result['products']['product'][0]['servertype'] == 'goedge') {
                
                $db = new GoEdgeDatabase();
                $account = $db->getAccountByServiceId($serviceId);
                
                return array(
                    'goedge_account' => $account,
                    'goedge_admin_url' => 'admin/plan_binding.php'
                );
            }
        } catch (Exception $e) {
            $logger = new GoEdgeLogger();
            $logger->error("处理管理员页面钩子异常", $serviceId, array('error' => $e->getMessage()));
        }
    }
    
    return array();
});

/**
 * 定期同步账户状态（通过cron任务调用）
 * 简化版本：仅记录日志，不执行批量同步
 */
add_hook('DailyCronJob', 1, function($vars) {
    try {
        $logger = new GoEdgeLogger();
        $logger->info("GoEdge插件定期任务执行 - 账户管理功能已简化");

        // 可以在这里添加其他必要的定期任务
        // 例如：清理过期日志、检查配置等

    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("GoEdge定期任务执行异常", null, array('error' => $e->getMessage()));
    }
});
