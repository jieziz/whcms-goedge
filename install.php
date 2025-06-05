<?php
/**
 * GoEdge CDN服务插件安装脚本
 *
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/GoEdgeDatabase.php';

/**
 * 插件激活时执行
 */
function goedge_activate()
{
    try {
        $db = new GoEdgeDatabase();
        
        // 创建数据表
        $db->createTables();
        
        // 创建日志目录
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // 创建.htaccess保护日志目录
        $htaccessContent = "Order Deny,Allow\nDeny from all";
        file_put_contents($logDir . '/.htaccess', $htaccessContent);
        
        return array('status' => 'success', 'description' => 'GoEdge 插件安装成功！');
        
    } catch (Exception $e) {
        return array('status' => 'error', 'description' => '安装失败: ' . $e->getMessage());
    }
}

/**
 * 插件停用时执行
 */
function goedge_deactivate()
{
    try {
        // 这里可以添加停用时的清理逻辑
        // 注意：不要删除数据表，以防用户重新激活
        
        return array('status' => 'success', 'description' => 'GoEdge 插件已停用');
        
    } catch (Exception $e) {
        return array('status' => 'error', 'description' => '停用失败: ' . $e->getMessage());
    }
}

/**
 * 插件升级时执行
 */
function goedge_upgrade($vars)
{
    try {
        $db = new GoEdgeDatabase();

        // 简化版本不需要复杂的升级逻辑
        // 如果需要升级，直接重新创建表即可
        $db->createTables();

        return array('status' => 'success', 'description' => 'GoEdge 插件升级成功！');

    } catch (Exception $e) {
        return array('status' => 'error', 'description' => '升级失败: ' . $e->getMessage());
    }
}



/**
 * 检查系统要求
 */
function goedge_checkRequirements()
{
    $requirements = array();
    
    // PHP版本检查
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        $requirements[] = 'PHP 版本需要 7.4.0 或更高 (当前: ' . PHP_VERSION . ')';
    }
    
    // 扩展检查
    $requiredExtensions = array('curl', 'json', 'pdo', 'pdo_mysql');
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $requirements[] = "缺少必需的 PHP 扩展: {$ext}";
        }
    }
    
    // 目录权限检查
    $directories = array(__DIR__ . '/logs', __DIR__ . '/cache');
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!is_writable($dir)) {
            $requirements[] = "目录不可写: {$dir}";
        }
    }
    
    return $requirements;
}



/**
 * 清理安装
 */
function goedge_uninstall()
{
    try {
        $db = new GoEdgeDatabase();
        
        // 询问是否删除数据
        if (isset($_POST['delete_data']) && $_POST['delete_data'] == '1') {
            // 删除数据表
            $tables = array(
                'mod_goedge_plan_bindings'
            );

            foreach ($tables as $table) {
                try {
                    $db->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                } catch (PDOException $e) {
                    error_log("删除表 {$table} 失败: " . $e->getMessage());
                }
            }
            
            // 删除文件
            $filesToDelete = array(
                __DIR__ . '/logs',
                __DIR__ . '/cache'
            );
            
            foreach ($filesToDelete as $path) {
                if (is_dir($path)) {
                    removeDirectory($path);
                }
            }
        }
        
        return array('status' => 'success', 'description' => 'GoEdge 插件卸载成功！');
        
    } catch (Exception $e) {
        return array('status' => 'error', 'description' => '卸载失败: ' . $e->getMessage());
    }
}

/**
 * 递归删除目录
 */
function removeDirectory($dir)
{
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// 如果直接访问此文件，显示安装界面
if (basename($_SERVER['PHP_SELF']) == 'install.php') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>GoEdge 插件安装</title>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .success { color: green; }
            .error { color: red; }
            .warning { color: orange; }
            .btn { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
        </style>
    </head>
    <body>
        <h1>GoEdge 插件安装向导</h1>
        
        <?php
        if ($_POST['action'] == 'install') {
            $requirements = goedge_checkRequirements();
            
            if (empty($requirements)) {
                $result = goedge_activate();
                echo '<div class="' . $result['status'] . '">' . $result['description'] . '</div>';
                
                if ($result['status'] == 'success') {
                    echo '<p><a href="admin/goedge_admin.php">进入管理面板</a></p>';
                }
            } else {
                echo '<div class="error">系统要求检查失败:</div>';
                echo '<ul>';
                foreach ($requirements as $req) {
                    echo '<li>' . $req . '</li>';
                }
                echo '</ul>';
            }
        } else {
            $requirements = goedge_checkRequirements();
            
            if (empty($requirements)) {
                echo '<div class="success">系统要求检查通过！</div>';
                echo '<form method="POST">';
                echo '<input type="hidden" name="action" value="install">';
                echo '<button type="submit" class="btn">开始安装</button>';
                echo '</form>';
            } else {
                echo '<div class="error">请先解决以下问题:</div>';
                echo '<ul>';
                foreach ($requirements as $req) {
                    echo '<li>' . $req . '</li>';
                }
                echo '</ul>';
            }
        }
        ?>
    </body>
    </html>
    <?php
}
