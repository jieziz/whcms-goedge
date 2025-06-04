<?php
/**
 * GoEdge CDN插件 - 套餐绑定配置
 *
 * @package    WHMCS GoEdge Plugin
 * @author     Your Name
 * @copyright  Copyright (c) 2024
 * @license    MIT License
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once dirname(__DIR__) . '/lib/GoEdgeAPI.php';
require_once dirname(__DIR__) . '/lib/GoEdgeDatabase.php';
require_once dirname(__DIR__) . '/lib/GoEdgeLogger.php';

// 检查管理员权限
if (!isset($_SESSION['adminid'])) {
    header('Location: login.php');
    exit;
}

// 处理AJAX请求
if (isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_goedge_plans':
            try {
                // 从WHMCS产品配置获取API信息
                $productId = $_POST['product_id'] ?? '';

                if (empty($productId)) {
                    echo json_encode(array('success' => false, 'error' => '请先选择WHMCS产品'));
                    exit;
                }

                // 获取产品的服务器配置
                $productResult = localAPI('GetProducts', array('pid' => $productId));
                if ($productResult['result'] != 'success') {
                    echo json_encode(array('success' => false, 'error' => '获取产品信息失败'));
                    exit;
                }

                $product = $productResult['products']['product'][0];
                if (empty($product['servergroup'])) {
                    echo json_encode(array('success' => false, 'error' => '产品未配置服务器组'));
                    exit;
                }

                $serverResult = localAPI('GetServers', array('servergroup' => $product['servergroup']));
                if ($serverResult['result'] != 'success' || empty($serverResult['servers']['server'])) {
                    echo json_encode(array('success' => false, 'error' => '获取服务器配置失败'));
                    exit;
                }

                $server = is_array($serverResult['servers']['server'][0]) ?
                         $serverResult['servers']['server'][0] :
                         $serverResult['servers']['server'];

                $apiParams = array(
                    'serveraccesshash' => $server['ipaddress'] ?? 'https://api.goedge.cn',
                    'serverusername' => $server['username'] ?? '',
                    'serverpassword' => $server['password'] ?? ''
                );

                if (empty($apiParams['serverusername']) || empty($apiParams['serverpassword'])) {
                    echo json_encode(array('success' => false, 'error' => '产品的API配置不完整，请检查服务器设置'));
                    exit;
                }

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
                $planId = $_POST['plan_id'] ?? '';
                
                if (empty($productId) || empty($planId)) {
                    echo json_encode(array('success' => false, 'error' => '产品ID和套餐计划ID不能为空'));
                    exit;
                }
                
                // 保存绑定关系到数据库
                $db = new GoEdgeDatabase();
                $result = $db->savePlanBinding($productId, $planId);
                
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

// 获取WHMCS产品列表和API配置
$whmcsProducts = array();
$apiConfigs = array();
try {
    $result = localAPI('GetProducts', array());
    if ($result['result'] == 'success') {
        foreach ($result['products']['product'] as $product) {
            if ($product['servertype'] == 'goedge') {
                $whmcsProducts[] = $product;

                // 获取产品的服务器配置
                if (!empty($product['servergroup'])) {
                    $serverResult = localAPI('GetServers', array('servergroup' => $product['servergroup']));
                    if ($serverResult['result'] == 'success' && !empty($serverResult['servers']['server'])) {
                        $server = is_array($serverResult['servers']['server'][0]) ?
                                 $serverResult['servers']['server'][0] :
                                 $serverResult['servers']['server'];

                        $apiConfigs[$product['pid']] = array(
                            'endpoint' => $server['ipaddress'] ?? 'https://api.goedge.cn',
                            'key' => $server['username'] ?? '',
                            'secret' => $server['password'] ?? ''
                        );
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    // 处理错误
}

// 获取现有绑定关系
$db = new GoEdgeDatabase();
$existingBindings = $db->getAllPlanBindings();
?>

<!DOCTYPE html>
<html>
<head>
    <title>GoEdge CDN插件 - 套餐绑定配置</title>
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
    </style>
</head>
<body>
    <div class="main-container">
        <!-- 页面标题 -->
        <div class="header-card">
            <h1><i class="fas fa-link"></i> GoEdge CDN 套餐绑定配置</h1>
            <p class="mb-0">配置WHMCS产品与GoEdge套餐计划的绑定关系</p>
        </div>

        <!-- API配置说明 -->
        <div class="config-card card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> API配置说明</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-lightbulb"></i> 配置说明</h6>
                    <p class="mb-2">GoEdge API配置信息从WHMCS产品的服务器设置中读取，请确保：</p>
                    <ul class="mb-0">
                        <li><strong>服务器IP地址</strong>：设置为GoEdge API端点（如：https://api.goedge.cn）</li>
                        <li><strong>用户名</strong>：设置为GoEdge API密钥</li>
                        <li><strong>密码</strong>：设置为GoEdge API密码</li>
                        <li><strong>服务器组</strong>：确保产品已分配到正确的服务器组</li>
                    </ul>
                </div>

                <?php if (!empty($apiConfigs)): ?>
                <div class="mt-3">
                    <h6><i class="fas fa-check-circle text-success"></i> 已配置的产品API信息</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>产品</th>
                                    <th>API端点</th>
                                    <th>配置状态</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($whmcsProducts as $product): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($product['name']) ?></td>
                                        <td>
                                            <?php if (isset($apiConfigs[$product['pid']])): ?>
                                                <code><?= htmlspecialchars($apiConfigs[$product['pid']]['endpoint']) ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">未配置</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (isset($apiConfigs[$product['pid']]) &&
                                                     !empty($apiConfigs[$product['pid']]['key']) &&
                                                     !empty($apiConfigs[$product['pid']]['secret'])): ?>
                                                <span class="badge bg-success">已配置</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">未完整配置</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 添加绑定区域 -->
        <div class="config-card card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-plus"></i> 添加新绑定</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">WHMCS产品</label>
                            <select class="form-control" id="whmcsProduct" required>
                                <option value="">请选择WHMCS产品</option>
                                <?php foreach ($whmcsProducts as $product): ?>
                                    <option value="<?= $product['pid'] ?>"><?= htmlspecialchars($product['name']) ?> (ID: <?= $product['pid'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">GoEdge套餐计划</label>
                            <select class="form-control" id="goedgePlan" required>
                                <option value="">请先选择WHMCS产品</option>
                            </select>
                        </div>
                    </div>
                </div>
                <button class="btn-goedge" onclick="saveBinding()">
                    <i class="fas fa-save"></i> 保存绑定
                </button>
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
                            <th>操作</th>
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
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="editBinding(<?= $binding['id'] ?>)" title="编辑">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteBinding(<?= $binding['id'] ?>)" title="删除">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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
// 页面加载完成后绑定事件
$(document).ready(function() {
    // 监听产品选择变化
    $('#whmcsProduct').change(function() {
        const productId = $(this).val();
        if (productId) {
            loadGoEdgePlans(productId);
        } else {
            $('#goedgePlan').html('<option value="">请先选择WHMCS产品</option>');
        }
    });
});

// 加载GoEdge套餐列表
function loadGoEdgePlans(productId) {
    if (!productId) {
        alert('请先选择WHMCS产品');
        return;
    }

    // 显示加载状态
    $('#goedgePlan').html('<option value="">正在加载套餐列表...</option>');

    $.post('', {
        ajax: true,
        action: 'get_goedge_plans',
        product_id: productId
    }, function(response) {
        if (response.success) {
            updateGoEdgePlansDropdown(response.data);
        } else {
            $('#goedgePlan').html('<option value="">加载失败</option>');
            alert('获取套餐列表失败: ' + response.error);
        }
    }, 'json').fail(function() {
        $('#goedgePlan').html('<option value="">网络错误</option>');
        alert('网络请求失败，请检查网络连接');
    });
}

// 更新GoEdge套餐下拉列表
function updateGoEdgePlansDropdown(plans) {
    const dropdown = $('#goedgePlan');
    dropdown.empty();
    dropdown.append('<option value="">请选择GoEdge套餐计划</option>');
    
    plans.forEach(function(plan) {
        dropdown.append(`<option value="${plan.id}">${plan.name} (ID: ${plan.id})</option>`);
    });
}

// 保存绑定关系
function saveBinding() {
    const productId = $('#whmcsProduct').val();
    const planId = $('#goedgePlan').val();

    if (!productId || !planId) {
        alert('请选择WHMCS产品和GoEdge套餐计划');
        return;
    }

    $.post('', {
        ajax: true,
        action: 'save_binding',
        product_id: productId,
        plan_id: planId
    }, function(response) {
        if (response.success) {
            alert('绑定关系保存成功');
            location.reload();
        } else {
            alert('保存失败: ' + response.error);
        }
    }, 'json');
}

// 编辑绑定
function editBinding(bindingId) {
    alert('编辑功能待实现');
}

// 删除绑定
function deleteBinding(bindingId) {
    if (confirm('确定要删除这个绑定关系吗？')) {
        alert('删除功能待实现');
    }
}
</script>
</body>
</html>
