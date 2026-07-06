#!/bin/bash
# ============================================
# 小七授权系统 - 一键部署脚本
# 用法:
#   bash deploy.sh                  → 部署到当前目录
#   bash deploy.sh /www/wwwroot/xxx → 部署到指定目录
# ============================================
set -e

GITHUB_REPO="https://github.com/AyMzz-dev/-/archive/refs/heads/master.zip"
TMP_DIR="/tmp/wenquan_deploy_$(date +%s)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()   { echo -e "${GREEN}[✓]${NC} $1"; }
warn()  { echo -e "${YELLOW}[!]${NC} $1"; }
err()   { echo -e "${RED}[✗]${NC} $1"; }
info()  { echo -e "${BLUE}[i]${NC} $1"; }

# ============================================
# 1. 下载最新代码
# ============================================
download_code() {
    log "正在从 GitHub 下载最新代码..."
    mkdir -p "$TMP_DIR"
    curl -sL "$GITHUB_REPO" -o "$TMP_DIR/master.zip"

    if [ ! -f "$TMP_DIR/master.zip" ]; then
        err "下载失败，请检查网络连接"
        exit 1
    fi

    local size=$(stat -c%s "$TMP_DIR/master.zip" 2>/dev/null || echo 0)
    if [ "$size" -lt 1000 ]; then
        err "下载的文件过小 ($size bytes)，可能是 GitHub 访问受限"
        err "请尝试手动下载: $GITHUB_REPO"
        exit 1
    fi

    unzip -qo "$TMP_DIR/master.zip" -d "$TMP_DIR"
    SRC_DIR=$(ls -d "$TMP_DIR"/*/ 2>/dev/null | head -1)

    if [ -z "$SRC_DIR" ]; then
        err "解压失败，目录为空"
        exit 1
    fi

    log "下载完成 → $(basename "$SRC_DIR")"
}

# ============================================
# 2. 部署到目标目录
# ============================================
deploy_to() {
    local SRC="$1"
    local DST="$2"

    # 规范化路径
    DST=$(cd "$DST" 2>/dev/null && pwd || echo "$DST")

    if [ ! -d "$DST" ]; then
        err "目标目录不存在: $DST"
        exit 1
    fi

    echo ""
    log "部署目标: $DST"

    # 备份 config
    if [ -f "$DST/function/config.inc.php" ]; then
        cp "$DST/function/config.inc.php" "$TMP_DIR/config_backup.php"
        warn "已备份 config.inc.php"
    fi

    # 同步文件
    info "正在同步文件..."
    rsync -a --delete \
        --exclude='config.inc.php' \
        --exclude='install.php' \
        --exclude='.git' \
        --exclude='.gitignore' \
        --exclude='upload/' \
        --exclude='README.md' \
        --exclude='LICENSE' \
        --exclude='CHANGELOG' \
        --exclude='deploy.sh' \
        "$SRC/" "$DST/" 2>&1 | grep -E '^[^s]' | tail -5 || true

    # 恢复 config
    if [ -f "$TMP_DIR/config_backup.php" ]; then
        cp "$TMP_DIR/config_backup.php" "$DST/function/config.inc.php"
        log "已恢复 config.inc.php（数据库配置不变）"
    fi

    # 设置权限
    if command -v chown &>/dev/null; then
        chown -R www:www "$DST" 2>/dev/null || true
    fi
    chmod -R 755 "$DST" 2>/dev/null || true
    log "文件权限已设置"

    echo ""
    log "部署完成！"
}

# ============================================
# 3. 数据库迁移
# ============================================
run_db_migration() {
    local DST="$1"
    local CONFIG="$DST/function/config.inc.php"

    if [ ! -f "$CONFIG" ]; then
        warn "未找到 config.inc.php，跳过数据库迁移"
        warn "请手动执行 SQL（见下方数据库迁移部分）"
        return
    fi

    info "读取数据库配置..."
    DB_HOST=$(php -r "include '$CONFIG'; echo \$G['db']['host'] ?? 'localhost';" 2>/dev/null)
    DB_NAME=$(php -r "include '$CONFIG'; echo \$G['db']['name'] ?? '';" 2>/dev/null)
    DB_USER=$(php -r "include '$CONFIG'; echo \$G['db']['user'] ?? 'root';" 2>/dev/null)
    DB_PASS=$(php -r "include '$CONFIG'; echo \$G['db']['pass'] ?? '';" 2>/dev/null)

    if [ -z "$DB_NAME" ]; then
        warn "无法读取数据库名称，跳过数据库迁移"
        return
    fi

    log "连接数据库: $DB_NAME@$DB_HOST"

    local MYSQL_CMD="mysql"
    if [ -n "$DB_USER" ]; then
        MYSQL_CMD="$MYSQL_CMD -u$DB_USER"
    fi
    if [ -n "$DB_PASS" ]; then
        MYSQL_CMD="$MYSQL_CMD -p$DB_PASS"
    fi
    if [ -n "$DB_HOST" ] && [ "$DB_HOST" != "localhost" ]; then
        MYSQL_CMD="$MYSQL_CMD -h$DB_HOST"
    fi
    MYSQL_CMD="$MYSQL_CMD $DB_NAME"

    $MYSQL_CMD <<'SQL'
CREATE TABLE IF NOT EXISTS `sq_notice` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `time` int(11) NOT NULL,
  `status` int(1) NOT NULL DEFAULT '1',
  `sort` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `sq_notice` (`ID`, `title`, `content`, `time`, `status`, `sort`) VALUES
(1, '小七授权系统 v12.13 发布', '欢迎使用小七PHP网络授权系统！\n\n本版本主要特性：\n1. 数据库表分离，一级/二级管理员独立管理\n2. 密码安全升级，使用 password_hash 加密\n3. 新增云黑等级系统、云白系统\n4. 新增公告管理、在线更新中心\n5. 界面全面美化，紫蓝渐变玻璃态设计\n6. 一键自动更新，从GitHub逐文件对比更新\n\n开源地址：https://github.com/AyMzz-dev/-', UNIX_TIMESTAMP(), 1, 10),
(2, '关于系统开源说明', '本系统采用 AGPL-3.0 开源协议发布。\n\n您可以自由使用、修改和分发本软件，但如果您修改后通过网络提供服务，必须公开修改后的源代码。\n\n严谨用于任何违反中华人民共和国法律法规的用途。\n\n感谢您的使用！', UNIX_TIMESTAMP(), 1, 5);
SQL

    log "数据库迁移完成"
}

# ============================================
# 4. 清理
# ============================================
cleanup() {
    rm -rf "$TMP_DIR"
    log "临时文件已清理"
}

# ============================================
# 主流程
# ============================================
TARGET="${1:-$(pwd)}"

echo ""
echo "========================================"
echo "  小七授权系统 - 一键部署"
echo "  $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"
echo ""

# 检查依赖
for cmd in curl unzip rsync php; do
    if ! command -v $cmd &>/dev/null; then
        warn "缺少 $cmd，请先安装"
    fi
done

download_code
deploy_to "$SRC_DIR" "$TARGET"
run_db_migration "$TARGET"
cleanup

echo ""
echo "========================================"
echo "  ✓ 全部完成！"
echo "  访问后台: $TARGET/Adminx/admin.html"
echo "  默认账号: 小七 / xiaoqida"
echo "========================================"
echo ""
echo "  如果部署后无法登录，请检查:"
echo "  1. PHP 版本 >= 7.0"
echo "  2. PHP Zip 扩展已安装"
echo "  3. function/config.inc.php 数据库配置正确"
echo ""