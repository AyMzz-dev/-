# 小七授权服务端 (Go)

Go 编写的云端授权验证服务端，配合小七授权系统 PHP 客户端使用。

## 特性

- **单文件部署** — 编译后只有一个可执行文件，复制到服务器直接运行
- **SQLite 数据库** — 无需安装 MySQL，数据存储在 `auth.db` 单文件中
- **内置 Web 管理面板** — 浏览器打开即可添加/启用/禁用/删除授权
- **跨平台编译** — 支持 Windows / Linux / macOS

## 快速开始

### 1. 编译

```bash
cd server/go
go mod tidy
go build -o auth-server .
```

### 2. 运行

```bash
# 默认端口 99，数据库 auth.db
./auth-server

# 自定义端口
./auth-server -port 9090

# 自定义数据库路径
./auth-server -db /data/auth.db
```

### 3. 打开管理面板

浏览器访问 `http://你的IP:99/`

### 4. 添加授权

在管理面板中：填写域名 → 点击生成密钥 → 填写备注 → 添加授权

## API 接口

### 验证授权

```
GET /api.php?mod=checkauth&domain=客户域名&sitekey=站点密钥
```

返回 JSON：
```json
{"code": 1, "msg": "授权有效"}
{"code": 0, "msg": "授权不存在"}
{"code": -3, "msg": "您的授权已被封禁", "tips": "..."}
```

### 发送邮件

```
POST /api.php?mod=sendmail&domain=客户域名&sitekey=站点密钥
Content-Type: application/x-www-form-urlencoded

title=标题&content=内容&tomail=收件人
```

## PHP 客户端配置

在 `function/function_core.php` 中修改：

```php
$ServerDomain['1'] = 'http://你的IP:99/api.php';
```

## 防火墙

开放对应端口：

```bash
# Linux
firewall-cmd --permanent --add-port=99/tcp
firewall-cmd --reload
```

## 跨平台编译

```bash
# Windows
GOOS=windows GOARCH=amd64 go build -o auth-server.exe .

# Linux
GOOS=linux GOARCH=amd64 go build -o auth-server .

# macOS
GOOS=darwin GOARCH=amd64 go build -o auth-server .
```