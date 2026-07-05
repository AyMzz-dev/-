<?php include 'function/function_core.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title>开通代理 - <?php echo $G['config']['sitename'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./static/global.css">
    <script src="https://v.vaptcha.com/v3.js"></script>
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { max-width: 520px; width: 100%; }
        .form-group { position: relative; margin-bottom: 18px; }
        .form-group > i {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%);
            color: #adb5bd; font-size: 1rem; z-index: 2;
            pointer-events: none;
        }
        .form-group .form-control-custom { padding-left: 48px; }
        .form-group select.form-control-custom { padding-left: 48px; }
        .radio-group { margin-bottom: 18px; }
        .radio-group-label { display: block; margin-bottom: 10px; font-weight: 500; font-size: 0.95rem; color: #212529; }
        .radio-group-label i { color: #6a11cb; margin-right: 6px; }
        .radio-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .radio-item {
            flex: 1; min-width: 100px; text-align: center;
            padding: 10px 8px; border: 2px solid #e1e5eb; border-radius: 10px;
            cursor: pointer; transition: all 0.3s ease; font-size: 0.9rem;
            background: rgba(255,255,255,0.8);
        }
        .radio-item:hover { border-color: #6a11cb; background: rgba(106,17,203,0.04); }
        .radio-item input[type="radio"] { display: none; }
        .radio-item input[type="radio"]:checked + span { font-weight: 600; }
        .radio-item:has(input:checked) {
            border-color: #6a11cb; background: rgba(106,17,203,0.06);
            box-shadow: 0 0 0 3px rgba(106,17,203,0.08);
        }
        .radio-item i { display: block; margin-bottom: 4px; font-size: 1.2rem; color: #6a11cb; }
        .login-card-footer { padding: 16px 28px; text-align: center; background: #f8f9fa; border-top: 1px solid rgba(0,0,0,0.04); }
        .login-card-footer a { color: #6a11cb; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.3s; }
        .login-card-footer a:hover { color: #2575fc; }
        .login-card-footer a i { margin-right: 4px; }
    </style>
</head>

<body class="login-page-bg">
    <div class="login-card animate-scaleIn">
        <div class="login-card-header">
            <h2><i class="fas fa-user-plus"></i> 开通代理</h2>
            <p><?php echo $G['config']['sitename'] ?></p>
        </div>
        <div class="login-card-body">
            <div class="form-group">
                <i class="fas fa-layer-group"></i>
                <select class="form-control-custom" id="levellist">
                    <option value="0">选择一个级别</option>
                </select>
            </div>

            <div class="form-group">
                <i class="fas fa-user"></i>
                <input class="form-control-custom" id="kt_user" type="text" placeholder="开通的代理账号">
            </div>

            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input class="form-control-custom" id="kt_pass" type="text" placeholder="开通的账号的密码">
            </div>

            <div class="form-group">
                <i class="fab fa-qq"></i>
                <input class="form-control-custom" id="kt_qq" type="text" placeholder="您的QQ账号">
            </div>

            <div class="radio-group">
                <span class="radio-group-label"><i class="fas fa-credit-card"></i> 支付方式：</span>
                <div class="radio-row">
                    <label class="radio-item">
                        <input type="radio" name="zffs" value="zxzf" checked onclick="$('#show_kmcz').hide();$('#show_zxzf').show();">
                        <i class="fas fa-qrcode"></i>
                        <span>在线支付</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="zffs" value="czkm" onclick="$('#show_kmcz').show();$('#show_zxzf').hide();">
                        <i class="fas fa-key"></i>
                        <span>充值卡密</span>
                    </label>
                </div>
            </div>

            <div class="form-group" id="show_kmcz" style="display:none;">
                <i class="fas fa-ticket-alt"></i>
                <input class="form-control-custom" id="kt_key" type="text" placeholder="请输入卡密">
            </div>

            <div class="radio-group" id="show_zxzf">
                <span class="radio-group-label"><i class="fas fa-wallet"></i> 请选择在线支付方式：</span>
                <div class="radio-row">
                    <label class="radio-item">
                        <input type="radio" name="zxzffs" value="zfb" checked>
                        <i class="fab fa-alipay"></i>
                        <span>支付宝</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="zxzffs" value="wx">
                        <i class="fab fa-weixin"></i>
                        <span>微信支付</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="zxzffs" value="qq">
                        <i class="fab fa-qq"></i>
                        <span>QQ钱包</span>
                    </label>
                </div>
            </div>

            <div class="form-group" id="vaptchaContainer" style="padding:0; margin-top: 8px;"></div>

            <button type="button" class="btn btn-primary btn-block" onclick="buyagent();">
                <i class="fas fa-rocket"></i> 立即开通
            </button>
        </div>
        <div class="login-card-footer">
            <a href="./"><i class="fas fa-home"></i> 返回首页</a>
        </div>
    </div>

<script src="./static/jquery-3.3.1.js"></script>
<script type="text/javascript" src="./static/frame/layui/layui.js"></script>
<script>
    layui.use(['layer'], function () {
        $('#show_kmcz').hide();
        window.layer = layui.layer;
        var loading = layer.load();
        $.ajax({
            url: 'ajax.php?mod=levellist',
            type: 'POST',
            dataType: 'html',
            data: '',
            success: function (data) {
                layer.close(loading);
                if (data === '' || data === null) {
                    layer.open({
                        type: 1
                        ,
                        title: false
                        ,
                        closeBtn: false
                        ,
                        area: '300px;'
                        ,
                        shade: 0.8
                        ,
                        id: 'sitenotice'
                        ,
                        resize: false
                        ,
                        btn: ['确定']
                        ,
                        btnAlign: 'c'
                        ,
                        moveType: 1
                        ,
                        content: '<div style="padding: 20px; line-height: 22px; background: linear-gradient(135deg, #6a11cb, #2575fc); color: #fff; font-weight: 300; border-radius: 12px;">站长没有添加任何可以购买的代理级别</div>'
                        ,
                        success: function (layero) {
                        }
                    });
                } else {
                    $('#levellist').html(data);
                }
            },
            error: function (data) {
                layer.close(loading);
                layer.msg('请求失败' + data);
            }
        });
        $.ajax({
            url: './function/GetVerification.php',
            type: 'GET',
            dataType: 'html',
            success: function (data) {
                $('#vaptchaContainer').html(data);
            },
            error: function (data) {
                layer.msg('[' + data.status + ']' + data.statusText);
            }
        });

    });

    function buyagent() {
        var lid = $('#levellist').val();
        var user = $('#kt_user').val();
        var pass = $('#kt_pass').val();
        var qq = $('#kt_qq').val();
        var zffs = $('input:radio[name="zffs"]:checked').val();
        var zxzffs = $('input:radio[name="zxzffs"]:checked').val();
        var kt_key = $("#kt_key").val();
        var loading = layer.load();
        var token ='';
        if (typeof vaptchaObj != 'undefined'){
            token = vaptchaObj.getToken();
            vaptchaObj.reset();
        }
        $.ajax({
            url: 'ajax.php?mod=buy_agent',
            type: 'POST',
            dataType: 'json',
            data: 'lid=' + lid + '&username=' + encodeURI(user) + '&password=' + encodeURI(pass) + '&qq=' + qq + '&zffs=' + zffs + '&zxzffs=' + zxzffs + '&kt_key=' + kt_key + '&token=' + token,
            success: function (data) {
                layer.close(loading);
                if (data.code === '1') {
                    layer.confirm(data.info, {
                        btn: ['立即付款', '关闭'] //按钮
                    }, function () {
                        window.open('payment.php?tradeno=' + data.tradeno);
                        layer.closeAll();
                        layer.confirm('请在新打开的窗口中进行付款！', {
                            btn: ['已付款', '关闭'] //按钮
                        }, function () {
                            window.open('payresult.php?tradeno=' + data.tradeno);
                        });
                    });
                } else {
                    layer.msg(data.msg);
                }
            },
            error: function (data) {
                layer.close(loading);
                layer.msg('请求失败' + data);
            }
        });

    }
</script>

</body>
</html>