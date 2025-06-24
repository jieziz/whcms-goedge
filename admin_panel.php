<?php
/**
 * GoEdge CDN插件 - WHMCS管理员面板
 *
 * 通过WHMCS环境验证管理员身份的安全管理界面
 *
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

// 尝试加载WHMCS环境
$whmcs_root = dirname(dirname(dirname(__DIR__)));
$whmcs_loaded = false;

// 检查多个可能的WHMCS路径
$possible_paths = array(
    $whmcs_root,
    dirname($whmcs_root),
    dirname(dirname($whmcs_root)),
);

foreach ($possible_paths as $path) {
    if (file_exists($path . '/init.php')) {
        chdir($path);
        require_once $path . '/init.php';
        $whmcs_loaded = true;
        $whmcs_url = $CONFIG['SystemURL'];
        break;
    }
}

// 如果无法加载WHMCS环境，显示错误
if (!$whmcs_loaded) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>GoEdge CDN 管理面板 - 配置错误</title>
        <meta charset="utf-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row justify-content-center mt-5">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-danger text-white text-center">
                            <h4><i class="fas fa-exclamation-triangle"></i> 配置错误</h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-danger">
                                <h5>无法加载WHMCS环境</h5>
                                <p>插件无法找到WHMCS的初始化文件。请确保：</p>
                                <ul>
                                    <li>插件文件位于正确的目录：<code>/modules/servers/goedge/</code></li>
                                    <li>WHMCS安装完整且可正常访问</li>
                                    <li>文件权限设置正确</li>
                                </ul>
                            </div>
                            <div class="alert alert-info">
                                <h6>检查的路径：</h6>
                                <ul class="mb-0">
                                    <?php foreach ($possible_paths as $path): ?>
                                        <li><code><?= htmlspecialchars($path) ?>/init.php</code>
                                            <?= file_exists($path . '/init.php') ? '✅' : '❌' ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="text-center">
                                <a href="javascript:history.back()" class="btn btn-secondary">返回</a>
                                <a href="test_index.php" class="btn btn-primary">使用测试页面</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 检查管理员登录状态
if (!isset($_SESSION['adminid'])) {
    // 重定向到WHMCS管理员登录页面
    $login_url = $whmcs_url . '/admin/login.php';
    $return_url = urlencode($_SERVER['REQUEST_URI']);

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>GoEdge CDN 管理面板 - 需要登录</title>
        <meta charset="utf-8">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <meta http-equiv="refresh" content="3;url=<?= htmlspecialchars($login_url) ?>">
    </head>
    <body class="bg-light">
        <div class="container">
            <div class="row justify-content-center mt-5">
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header bg-warning text-dark text-center">
                            <h4><i class="fas fa-lock"></i> 需要管理员登录</h4>
                        </div>
                        <div class="card-body text-center">
                            <div class="alert alert-warning">
                                <h5>访问受限</h5>
                                <p>此页面需要WHMCS管理员权限才能访问。</p>
                            </div>
                            <div class="mb-3">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">正在跳转...</span>
                                </div>
                                <p class="mt-2">正在跳转到管理员登录页面...</p>
                            </div>
                            <div class="d-grid gap-2">
                                <a href="<?= htmlspecialchars($login_url) ?>" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> 立即登录
                                </a>
                                <a href="javascript:history.back()" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> 返回
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 加载必要的类
require_once __DIR__ . '/lib/GoEdgeAPI.php';
require_once __DIR__ . '/lib/GoEdgeDatabase.php';
require_once __DIR__ . '/lib/GoEdgeLogger.php';

// 现在我们有了真正的WHMCS环境，可以直接使用localAPI函数

// 处理AJAX请求
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'test_api':
            try {
                $endpoint = $_POST['endpoint'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (empty($endpoint) || empty($username) || empty($password)) {
                    echo json_encode(array('success' => false, 'error' => 'API配置信息不完整'));
                    exit;
                }
                
                $apiParams = array(
                    'serveraccesshash' => $endpoint,
                    'serverusername' => $username,
                    'serverpassword' => $password
                );
                
                $api = new GoEdgeAPI($apiParams);
                $result = $api->testConnection();
                
                echo json_encode($result);
                
            } catch (Exception $e) {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
            exit;
            
        case 'get_plans':
            try {
                $endpoint = $_POST['endpoint'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (empty($endpoint) || empty($username) || empty($password)) {
                    echo json_encode(array('success' => false, 'error' => 'API配置信息不完整'));
                    exit;
                }
                
                $apiParams = array(
                    'serveraccesshash' => $endpoint,
                    'serverusername' => $username,
                    'serverpassword' => $password
                );
                
                $api = new GoEdgeAPI($apiParams);
                $result = $api->getAvailablePlans();
                
                echo json_encode($result);
                
            } catch (Exception $e) {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
            exit;
            
        case 'save_binding':
            try {
                $productId = $_POST['product_id'] ?? '';
                $productName = $_POST['product_name'] ?? '';
                $planId = $_POST['plan_id'] ?? '';
                $planName = $_POST['plan_name'] ?? '';
                
                if (empty($productId) || empty($planId)) {
                    echo json_encode(array('success' => false, 'error' => '产品ID和套餐计划ID不能为空'));
                    exit;
                }
                
                $db = new GoEdgeDatabase();
                $result = $db->savePlanBinding($productId, $planId, $productName, $planName);
                
                if ($result) {
                    echo json_encode(array('success' => true, 'message' => '绑定关系保存成功'));
                } else {
                    echo json_encode(array('success' => false, 'error' => '保存绑定关系失败'));
                }
                
            } catch (Exception $e) {
                echo json_encode(array('success' => false, 'error' => $e->getMessage()));
            }
            exit;
    }
}

// 获取现有绑定关系
try {
    $db = new GoEdgeDatabase();
    $existingBindings = $db->getAllPlanBindings();
} catch (Exception $e) {
    $existingBindings = array();
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>GoEdge CDN插件 - 管理面板</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .header-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }
        .config-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .btn-goedge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 5px;
            padding: 8px 16px;
            margin: 5px;
        }
        .btn-goedge:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            color: white;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-success { background: #28a745; }
        .status-error { background: #dc3545; }
        .status-warning { background: #ffc107; }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- 页面标题 -->
        <div class="header-card">
            <h1><i class="fas fa-cog"></i> GoEdge CDN 管理面板</h1>
            <p class="mb-0">独立的管理界面，用于配置和测试 GoEdge CDN 插件</p>
        </div>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> 数据库连接错误: <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- API测试区域 -->
        <div class="config-card card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plug"></i> API连接测试</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">API端点</label>
                            <input type="text" class="form-control" id="apiEndpoint" 
                                   value="http://91.229.202.41:9587" placeholder="https://api.goedge.cn">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">AccessKeyId</label>
                            <input type="text" class="form-control" id="apiUsername" 
                                   placeholder="您的AccessKeyId">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label class="form-label">AccessKey</label>
                            <input type="password" class="form-control" id="apiPassword" 
                                   placeholder="您的AccessKey">
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-goedge" onclick="testAPI()">
                        <i class="fas fa-plug"></i> 测试连接
                    </button>
                    <button class="btn-goedge" onclick="loadPlans()">
                        <i class="fas fa-list"></i> 获取套餐列表
                    </button>
                </div>
                <div id="apiTestResult" class="mt-3"></div>
            </div>
        </div>

        <!-- 套餐绑定区域 -->
        <div class="config-card card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-link"></i> 套餐绑定配置</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group mb-3">
                            <label class="form-label">WHMCS产品ID</label>
                            <input type="number" class="form-control" id="productId" placeholder="输入产品ID">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3">
                            <label class="form-label">产品名称</label>
                            <input type="text" class="form-control" id="productName" placeholder="输入产品名称">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3">
                            <label class="form-label">GoEdge套餐ID</label>
                            <select class="form-control" id="planId">
                                <option value="">请先获取套餐列表</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button class="btn-goedge w-100" onclick="saveBinding()">
                                    <i class="fas fa-save"></i> 保存绑定
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 现有绑定关系 -->
        <div class="config-card card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> 现有绑定关系</h5>
            </div>
            <div class="card-body">
                <?php if (empty($existingBindings)): ?>
                    <div class="text-center p-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">暂无绑定关系，请添加WHMCS产品与GoEdge套餐计划的绑定</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>WHMCS产品</th>
                                    <th>GoEdge套餐计划</th>
                                    <th>绑定时间</th>
                                    <th>状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($existingBindings as $binding): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($binding['product_name']) ?></strong><br>
                                            <small class="text-muted">ID: <?= $binding['product_id'] ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($binding['plan_name']) ?></strong><br>
                                            <small class="text-muted">Plan ID: <?= $binding['plan_id'] ?></small>
                                        </td>
                                        <td>
                                            <small><?= $binding['created_at'] ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $binding['status'] == 'active' ? 'success' : 'secondary' ?>">
                                                <?= $binding['status'] == 'active' ? '活跃' : '禁用' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    // 测试API连接
    function testAPI() {
        const endpoint = $('#apiEndpoint').val();
        const username = $('#apiUsername').val();
        const password = $('#apiPassword').val();

        if (!endpoint || !username || !password) {
            alert('请填写完整的API配置信息');
            return;
        }

        $('#apiTestResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> 正在测试连接...</div>');

        $.post('', {
            ajax: true,
            action: 'test_api',
            endpoint: endpoint,
            username: username,
            password: password
        }, function(response) {
            if (response.success) {
                $('#apiTestResult').html('<div class="alert alert-success"><i class="fas fa-check"></i> API连接成功！</div>');
            } else {
                $('#apiTestResult').html('<div class="alert alert-danger"><i class="fas fa-times"></i> API连接失败: ' + response.error + '</div>');
            }
        }, 'json').fail(function() {
            $('#apiTestResult').html('<div class="alert alert-danger"><i class="fas fa-times"></i> 网络请求失败</div>');
        });
    }

    // 获取套餐列表
    function loadPlans() {
        const endpoint = $('#apiEndpoint').val();
        const username = $('#apiUsername').val();
        const password = $('#apiPassword').val();

        if (!endpoint || !username || !password) {
            alert('请先填写API配置信息');
            return;
        }

        $('#planId').html('<option value="">正在加载套餐列表...</option>');

        $.post('', {
            ajax: true,
            action: 'get_plans',
            endpoint: endpoint,
            username: username,
            password: password
        }, function(response) {
            if (response.success) {
                updatePlansDropdown(response.data);
                $('#apiTestResult').html('<div class="alert alert-success"><i class="fas fa-check"></i> 套餐列表加载成功，共 ' + response.data.length + ' 个套餐</div>');
            } else {
                $('#planId').html('<option value="">加载失败</option>');
                $('#apiTestResult').html('<div class="alert alert-danger"><i class="fas fa-times"></i> 获取套餐列表失败: ' + response.error + '</div>');
            }
        }, 'json').fail(function() {
            $('#planId').html('<option value="">网络错误</option>');
            $('#apiTestResult').html('<div class="alert alert-danger"><i class="fas fa-times"></i> 网络请求失败</div>');
        });
    }

    // 更新套餐下拉列表
    function updatePlansDropdown(plans) {
        const dropdown = $('#planId');
        dropdown.empty();
        dropdown.append('<option value="">请选择GoEdge套餐计划</option>');

        plans.forEach(function(plan) {
            dropdown.append(`<option value="${plan.id}" data-name="${plan.name}">${plan.name} (ID: ${plan.id})</option>`);
        });
    }

    // 保存绑定关系
    function saveBinding() {
        const productId = $('#productId').val();
        const productName = $('#productName').val();
        const planId = $('#planId').val();
        const planName = $('#planId option:selected').data('name');

        if (!productId || !planId) {
            alert('请填写产品ID并选择套餐计划');
            return;
        }

        $.post('', {
            ajax: true,
            action: 'save_binding',
            product_id: productId,
            product_name: productName || ('Product #' + productId),
            plan_id: planId,
            plan_name: planName || ('Plan #' + planId)
        }, function(response) {
            if (response.success) {
                alert('绑定关系保存成功');
                location.reload();
            } else {
                alert('保存失败: ' + response.error);
            }
        }, 'json');
    }
    </script>
</body>
</html>
