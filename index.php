<?php include 'function/function_core.php';
if (!$G['config']['xtsy']) die(); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>授权在线开通 - <?php echo $G['config']['sitename'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./static/global.css">
    <link rel="stylesheet" href="./static/frame/layui/css/layui.css">
<style>
    :root {
        --primary: #6a11cb;
        --secondary: #2575fc;
        --success: #00b09b;
        --danger: #ff416c;
        --light: #f8f9fa;
        --dark: #212529;
        --gray: #6c757d;
        --card-bg: rgba(255, 255, 255, 0.92);
        --shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
    }
    body {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        min-height: 100vh;
        padding: 20px;
        color: var(--dark);
        line-height: 1.6;
    }
    .container { width: 100%; max-width: 1400px; margin: 0 auto; }
    /* 头部 */
    .header {
        text-align: center;
        margin-bottom: 40px;
        padding: 20px;
        color: white;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }
    .header h1 { font-size: 2.8rem; font-weight: 700; margin-bottom: 10px; }
    .header p { font-size: 1.2rem; opacity: 0.9; max-width: 700px; margin: 0 auto; }
    /* 导航 */
    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 15px 30px;
        margin-bottom: 30px;
        box-shadow: var(--shadow);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .logo {
        font-size: 1.8rem;
        font-weight: 700;
        color: white;
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }
    .logo i { font-size: 2rem; }
    .nav-links { display: flex; gap: 25px; }
    .nav-links a {
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        font-weight: 500;
        padding: 8px 15px;
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    .nav-links a:hover { background: rgba(255, 255, 255, 0.2); color: white; }
    /* 主内容 */
    .main-content { display: grid; grid-template-columns: 1fr 350px; gap: 30px; }
    /* 应用选择卡片 */
    .app-selector {
        background: var(--card-bg);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: transform 0.3s ease;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    .app-selector:hover { transform: translateY(-5px); }
    .card-header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 20px;
        text-align: center;
    }
    .card-header h2 { font-size: 1.5rem; margin: 0; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 10px; }
    .card-body { padding: 25px; }
    .form-group { margin-bottom: 25px; }
    .form-group label { display: block; margin-bottom: 10px; font-weight: 500; color: var(--dark); font-size: 1rem; }
    .form-control {
        width: 100%;
        padding: 14px 16px;
        border: 2px solid #e1e5eb;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
        font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
        color: var(--dark);
        outline: none;
    }
    .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1); }
    select.form-control {
        appearance: none;
        -webkit-appearance: none;
        background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1.2rem;
        cursor: pointer;
    }
    /* 购买指南 */
    .buy-options {
        background: var(--card-bg);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: var(--shadow);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        height: fit-content;
    }
    .buy-options-header {
        background: linear-gradient(135deg, #00b09b, #96c93d);
        color: white;
        padding: 20px;
        text-align: center;
    }
    .options-container { padding: 25px; }
    .options-placeholder { text-align: center; color: var(--gray); padding: 30px 0; }
    .options-placeholder i { font-size: 3rem; margin-bottom: 15px; opacity: 0.7; }
    /* 应用卡片样式 */
    .app-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    .app-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    .app-card-header {
        background: linear-gradient(135deg, #3498db, #2c3e50);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .app-name { font-size: 1.3rem; font-weight: 600; }
    .app-price { font-size: 1.5rem; font-weight: 700; }
    .app-card-body { padding: 20px; }
    .app-features { list-style: none; margin-bottom: 20px; }
    .app-features li { padding: 8px 0; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #eee; }
    .app-features li i { color: var(--success); }
    .app-actions { display: flex; justify-content: space-between; gap: 10px; }
    .btn {
        display: inline-block;
        padding: 12px 24px;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        flex: 1;
        font-family: 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
        text-decoration: none;
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        box-shadow: 0 4px 15px rgba(106, 17, 203, 0.3);
    }
    .btn-primary:hover { background: linear-gradient(135deg, #5a0db5, #1c68fa); box-shadow: 0 6px 20px rgba(106, 17, 203, 0.4); transform: translateY(-2px); }
    .btn-secondary { background: #e9ecef; color: var(--dark); }
    .btn-secondary:hover { background: #dde0e3; }
    .btn-info { background: linear-gradient(135deg, #00b09b, #96c93d); color: white; }
    .btn-info:hover { background: linear-gradient(135deg, #009a87, #7cb028); transform: translateY(-2px); }
    /* 页脚 */
    .footer { text-align: center; margin-top: 40px; color: rgba(255, 255, 255, 0.8); padding: 20px; font-size: 0.9rem; }
    .footer a { color: white; text-decoration: none; }
    .footer a:hover { text-decoration: underline; }
    /* 动画 */
    @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .app-selector, .buy-options { animation: fadeIn 0.6s ease forwards; }
    /* 响应式 */
    @media (max-width: 992px) {
        .main-content { grid-template-columns: 1fr; }
        .nav-links { display: none; }
        .header h1 { font-size: 2.2rem; }
    }
    @media (max-width: 768px) {
        .app-actions { flex-direction: column; }
        .header h1 { font-size: 1.8rem; }
        body { padding: 12px; }
    }
</style>
</head>
<body>
<div class="container">
    <!-- 导航栏 -->
    <div class="navbar">
        <a href="./" class="logo">
            <i class="fas fa-key"></i>
            <span><?php echo $G['config']['sitename'] ?></span>
        </a>
        <div class="nav-links">
            <?php echo $G['config']['mainnav'] ?>
        </div>
    </div>

    <!-- 头部 -->
    <div class="header">
        <h1><i class="fas fa-shopping-cart"></i> 授权在线开通</h1>
        <p>安全便捷的授权购买平台，一键开通您所需的应用授权</p>
    </div>

    <!-- 公告栏 -->
    <?php
    $notices = $db->select_limit_row('sq_notice','*',0,5,array('status'=>1),'AND','ORDER BY sort DESC, ID DESC');
    if (!empty($notices)):
    ?>
    <div class="notice-bar" style="background:rgba(255,255,255,0.15);backdrop-filter:blur(15px);-webkit-backdrop-filter:blur(15px);border-radius:16px;padding:15px 25px;margin-bottom:25px;border:1px solid rgba(255,255,255,0.2);">
        <div style="color:white;font-weight:700;margin-bottom:10px;font-size:1.1rem;"><i class="fas fa-bullhorn"></i> 系统公告</div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <?php foreach($notices as $notice): ?>
            <span style="background:rgba(255,255,255,0.2);color:white;padding:6px 15px;border-radius:20px;font-size:0.9rem;cursor:pointer;" onclick="layer.open({type:1,title:'<?php echo htmlspecialchars($notice['title'],ENT_QUOTES) ?>',area:['500px','auto'],content:'<div style=\'padding:20px;line-height:1.8;\'><?php echo nl2br(htmlspecialchars($notice['content'],ENT_QUOTES)) ?></div>'});">
                <i class="fas fa-volume-up" style="margin-right:5px;"></i><?php echo htmlspecialchars($notice['title'],ENT_QUOTES) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 主要内容区 -->
    <div class="main-content">
        <!-- 左侧：应用选择 -->
        <div class="app-selector">
            <div class="card-header">
                <h2><i class="fas fa-cube"></i> 选择应用</h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label><i class="fas fa-list"></i> 请选择需要购买的应用</label>
                    <select class="form-control" id="applist" onchange="loadbuylist();">
                        <option value="0">选择应用...</option>
                    </select>
                </div>
                <div id="buylist">
                    <div class="options-placeholder">
                        <i class="fas fa-box-open"></i>
                        <h3>请选择应用查看购买选项</h3>
                        <p>选择上方应用后，这里将显示可用的购买方案</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 右侧：购买指南 -->
        <div class="buy-options">
            <div class="buy-options-header">
                <h2><i class="fas fa-shopping-bag"></i> 购买指南</h2>
            </div>
            <div class="options-container">
                <div class="guide-item">
                    <h3><i class="fas fa-circle" style="font-size:6px;color:#6a11cb;"></i> 选择应用</h3>
                    <p>从下拉菜单中选择您需要购买授权的应用</p>
                </div>
                <div class="guide-item">
                    <h3><i class="fas fa-circle" style="font-size:6px;color:#6a11cb;"></i> 查看方案</h3>
                    <p>查看不同时长的授权方案及对应价格</p>
                </div>
                <div class="guide-item">
                    <h3><i class="fas fa-circle" style="font-size:6px;color:#6a11cb;"></i> 立即购买</h3>
                    <p>选择适合的方案并完成支付流程</p>
                </div>
                <div class="guide-item">
                    <h3><i class="fas fa-circle" style="font-size:6px;color:#6a11cb;"></i> 获取授权</h3>
                    <p>支付成功后，系统将自动发送授权信息到您的邮箱</p>
                </div>
                <div class="support-info">
                    <h3><i class="fas fa-headset"></i> 客户支持</h3>
                    <p>客服QQ：<a href="http://wpa.qq.com/msgrd?v=3&uin=<?php echo $G['config']['adminqq'] ?>&site=qq&menu=yes" target="_blank"><?php echo $G['config']['adminqq'] ?></a></p>
                    <p>客服邮箱：<a href="mailto:<?php echo $G['config']['adminmail'] ?>"><?php echo $G['config']['adminmail'] ?></a></p>
                    <p>工作时间：9:00 - 22:00（全年无休）</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 页脚 -->
    <div class="footer">
        <p>© <?php echo date('Y') ?> <?php echo $G['config']['sitename'] ?></p>
        <p>安全可靠的授权购买平台 | 专业团队维护 | 7×24小时技术支持</p>
        <?php if (!empty($G['config']['beian'])) echo '<p><a href="http://www.miitbeian.gov.cn" target="_blank">'.$G['config']['beian'].'</a></p>' ?>
    </div>
</div>

<script src="./static/jquery-3.3.1.js"></script>
<script src="./static/frame/layui/layui.js"></script>
<script>
    layui.use(['layer'], function () {
        window.layer = layui.layer;
        var loading = layer.load();

        $.ajax({
            url: 'ajax.php?mod=buy_applist',
            type: 'POST',
            dataType: 'html',
            success: function (data) {
                layer.close(loading);
                if (data === '' || data === null) {
                    layer.open({
                        type: 1, title: false, closeBtn: false, area: '300px;',
                        shade: 0.8, id: 'sitenotice', resize: false,
                        btn: ['确定'], btnAlign: 'c', moveType: 1,
                        content: '<div style="padding: 20px; line-height: 22px; background: linear-gradient(135deg, #6a11cb, #2575fc); color: #fff; font-weight: 300; border-radius: 12px;">站长没有添加任何应用</div>'
                    });
                } else {
                    $('#applist').html(data);
                    if (getQueryVariable('appid') !== false) {
                        $("#applist").val(getQueryVariable('appid'));
                        loadbuylist();
                    }
                }
            },
            error: function (data) { layer.close(loading); layer.msg('请求失败' + data); }
        });
    });

    function loadbuylist() {
        var appId = $("#applist").val();
        if (appId == "0") {
            $('#buylist').html('<div class="options-placeholder"><i class="fas fa-box-open"></i><h3>请选择应用查看购买选项</h3><p>选择上方应用后，这里将显示可用的购买方案</p></div>');
            return;
        }
        var loading = layer.load();
        $.ajax({
            url: 'ajax.php?mod=loadbuylist',
            type: 'POST',
            dataType: 'html',
            data: 'loadbuylist=' + appId,
            success: function (data) {
                layer.close(loading);
                if (data === '' || data === null) {
                    layer.msg('没有数据');
                } else {
                    $('#buylist').html(data);
                }
            },
            error: function (data) { layer.close(loading); layer.msg('请求失败' + data); }
        });
    }

    function buy(id) {
        layer.open({
            type: 2,
            title: '开通授权(商品ID:' + id + ')',
            shadeClose: true,
            shade: 0.8,
            area: ['90%', '90%'],
            content: 'ajax.php?mod=buy_first&fid=' + id
        });
    }

    function buy_submit(id, type) {
        var loading = layer.load();
        if (type === 1) {
            var kt_user = $("#kt_user").val();
            var kt_pass = $("#kt_pass").val();
            var kt_varinfo = '&kt_user=' + encodeURI(kt_user) + '&kt_pass=' + kt_pass;
        } else if (type === 2) {
            var kt_kami = $("#kt_user").val();
            var kt_varinfo = '&kt_user=' + encodeURI(kt_kami);
            var kt_kaminum = $("#kt_keysnum").val();
        } else if (type === 3) {
            var kt_robotqq = $("#kt_robotqq").val();
            var kt_mac = $("#kt_mac").val();
            var kt_ip = $("#kt_ip").val();
            var kt_varinfo = '&kt_robotqq=' + kt_robotqq + '&kt_mac=' + kt_mac + '&kt_ip=' + encodeURI(kt_ip);
        }
        var kt_num = $("#kt_num").val();
        var kt_adminqq = $("#kt_adminqq").val();
        var kt_mail = $("#kt_mail").val();
        var zffs = $('input:radio[name="zffs"]:checked').val();
        var zxzffs = $('input:radio[name="zxzffs"]:checked').val();
        var kt_key = $("#kt_key").val();

        $.ajax({
            url: 'ajax.php?mod=buy_submit',
            type: 'POST',
            dataType: 'json',
            data: 'id=' + id + kt_varinfo + '&kt_num=' + kt_num + '&kt_mail=' + kt_mail + '&zffs=' + zffs + '&zxzffs=' + zxzffs + '&kt_key=' + kt_key + '&type=' + type + '&kt_adminqq=' + kt_adminqq,
            success: function (data) {
                layer.close(loading);
                if (data.code === '1') {
                    layer.confirm(data.info, {
                        btn: ['立即付款', '关闭']
                    }, function () {
                        window.open('payment.php?tradeno=' + data.tradeno);
                        layer.closeAll();
                        layer.confirm('请在新打开的窗口中进行付款！', {
                            btn: ['已付款', '关闭']
                        }, function () {
                            window.open('payresult.php?tradeno=' + data.tradeno);
                        });
                    });
                } else {
                    layer.msg(data.msg);
                }
            },
            error: function (data) { layer.close(loading); layer.msg('请求失败' + data); }
        });
    }

    function info(id) {
        layer.open({
            type: 2,
            title: '应用详情(应用ID:' + id + ')',
            shadeClose: true,
            shade: 0.8,
            area: ['90%', '90%'],
            content: 'ajax.php?mod=introduce&appid=' + id
        });
    }

    function getQueryVariable(variable) {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split("=");
            if (pair[0] == variable) { return pair[1]; }
        }
        return (false);
    }
</script>
</body>
</html>