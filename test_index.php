<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GoEdge API 测试中心</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .test-card {
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        .test-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .test-card h3 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .test-card p {
            color: #7f8c8d;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        .test-button {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .test-button:hover {
            background: #2980b9;
        }
        .test-button.secondary {
            background: #95a5a6;
        }
        .test-button.secondary:hover {
            background: #7f8c8d;
        }
        .config-info {
            background: #ecf0f1;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .config-info h4 {
            margin-top: 0;
            color: #2c3e50;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ready { background: #27ae60; }
        .status-warning { background: #f39c12; }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e8ed;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 GoEdge API 测试中心</h1>
        
        <div class="config-info">
            <h4>📋 当前配置</h4>
            <p><strong>API端点:</strong> http://91.229.202.41:9587</p>
            <p><strong>AccessKeyId:</strong> 5cRhxh37kXX8Az5a</p>
            <p><strong>调试模式:</strong> 已启用</p>
            <p><strong>测试时间:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <div class="test-grid">
            <!-- createUser API测试 -->
            <div class="test-card">
                <h3>➕ 创建用户API测试</h3>
                <p>专门测试 createUser API 接口参数传递，验证 nodeClusterId 字段是否正确传递。根据API文档修复参数格式。</p>
                <p><span class="status-indicator status-ready"></span>参数验证</p>
                <a href="test_createuser_api.php" class="test-button">🆕 运行测试</a>
            </div>

            <!-- 用户套餐测试 -->
            <div class="test-card">
                <h3>📦 用户套餐测试</h3>
                <p>测试用户套餐相关的API功能，包括套餐创建、更新、暂停、恢复、续费和删除等完整生命周期操作。</p>
                <p><span class="status-indicator status-warning"></span>高级功能</p>
                <a href="test_userplanservice_api.php" class="test-button">🧪 运行测试</a>
            </div>

            <!-- 管理界面 -->
            <div class="test-card">
                <h3>⚙️ 管理界面</h3>
                <p>访问GoEdge插件的安全管理界面，配置套餐绑定。需要WHMCS管理员权限。</p>
                <p><span class="status-indicator status-warning"></span>需要权限</p>
                <a href="admin_panel.php" class="test-button">📋 套餐绑定</a>
            </div>
        </div>

        <div class="config-info">
            <h4>📖 测试说明</h4>
            <ul>
                <li><strong>集群功能测试</strong>：验证用户创建时的集群自动关联，这是解决"关联集群为空"问题的核心功能</li>
                <li><strong>创建用户API测试</strong>：验证创建用户接口的参数传递，特别是集群ID字段</li>
                <li><strong>用户套餐测试</strong>：测试套餐管理的完整流程，验证业务逻辑</li>
                <li><strong>管理界面</strong>：提供可视化的配置和监控界面</li>
            </ul>
        </div>

        <div class="config-info">
            <h4>🔍 测试重点</h4>
            <p><strong>关键检查项：</strong></p>
            <ul>
                <li>✅ API连接是否成功</li>
                <li>✅ 集群列表获取是否正常</li>
                <li>✅ 默认集群选择是否正确</li>
                <li>🎯 <strong>用户创建时是否正确传递 nodeClusterId 参数</strong></li>
                <li>📦 <strong>用户套餐管理功能是否正常</strong></li>
            </ul>
        </div>

        <div class="config-info">
            <h4>⚠️ 注意事项</h4>
            <ul>
                <li>测试会创建真实的用户数据，请在测试环境中运行</li>
                <li>某些测试会自动清理创建的测试数据</li>
                <li>如果API调用失败，请检查网络连接和API配置</li>
                <li>测试结果会直接显示在页面上，包含详细的请求和响应信息</li>
            </ul>
        </div>

        <div class="footer">
            <p>GoEdge WHMCS 插件 v1.1 | 集群自动关联功能测试</p>
            <p>如有问题，请查看测试输出中的详细错误信息</p>
        </div>
    </div>
</body>
</html>
