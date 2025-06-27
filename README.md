# WHMCS GoEdge CDN插件

一个简洁高效的WHMCS插件，专注于GoEdge CDN平台的自动化集成。通过最小化的配置实现完整的业务流程自动化。

## 🎯 设计理念

本插件采用"简洁而强大"的设计理念：
- **专注核心业务**：自动化账户创建和套餐分配流程
- **最小化配置**：只需配置产品与套餐的绑定关系
- **原生体验**：客户直接使用GoEdge官方控制面板
- **零维护负担**：避免重复功能开发，减少维护成本

## ✨ 功能特性

### 🚀 核心自动化
- **智能账户创建**: 客户购买后自动在GoEdge平台创建CDN账户
- **用户识别机制**: 如果客户邮箱已在GoEdge平台存在，直接使用现有账户购买套餐
- **套餐自动分配**: 根据预配置的绑定关系自动分配GoEdge套餐
- **生命周期管理**: 专注核心业务流程，简化服务管理
- **事务安全**: 完整的事务回滚机制，确保数据一致性

### 🔐 安全特性
- **AccessKey认证**: 使用GoEdge官方AccessKey/AccessToken认证机制，安全可靠
- **动态Token**: 自动获取和刷新AccessToken，确保认证有效性
- **直接登录**: 客户使用自己的用户名和密码直接登录GoEdge控制面板
- **权限隔离**: 基于WHMCS的用户权限体系
- **操作审计**: 关键操作的完整日志记录

### ⚙️ 管理功能
- **产品配置管理**: 直接在WHMCS产品配置中设置GoEdge套餐计划ID
- **API连接管理**: 统一使用WHMCS服务器配置中的API参数
- **日志监控**: 详细的操作日志和错误追踪

### 👥 客户体验
- **简洁界面**: 客户端仅保留"进入控制面板"按钮
- **直接访问**: 提供GoEdge官方登录页面的直接链接
- **自主登录**: 客户使用自己的用户名和密码登录，无需复杂的SSO配置
- **完整功能**: 在GoEdge官方控制面板享受完整的CDN管理功能

## 系统要求

### 服务器环境
- **PHP**: 7.4 或更高版本
- **MySQL**: 5.7 或更高版本  
- **WHMCS**: 8.0 或更高版本

### PHP扩展
- `curl` - 用于API请求
- `json` - 用于数据处理
- `openssl` - 用于加密功能
- `pdo_mysql` - 用于数据库连接

### GoEdge平台要求
- 有效的GoEdge CDN平台账户
- API访问权限和密钥
- 管理员权限用于创建用户账户和套餐

## 快速安装

### 1. 文件部署
```bash
# 下载插件文件
git clone https://github.com/jieziz/whcms-goedge.git

# 复制到WHMCS模块目录
cp -r whmcs-goedge/* /path/to/whmcs/modules/servers/goedge/

# 设置文件权限
chmod 755 /path/to/whmcs/modules/servers/goedge/
chmod 644 /path/to/whmcs/modules/servers/goedge/*.php
```

### 2. 模块配置
插件采用极简设计，无需数据库初始化，所有配置通过WHMCS标准产品配置管理。

### 3. 创建GoEdge AccessKey
在配置WHMCS之前，您需要在GoEdge管理平台创建API AccessKey：

1. **登录GoEdge管理平台**
2. **创建管理员AccessKey**：
   - 进入"系统用户" → 选择用户"详情" → "API AccessKey"
   - 点击"创建AccessKey"
   - 记录生成的`accessKeyId`和`accessKey`
   - **注意**: 这是管理员级别的AccessKey，具有完整的API权限

### 4. WHMCS产品和服务器配置
1. **创建服务器**：
   - 进入WHMCS管理后台 → 系统设置 → 产品/服务 → 服务器
   - 添加新服务器，类型选择 `GoEdge CDN`
   - **服务器IP地址**：设置为GoEdge API端点（如：`http://91.229.202.41:9587`）
   - **用户名**：设置为您的GoEdge AccessKeyId
   - **密码**：设置为您的GoEdge AccessKey

2. **创建产品**：
   - 进入 产品/服务 → 产品/服务
   - 创建新产品，模块类型选择 `GoEdge CDN`
   - 分配到上面创建的服务器组
   - **注意**: 用户名字段填入AccessKeyId，密码字段填入AccessKey

### 5. 套餐绑定配置
1. 访问 `admin/plan_binding.php` 配置页面
2. 查看API配置状态（从WHMCS产品配置中自动读取）
3. 选择WHMCS产品，系统自动加载对应的GoEdge套餐列表
4. 将WHMCS产品绑定到对应的GoEdge套餐计划ID

## 使用说明

### 管理员操作

#### 套餐绑定配置
- 访问 `admin/plan_binding.php` 进行配置
- 查看API配置状态（从WHMCS产品配置中自动读取）
- 选择WHMCS产品，自动加载对应的GoEdge套餐列表
- 将WHMCS产品绑定到GoEdge套餐计划ID
- 管理现有绑定关系

### 客户操作

#### 控制面板访问
- 客户在WHMCS服务详情页面点击"进入控制面板"按钮
- 页面显示简洁的"进入GoEdge控制面板"按钮
- 点击按钮直接跳转到GoEdge官方登录页面
- 使用自己的账户信息登录，享受完整的CDN管理功能
- 所有CDN管理操作（统计查看、配置修改、密码管理等）在GoEdge官方界面完成

## 工作流程

### 自动化业务流程
1. **客户购买**: 客户在WHMCS购买CDN产品
2. **账户检查**: 系统检查客户邮箱是否已在GoEdge平台存在
3. **账户处理**: 
   - 如果不存在：创建新的GoEdge账户
   - 如果存在：使用现有账户
4. **套餐分配**: 根据绑定关系自动分配对应的GoEdge套餐
5. **服务激活**: 账户和套餐创建成功，服务自动激活

### 生命周期管理
- **暂停**: WHMCS服务暂停时，自动暂停GoEdge账户
- **恢复**: WHMCS服务恢复时，自动恢复GoEdge账户
- **终止**: WHMCS服务终止时，自动终止GoEdge账户

## 📁 文件结构

```
whmcs-goedge/
├── lib/
│   ├── GoEdgeAPI.php            # GoEdge API接口类（已优化，移除无用代码）
│   ├── GoEdgeDatabase.php       # 数据库操作类（极简设计，无数据表）
│   ├── GoEdgeLogger.php         # 日志记录类（文件日志）
│   ├── GoEdgeTransaction.php    # 事务处理类（回滚保护）
│   └── GoEdgeUserHelper.php     # 用户辅助类
├── test_createuser_api.php      # 用户创建API测试脚本
├── test_userplanservice_api.php # 用户套餐API测试脚本
├── test_index.php               # 测试中心入口
├── clientarea.php               # 客户端控制面板页面
├── goedge.php                   # 主插件文件
├── hooks.php                    # WHMCS钩子函数
├── LICENSE                      # 许可证文件
└── README.md                    # 说明文档
```

## 🗄️ 数据库结构

插件采用极简数据库设计，无需创建任何数据表：

### 配置存储方式
- **服务器配置**: 存储在WHMCS标准的 `tblservers` 表中
- **套餐绑定**: 直接在WHMCS产品配置中设置
- **无额外数据表**: 完全依赖WHMCS原生数据结构

### 配置方式

**1. 服务器配置（在WHMCS管理后台）:**
- 进入：系统设置 → 产品/服务 → 服务器
- 添加新服务器，类型选择 `GoEdge CDN`
- 服务器IP地址：GoEdge API端点（如：`http://91.229.202.41:9587`）
- 用户名：GoEdge AccessKeyId
- 密码：GoEdge AccessKey

**2. 产品配置（在产品设置页面）:**
- 在产品的"模块设置"中选择 `GoEdge CDN`
- 在"GoEdge 套餐计划ID"字段中输入对应的套餐ID

## 🧪 API测试工具

插件提供了专门的API测试脚本，帮助开发者和管理员验证API调用是否正常工作：

### UserService API测试
```bash
php test_userservice_api.php
```
测试功能包括：
- API连接测试
- 用户查找（findUserByEmail）
- 用户创建（createAccount）
- 用户信息获取（getUserInfo）
- 用户删除（deleteUser）

### UserPlanService API测试
```bash
php test_userplanservice_api.php
```
测试功能包括：
- 获取可用套餐计划
- 创建用户套餐（createPackageForUser）
- 获取用户套餐列表（getUserPlans）
- 获取单个用户套餐信息（getUserPlanInfo）
- 更新用户套餐（updateUserPlan）
- 续费用户套餐

### 使用测试脚本
1. 修改测试脚本中的API配置信息
2. 设置测试用户邮箱
3. 运行测试脚本查看结果
4. 根据测试结果调试API配置

## 🔧 故障排除

### 常见问题

#### 1. API连接失败
**症状**: 套餐绑定页面提示API连接失败
**解决方案**:
- 检查API端点URL是否正确（如：http://91.229.202.41:9587）
- 验证AccessKeyId和AccessKey是否有效
- 确认AccessKey具有管理员权限（type: admin）
- 确认服务器网络连接正常，能访问GoEdge API
- 检查防火墙设置，确保允许HTTPS出站连接
- 查看错误日志：检查文件日志 `/path/to/whmcs/modules/servers/goedge/logs/`

#### 2. 账户创建失败
**症状**: 客户购买后账户未自动创建
**解决方案**:
- 检查WHMCS钩子是否正常工作（`hooks.php` 文件）
- 查看文件日志获取详细错误信息
- 验证产品绑定配置：访问 `admin_panel.php` 检查绑定关系
- 确认GoEdge API权限，确保有创建用户和套餐的权限
- 检查事务回滚日志，确认失败原因

#### 3. 客户端页面访问失败
**症状**: 客户点击"进入控制面板"按钮无法正常显示页面
**解决方案**:
- 验证GoEdge登录地址配置（serveraccesshash参数）
- 检查clientarea.php文件是否存在且可访问
- 查看WHMCS错误日志和客户端页面错误日志
- 确认服务器PHP环境正常

#### 4. 套餐绑定配置问题
**症状**: 无法加载GoEdge套餐列表或绑定失败
**解决方案**:
- 确认WHMCS产品已正确配置服务器组
- 检查服务器组中的API配置信息
- 验证GoEdge API返回的套餐数据格式
- 检查WHMCS产品配置中的套餐计划ID设置

#### 5. 事务回滚问题
**症状**: 账户创建过程中出现部分成功、部分失败的情况
**解决方案**:
- 检查 `GoEdgeTransaction` 类的回滚日志
- 手动清理不完整的数据记录
- 重新执行账户创建流程
- 确认API调用的原子性

### 日志查看

#### 文件日志
日志文件位置：`/path/to/whmcs/modules/servers/goedge/logs/`
- `goedge_YYYY-MM-DD.log` - 按日期分割的日志文件
- 包含详细的API调用和错误信息
- 每行一个JSON格式的日志记录

#### 日志格式示例
```json
{"timestamp":"2024-01-01 12:00:00","level":"INFO","service_id":"123","message":"创建账户成功","data":{"user_id":"456"},"ip":"127.0.0.1","user_agent":"Mozilla/5.0..."}
```

### 数据库维护

#### 检查配置一致性
```sql
-- 检查GoEdge产品配置
SELECT p.id, p.name, p.configoption1 as goedge_plan_id
FROM tblproducts p
WHERE p.servertype = 'goedge';

-- 检查服务器配置
SELECT * FROM tblservers WHERE type = 'goedge' AND disabled = 0;
```

#### 日志文件维护
```bash
# 清理30天前的日志文件
find /path/to/whmcs/modules/servers/goedge/logs/ -name "goedge_*.log" -mtime +30 -delete

# 压缩旧日志文件
gzip /path/to/whmcs/modules/servers/goedge/logs/goedge_$(date -d '7 days ago' +%Y-%m-%d).log
```

### 调试模式
启用调试模式以获取更详细的日志信息：
1. 在WHMCS产品配置中启用"调试模式"
2. 查看详细的API调用日志
3. 分析错误原因和解决方案


### 主要特性
- **极简配置**: 安装后仅需配置套餐绑定关系即可使用
- **智能用户管理**: 自动识别现有用户，避免重复创建
- **原生体验**: 客户直接使用GoEdge官方功能，无需学习新界面
- **企业级安全**: Token认证、事务保护、操作审计
- **运维友好**: 文件日志系统、故障排除工具、最简数据库维护
- **极简数据库**: 仅一个核心表，最低维护成本

---

**💡 设计哲学**: 本插件专注于核心业务流程的自动化，通过简洁的设计提供强大的功能。我们相信最好的插件是让用户感觉不到它存在的插件。
