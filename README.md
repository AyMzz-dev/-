## 温泉PHP网络授权系统Beta

> 警告：原作者已不再更新程序，可能会存在安全漏洞、未知BUG，请谨慎使用；本项目由开源社区中的诸位自行维护。

### 小七二次开发修复版

本版本由 **小七（QQ: 45294701）** 在原版基础上进行二次开发，主要修复和新增以下内容：

#### 安全修复
- 修复 XSS 漏洞：`tips()` 函数使用 `json_encode()` + `htmlspecialchars()` 过滤用户输入
- 密码安全：使用 `password_hash()` 存储密码，`verify_password()` 验证，兼容旧版明文密码自动升级
- IP 安全：`get_real_ip()` 优先使用 `REMOTE_ADDR` 防止 IP 伪造
- SQL 注入防护：`db.class.php` 使用 `mysqli_real_escape_string()` 替代 `addslashes()`
- Session 安全：修复 cookie 参数 lifetime 配置，登录后 `session_regenerate_id(true)` 防会话固定
- Switch 穿透修复：`ajax.php` 中 `macauth`、`kmauth`、`deluser`、`keystatus`、`saveintroduce` 添加 `break` 语句
- 信息泄露：移除数据库错误信息对用户的输出，管理员密码不再存储到 session

#### 数据库表分离
- 一级管理员 → `sq_admin_1` 表（Adminx 后台）
- 二级管理员 → `sq_admin_2` 表（admin 后台）
- 两个后台登录互不影响，独立管理
- 所有表配置 PRIMARY KEY、username 索引、accesstoken 索引、AUTO_INCREMENT

#### 新增功能模块
- **云黑等级系统**：云黑列表新增等级字段（1-3级），支持颜色徽章展示和下拉切换编辑
- **云白系统**：白名单管理，支持添加/删除/等级编辑，等级徽章下拉切换
- **授权查询**：授权站点查询管理，支持品牌名/链接/类型 CRUD 和行内编辑
- **二级管理**：二级管理员列表管理，支持搜索/删除/编辑/状态切换
- **授权划拨**：用户上级 ID 划拨功能
- **代理划拨**：代理上级 ID 划拨功能
- **公告管理**：系统公告发布与管理，支持标题/内容/排序/显示切换，首页公告栏展示
- **在线更新**：通过 GitHub API 检测远程版本更新，查看更新日志，一键跳转仓库

#### UI 美化
- 全局紫蓝渐变（#6a11cb → #2575fc）玻璃态设计风格
- Font Awesome 6.4.0 图标库
- 白色玻璃态卡片（backdrop-filter: blur(20px)）
- 首页应用卡片现代化改造（app-card 卡片设计）
- 输入框、弹窗统一样式升级
- 返回首页按钮、页面居中等细节优化
#### Bug 修复
- 修复主题切换后侧边栏菜单项不可见问题
- 修复 `vip_comm.js` 皮肤切换 html/body 选择器不一致
- 修复退出登录下拉菜单被侧边栏遮挡（z-index）
- 移除紫蓝渐变主题选项

#### 新增数据库表
- `sq_bailist` — 云白系统表
- `sq_site` — 授权查询站点表
- `sq_notice` — 系统公告表

---

### 许可协议

本项目采用 **GNU Affero General Public License v3.0 (AGPL-3.0)** 开源协议，详见 [LICENSE](LICENSE) 文件。

**核心要求：**
- 您可以自由使用、修改、分发本软件
- 如果您修改了本软件并通过网络提供服务，必须公开修改后的源代码
- 必须保留原始版权声明和许可协议
- 严禁用于包括但不限于色情、赌博、诈骗等违反中华人民共和国相关法律法规的用途

本程序仅供软件授权、学习研究、技术交流使用，原作者不再提供任何技术支持。


### 安装说明：

推荐环境：Nginx + PHP 7.3 + MySQL 5.6
支持环境：PHP 7.x +  MySQL 5.6/5.7
其他环境请自行测试，推荐环境是此套程序开发时候所使用的环境。

1. 导入文件 `数据库.sql` 到您的数据库中 *** 导入完成后请务必删除数据库文件 ***

2. 复制 `function/config.inc.example.php` 为 `function/config.inc.php`，修改其中的数据库连接信息

3. 通过执行以下代码新增管理员（请自行将各项参数替换）：

> **注意**：密码字段需使用 password_hash() 加密，不能直接使用 MD5（系统会兼容旧版 MD5 密码自动升级）。请先在 PHP 中生成密码哈希：

```php
<?php
// 生成密码哈希，将输出结果填入下方 SQL 的 password 字段
echo password_hash('您的密码', PASSWORD_DEFAULT);
?>
```

**一级管理员（Adminx 后台）：**
```sql
INSERT INTO sq_admin_1 (ID, username, password, loginip, logintime, qq, lastaccesstime, accesstoken) VALUES (NULL, '您的用户名', '这里填 password_hash 生成的结果', '', unix_timestamp(now()) , '您的QQ', unix_timestamp(now()), '这里写64位随机字符');
```

**二级管理员（admin 后台）：**
```sql
INSERT INTO sq_admin_2 (ID, username, password, loginip, logintime, qq, lastaccesstime, accesstoken) VALUES (NULL, '您的用户名', '这里填 password_hash 生成的结果', '', unix_timestamp(now()) , '您的QQ', unix_timestamp(now()), '这里写64位随机字符');
```


上方执行示例（密码「123456」的 password_hash 结果）：
```sql
INSERT INTO sq_admin_1 (ID, username, password, loginip, logintime, qq, lastaccesstime, accesstoken) VALUES (NULL, 'admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', '', unix_timestamp(now()) , '12345', unix_timestamp(now()), 'JjYBHCBSNeuoNnkTVRCf5EENhZWDcolJOh7MQlmV9pn4S4p4SZFHJaSH75MBK3OZ');
```

以上语句表示新增一个管理员，用户名为【admin】，密码为【123456】，管理员QQ为【12345】。

> 关于随机字符生成可使用：https://suijimimashengcheng.51240.com/

一级后台：/Adminx
二级后台：/admin
用户后台：/user

### 一键部署脚本

项目提供 `deploy.sh` 一键部署脚本，自动从 GitHub 下载最新代码并同步到服务器：

```bash
# 部署到当前目录
cd /www/wwwroot/你的域名
bash deploy.sh

# 或指定目标目录
bash deploy.sh /www/wwwroot/你的域名
```

**脚本自动完成：**
1. 从 GitHub 下载最新 master.zip
2. 同步文件到目标目录（自动排除 `config.inc.php` 保留数据库配置）
3. 自动备份并恢复 config.inc.php（数据库配置不变）
4. 执行数据库迁移（创建 `sq_notice` 公告表）
5. 设置文件权限

**依赖要求：** `curl`、`unzip`、`rsync`、`php`、`mysql`

```bash
# CentOS
yum install -y curl unzip rsync php mysql

# Ubuntu/Debian
apt install -y curl unzip rsync php mysql-client
```

### 在线更新

系统内置自动更新功能，无需手动操作：

1. 登录后台 → 欢迎页 → 点击"立即检查更新"
2. 系统逐文件 MD5 对比 GitHub 仓库，列出所有需要更新的文件
3. 点击"一键更新"自动下载最新代码并替换，保留 `config.inc.php`
4. 首次使用需执行数据库迁移（见下方）

### 部署须知

上传到服务器时，以下核心文件必须同步更新：

| 文件 | 说明 |
|------|------|
| `Adminx/ajax.php` | 一级后台核心逻辑（含分表+新增API） |
| `admin/ajax.php` | 二级后台核心逻辑（含分表+新增API） |
| `Adminx/index.php` | 一级后台登录校验 |
| `admin/index.php` | 二级后台登录校验 |
| `Adminx/admin.html` | 一级后台框架页面 |
| `admin/admin.html` | 二级后台框架页面 |
| `index.php` | 首页 |
| `ajax.php` | 首页后端 |
| `static/` | 静态资源目录 |

### 参与贡献

欢迎提交 Issue 和 Pull Request 来完善本项目。

**贡献方式：**
1. Fork 本仓库
2. 创建你的功能分支 (`git checkout -b feature/xxx`)
3. 提交你的修改 (`git commit -m 'feat: 添加xxx功能'`)
4. 推送到分支 (`git push origin feature/xxx`)
5. 提交 Pull Request

**提交规范：** 请使用 [Conventional Commits](https://www.conventionalcommits.org/) 格式编写 commit message。

---

### 鸣谢

- 感谢原版温泉授权系统作者
- 感谢 **小七** 对本项目的二次开发与安全修复
- 感谢所有参与开源贡献的开发者