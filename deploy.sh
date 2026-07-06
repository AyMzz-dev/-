#!/bin/bash
# ============================================
# 小七授权系统 - 一键部署脚本
# 用法: bash deploy.sh [站点名]
# 站点名: auth (默认) | 6666 | all
# ============================================
set -e

GITHUB_REPO="https://github.com/AyMzz-dev/-/archive/refs/heads/master.zip"
TMP_DIR="/tmp/wenquan_deploy_$(date +%s)"
WEB_AUTH="/www/wwwroot/auth.authcnmtx.cn"
WEB_6666="/www/wwwroot/6666.authcnmtx.cn"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
err() { echo -e "${RED}[✗]${NC} $1"; }

# ============================================
# 1. 下载最新代码
# ============================================
download_code() {
    log "正在从 GitHub 下载最新代码..."
    mkdir -p "$TMP_DIR"
    curl -sL "$GITHUB_REPO" -o "$TMP_DIR/master.zip"
    if [ ! -f "$TMP_DIR/master.zip" ] || [ $(stat -c%s "$TMP_DIR/master.zip") -lt 1000 ]; then
        err "下载失败，请检查网络"
        exit 1
    fi
    unzip -qo "$TMP_DIR/master.zip" -d "$TMP_DIR"
    SRC_DIR=$(ls -d "$TMP_DIR"/*/ 2>/dev/null | head -1)
    log "下载完成 → $SRC_DIR"
}

# ============================================
# 2. 部署到站点
# ============================================
deploy_site() {
    local SRC="$1"
    local DST="$2"
    local NAME="$3"

    log "部署到 $NAME ($DST)..."

    # 备份 config
    if [ -f "$DST/function/config.inc.php" ]; then
        cp "$DST/function/config.inc.php" "$TMP_DIR/config_backup_$NAME.php"
        warn "已备份 config.inc.php"
    fi

    # 同步文件（排除敏感文件）
    rsync -av --exclude='config.inc.php' \
              --exclude='install.php' \
              --exclude='.git' \
              --exclude='.gitignore' \
              --exclude='upload/' \
              --exclude='README.md' \
              --exclude='LICENSE' \
              --exclude='CHANGELOG' \
              --exclude='部署清单.md' \
              "$SRC/" "$DST/" 2>&1 | tail -3

    # 恢复 config
    if [ -f "$TMP_DIR/config_backup_$NAME.php" ]; then
        cp "$TMP_DIR/config_backup_$NAME.php" "$DST/function/config.inc.php"
        log "已恢复 config.inc.php"
    fi

    # 设置权限
    chown -R www:www "$DST" 2>/dev/null || true
    chmod -R 755 "$DST" 2>/dev/null || true

    log "部署完成: $NAME"
}

# ============================================
# 3. 数据库迁移
# ============================================
run_db_migration() {
    log "执行数据库迁移..."
    # 从 config 读取数据库信息
    if [ -f "$WEB_AUTH/function/config.inc.php" ]; then
        DB_HOST=$(php -r "include '$WEB_AUTH/function/config.inc.php'; echo \$G['db']['host'] ?? 'localhost';" 2>/dev/null)
        DB_NAME=$(php -r "include '$WEB_AUTH/function/config.inc.php'; echo \$G['db']['name'] ?? 'wenquan';" 2>/dev/null)
        DB_USER=$(php -r "include '$WEB_AUTH/function/config.inc.php'; echo \$G['db']['user'] ?? 'root';" 2>/dev/null)
        DB_PASS=$(php -r "include '$WEB_AUTH/function/config.inc.php'; echo \$G['db']['pass'] ?? '';" 2>/dev/null)

        if [ -n "$DB_NAME" ]; then
            mysql -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" "$DB_NAME" <<'SQL'
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
(1, '温泉PHP授权系统 v12.13 小七二次开发版发布', '欢迎使用小七PHP网络授权系统 v12.13！\n\n本版本由小七二次开发，主要更新内容：\n1. 数据库表分离，一级/二级管理员独立管理\n2. 密码安全升级，使用 password_hash 加密\n3. 新增云黑等级系统、云白系统\n4. 新增公告管理、在线更新中心\n5. 界面全面美化，紫蓝渐变玻璃态设计\n6. 一键自动更新，从GitHub逐文件对比更新\n\n开源地址：https://github.com/AyMzz-dev/-', UNIX_TIMESTAMP(), 1, 10),
(2, '关于系统开源说明', '本系统采用 AGPL-3.0 开源协议发布。\n\n您可以自由使用、修改和分发本软件，但如果您修改后通过网络提供服务，必须公开修改后的源代码。\n\n严谨用于任何违反中华人民共和国法律法规的用途。\n\n感谢您的使用！', UNIX_TIMESTAMP(), 1, 5);
SQL
            log "数据库迁移完成"
        else
            warn "无法读取数据库配置，跳过数据库迁移"
        fi
    else
        warn "config.inc.php 不存在，跳过数据库迁移"
    fi
}

# ============================================
# 4. 清理
# ============================================
cleanup() {
    rm -rf "$TMP_DIR"
    log "清理完成"
}

# ============================================
# 主流程
# ============================================
SITE="${1:-auth}"

echo ""
echo "========================================"
echo "  小七授权系统 - 一键部署"
echo "  $(date '+%Y-%m-%d %H:%M:%S')"
echo "========================================"
echo ""

download_code

case "$SITE" in
    auth)
        deploy_site "$SRC_DIR" "$WEB_AUTH" "auth.authcnmtx.cn"
        run_db_migration
        ;;
    6666)
        deploy_site "$SRC_DIR" "$WEB_6666" "6666.authcnmtx.cn"
        ;;
    all)
        deploy_site "$SRC_DIR" "$WEB_AUTH" "auth.authcnmtx.cn"
        deploy_site "$SRC_DIR" "$WEB_6666" "6666.authcnmtx.cn"
        run_db_migration
        ;;
    *)
        echo "用法: bash deploy.sh [auth|6666|all]"
        cleanup
        exit 1
        ;;
esac

cleanup

echo ""
echo "========================================"
echo "  ✓ 部署完成！"
echo "  auth: http://auth.authcnmtx.cn/Adminx/admin.html"
echo "  6666: http://6666.authcnmtx.cn/skylint@admin2j/admin.html"
echo "========================================"