<?php
require_once __DIR__ . '/.indexer_files/php/URLRouter.php';
require_once __DIR__ . '/.indexer_files/php/IndexerCache.php';
require_once __DIR__ . '/.indexer_files/php/Markdown.php';
require_once __DIR__ . '/.indexer_files/php/CodeHighlight.php';
$scriptDir = dirname(__FILE__);
$baseDir = $scriptDir . '/files';
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$scriptPath = str_replace($documentRoot, '', $scriptDir);
$webPath = rtrim($scriptPath, '/');
if ($webPath === '' || $webPath === '/') {
    $webPath = '';
} else {
    $webPath = '/' . ltrim($webPath, '/');
}
$router = new URLRouter($scriptDir, $webPath, $baseDir);
$currentPath = $router->parseCleanURL();
$currentPath = ltrim($currentPath, '/');
$currentPath = str_replace(['../', './'], '', $currentPath);
$fullPath = $baseDir . '/' . $currentPath;
$webCurrentPath = '/files' . ($currentPath ? '/' . $currentPath : '');
$indexerFilesDir = $scriptDir . '/.indexer_files';
$zipCacheDir = $indexerFilesDir . '/zip_cache';
$indexCacheDir = $indexerFilesDir . '/index_cache';
$iconsDir = $indexerFilesDir . '/icons';
$localApiDir = $indexerFilesDir . '/local_api';
$localStyleDir = $localApiDir . '/style';
$configFile = $indexerFilesDir . '/config.json';
$localExtensionMapFile = $localApiDir . '/extensionMap.json';
$localIconsFile = $localApiDir . '/icons.json';
$config = loadConfiguration();
$cacheType = isset($config['main']['cache_type']) ? $config['main']['cache_type'] : 'sqlite';
$disableFileDownloads = isset($config['main']['disable_file_downloads']) ? $config['main']['disable_file_downloads'] : false;
$disableFolderDownloads = isset($config['main']['disable_folder_downloads']) ? $config['main']['disable_folder_downloads'] : false;
$iconType = isset($config['main']['icon_type']) ? $config['main']['icon_type'] : 'default';
$denyList = parseDenyAllowList(isset($config['main']['deny_list']) ? $config['main']['deny_list'] : '');
$allowList = parseDenyAllowList(isset($config['main']['allow_list']) ? $config['main']['allow_list'] : '');
$conflictingRules = findConflictingRules($denyList, $allowList);
$cacheInstance = initializeCache();
runCacheCleanup();
if (!empty($currentPath)) {
    if ($router->isFileRequest($currentPath)) {
        $router->handleFileRequest($currentPath);
        exit;
    }
    if ($router->isFolderRequest($currentPath)) {
        if (isset($_GET['download']) && $_GET['download'] === 'archive') {
            $router->handleFolderRequest($currentPath);
            exit;
        }
        $router->handleFolderRequest($currentPath);
    } else {
        http_response_code(404);
        exit('Path not found');
    }
}
function getSecurityStatus() {
    global $config;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || $_SERVER['SERVER_PORT'] == 443
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $hostname = '';
    if (isset($config['main']['access_url']) && !empty($config['main']['access_url'])) {
        $parsedUrl = parse_url($config['main']['access_url']);
        $hostname = $parsedUrl['host'] ?? $_SERVER['HTTP_HOST'];
    } else {
        $hostname = $_SERVER['HTTP_HOST'];
    }
    return [
        'secure' => $isHttps,
        'hostname' => $hostname
    ];
}
function getVersionInfo() {
    global $config, $indexerFilesDir;
    $currentVersion = $config['version'] ?? null;
    $cache = initializeCache();
    $cachedVersionData = $cache->get('remote_version_info', 'version');
    if ($cachedVersionData !== null) {
        return [
            'current' => $currentVersion,
            'remote' => $cachedVersionData['remote_version'],
            'type' => $cachedVersionData['installation_type']
        ];
    }
    $urls = [
        'https://raw.githubusercontent.com/5q12-ccls/5q12-s-Indexer/refs/heads/main/repo',
        'https://ccls.icu/src/repositories/5q12-indexer/main/repo/'
    ];
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => '5q12-Indexer',
            'header' => [
                'Accept: text/plain, */*',
                'Cache-Control: no-cache'
            ]
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $remoteVersion = null;
    $successfulUrl = null;
    foreach ($urls as $url) {
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $lines = preg_split('/\r\n|\r|\n/', $response);
            if (isset($lines[0]) && preg_match('/^VERSION=(.+)$/', trim($lines[0]), $matches)) {
                $remoteVersion = trim($matches[1]);
                $successfulUrl = $url;
                break;
            }
        }
    }
    $installationType = file_exists($indexerFilesDir . '/.docker') ? 'docker' :
                        (file_exists($indexerFilesDir . '/.script') ? 'script' : 'manual');
    if ($remoteVersion !== null) {
        $cacheData = [
            'remote_version' => $remoteVersion,
            'installation_type' => $installationType,
            'source_url' => $successfulUrl
        ];
        $cache->set('remote_version_info', 'version', $cacheData, 7200);
    }
    return [
        'current' => $currentVersion,
        'remote' => $remoteVersion,
        'type' => $installationType
    ];
}
function generateUpdateNotice() {
    $info = getVersionInfo();
    if (!$info['remote'] || !version_compare($info['current'], $info['remote'], '<')) {
        return '';
    }
    $instructions = [
        'docker' => 'Update using Docker: Pull the latest image and restart your container.',
        'script' => 'Update using the installation script: Run "5q12-index update".',
        'manual' => 'Manual installation: Download and replace files manually from the repository.'
    ];
    $changelogUrl = 'https://ccls.icu/src/repositories/5q12-indexer/releases/latest/changelog.md/?view=default';
    return '
    <div class="update-notice">
        <div class="update-notice-content">
            <strong>Update Available</strong><br>
            Current: v' . htmlspecialchars($info['current']) . ' | Latest: v' . htmlspecialchars($info['remote']) . '<br>
            <small>' . htmlspecialchars($instructions[$info['type']]) . '</small><br>
            <a href="' . htmlspecialchars($changelogUrl) . '" target="_blank" class="changelog-link">View Changelog</a>
        </div>
    </div>';
}
function parseDenyAllowList($listString) {
    if (empty(trim($listString))) {
        return [];
    }
    $items = array_map('trim', explode(',', $listString));
    $parsedList = [];
    foreach ($items as $item) {
        if (empty($item)) continue;
        $rule = [
            'original' => $item,
            'path' => '',
            'type' => 'exact',
            'target' => 'both'
        ];
        if (substr($item, -2) === '/*') {
            $rule['type'] = 'folder_recursive';
            $rule['path'] = substr($item, 0, -2);
            $rule['target'] = 'folder';
        }
        elseif (substr($item, -1) === '*' && substr($item, -2) !== '/*') {
            if (strpos($item, '.') !== false && strrpos($item, '.') > strrpos($item, '/')) {
                $rule['type'] = 'wildcard';
                $rule['path'] = $item;
                $rule['target'] = 'file';
            } else {
                $rule['type'] = 'wildcard';
                $rule['path'] = substr($item, 0, -1);
                $rule['target'] = 'folder';
            }
        }
        elseif (strpos($item, '*') !== false) {
            $rule['type'] = 'wildcard';
            $rule['path'] = $item;
            $rule['target'] = 'file';
        }
        else {
            $rule['type'] = 'exact';
            $rule['path'] = $item;
            if (strpos(basename($item), '.') !== false) {
                $rule['target'] = 'file';
            } else {
                $rule['target'] = 'folder';
            }
        }
        $parsedList[] = $rule;
    }
    return $parsedList;
}
function findConflictingRules($denyList, $allowList) {
    $conflicts = [];
    foreach ($denyList as $denyRule) {
        foreach ($allowList as $allowRule) {
            if ($denyRule['path'] === $allowRule['path'] && 
                $denyRule['type'] === $allowRule['type']) {
                $conflicts[] = [
                    'deny' => $denyRule['original'],
                    'allow' => $allowRule['original']
                ];
            }
        }
    }
    return $conflicts;
}
function pathMatchesRule($relativePath, $rule, $isFolder = false) {
    $rulePath = $rule['path'];
    if ($rule['type'] === 'exact') {
        return $relativePath === $rulePath;
    }
    if ($rule['type'] === 'wildcard') {
        if ($rule['target'] === 'file' && !$isFolder) {
            if (strpos($rulePath, '/') !== false) {
                $lastSlashPos = strrpos($rulePath, '/');
                $directory = substr($rulePath, 0, $lastSlashPos);
                $pattern = substr($rulePath, $lastSlashPos + 1);
                $fileDir = dirname($relativePath);
                $fileName = basename($relativePath);
                $directory = rtrim($directory, '/');
                $fileDir = rtrim($fileDir, '/');
                if ($fileDir === $directory) {
                    if (strpos($pattern, '.') === 0) {
                        $extension = substr($pattern, 1, -1);
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        return $fileExtension === $extension;
                    } else {
                        $regexPattern = str_replace('*', '.*', preg_quote($pattern, '/'));
                        return preg_match('/^' . $regexPattern . '$/i', $fileName);
                    }
                }
            }
        } elseif ($rule['target'] === 'folder') {
            if ($isFolder) {
                $pathParts = explode('/', $relativePath);
                $topLevelFolder = $pathParts[0];
                if (count($pathParts) === 1 && strpos($topLevelFolder, $rulePath) === 0) {
                    return true;
                }
                return false;
            } else {
                $fileDir = dirname($relativePath);
                if ($fileDir === '.') $fileDir = '';
                if (strpos($fileDir, '/') === false) {
                    return strpos($fileDir, $rulePath) === 0;
                }
                return false;
            }
        }
    }
    if ($rule['type'] === 'folder_recursive') {
        if ($relativePath === $rulePath) {
            return true;
        }
        return strpos($relativePath, $rulePath . '/') === 0;
    }
    return false;
}
function isPathDenied($relativePath, $isFolder = false) {
    global $denyList, $conflictingRules;
    foreach ($denyList as $rule) {
        $isConflicting = false;
        foreach ($conflictingRules as $conflict) {
            if ($conflict['deny'] === $rule['original']) {
                $isConflicting = true;
                break;
            }
        }
        if ($isConflicting) continue;
        if (pathMatchesRule($relativePath, $rule, $isFolder)) {
            return true;
        }
    }
    return false;
}
function isPathAllowed($relativePath, $isFolder = false) {
    global $allowList, $conflictingRules;
    foreach ($allowList as $rule) {
        $isConflicting = false;
        foreach ($conflictingRules as $conflict) {
            if ($conflict['allow'] === $rule['original']) {
                $isConflicting = true;
                break;
            }
        }
        if ($isConflicting) continue;
        if (pathMatchesRule($relativePath, $rule, $isFolder)) {
            return true;
        }
    }
    return false;
}
function isContentAllowedByWildcard($relativePath, $isFolder = false) {
    global $allowList, $conflictingRules;
    foreach ($allowList as $rule) {
        $isConflicting = false;
        foreach ($conflictingRules as $conflict) {
            if ($conflict['allow'] === $rule['original']) {
                $isConflicting = true;
                break;
            }
        }
        if ($isConflicting) continue;
        if ($rule['type'] === 'wildcard' && $rule['target'] === 'folder') {
            if (strpos($relativePath, $rule['path']) === 0) {
                return true;
            }
        }
    }
    return false;
}
function isFolderAccessible($currentPath) {
    global $denyList, $allowList, $conflictingRules, $config;
    if (isset($config['main']['index_all']) && $config['main']['index_all']) {
        return true;
    }
    foreach ($allowList as $rule) {
        $isConflicting = false;
        foreach ($conflictingRules as $conflict) {
            if ($conflict['allow'] === $rule['original']) {
                $isConflicting = true;
                break;
            }
        }
        if ($isConflicting) continue;
        if (pathMatchesRule($currentPath, $rule, true)) {
            return true;
        }
    }
    foreach ($denyList as $rule) {
        $isConflicting = false;
        foreach ($conflictingRules as $conflict) {
            if ($conflict['deny'] === $rule['original']) {
                $isConflicting = true;
                break;
            }
        }
        if ($isConflicting) continue;
        if ($rule['type'] === 'folder_recursive') {
            if ($currentPath === $rule['path'] || strpos($currentPath, $rule['path'] . '/') === 0) {
                return false;
            }
        } elseif ($rule['type'] === 'wildcard' && $rule['target'] === 'folder') {
            $pathParts = explode('/', $currentPath);
            $topLevelFolder = $pathParts[0];
            if (strpos($topLevelFolder, $rule['path']) === 0) {
                if ($currentPath === $topLevelFolder) {
                    return false;
                }
            }
        } elseif ($rule['type'] === 'exact') {
            if ($currentPath === $rule['path']) {
                return false;
            }
        }
    }
    return true;
}
function shouldIndexFile($filename, $extension) {
    global $config, $currentPath;
    $relativePath = $currentPath ? $currentPath . '/' . basename($filename) : basename($filename);
    if (empty($extension) || trim($extension) === '') {
        $indexNonDescript = isset($config['exclusions']['index_non-descript-files']) ? 
                           $config['exclusions']['index_non-descript-files'] : true;
        if (!$indexNonDescript) {
            return false;
        }
        if (isPathDenied($relativePath, false)) {
            if (isPathAllowed($relativePath, false)) {
                return true;
            }
            return false;
        }
        if (isContentAllowedByWildcard($relativePath, false)) {
            return true;
        }
        if (isPathAllowed($relativePath, false)) {
            return true;
        }
        if (isset($config['main']['index_all']) && $config['main']['index_all']) {
            return true;
        }
        if (strpos(basename($filename), '.') === 0) {
            if (!isset($config['main']['index_hidden']) || !$config['main']['index_hidden']) {
                return false;
            }
        }
        return true;
    }
    if (isPathDenied($relativePath, false)) {
        if (isPathAllowed($relativePath, false)) {
            return true;
        }
        return false;
    }
    if (isContentAllowedByWildcard($relativePath, false)) {
        return true;
    }
    if (isPathAllowed($relativePath, false)) {
        return true;
    }
    if (isset($config['main']['index_all']) && $config['main']['index_all']) {
        return true;
    }
    if (strpos(basename($filename), '.') === 0) {
        if (!isset($config['main']['index_hidden']) || !$config['main']['index_hidden']) {
            return false;
        }
    }
    $settingKey = getExtensionSetting($extension, 'indexing');
    if ($settingKey !== null) {
        return isset($config['exclusions'][$settingKey]) ? $config['exclusions'][$settingKey] : true;
    }
    return true;
}
function shouldIndexFolder($foldername) {
    global $config, $currentPath;
    $relativePath = $currentPath ? $currentPath . '/' . basename($foldername) : basename($foldername);
    if (basename($foldername) === '.indexer_files') {
        if (isset($config['main']['index_all']) && $config['main']['index_all']) {
            return true;
        }
        return false;
    }
    if (isPathDenied($relativePath, true)) {
        if (isPathAllowed($relativePath, true)) {
            return true;
        }
        return false;
    }
    if (isContentAllowedByWildcard($relativePath, true)) {
        return true;
    }
    if (isPathAllowed($relativePath, true)) {
        return true;
    }
    if (isset($config['main']['index_all']) && $config['main']['index_all']) {
        return true;
    }
    if (strpos(basename($foldername), '.') === 0) {
        if (!isset($config['main']['index_hidden']) || !$config['main']['index_hidden']) {
            return false;
        }
    }
    return isset($config['exclusions']['index_folders']) ? $config['exclusions']['index_folders'] : true;
}
function isFileAccessible($filePath, $currentPath, $extension) {
    global $config, $disableFileDownloads;
    if (!isFolderAccessible($currentPath)) {
        return false;
    }
    $fileName = basename($filePath);
    $relativePath = $currentPath ? $currentPath . '/' . $fileName : $fileName;
    if (isPathDenied($relativePath, false)) {
        if (!isPathAllowed($relativePath, false)) {
            return false;
        }
    }
    if (!shouldIndexFile($filePath, $extension)) {
        return false;
    }
    return true;
}
function loadConfiguration() {
    global $configFile;
    if (file_exists($configFile)) {
        $configData = json_decode(file_get_contents($configFile), true);
        if ($configData !== null) {
            return $configData;
        }
    }
    return [];
}
function getExtensionSetting($extension, $type = 'indexing') {
    if (empty($extension) || trim($extension) === '') {
        if ($type === 'indexing') {
            return 'index_non-descript-files';
        } elseif ($type === 'viewing') {
            return 'view_non-descript-files';
        }
        return null;
    }
    $extensionMappings = loadLocalExtensionMappings();
    if ($extensionMappings === null) {
        return null;
    }
    $extension = strtolower($extension);
    if ($type === 'indexing' && isset($extensionMappings['indexing'][$extension])) {
        return $extensionMappings['indexing'][$extension];
    } elseif ($type === 'viewing' && isset($extensionMappings['viewing'][$extension])) {
        return $extensionMappings['viewing'][$extension];
    }
    return null;
}
function loadLocalExtensionMappings() {
    global $localExtensionMapFile;
    if (!file_exists($localExtensionMapFile)) {
        return null;
    }
    $mappingData = file_get_contents($localExtensionMapFile);
    $mappings = json_decode($mappingData, true);
    return $mappings ?: null;
}
function loadLocalIconMappings() {
    global $localIconsFile;
    if (!file_exists($localIconsFile)) {
        return [];
    }
    $mappingData = file_get_contents($localIconsFile);
    $mappings = json_decode($mappingData, true);
    return $mappings ?: [];
}
function getIconPath($type, $extension = '') {
    global $webPath, $iconsDir, $iconType, $scriptDir;
    if ($iconType === 'disabled') {
        return null;
    }
    if ($iconType === 'emoji') {
        return null;
    }
    if ($iconType === 'minimal') {
        $iconFilename = ($type === 'folder') ? 'folder-proto.png' : 'non-descript-default-file.png';
        $iconPath = $iconsDir . '/' . $iconFilename;
        if (file_exists($iconPath)) {
            $relativePath = str_replace($scriptDir, '', $iconPath);
            return $webPath . $relativePath;
        }
        return null;
    }
    if ($iconType === 'default') {
        if ($type === 'folder') {
            $iconInfo = getIconFromLocal($type, $extension);
            if ($iconInfo !== null) {
                $relativePath = str_replace($scriptDir, '', $iconInfo['path']);
                return $webPath . $relativePath;
            }
        }
        if ($type === 'file') {
            if (empty($extension) || trim($extension) === '') {
                $iconFilename = 'non-descript-default-file.png';
                $iconPath = $iconsDir . '/' . $iconFilename;
                if (file_exists($iconPath)) {
                    $relativePath = str_replace($scriptDir, '', $iconPath);
                    return $webPath . $relativePath;
                }
                return null;
            }
            $iconMappings = loadLocalIconMappings();
            if ($iconMappings && is_array($iconMappings)) {
                $extension = strtolower($extension);
                if (!isset($iconMappings[$extension])) {
                    $iconFilename = 'non-descript-default-file.png';
                    $iconPath = $iconsDir . '/' . $iconFilename;
                    if (file_exists($iconPath)) {
                        $relativePath = str_replace($scriptDir, '', $iconPath);
                        return $webPath . $relativePath;
                    }
                    return null;
                }
            }
            $iconInfo = getIconFromLocal($type, $extension);
            if ($iconInfo !== null) {
                $relativePath = str_replace($scriptDir, '', $iconInfo['path']);
                return $webPath . $relativePath;
            }
        }
    }
    return null;
}
function getIconFromLocal($type, $extension = '') {
    global $iconsDir;
    $iconMappings = loadLocalIconMappings();
    if ($type === 'folder') {
        $iconFile = isset($iconMappings['folder']) ? $iconMappings['folder'] : 'folder-proto.png';
    } else {
        $extension = strtolower($extension);
        $iconFile = isset($iconMappings[$extension]) ? $iconMappings[$extension] : 'non-descript-default-file.png';
    }
    $iconPath = $iconsDir . '/' . $iconFile;
    if (!file_exists($iconPath)) {
        if ($type === 'folder') {
            $iconFile = 'folder-proto.png';
        } else {
            $iconFile = 'non-descript-default-file.png';
        }
        $iconPath = $iconsDir . '/' . $iconFile;
        if (!file_exists($iconPath)) {
            return null;
        }
    }
    return [
        'filename' => $iconFile,
        'path' => $iconPath,
        'size' => filesize($iconPath),
        'last_modified' => filemtime($iconPath)
    ];
}
function isFileViewable($extension) {
    global $config;
    if (empty($extension) || trim($extension) === '') {
        $viewNonDescript = isset($config['viewable_files']['view_non-descript-files']) ? 
                          $config['viewable_files']['view_non-descript-files'] : false;
        return $viewNonDescript;
    }
    $settingKey = getExtensionSetting($extension, 'viewing');
    if ($settingKey !== null) {
        return isset($config['viewable_files'][$settingKey]) ? $config['viewable_files'][$settingKey] : false;
    }
    return false;
}
function cleanupOldTempFiles() {
    global $zipCacheDir;
    if (!is_dir($zipCacheDir)) return;
    $files = glob($zipCacheDir . '/*');
    $fiveMinutesAgo = time() - 300;
    foreach ($files as $file) {
        if (filemtime($file) < $fiveMinutesAgo) {
            if (is_dir($file)) {
                deleteDirectory($file);
            } else {
                unlink($file);
            }
        }
    }
}
function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_dir($file)) {
            deleteDirectory($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}
function getDirectorySize($path) {
    $size = 0;
    if (is_dir($path)) {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                $size += getDirectorySize($filePath);
            } else {
                $size += filesize($filePath);
            }
        }
    }
    return $size;
}
$cacheInstance = null;
function initializeCache() {
    global $cacheInstance, $cacheType, $indexCacheDir;
    if ($cacheInstance === null) {
        $cacheInstance = new IndexerCache($cacheType, $indexCacheDir);
    }
    return $cacheInstance;
}
function getCacheData($path) {
    $cache = initializeCache();
    return $cache->get($path, 'directory');
}
function setCacheData($path, $data) {
    $cache = initializeCache();
    $cache->set($path, 'directory', $data);
}
function runCacheCleanup() {
    $cache = initializeCache();
    $cache->cleanup();
}
function copyDirectoryExcludePhp($source, $destination) {
    global $currentPath;
    if (!is_dir($source)) return false;
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    $files = scandir($source);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        $sourcePath = $source . '/' . $file;
        $destPath = $destination . '/' . $file;
        if (is_dir($sourcePath)) {
            if (shouldIndexFolder($sourcePath)) {
                copyDirectoryExcludePhp($sourcePath, $destPath);
            }
        } else {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (shouldIndexFile($sourcePath, $extension)) {
                copy($sourcePath, $destPath);
            }
        }
    }
    return true;
}
function addDirectoryToZip($zip, $dir, $zipPath = '') {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        $filePath = $dir . '/' . $file;
        $zipFilePath = $zipPath ? $zipPath . '/' . $file : $file;
        if (is_dir($filePath)) {
            $zip->addEmptyDir($zipFilePath);
            addDirectoryToZip($zip, $filePath, $zipFilePath);
        } else {
            $zip->addFile($filePath, $zipFilePath);
        }
    }
}
function getFileUrl($path, $filename) {
    global $router, $disableFileDownloads;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (isFileViewable($extension)) {
        return $router->generateFileURL($path, $filename, 'view', 'default');
    } else {
        if ($disableFileDownloads) {
            return '#';
        }
        return $router->generateFileURL($path, $filename, 'download');
    }
}
function getSortUrl($sortBy, $currentSort, $currentDir, $currentPath) {
    global $router;
    return $router->generateSortURL($sortBy, $currentSort, $currentDir, $currentPath);
}
function getBreadcrumbs($currentPath) {
    global $router;
    return $router->generateBreadcrumbs($currentPath);
}
function getBaseUrl($currentPath, $webPath) {
    if (empty($currentPath)) {
        return $webPath . '/';
    } else {
        return $webPath . '/' . $currentPath . '/';
    }
}
function generateSharePopup($shareType, $shareFile, $currentPath) {
    global $router, $webPath;
    if ($shareType === 'view') {
        if (is_dir($GLOBALS['fullPath'] . '/' . $shareFile)) {
            $shareUrl = $router->generateFolderURL($currentPath, $shareFile, 'view');
            $popupTitle = 'Share Folder Link';
            $popupText = 'Copy the following URL to share folder';
        } else {
            $shareUrl = $router->generateFileURL($currentPath, $shareFile, 'view', 'default');
            $popupTitle = 'Share File Link';
            $popupText = 'Copy the following URL to view file';
        }
    } elseif ($shareType === 'folder') {
        $shareUrl = $router->generateFolderURL($currentPath, '', 'view');
        $popupTitle = 'Share Folder Location';
        $popupText = 'Copy the following URL to share the folder containing';
    } elseif ($shareType === 'download') {
        $isFolder = is_dir($GLOBALS['fullPath'] . '/' . $shareFile);
        if ($isFolder) {
            $shareUrl = $router->generateFolderURL($currentPath, $shareFile, 'download');
        } else {
            $shareUrl = $router->generateFileURL($currentPath, $shareFile, 'download');
        }
        $popupTitle = 'Share Download Link';
        $popupText = 'Copy the following URL to download';
    }
    $absoluteShareUrl = getAbsoluteUrl($shareUrl);
    $closeParams = $_GET;
    unset($closeParams['share_popup']);
    unset($closeParams['share_file']);
    $closeUrl = $router->generateFolderURL($currentPath, '', 'view');
    if ($closeParams) {
        $closeUrl .= '?' . http_build_query($closeParams);
    }
    return '
    <div class="share-popup-overlay">
        <div class="share-popup">
            <h3>' . htmlspecialchars($popupTitle) . '</h3>
            <p>' . htmlspecialchars($popupText) . ' "<strong>' . htmlspecialchars($shareFile) . '</strong>":</p>
            <div class="share-url-container">' . htmlspecialchars($absoluteShareUrl) . '</div>
            <p><small>Select the URL above and copy it (Ctrl+C / Cmd+C)</small></p>
            <div class="popup-buttons">
                <a href="' . htmlspecialchars($closeUrl) . '" class="popup-btn">Close</a>
            </div>
        </div>
    </div>';
}
function getFileActionMenu($file, $currentPath) {
    global $disableFileDownloads, $router, $webPath;
    $extension = $file['extension'];
    $fileName = $file['name'];
    $isViewable = isFileViewable($extension);
    $showActions = isset($_GET['action']) && $_GET['action'] === $fileName;
    $anchorId = 'file-' . preg_replace('/[^a-zA-Z0-9-_]/', '-', $fileName);
    $menu = '<div class="item-actions-menu" id="' . htmlspecialchars($anchorId) . '">';
    if ($showActions) {
        $closeParams = $_GET;
        unset($closeParams['action']);
        unset($closeParams['options']);
        $closeUrl = $router->generateFolderURL($currentPath, '', 'view');
        if ($closeParams) {
            $closeUrl .= '?' . http_build_query($closeParams);
        }
        $closeUrl .= '#' . $anchorId;
        $menu .= '<a href="' . $closeUrl . '" class="actions-toggle">×</a>';
        $menu .= '<div class="actions-dropdown">';
        if ($isViewable) {
            $openUrl = $router->generateFileURL($currentPath, $fileName, 'view', 'default');
            $menu .= '<a href="' . htmlspecialchars($openUrl) . '">Open</a>';
            $menu .= '<a href="' . htmlspecialchars($openUrl) . '" target="_blank">Open in new tab</a>';
        }
        if (!$disableFileDownloads) {
            $downloadUrl = $router->generateFileURL($currentPath, $fileName, 'download');
            $menu .= '<a href="' . htmlspecialchars($downloadUrl) . '">Download</a>';
        }
        $baseUrl = $router->generateFolderURL($currentPath, '', 'view');
        $shareParams = array_merge($_GET, ['share_popup' => 'view', 'share_file' => $fileName]);
        $shareUrl = $baseUrl . '?' . http_build_query($shareParams);
        $menu .= '<a href="' . $shareUrl . '">Share</a>';
        if (!$disableFileDownloads) {
            $shareParams = array_merge($_GET, ['share_popup' => 'download', 'share_file' => $fileName]);
            $shareUrl = $baseUrl . '?' . http_build_query($shareParams);
            $menu .= '<a href="' . $shareUrl . '">Share Download</a>';
        }
        $menu .= '</div>';
    } else {
        $actionParams = $_GET;
        unset($actionParams['options']);
        if (isset($actionParams['action'])) {
            unset($actionParams['action']);
        }
        $actionParams['action'] = $fileName;
        $actionUrl = $router->generateFolderURL($currentPath, '', 'view');
        $actionUrl .= '?' . http_build_query($actionParams) . '#' . $anchorId;
        $menu .= '<a href="' . $actionUrl . '" class="actions-toggle">⋯</a>';
    }
    $menu .= '</div>';
    return $menu;
}
function getFolderActionMenu($folder, $currentPath) {
    global $disableFolderDownloads, $router, $webPath;
    $folderName = $folder['name'];
    $showActions = isset($_GET['action']) && $_GET['action'] === $folderName;
    $anchorId = 'folder-' . preg_replace('/[^a-zA-Z0-9-_]/', '-', $folderName);
    $menu = '<div class="item-actions-menu" id="' . htmlspecialchars($anchorId) . '">';
    if ($showActions) {
        $closeParams = $_GET;
        unset($closeParams['action']);
        unset($closeParams['options']);
        $closeUrl = $router->generateFolderURL($currentPath, '', 'view');
        if ($closeParams) {
            $closeUrl .= '?' . http_build_query($closeParams);
        }
        $closeUrl .= '#' . $anchorId;
        $menu .= '<a href="' . $closeUrl . '" class="actions-toggle">×</a>';
        $menu .= '<div class="actions-dropdown">';
        $openUrl = $router->generateFolderURL($currentPath, $folderName, 'view');
        $menu .= '<a href="' . htmlspecialchars($openUrl) . '">Open</a>';
        $menu .= '<a href="' . htmlspecialchars($openUrl) . '" target="_blank">Open in new tab</a>';
        if (!$disableFolderDownloads) {
            $downloadUrl = $router->generateFolderURL($currentPath, $folderName, 'download');
            $menu .= '<a href="' . htmlspecialchars($downloadUrl) . '">Download</a>';
        }
        $baseUrl = $router->generateFolderURL($currentPath, '', 'view');
        $shareParams = array_merge($_GET, ['share_popup' => 'view', 'share_file' => $folderName]);
        $shareUrl = $baseUrl . '?' . http_build_query($shareParams);
        $menu .= '<a href="' . $shareUrl . '">Share</a>';
        if (!$disableFolderDownloads) {
            $shareParams = array_merge($_GET, ['share_popup' => 'download', 'share_file' => $folderName]);
            $shareUrl = $baseUrl . '?' . http_build_query($shareParams);
            $menu .= '<a href="' . $shareUrl . '">Share Download</a>';
        }
        $menu .= '</div>';
    } else {
        $actionParams = $_GET;
        unset($actionParams['options']);
        if (isset($actionParams['action'])) {
            unset($actionParams['action']);
        }
        $actionParams['action'] = $folderName;
        $actionUrl = $router->generateFolderURL($currentPath, '', 'view');
        $actionUrl .= '?' . http_build_query($actionParams) . '#' . $anchorId;
        $menu .= '<a href="' . $actionUrl . '" class="actions-toggle">⋯</a>';
    }
    $menu .= '</div>';
    return $menu;
}
function formatBytes($size) {
    if ($size === null) return '-';
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, 1) . ' ' . $units[$i];
}
function getSortParams() {
    $sortBy = $_GET['sort'] ?? 'name';
    $sortDir = $_GET['dir'] ?? 'asc';
    $validSorts = ['name', 'size', 'modified', 'type'];
    $validDirs = ['asc', 'desc'];
    if (!in_array($sortBy, $validSorts)) $sortBy = 'name';
    if (!in_array($sortDir, $validDirs)) $sortDir = 'asc';
    return ['sort' => $sortBy, 'dir' => $sortDir];
}
function sortItems($items, $sortBy, $sortDir) {
    usort($items, function($a, $b) use ($sortBy, $sortDir) {
        $result = 0;
        switch ($sortBy) {
            case 'name':
                $result = strcasecmp($a['name'], $b['name']);
                break;
            case 'size':
                $result = $a['size'] <=> $b['size'];
                break;
            case 'modified':
                $result = $a['modified'] <=> $b['modified'];
                break;
            case 'type':
                $aExt = isset($a['extension']) ? $a['extension'] : ($a['is_dir'] ? 'folder' : '');
                $bExt = isset($b['extension']) ? $b['extension'] : ($b['is_dir'] ? 'folder' : '');
                $result = strcasecmp($aExt, $bExt);
                break;
        }
        return $sortDir === 'desc' ? -$result : $result;
    });
    return $items;
}
function getSortIndicator($column, $currentSort, $currentDir) {
    if ($column !== $currentSort) {
        return '';
    }
    return $currentDir === 'asc' ? ' ↑' : ' ↓';
}
function getAbsoluteUrl($relativeUrl) {
    global $config;
    if (isset($config['main']['access_url']) && !empty($config['main']['access_url'])) {
        $baseUrl = rtrim($config['main']['access_url'], '/');
        if (strpos($relativeUrl, '/') === 0) {
            return $baseUrl . $relativeUrl;
        } else {
            $currentDir = dirname($_SERVER['REQUEST_URI']);
            if ($currentDir === '/' || $currentDir === '\\') {
                return $baseUrl . '/' . $relativeUrl;
            } else {
                return $baseUrl . rtrim($currentDir, '/') . '/' . $relativeUrl;
            }
        }
    }
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    if (strpos($relativeUrl, '/') === 0) {
        return $protocol . '://' . $host . $relativeUrl;
    } else {
        $currentDir = dirname($_SERVER['REQUEST_URI']);
        return $protocol . '://' . $host . rtrim($currentDir, '/') . '/' . $relativeUrl;
    }
}
if (!is_dir($fullPath)) {
    http_response_code(404);
    die('Directory not found');
}
if (!isFolderAccessible($currentPath)) {
    http_response_code(403);
    header('Location: ' . $_SERVER['SCRIPT_NAME']);
    exit;
}
$sortParams = getSortParams();
$sortBy = $sortParams['sort'];
$sortDir = $sortParams['dir'];
$cacheKey = $currentPath . '_sort_' . $sortBy . '_' . $sortDir;
$cachedData = getCacheData($cacheKey);
if ($cachedData !== null) {
    $directories = $cachedData['directories'];
    $files = $cachedData['files'];
} else {
    $items = scandir($fullPath);
    $directories = [];
    $files = [];
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $itemPath = $fullPath . '/' . $item;
        $itemInfo = [
            'name' => $item,
            'modified' => filemtime($itemPath),
            'is_dir' => is_dir($itemPath)
        ];
        if ($itemInfo['is_dir']) {
            if (shouldIndexFolder(($currentPath ? $currentPath . '/' : '') . $item)) {
                $itemInfo['size'] = getDirectorySize($itemPath);
                $directories[] = $itemInfo;
            }
        } else {
            $extension = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (shouldIndexFile(($currentPath ? $currentPath . '/' : '') . $item, $extension)) {
                $itemInfo['size'] = filesize($itemPath);
                $itemInfo['extension'] = $extension;
                $files[] = $itemInfo;
            }
        }
    }
    $directories = sortItems($directories, $sortBy, $sortDir);
    $files = sortItems($files, $sortBy, $sortDir);
    setCacheData($cacheKey, [
        'directories' => $directories,
        'files' => $files
    ]);
}
function parseSizeString($sizeString) {
    if (empty($sizeString) || !is_string($sizeString)) {
        return null;
    } 
    $sizeString = trim(strtoupper($sizeString));
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(KB|MB|GB|TB|B)?$/', $sizeString, $matches)) {
        $number = floatval($matches[1]);
        $unit = isset($matches[2]) ? $matches[2] : 'B';
        $multipliers = [
            'B' => 1,
            'KB' => 1024,
            'MB' => 1024 * 1024,
            'GB' => 1024 * 1024 * 1024,
            'TB' => 1024 * 1024 * 1024 * 1024
        ];
        if (isset($multipliers[$unit])) {
            return intval($number * $multipliers[$unit]);
        }
    }
    return null;
}
function getMaxDownloadSize($type) {
    global $config;
    if ($type === 'file') {
        $defaultSize = 2 * 1024 * 1024 * 1024;
    } else {
        $defaultSize = 50 * 1024 * 1024;
    }
    $configKey = ($type === 'file') ? 'max_download_size_file' : 'max_download_size_folder';
    if (isset($config['main'][$configKey])) {
        $parsedSize = parseSizeString($config['main'][$configKey]);
        return $parsedSize !== null ? $parsedSize : $defaultSize;
    }
    return $defaultSize;
}
function formatSizeForError($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo empty($currentPath) ? '5q12 Indexer' : htmlspecialchars(basename($currentPath)); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $webPath; ?>/.indexer_files/favicon/icon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $webPath; ?>/.indexer_files/favicon/16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $webPath; ?>/.indexer_files/favicon/32x32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="<?php echo $webPath; ?>/.indexer_files/favicon/48x48.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $webPath; ?>/.indexer_files/favicon/96x96.png">
    <link rel="icon" type="image/png" sizes="144x144" href="<?php echo $webPath; ?>/.indexer_files/favicon/144x144.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $webPath; ?>/.indexer_files/favicon/192x192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $webPath; ?>/.indexer_files/favicon/180x180.png">
    <link rel="stylesheet" href="<?php echo $webPath; ?>/.indexer_files/local_api/style/base-1.2.0.min.css">
    <link rel="stylesheet" href="<?php echo $webPath; ?>/.indexer_files/local_api/style/index-1.2.0.min.css">
</head>
<body<?php if ($iconType === 'disabled') echo ' class="icon-disabled"'; ?>>
    <?php
    $securityStatus = getSecurityStatus();
    $lockIcon = $securityStatus['secure'] 
        ? $webPath . '/.indexer_files/icons/app/green.png'
        : $webPath . '/.indexer_files/icons/app/red.png';
    ?>
    <div class="security-bar">
        <span class="security-lock" data-tooltip="<?php echo $securityStatus['secure'] ? 'Connection is secure (HTTPS)' : 'Connection is not secure - Consider using HTTPS'; ?>">
            <img src="<?php echo htmlspecialchars($lockIcon); ?>" alt="<?php echo $securityStatus['secure'] ? 'Secure' : 'Not Secure'; ?>">
        </span>
        <span class="security-hostname"><?php echo htmlspecialchars($securityStatus['hostname']); ?></span>
    </div>
    <div class="container">
        <div class="header">
            <h1><?php echo empty($currentPath) ? '5q12-Indexer' : '5q12-Indexer: /' . htmlspecialchars(basename($currentPath)); ?></h1>
            <div class="breadcrumbs"><?php echo getBreadcrumbs($currentPath); ?></div>
        </div>
        <?php
        $sortParams = getSortParams();
        $currentSort = $sortParams['sort'];
        $currentDir = $sortParams['dir'];
        $showOptions = isset($_GET['options']);
        ?>
        <div class="file-list">
            <div class="header-row">
                <div class="file-item">
                    <div class="file-icon"></div>
                    <div class="file-name">
                        <a href="<?php echo getSortUrl('name', $currentSort, $currentDir, $currentPath); ?>" 
                        class="sortable-header <?php echo $currentSort === 'name' ? 'active' : ''; ?>">
                            Name<?php echo getSortIndicator('name', $currentSort, $currentDir); ?>
                        </a>
                    </div>
                    <div class="file-size">
                        <a href="<?php echo getSortUrl('size', $currentSort, $currentDir, $currentPath); ?>" 
                        class="sortable-header <?php echo $currentSort === 'size' ? 'active' : ''; ?>">
                            Size<?php echo getSortIndicator('size', $currentSort, $currentDir); ?>
                        </a>
                    </div>
                    <div class="file-date">
                        <a href="<?php echo getSortUrl('modified', $currentSort, $currentDir, $currentPath); ?>" 
                        class="sortable-header <?php echo $currentSort === 'modified' ? 'active' : ''; ?>">
                            Modified<?php echo getSortIndicator('modified', $currentSort, $currentDir); ?>
                        </a>
                    </div>
                    <div class="options-menu-container">
                        <div class="options-menu">
                            <?php if ($showOptions): ?>
                                <a href="<?php 
                                    $closeParams = $_GET;
                                    unset($closeParams['options']);
                                    unset($closeParams['action']);
                                    $closeUrl = $router->generateFolderURL($currentPath, '', 'view');
                                    if ($closeParams) {
                                        $closeUrl .= '?' . http_build_query($closeParams);
                                    }
                                    echo $closeUrl;
                                ?>" class="options-toggle">×</a>
                                <div class="options-dropdown">
                                    <?php
                                    $baseParams = $_GET;
                                    unset($baseParams['options']);
                                    $baseUrl = $router->generateFolderURL($currentPath, '', 'view');
                                    $sortOptions = [
                                        ['sort' => 'name', 'dir' => 'asc', 'label' => 'Name (A-Z)'],
                                        ['sort' => 'name', 'dir' => 'desc', 'label' => 'Name (Z-A)'],
                                        ['sort' => 'size', 'dir' => 'asc', 'label' => 'Size (Small to Large)'],
                                        ['sort' => 'size', 'dir' => 'desc', 'label' => 'Size (Large to Small)'],
                                        ['sort' => 'modified', 'dir' => 'asc', 'label' => 'Date Modified (Oldest First)'],
                                        ['sort' => 'modified', 'dir' => 'desc', 'label' => 'Date Modified (Newest First)'],
                                        ['sort' => 'type', 'dir' => 'asc', 'label' => 'Type (A-Z)'],
                                        ['sort' => 'type', 'dir' => 'desc', 'label' => 'Type (Z-A)']
                                    ];
                                    foreach ($sortOptions as $option) {
                                        $params = array_merge($baseParams, [
                                            'sort' => $option['sort'],
                                            'dir' => $option['dir']
                                        ]);
                                        $isActive = ($currentSort === $option['sort'] && $currentDir === $option['dir']);
                                        echo '<a href="' . $baseUrl . '?' . http_build_query($params) . '" class="' . ($isActive ? 'active' : '') . '">' . $option['label'] . '</a>';
                                    }
                                    ?>
                                </div>
                            <?php else: ?>
                                <?php
                                $optionsParams = $_GET;
                                unset($optionsParams['action']);
                                $optionsParams['options'] = '1';
                                $baseUrl = $router->generateFolderURL($currentPath, '', 'view');
                                ?>
                                <a href="<?php echo $baseUrl . '?' . http_build_query($optionsParams); ?>" class="options-toggle">⋯</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php if ($currentPath): ?>
            <div class="parent-dir">
                <div class="file-item">
                    <div class="file-icon">
                        <?php 
                        $folderIconPath = getIconPath('folder');
                        if ($iconType === 'disabled'): ?>
                        <?php elseif ($iconType === 'emoji' || $folderIconPath === null): ?>
                            <div class="file-icon-emoji">📁</div>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($folderIconPath); ?>" alt="Folder">
                        <?php endif; ?>
                    </div>
                    <div class="file-name">
                        <a href="<?php echo $router->generateFolderURL(dirname($currentPath) === '.' ? '' : dirname($currentPath), '', 'view'); ?>">
                            ../
                        </a>
                    </div>
                    <div class="file-size">-</div>
                    <div class="file-date">-</div>
                    <div class="item-actions-menu-container"></div>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach ($directories as $dir): ?>
            <div class="directory">
                <div class="file-item">
                    <div class="file-icon">
                        <?php 
                        $folderIconPath = getIconPath('folder');
                        if ($iconType === 'disabled'): ?>
                        <?php elseif ($iconType === 'emoji' || $folderIconPath === null): ?>
                            <div class="file-icon-emoji">📁</div>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($folderIconPath); ?>" alt="Folder">
                        <?php endif; ?>
                    </div>
                    <div class="file-name">
                        <a href="<?php echo $router->generateFolderURL($currentPath, $dir['name'], 'view'); ?>">
                            <?php echo htmlspecialchars($dir['name']); ?>/
                        </a>
                    </div>
                    <div class="file-size"><?php echo formatBytes($dir['size']); ?></div>
                    <div class="file-date"><?php echo date('Y-m-d H:i', $dir['modified']); ?></div>
                    <div class="item-actions-menu-container">
                        <?php echo getFolderActionMenu($dir, $currentPath); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php foreach ($files as $file): ?>
            <div class="file">
                <div class="file-item">
                    <div class="file-icon">
                        <?php 
                        $fileIconPath = getIconPath('file', $file['extension']);
                        if ($iconType === 'disabled'): ?>
                        <?php elseif ($iconType === 'emoji' || $fileIconPath === null): ?>
                            <div class="file-icon-emoji">📄</div>
                        <?php else: ?>
                            <img src="<?php echo htmlspecialchars($fileIconPath); ?>" alt="File">
                        <?php endif; ?>
                    </div>
                    <div class="file-name">
                        <a href="<?php echo getFileUrl($currentPath, $file['name']); ?>" <?php echo isFileViewable($file['extension']) ? 'target="_blank"' : ''; ?>>
                            <?php echo htmlspecialchars($file['name']); ?>
                        </a>
                    </div>
                    <div class="file-size"><?php echo formatBytes($file['size']); ?></div>
                    <div class="file-date"><?php echo date('Y-m-d H:i', $file['modified']); ?></div>
                    <div class="item-actions-menu-container">
                        <?php echo getFileActionMenu($file, $currentPath); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    if (isset($_GET['share_popup']) && isset($_GET['share_file'])) {
        $shareType = $_GET['share_popup'];
        $shareFile = $_GET['share_file'];
        echo generateSharePopup($shareType, $shareFile, $currentPath);
    }
    echo generateUpdateNotice();
    ?>
</body>
</html>