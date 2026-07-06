package main

import (
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"flag"
	"html/template"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"time"

	_ "modernc.org/sqlite"
)

var db *sql.DB
var port = flag.String("port", "99", "监听端口")

// ============================================
// 数据结构
// ============================================

type AuthSite struct {
	ID            int    `json:"id"`
	Domain        string `json:"domain"`
	Sitekey       string `json:"sitekey"`
	Status        int    `json:"status"`
	Note          string `json:"note"`
	CreateTime    int64  `json:"create_time"`
	LastCheckTime int64  `json:"last_check_time"`
}

type JSONResponse map[string]interface{}

// ============================================
// 数据库初始化
// ============================================

func initDB(dbPath string) {
	var err error
	db, err = sql.Open("sqlite", dbPath+"?_journal_mode=WAL&_busy_timeout=5000")
	if err != nil {
		log.Fatalf("打开数据库失败: %v", err)
	}

	db.SetMaxOpenConns(1)
	db.SetMaxIdleConns(1)

	_, err = db.Exec(`
		CREATE TABLE IF NOT EXISTS auth_sites (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			domain TEXT NOT NULL,
			sitekey TEXT NOT NULL UNIQUE,
			status INTEGER DEFAULT 1,
			note TEXT DEFAULT '',
			create_time INTEGER NOT NULL,
			last_check_time INTEGER DEFAULT 0
		);
		CREATE TABLE IF NOT EXISTS auth_logs (
			id INTEGER PRIMARY KEY AUTOINCREMENT,
			sitekey TEXT NOT NULL,
			domain TEXT NOT NULL,
			action TEXT NOT NULL,
			ip TEXT NOT NULL,
			result INTEGER DEFAULT 0,
			create_time INTEGER NOT NULL
		);
	`)
	if err != nil {
		log.Fatalf("创建数据表失败: %v", err)
	}

	log.Println("[数据库] 初始化完成")
}

// ============================================
// 生成随机密钥
// ============================================

func generateKey() string {
	b := make([]byte, 32)
	rand.Read(b)
	return hex.EncodeToString(b)
}

// ============================================
// 工具函数
// ============================================

func jsonResponse(w http.ResponseWriter, code int, msg string, tips string) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	resp := JSONResponse{"code": code}
	if msg != "" {
		resp["msg"] = msg
	}
	if tips != "" {
		resp["tips"] = tips
	}
	json.NewEncoder(w).Encode(resp)
}

func jsonOK(w http.ResponseWriter, data interface{}) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	json.NewEncoder(w).Encode(data)
}

func clientIP(r *http.Request) string {
	if ip := r.Header.Get("X-Forwarded-For"); ip != "" {
		return ip
	}
	if ip := r.Header.Get("X-Real-IP"); ip != "" {
		return ip
	}
	return r.RemoteAddr
}

func logAction(action, sitekey, domain, ip string, result int) {
	db.Exec("INSERT INTO auth_logs (sitekey,domain,action,ip,result,create_time) VALUES (?,?,?,?,?,?)",
		sitekey, domain, action, ip, result, time.Now().Unix())
}

// ============================================
// API 路由
// ============================================

func apiHandler(w http.ResponseWriter, r *http.Request) {
	mod := r.URL.Query().Get("mod")
	domain := r.URL.Query().Get("domain")
	sitekey := r.URL.Query().Get("sitekey")
	ip := clientIP(r)

	log.Printf("[请求] %s -> mod=%s domain=%s", ip, mod, domain)

	switch mod {
	case "checkauth":
		handleCheckAuth(w, domain, sitekey, ip)
	case "sendmail":
		handleSendMail(w, r, domain, sitekey, ip)
	default:
		jsonResponse(w, 0, "未知模块", "")
	}
}

func handleCheckAuth(w http.ResponseWriter, domain, sitekey, ip string) {
	if domain == "" || sitekey == "" {
		jsonResponse(w, 0, "参数不完整", "")
		return
	}

	var status int
	err := db.QueryRow("SELECT status FROM auth_sites WHERE sitekey=? AND domain=?", sitekey, domain).Scan(&status)

	if err == sql.ErrNoRows {
		logAction("checkauth", sitekey, domain, ip, 0)
		log.Printf("[验证] 未授权 - %s", domain)
		jsonResponse(w, 0, "授权不存在", "域名或密钥不匹配")
		return
	}
	if err != nil {
		logAction("checkauth", sitekey, domain, ip, 0)
		jsonResponse(w, 0, "数据库错误", "查询失败")
		return
	}

	if status == 1 {
		logAction("checkauth", sitekey, domain, ip, 1)
		db.Exec("UPDATE auth_sites SET last_check_time=? WHERE sitekey=?", time.Now().Unix(), sitekey)
		log.Printf("[验证] 通过 - %s", domain)
		jsonResponse(w, 1, "授权有效", "")
	} else {
		logAction("checkauth", sitekey, domain, ip, -3)
		log.Printf("[验证] 被禁用 - %s", domain)
		jsonResponse(w, -3, "您的授权已被封禁，请联系管理员", "您的授权已被封禁，请联系管理员")
	}
}

func handleSendMail(w http.ResponseWriter, r *http.Request, domain, sitekey, ip string) {
	if domain == "" || sitekey == "" {
		jsonResponse(w, 0, "参数不完整", "")
		return
	}

	r.ParseForm()
	title := r.FormValue("title")
	content := r.FormValue("content")
	tomail := r.FormValue("tomail")

	log.Printf("[邮件] 收件人=%s 标题=%s", tomail, title)
	_ = content // 邮件功能预留

	logAction("sendmail", sitekey, domain, ip, 1)
	jsonResponse(w, 1, "发送成功", "")
}

// ============================================
// 管理 API
// ============================================

func apiListSites(w http.ResponseWriter, r *http.Request) {
	rows, err := db.Query("SELECT id,domain,sitekey,status,note,create_time,last_check_time FROM auth_sites ORDER BY id DESC")
	if err != nil {
		jsonOK(w, []AuthSite{})
		return
	}
	defer rows.Close()

	var sites []AuthSite
	for rows.Next() {
		var s AuthSite
		rows.Scan(&s.ID, &s.Domain, &s.Sitekey, &s.Status, &s.Note, &s.CreateTime, &s.LastCheckTime)
		sites = append(sites, s)
	}
	if sites == nil {
		sites = []AuthSite{}
	}
	jsonOK(w, sites)
}

func apiAddSite(w http.ResponseWriter, r *http.Request) {
	domain := r.FormValue("domain")
	sitekey := r.FormValue("sitekey")
	note := r.FormValue("note")

	if domain == "" || sitekey == "" || len(sitekey) < 16 {
		jsonOK(w, JSONResponse{"ok": false, "msg": "域名和密钥不能为空，密钥至少16位"})
		return
	}

	_, err := db.Exec("INSERT INTO auth_sites (domain,sitekey,status,note,create_time) VALUES (?,?,1,?,?)",
		domain, sitekey, note, time.Now().Unix())
	if err != nil {
		jsonOK(w, JSONResponse{"ok": false, "msg": "添加失败，域名或密钥可能已存在"})
		return
	}

	log.Printf("[授权] 添加成功 - %s", domain)
	jsonOK(w, JSONResponse{"ok": true, "msg": "添加成功"})
}

func apiToggleSite(w http.ResponseWriter, r *http.Request) {
	domain := r.FormValue("domain")
	status := r.FormValue("status") // "1" or "0"

	if domain == "" {
		jsonOK(w, JSONResponse{"ok": false, "msg": "域名不能为空"})
		return
	}

	db.Exec("UPDATE auth_sites SET status=? WHERE domain=?", status, domain)
	action := "启用"
	if status == "0" {
		action = "禁用"
	}
	log.Printf("[授权] 已%s - %s", action, domain)
	jsonOK(w, JSONResponse{"ok": true, "msg": "操作成功"})
}

func apiDeleteSite(w http.ResponseWriter, r *http.Request) {
	domain := r.FormValue("domain")

	if domain == "" {
		jsonOK(w, JSONResponse{"ok": false, "msg": "域名不能为空"})
		return
	}

	db.Exec("DELETE FROM auth_sites WHERE domain=?", domain)
	log.Printf("[授权] 已删除 - %s", domain)
	jsonOK(w, JSONResponse{"ok": true, "msg": "删除成功"})
}

func apiGenerateKey(w http.ResponseWriter, r *http.Request) {
	jsonOK(w, JSONResponse{"key": generateKey()})
}

func apiStats(w http.ResponseWriter, r *http.Request) {
	var total, active, disabled int
	db.QueryRow("SELECT COUNT(*) FROM auth_sites").Scan(&total)
	db.QueryRow("SELECT COUNT(*) FROM auth_sites WHERE status=1").Scan(&active)
	db.QueryRow("SELECT COUNT(*) FROM auth_sites WHERE status=0").Scan(&disabled)

	var todayLogs int
	db.QueryRow("SELECT COUNT(*) FROM auth_logs WHERE create_time > ?", time.Now().Unix()-86400).Scan(&todayLogs)

	jsonOK(w, JSONResponse{
		"total":      total,
		"active":     active,
		"disabled":   disabled,
		"today_logs": todayLogs,
	})
}

// ============================================
// CORS 中间件
// ============================================

func corsMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type")
		if r.Method == "OPTIONS" {
			w.WriteHeader(200)
			return
		}
		next.ServeHTTP(w, r)
	})
}

// ============================================
// 管理页面
// ============================================

func adminPage(w http.ResponseWriter, r *http.Request) {
	tmpl := template.Must(template.New("admin").Parse(adminHTML))
	tmpl.Execute(w, map[string]string{"Port": *port})
}

// ============================================
// 主函数
// ============================================

func main() {
	dbPath := flag.String("db", "auth.db", "数据库文件路径")
	flag.Parse()

	// 确保数据库目录存在
	dbDir := filepath.Dir(*dbPath)
	if dbDir != "." {
		os.MkdirAll(dbDir, 0755)
	}

	initDB(*dbPath)
	defer db.Close()

	mux := http.NewServeMux()

	// API 路由
	mux.HandleFunc("/api.php", apiHandler)
	mux.HandleFunc("/api/list", apiListSites)
	mux.HandleFunc("/api/add", apiAddSite)
	mux.HandleFunc("/api/toggle", apiToggleSite)
	mux.HandleFunc("/api/delete", apiDeleteSite)
	mux.HandleFunc("/api/genkey", apiGenerateKey)
	mux.HandleFunc("/api/stats", apiStats)

	// 管理页面
	mux.HandleFunc("/", adminPage)

	handler := corsMiddleware(mux)

	log.Printf("========================================")
	log.Printf("  小七授权服务端 (Go)")
	log.Printf("  端口: %s", *port)
	log.Printf("  数据库: %s", *dbPath)
	log.Printf("  API: http://0.0.0.0:%s/api.php", *port)
	log.Printf("  管理: http://0.0.0.0:%s/", *port)
	log.Printf("========================================")

	if err := http.ListenAndServe(":"+*port, handler); err != nil {
		log.Fatalf("启动失败: %v", err)
	}
}

// ============================================
// 内嵌管理页面 HTML
// ============================================

const adminHTML = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>小七授权服务端 - 管理面板</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;color:#333;min-height:100vh}
.header{background:linear-gradient(135deg,#6a11cb,#2575fc);color:#fff;padding:20px 30px;display:flex;justify-content:space-between;align-items:center}
.header h1{font-size:22px;font-weight:600}
.server-info{font-size:13px;opacity:0.85}
.container{max-width:1100px;margin:20px auto;padding:0 20px}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.stat-card .num{font-size:28px;font-weight:700;color:#6a11cb}
.stat-card .label{font-size:13px;color:#888;margin-top:5px}
.card{background:#fff;border-radius:10px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:20px}
.card h2{font-size:16px;margin-bottom:16px;color:#333;border-bottom:2px solid #6a11cb;padding-bottom:8px;display:inline-block}
.form-row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.form-row input{flex:1;min-width:150px;padding:10px 14px;border:1px solid #d9d9d9;border-radius:6px;font-size:14px;outline:none;transition:border-color 0.2s}
.form-row input:focus{border-color:#6a11cb;box-shadow:0 0 0 2px rgba(106,17,203,0.1)}
.btn{padding:10px 20px;border:none;border-radius:6px;font-size:14px;cursor:pointer;font-weight:500;transition:all 0.2s;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,#6a11cb,#2575fc);color:#fff}
.btn-primary:hover{opacity:0.9}
.btn-success{background:#52c41a;color:#fff}
.btn-success:hover{background:#49b018}
.btn-warning{background:#fa8c16;color:#fff}
.btn-danger{background:#ff4d4f;color:#fff}
.btn-sm{padding:4px 12px;font-size:12px}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:12px 8px;font-size:13px;color:#888;border-bottom:2px solid #f0f0f0;font-weight:500}
td{padding:10px 8px;font-size:13px;border-bottom:1px solid #f0f0f0}
tr:hover td{background:#fafafa}
.status-badge{padding:2px 8px;border-radius:10px;font-size:12px;font-weight:500}
.status-active{background:#f6ffed;color:#52c41a}
.status-disabled{background:#fff2f0;color:#ff4d4f}
.key-text{font-family:monospace;font-size:12px;color:#666;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block}
.empty{text-align:center;padding:40px;color:#999;font-size:14px}
.footer{text-align:center;padding:10px;color:#999;font-size:12px}
</style>
</head>
<body>
<div class="header">
    <h1>小七授权服务端</h1>
    <div class="server-info">API 端口: {{.Port}} | 数据库: SQLite</div>
</div>

<div class="container">
    <div class="stats">
        <div class="stat-card"><div class="num" id="statTotal">0</div><div class="label">总授权数</div></div>
        <div class="stat-card"><div class="num" id="statActive" style="color:#52c41a">0</div><div class="label">已启用</div></div>
        <div class="stat-card"><div class="num" id="statDisabled" style="color:#ff4d4f">0</div><div class="label">已禁用</div></div>
        <div class="stat-card"><div class="num" id="statToday" style="color:#1890ff">0</div><div class="label">今日请求</div></div>
    </div>

    <div class="card">
        <h2>添加授权</h2>
        <div class="form-row">
            <input type="text" id="addDomain" placeholder="域名 (如 example.com)" style="flex:2">
            <input type="text" id="addKey" placeholder="密钥 (点击生成)" style="flex:2">
            <button class="btn btn-primary" onclick="genKey()">生成密钥</button>
            <input type="text" id="addNote" placeholder="备注 (可选)" style="flex:1">
            <button class="btn btn-success" onclick="addSite()">添加授权</button>
        </div>
    </div>

    <div class="card">
        <h2>授权列表</h2>
        <table>
            <thead><tr><th>域名</th><th>密钥</th><th>状态</th><th>备注</th><th>创建时间</th><th>最后验证</th><th>操作</th></tr></thead>
            <tbody id="siteList"><tr><td colspan="7" class="empty">加载中...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="footer">小七授权服务端 v1.0 | Go + SQLite | 单文件部署</div>

<script>
function ts(n) {
    if (!n || n===0) return '从未验证';
    var d = new Date(n*1000);
    return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+' '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0');
}

function loadStats() {
    fetch('/api/stats').then(r=>r.json()).then(d=>{
        document.getElementById('statTotal').textContent = d.total;
        document.getElementById('statActive').textContent = d.active;
        document.getElementById('statDisabled').textContent = d.disabled;
        document.getElementById('statToday').textContent = d.today_logs;
    });
}

function loadSites() {
    fetch('/api/list').then(r=>r.json()).then(data=>{
        var tbody = document.getElementById('siteList');
        if (!data || data.length===0) {
            tbody.innerHTML = '<tr><td colspan="7" class="empty">暂无授权，请添加</td></tr>';
            return;
        }
        var html = '';
        data.forEach(function(s) {
            var badge = s.status===1 ? '<span class="status-badge status-active">正常</span>' : '<span class="status-badge status-disabled">禁用</span>';
            var toggleBtn = s.status===1
                ? '<button class="btn btn-warning btn-sm" onclick="toggleSite(\''+s.domain+'\',0)">禁用</button>'
                : '<button class="btn btn-success btn-sm" onclick="toggleSite(\''+s.domain+'\',1)">启用</button>';
            html += '<tr>'+
                '<td><b>'+s.domain+'</b></td>'+
                '<td><span class="key-text" title="'+s.sitekey+'">'+s.sitekey.substring(0,8)+'...</span></td>'+
                '<td>'+badge+'</td>'+
                '<td>'+s.note+'</td>'+
                '<td>'+ts(s.create_time)+'</td>'+
                '<td>'+ts(s.last_check_time)+'</td>'+
                '<td>'+toggleBtn+' <button class="btn btn-danger btn-sm" onclick="delSite(\''+s.domain+'\')">删除</button></td>'+
                '</tr>';
        });
        tbody.innerHTML = html;
    });
}

function genKey() {
    fetch('/api/genkey').then(r=>r.json()).then(d=>{
        document.getElementById('addKey').value = d.key;
    });
}

function addSite() {
    var domain = document.getElementById('addDomain').value.trim();
    var key = document.getElementById('addKey').value.trim();
    var note = document.getElementById('addNote').value.trim();
    if (!domain || !key) return alert('域名和密钥不能为空！');
    if (key.length < 16) return alert('密钥至少16位！');
    var fd = new FormData();
    fd.append('domain',domain);
    fd.append('sitekey',key);
    fd.append('note',note);
    fetch('/api/add',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            alert('添加成功！\n\n密钥: '+key);
            document.getElementById('addDomain').value = '';
            document.getElementById('addKey').value = '';
            document.getElementById('addNote').value = '';
            loadSites();
            loadStats();
        } else {
            alert(d.msg);
        }
    });
}

function toggleSite(domain, status) {
    var fd = new FormData();
    fd.append('domain',domain);
    fd.append('status',status);
    fetch('/api/toggle',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadSites(); loadStats(); }
    });
}

function delSite(domain) {
    if (!confirm('确定删除 '+domain+' 吗？此操作不可恢复！')) return;
    var fd = new FormData();
    fd.append('domain',domain);
    fetch('/api/delete',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadSites(); loadStats(); }
    });
}

loadStats();
loadSites();
setInterval(loadStats, 30000);
</script>
</body>
</html>`