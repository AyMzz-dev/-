<?php
require '../function/function_core.php';
if ($_GET['mod'] === 'login'){
    if (empty($_POST['username'])){
        die(json_encode(array('code'=>-1,'msg'=>'请输入您的用户名')));
    }
    if (empty($_POST['password'])) {
        die(json_encode(array('code' => -2, 'msg' => '请输入您的密码')));
    }
    include '../function/VerificationCode.class.php';
    $verification = Verification::check($_POST['token']);
    if ($verification !== true) {
        die(json_encode(array('code' => '-88', 'msg' => '请先进行人机验证')));
    }
    // 密码验证：先尝试 password_verify，再兼容旧版 md5
    $admin_info = $db->select_first_row('sq_admin_2','*',array('username'=>$_POST['username']),'AND');
    if (!$admin_info){
        $db->posterror('管理员登录尝试失败，账号['.$_POST['username'].']不存在','success');
        die(json_encode(array('code' => -3, 'msg' => '输入的账号密码错误！')));
    }
    $login_ok = false;
    if (verify_password($_POST['password'], $admin_info['password'])){
        $login_ok = true;
    }elseif (strlen($admin_info['password']) === 32 && $admin_info['password'] === md5($_POST['password'])){
        // 旧版 md5 密码兼容，自动升级为 password_hash
        $db->update('sq_admin_2',array('username'=>$_POST['username']),'AND',array('password'=>hash_password($_POST['password'])));
        $login_ok = true;
    }
    if (!$login_ok){
        $db->posterror('管理员登录尝试失败，账号['.$_POST['username'].']密码错误','success');
        die(json_encode(array('code' => -3, 'msg' => '输入的账号密码错误！')));
    }
    session_regenerate_id(true);
    $_SESSION['admin_username'] = $_POST['username'];
    $_SESSION['admin_id'] = $admin_info['ID'];
    $_SESSION['admin_qq'] = $admin_info['qq'];
    $_SESSION['admin_type'] = 2;
    $_SESSION['admin_HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
    $db->update('sq_admin_2',array('username'=>$_SESSION['admin_username']),'AND',array('loginip'=>get_real_ip(),'logintime'=>time()));
    $db->posterror('管理员 '.$_POST['username'].'使用密码登录后台成功','success');
    die(json_encode(array('code' => '1', 'msg' => '登陆成功，正在跳转！')));
} else if($_GET['mod'] === 'checklogin'){
    if (empty($_SESSION['admin_username']) || empty($_SESSION['admin_id'])){
        die(json_encode(array('code'=>-1)));
    }
    if (!$result = $db->select_first_row('sq_admin_2','*',array('ID'=>$_SESSION['admin_id'],'username'=>$_SESSION['admin_username']),'AND')){
        die(json_encode(array('code'=>-2)));
    }
    if ($_SERVER['HTTP_USER_AGENT'] !== $_SESSION['admin_HTTP_USER_AGENT']){
        die(json_encode(array('code'=>-3)));
    }else{
        die(json_encode(array('code'=>1,'username'=>$_SESSION['admin_username'],'adminqq'=>$_SESSION['admin_qq'])));
    }

} else{
    if (empty($_SESSION['admin_username']) || empty($_SESSION['admin_id'])){
        echo '没有登录:code:-1';
        header('Location: login.html');
        die();
    }
    if (!$result = $db->select_first_row('sq_admin_2','*',array('ID'=>$_SESSION['admin_id'],'username'=>$_SESSION['admin_username']),'AND')){
        echo '没有登录:code:-2';
        header('Location: login.html');
        die();
    }
    if ($_SERVER['HTTP_USER_AGENT'] !== $_SESSION['admin_HTTP_USER_AGENT']){
        echo '没有登录:code:-3';
        header('Location: login.html');
        die();
    }
}
switch ($_GET['mod']){
    case 'getsysinfo':
        $fanhuishuju['appnum'] = $db->select_count_row('sq_apps');
        $fanhuishuju['sysver'] = $G['siteinfo']['ver'];
        $fanhuishuju['czkeynum'] = $db->select_count_row('sq_key');
        $fanhuishuju['tckeynum'] = $db->select_count_row('sq_fidkey');
        $fanhuishuju['agentnum'] = $db->select_count_row('sq_agent');
        $fanhuishuju['tradenum'] = $db->select_count_row('sq_trade');
        die(json_encode($fanhuishuju));
        break;
    case 'getuserlist':
        if (empty($_GET['appid'])){
            die(json_encode(array('code'=>'-2','msg'=>'请先选择一个应用(如果应用过多，可点击上方下拉选择框后，输入关键词可快速查找应用)')));
        }
        //$where['appid'] = $_GET['appid'];
        if (!empty($_GET['search'])){
            $where['username'] = $where['password'] = $where['mac'] = $where['rip'] = $where['lip'] = $where['uqq'] = $where['mail'] = $where['balance'] = $where['rqq'] = $_GET['search'];
            //$where['uqq'] = intval($where['uqq'])
            if (!is_numeric($where['uqq'])){
                unset($where['uqq']);
            }
            if (!is_numeric($where['rqq'])){
                unset($where['rqq']);
            }
            if (!is_numeric($where['balance'])){
                unset($where['balance']);
            }
            $whereinfo = 'appid='.intval($_GET['appid']).' AND ('. $db->wheretosql($where,'OR').')';
        }else{
            $whereinfo = 'appid='.intval($_GET['appid']);
        }
        //echo $whereinfo;
        $backinfo['code'] = 0;
        $backinfo['count'] = $db->select_count_row('sq_user',$whereinfo,'AND');
        include "../function/function_app.php";
        $appinfo = app_idgetinfo($_GET['appid']);
        if(!$arr = $db->select_limit_row('sq_user','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], $whereinfo, 'AND','ORDER BY ID DESC')){
            //$backinfo['code'] = -1;
            $backinfo['msg'] = $db->geterror();
            die(json_encode($backinfo));
        }

        $backinfo['msg'] = '';

        foreach ($arr as $value){
            $newinfo = array();
            $newinfo['id'] = $value['ID'];
            $newinfo['username'] = $value['username'];
            $newinfo['password'] = $value['password'];
            $newinfo['mac'] = $value['mac'];
            $newinfo['lip'] = $value['lip'];
            $newinfo['rip'] = $value['rip'];
            $newinfo['uqq'] = $value['uqq'];
            $newinfo['mail'] = $value['mail'];
            $newinfo['rtime'] = Get_Date($value['rtime']);
            $newinfo['ltime'] = Get_Date($value['ltime']);
            if ($appinfo['usetype'] === 'dqsj'){
                if (!empty($value['balance'])){
                    if ($value['balance'] == '-1'){
                        $value['balance'] = '永久使用';
                    }
                    $value['balance'] = Get_Date($value['balance']);
                }else{
                    $value['balance']='-';
                }
            }
            $newinfo['balance'] = $value['balance'];
            $newinfo['rqq'] = htmlspecialchars($value['rqq']);
            if ((time()-$value['htime']) < $appinfo['onlinesecond']){
                $online = '在线';
            }else{
                $online = '离线';
            }
            $newinfo['login'] = $online;
            $newinfo['status'] = '<input type="checkbox" value='.$value['ID'].' name="status" lay-skin="switch" lay-text="正常|冻结" id="qzgx"'.($value['status'] == 1? ' checked' : '').'>';

            $newinfo['origin'] = GetOriginText($value['origin']);

            $newinfo['oid'] = $value['oid'];
            $backinfo['data'][] = $newinfo;
        }

        //print_r($backinfo);
        die(json_encode($backinfo));

        break;
    case 'changeuser':
        if (empty($_POST['mod'])){
            die('模块标识不能为空');
        }
        if (empty($_POST['userid'])){
            die('用户ID不能为空');
        }
        if ($_POST['mod'] == 'status'){
            $_POST['value'] = textbooltonum($_POST['value']);
        }
        if ($_POST['mod'] == 'balance'){
            if ($_POST['value'] == '永久使用'){
                $_POST['value'] = -1;
            }else{
                $value = strtotime($_POST['value']);
                if (!empty($value)){
                    $_POST['value'] = $value;
                }
            }

        }

        if (!$db->update('sq_user',array('ID'=>$_POST['userid']),'AND',array($_POST['mod']=>$_POST['value']))){
            die('修改失败'.$db->geterror());
        }else{
            die('修改成功！');
        }
        break;
    case 'deluser':
        if (empty($_POST['userid'])){
            die('用户ID不能为空');
        }
        $db->delete('sq_token',array('uid'=>$_POST['userid']),'AND');
        if (!$db->delete('sq_user',array('ID'=>$_POST['userid']),'AND')){
            die('删除失败'.$db->geterror());
        }else{
            die('删除成功！');
        }
        
    case 'dl_applist':
        $back = $db->select_all_row('sq_apps','ID,appname',array(),'AND');
        if (count($back) === 0){
            die();
        }
        $i = '<option value="0">代理全部应用</option>';
        foreach ($back as $value){
            $i .= '<option value="'.$value['ID'].'">'.$value['appname'].'</option>';
        }
        die($i);
        break;
    case 'getgrideinfo':
        if (empty($_POST['id'])){
            die('级别为空，无法加载');
        }
        if (!$result = $db->select_first_row('sq_level','*',array('ID'=>$_POST['id']),'AND')){
            die('数据库错误：'.$db->geterror());
        }
        die(json_encode(array(
            'grade_name'=>$result['lname'],
            'grade_present'=>$result['fracture'],
            'grade_price'=>$result['price'],
        )));
        break;
    case 'getlevallist':
        include '../function/function_app.php';
        $list  = '<option></option>';
        $back = $db->select_all_row('sq_level','ID,lname',array(),'AND');
        if (!empty($_POST['aid'])){
            $info = $db->select_first_row('sq_agent','levelid',array('ID'=>$_POST['aid']),'AND');
            print_r($info);
            $lid = $info['levelid'];
        }
        //echo $lid;
        foreach ($back as $value){
            $list.='<option value="'.$value['ID'].'"'.(!empty($_POST['aid']) && $lid == $value['ID'] ? ' selected' : '').'>'.$value['lname'].'</option>';
        }
        die($list);
        break;
    case 'addagent':
        if ($db->select_first_row('sq_agent','*',array('username'=>$_POST['agent_name']),'AND') != false){
            die('该代理账号已经存在，请尝试其他用户名');
        }
        if(!$db->insert_back_id('sq_agent',array('username'=>$_POST['agent_name'],'password'=>$_POST['agent_pass'],'money'=>$_POST['agent_money'],'levelid'=>$_POST['agent_leval'],'begintime'=>time(),'status'=>'1','loginip'=>'-','qq'=>$_POST['agent_qq']))){
            die('添加失败：'.$db->geterror());
        }else{
            die('代理添加成功！');
        }
        break;
    case 'getagentlist':
        include '../function/function_app.php';
        if(!$result = $db->select_limit_row('sq_agent','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], array(), 'AND')){
			
            $backinfo['code'] = -1;
            $backinfo['msg'] = $db->geterror();
			if(empty($backinfo['msg'])){
				$backinfo['code'] = 0;
			}
            die(json_encode($backinfo));
        }else{
            $info = '';
            $backinfo['code'] = 0;
            $backinfo['msg'] = '';
            $backinfo['count'] = $db->select_count_row('sq_agent');
            foreach ($result as $value){
                if ($value['logintime'] == 0){
                    $value['logintime'] = '-';
                }
                $value['begintime'] = Get_Date($value['begintime']);
                $value['logintime'] = Get_Date($value['logintime']);
                $value['levelname'] = level_idgetname($value['levelid']);
                $value['status'] = '<input type="checkbox" value='.$value['ID'].' name="status" lay-skin="switch" lay-text="正常|冻结" id="qzgx"'.($value['status'] == 1? ' checked' : '').'>';
                $backinfo['data'][] = $value;
            }
            die(json_encode($backinfo));
        }
        break;
    case 'coldagent':
        if (!$db->update('sq_agent',array('ID'=>$_POST['agentid']),'AND',array('status'=>0))){
            die('冻结失败'.$db->geterror());
        }else{
            die('冻结成功！');
        }
        break;
    case 'uncoldagent':
        if (!$db->update('sq_agent',array('ID'=>$_POST['agentid']),'AND',array('status'=>1))){
            die('解冻失败'.$db->geterror());
        }else{
            die('解冻成功！');
        }
        break;
    case 'getagentinfo':
        $result = $db->select_first_row('sq_agent','*',array('ID'=>$_POST['id']),'AND');
        die(json_encode(array(
            'agent_leval'=>$result['levelid'],
            'agent_money'=>$result['money'],
            'agent_name'=>$result['username'],
            'agent_pass'=>$result['password']

        )));
        break;
    case 'changeagent':
//        if (!$db->update('sq_agent',array('ID'=>$_POST['id']),'AND',array('username'=>$_POST['agent_name'],'password'=>$_POST['agent_pass'],'money'=>$_POST['agent_money'],'levelid'=>$_POST['agent_leval'],'begintime'=>time(),'status'=>'1','loginip'=>'-'))){
//            die('修改失败'.$db->geterror());
//        }else{
//            die('修改成功！');
//        }
        if (empty($_POST['mod'])){
            die('模块标识不能为空');
        }
        if (empty($_POST['aid'])){
            die('代理ID不能为空');
        }
        if ($_POST['mod'] == 'status') $_POST['value'] = textbooltonum($_POST['value']);
        if (!$db->update('sq_agent',array('ID'=>$_POST['aid']),'AND',array($_POST['mod']=>$_POST['value']))){
            die('修改失败'.$db->geterror());
        }else{
            die('修改成功！');
        }
        break;
    case 'delagent':
        if (empty($_POST['aid'])){
            die('非法提交');
        }
        if (!$db->delete('sq_agent',array('ID'=>$_POST['aid']),'AND')){
            die('删除失败 '.$db->geterror());
        }else{
            die('删除代理成功');
        }
        break;
    case 'savesignset':
        updateset('alipay_id',$_POST['alipay_id']);
        updateset('alipay_rsa',$_POST['alipay_rsa']);
        updateset('alipay_pkey',$_POST['alipay_pkey']);
        updateset('tenpay_id',$_POST['tenpay_id']);
        updateset('qqpay_key',$_POST['qqpay_key']);
        die('修改成功！');
        break;
    case 'creatkey':
        $count = $_POST['sc_count'];
        if ($count <= 0){
            die(json_encode(array('code'=>'-1','msg'=>'生成的数量不能为空或者0或者负数')));
        }
        $time = time();
        $money = $_POST['sc_money'];
        if ($money <= 0){
            die(json_encode(array('code'=>'-1','msg'=>'卡密金额不能为空或者0或者负数')));
        }
        $keylist='';
        for ($x=1; $x<=$_POST['sc_count']; $x++) {
            $key = substr($time,0,6).'-'.rand_str(6).'-'.rand_str(6).'-'.rand_str(6).'-'.rand_str(6).'-'.$money;
            $insarray[] = "'{$key}','{$time}','0','{$money}','{$money}','0','1'";
            $keylist .= '<br>'.$key;
        }
        $sign = rand_str(32);
        $_SESSION['cards'][$sign] = str_replace('<br>',"\r\n",$keylist);
        if(!$num = $db->insert_back_row('sq_key',array('kami','creattime','firstusetime','allmoney','lastmoney','lastusetime','status'),$insarray)){
            die(json_encode(array('code'=>'-1','msg'=>'数据库错误：'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>'1','sign'=>$sign,'keys'=>'您的卡密如下：'.$keylist)));
        }
        break;
    case 'download':
        if (empty($_SESSION['cards'][$_GET['sign']])){
            die('该Sign不存在或者已过期，无法下载卡密');
        }else{
            $filename = $_GET['sign'].".txt";
            header('Content-Type:text/plain'); //指定下载文件类型
            header('Content-Disposition: attachment; filename="'.$filename.'"'); //指定下载文件的描述
            header('Content-Length:'.strlen($_SESSION['cards'][$_GET['sign']])); //指定下载文件的大小
            die($_SESSION['cards'][$_GET['sign']]);
        }
        break;
    case 'getkeylist':
        $where = array();
        if (!empty($_GET['search'])){
            $where['kami'] = $_GET['search'];
        }
        $backinfo['code'] = 0;
        $backinfo['count'] = $db->select_count_row('sq_key',$where,'AND');
        if(!$arr = $db->select_limit_row('sq_key','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], $where, 'AND','ORDER BY ID DESC')){
            $backinfo['msg'] = $db->geterror();
            die(json_encode($backinfo));
        }
        foreach ($arr as $key => $value){
            if ($value['creattime'] == 0){
                $arr[$key]['creattime'] = '-';
            }else{
                $arr[$key]['creattime'] = Get_Date($value['creattime']);
            }

            if ($value['firstusetime'] == 0){
                $arr[$key]['firstusetime'] = '-';
            }else{
                $arr[$key]['firstusetime'] = Get_Date($value['firstusetime']);
            }
            if ($value['lastusetime'] == 0){
                $arr[$key]['lastusetime'] = '-';
            }else{
                $arr[$key]['lastusetime'] = Get_Date($value['lastusetime']);
            }
            $arr[$key]['status'] = '<input type="checkbox" value='.$value['ID'].' name="status" lay-skin="switch" lay-text="正常|冻结" id="qzgx"'.($value['status'] == 1? ' checked' : '').'>';
        }
        $backinfo['data'] = $arr;
        die(json_encode($backinfo));
        break;
    case 'keystatus':
        if (empty($_POST['keyid'])){
            die(makejson(-1,'keyid不能为空'));
        }
        if(!$db->update('sq_key',array('ID'=>$_POST['keyid']),'AND',array('status'=>textbooltonum($_POST['status'])))){
            die(makejson(-2,'修改失败或者没有任何更改'.$db->geterror()));
        }else{
            die(makejson(1,'状态保存成功'));
        }
    case 'coldkey':
        if (!$db->update('sq_key',array('ID'=>$_POST['keyid']),'AND',array('status'=>0))){
            die('冻结失败'.$db->geterror());
        }else{
            die('冻结成功！');
        }
        break;
    case 'uncoldkey':
        if (!$db->update('sq_key',array('ID'=>$_POST['keyid']),'AND',array('status'=>1))){
            die('解冻失败'.$db->geterror());
        }else{
            die('解冻成功！');
        }
        break;
    case 'delkey':
        if (empty($_POST['keyid'])){
            die(makejson(-1,'卡密ID不能为空'));
        }
        if (!$db->delete('sq_key',array('ID'=>$_POST['keyid']),'AND')){
            die(makejson(-2,'删除失败 '.$db->geterror()));
        }else{
            die(makejson(1,'删除成功！'));
        }
        break;
    case 'changepassword':
        //die('本站点禁止修改密码');
        if (empty($_POST['oldpass']) || empty($_POST['newpass']) || empty($_POST['renewpass'])){
            die('请填写完所有数据！');
        }
        if ($_POST['newpass'] != $_POST['renewpass']){
            die('两次输入的密码一样，无法修改！');
        }
        if (!$agentinfo = $db->select_first_row('sq_admin_2','*',array('username'=>$_SESSION['admin_username']),'AND')){
            die('管理员信息获取失败');
        }
        if (md5($_POST['oldpass']) !== $agentinfo['password']){
            die('输入的原密码错误，无法修改！');
        }

        if(!$db->update('sq_admin_2',array('username'=>$_SESSION['admin_username']),'AND',array('password'=>md5($_POST['newpass'])))){
            die('密码修改失败，服务器内部发生错误');
        }else{
            die('管理密码修改成功，请使用新密码进行登录！');
        }

        break;
    case 'user_applist':
        $back = $db->select_all_row('sq_apps','ID,appname',array(),'AND');
        if (count($back) === 0){
            die();
        }
        $i = '<option></option>';
        foreach ($back as $value){
            $i .= '<option value="'.$value['ID'].'">'.$value['appname'].'</option>';
        }
        die($i);
        break;
    case 'checkupdate':
        // 通过 GitHub 比较版本
        $localVer = str_replace('.','',$G['siteinfo']['ver']);
        $needUpdate = false;
        if (!empty($_POST['remote_sha'])){
            $remoteCheck = @file_get_contents('https://raw.githubusercontent.com/AyMzz-dev/-/master/function/ver.inc.php');
            if ($remoteCheck){
                preg_match("/'ver'\s*=>\s*'([^']+)'/", $remoteCheck, $matches);
                if (!empty($matches[1])){
                    $remoteVer = str_replace('.','',$matches[1]);
                    if (intval($remoteVer) > intval($localVer)){
                        $needUpdate = true;
                    }
                }
            }
        }
        die(json_encode(array('need_update'=>$needUpdate)));
        break;
    case 'autoupdate':
        set_time_limit(0);
        ignore_user_abort(true);
        
        $repoUrl = 'https://github.com/AyMzz-dev/-/archive/refs/heads/master.zip';
        $tmpZip = sys_get_temp_dir().'/wenquan_update_'.time().'.zip';
        $tmpDir = sys_get_temp_dir().'/wenquan_update_'.time();
        
        $zipData = @file_get_contents($repoUrl);
        if (!$zipData || strlen($zipData) < 1000) {
            die(json_encode(array('code'=>-1,'msg'=>'下载更新包失败，请检查网络或GitHub仓库')));
        }
        file_put_contents($tmpZip, $zipData);
        
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            unlink($tmpZip);
            die(json_encode(array('code'=>-2,'msg'=>'解压更新包失败，请检查服务器PHP Zip扩展')));
        }
        $zip->extractTo($tmpDir);
        $zip->close();
        unlink($tmpZip);
        
        $subDirs = scandir($tmpDir);
        $srcDir = $tmpDir;
        foreach ($subDirs as $d) {
            if ($d != '.' && $d != '..' && is_dir($tmpDir.'/'.$d)) {
                $srcDir = $tmpDir.'/'.$d;
                break;
            }
        }
        
        $excludeFiles = array('config.inc.php', 'install.php', '.gitignore', 'README.md', 'LICENSE', 'CHANGELOG', '部署清单.md');
        $excludeDirs = array('.git', '.trae-cn', 'upload');
        $updatedFiles = 0;
        $errors = 0;
        
        function copyUpdateFiles2($src, $dst, $excludeFiles, $excludeDirs, &$updatedFiles, &$errors) {
            if (!is_dir($src)) return;
            if (!is_dir($dst)) @mkdir($dst, 0755, true);
            $dir = opendir($src);
            while (($file = readdir($dir)) !== false) {
                if ($file == '.' || $file == '..') continue;
                if (in_array($file, $excludeDirs)) continue;
                $srcPath = $src.'/'.$file;
                $dstPath = $dst.'/'.$file;
                if (is_dir($srcPath)) {
                    copyUpdateFiles2($srcPath, $dstPath, $excludeFiles, $excludeDirs, $updatedFiles, $errors);
                } else {
                    if (in_array($file, $excludeFiles)) continue;
                    if (@copy($srcPath, $dstPath)) {
                        $updatedFiles++;
                    } else {
                        $errors++;
                    }
                }
            }
            closedir($dir);
        }
        
        $webRoot = dirname(dirname(__DIR__));
        copyUpdateFiles2($srcDir, $webRoot, $excludeFiles, $excludeDirs, $updatedFiles, $errors);
        
        function delTree2($dir) {
            if (!is_dir($dir)) return;
            $files = array_diff(scandir($dir), array('.','..'));
            foreach ($files as $file) {
                (is_dir("$dir/$file")) ? delTree2("$dir/$file") : @unlink("$dir/$file");
            }
            @rmdir($dir);
        }
        delTree2($tmpDir);
        
        die(json_encode(array(
            'code' => ($errors == 0) ? 1 : 0,
            'msg' => '更新完成！成功更新 '.$updatedFiles.' 个文件'.($errors > 0 ? '，'.$errors.' 个文件失败' : ''),
            'updated' => $updatedFiles,
            'errors' => $errors
        )));
        break;
    case 'keylog':
        if (!$result = $db->select_limit_row('sq_log_kami','*','',0,array('keyid'=>$_POST['keyid']),'AND',"ORDER BY time DESC")){
            die(makejson(-1,'数据库中没有记录'));
        }

        die(makejson(1,'success',array('items'=>$result)));
        break;
    case 'loadbuybody':
        include "../function/function_app.php";
        if(!$appinfo = app_idgetinfo($_POST['appid'])){
            die();
        }

        $output = '';
        //$output = '<br><form class="form-horizontal" style="width: 90%">';
        if ($appinfo['logintype'] == 'zhmm'){
            $output .= '
    <div class="layui-form-item">
        <label class="layui-form-label">用户名</label>
        <div class="layui-input-block">
            <input type="text" name="kt_user" required  lay-verify="required" placeholder="开通/续费的用户名" autocomplete="off" class="layui-input">
        </div>
    </div>
    
    <div class="layui-form-item">
        <label class="layui-form-label">密码</label>
        <div class="layui-input-block">
            <input type="text" name="kt_pass" placeholder="开通的密码，如果是续费这里可留空" autocomplete="off" class="layui-input">
        </div>
    </div>
    ';
            $type = 1;
        }else if ($appinfo['logintype'] == 'kmsq'){
            $output .= '
    <div class="layui-form-item">
        <label class="layui-form-label">授权卡密</label>
        <div class="layui-input-block">
             <input type="text" name="kt_user" placeholder="输入为续费，留空为新开" autocomplete="off" class="layui-input">
        </div>
    </div>';
            $type = 2;
        }else if ($appinfo['logintype'] == 'jcbd'){
            if ($appinfo['bindqq'] == '1'){
                $output .= '
    <div class="layui-form-item">
        <label class="layui-form-label">机器人QQ</label>
        <div class="layui-input-block">
            <input type="text" name="kt_robotqq" placeholder="" required  lay-verify="required" autocomplete="off" class="layui-input">
        </div>
    </div>
    ';
            }
            if ($appinfo['bindmac'] == '1'){
                $output .= '<div class="layui-form-item">
        <label class="layui-form-label">设备码</label>
        <div class="layui-input-block">
            <input type="text" name="kt_mac" placeholder="" required  lay-verify="required" autocomplete="off" class="layui-input">
        </div>
    </div>
    ';
            }
            if ($appinfo['bindip'] == '1'){
                $output .= '<div class="layui-form-item">
        <label class="layui-form-label">IP地址</label>
        <div class="layui-input-block">
            <input type="text" name="kt_ip" placeholder="" required  lay-verify="required" autocomplete="off" class="layui-input">
        </div>
    </div>
    ';
            }
            $type = 3;
        }


        $output .= '<div class="layui-form-item">
        <label class="layui-form-label">开通数量</label>
        <div class="layui-input-block">
            <input type="text" name="kt_num" placeholder="" required  lay-verify="required|number" autocomplete="off" class="layui-input">
            <div class="layui-form-mid layui-word-aux">如果是余额方式则为余额点数，如果是到期时间方式则为分钟数量(1天=1440，30天=43200，180天=259200，365天=525600)，不限授权请填-1</div>
        </div>
         
    </div>
    
    <div class="layui-form-item">
        <label class="layui-form-label">用户邮箱</label>
        <div class="layui-input-block">
            <input type="text" name="kt_mail" placeholder="" required  lay-verify="required" autocomplete="off" class="layui-input">
        </div>
    </div>
     <div class="layui-form-item">
        <label class="layui-form-label">主人QQ</label>
        <div class="layui-input-block">
            <input type="text" name="kt_adminqq" placeholder="" required  lay-verify="required|number" autocomplete="off" class="layui-input">
        </div>
    </div>
';

        die($output);
        break;
    case 'buy_submit':
        include "../function/function_app.php";
        $_POST = json_decode($_POST['content'],true);
        if (!$appinfo = app_idgetinfo($_POST['id'])){
            die('应用信息获取失败');
        }
        if (empty($_POST['kt_adminqq'])){
            die('用户QQ不能为空');
        }
        if (empty($_POST['kt_mail'])){
            die('用户邮箱不能为空');
        }
        if (empty($_POST['kt_num'])){
            die('开通的数量不能为空');
        }
        $balance = $_POST['kt_num'];
        include '../function/function_auth.php';
        if(!isset($_POST['kt_ip'])){
            $_POST['kt_ip'] = '';
        }
        if(!isset($_POST['kt_robotqq'])){
            $_POST['kt_robotqq'] = '';
        }
        if(!isset($_POST['kt_mac'])){
            $_POST['kt_mac'] = '';
        } 
        if ($appinfo['logintype'] == 'jcbd'){
            $_POST['kt_user'] = json_encode(array(
                'lip'=>$_POST['kt_ip'],
                'rqq'=>$_POST['kt_robotqq'],
                'mac'=>$_POST['kt_mac']
            ));
        }
        if (empty($_POST['kt_pass'])){
            $_POST['kt_pass'] = '';
        }
        $back = auth_add($_POST['kt_user'],$_POST['kt_pass'],'',$_POST['kt_num'],$_POST['kt_adminqq'],$_POST['kt_mail'],$_POST['id'],1,'',$_SESSION['admin_id'],$newkey,$tips,array());
        if ($back == 3){
            die('成功生成卡密：'.$newkey);
        }else if ($back == 1){
            die('授权开通成功');
        }else if ($back == 2){
            die('授权续费成功');
        }else{
            die($tips);
        }
        break;
    case 'agentlog':
        if (!$result = $db->select_limit_row('sq_log_agent','*','',0,array('aid'=>$_POST['aid']),'AND',"ORDER BY time DESC")){
            die('数据库中没有记录！');
        }
        foreach ($result as $value){
            echo '['.Get_Date($value['time']).'] '.$value['msg'].'<br>';
        }
        echo '=======================<br>';
        die('只列出最近五十条记录，若想查看更多，请前往数据库执行这行语句查询：[SELECT * FROM sq_log_agent WHERE aid='.$_POST['aid']);
        break;
    case 'saveintroduce':
        if (!$db->update('sq_apps',array('ID'=>$_POST['appid']),'AND',array('introduce'=>$_POST['content']))){
            die("保存失败".$db->geterror());
        }else{
            die('保存成功');
        }
    case 'upload':
        $upload_dir = './img/apps/';

        if(strtolower($_SERVER['REQUEST_METHOD']) != 'post'){
            die('错误的请求');
        }

        if(array_key_exists('file',$_FILES) && $_FILES['file']['error'] == 0 ){
            $pic = $_FILES['file'];
            $pic['name'] = time().'.png';
            if(move_uploaded_file($pic['tmp_name'], '.'.$upload_dir.$pic['name'])){
                if(!$db->update('sq_apps',array('ID'=>$_GET['appid']),'AND',array('imgsrc'=>$upload_dir.$pic['name']))){
                    die($db->geterror());
                }
                die(json_encode(array('code'=>0,'msg'=>'更换成功')));
            }
        }

        die(json_encode(array('code'=>0,'msg'=>$_FILES['file']['error'])));;
    case 'getnotice':
        $notices = $db->select_limit_row('sq_notice','*',0,20,array('status'=>1),'AND','ORDER BY sort DESC, ID DESC');
        if (empty($notices)){
            die('<div style="text-align:center;padding:30px;color:#999;"><i class="layui-icon" style="font-size:40px;">&#xe645;</i><p style="margin-top:10px;">暂无公告</p></div>');
        }
        $html = '';
        foreach ($notices as $v){
            $html .= '<div style="padding:12px 0;border-bottom:1px solid #f0f0f0;">';
            $html .= '<div style="font-weight:600;color:#333;margin-bottom:5px;"><i class="layui-icon" style="color:#6a11cb;">&#xe645;</i> '.htmlspecialchars($v['title']).' <span style="font-size:12px;color:#999;">'.Get_Date($v['time']).'</span></div>';
            $html .= '<div style="color:#666;line-height:1.6;">'.nl2br(htmlspecialchars($v['content'])).'</div>';
            $html .= '</div>';
        }
        die($html);
        break;
    case 'getuplog':
        $uplog = @file_get_contents('https://raw.githubusercontent.com/AyMzz-dev/-/master/CHANGELOG');
        if ($uplog){
            die($uplog);
        }
        $ver = $G['siteinfo']['ver'];
        die("温泉PHP网络授权系统 v{$ver}\n\n开源地址：https://github.com/AyMzz-dev/-\n\n如需查看最新更新日志，请前往 GitHub 仓库。");
        break;
    case 'gettoken':
        $result = $db->select_first_row('sq_admin_2','accesstoken',array('ID'=>$_SESSION['admin_id']),'AND');
        die($result['accesstoken']);
        break;
    case 'resettoken':
        $token = rand_str(64);
        $result = $db->update('sq_admin_2',array('ID'=>$_SESSION['admin_id']),'AND',array('accesstoken'=>$token));
        die($token);
        break;
    case 'getbclist':

        if(!$result = $db->select_limit_row('sq_bclist','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], array(), 'AND')){

            $backinfo['msg'] = $db->geterror();
            if ($backinfo['msg'] == ''){
                $backinfo['code'] = 0;
            }
            die(json_encode($backinfo));
        }else{
            include_once '../function/function_app.php';
            $info = '';
            $backinfo['code'] = 0;
            $backinfo['msg'] = '';
            $backinfo['count'] = $db->select_count_row('sq_bclist');
            foreach ($result as $value){
                if ($value['appid'] == 0){
                    $value['app'] = '所有应用';
                }else{
                    $value['app'] = app_idgetname($value['appid']);
                }
                $value['time'] = Get_Date($value['time']);
                $backinfo['data'][] = $value;
            }


            die(json_encode($backinfo));
        }
        break;
    case 'addbc':

        if (empty($_POST['appid'])){
            $_POST['appid'] = '0';
        }
        if (empty($_POST['obj'])){
            die(json_encode(array('code'=>-1,'msg'=>'拉黑对象不能为空')));
        }
        if ($db->select_first_row('sq_bclist','ID',array('obj'=>$_POST['obj'],'appid'=>$_POST['appid']),'AND') != false){
            die(json_encode(array('code'=>-2,'msg'=>'拉黑对象已存在于黑名单中')));
        }
        $_POST['time'] = time();
        $_POST['uid'] = $_SESSION['admin_username'];
        if(!$db->insert_back_id('sq_bclist',$_POST)){
            die(json_encode(array('code'=>-3,'msg'=>$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'添加成功')));
        }

        break;
    case 'delbc':
        if (!$db->delete('sq_bclist',array('ID'=>$_POST['id']),'AND')){
            die(json_encode(array('code'=>-1,'msg'=>'删除失败'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'删除成功')));
        }
        break;
    case 'changebc':
        if (empty($_POST['id']) || empty($_POST['mod']) || !isset($_POST['value'])){
            die(json_encode(array('code'=>-1,'msg'=>'参数错误')));
        }
        if (!$db->update('sq_bclist',array('ID'=>$_POST['id']),'AND',array($_POST['mod']=>$_POST['value']))){
            die(json_encode(array('code'=>-1,'msg'=>'修改失败'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'修改成功')));
        }
        break;
    case 'KeyOperation':
        switch ($_POST['operation']){
            case 'ExportNoUse':
                $result = $db->select_all_row('sq_key','kami',array('firstusetime'=>0,'status'=>1));
                $keylist = '';
                foreach ($result as $value){
                    $keylist .= $value['kami']."\r\n";
                }
                if (empty($keylist)){
                    die(makejson(-4,'导出失败，没有符合的卡密'));
                }
                $sign = rand_str(32);
                $_SESSION['cards'][$sign] = $keylist;
                die(makejson(1,'success',array('sign'=>$sign)));
                break;
            case 'ExportNoMoney':
                $result = $db->select_all_row('sq_key','kami',array('lastmoney'=>0,'status'=>1));
                $keylist = '';
                foreach ($result as $value){
                    $keylist .= $value['kami']."\r\n";
                }
                if (empty($keylist)){
                    die(makejson(-4,'导出失败，没有符合的卡密'));
                }
                $sign = rand_str(32);
                $_SESSION['cards'][$sign] = $keylist;
                die(makejson(1,'success',array('sign'=>$sign)));
                break;
            case 'ExportAll':
                $result = $db->select_all_row('sq_key','kami');
                $keylist = '';
                foreach ($result as $value){
                    $keylist .= $value['kami']."\r\n";
                }
                if (empty($keylist)){
                    die(makejson(-4,'导出失败，没有符合的卡密'));
                }
                $sign = rand_str(32);
                $_SESSION['cards'][$sign] = $keylist;
                die(makejson(1,'success',array('sign'=>$sign)));
                break;
            case 'DelNoMoney':
                $db->delete('sq_key',array('lastmoney'=>0),'AND');

                die(makejson(2,'success',array('nums'=>$db->affected_num())));
                break;
            case 'DelNoUse':
                $db->delete('sq_key',array('firstusetime'=>0),'AND');
                die(makejson(2,'success',array('nums'=>$db->affected_num())));
                break;
            case 'DelAll':
                $db->delete('sq_key',array('1'=>'1'),'AND');
                die(makejson(2,'success',array('nums'=>$db->affected_num())));
                break;
            default:
                die(makejson(-1,'未知操作：'.$_POST['operation']));
        }
        break;
    case 'fidlist_show':
        include_once '../function/function_app.php';
        $res = $db->select_all_row('sq_fidlist');
        $str = '<option></option>';
        foreach ($res as $info){
            $str .='<option value="'.$info['ID'].'">'.$info['fidname'].'('.app_idgetname($info['appid']).')</option>';
        }
        die($str);
        break;
    case 'creatfidkey':
        $count = $_POST['sc_count'];
        if ($count <= 0){
            die(json_encode(array('code'=>'-1','msg'=>'生成的数量不能为空或者0或者负数')));
        }
        $time = time();
        if ($count <= 0){
            die(json_encode(array('code'=>'-1','msg'=>'卡密金额不能为空或者0或者负数')));
        }
        $keylist='';
        for ($x=1; $x<=$_POST['sc_count']; $x++) {
            $key = substr($time,0,6).'-'.rand_str(6).'-'.rand_str(6).'-'.rand_str(6).'-'.rand_str(6).rand_str(6);
            $insarray[] = "'{$key}','{$time}','0','{$_POST['sc_fid']}','1'";
            $keylist .= '<br>'.$key;
        }
        $sign = rand_str(32);
        $_SESSION['cards'][$sign] = str_replace('<br>',"\r\n",$keylist);
        if(!$num = $db->insert_back_row('sq_fidkey',array('kami','creattime','usetime','fid','status'),$insarray)){
            die(json_encode(array('code'=>'-1','msg'=>'数据库错误：'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>'1','sign'=>$sign,'keys'=>'您的卡密如下：'.$keylist)));
        }
        break;
    case 'gettckeylist':
        $where = array();
        if (!empty($_GET['search'])){
            $where['kami'] = $_GET['search'];
        }
        $where['fid'] = $_GET['fid'];
        $backinfo['code'] = 0;
        $backinfo['count'] = $db->select_count_row('sq_fidkey',$where,'AND');
        if(!$arr = $db->select_limit_row('sq_fidkey','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], $where, 'AND','ORDER BY ID DESC')){
            $backinfo['msg'] = $db->geterror();
            die(json_encode($backinfo));
        }
        foreach ($arr as $key => $value){
            if ($value['creattime'] == 0){
                $arr[$key]['creattime'] = '-';
            }else{
                $arr[$key]['creattime'] = Get_Date($value['creattime']);
            }

            if ($value['usetime'] == 0){
                $arr[$key]['usetime'] = '-';
            }else{
                $arr[$key]['usetime'] = Get_Date($value['usetime']);
            }

            $arr[$key]['status'] = '<input type="checkbox" value='.$value['ID'].' name="status" lay-skin="switch" lay-text="正常|冻结" id="qzgx"'.($value['status'] == 1? ' checked' : '').'>';
        }
        $backinfo['data'] = $arr;
        die(json_encode($backinfo));

        break;
    case 'FidKeyOperation':
        switch ($_POST['operation']){
            case 'ExportNoUse':
                $result = $db->select_all_row('sq_fidkey','kami',array('usetime'=>0,'status'=>1,'fid'=>$_POST['fid']),'AND');
                $keylist = '';
                foreach ($result as $value){
                    $keylist .= $value['kami']."\r\n";
                }
                if (empty($keylist)){
                    die('没有符合要求的卡密');
                }
                $sign = rand_str(32);
                $_SESSION['cards'][$sign] = $keylist;
                die('您的卡密已就绪，<a href="../ajax.php?mod=download&sign='.$sign.'">点击这里下载卡密</a>');
                break;
            case 'ExportAll':
                $result = $db->select_all_row('sq_fidkey','kami',array('fid'=>$_POST['fid'],'status'=>1));
                $keylist = '';
                foreach ($result as $value){
                    $keylist .= $value['kami']."\r\n";
                }
                if (empty($keylist)){
                    die('没有符合要求的卡密');
                }
                $sign = rand_str(32);
                $_SESSION['cards'][$sign] = $keylist;
                die('您的卡密已就绪，<a href="../ajax.php?mod=download&sign='.$sign.'">点击这里下载卡密</a>');
                break;
            case 'DelNoUse':
                $db->delete('sq_fidkey',array('usetime'=>0,'fid'=>$_POST['fid'],'status'=>1),'AND');
                die('成功删除'.(int)$db->affected_num().'行');
                break;
            case 'DelAll':
                $db->delete('sq_fidkey',array('fid'=>$_POST['fid']),'AND');
                die('成功删除'.(int)$db->affected_num().'行');
                break;
            case 'DelUse':
                $db->delete('sq_fidkey', '`usetime` > 0 AND `fid` = '.$_POST['fid'].' AND `status` = 1','AND');
                die('成功删除'.(int)$db->affected_num().'行');
                break;
        }
        break;
    case 'deltckey':
        if (empty($_POST['keyid'])){
            die('非法提交');
        }
        if (!$db->delete('sq_fidkey',array('ID'=>$_POST['keyid']),'AND')){
            die('删除失败 '.$db->geterror());
        }else{
            die('删除成功！');
        }
        break;
    case 'tckeystatus':
        if(!$db->update('sq_fidkey',array('ID'=>$_POST['keyid']),'AND',array('status'=>textbooltonum($_POST['status'])))){
            die('更新失败'.$db->geterror());
        }else{
            die('成功');
        }
        break;
    case 'getbailist':
        if(!$result = $db->select_limit_row('sq_bailist','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], array(), 'AND')){
            $backinfo['msg'] = $db->geterror();
            if ($backinfo['msg'] == ''){
                $backinfo['code'] = 0;
            }
            die(json_encode($backinfo));
        }else{
            $info = '';
            $backinfo['code'] = 0;
            $backinfo['msg'] = '';
            $backinfo['count'] = $db->select_count_row('sq_bailist');
            foreach ($result as $value){
                $value['time'] = Get_Date($value['time']);
                $backinfo['data'][] = $value;
            }
            die(json_encode($backinfo));
        }
        break;
    case 'addbai':
        if (empty($_POST['obj'])){
            die(json_encode(array('code'=>-1,'msg'=>'云白对象不能为空')));
        }
        if ($db->select_first_row('sq_bailist','ID',array('obj'=>$_POST['obj']),'AND') != false){
            die(json_encode(array('code'=>-2,'msg'=>'云白对象已存在于白名单中')));
        }
        $_POST['time'] = time();
        $_POST['uid'] = $_SESSION['admin_username'];
        if(!$db->insert_back_id('sq_bailist',$_POST)){
            die(json_encode(array('code'=>-3,'msg'=>$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'添加成功')));
        }
        break;
    case 'delbai':
        if (!$db->delete('sq_bailist',array('ID'=>$_POST['id']),'AND')){
            die(json_encode(array('code'=>-1,'msg'=>'删除失败'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'删除成功')));
        }
        break;
    case 'changebai':
        if (empty($_POST['id']) || empty($_POST['mod']) || !isset($_POST['value'])){
            die(json_encode(array('code'=>-1,'msg'=>'参数错误')));
        }
        if (!$db->update('sq_bailist',array('ID'=>$_POST['id']),'AND',array($_POST['mod']=>$_POST['value']))){
            die(json_encode(array('code'=>-1,'msg'=>'修改失败'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'修改成功')));
        }
        break;
    case 'getsqlist':
        if(!$result = $db->select_limit_row('sq_site','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], array(), 'AND')){
            $backinfo['msg'] = $db->geterror();
            if ($backinfo['msg'] == ''){
                $backinfo['code'] = 0;
            }
            die(json_encode($backinfo));
        }else{
            $info = '';
            $backinfo['code'] = 0;
            $backinfo['msg'] = '';
            $backinfo['count'] = $db->select_count_row('sq_site');
            foreach ($result as $value){
                $value['type'] = $value['type'] == '0' ? '温泉授权系统' : '南逸授权系统';
                $backinfo['data'][] = $value;
            }
            die(json_encode($backinfo));
        }
        break;
    case 'addsq':
        if (empty($_POST['mc']) || empty($_POST['lj'])){
            die(json_encode(array('code'=>-1,'msg'=>'品牌名和链接不能为空')));
        }
        if(!$db->insert_back_id('sq_site',array('mc'=>$_POST['mc'],'lj'=>$_POST['lj'],'type'=>$_POST['type'],'appid'=>''))){
            die(json_encode(array('code'=>-2,'msg'=>$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'添加成功')));
        }
        break;
    case 'delsq':
        if (!$db->delete('sq_site',array('ID'=>$_POST['ID']),'AND')){
            die(json_encode(array('code'=>-1,'msg'=>'删除失败'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'删除成功')));
        }
        break;
    case 'changesq':
        if (empty($_POST['id']) || empty($_POST['mod']) || !isset($_POST['value'])){
            die('参数错误');
        }
        if (!$db->update('sq_site',array('ID'=>$_POST['id']),'AND',array($_POST['mod']=>$_POST['value']))){
            die('修改失败'.$db->geterror());
        }else{
            die('修改成功！');
        }
        break;
    case 'getadmin2list':
        $where = array();
        if (!empty($_GET['search'])){
            $where['username'] = $where['qq'] = $_GET['search'];
            $whereinfo = $db->wheretosql($where,'OR');
        }else{
            $whereinfo = '';
        }
        if(!$result = $db->select_limit_row('sq_admin_2','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], $whereinfo, 'AND')){
            $backinfo['msg'] = $db->geterror();
            if ($backinfo['msg'] == ''){
                $backinfo['code'] = 0;
            }
            die(json_encode($backinfo));
        }else{
            $info = '';
            $backinfo['code'] = 0;
            $backinfo['msg'] = '';
            $backinfo['count'] = $db->select_count_row('sq_admin_2',$whereinfo,'AND');
            foreach ($result as $key => $value){
                if ($value['logintime'] == 0){
                    $result[$key]['logintime'] = '-';
                }else{
                    $result[$key]['logintime'] = Get_Date($value['logintime']);
                }
                $result[$key]['status'] = '<input type="checkbox" value='.$value['ID'].' name="status" lay-skin="switch" lay-text="正常|冻结" id="qzgx"'.(empty($value['status']) || $value['status'] == 1 ? ' checked' : '').'>';
            }
            $backinfo['data'] = $result;
            die(json_encode($backinfo));
        }
        break;
    case 'deladmin2':
        if (empty($_POST['hid'])){
            die('非法提交');
        }
        if (!$db->delete('sq_admin_2',array('ID'=>$_POST['hid']),'AND')){
            die('删除失败 '.$db->geterror());
        }else{
            die('删除二级管理员成功');
        }
        break;
    case 'changeadmin2':
        if (empty($_POST['mod'])){
            die('模块标识不能为空');
        }
        if (empty($_POST['hid'])){
            die('ID不能为空');
        }
        if ($_POST['mod'] == 'status') $_POST['value'] = textbooltonum($_POST['value']);
        if (!$db->update('sq_admin_2',array('ID'=>$_POST['hid']),'AND',array($_POST['mod']=>$_POST['value']))){
            die('修改失败'.$db->geterror());
        }else{
            die('修改成功！');
        }
        break;
    case 'sqhb':
        if (empty($_POST['userid']) || empty($_POST['newid'])){
            die('参数不能为空');
        }
        if (!$db->update('sq_user',array('ID'=>$_POST['userid']),'AND',array('oid'=>$_POST['newid']))){
            die('划拨失败：'.$db->geterror());
        }else{
            die('授权划拨成功！用户ID '.$_POST['userid'].' 的上级已修改为 '.$_POST['newid']);
        }
        break;
    case 'dlhb':
        if (empty($_POST['userid']) || empty($_POST['newid'])){
            die('参数不能为空');
        }
        if (!$db->update('sq_agent',array('ID'=>$_POST['userid']),'AND',array('superior'=>$_POST['newid']))){
            die('划拨失败：'.$db->geterror());
        }else{
            die('代理划拨成功！代理ID '.$_POST['userid'].' 的上级已修改为 '.$_POST['newid']);
        }
        break;
    case 'getlevallist':
        include '../function/function_app.php';
        $list  = '<option></option>';
        $back = $db->select_all_row('sq_level','ID,lname',array(),'AND');
        if (!empty($_POST['aid'])){
            $info = $db->select_first_row('sq_agent','levelid',array('ID'=>$_POST['aid']),'AND');
            print_r($info);
            $lid = $info['levelid'];
        }
        foreach ($back as $value){
            $list.='<option value="'.$value['ID'].'"'.(!empty($_POST['aid']) && $lid == $value['ID'] ? ' selected' : '').'>'.$value['lname'].'</option>';
        }
        die($list);
        break;
    case 'getnoticelist':
        if(!$result = $db->select_limit_row('sq_notice','*',($_GET['page'] - 1) * $_GET['limit'] , $_GET['limit'], array(), 'AND','ORDER BY sort DESC, ID DESC')){
            $backinfo['msg'] = $db->geterror();
            if ($backinfo['msg'] == ''){
                $backinfo['code'] = 0;
            }
            die(json_encode($backinfo));
        }else{
            $backinfo['code'] = 0;
            $backinfo['msg'] = '';
            $backinfo['count'] = $db->select_count_row('sq_notice');
            foreach ($result as $value){
                $value['time'] = Get_Date($value['time']);
                $backinfo['data'][] = $value;
            }
            die(json_encode($backinfo));
        }
        break;
    case 'addnotice':
        if (empty($_POST['title']) || empty($_POST['content'])){
            die(json_encode(array('code'=>-1,'msg'=>'公告标题和内容不能为空')));
        }
        $_POST['time'] = time();
        $_POST['status'] = 1;
        if (empty($_POST['sort'])) $_POST['sort'] = 0;
        if(!$db->insert_back_id('sq_notice',$_POST)){
            die(json_encode(array('code'=>-2,'msg'=>$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'公告添加成功')));
        }
        break;
    case 'delnotice':
        if (!$db->delete('sq_notice',array('ID'=>$_POST['id']),'AND')){
            die(json_encode(array('code'=>-1,'msg'=>'删除失败'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'删除成功')));
        }
        break;
    case 'changenotice':
        if (empty($_POST['id']) || empty($_POST['mod']) || !isset($_POST['value'])){
            die(json_encode(array('code'=>-1,'msg'=>'参数错误')));
        }
        if (!$db->update('sq_notice',array('ID'=>$_POST['id']),'AND',array($_POST['mod']=>$_POST['value']))){
            die(json_encode(array('code'=>-1,'msg'=>'修改失败'.$db->geterror())));
        }else{
            die(json_encode(array('code'=>1,'msg'=>'修改成功')));
        }
        break;
    case 'getversion':
        die(json_encode(array('version'=>str_replace('.','',$G['siteinfo']['ver']))));
        break;
    default:
        die('What the fuck');
}
function get_zip_originalsize($filename, $path) {//解压zip文件
    $resource = zip_open($filename);
    while ($dir_resource = zip_read($resource)) {
        if (zip_entry_open($resource,$dir_resource)) {
            $file_name = $path.zip_entry_name($dir_resource);
            $file_path = substr($file_name,0,strrpos($file_name, "/"));
            if(!is_dir($file_path)){
                mkdir($file_path,0777,true);
            }
            if(!is_dir($file_name)){
                $file_size = zip_entry_filesize($dir_resource);
                $file_content = zip_entry_read($dir_resource,$file_size);
                file_put_contents($file_name,$file_content);
            }
            zip_entry_close($dir_resource);
        }
    }
    zip_close($resource);
}

function getFile($url, $save_dir = '', $filename = '', $type = 0) {
    if (trim($url) == '') {
        return false;
    }
    if (trim($save_dir) == '') {
        $save_dir = './';
    }
    if (0 !== strrpos($save_dir, '/')) {
        $save_dir.= '/';
    }
    //创建保存目录
    if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
        return false;
    }
    //获取远程文件所采用的方法
    if ($type) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $content = curl_exec($ch);
        curl_close($ch);
    } else {
        ob_start();
        readfile($url);
        $content = ob_get_contents();
        ob_end_clean();
    }
    //echo $content;
    $size = strlen($content);
    //文件大小
    $fp2 = @fopen($save_dir . $filename, 'a');
    fwrite($fp2, $content);
    fclose($fp2);
    unset($content, $url);
    return array(
        'file_name' => $filename,
        'save_path' => $save_dir . $filename,
        'file_size' => $size
    );
}
