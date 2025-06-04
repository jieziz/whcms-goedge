<?php

/**
 * GoEdge 用户信息辅助类
 * 通过API获取用户信息，替代本地数据库存储
 */
class GoEdgeUserHelper
{
    private $api;
    private $logger;

    public function __construct($api, $logger = null)
    {
        $this->api = $api;
        $this->logger = $logger;
    }

    /**
     * 根据WHMCS服务ID获取GoEdge用户信息
     * 通过客户邮箱查找用户
     */
    public function getUserByServiceId($serviceId)
    {
        try {
            // 获取WHMCS服务信息
            $serviceResult = localAPI('GetClientsProducts', array(
                'serviceid' => $serviceId,
                'stats' => false
            ));

            if ($serviceResult['result'] != 'success' || !isset($serviceResult['products']['product'][0])) {
                throw new Exception('无法获取WHMCS服务信息');
            }

            $service = $serviceResult['products']['product'][0];
            $clientId = $service['clientid'];

            // 获取客户信息
            $clientResult = localAPI('GetClientsDetails', array('clientid' => $clientId));
            if ($clientResult['result'] != 'success') {
                throw new Exception('无法获取客户信息');
            }

            $userEmail = $clientResult['email'];
            if (empty($userEmail)) {
                throw new Exception('客户邮箱为空');
            }

            // 通过API查找GoEdge用户
            $findResult = $this->api->findUserByEmail($userEmail);
            if (!$findResult['success']) {
                return array('success' => false, 'error' => '用户不存在于GoEdge平台');
            }

            return array(
                'success' => true,
                'data' => array(
                    'service_id' => $serviceId,
                    'client_id' => $clientId,
                    'email' => $userEmail,
                    'goedge_user_id' => $findResult['data']['user_id'],
                    'username' => $findResult['data']['username'],
                    'status' => $findResult['data']['status']
                )
            );

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('获取用户信息失败', $serviceId, array('error' => $e->getMessage()));
            }
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取用户的套餐信息
     */
    public function getUserPackages($goedgeUserId)
    {
        try {
            $result = $this->api->getUserPlans($goedgeUserId);
            if (!$result['success']) {
                return array('success' => false, 'error' => $result['error']);
            }

            return array('success' => true, 'data' => $result['data']);

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('获取用户套餐失败', null, array(
                    'goedge_user_id' => $goedgeUserId,
                    'error' => $e->getMessage()
                ));
            }
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 获取用户详细信息
     */
    public function getUserInfo($goedgeUserId)
    {
        try {
            $result = $this->api->getUserInfo($goedgeUserId);
            if (!$result['success']) {
                return array('success' => false, 'error' => $result['error']);
            }

            return array('success' => true, 'data' => $result['data']);

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->log('获取用户详细信息失败', null, array(
                    'goedge_user_id' => $goedgeUserId,
                    'error' => $e->getMessage()
                ));
            }
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 检查用户是否存在
     */
    public function userExists($email)
    {
        try {
            $result = $this->api->findUserByEmail($email);
            return $result['success'];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取用户的第一个套餐（用于兼容原有逻辑）
     */
    public function getFirstUserPackage($goedgeUserId)
    {
        try {
            $packagesResult = $this->getUserPackages($goedgeUserId);
            if (!$packagesResult['success'] || empty($packagesResult['data'])) {
                return array('success' => false, 'error' => '用户没有套餐');
            }

            $packages = $packagesResult['data'];
            $firstPackage = $packages[0];

            // 转换为兼容格式
            return array(
                'success' => true,
                'data' => array(
                    'goedge_package_id' => $firstPackage['id'],
                    'package_name' => $firstPackage['name'],
                    'package_type' => $firstPackage['type'] ?? 'cdn',
                    'status' => $firstPackage['isOn'] ? 'active' : 'suspended',
                    'features' => $firstPackage['features'] ?? array()
                )
            );

        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    /**
     * 格式化用户信息用于显示
     */
    public function formatUserInfoForDisplay($serviceId)
    {
        $userResult = $this->getUserByServiceId($serviceId);
        if (!$userResult['success']) {
            return array(
                'success' => false,
                'error' => $userResult['error']
            );
        }

        $userData = $userResult['data'];
        $goedgeUserId = $userData['goedge_user_id'];

        // 获取详细用户信息
        $userInfoResult = $this->getUserInfo($goedgeUserId);
        if (!$userInfoResult['success']) {
            return array(
                'success' => false,
                'error' => '无法获取用户详细信息: ' . $userInfoResult['error']
            );
        }

        // 获取套餐信息
        $packagesResult = $this->getUserPackages($goedgeUserId);
        $packages = $packagesResult['success'] ? $packagesResult['data'] : array();

        return array(
            'success' => true,
            'data' => array(
                'user_info' => $userInfoResult['data'],
                'packages' => $packages,
                'service_id' => $serviceId,
                'client_id' => $userData['client_id']
            )
        );
    }
}
