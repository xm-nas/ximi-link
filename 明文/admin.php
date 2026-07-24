<?php
/**
 * Ximi 管理后台 (PHP 服务端一键保存版)
 * 最后更新：2026-06
 */

// 定位数据文件路径
$dataFile = __DIR__ . '/link-date.js';

if (!file_exists($dataFile)) {
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>错误：未找到数据文件 {$dataFile}，请检查路径。</h2>");
}

// ==========================================
// 1. 解析 link-date.js 获取配置
// ==========================================
$jsContent = file_get_contents($dataFile);

// 提取 settingData = {...}; 中的 JSON 对象
preg_match('/const\s+settingData\s*=\s*(\{.*?\});/s', $jsContent, $matches);
if (empty($matches[1])) {
    die("<h2 style='color:red; text-align:center;'>解析配置错误：link-date.js 格式不规范。</h2>");
}

$settingData = json_decode($matches[1], true);
$correctHash = $settingData['user'][0]['pwd'] ?? '';

// ==========================================
// 2. 纯服务端密码与 MD5 鉴权拦截
// ==========================================
$isAuthorized = false;
$errorMsg = '';

// 处理前端通过 AJAX 发送的保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    header('Content-Type: application/json');
    
    $inputPwd = $_POST['auth_pwd'] ?? '';
    $inputHash = md5($inputPwd);
    
    // 强制验证 MD5
    if (empty($correctHash) || $inputHash !== $correctHash) {
        echo json_encode(['success' => false, 'message' => '鉴权失败：密码错误，无权写入文件！']);
        exit;
    }
    
    $newContent = $_POST['js_content'] ?? '';
    if (empty($newContent)) {
        echo json_encode(['success' => false, 'message' => '保存失败：内容不能为空！']);
        exit;
    }
    
    // 2.1 执行时间戳备份 (格式：年月日时分秒)
    $backupFile = __DIR__ . '/' . date('YmdHis') . '_link-date.js.bak';
    if (!copy($dataFile, $backupFile)) {
        echo json_encode(['success' => false, 'message' => '备份失败，取消写入！请检查目录写入权限。']);
        exit;
    }
    
    // 2.2 写入新数据
    if (file_put_contents($dataFile, $newContent) !== false) {
        echo json_encode(['success' => true, 'message' => '保存成功！原文件已备份为: ' . basename($backupFile)]);
    } else {
        echo json_encode(['success' => false, 'message' => '写入文件失败，请检查目录权限。']);
    }
    exit;
}

// 处理页面的常规访问鉴权
if (isset($_POST['login_pwd'])) {
    $loginPwd = $_POST['login_pwd'];
    if (md5($loginPwd) === $correctHash) {
        $isAuthorized = true;
        // 将明文密码存入 Session 或传给前端隐式持有用于保存时的二次验证
        $sessionPwd = $loginPwd; 
    } else {
        $errorMsg = '密码错误，请重新输入！';
    }
}

// 如果未认证，直接渲染输入密码的弹窗/表单页面，不加载任何后续敏感 HTML 与 JS
if (!$isAuthorized) {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>身份验证 - Ximi 管理后台</title>
    <style>
        body { background: #f4f6f8; font-family: -apple-system, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); width: 320px; text-align: center; }
        h3 { margin-bottom: 20px; color: #1e293b; }
        input { padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; width: 100%; box-sizing: border-box; margin-bottom: 15px; outline: none; font-size: 14px; text-align: center; }
        input:focus { border-color: #2563eb; }
        button { background: #2563eb; color: #fff; border: none; padding: 10px; width: 100%; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; }
        button:hover { background: #1d4ed8; }
        .error { color: #ef4444; font-size: 13px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="login-box">
    <h3>Ximi 后台安全认证</h3>
    <?php if ($errorMsg): ?><div class="error"><?php echo $errorMsg; ?></div><?php endif; ?>
    <form method="POST" action="">
        <input type="password" name="login_pwd" placeholder="请输入管理密码" autofocus required>
        <button type="submit">进入后台</button>
    </form>
</div>
</body>
</html>
<?php
    exit; // 阻断后续代码执行
}

// ==========================================
// 3. 认证成功：渲染管理后台
// ==========================================
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>Ximi 管理后台</title>
    <style>
        :root { --bg: #f4f6f8; --sidebar-w: 260px; --accent: #2563eb; --border: #e2e8f0; }
        body { margin: 0; display: flex; font-family: -apple-system, sans-serif; background: var(--bg); color: #334155; }
        
        /* === 左侧栏菜单 === */
        #sidebar { width: var(--sidebar-w); background: #fff; height: 100vh; border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; font-size: 18px; font-weight: bold; border-bottom: 1px solid var(--border); color: #0f172a; }
        
        .menu-group { border-bottom: 1px solid #f1f5f9; }
        .group-title { padding: 15px 20px; font-weight: 600; cursor: pointer; display: flex; justify-content: space-between; align-items: center; background: #fafafa; transition: 0.2s; }
        .group-title:hover { background: #f1f5f9; }
        
        .menu-sub { padding: 5px 0 10px 0; }
        .menu-item { padding: 10px 20px 10px 40px; cursor: pointer; font-size: 14px; margin: 2px 10px; border-radius: 6px; position: relative; }
        .menu-item:before { content: "•"; position: absolute; left: 25px; color: #cbd5e1; }
        .menu-item:hover { background: #f1f5f9; }
        .menu-item.active { background: #dbeafe; color: var(--accent); font-weight: 600; }
        .menu-item.active:before { color: var(--accent); }
        .menu-new { color: #059669; font-weight: 600; }
        .menu-new:before { content: "+"; font-weight: bold; color: #059669; }

        /* === 右侧主区域 === */
        #main { flex: 1; padding: 40px; overflow-y: auto; height: 100vh; box-sizing: border-box; }
        .view-title { font-size: 22px; margin-top: 0; border-bottom: 2px solid var(--border); padding-bottom: 15px; margin-bottom: 25px; color: #1e293b; }
        
        .card-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .card label { display: block; font-size: 12px; color: #64748b; margin-bottom: 5px; font-weight: 600; }
        
        input, select { padding: 10px; border: 1px solid var(--border); border-radius: 6px; width: 100%; margin-bottom: 15px; box-sizing: border-box; outline: none; }
        input:focus, select:focus { border-color: var(--accent); }
        
        .btn-del { background: #fee2e2; color: #ef4444; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; width: 100%; }
        .btn-del:hover { background: #fecaca; }
        .btn-save { position: fixed; bottom: 30px; right: 30px; background: #059669; color: #fff; padding: 15px 30px; border-radius: 50px; cursor: pointer; border: none; font-size: 16px; font-weight: bold; box-shadow: 0 4px 12px rgba(5,150,105,0.3); transition: 0.3s; z-index: 100; }
        .btn-save:hover { background: #047857; transform: translateY(-2px); }
        li { padding: 10px; }
    </style>
    <script src="md5.js"></script>
    <script src="crypto-js.min.js"></script>
</head>
<body>

<div id="sidebar">
    <div class="sidebar-header">后台管理中心</div>
    <div id="sidebar-content"></div>
</div>

<div id="main">
    <h1 class="view-title" id="view-title">👈 请在左侧选择要管理的功能</h1>
    
    <div id="user" class="card" style="margin-bottom: 25px; border-bottom: 2px solid var(--border);">
        <h3>系统设置</h3>
        <div>版本信息: <input type="text" id="sys-version" readonly></div>
        <div>加密方式: 
            <select id="sys-encryption">
                <option value="none">none</option>
                <option value="base64">base64</option>
                <option value="md5">md5</option>
            </select>
        </div>
        <div>密码设置: <input type="password" id="sys-pwd" placeholder="至少6位，包含任意2种字符类型"></div>
        <div id="pwd-hint" style="font-size:12px; color:gray;"></div>
    </div>

    <div id="view-content">
        <ol>
            <li>本程序是一个基于 PHP 后台与前端结合的链接管理工具。</li>
            <li><strong>【升级】</strong> 现在的保存功能不需要手动下载文件，点击右下角即可直接保存到服务器。</li>
            <li>每次点击保存时，系统都会在服务器当前目录下自动生成高精度秒级备份文件。</li>
            <li>无论系统采用何种加密模式，出于安全考虑，均在服务端强制要求进行 MD5 密码认证校验。</li>
            <li>作者：希米</li>
            <li>原文：https://www.ximi.me/post-6040.html</li>
            <li>最后更新：2026-06-15 (PHP 强验证一键保存版)</li>
        </ol>
    </div>
</div>

<button class="btn-save" onclick="saveToServer()">📥 一键保存更改 (link-date.js)</button>

<script src="link-date.js"></script>

<script>
    // 隐式保存当前通过 PHP 验证的明文密码，用于向后端递交保存请求时的验证
    const AUTH_PWD = <?php echo json_encode($sessionPwd); ?>;

    let categories = [];
    let links = [];
    let menuState = { cat: true, link: true }; 
    let current = { type: '', id: '' }; 

    window.addEventListener('DOMContentLoaded', async () => {
        if (typeof settingData === 'undefined' || typeof getNavData !== 'function') {
            alert("配置数据未加载，请检查 link-date.js 是否存在");
            return;
        }

        const settings = settingData.user[0];
        document.getElementById('sys-version').value = settings.version;
        document.getElementById('sys-encryption').value = settings.encryption;
        
        // 由于已经在 PHP 服务端用 MD5 强拦截过滤了一次，这里直接使用通过验证的密码进行前端解密
        let navData = getNavData(AUTH_PWD);

        if (navData) {
            document.getElementById('sys-pwd').value = AUTH_PWD;
            categories = navData.categories || [];
            links = navData.links || [];
            links.forEach(l => { if(!l.id) l.id = Date.now() + Math.random(); });
            
            renderSidebar();
            renderContent();
        } else {
            alert("前端解密失败，数据格式或配置可能有误。");
        }
    });

    function toggleMenu(menu) {
        menuState[menu] = !menuState[menu];
        renderSidebar();
    }

    function view(type, id) {
        if (type === 'cat_new') {
            let newName = '新分类_' + Math.floor(Math.random() * 1000);
            categories.push({ name: newName, icon: '' });
            current = { type: 'cat_edit', id: newName };
        } else if (type === 'link_new') {
            if(categories.length === 0) return alert("请先新建一个分类！");
            let defaultCat = categories[0].name;
            links.unshift({ id: Date.now() + Math.random(), name: '', url: '', categories: defaultCat, description: '', icon: '' });
            current = { type: 'link_cat', id: defaultCat };
        } else {
            current = { type, id };
        }
        renderSidebar();
        renderContent();
    }

    function renderSidebar() {
        const sidebar = document.getElementById('sidebar-content');
        let catHtml = `<div class="menu-item menu-new" onclick="view('cat_new')">新建分类</div>`;
        categories.forEach(c => {
            let active = (current.type === 'cat_edit' && current.id === c.name) ? 'active' : '';
            catHtml += `<div class="menu-item ${active}" onclick="view('cat_edit', '${c.name}')">${c.name}</div>`;
        });

        let linkHtml = `<div class="menu-item menu-new" onclick="view('link_new')">新建链接</div>`;
        categories.forEach(c => {
            let active = (current.type === 'link_cat' && current.id === c.name) ? 'active' : '';
            linkHtml += `<div class="menu-item ${active}" onclick="view('link_cat', '${c.name}')">${c.name}</div>`;
        });

        sidebar.innerHTML = `
            <div class="menu-group">
                <div class="group-title" onclick="toggleMenu('cat')">
                    <span>分类管理</span> <span>${menuState.cat ? '▼' : '▶'}</span>
                </div>
                <div class="menu-sub" style="display: ${menuState.cat ? 'block' : 'none'}">${catHtml}</div>
            </div>
            <div class="menu-group">
                <div class="group-title" onclick="toggleMenu('link')">
                    <span>链接管理</span> <span>${menuState.link ? '▼' : '▶'}</span>
                </div>
                <div class="menu-sub" style="display: ${menuState.link ? 'block' : 'none'}">${linkHtml}</div>
            </div>
        `;
    }

    function renderContent() {
        const content = document.getElementById('view-content');
        const title = document.getElementById('view-title');

        if (current.type === 'cat_edit') {
            const c = categories.find(x => x.name === current.id);
            if (!c) return;
            title.innerText = "正在编辑分类： " + c.name;
            content.innerHTML = `
                <div class="card" style="max-width: 500px;">
                    <label>分类名称</label>
                    <input value="${c.name}" onchange="updateCatName('${c.name}', this.value)" placeholder="输入分类名称">
                    <label>分类图标 URL</label>
                    <input value="${c.icon}" oninput="updateCatIcon('${c.name}', this.value)" placeholder="输入图标图片链接">
                    <br><br>
                    <button class="btn-del" onclick="deleteCat('${c.name}')">🗑️ 删除该分类 (包含内部所有链接)</button>
                </div>
            `;
        } else if (current.type === 'link_cat') {
            title.innerText = "分类下的链接列表： " + current.id;
            const filtered = links.filter(l => l.categories === current.id);
            
            if (filtered.length === 0) {
                content.innerHTML = `<p style="color:#94a3b8;">该分类下暂无链接，请点击左侧 "+ 新建链接" 添加。</p>`;
                return;
            }

            content.innerHTML = `<div class="card-grid">` + filtered.map(l => `
                <div class="card">
                    <label>应用名称</label>
                    <input value="${l.name}" oninput="updateLink('${l.id}', 'name', this.value)" placeholder="例如: 百度">
                    <label>链接地址 URL</label>
                    <input value="${l.url}" oninput="updateLink('${l.id}', 'url', this.value)" placeholder="https://...">
                    <label>图标 URL</label>
                    <input value="${l.icon}" oninput="updateLink('${l.id}', 'icon', this.value)" placeholder="图标图片地址">
                    <label>所属分类</label>
                    <select onchange="updateLink('${l.id}', 'categories', this.value)">
                        ${categories.map(cat => `<option value="${cat.name}" ${l.categories === cat.name ? 'selected' : ''}>${cat.name}</option>`).join('')}
                    </select>
                    <label>一句话描述</label>
                    <input value="${l.description}" oninput="updateLink('${l.id}', 'description', this.value)" placeholder="一句话简介">
                    <button class="btn-del" onclick="deleteLink('${l.id}')">🗑️ 删除此链接</button>
                </div>`).join('') + `</div>`;
        }
    }

    function updateLink(id, key, value) {
        const link = links.find(l => l.id == id);
        if (link) {
            link[key] = value;
            if(key === 'categories') renderContent(); 
        }
    }

    function updateCatName(oldName, newName) {
        if (!newName || newName.trim() === '') return alert("分类名不能为空！");
        const cat = categories.find(c => c.name === oldName);
        if (cat) {
            cat.name = newName;
            links.forEach(l => { if (l.categories === oldName) l.categories = newName; });
            current.id = newName;
            renderSidebar();
            renderContent();
        }
    }

    function updateCatIcon(name, newIcon) {
        const cat = categories.find(c => c.name === name);
        if (cat) cat.icon = newIcon;
    }

    function deleteCat(name) {
        if (confirm(`⚠️ 危险操作：确定要删除分类【${name}】吗？\n该分类下的所有链接也会被一并清空！`)) {
            categories = categories.filter(c => c.name !== name);
            links = links.filter(l => l.categories !== name);
            current = { type: '', id: '' };
            renderSidebar();
            document.getElementById('view-title').innerText = "👈 请在左侧选择要管理的功能";
            document.getElementById('view-content').innerHTML = "";
        }
    }

    function deleteLink(id) {
        if (confirm("确定要删除这条链接吗？")) {
            links = links.filter(l => l.id != id);
            renderContent();
        }
    }

    function validatePwd(pwd) {
        if (pwd.length < 6) return false;
        let types = 0;
        if (/[A-Z]/.test(pwd)) types++;
        if (/[a-z]/.test(pwd)) types++;
        if (/[0-9]/.test(pwd)) types++;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(pwd)) types++;
        return types >= 2;
    }

    // ==========================================
    // 4. 发送到服务端保存
    // ==========================================
    async function saveToServer() {
        // 4.1 弹窗确认提示
        if (!confirm("确定要保存所有更改并同步更新到服务器上的 link-date.js 吗？\n系统将会为您自动备份旧数据。")) {
            return;
        }

        const encSelect = document.getElementById('sys-encryption').value;
        const newPwd = document.getElementById('sys-pwd').value;
        const currentSetting = settingData.user[0];

        if (!newPwd || !validatePwd(newPwd)) {
            return alert("密码必须填写且强度满足要求（6位以上，含2种字符类型）！");
        }

        const exportCats = categories.filter(c => c.name.trim() !== "").map(c => ({ name: c.name, icon: c.icon }));
        const exportLinks = links.map(l => { const { id, ...rest } = l; return rest; });
        const jsonString = JSON.stringify({ categories: exportCats, links: exportLinks });

        let finalData = "";
        // 通过 SparkMD5（或使用已经打包好的服务端计算方式）确保落库为 MD5
        const finalPwdHash = SparkMD5.hash(newPwd);

        try {
            if (encSelect === 'md5') {
                const base64Data = btoa(unescape(encodeURIComponent(jsonString)));
                finalData = CryptoJS.AES.encrypt(base64Data, newPwd).toString();
            } else if (encSelect === 'base64') {
                finalData = btoa(unescape(encodeURIComponent(jsonString)));
            } else {
                finalData = jsonString;
            }

            const newSetting = {
                user: [{
                    version: currentSetting.version,
                    encryption: encSelect,
                    pwd: finalPwdHash, 
                    pwdHint: currentSetting.pwdHint
                }]
            };

            // 组装完整的 JS 字符串内容
            const fullJsBody = `const settingData = ${JSON.stringify(newSetting, null, 4)};\n` +
                            `const navDataContent = \`${finalData}\`;\n\n` +
                            `function getNavData(inputPwd) {\n` +
                            `    const enc = settingData.user[0].encryption;\n` +
                            `    if (enc === 'md5' && (!inputPwd)) return null;\n` +
                            `    try {\n` +
                            `        if (enc === 'md5') {\n` +
                            `            const decrypted = CryptoJS.AES.decrypt(navDataContent, inputPwd);\n` +
                            `            const decryptedString = decrypted.toString(CryptoJS.enc.Utf8);\n` +
                            `            if (!decryptedString) return null;\n` +
                            `            return JSON.parse(decodeURIComponent(escape(atob(decryptedString))));\n` +
                            `        } \n` +
                            `        else if (enc === 'base64') {\n` +
                            `            const str = decodeURIComponent(atob(navDataContent).split('').map(function(c) {\n` +
                            `                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);\n` +
                            `            }).join(''));\n` +
                            `            return JSON.parse(str);\n` +
                            `        } \n` +
                            `        else {\n` +
                            `            return typeof navDataContent === 'string' ? JSON.parse(navDataContent) : navDataContent;\n` +
                            `        }\n` +
                            `    } catch(e) {\n` +
                            `        console.error("数据解析失败:", e);\n` +
                            `        return null;\n` +
                            `    }\n` +
                            `}`;

            // 4.2 使用 AJAX 发送到本页面后台进行异步安全写入
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('auth_pwd', AUTH_PWD); // 发送明文密码给 PHP 校验 MD5
            formData.append('js_content', fullJsBody);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                alert(result.message);
                // 如果密码发生了变更，直接刷新页面让用户重新登录
                if (newPwd !== AUTH_PWD) {
                    window.location.reload();
                }
            } else {
                alert("保存失败: " + result.message);
            }

        } catch (e) {
            alert("客户端封装数据失败: " + e.message);
        }
    }
</script>
</body>
</html>