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
            $logger->info("检测到GoEdge服务续费", $vars['serviceid']);

            // 简化版本：续费逻辑在模块的RenewAccount函数中处理
            // 这个钩子主要用于记录和通知
        }
    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("处理服务续费钩子异常", $vars['serviceid'], array('error' => $e->getMessage()));
    }
});

// 简化版本：移除暂停/恢复/终止相关的钩子，专注核心业务流程

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
            // 简化版本：直接提供控制面板链接，不需要本地账户信息
            return array(
                'goedge_control_panel_url' => $vars['serveraccesshash'] . '/login'
            );
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

                // 简化版本：只提供管理链接，不需要本地账户信息
                return array(
                    'goedge_admin_url' => 'admin_panel.php'
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
