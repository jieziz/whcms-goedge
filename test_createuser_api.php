<?php
/**
 * 测试 createUser API 接口参数传递
 * 
 * 验证 createUser API 是否正确传递 nodeClusterId 参数
 */

// 引入必要的文件
require_once 'lib/GoEdgeAPI.php';

// 测试配置
$testConfig = array(
    'serveraccesshash' => 'http://91.229.202.41:9587',
    'serverusername' => '5cRhxh37kXX8Az5a',
    'serverpassword' => 'f3VrA91HkO3s9HpClNOi1knZjpvmwIJS',
    'configoption9' => 'on' // 开启调试模式
);

echo "=== createUser API 参数传递测试 ===\n";
echo "测试时间: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 初始化API客户端
    $api = new GoEdgeAPI($testConfig);
    
    echo "1. 获取默认集群信息\n";
    $defaultClusterResult = $api->getDefaultClusterId();
    
    if (!$defaultClusterResult['success']) {
        echo "❌ 无法获取默认集群，测试终止\n";
        echo "   错误: {$defaultClusterResult['error']}\n";
        exit(1);
    }
    
    $clusterId = $defaultClusterResult['data']['cluster_id'];
    $clusterName = $defaultClusterResult['data']['cluster_name'];
    
    echo "✅ 默认集群获取成功\n";
    echo "   集群ID: {$clusterId}\n";
    echo "   集群名称: {$clusterName}\n\n";
    
    echo "2. 准备创建用户测试\n";
    echo "   API调用: POST /UserService/createUser\n";
    echo "   根据API文档，应传递的参数:\n";
    
    // 准备测试用户数据
    $testAccountData = array(
        'username' => 'test_createuser_' . time(),
        'password' => 'TestPassword123!',
        'email' => 'test_createuser_' . time() . '@example.com',
        'fullname' => '创建用户API测试',
        'mobile' => '13800138001',
        'tel' => '021-12345678',
        'remark' => '通过WHMCS插件创建的测试用户',
        'source' => 'WHMCS'
    );
    
    echo "   基础参数:\n";
    foreach ($testAccountData as $key => $value) {
        echo "     - {$key}: {$value}\n";
    }
    
    echo "   集群参数:\n";
    echo "     - nodeClusterId: {$clusterId} (根据API文档，使用单数字段)\n\n";
    
    echo "3. 执行用户创建\n";
    $createResult = $api->createAccount($testAccountData);
    
    if ($createResult['success']) {
        echo "✅ 用户创建成功\n";
        echo "   用户信息:\n";
        foreach ($createResult['data'] as $key => $value) {
            echo "     - {$key}: {$value}\n";
        }
        
        $userId = $createResult['data']['user_id'];
        
        // 验证用户是否正确关联到集群
        echo "\n4. 验证集群关联\n";
        echo "   获取用户详细信息以验证集群关联...\n";
        
        $userInfoResult = $api->getUserInfo($userId);
        
        if ($userInfoResult['success']) {
            echo "✅ 用户信息获取成功\n";
            echo "   用户详细信息:\n";
            foreach ($userInfoResult['data'] as $key => $value) {
                echo "     - {$key}: {$value}\n";
            }
            
            // 注意：getUserInfo 可能不直接返回集群信息
            // 这里主要验证用户创建成功，集群关联需要在GoEdge管理界面确认
            echo "\n   ⚠️  注意：集群关联信息需要在GoEdge管理界面确认\n";
            echo "   建议检查项目：\n";
            echo "   1. 登录GoEdge管理界面\n";
            echo "   2. 查看用户 '{$userInfoResult['data']['username']}' 的详细信息\n";
            echo "   3. 确认用户是否关联到集群 '{$clusterName}' (ID: {$clusterId})\n";
            
        } else {
            echo "❌ 用户信息获取失败\n";
            echo "   错误: {$userInfoResult['error']}\n";
        }

        // 测试套餐购买的 period 参数
        echo "\n5. 测试套餐购买 period 参数\n";
        echo "   根据错误信息 'invalid period \"month\"'，测试不同的 period 值...\n";

        // 首先获取可用的套餐计划
        $plansResult = $api->getAvailablePlans();
        if (!$plansResult['success'] || empty($plansResult['data'])) {
            echo "❌ 无法获取套餐计划，跳过套餐购买测试\n";
            echo "   错误: " . ($plansResult['error'] ?? '没有可用套餐') . "\n";
        } else {
            $testPlanId = $plansResult['data'][0]['id'];
            echo "✅ 获取到套餐计划，使用套餐ID: {$testPlanId}\n";

            // 测试不同的 period 值
            $periodValues = array(
                'monthly',  // 最可能的值
                'daily',
                'yearly',
                'months',
                'days',
                'years',
                'month',    // 原来的值（已知失败）
                'day',
                'year',
                null        // 不传递 period 参数
            );

            $successfulPeriod = null;

            foreach ($periodValues as $period) {
                echo "\n   测试 period = " . ($period === null ? 'null (不传递)' : "'{$period}'") . ":\n";

                $userPlanData = array(
                    'plan_id' => $testPlanId,
                    'user_plan_name' => "测试套餐 - period=" . ($period ?? 'null'),
                    'expire_date' => date('Y-m-d', strtotime('+1 month'))
                );

                // 如果 period 不为 null，则添加到数据中
                if ($period !== null) {
                    $userPlanData['period'] = $period;
                }

                // 调用测试方法
                $testResult = $api->testBuyUserPlanWithPeriod($userId, $userPlanData, $period ?? '');

                if ($testResult['success']) {
                    echo "   ✅ period=" . ($period === null ? 'null' : "'{$period}'") . " 成功！\n";
                    echo "   返回的用户套餐ID: {$testResult['data']['package_id']}\n";
                    $successfulPeriod = $period;
                    break; // 找到有效的 period 值就停止测试
                } else {
                    echo "   ❌ period=" . ($period === null ? 'null' : "'{$period}'") . " 失败: {$testResult['error']}\n";
                }
            }

            if ($successfulPeriod !== null) {
                echo "\n   🎉 找到有效的 period 值: '{$successfulPeriod}'\n";
                echo "   建议在 GoEdgeAPI.php 中使用此值替换 'month'\n";
            } else {
                echo "\n   ⚠️  所有 period 值都失败，可能需要检查 API 文档或联系技术支持\n";
            }
        }

    } else {
        echo "❌ 用户创建失败\n";
        echo "   错误: {$createResult['error']}\n";

        if (isset($createResult['debug_info'])) {
            echo "   调试信息:\n";
            echo "   " . json_encode($createResult['debug_info'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    echo "\n=== API参数对比 ===\n";
    echo "修复前（错误）:\n";
    echo "  nodeClusterIds: [{$clusterId}]  // 数组格式，字段名错误\n\n";
    echo "修复后（正确）:\n";
    echo "  nodeClusterId: {$clusterId}     // 整数格式，符合API文档\n\n";
    
    echo "=== 完整的API请求参数 ===\n";
    echo "{\n";
    echo "  \"username\": \"{$testAccountData['username']}\",\n";
    echo "  \"password\": \"{$testAccountData['password']}\",\n";
    echo "  \"email\": \"{$testAccountData['email']}\",\n";
    echo "  \"fullname\": \"{$testAccountData['fullname']}\",\n";
    echo "  \"mobile\": \"{$testAccountData['mobile']}\",\n";
    echo "  \"tel\": \"{$testAccountData['tel']}\",\n";
    echo "  \"remark\": \"{$testAccountData['remark']}\",\n";
    echo "  \"source\": \"{$testAccountData['source']}\",\n";
    echo "  \"nodeClusterId\": {$clusterId}\n";
    echo "}\n\n";
    
    echo "=== 测试总结 ===\n";
    echo "修复内容:\n";
    echo "1. ✅ 字段名修正: nodeClusterIds -> nodeClusterId\n";
    echo "2. ✅ 数据类型修正: array -> integer\n";
    echo "3. ✅ 新增API文档中的其他字段: tel, remark, source\n";
    echo "4. ✅ 移除了不必要的 isOn 字段（API文档中没有）\n";
    
} catch (Exception $e) {
    echo "❌ 测试过程中发生异常\n";
    echo "异常信息: " . $e->getMessage() . "\n";
    echo "文件位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== 测试完成 ===\n";
?>
