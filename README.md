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
- **生命周期管理**: 自动处理服务暂停、恢复、终止状态同步
- **事务安全**: 完整的事务回滚机制，确保数据一致性

### 🔐 安全特性
- **API安全认证**: 使用HMAC-SHA256签名确保数据传输安全
- **直接登录**: 客户使用自己的用户名和密码直接登录GoEdge控制面板
- **权限隔离**: 基于WHMCS的用户权限体系
- **操作审计**: 关键操作的完整日志记录

### ⚙️ 管理功能
- **套餐绑定配置**: 专注核心配置任务的管理界面
- **API连接管理**: 统一使用WHMCS产品配置中的API参数
- **绑定关系管理**: 直观管理WHMCS产品与GoEdge套餐的对应关系
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
git clone https://github.com/your-repo/whmcs-goedge.git

# 复制到WHMCS模块目录
cp -r whmcs-goedge/* /path/to/whmcs/modules/servers/goedge/

# 设置文件权限
chmod 755 /path/to/whmcs/modules/servers/goedge/
chmod 644 /path/to/whmcs/modules/servers/goedge/*.php
```

### 2. 数据库初始化
插件会在首次使用时自动创建必要的数据表（仅一个核心表），无需手动执行安装脚本。

### 3. WHMCS产品和服务器配置
1. **创建服务器**：
   - 进入WHMCS管理后台 → 系统设置 → 产品/服务 → 服务器
   - 添加新服务器，类型选择 `GoEdge CDN`
   - **服务器IP地址**：设置为GoEdge API端点（如：`https://api.goedge.cn`）
   - **用户名**：设置为您的GoEdge API密钥
   - **密码**：设置为您的GoEdge API密码

2. **创建产品**：
   - 进入 产品/服务 → 产品/服务
   - 创建新产品，模块类型选择 `GoEdge CDN`
   - 分配到上面创建的服务器组

### 4. 套餐绑定配置
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
├── admin/
│   └── plan_binding.php          # 套餐绑定配置页面
├── lib/
│   ├── GoEdgeAPI.php            # GoEdge API接口类
│   ├── GoEdgeDatabase.php       # 数据库操作类
│   ├── GoEdgeLogger.php         # 日志记录类
│   └── GoEdgeTransaction.php    # 事务处理类
├── docs/                        # 文档目录
├── clientarea.php               # 客户端控制面板页面
├── goedge.php                   # 主插件文件
├── hooks.php                    # WHMCS钩子函数
├── install.php                  # 安装脚本
├── LICENSE                      # 许可证文件
└── README.md                    # 说明文档
```

## 🗄️ 数据库结构

插件采用极简数据库设计，仅创建一个核心数据表：

### 核心数据表

#### `mod_goedge_plan_bindings` - 套餐绑定表
存储WHMCS产品与GoEdge套餐计划的绑定关系
```sql
- id: 主键
- product_id: WHMCS产品ID（唯一）
- product_name: WHMCS产品名称
- plan_id: GoEdge套餐计划ID
- plan_name: GoEdge套餐计划名称
- status: 绑定状态（active/disabled）
- created_at/updated_at: 时间戳
```

**极简设计理念**:
- **单表设计**: 仅保留一个核心业务表，最大化简化数据库
- **移除冗余**: 删除了账户、套餐、日志和设置存储表
- **API优先**: 通过API实时获取数据，避免数据同步问题
- **文件日志**: 使用文件日志系统，无需数据库日志表
- **配置统一**: 所有配置通过WHMCS产品配置和代码常量管理

## 🎨 简化设计说明

### 已移除的功能
为了保持插件的简洁性和专注性，以下功能已被移除：

1. **账户管理页面**: 移除了管理员查看和管理GoEdge账户的页面
2. **管理仪表板**: 移除了统计数据展示的管理仪表板
3. **客户端账户信息显示**: 客户端页面不再显示账户详细信息（用户名、邮箱、状态等）
4. **SSO单点登录**: 移除了复杂的SSO认证，改为直接链接到GoEdge官方登录页面
5. **本地数据存储**: 移除了账户和套餐信息的本地存储表
6. **设置管理表**: 移除了插件设置存储表，改用代码常量和WHMCS配置
7. **数据库日志**: 移除了数据库日志表，改用纯文件日志系统

### 最终简化结果
- **单表数据库**: 仅保留`mod_goedge_plan_bindings`一个核心表
- **文件日志**: 完全使用文件日志，无数据库日志负担

### 设计优势
- **减少维护负担**: 无需维护复杂的管理界面和数据同步
- **提高稳定性**: 减少了潜在的故障点和数据不一致问题
- **更好的用户体验**: 客户直接使用GoEdge官方界面，功能更完整
- **简化配置**: 管理员只需关注核心的套餐绑定配置
- **极简数据库**: 最少的数据表，最低的维护成本

## 🔧 故障排除

### 常见问题

#### 1. API连接失败
**症状**: 套餐绑定页面提示API连接失败
**解决方案**:
- 检查API端点URL是否正确（默认：https://api.goedge.cn）
- 验证API密钥和密码是否有效
- 确认服务器网络连接正常，能访问GoEdge API
- 检查防火墙设置，确保允许HTTPS出站连接
- 查看错误日志：检查文件日志 `/path/to/whmcs/modules/servers/goedge/logs/`

#### 2. 账户创建失败
**症状**: 客户购买后账户未自动创建
**解决方案**:
- 检查WHMCS钩子是否正常工作（`hooks.php` 文件）
- 查看文件日志获取详细错误信息
- 验证产品绑定配置：访问 `admin/plan_binding.php` 检查绑定关系
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
- 查看 `mod_goedge_plan_bindings` 表中的绑定记录

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

#### 检查数据一致性
```sql
-- 检查未绑定的产品
SELECT pid, name FROM tblproducts
WHERE servertype = 'goedge'
AND pid NOT IN (SELECT product_id FROM mod_goedge_plan_bindings);

-- 检查绑定关系状态
SELECT * FROM mod_goedge_plan_bindings WHERE status = 'disabled';
```

#### 日志文件维护
```bash
# 清理30天前的日志文件
find /path/to/whmcs/modules/servers/goedge/logs/ -name "goedge_*.log" -mtime +30 -delete

# 压缩旧日志文件
gzip /path/to/whmcs/modules/servers/goedge/logs/goedge_$(date -d '7 days ago' +%Y-%m-%d).log
```

## 📞 技术支持

### 联系方式
- **邮箱**: support@example.com
- **文档**: https://docs.example.com/goedge-plugin
- **GitHub**: https://github.com/your-repo/whmcs-goedge

### 报告问题
提交问题时请包含：
- **WHMCS版本信息**: 在WHMCS管理后台查看
- **PHP版本信息**: `php -v` 命令输出
- **插件版本**: 当前使用的插件版本号
- **错误日志内容**: 来自日志文件 `/path/to/whmcs/modules/servers/goedge/logs/`
- **复现步骤**: 详细的操作步骤
- **环境信息**: 服务器配置、网络环境等

### 调试模式
启用调试模式以获取更详细的日志信息：
1. 在WHMCS产品配置中启用"调试模式"
2. 查看详细的API调用日志
3. 分析错误原因和解决方案

## 📄 许可证

本插件采用 MIT 许可证，详见 [LICENSE](LICENSE) 文件。

## 📋 更新日志

### v1.0.0 (2024-01-01)
- ✅ 初始版本发布
- ✅ 核心自动化功能：账户创建、套餐分配、生命周期管理
- ✅ 套餐绑定配置：WHMCS产品与GoEdge套餐计划绑定
- ✅ 简化客户端界面：仅保留"进入控制面板"按钮
- ✅ 直接登录支持：客户使用GoEdge官方控制面板
- ✅ 统一API配置：使用WHMCS产品配置管理API参数
- ✅ 简化管理界面：专注核心配置任务
- ✅ 事务安全机制：完整的回滚保护
- ✅ 详细日志记录：便于故障排除和监控
- ✅ 用户识别机制：智能处理现有用户和新用户
- ✅ 数据库结构优化：极简单表设计，仅保留核心业务表
- ✅ 文件日志系统：移除数据库日志，改用高效文件日志

### 主要特性
- **极简配置**: 安装后仅需配置套餐绑定关系即可使用
- **智能用户管理**: 自动识别现有用户，避免重复创建
- **原生体验**: 客户直接使用GoEdge官方功能，无需学习新界面
- **企业级安全**: HMAC-SHA256签名、事务保护、操作审计
- **运维友好**: 文件日志系统、故障排除工具、最简数据库维护
- **极简数据库**: 仅一个核心表，最低维护成本

---

**💡 设计哲学**: 本插件专注于核心业务流程的自动化，通过简洁的设计提供强大的功能。我们相信最好的插件是让用户感觉不到它存在的插件。
