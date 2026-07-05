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
    <title>更换授权 - <?php echo $G['config']['sitename'] ?></title>
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
        #changecontent { margin-bottom: 8px; }
        #changecontent .form-group,
        #changecontent .mdl-textfield { margin-bottom: 18px; }
        #changecontent input,
        #changecontent select { margin-bottom: 0; }
        .btn-secondary-custom {
            background: #e9ecef; color: #212529; border: none;
            border-radius: 12px; padding: 14px; font-size: 1rem;
            font-weight: 600; cursor: pointer; width: 100%;
            transition: all 0.3s ease; margin-bottom: 18px;
        }
        .btn-secondary-custom:hover { background: #dde0e3; transform: translateY(-1px); }
        .btn-secondary-custom:disabled { opacity: 0.6; cursor: not-allowed; }
        .login-card-footer { padding: 16px 28px; text-align: center; background: #f8f9fa; border-top: 1px solid rgba(0,0,0,0.04); }
        .login-card-footer a { color: #6a11cb; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: color 0.3s; }
        .login-card-footer a:hover { color: #2575fc; }
        .login-card-footer a i { margin-right: 4px; }
    </style>
</head>

<body class="login-page-bg">
    <div class="login-card animate-scaleIn">
        <div class="login-card-header">
            <h2><i class="fas fa-exchange-alt"></i> 更换授权</h2>
            <p><?php echo $G['config']['sitename'] ?></p>
        </div>
        <div class="login-card-body">
            <div class="form-group">
                <i class="fas fa-cubes"></i>
                <select class="form-control-custom mdl-textfield__input" style="width:100%" id="applist" onchange="changegetform()">
                    <option value="0">选择应用</option>
                </select>
            </div>

            <div id="changecontent"></div>

            <div class="form-group">
                <i class="fas fa-envelope"></i>
                <input class="form-control-custom mdl-textfield__input" id="cg_mail" type="text" placeholder="请输入您授权时候的邮箱">
            </div>

            <div class="form-group" id="vaptchaContainer" style="padding:0; margin-top: 8px;"></div>

            <button type="button" class="btn-secondary-custom" onclick="getvercode();" id="sendcode">
                <i class="fas fa-paper-plane"></i> 获取验证码
            </button>

            <div class="form-group">
                <i class="fas fa-shield-alt"></i>
                <input class="form-control-custom mdl-textfield__input" id="cg_vercode" type="text" placeholder="邮箱验证码">
            </div>

            <button type="button" class="btn btn-primary btn-block" onclick="postchange();">
                <i class="fas fa-check-circle"></i> 确认换绑
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
            url: 'ajax.php?mod=buy_applist',
            type: 'POST',
            dataType: 'html',
            data: '',
            success: function (data) {
                layer.close(loading);
                if (data === '' || data === null) {
                    layer.open({
                        type: 1,title: false,closeBtn: false,area: '300px;',shade: 0.8,id: 'sitenotice',resize: false,btn: ['确定'],btnAlign: 'c',moveType: 1
                        ,content: '<div style="padding: 20px; line-height: 22px; background: linear-gradient(135deg, #6a11cb, #2575fc); color: #fff; font-weight: 300; border-radius: 12px;">站长没有添加任何应用</div>'
                    });
                } else {
                    $('#applist').html(data);
                    if (getQueryVariable('appid') !== false) {
                        $("#applist").val(getQueryVariable('appid'));
                    }
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

    function changegetform() {
        var loading = layer.load();
        var appid = $("#applist").val();
        $.ajax({
            url: 'ajax.php?mod=changegetform',
            type: 'POST',
            dataType: 'html',
            data: 'appid='+appid,
            success: function (data) {
                $('#changecontent').html(data);
                layer.close(loading);
            },
            error: function (data) {
                layer.close(loading);
                layer.msg('[' + data.status + ']' + data.statusText);
            }
        });
    }
    function getvercode() {
        var loading = layer.load();
        var putArr = document.getElementsByClassName("mdl-textfield__input");
        var postvalue = '';
        for (var i=0; i< putArr.length; i++){
            postvalue = postvalue + putArr[i].id+'='+ encodeURIComponent(putArr[i].value)+'&';
        }
        var token='';
        if (typeof vaptchaObj != 'undefined'){
            token = vaptchaObj.getToken();
            vaptchaObj.reset();
        }
        $.ajax({
            url: 'ajax.php?mod=ChangeGetVerCode',
            type: 'POST',
            dataType: 'json',
            data: postvalue+'token='+token,
            success: function (data) {
                if (data.code === 1){
                    $('#sendcode').html('60');
                    $('#sendcode').attr("disabled",true);
                    layer.msg('邮件发送成功，请注意检查垃圾箱和收件箱');
                    window.interval = setInterval(function () {
                        var i =parseInt($('#sendcode').html()) -1;
                        $('#sendcode').html(i.toString());
                        if (i === -1){
                            clearInterval(interval);
                            $('#sendcode').html('<i class="fas fa-paper-plane"></i> 获取验证码');
                            $('#sendcode').removeAttr("disabled");
                        }

                    }, 1000);
                }else{
                    layer.alert(data.msg);
                }
                layer.close(loading);

            },
            error: function (data) {
                layer.close(loading);
                layer.msg('[' + data.status + ']' + data.statusText);
            }
        });
    }

    function postchange() {
        var loading = layer.load();
        var putArr = document.getElementsByClassName("mdl-textfield__input");
        var postvalue = '';
        for (var i=0; i< putArr.length; i++){
            postvalue = postvalue + putArr[i].id+'='+ encodeURIComponent(putArr[i].value)+'&';
        }

        $.ajax({
            url: 'ajax.php?mod=postchange',
            type: 'POST',
            dataType: 'html',
            data: postvalue+'token=',
            success: function (data) {
                layer.alert(data);
                layer.close(loading);
            },
            error: function (data) {
                layer.close(loading);
                layer.msg('[' + data.status + ']' + data.statusText);
            }
        });
    }



    function getQueryVariable(variable) {
        var query = window.location.search.substring(1);
        var vars = query.split("&");
        for (var i = 0; i < vars.length; i++) {
            var pair = vars[i].split("=");
            if (pair[0] == variable) {
                return pair[1];
            }
        }
        return (false);
    }
</script>

</body>
</html>