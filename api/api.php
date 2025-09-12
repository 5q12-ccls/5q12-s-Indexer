<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
$configFile = __DIR__ . '/config.json';
$iconsDir = __DIR__ . '/icons';
$iconsMappingFile = __DIR__ . '/icons.json';
$extensionMapFile = __DIR__ . '/extensionMap.json';
function logRequest($endpoint, $params = [], $response = 'success') {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'endpoint' => $endpoint,
        'params' => $params,
        'response' => $response
    ];
    $logFile = '/web/admin/logs/indexer/api/requests.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
}
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}
function errorResponse($message, $status = 400) {
    logRequest($_GET['action'] ?? 'unknown', $_GET, 'error: ' . $message);
    jsonResponse(['error' => $message, 'status' => $status], $status);
}
function loadExtensionMappings() {
    global $extensionMapFile;
    if (!file_exists($extensionMapFile)) {
        return null;
    }
    $mappingData = file_get_contents($extensionMapFile);
    $mappings = json_decode($mappingData, true);
    return $mappings ?: null;
}
function loadIconMappings() {
    global $iconsMappingFile;
    if (!file_exists($iconsMappingFile)) {
        return [];
    }
    $mappingData = file_get_contents($iconsMappingFile);
    $mappings = json_decode($mappingData, true);
    return $mappings ?: [];
}
function findIconForType($type, $extension = '') {
    global $iconsDir;
    $iconMappings = loadIconMappings();
    if ($type === 'folder') {
        $iconFile = isset($iconMappings['folder']) ? $iconMappings['folder'] : 'folder.png';
    } else {
        $extension = strtolower($extension);
        $iconFile = isset($iconMappings[$extension]) ? $iconMappings[$extension] : 'text.png';
    }
    $iconPath = $iconsDir . '/' . $iconFile;
    if (!file_exists($iconPath)) {
        $iconFile = 'text.png';
        $iconPath = $iconsDir . '/' . $iconFile;
        if (!file_exists($iconPath)) {
            return null;
        }
    }
    return [
        'filename' => $iconFile,
        'url' => 'https://api.indexer.ccls.icu/icons/' . $iconFile,
        'path' => $iconPath,
        'size' => filesize($iconPath),
        'last_modified' => filemtime($iconPath)
    ];
}
$action = $_GET['action'] ?? '';
switch ($action) {
    case 'versionCheck':
        handleVersionCheckRequest();
        break;
    case 'config':
        handleConfigRequest();
        break;
    case 'icons':
        handleIconsRequest();
        break;
    case 'icon':
        handleSingleIconRequest();
        break;
    case 'findIcon':
        handleFindIconRequest();
        break;
    case 'iconMappings':
        handleIconMappingsRequest();
        break;
    case 'extensionMappings':
        handleExtensionMappingsRequest();
        break;
    case 'getExtensionSetting':
        handleGetExtensionSettingRequest();
        break;
    case 'status':
        handleStatusRequest();
        break;
    default:
        handleDefaultRequest();
        break;
}
/**
 * Handle version check requests
 */
function handleVersionCheckRequest() {
    global $configFile;
    $currentVersion = $_GET['current_version'] ?? '';
    if (empty($currentVersion)) {
        errorResponse('Current version parameter is required');
    }
    if (!file_exists($configFile)) {
        errorResponse('Configuration file not found', 404);
    }
    $configData = file_get_contents($configFile);
    $config = json_decode($configData, true);
    if ($config === null) {
        errorResponse('Invalid configuration file', 500);
    }
    $latestVersion = isset($config['version']) ? $config['version'] : '1.0';
    $updateNeeded = version_compare($currentVersion, $latestVersion, '<');
    $response = [
        'success' => true,
        'current_version' => $currentVersion,
        'latest_version' => $latestVersion,
        'update_needed' => $updateNeeded,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($updateNeeded) {
        $response['message'] = "Update available: {$currentVersion} -> {$latestVersion}";
        $response['config_url'] = 'https://api.indexer.ccls.icu/api.php?action=config';
    } else {
        $response['message'] = "You have the latest version: {$currentVersion}";
    }
    logRequest('versionCheck', ['current_version' => $currentVersion, 'latest_version' => $latestVersion, 'update_needed' => $updateNeeded]);
    header('Cache-Control: public, max-age=900');
    jsonResponse($response);
}
function handleExtensionMappingsRequest() {
    $type = $_GET['type'] ?? 'all';
    $extensionMappings = loadExtensionMappings();
    if ($extensionMappings === null) {
        errorResponse('Extension mappings not found or invalid', 404);
    }
    $response = [
        'success' => true
    ];
    switch ($type) {
        case 'indexing':
            $response['mappings'] = $extensionMappings['indexing'];
            $response['count'] = count($extensionMappings['indexing']);
            break;
        case 'viewing':
            $response['mappings'] = $extensionMappings['viewing'];
            $response['count'] = count($extensionMappings['viewing']);
            break;
        case 'all':
        default:
            $response['mappings'] = $extensionMappings;
            $response['indexing_count'] = count($extensionMappings['indexing']);
            $response['viewing_count'] = count($extensionMappings['viewing']);
            break;
    }
    logRequest('extensionMappings', ['type' => $type]);
    header('Cache-Control: public, max-age=86400');
    jsonResponse($response);
}
function handleGetExtensionSettingRequest() {
    $extension = $_GET['extension'] ?? '';
    $type = $_GET['type'] ?? 'indexing';
    if (empty($extension)) {
        errorResponse('Extension parameter is required');
    }
    $extensionMappings = loadExtensionMappings();
    if ($extensionMappings === null) {
        errorResponse('Extension mappings not found or invalid', 404);
    }
    $extension = strtolower($extension);
    $setting = null;
    if ($type === 'indexing' && isset($extensionMappings['indexing'][$extension])) {
        $setting = $extensionMappings['indexing'][$extension];
    } elseif ($type === 'viewing' && isset($extensionMappings['viewing'][$extension])) {
        $setting = $extensionMappings['viewing'][$extension];
    }
    logRequest('getExtensionSetting', ['extension' => $extension, 'type' => $type]);
    header('Cache-Control: public, max-age=86400');
    jsonResponse([
        'success' => true,
        'extension' => $extension,
        'type' => $type,
        'setting' => $setting,
        'found' => $setting !== null
    ]);
}
function handleFindIconRequest() {
    $type = $_GET['type'] ?? 'file';
    $extension = $_GET['extension'] ?? '';
    if (empty($extension) && $type !== 'folder') {
        errorResponse('Extension parameter is required for file types');
    }
    $iconInfo = findIconForType($type, $extension);
    if ($iconInfo === null) {
        errorResponse('Icon not found', 404);
    }
    logRequest('findIcon', ['type' => $type, 'extension' => $extension]);
    header('Cache-Control: public, max-age=86400');
    jsonResponse([
        'success' => true,
        'type' => $type,
        'extension' => $extension,
        'icon' => $iconInfo
    ]);
}
function handleIconMappingsRequest() {
    $iconMappings = loadIconMappings();
    if (empty($iconMappings)) {
        errorResponse('Icon mappings not found or invalid', 404);
    }
    logRequest('iconMappings');
    header('Cache-Control: public, max-age=86400');
    jsonResponse([
        'success' => true,
        'mappings' => $iconMappings,
        'count' => count($iconMappings)
    ]);
}
function handleConfigRequest() {
    global $configFile;
    if (!file_exists($configFile)) {
        errorResponse('Configuration file not found', 404);
    }
    $configData = file_get_contents($configFile);
    $config = json_decode($configData, true);
    if ($config === null) {
        errorResponse('Invalid configuration file', 500);
    }
    logRequest('config');
    header('Cache-Control: public, max-age=3600');
    jsonResponse([
        'success' => true,
        'config' => $config,
        'version' => '1.0',
        'last_modified' => filemtime($configFile)
    ]);
}
function handleIconsRequest() {
    global $iconsDir;
    if (!is_dir($iconsDir)) {
        errorResponse('Icons directory not found', 404);
    }
    $icons = [];
    $files = scandir($iconsDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $filePath = $iconsDir . '/' . $file;
        if (is_file($filePath) && preg_match('/\.(png|jpg|jpeg|gif|svg|webp)$/i', $file)) {
            $icons[] = [
                'filename' => $file,
                'url' => 'https://api.indexer.ccls.icu/icons/' . $file,
                'size' => filesize($filePath),
                'last_modified' => filemtime($filePath)
            ];
        }
    }
    logRequest('icons');
    header('Cache-Control: public, max-age=86400');
    jsonResponse([
        'success' => true,
        'icons' => $icons,
        'count' => count($icons)
    ]);
}
function handleSingleIconRequest() {
    global $iconsDir;
    $iconName = $_GET['name'] ?? '';
    if (empty($iconName)) {
        errorResponse('Icon name not specified');
    }
    $iconName = basename($iconName);
    if (!preg_match('/\.(png|jpg|jpeg|gif|svg|webp)$/i', $iconName)) {
        errorResponse('Invalid icon file type');
    }
    $iconPath = $iconsDir . '/' . $iconName;
    if (!file_exists($iconPath)) {
        errorResponse('Icon not found', 404);
    }
    logRequest('icon', ['name' => $iconName]);
    jsonResponse([
        'success' => true,
        'icon' => [
            'filename' => $iconName,
            'url' => 'https://api.indexer.ccls.icu/icons/' . $iconName,
            'size' => filesize($iconPath),
            'last_modified' => filemtime($iconPath)
        ]
    ]);
}
function handleStatusRequest() {
    global $configFile, $iconsDir, $iconsMappingFile, $extensionMapFile;
    $status = [
        'success' => true,
        'service' => 'Indexer API',
        'version' => '1.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'config_exists' => file_exists($configFile),
        'icons_dir_exists' => is_dir($iconsDir),
        'icon_mappings_exists' => file_exists($iconsMappingFile),
        'extension_mappings_exists' => file_exists($extensionMapFile),
        'icon_count' => 0,
        'mapping_count' => 0,
        'extension_mapping_count' => 0
    ];
    if (is_dir($iconsDir)) {
        $files = glob($iconsDir . '/*.{png,jpg,jpeg,gif,svg,webp}', GLOB_BRACE);
        $status['icon_count'] = count($files);
    }
    if (file_exists($configFile)) {
        $status['config_last_modified'] = filemtime($configFile);
    }
    if (file_exists($iconsMappingFile)) {
        $status['mappings_last_modified'] = filemtime($iconsMappingFile);
        $mappings = loadIconMappings();
        $status['mapping_count'] = count($mappings);
    }
    if (file_exists($extensionMapFile)) {
        $status['extension_mappings_last_modified'] = filemtime($extensionMapFile);
        $extensionMappings = loadExtensionMappings();
        if ($extensionMappings !== null) {
            $status['extension_mapping_count'] = count($extensionMappings['indexing']) + count($extensionMappings['viewing']);
        }
    }
    logRequest('status');
    jsonResponse($status);
}
function handleDefaultRequest() {
    $info = [
        'success' => true,
        'service' => 'Indexer API',
        'version' => '1.0',
        'description' => 'API for the custom indexer project',
        'endpoints' => [
            'versionCheck' => [
                'url' => '?action=versionCheck&current_version={version}',
                'description' => 'Check if a config update is available',
                'method' => 'GET'
            ],
            'config' => [
                'url' => '?action=config',
                'description' => 'Get configuration file',
                'method' => 'GET'
            ],
            'icons' => [
                'url' => '?action=icons',
                'description' => 'List all available icons',
                'method' => 'GET'
            ],
            'icon' => [
                'url' => '?action=icon&name={filename}',
                'description' => 'Get information about a specific icon',
                'method' => 'GET'
            ],
            'findIcon' => [
                'url' => '?action=findIcon&type={file|folder}&extension={ext}',
                'description' => 'Find the appropriate icon for a file type or folder',
                'method' => 'GET'
            ],
            'iconMappings' => [
                'url' => '?action=iconMappings',
                'description' => 'Get all icon mappings from icons.json',
                'method' => 'GET'
            ],
            'extensionMappings' => [
                'url' => '?action=extensionMappings&type={all|indexing|viewing}',
                'description' => 'Get extension mappings for indexing or viewing',
                'method' => 'GET'
            ],
            'getExtensionSetting' => [
                'url' => '?action=getExtensionSetting&extension={ext}&type={indexing|viewing}',
                'description' => 'Get the configuration setting for a specific file extension',
                'method' => 'GET'
            ],
            'status' => [
                'url' => '?action=status',
                'description' => 'Get API status and health information',
                'method' => 'GET'
            ]
        ],
        'icon_base_url' => 'https://api.indexer.ccls.icu/icons/',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    logRequest('info');
    jsonResponse($info);
}
?>