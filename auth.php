<?php include 'function/function_core.php';?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <title>自助授权 - <?php echo $G['config']['sitename'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./static/global.css">
    <script src="https://v.vaptcha.com/v3.js"></script>
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-card { max-width: 480px; width: 100%; }
        .form-group { position: relative; margin-bottom: 18px; }
        .form-group > i {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%);
            color: #adb5bd; font-size: 1rem; z-index: 2;
            pointer-events: none;
        }
        .form-group .form-control-custom { padding-left: 48px; }
        .login-card-footer { padding: 16px 28px; text-align: center; background: #f8f9fa; border-top: 1px solid rgba(0,0,0,0.04); }
        .login-card-footer a { color: #6a11cb; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.3s; }
        .login-card-footer a:hover { color: #2575fc; }
        .login-card-footer a i { margin-right: 4px; }
    </style>
</head>

<body class="login-page-bg">
    <div class="login-card animate-scaleIn">
        <div class="login-card-header">
            <h2><i class="fas fa-magic"></i> 自助授权</h2>
            <p><?php echo $G['config']['sitename'] ?></p>
        </div>
        <div class="login-card-body">
            <div class="form-group">
                <i class="fas fa-gift"></i>
                <input class="form-control-custom" id="kami" type="text" placeholder="请输入您的套餐卡密">
            </div>

            <div class="form-group" id="vaptchaContainer" style="padding:0; margin-top: 8px;"></div>

            <button type="button" class="btn btn-primary btn-block" onclick="queryauth();">
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
        window.layer = layui.layer;
        $.ajax({
            url: './function/GetVerification.php',
            type: 'GET',
            dataType: 'html',
            success: function(data){
                $('#vaptchaContainer').html(data);
            },
            error: function(data){
                layer.msg('['+data.status+']'+data.statusText);
            }
        });
    });


    function queryauth() {
        var kami = $('#kami').val();
        var token ='';
        if (typeof vaptchaObj != 'undefined'){
            token = vaptchaObj.getToken();
            vaptchaObj.reset();
        }
        layer.open({
            type: 2,
            title: '使用卡密'+kami,
            shadeClose: true,
            shade: 0.8,
            area: ['90%', '90%'],
            content: 'ajax.php?mod=FieldKamiOpen_First&kami=' + kami + '&token='+token
        });
    }
</script>

</body>
</html>