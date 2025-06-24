<?php
/**
 * GoEdge UserPlanService API 测试脚本
 * 用于验证所有UserPlanService相关的API调用是否正确
 */

require_once 'lib/GoEdgeAPI.php';

// 测试配置 - 请根据实际情况修改
$testConfig = array(
    'serveraccesshash' => 'http://91.229.202.41:9587', // GoEdge API地址
    'serverusername' => '5cRhxh37kXX8Az5a',
    'serverpassword' => 'f3VrA91HkO3s9HpClNOi1knZjpvmwIJS',
    'configoption9' => 'on' // 开启调试模式
);

// 输出函数
function printApiResult($title, $endpoint, $requestData, $response, $success) {
    echo "[$title]\n";
    echo "API调用: POST $endpoint\n";
    if (!empty($requestData)) {
        echo "请求参数: " . json_encode($requestData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
    echo "响应结果: " . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "调用状态: " . ($success ? '✅ 成功' : '❌ 失败') . "\n";
    echo str_repeat('-', 60) . "\n\n";
}

$api = new GoEdgeAPI($testConfig);

echo "=== GoEdge UserPlanService API 测试 ===\n";
echo "测试时间: " . date('Y-m-d H:i:s') . "\n";
echo "API端点: " . $testConfig['serveraccesshash'] . "\n\n";

// 测试1: 测试连接
$connectionResult = $api->testConnection();
printApiResult("1. API连接测试", "/APINodeService/findAllEnabledAPINodes", array(), $connectionResult, $connectionResult['success']);

if (!$connectionResult['success']) {
    echo "❌ API连接失败，无法继续测试\n";
    exit(1);
}

// 测试2: 获取可用套餐计划
echo "2. 测试获取可用套餐计划 (getAvailablePlans)...\n";
$plansResult = $api->getAvailablePlans();
if ($plansResult['success'] && !empty($plansResult['data'])) {
    echo "✅ 获取套餐计划成功，共找到 " . count($plansResult['data']) . " 个套餐\n";
    $testPlanId = $plansResult['data'][0]['id']; // 使用第一个套餐进行测试
    echo "使用套餐ID: {$testPlanId} 进行测试\n";
} else {
    echo "❌ 获取套餐计划失败: " . ($plansResult['error'] ?? '未知错误') . "\n";
    exit(1);
}
echo "\n";

// 测试3: 查找测试用户
echo "3. 查找测试用户...\n";
$testEmail = 'softjez@gmail.com'; // 请替换为实际存在的邮箱
$findResult = $api->findUserByEmail($testEmail);
if ($findResult['success']) {
    echo "✅ 找到测试用户: " . $findResult['data']['username'] . "\n";
    $testUserId = $findResult['data']['user_id'];
} else {
    echo "❌ 未找到测试用户: " . $findResult['error'] . "\n";
    exit(1);
}
echo "\n";

// 测试4: 获取用户现有套餐
echo "4. 测试获取用户套餐列表 (getUserPlans)...\n";
$userPlansResult = $api->getUserPlans($testUserId);
if ($userPlansResult['success']) {
    echo "✅ 获取用户套餐成功，共有 " . count($userPlansResult['data']) . " 个套餐\n";
    if (!empty($userPlansResult['data'])) {
        foreach ($userPlansResult['data'] as $plan) {
            echo "  - 套餐ID: {$plan['id']}, 计划ID: {$plan['planId']}, 名称: {$plan['name']}\n";
        }
    }
} else {
    echo "❌ 获取用户套餐失败: " . $userPlansResult['error'] . "\n";
}
echo "\n";

// 测试5: 创建用户套餐
echo "5. 测试创建用户套餐 (createPackageForUser)...\n";
$userPlanData = array(
    'plan_id' => $testPlanId,
    'user_plan_name' => '测试套餐 - ' . date('Y-m-d H:i:s'),
    'expire_date' => date('Y-m-d', strtotime('+1 month'))
);

$createResult = $api->createPackageForUser($testUserId, $userPlanData);
if ($createResult['success']) {
    echo "✅ 创建用户套餐成功: " . json_encode($createResult['data'], JSON_UNESCAPED_UNICODE) . "\n";
    $newUserPlanId = $createResult['data']['package_id'];
    
    // 测试6: 获取单个用户套餐信息
    echo "\n6. 测试获取单个用户套餐信息 (getUserPlanInfo)...\n";
    $planInfoResult = $api->getUserPlanInfo($newUserPlanId);
    if ($planInfoResult['success']) {
        echo "✅ 获取用户套餐信息成功: " . json_encode($planInfoResult['data'], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ 获取用户套餐信息失败: " . $planInfoResult['error'] . "\n";
    }
    
    // // 测试7: 更新用户套餐
    // echo "\n7. 测试更新用户套餐 (updateUserPlan)...\n";
    // $updateData = array(
    //     'name' => '更新后的测试套餐 - ' . date('Y-m-d H:i:s'),
    //     'day_to' => date('Ymd', strtotime('+2 months'))
    // );
    // $updateResult = $api->updateUserPlan($newUserPlanId, $updateData);
    // if ($updateResult['success']) {
    //     echo "✅ 更新用户套餐成功: " . json_encode($updateResult['data'], JSON_UNESCAPED_UNICODE) . "\n";
    // } else {
    //     echo "❌ 更新用户套餐失败: " . $updateResult['error'] . "\n";
    // }
    
    // 测试8: 暂停用户套餐
    echo "\n8. 测试暂停用户套餐 (suspendUserPlan)...\n";
    $suspendResult = $api->suspendUserPlan($newUserPlanId);
    if ($suspendResult['success']) {
        echo "✅ 暂停用户套餐成功: " . json_encode($suspendResult['data'], JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "❌ 暂停用户套餐失败: " . $suspendResult['error'] . "\n";
    }
    
    // 测试9: 恢复用户套餐
    // echo "\n9. 测试恢复用户套餐 (unsuspendUserPlan)...\n";
    // $unsuspendResult = $api->unsuspendUserPlan($newUserPlanId);
    // if ($unsuspendResult['success']) {
    //     echo "✅ 恢复用户套餐成功: " . json_encode($unsuspendResult['data'], JSON_UNESCAPED_UNICODE) . "\n";
    // } else {
    //     echo "❌ 恢复用户套餐失败: " . $unsuspendResult['error'] . "\n";
    // }
    
    // 测试10: 续费用户套餐
    // echo "\n10. 测试续费用户套餐 (renewUserPlan)...\n";
    // $renewData = array('expire_date' => date('Y-m-d', strtotime('+3 months')));
    // $renewResult = $api->renewUserPlan($newUserPlanId, $renewData);
    // if ($renewResult['success']) {
    //     echo "✅ 续费用户套餐成功: " . json_encode($renewResult['data'], JSON_UNESCAPED_UNICODE) . "\n";
    // } else {
    //     echo "❌ 续费用户套餐失败: " . $renewResult['error'] . "\n";
    // }
    
    // 测试11: 删除用户套餐
    // echo "\n11. 测试删除用户套餐 (deleteUserPlan)...\n";
    // $deleteResult = $api->deleteUserPlan($newUserPlanId);
    // if ($deleteResult['success']) {
    //     echo "✅ 删除用户套餐成功: " . json_encode($deleteResult['data'], JSON_UNESCAPED_UNICODE) . "\n";
    // } else {
    //     echo "❌ 删除用户套餐失败: " . $deleteResult['error'] . "\n";
    // }
    
} else {
    echo "❌ 创建用户套餐失败: " . $createResult['error'] . "\n";
}

echo "\n=== 测试完成 ===\n";

/**
 * 使用说明:
 * 1. 修改 $testConfig 中的API配置信息
 * 2. 将 serverusername 设置为您的 AccessKeyId
 * 3. 将 serverpassword 设置为您的 AccessKey
 * 4. 修改 $testEmail 为实际存在的用户邮箱
 * 5. 运行脚本: php test_userplanservice_api.php
 * 6. 检查输出结果，确认所有API调用是否正常工作
 */
?>
