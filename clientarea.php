<?php
/**
 * GoEdge CDN客户端登录页面
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
require_once __DIR__ . '/lib/GoEdgeLogger.php';

/**
 * GoEdge CDN客户端登录页面
 * 提供GoEdge官方登录页面的直接链接，客户自行登录
 */
function goedge_controlPanel($params)
{
    try {
        $logger = new GoEdgeLogger();

        // 获取GoEdge登录地址
        $goedgeLoginUrl = rtrim($params['serveraccesshash'], '/') . '/login';

        $logger->info("客户访问GoEdge控制面板", $params['serviceid']);

        ob_start();
        ?>
        <div class="goedge-login-panel">
            <style>
                .goedge-login-panel {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    max-width: 500px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .login-card {
                    background: white;
                    border: 1px solid #e1e5e9;
                    border-radius: 12px;
                    padding: 40px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .login-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    border-radius: 8px;
                    margin-bottom: 30px;
                }
                .login-header h3 {
                    margin: 0;
                    font-size: 1.6em;
                }
                .login-button {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 18px 35px;
                    border-radius: 8px;
                    font-size: 1.2em;
                    font-weight: 600;
                    text-decoration: none;
                    display: inline-block;
                    transition: all 0.3s ease;
                    margin: 20px 0;
                }
                .login-button:hover {
                    opacity: 0.9;
                    color: white;
                    text-decoration: none;
                    transform: translateY(-2px);
                    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
                }
                .help-text {
                    color: #6c757d;
                    font-size: 0.9em;
                    margin-top: 25px;
                    line-height: 1.6;
                }
            </style>

            <div class="login-card">
                <div class="login-header">
                    <h3><i class="fas fa-cloud"></i> GoEdge CDN 控制面板</h3>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">管理您的CDN服务</p>
                </div>

                <a href="<?= htmlspecialchars($goedgeLoginUrl) ?>" target="_blank" class="login-button">
                    <i class="fas fa-external-link-alt"></i> 进入 GoEdge 控制面板
                </a>

                <div class="help-text">
                    <p><strong>使用说明：</strong></p>
                    <p>• 点击上方按钮将在新窗口打开GoEdge官方控制面板</p>
                    <p>• 使用您的账户信息登录，享受完整的CDN管理功能</p>
                    <p>• 如忘记密码，请在登录页面使用"忘记密码"功能</p>
                    <p>• 如有问题，请联系客服获取帮助</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();

    } catch (Exception $e) {
        $logger = new GoEdgeLogger();
        $logger->error("显示登录页面异常", $params['serviceid'], array('error' => $e->getMessage()));
        return '
        <div style="text-align: center; padding: 40px; font-family: Arial, sans-serif;">
            <div style="background: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> 显示登录页面时发生错误
            </div>
            <p>请稍后重试或联系客服</p>
        </div>';
    }
}


