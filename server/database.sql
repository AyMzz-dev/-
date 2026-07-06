-- ============================================
-- 小七授权服务端 - 数据库结构
-- 用于易语言服务端
-- ============================================

CREATE DATABASE IF NOT EXISTS `auth_server` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `auth_server`;

-- 授权站点表
CREATE TABLE IF NOT EXISTS `auth_sites` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `domain` VARCHAR(255) NOT NULL COMMENT '域名',
    `sitekey` VARCHAR(64) NOT NULL COMMENT '站点密钥',
    `status` INT DEFAULT 1 COMMENT '1=正常 0=禁用',
    `note` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `create_time` INT NOT NULL COMMENT '创建时间戳',
    `last_check_time` INT DEFAULT 0 COMMENT '最后验证时间',
    UNIQUE KEY `idx_sitekey` (`sitekey`),
    KEY `idx_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='授权站点表';

-- 授权日志表
CREATE TABLE IF NOT EXISTS `auth_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sitekey` VARCHAR(64) NOT NULL,
    `domain` VARCHAR(255) NOT NULL,
    `action` VARCHAR(32) NOT NULL COMMENT 'checkauth/sendmail',
    `ip` VARCHAR(45) NOT NULL,
    `result` INT DEFAULT 0 COMMENT '返回的code',
    `create_time` INT NOT NULL,
    KEY `idx_sitekey` (`sitekey`),
    KEY `idx_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='授权日志表';