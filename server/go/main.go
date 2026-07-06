package main

import (
	"crypto/rand"
	"crypto/sha256"
	"database/sql"
	"encoding/csv"
	"encoding/hex"
	"encoding/json"
	"flag"
	"fmt"
	"html/template"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	_ "modernc.org/sqlite"
)

var db *sql.DB
var port = flag.String("port", "99", "监听端口")
var adminPass = flag.String("pass", "admin123", "管理面板登录密码")

// ============================================
// 数据结构
// ============================================

type AuthSite struct {
	ID            int64  `json:"id"`
	Domain        string `json:"domain"`
	Sitekey       string `json:"sitekey"`
	Status        int    `json:"status"`
	Note          string `json:"note"`
	AuthLevel     int    `json:"auth_level"`
	IPWhitelist   string `json:"ip_whitelist"`
	ExpireTime    int64  `json:"expire_time"`
	CreateTime    int64  `json:"create_time"`
	LastCheckTime int64  `json:"last_check_time"`
}

type AlertInfo struct {
	Domain      string `json:"domain"`
	FailCount   int    `json:"fail_count"`
	LastFail    int64  `json:"last_fail"`
	LastFailMsg string `json:"last_fail_msg"`
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

	// 核心表
	db.Exec(`CREATE TABLE IF NOT EXISTS auth_sites (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		domain TEXT NOT NULL,
		sitekey TEXT NOT NULL UNIQUE,
		status INTEGER DEFAULT 1,
		note TEXT DEFAULT '',
		auth_level INTEGER DEFAULT 1,
		ip_whitelist TEXT DEFAULT '',
		expire_time INTEGER DEFAULT 0,
		create_time INTEGER NOT NULL,
		last_check_time INTEGER DEFAULT 0
	)`)
	db.Exec(`CREATE TABLE IF NOT EXISTS auth_logs (
		id INTEGER PRIMARY KEY AUTOINCREMENT,
		sitekey TEXT NOT NULL,
		domain TEXT NOT NULL,
		action TEXT NOT NULL,
		ip TEXT NOT NULL,
		result INTEGER DEFAULT 0,
		create_time INTEGER NOT NULL
	)`)
	db.Exec(`CREATE TABLE IF NOT EXISTS auth_config (
		key TEXT PRIMARY KEY,
		value TEXT DEFAULT ''
	)`)
	db.Exec(`CREATE TABLE IF NOT EXISTS auth_alerts (
		domain TEXT PRIMARY KEY,
		fail_count INTEGER DEFAULT 0,
		last_fail INTEGER DEFAULT 0,
		last_fail_msg TEXT DEFAULT ''
	)`)

	// 迁移旧表加新字段
	db.Exec("ALTER TABLE auth_sites ADD COLUMN auth_level INTEGER DEFAULT 1")
	db.Exec("ALTER TABLE auth_sites ADD COLUMN ip_whitelist TEXT DEFAULT ''")
	db.Exec("ALTER TABLE auth_sites ADD COLUMN expire_time INTEGER DEFAULT 0")

	// 初始化默认密码
	hash := sha256Hex(*adminPass)
	db.Exec("INSERT OR IGNORE INTO auth_config (key,value) VALUES ('admin_pass','" + hash + "')")

	log.Println("[数据库] 初始化完成")
}

func sha256Hex(s string) string {
	h := sha256.Sum256([]byte(s))
	return hex.EncodeToString(h[:])
}

// ============================================
// 工具函数
// ============================================

func generateKey() string {
	b := make([]byte, 32)
	rand.Read(b)
	return hex.EncodeToString(b)
}

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
		return strings.Split(ip, ",")[0]
	}
	if ip := r.Header.Get("X-Real-IP"); ip != "" {
		return ip
	}
	return strings.Split(r.RemoteAddr, ":")[0]
}

func logAction(action, sitekey, domain, ip string, result int) {
	db.Exec("INSERT INTO auth_logs (sitekey,domain,action,ip,result,create_time) VALUES (?,?,?,?,?,?)",
		sitekey, domain, action, ip, result, time.Now().Unix())
}

func getConfig(key string) string {
	var v string
	db.QueryRow("SELECT value FROM auth_config WHERE key=?", key).Scan(&v)
	return v
}

func setConfig(key, value string) {
	db.Exec("INSERT OR REPLACE INTO auth_config (key,value) VALUES (?,?)", key, value)
}

// ============================================
// 授权验证（含到期/白名单/版本）
// ============================================

func apiHandler(w http.ResponseWriter, r *http.Request) {
	mod := r.URL.Query().Get("mod")
	domain := r.URL.Query().Get("domain")
	sitekey := r.URL.Query().Get("sitekey")
	ver := r.URL.Query().Get("ver")
	ip := clientIP(r)

	log.Printf("[请求] %s -> mod=%s domain=%s ver=%s", ip, mod, domain, ver)

	switch mod {
	case "checkauth":
		handleCheckAuth(w, domain, sitekey, ip, ver)
	case "sendmail":
		handleSendMail(w, r, domain, sitekey, ip)
	default:
		jsonResponse(w, 0, "未知模块", "")
	}
}

func handleCheckAuth(w http.ResponseWriter, domain, sitekey, ip, ver string) {
	if domain == "" || sitekey == "" {
		jsonResponse(w, 0, "参数不完整", "")
		return
	}

	var s AuthSite
	err := db.QueryRow("SELECT id,status,auth_level,ip_whitelist,expire_time FROM auth_sites WHERE sitekey=? AND domain=?",
		sitekey, domain).Scan(&s.ID, &s.Status, &s.AuthLevel, &s.IPWhitelist, &s.ExpireTime)

	if err == sql.ErrNoRows {
		logAction("checkauth", sitekey, domain, ip, 0)
		recordAlert(domain, "授权不存在")
		jsonResponse(w, 0, "授权不存在", "域名或密钥不匹配")
		return
	}
	if err != nil {
		logAction("checkauth", sitekey, domain, ip, 0)
		jsonResponse(w, 0, "数据库错误", "查询失败")
		return
	}

	// 状态检查
	if s.Status != 1 {
		logAction("checkauth", sitekey, domain, ip, -3)
		recordAlert(domain, "授权已被封禁")
		jsonResponse(w, -3, "您的授权已被封禁，请联系管理员", "您的授权已被封禁，请联系管理员")
		return
	}

	// 到期检查
	if s.ExpireTime > 0 && time.Now().Unix() > s.ExpireTime {
		db.Exec("UPDATE auth_sites SET status=0 WHERE id=?", s.ID)
		logAction("checkauth", sitekey, domain, ip, -4)
		recordAlert(domain, "授权已到期")
		jsonResponse(w, -4, "授权已到期，请联系管理员续费", "授权已到期，请联系管理员续费")
		return
	}

	// IP白名单检查
	if s.IPWhitelist != "" {
		allowed := strings.Split(s.IPWhitelist, ",")
		matched := false
		for _, a := range allowed {
			a = strings.TrimSpace(a)
			if a == ip || a == "*" {
				matched = true
				break
			}
		}
		if !matched {
			logAction("checkauth", sitekey, domain, ip, -5)
			recordAlert(domain, "IP不在白名单: "+ip)
			jsonResponse(w, -5, "IP不在白名单中", "当前IP("+ip+")未授权，请联系管理员添加白名单")
			return
		}
	}

	// 版本绑定检查
	minVer := getConfig("min_version_" + fmt.Sprint(s.AuthLevel))
	if minVer != "" && ver != "" && compareVer(ver, minVer) < 0 {
		jsonResponse(w, -6, "版本过低，请升级", "当前版本:"+ver+", 要求最低版本:"+minVer)
		return
	}

	// 全部通过
	logAction("checkauth", sitekey, domain, ip, 1)
	db.Exec("UPDATE auth_sites SET last_check_time=? WHERE id=?", time.Now().Unix(), s.ID)
	clearAlert(domain)

	// 返回授权信息
	resp := JSONResponse{"code": 1, "msg": "授权有效", "level": s.AuthLevel}
	if s.ExpireTime > 0 {
		resp["expire"] = s.ExpireTime
	}
	jsonOK(w, resp)
	log.Printf("[验证] 通过 - %s Lv%d", domain, s.AuthLevel)
}

func compareVer(a, b string) int {
	// 简单版本比较 x.y.z
	ap := strings.Split(a, ".")
	bp := strings.Split(b, ".")
	for i := 0; i < 3; i++ {
		var av, bv int
		if i < len(ap) {
			fmt.Sscanf(ap[i], "%d", &av)
		}
		if i < len(bp) {
			fmt.Sscanf(bp[i], "%d", &bv)
		}
		if av > bv {
			return 1
		}
		if av < bv {
			return -1
		}
	}
	return 0
}

func recordAlert(domain, msg string) {
	db.Exec("INSERT INTO auth_alerts (domain,fail_count,last_fail,last_fail_msg) VALUES (?,1,?,?) ON CONFLICT(domain) DO UPDATE SET fail_count=fail_count+1, last_fail=?, last_fail_msg=?",
		domain, time.Now().Unix(), msg, time.Now().Unix(), msg)
}

func clearAlert(domain string) {
	db.Exec("DELETE FROM auth_alerts WHERE domain=?", domain)
}

func handleSendMail(w http.ResponseWriter, r *http.Request, domain, sitekey, ip string) {
	if domain == "" || sitekey == "" {
		jsonResponse(w, 0, "参数不完整", "")
		return
	}
	r.ParseForm()
	log.Printf("[邮件] 收件人=%s 标题=%s", r.FormValue("tomail"), r.FormValue("title"))
	logAction("sendmail", sitekey, domain, ip, 1)
	jsonResponse(w, 1, "发送成功", "")
}

// ============================================
// 管理 API — 登录
// ============================================

func apiLogin(w http.ResponseWriter, r *http.Request) {
	pass := r.FormValue("pass")
	if sha256Hex(pass) == getConfig("admin_pass") {
		token := generateKey()
		setConfig("session_token", token)
		jsonOK(w, JSONResponse{"ok": true, "token": token})
	} else {
		jsonOK(w, JSONResponse{"ok": false, "msg": "密码错误"})
	}
}

func apiChangePass(w http.ResponseWriter, r *http.Request) {
	old := r.FormValue("old")
	newPass := r.FormValue("new")
	if sha256Hex(old) != getConfig("admin_pass") {
		jsonOK(w, JSONResponse{"ok": false, "msg": "原密码错误"})
		return
	}
	setConfig("admin_pass", sha256Hex(newPass))
	jsonOK(w, JSONResponse{"ok": true, "msg": "密码修改成功"})
}

func checkAuth(r *http.Request) bool {
	token := r.Header.Get("X-Auth-Token")
	if token == "" {
		token = r.URL.Query().Get("token")
	}
	if token == "" {
		return false
	}
	return token == getConfig("session_token")
}

func authMiddleware(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		if !checkAuth(r) {
			jsonOK(w, JSONResponse{"ok": false, "msg": "未登录"})
			return
		}
		next(w, r)
	}
}

// ============================================
// 管理 API — 授权 CRUD
// ============================================

func apiListSites(w http.ResponseWriter, r *http.Request) {
	rows, err := db.Query("SELECT id,domain,sitekey,status,note,auth_level,ip_whitelist,expire_time,create_time,last_check_time FROM auth_sites ORDER BY id DESC")
	if err != nil {
		jsonOK(w, []AuthSite{})
		return
	}
	defer rows.Close()
	var sites []AuthSite
	for rows.Next() {
		var s AuthSite
		rows.Scan(&s.ID, &s.Domain, &s.Sitekey, &s.Status, &s.Note, &s.AuthLevel, &s.IPWhitelist, &s.ExpireTime, &s.CreateTime, &s.LastCheckTime)
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
	ipWhitelist := r.FormValue("ip_whitelist")
	authLevel := atoi(r.FormValue("auth_level"), 1)
	expireDays := atoi(r.FormValue("expire_days"), 0)

	if domain == "" || sitekey == "" || len(sitekey) < 16 {
		jsonOK(w, JSONResponse{"ok": false, "msg": "域名和密钥不能为空，密钥至少16位"})
		return
	}

	var expireTime int64
	if expireDays > 0 {
		expireTime = time.Now().Unix() + int64(expireDays)*86400
	}

	_, err := db.Exec("INSERT INTO auth_sites (domain,sitekey,status,note,auth_level,ip_whitelist,expire_time,create_time) VALUES (?,?,1,?,?,?,?,?)",
		domain, sitekey, note, authLevel, ipWhitelist, expireTime, time.Now().Unix())
	if err != nil {
		jsonOK(w, JSONResponse{"ok": false, "msg": "添加失败，域名或密钥可能已存在"})
		return
	}
	log.Printf("[授权] 添加成功 - %s Lv%d %d天", domain, authLevel, expireDays)
	jsonOK(w, JSONResponse{"ok": true, "msg": "添加成功"})
}

func apiUpdateSite(w http.ResponseWriter, r *http.Request) {
	domain := r.FormValue("domain")
	if domain == "" {
		jsonOK(w, JSONResponse{"ok": false, "msg": "域名不能为空"})
		return
	}

	if v := r.FormValue("status"); v != "" {
		db.Exec("UPDATE auth_sites SET status=? WHERE domain=?", v, domain)
	}
	if v := r.FormValue("note"); v != "" {
		db.Exec("UPDATE auth_sites SET note=? WHERE domain=?", v, domain)
	}
	if v := r.FormValue("auth_level"); v != "" {
		db.Exec("UPDATE auth_sites SET auth_level=? WHERE domain=?", v, domain)
	}
	if v := r.FormValue("ip_whitelist"); v != "" {
		db.Exec("UPDATE auth_sites SET ip_whitelist=? WHERE domain=?", v, domain)
	} else if r.FormValue("clear_ip") == "1" {
		db.Exec("UPDATE auth_sites SET ip_whitelist='' WHERE domain=?", domain)
	}
	if v := r.FormValue("expire_days"); v != "" {
		days := atoi(v, 0)
		var expireTime int64
		if days > 0 {
			// 从当前到期时间续期，如果已到期则从今天开始
			var curExpire int64
			db.QueryRow("SELECT expire_time FROM auth_sites WHERE domain=?", domain).Scan(&curExpire)
			base := time.Now().Unix()
			if curExpire > base {
				base = curExpire
			}
			expireTime = base + int64(days)*86400
		}
		db.Exec("UPDATE auth_sites SET expire_time=? WHERE domain=?", expireTime, domain)
	}
	if v := r.FormValue("sitekey"); v != "" && len(v) >= 16 {
		db.Exec("UPDATE auth_sites SET sitekey=? WHERE domain=?", v, domain)
	}

	log.Printf("[授权] 已更新 - %s", domain)
	jsonOK(w, JSONResponse{"ok": true, "msg": "更新成功"})
}

func apiToggleSite(w http.ResponseWriter, r *http.Request) {
	domain := r.FormValue("domain")
	status := r.FormValue("status")
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
	db.Exec("DELETE FROM auth_alerts WHERE domain=?", domain)
	log.Printf("[授权] 已删除 - %s", domain)
	jsonOK(w, JSONResponse{"ok": true, "msg": "删除成功"})
}

func apiGenerateKey(w http.ResponseWriter, r *http.Request) {
	jsonOK(w, JSONResponse{"key": generateKey()})
}

func apiStats(w http.ResponseWriter, r *http.Request) {
	var total, active, disabled, expired int
	db.QueryRow("SELECT COUNT(*) FROM auth_sites").Scan(&total)
	db.QueryRow("SELECT COUNT(*) FROM auth_sites WHERE status=1").Scan(&active)
	db.QueryRow("SELECT COUNT(*) FROM auth_sites WHERE status=0").Scan(&disabled)
	db.QueryRow("SELECT COUNT(*) FROM auth_sites WHERE expire_time>0 AND expire_time<? AND status=1", time.Now().Unix()).Scan(&expired)

	var todayLogs int
	db.QueryRow("SELECT COUNT(*) FROM auth_logs WHERE create_time > ?", time.Now().Unix()-86400).Scan(&todayLogs)

	var alertCount int
	db.QueryRow("SELECT COUNT(*) FROM auth_alerts").Scan(&alertCount)

	jsonOK(w, JSONResponse{
		"total":        total,
		"active":       active,
		"disabled":     disabled,
		"expiring":     expired,
		"today_logs":   todayLogs,
		"alert_count":  alertCount,
	})
}

// ============================================
// 管理 API — 告警
// ============================================

func apiAlerts(w http.ResponseWriter, r *http.Request) {
	rows, err := db.Query("SELECT domain,fail_count,last_fail,last_fail_msg FROM auth_alerts WHERE fail_count>=3 ORDER BY fail_count DESC")
	if err != nil {
		jsonOK(w, []AlertInfo{})
		return
	}
	defer rows.Close()
	var alerts []AlertInfo
	for rows.Next() {
		var a AlertInfo
		rows.Scan(&a.Domain, &a.FailCount, &a.LastFail, &a.LastFailMsg)
		alerts = append(alerts, a)
	}
	if alerts == nil {
		alerts = []AlertInfo{}
	}
	jsonOK(w, alerts)
}

func apiClearAlert(w http.ResponseWriter, r *http.Request) {
	domain := r.FormValue("domain")
	if domain == "" {
		db.Exec("DELETE FROM auth_alerts")
	} else {
		db.Exec("DELETE FROM auth_alerts WHERE domain=?", domain)
	}
	jsonOK(w, JSONResponse{"ok": true})
}

// ============================================
// 管理 API — 导入导出
// ============================================

func apiExportCSV(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "text/csv; charset=utf-8")
	w.Header().Set("Content-Disposition", "attachment; filename=auth_export.csv")
	// BOM for Excel
	w.Write([]byte{0xEF, 0xBB, 0xBF})

	writer := csv.NewWriter(w)
	writer.Write([]string{"域名", "密钥", "状态", "备注", "授权等级", "IP白名单", "到期时间", "创建时间", "最后验证"})

	rows, _ := db.Query("SELECT domain,sitekey,status,note,auth_level,ip_whitelist,expire_time,create_time,last_check_time FROM auth_sites ORDER BY id")
	defer rows.Close()
	for rows.Next() {
		var s AuthSite
		rows.Scan(&s.Domain, &s.Sitekey, &s.Status, &s.Note, &s.AuthLevel, &s.IPWhitelist, &s.ExpireTime, &s.CreateTime, &s.LastCheckTime)
		statusStr := "正常"
		if s.Status == 0 {
			statusStr = "禁用"
		}
		expireStr := "永久"
		if s.ExpireTime > 0 {
			expireStr = time.Unix(s.ExpireTime, 0).Format("2006-01-02")
		}
		writer.Write([]string{
			s.Domain, s.Sitekey, statusStr, s.Note,
			fmt.Sprint(s.AuthLevel), s.IPWhitelist, expireStr,
			time.Unix(s.CreateTime, 0).Format("2006-01-02 15:04"),
			time.Unix(s.LastCheckTime, 0).Format("2006-01-02 15:04"),
		})
	}
	writer.Flush()
}

func apiImportCSV(w http.ResponseWriter, r *http.Request) {
	r.ParseMultipartForm(10 << 20)
	file, _, err := r.FormFile("file")
	if err != nil {
		jsonOK(w, JSONResponse{"ok": false, "msg": "请选择CSV文件"})
		return
	}
	defer file.Close()

	reader := csv.NewReader(file)
	records, err := reader.ReadAll()
	if err != nil || len(records) < 2 {
		jsonOK(w, JSONResponse{"ok": false, "msg": "CSV格式错误"})
		return
	}

	imported := 0
	skipped := 0
	for i, row := range records {
		if i == 0 {
			continue // 跳过表头
		}
		if len(row) < 2 || row[0] == "" || row[1] == "" {
			skipped++
			continue
		}
		domain := strings.TrimSpace(row[0])
		sitekey := strings.TrimSpace(row[1])
		note := ""
		authLevel := 1
		ipWhitelist := ""
		expireTime := int64(0)
		status := 1

		if len(row) > 2 {
			switch strings.TrimSpace(row[2]) {
			case "禁用", "0":
				status = 0
			}
		}
		if len(row) > 3 {
			note = strings.TrimSpace(row[3])
		}
		if len(row) > 4 {
			authLevel = atoi(strings.TrimSpace(row[4]), 1)
		}
		if len(row) > 5 {
			ipWhitelist = strings.TrimSpace(row[5])
		}
		if len(row) > 6 {
			expireStr := strings.TrimSpace(row[6])
			if expireStr != "" && expireStr != "永久" {
				if t, err := time.Parse("2006-01-02", expireStr); err == nil {
					expireTime = t.Unix()
				}
			}
		}

		_, err := db.Exec("INSERT INTO auth_sites (domain,sitekey,status,note,auth_level,ip_whitelist,expire_time,create_time) VALUES (?,?,?,?,?,?,?,?)",
			domain, sitekey, status, note, authLevel, ipWhitelist, expireTime, time.Now().Unix())
		if err != nil {
			skipped++
		} else {
			imported++
		}
	}

	log.Printf("[导入] 成功%d 跳过%d", imported, skipped)
	jsonOK(w, JSONResponse{"ok": true, "msg": fmt.Sprintf("导入成功%d条，跳过%d条", imported, skipped), "imported": imported, "skipped": skipped})
}

// ============================================
// 管理 API — 版本配置
// ============================================

func apiGetVersionConfig(w http.ResponseWriter, r *http.Request) {
	jsonOK(w, JSONResponse{
		"min_ver_1": getConfig("min_version_1"),
		"min_ver_2": getConfig("min_version_2"),
		"min_ver_3": getConfig("min_version_3"),
	})
}

func apiSetVersionConfig(w http.ResponseWriter, r *http.Request) {
	for _, lv := range []string{"1", "2", "3"} {
		if v := r.FormValue("min_ver_" + lv); v != "" {
			setConfig("min_version_"+lv, v)
		}
	}
	jsonOK(w, JSONResponse{"ok": true, "msg": "版本配置已更新"})
}

// ============================================
// 辅助函数
// ============================================

func atoi(s string, def int) int {
	if s == "" {
		return def
	}
	var v int
	fmt.Sscanf(s, "%d", &v)
	if v <= 0 {
		return def
	}
	return v
}

// ============================================
// 路由
// ============================================

func main() {
	dbPath := flag.String("db", "auth.db", "数据库文件路径")
	flag.Parse()

	dbDir := filepath.Dir(*dbPath)
	if dbDir != "." {
		os.MkdirAll(dbDir, 0755)
	}

	initDB(*dbPath)
	defer db.Close()

	mux := http.NewServeMux()

	// 公开 API
	mux.HandleFunc("/api.php", apiHandler)
	mux.HandleFunc("/api/login", apiLogin)

	// 需要认证的管理 API
	mux.HandleFunc("/api/list", authMiddleware(apiListSites))
	mux.HandleFunc("/api/add", authMiddleware(apiAddSite))
	mux.HandleFunc("/api/update", authMiddleware(apiUpdateSite))
	mux.HandleFunc("/api/toggle", authMiddleware(apiToggleSite))
	mux.HandleFunc("/api/delete", authMiddleware(apiDeleteSite))
	mux.HandleFunc("/api/genkey", authMiddleware(apiGenerateKey))
	mux.HandleFunc("/api/stats", authMiddleware(apiStats))
	mux.HandleFunc("/api/alerts", authMiddleware(apiAlerts))
	mux.HandleFunc("/api/clear_alert", authMiddleware(apiClearAlert))
	mux.HandleFunc("/api/export", authMiddleware(apiExportCSV))
	mux.HandleFunc("/api/import", authMiddleware(apiImportCSV))
	mux.HandleFunc("/api/version_config", authMiddleware(apiGetVersionConfig))
	mux.HandleFunc("/api/set_version", authMiddleware(apiSetVersionConfig))
	mux.HandleFunc("/api/change_pass", authMiddleware(apiChangePass))

	// 管理页面
	mux.HandleFunc("/", adminPage)

	handler := corsMiddleware(mux)

	log.Printf("========================================")
	log.Printf("  小七授权服务端 v2.0 (Go)")
	log.Printf("  端口: %s", *port)
	log.Printf("  数据库: %s", *dbPath)
	log.Printf("  管理: http://0.0.0.0:%s/", *port)
	log.Printf("  默认密码: %s", *adminPass)
	log.Printf("========================================")

	if err := http.ListenAndServe(":"+*port, handler); err != nil {
		log.Fatalf("启动失败: %v", err)
	}
}

func corsMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Access-Control-Allow-Origin", "*")
		w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
		w.Header().Set("Access-Control-Allow-Headers", "Content-Type, X-Auth-Token")
		if r.Method == "OPTIONS" {
			w.WriteHeader(200)
			return
		}
		next.ServeHTTP(w, r)
	})
}

func adminPage(w http.ResponseWriter, r *http.Request) {
	tmpl := template.Must(template.New("admin").Parse(adminHTML))
	tmpl.Execute(w, map[string]string{"Port": *port})
}

// ============================================
// 管理面板 HTML
// ============================================

const adminHTML = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>小七授权服务端 v2.0</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;color:#333;min-height:100vh}
.header{background:linear-gradient(135deg,#6a11cb,#2575fc);color:#fff;padding:16px 30px;display:flex;justify-content:space-between;align-items:center}
.header h1{font-size:20px;font-weight:600}
.header-right{display:flex;align-items:center;gap:16px}
.header-right span{font-size:13px;opacity:0.85}
.header-right button{background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.3);color:#fff;padding:6px 16px;border-radius:6px;cursor:pointer;font-size:13px}
.header-right button:hover{background:rgba(255,255,255,0.3)}
.container{max-width:1200px;margin:20px auto;padding:0 20px}
.stats{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:10px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center}
.stat-card .num{font-size:24px;font-weight:700;color:#6a11cb}
.stat-card .label{font-size:12px;color:#888;margin-top:4px}
.card{background:#fff;border-radius:10px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,0.06);margin-bottom:16px}
.card h2{font-size:15px;margin-bottom:14px;color:#333;border-bottom:2px solid #6a11cb;padding-bottom:6px;display:inline-block}
.form-row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.form-row input,.form-row select{flex:1;min-width:100px;padding:8px 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:13px;outline:none}
.form-row input:focus,.form-row select:focus{border-color:#6a11cb;box-shadow:0 0 0 2px rgba(106,17,203,0.1)}
.btn{padding:8px 16px;border:none;border-radius:6px;font-size:13px;cursor:pointer;font-weight:500;white-space:nowrap}
.btn-primary{background:linear-gradient(135deg,#6a11cb,#2575fc);color:#fff}
.btn-success{background:#52c41a;color:#fff}
.btn-warning{background:#fa8c16;color:#fff}
.btn-danger{background:#ff4d4f;color:#fff}
.btn-outline{background:#fff;color:#6a11cb;border:1px solid #6a11cb}
.btn-sm{padding:4px 10px;font-size:12px}
.btn-xs{padding:2px 8px;font-size:11px}
table{width:100%;border-collapse:collapse}
th{text-align:left;padding:10px 6px;font-size:12px;color:#888;border-bottom:2px solid #f0f0f0;font-weight:500;white-space:nowrap}
td{padding:8px 6px;font-size:12px;border-bottom:1px solid #f0f0f0}
tr:hover td{background:#fafafa}
.badge{padding:2px 8px;border-radius:10px;font-size:11px;font-weight:500}
.badge-active{background:#f6ffed;color:#52c41a}
.badge-disabled{background:#fff2f0;color:#ff4d4f}
.badge-expired{background:#fff7e6;color:#fa8c16}
.badge-lv1{background:#e6f7ff;color:#1890ff}
.badge-lv2{background:#f9f0ff;color:#722ed1}
.badge-lv3{background:#fff7e6;color:#d48806}
.key-text{font-family:monospace;font-size:11px;color:#999;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block}
.empty{text-align:center;padding:40px;color:#999;font-size:14px}
.modal-bg{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100;justify-content:center;align-items:center}
.modal-bg.show{display:flex}
.modal{background:#fff;border-radius:12px;padding:24px;width:500px;max-height:80vh;overflow-y:auto}
.modal h3{margin-bottom:16px;color:#333}
.modal .form-group{margin-bottom:12px}
.modal .form-group label{display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:#555}
.modal .form-group input,.modal .form-group select{width:100%;padding:8px 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:13px}
.modal .btn-row{display:flex;gap:8px;justify-content:flex-end;margin-top:16px}
.tabs{display:flex;gap:0;margin-bottom:16px;border-bottom:1px solid #e8e8e8}
.tab{padding:8px 20px;cursor:pointer;font-size:14px;color:#666;border-bottom:2px solid transparent}
.tab.active{color:#6a11cb;border-bottom-color:#6a11cb;font-weight:600}
.tab-content{display:none}
.tab-content.active{display:block}
.alert-row{background:#fff2f0}
.alert-row td{color:#ff4d4f}
.login-box{max-width:360px;margin:100px auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 20px rgba(0,0,0,0.1);text-align:center}
.login-box h2{margin-bottom:24px;color:#6a11cb}
.login-box input{width:100%;padding:12px;border:1px solid #d9d9d9;border-radius:8px;font-size:14px;margin-bottom:16px;outline:none}
.login-box input:focus{border-color:#6a11cb}
.login-box .btn{width:100%;padding:12px;font-size:15px}
.footer{text-align:center;padding:10px;color:#999;font-size:12px}
</style>
</head>
<body>
<div id="loginPage">
    <div class="login-box">
        <h2>🔐 小七授权服务端 v2.0</h2>
        <input type="password" id="loginPass" placeholder="请输入管理密码" onkeydown="if(event.key==='Enter')doLogin()">
        <button class="btn btn-primary" onclick="doLogin()">登录</button>
        <p id="loginError" style="color:#ff4d4f;margin-top:12px;font-size:13px;display:none"></p>
    </div>
</div>

<div id="mainPage" style="display:none">
<div class="header">
    <h1>小七授权服务端 v2.0</h1>
    <div class="header-right">
        <span>API: {{.Port}}</span>
        <button onclick="doLogout()">退出</button>
    </div>
</div>

<div class="container">
    <div class="stats">
        <div class="stat-card"><div class="num" id="statTotal">0</div><div class="label">总授权</div></div>
        <div class="stat-card"><div class="num" id="statActive" style="color:#52c41a">0</div><div class="label">已启用</div></div>
        <div class="stat-card"><div class="num" id="statDisabled" style="color:#ff4d4f">0</div><div class="label">已禁用</div></div>
        <div class="stat-card"><div class="num" id="statExpiring" style="color:#fa8c16">0</div><div class="label">即将到期</div></div>
        <div class="stat-card"><div class="num" id="statToday" style="color:#1890ff">0</div><div class="label">今日请求</div></div>
        <div class="stat-card" style="cursor:pointer" onclick="showAlerts()"><div class="num" id="statAlert" style="color:#ff4d4f">0</div><div class="label">异常告警</div></div>
    </div>

    <div class="tabs">
        <div class="tab active" onclick="switchTab('list')">授权列表</div>
        <div class="tab" onclick="switchTab('add')">添加授权</div>
        <div class="tab" onclick="switchTab('tools')">批量工具</div>
        <div class="tab" onclick="switchTab('settings')">系统设置</div>
    </div>

    <div id="tab-list" class="tab-content active">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <h2 style="margin-bottom:0">授权列表</h2>
                <div>
                    <input type="text" id="searchInput" placeholder="搜索域名..." style="padding:6px 12px;border:1px solid #d9d9d9;border-radius:6px;font-size:13px;width:200px;outline:none" oninput="filterSites()">
                    <button class="btn btn-outline btn-sm" onclick="loadSites()">刷新</button>
                </div>
            </div>
            <table>
                <thead><tr>
                    <th>域名</th><th>密钥</th><th>等级</th><th>IP白名单</th><th>到期</th><th>状态</th><th>备注</th><th>最后验证</th><th>操作</th>
                </tr></thead>
                <tbody id="siteList"><tr><td colspan="9" class="empty">加载中...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div id="tab-add" class="tab-content">
        <div class="card">
            <h2>添加授权</h2>
            <div class="form-row">
                <input type="text" id="addDomain" placeholder="域名 *" style="flex:2">
                <input type="text" id="addKey" placeholder="密钥 *" style="flex:2">
                <button class="btn btn-primary" onclick="genKey()">生成密钥</button>
                <select id="addLevel" style="flex:0.5"><option value="1">基础版</option><option value="2">高级版</option><option value="3">旗舰版</option></select>
                <input type="text" id="addIP" placeholder="IP白名单(逗号分隔)" style="flex:1.5">
                <input type="number" id="addDays" placeholder="天数(0=永久)" style="flex:0.8" value="0" min="0">
                <input type="text" id="addNote" placeholder="备注" style="flex:1">
                <button class="btn btn-success" onclick="addSite()">添加</button>
            </div>
        </div>
    </div>

    <div id="tab-tools" class="tab-content">
        <div class="card">
            <h2>批量导入</h2>
            <div class="form-row">
                <input type="file" id="csvFile" accept=".csv" style="flex:2">
                <button class="btn btn-primary" onclick="importCSV()">导入CSV</button>
                <button class="btn btn-outline" onclick="exportCSV()">导出全部</button>
            </div>
            <div style="margin-top:8px;font-size:12px;color:#999">
                CSV格式：域名,密钥,状态,备注,授权等级,IP白名单,到期时间(YYYY-MM-DD)
            </div>
        </div>
    </div>

    <div id="tab-settings" class="tab-content">
        <div class="card">
            <h2>版本绑定</h2>
            <div class="form-row">
                <label style="font-size:13px;font-weight:600">基础版最低版本:</label>
                <input type="text" id="minVer1" placeholder="如 12.0" style="flex:1">
                <label style="font-size:13px;font-weight:600">高级版最低版本:</label>
                <input type="text" id="minVer2" placeholder="如 12.5" style="flex:1">
                <label style="font-size:13px;font-weight:600">旗舰版最低版本:</label>
                <input type="text" id="minVer3" placeholder="如 13.0" style="flex:1">
                <button class="btn btn-primary" onclick="saveVersionConfig()">保存</button>
            </div>
        </div>
        <div class="card">
            <h2>修改密码</h2>
            <div class="form-row">
                <input type="password" id="oldPass" placeholder="原密码" style="flex:1">
                <input type="password" id="newPass" placeholder="新密码" style="flex:1">
                <button class="btn btn-primary" onclick="changePass()">修改</button>
            </div>
        </div>
    </div>
</div>
<div class="footer">小七授权服务端 v2.0 | Go + SQLite</div>
</div>

<!-- 编辑弹窗 -->
<div class="modal-bg" id="editModal">
    <div class="modal">
        <h3>编辑授权</h3>
        <input type="hidden" id="editDomain">
        <div class="form-group"><label>备注</label><input type="text" id="editNote"></div>
        <div class="form-group"><label>授权等级</label><select id="editLevel"><option value="1">基础版</option><option value="2">高级版</option><option value="3">旗舰版</option></select></div>
        <div class="form-group"><label>IP白名单</label><input type="text" id="editIP" placeholder="逗号分隔，留空=不限制，填*放行全部"></div>
        <div class="form-group"><label>续期天数</label><input type="number" id="editDays" placeholder="0=不修改" value="0" min="0"></div>
        <div class="form-group"><label>新密钥（留空不修改）</label><input type="text" id="editKey" placeholder="不修改则留空" minlength="16"></div>
        <div class="btn-row">
            <button class="btn btn-outline" onclick="closeModal()">取消</button>
            <button class="btn btn-primary" onclick="saveEdit()">保存</button>
        </div>
    </div>
</div>

<!-- 告警弹窗 -->
<div class="modal-bg" id="alertModal">
    <div class="modal">
        <h3>异常告警</h3>
        <div id="alertList"></div>
        <div class="btn-row">
            <button class="btn btn-outline" onclick="clearAllAlerts()">清空全部</button>
            <button class="btn btn-primary" onclick="closeAlertModal()">关闭</button>
        </div>
    </div>
</div>

<script>
var token = localStorage.getItem('auth_token') || '';
var allSites = [];

// ====== 登录 ======
function doLogin() {
    var pass = document.getElementById('loginPass').value;
    var fd = new FormData();
    fd.append('pass', pass);
    fetch('/api/login', {method:'POST', body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            token = d.token;
            localStorage.setItem('auth_token', token);
            document.getElementById('loginPage').style.display = 'none';
            document.getElementById('mainPage').style.display = 'block';
            refreshAll();
        } else {
            document.getElementById('loginError').style.display = 'block';
            document.getElementById('loginError').textContent = d.msg;
        }
    });
}
function doLogout() {
    localStorage.removeItem('auth_token');
    token = '';
    document.getElementById('loginPage').style.display = 'block';
    document.getElementById('mainPage').style.display = 'none';
}
function authFetch(url, opts) {
    opts = opts || {};
    opts.headers = opts.headers || {};
    opts.headers['X-Auth-Token'] = token;
    return fetch(url, opts);
}

// ====== 切换 ======
function switchTab(name) {
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
    document.querySelector('.tab[onclick="switchTab(\''+name+'\')"]').classList.add('active');
    document.getElementById('tab-'+name).classList.add('active');
    if (name === 'settings') loadVersionConfig();
}

// ====== 数据 ======
function ts(n) {
    if (!n || n===0) return '-';
    var d = new Date(n*1000);
    return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')+' '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0');
}
function tsDate(n) {
    if (!n || n===0) return '永久';
    return ts(n).split(' ')[0];
}
function levelBadge(lv) {
    var cls = 'badge-lv' + lv;
    var txt = ['','基础','高级','旗舰'][lv] || '基础';
    return '<span class="badge '+cls+'">'+txt+'</span>';
}
function refreshAll() { loadSites(); loadStats(); }
function loadStats() {
    authFetch('/api/stats').then(r=>r.json()).then(d=>{
        document.getElementById('statTotal').textContent = d.total;
        document.getElementById('statActive').textContent = d.active;
        document.getElementById('statDisabled').textContent = d.disabled;
        document.getElementById('statExpiring').textContent = d.expiring;
        document.getElementById('statToday').textContent = d.today_logs;
        document.getElementById('statAlert').textContent = d.alert_count;
    });
}
function loadSites() {
    authFetch('/api/list').then(r=>r.json()).then(data=>{
        allSites = data || [];
        renderSites(allSites);
    });
}
function renderSites(data) {
    var tbody = document.getElementById('siteList');
    if (!data || data.length===0) {
        tbody.innerHTML = '<tr><td colspan="9" class="empty">暂无授权</td></tr>';
        return;
    }
    var html = '';
    var now = Math.floor(Date.now()/1000);
    data.forEach(function(s) {
        var expireText = '永久';
        var expireClass = '';
        if (s.expire_time > 0) {
            expireText = tsDate(s.expire_time);
            if (s.expire_time < now) expireClass = 'badge-expired';
        }
        var statusBadge = s.status===1 ? '<span class="badge badge-active">正常</span>' : '<span class="badge badge-disabled">禁用</span>';
        var toggleBtn = s.status===1
            ? '<button class="btn btn-warning btn-xs" onclick="toggleSite(\''+s.domain+'\',0)">禁用</button>'
            : '<button class="btn btn-success btn-xs" onclick="toggleSite(\''+s.domain+'\',1)">启用</button>';
        html += '<tr>'+
            '<td><b>'+s.domain+'</b></td>'+
            '<td><span class="key-text" title="'+s.sitekey+'">'+s.sitekey.substring(0,8)+'...</span></td>'+
            '<td>'+levelBadge(s.auth_level)+'</td>'+
            '<td><span style="font-size:11px;color:#666">'+(s.ip_whitelist||'不限')+'</span></td>'+
            '<td><span class="badge '+expireClass+'">'+expireText+'</span></td>'+
            '<td>'+statusBadge+'</td>'+
            '<td><span style="font-size:11px">'+s.note+'</span></td>'+
            '<td style="font-size:11px">'+ts(s.last_check_time)+'</td>'+
            '<td>'+toggleBtn+' <button class="btn btn-outline btn-xs" onclick="editSite(\''+s.domain+'\')">编辑</button> <button class="btn btn-danger btn-xs" onclick="delSite(\''+s.domain+'\')">删除</button></td>'+
            '</tr>';
    });
    tbody.innerHTML = html;
}
function filterSites() {
    var q = document.getElementById('searchInput').value.toLowerCase();
    if (!q) { renderSites(allSites); return; }
    renderSites(allSites.filter(function(s){ return s.domain.toLowerCase().indexOf(q)>=0 || s.note.toLowerCase().indexOf(q)>=0; }));
}
function genKey() {
    authFetch('/api/genkey').then(r=>r.json()).then(d=>{ document.getElementById('addKey').value = d.key; });
}
function addSite() {
    var fd = new FormData();
    fd.append('domain', document.getElementById('addDomain').value.trim());
    fd.append('sitekey', document.getElementById('addKey').value.trim());
    fd.append('note', document.getElementById('addNote').value.trim());
    fd.append('auth_level', document.getElementById('addLevel').value);
    fd.append('ip_whitelist', document.getElementById('addIP').value.trim());
    fd.append('expire_days', document.getElementById('addDays').value);
    authFetch('/api/add', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            alert('添加成功！');
            document.getElementById('addDomain').value = '';
            document.getElementById('addKey').value = '';
            document.getElementById('addNote').value = '';
            document.getElementById('addIP').value = '';
            refreshAll();
        } else { alert(d.msg); }
    });
}
function toggleSite(domain, status) {
    var fd = new FormData(); fd.append('domain',domain); fd.append('status',status);
    authFetch('/api/toggle',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.ok) refreshAll(); });
}
function delSite(domain) {
    if (!confirm('确定删除 '+domain+' 吗？')) return;
    var fd = new FormData(); fd.append('domain',domain);
    authFetch('/api/delete',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.ok) refreshAll(); });
}
function editSite(domain) {
    var s = allSites.find(function(x){return x.domain===domain;});
    if (!s) return;
    document.getElementById('editDomain').value = s.domain;
    document.getElementById('editNote').value = s.note;
    document.getElementById('editLevel').value = s.auth_level;
    document.getElementById('editIP').value = s.ip_whitelist;
    document.getElementById('editDays').value = 0;
    document.getElementById('editKey').value = '';
    document.getElementById('editModal').classList.add('show');
}
function closeModal() { document.getElementById('editModal').classList.remove('show'); }
function saveEdit() {
    var fd = new FormData();
    fd.append('domain', document.getElementById('editDomain').value);
    fd.append('note', document.getElementById('editNote').value);
    fd.append('auth_level', document.getElementById('editLevel').value);
    fd.append('ip_whitelist', document.getElementById('editIP').value);
    fd.append('expire_days', document.getElementById('editDays').value);
    var key = document.getElementById('editKey').value.trim();
    if (key) fd.append('sitekey', key);
    authFetch('/api/update',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { closeModal(); refreshAll(); } else { alert(d.msg); }
    });
}

// ====== 导入导出 ======
function exportCSV() { window.open('/api/export?token='+encodeURIComponent(token)); }
function importCSV() {
    var file = document.getElementById('csvFile').files[0];
    if (!file) { alert('请选择CSV文件'); return; }
    var fd = new FormData(); fd.append('file', file);
    authFetch('/api/import',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        alert(d.msg); document.getElementById('csvFile').value = ''; refreshAll();
    });
}

// ====== 告警 ======
function showAlerts() {
    authFetch('/api/alerts').then(r=>r.json()).then(data=>{
        var html = '';
        if (!data || data.length===0) {
            html = '<p style="color:#999;text-align:center;padding:20px">无异常告警</p>';
        } else {
            data.forEach(function(a) {
                html += '<div style="padding:10px;border-bottom:1px solid #f0f0f0"><b>'+a.domain+'</b> <span style="color:#ff4d4f">失败'+a.fail_count+'次</span><br><span style="font-size:12px;color:#999">'+a.last_fail_msg+' ('+ts(a.last_fail)+')</span> <button class="btn btn-xs btn-outline" onclick="clearAlert(\''+a.domain+'\')">清除</button></div>';
            });
        }
        document.getElementById('alertList').innerHTML = html;
        document.getElementById('alertModal').classList.add('show');
    });
}
function closeAlertModal() { document.getElementById('alertModal').classList.remove('show'); }
function clearAlert(domain) {
    var fd = new FormData(); fd.append('domain', domain);
    authFetch('/api/clear_alert',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.ok) showAlerts(); });
}
function clearAllAlerts() {
    authFetch('/api/clear_alert',{method:'POST',body:new FormData()}).then(r=>r.json()).then(d=>{ if(d.ok) showAlerts(); });
}

// ====== 版本配置 ======
function loadVersionConfig() {
    authFetch('/api/version_config').then(r=>r.json()).then(d=>{
        document.getElementById('minVer1').value = d.min_ver_1 || '';
        document.getElementById('minVer2').value = d.min_ver_2 || '';
        document.getElementById('minVer3').value = d.min_ver_3 || '';
    });
}
function saveVersionConfig() {
    var fd = new FormData();
    fd.append('min_ver_1', document.getElementById('minVer1').value);
    fd.append('min_ver_2', document.getElementById('minVer2').value);
    fd.append('min_ver_3', document.getElementById('minVer3').value);
    authFetch('/api/set_version',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ alert(d.msg); });
}
function changePass() {
    var old = document.getElementById('oldPass').value;
    var newP = document.getElementById('newPass').value;
    if (!old || !newP) { alert('请填写完整'); return; }
    var fd = new FormData(); fd.append('old',old); fd.append('new',newP);
    authFetch('/api/change_pass',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        alert(d.msg); document.getElementById('oldPass').value=''; document.getElementById('newPass').value='';
    });
}

// ====== 初始化 ======
if (token) {
    authFetch('/api/stats').then(r=>r.json()).then(d=>{
        if (d.total !== undefined) {
            document.getElementById('loginPage').style.display = 'none';
            document.getElementById('mainPage').style.display = 'block';
            refreshAll();
        } else { doLogout(); }
    }).catch(function(){ doLogout(); });
}
setInterval(function(){ if (token) loadStats(); }, 30000);
</script>
</body>
</html>`