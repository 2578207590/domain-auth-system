<?php
/**
 * 日志去重优化 - 数据库索引
 * 为 logs 表添加索引，加速每日去重查询
 */
if (!file_exists('config.php')) die('系统未安装');
require 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PWD, DB_NAME);
if ($conn->connect_error) die('数据库连接失败');
$conn->query("ALTER TABLE logs ADD INDEX idx_daily (action(32),content(64),create_time)");
echo "✅ 日志表索引已添加\n";
$conn->close();
