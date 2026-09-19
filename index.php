<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

$dataFile = 'messages.json';
$usersFile = 'users.json';

function loadUsers() {
    global $usersFile;
    if (file_exists($usersFile)) {
        return json_decode(file_get_contents($usersFile), true);
    }
    return [];
}

function saveUsers($users) {
    global $usersFile;
    file_put_contents($usersFile, json_encode($users, JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'register') {
        $nickname = trim($input['nickname'] ?? '');
        $password = trim($input['password'] ?? '');
        
        if (empty($nickname) || empty($password)) {
            echo json_encode(['status' => 'error', 'message' => '昵称和密码不能为空']);
            exit;
        }
        
        $users = loadUsers();
        if (isset($users[$nickname])) {
            echo json_encode(['status' => 'error', 'message' => '昵称已被注册']);
            exit;
        }
        
        $users[$nickname] = password_hash($password, PASSWORD_DEFAULT);
        saveUsers($users);
        echo json_encode(['status' => 'success', 'message' => '注册成功！请重新登录']);
        exit;
    }
    
    if ($action === 'login') {
        $nickname = trim($input['nickname'] ?? '');
        $password = trim($input['password'] ?? '');
        
        $users = loadUsers();
        if (isset($users[$nickname]) && password_verify($password, $users[$nickname])) {
            $_SESSION['nickname'] = $nickname;
            echo json_encode(['status' => 'success', 'nickname' => $nickname]);
        } else {
            echo json_encode(['status' => 'error', 'message' => '昵称或密码错误']);
        }
        exit;
    }
    
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'success']);
        exit;
    }
    
    if ($action === 'deleteAccount') {
        if (!isset($_SESSION['nickname'])) {
            echo json_encode(['status' => 'error', 'message' => '未登录']);
            exit;
        }
        
        $nickname = $_SESSION['nickname'];
        $password = trim($input['password'] ?? '');
        
        $users = loadUsers();
        if (!isset($users[$nickname]) || !password_verify($password, $users[$nickname])) {
            echo json_encode(['status' => 'error', 'message' => '密码错误，无法注销账号']);
            exit;
        }
        
        unset($users[$nickname]);
        saveUsers($users);
        
        $messages = [];
        if (file_exists($dataFile)) {
            $messages = json_decode(file_get_contents($dataFile), true);
        }
        $newMessages = [];
        foreach ($messages as $msg) {
            if ($msg['nickname'] !== $nickname) {
                $newMessages[] = $msg;
            }
        }
        file_put_contents($dataFile, json_encode($newMessages, JSON_UNESCAPED_UNICODE));
        
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => '账号已注销']);
        exit;
    }
    
    if ($action === 'sendMessage') {
        if (!isset($_SESSION['nickname'])) {
            echo json_encode(['status' => 'error', 'message' => '未登录']);
            exit;
        }
        
        $nickname = $_SESSION['nickname'];
        $message = trim($input['message'] ?? '');
        
        if (!empty($message)) {
            $newMessage = [
                'id' => uniqid(),
                'nickname' => htmlspecialchars($nickname),
                'message' => htmlspecialchars($message)
            ];
            
            $messages = [];
            if (file_exists($dataFile)) {
                $messages = json_decode(file_get_contents($dataFile), true);
            }
            
            $messages[] = $newMessage;
            
            if (count($messages) > 100) {
                $messages = array_slice($messages, -100);
            }
            
            file_put_contents($dataFile, json_encode($messages, JSON_UNESCAPED_UNICODE));
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '消息不能为空']);
        }
        exit;
    }
    
    if ($action === 'recallMessage') {
        if (!isset($_SESSION['nickname'])) {
            echo json_encode(['status' => 'error', 'message' => '未登录']);
            exit;
        }
        
        $messageId = $input['messageId'] ?? '';
        $nickname = $_SESSION['nickname'];
        
        if (empty($messageId)) {
            echo json_encode(['status' => 'error', 'message' => '消息ID不能为空']);
            exit;
        }
        
        $messages = [];
        if (file_exists($dataFile)) {
            $messages = json_decode(file_get_contents($dataFile), true);
        }
        
        $found = false;
        foreach ($messages as $key => $msg) {
            if ($msg['id'] === $messageId) {
                if ($msg['nickname'] === $nickname) {
                    $messages[$key]['message'] = '该消息已被撤回';
                    $messages[$key]['recalled'] = true;
                    $found = true;
                } else {
                    echo json_encode(['status' => 'error', 'message' => '只能撤回自己的消息']);
                    exit;
                }
                break;
            }
        }
        
        if ($found) {
            file_put_contents($dataFile, json_encode($messages, JSON_UNESCAPED_UNICODE));
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '消息不存在']);
        }
        exit;
    }
    
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'getMessages') {
    header('Content-Type: application/json');
    $messages = [];
    if (file_exists($dataFile)) {
        $messages = json_decode(file_get_contents($dataFile), true);
    }
    echo json_encode($messages);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'checkLogin') {
    header('Content-Type: application/json');
    echo json_encode(['loggedIn' => isset($_SESSION['nickname']), 'nickname' => $_SESSION['nickname'] ?? '']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>小象聊天室</title>
    <link rel="icon" href="https://icc.gt.tc/xiaoxiangimage/logo.jpg">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            width: 100%;
            max-width: 800px;
            height: 90vh;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        
        .logo h1 {
            font-size: 24px;
        }
        
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        
        .logout-btn, .delete-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .delete-btn:hover {
            background: rgba(231, 76, 60, 0.8);
        }
        
        .chat-area {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f5f5f5;
        }
        
        .message {
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 18px;
            max-width: 70%;
            position: relative;
        }
        
        .message .nickname {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .message .text {
            word-wrap: break-word;
        }
        
        .message.own {
            margin-left: auto;
            background: #667eea;
            color: white;
        }
        
        .message.own .nickname {
            color: #e0e0e0;
        }
        
        .message.other {
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .recall-btn {
            position: absolute;
            right: 10px;
            top: 10px;
            background: rgba(0, 0, 0, 0.1);
            border: none;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 10px;
            cursor: pointer;
            display: none;
        }
        
        .message:hover .recall-btn {
            display: block;
        }
        
        .message.own .recall-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .input-area {
            padding: 20px;
            background: white;
            border-top: 1px solid #eee;
        }
        
        .input-container {
            display: flex;
            gap: 10px;
        }
        
        #messageInput {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        
        #messageInput:focus {
            border-color: #667eea;
        }
        
        #sendBtn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        #sendBtn:hover {
            transform: scale(1.05);
        }
        
        #sendBtn:active {
            transform: scale(0.98);
        }
        
        .modal {
            display: flex;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 400px;
        }
        
        .modal-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .modal-logo img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
        }
        
        .modal-content h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .modal-content input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            margin-bottom: 15px;
            outline: none;
        }
        
        .modal-content input:focus {
            border-color: #667eea;
        }
        
        .modal-content button {
            padding: 12px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
            margin: 5px;
        }
        
        .modal-content button:hover {
            transform: scale(1.05);
        }
        
        .modal-content .cancel-btn {
            background: #95a5a6;
        }
        
        .modal-content .danger-btn {
            background: #e74c3c;
        }
        
        .toggle-auth {
            margin-top: 15px;
            color: #667eea;
            cursor: pointer;
            font-size: 14px;
        }
        
        .toggle-auth:hover {
            text-decoration: underline;
        }
        
        .message-msg {
            font-size: 12px;
            margin-top: 5px;
            padding: 8px;
            border-radius: 8px;
            display: none;
        }
        
        .error-msg {
            background: #fee;
            color: #e74c3c;
            border: 1px solid #fcc;
        }
        
        .success-msg {
            background: #efe;
            color: #27ae60;
            border: 1px solid #cfc;
        }
        
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="modal" id="authModal">
        <div class="modal-content">
            <div class="modal-logo">
                <img src="https://icc.gt.tc/xiaoxiangimage/logo.jpg" alt="小象">
            </div>
            <h2 id="modalTitle">登录聊天室</h2>
            <input type="text" id="authNickname" placeholder="昵称">
            <input type="password" id="authPassword" placeholder="密码">
            <div>
                <button id="submitAuthBtn">登录</button>
            </div>
            <div class="toggle-auth" id="toggleAuth">没有账号？立即注册</div>
            <div id="authMessage" class="message-msg"></div>
        </div>
    </div>
    
    <div class="modal hidden" id="deleteConfirmModal">
        <div class="modal-content">
            <div class="modal-logo">
                <img src="https://icc.gt.tc/xiaoxiangimage/logo.jpg" alt="小象">
            </div>
            <h2>⚠️ 注销账号</h2>
            <p style="margin-bottom: 20px; color: #666;">注销后所有聊天记录将被删除，且无法恢复</p>
            <input type="password" id="deletePassword" placeholder="请输入密码确认">
            <div>
                <button id="confirmDeleteBtn" class="danger-btn">确认注销</button>
                <button id="cancelDeleteBtn" class="cancel-btn">取消</button>
            </div>
            <div id="deleteMessage" class="message-msg"></div>
        </div>
    </div>
    
    <div class="container hidden" id="chatContainer">
        <div class="header">
            <div class="logo">
                <img src="https://icc.gt.tc/xiaoxiangimage/logo.jpg" alt="小象">
                <h1>小象聊天室</h1>
            </div>
            <div class="header-buttons">
                <button class="logout-btn" id="logoutBtn">退出登录</button>
                <button class="delete-btn" id="deleteAccountBtn">注销账号</button>
            </div>
        </div>
        <div class="chat-area" id="chatArea"></div>
        <div class="input-area">
            <div class="input-container">
                <input type="text" id="messageInput" placeholder="说点什么吧..." maxlength="500">
                <button id="sendBtn">发送</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentNickname = '';
        let isLoginMode = true;
        let lastMessagesJson = '';
        let pollInterval = null;
        
        const authModal = document.getElementById('authModal');
        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        const chatContainer = document.getElementById('chatContainer');
        const modalTitle = document.getElementById('modalTitle');
        const authNickname = document.getElementById('authNickname');
        const authPassword = document.getElementById('authPassword');
        const submitAuthBtn = document.getElementById('submitAuthBtn');
        const toggleAuth = document.getElementById('toggleAuth');
        const authMessage = document.getElementById('authMessage');
        const logoutBtn = document.getElementById('logoutBtn');
        const deleteAccountBtn = document.getElementById('deleteAccountBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const deletePassword = document.getElementById('deletePassword');
        const deleteMessage = document.getElementById('deleteMessage');
        const chatArea = document.getElementById('chatArea');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        
        function showMessage(element, message, isSuccess) {
            element.textContent = message;
            element.className = 'message-msg ' + (isSuccess ? 'success-msg' : 'error-msg');
            element.style.display = 'block';
            setTimeout(() => {
                element.style.display = 'none';
            }, 3000);
        }
        
        toggleAuth.addEventListener('click', () => {
            isLoginMode = !isLoginMode;
            if (isLoginMode) {
                modalTitle.textContent = '登录聊天室';
                submitAuthBtn.textContent = '登录';
                toggleAuth.textContent = '没有账号？立即注册';
            } else {
                modalTitle.textContent = '注册账号';
                submitAuthBtn.textContent = '注册';
                toggleAuth.textContent = '已有账号？立即登录';
            }
            authMessage.style.display = 'none';
        });
        
        async function recallMessage(messageId) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'recallMessage', messageId })
                });
                const result = await response.json();
                if (result.status === 'success') {
                    await loadMessages(true);
                } else {
                    console.error('撤回失败:', result.message);
                }
            } catch (error) {
                console.error('撤回失败:', error);
            }
        }
        
        submitAuthBtn.addEventListener('click', async () => {
            const nickname = authNickname.value.trim();
            const password = authPassword.value.trim();
            
            if (!nickname || !password) {
                showMessage(authMessage, '请填写昵称和密码', false);
                return;
            }
            
            const action = isLoginMode ? 'login' : 'register';
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, nickname, password })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                if (action === 'login') {
                    currentNickname = result.nickname;
                    authModal.classList.add('hidden');
                    chatContainer.classList.remove('hidden');
                    await loadMessages(true);
                    if (pollInterval) clearInterval(pollInterval);
                    pollInterval = setInterval(() => loadMessages(false), 2000);
                    messageInput.focus();
                } else {
                    showMessage(authMessage, result.message || '注册成功！请重新登录', true);
                    isLoginMode = true;
                    modalTitle.textContent = '登录聊天室';
                    submitAuthBtn.textContent = '登录';
                    toggleAuth.textContent = '没有账号？立即注册';
                    authPassword.value = '';
                }
            } else {
                showMessage(authMessage, result.message, false);
            }
        });
        
        logoutBtn.addEventListener('click', async () => {
            await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout' })
            });
            currentNickname = '';
            chatContainer.classList.add('hidden');
            authModal.classList.remove('hidden');
            authNickname.value = '';
            authPassword.value = '';
            lastMessagesJson = '';
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
        });
        
        deleteAccountBtn.addEventListener('click', () => {
            deletePassword.value = '';
            deleteMessage.style.display = 'none';
            deleteConfirmModal.classList.remove('hidden');
        });
        
        cancelDeleteBtn.addEventListener('click', () => {
            deleteConfirmModal.classList.add('hidden');
        });
        
        confirmDeleteBtn.addEventListener('click', async () => {
            const password = deletePassword.value.trim();
            if (!password) {
                showMessage(deleteMessage, '请输入密码', false);
                return;
            }
            
            const response = await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'deleteAccount', password })
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                deleteConfirmModal.classList.add('hidden');
                currentNickname = '';
                chatContainer.classList.add('hidden');
                authModal.classList.remove('hidden');
                authNickname.value = '';
                authPassword.value = '';
                lastMessagesJson = '';
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
                showMessage(authMessage, result.message || '账号已注销', true);
            } else {
                showMessage(deleteMessage, result.message, false);
            }
        });
        
        deletePassword.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') confirmDeleteBtn.click();
        });
        
        authNickname.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') submitAuthBtn.click();
        });
        
        authPassword.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') submitAuthBtn.click();
        });
        
        sendBtn.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
        
        async function sendMessage() {
            const text = messageInput.value.trim();
            if (!text) return;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'sendMessage', message: text })
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    messageInput.value = '';
                    await loadMessages(true);
                }
            } catch (error) {
                console.error('发送失败:', error);
            }
        }
        
        async function loadMessages(forceRefresh = false) {
            try {
                const response = await fetch('?action=getMessages&t=' + Date.now());
                const messages = await response.json();
                const messagesJson = JSON.stringify(messages);
                
                if (forceRefresh || messagesJson !== lastMessagesJson) {
                    displayMessages(messages);
                    lastMessagesJson = messagesJson;
                }
            } catch (error) {
                console.error('加载失败:', error);
            }
        }
        
        function displayMessages(messages) {
            const wasAtBottom = chatArea.scrollHeight - chatArea.scrollTop <= chatArea.clientHeight + 100;
            
            chatArea.innerHTML = '';
            messages.forEach(msg => {
                const div = document.createElement('div');
                div.className = `message ${msg.nickname === currentNickname ? 'own' : 'other'}`;
                div.innerHTML = `
                    <div class="nickname">${escapeHtml(msg.nickname)}</div>
                    <div class="text">${escapeHtml(msg.message)}</div>
                    ${msg.nickname === currentNickname && !msg.recalled ? `<button class="recall-btn" data-id="${msg.id}">撤回</button>` : ''}
                `;
                chatArea.appendChild(div);
            });
            
            document.querySelectorAll('.recall-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    recallMessage(btn.getAttribute('data-id'));
                });
            });
            
            if (wasAtBottom) {
                chatArea.scrollTop = chatArea.scrollHeight;
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
