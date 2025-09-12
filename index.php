<?php
$apiBaseUrl = 'https://api.indexer.ccls.icu';
$scriptDir = dirname(__FILE__);
$baseDir = $scriptDir;
$documentRoot = $_SERVER['DOCUMENT_ROOT'];
$scriptPath = str_replace($documentRoot, '', $scriptDir);
$webPath = rtrim($scriptPath, '/');
if ($webPath === '' || $webPath === '/') {
    $webPath = '';
} else {
    $webPath = '/' . ltrim($webPath, '/');
}
$currentPath = isset($_GET['path']) ? $_GET['path'] : '';
$currentPath = ltrim($currentPath, '/');
$currentPath = str_replace(['../', './'], '', $currentPath);
$fullPath = $baseDir . '/' . $currentPath;
$webCurrentPath = $webPath . '/' . $currentPath;
$indexerFilesDir = $scriptDir . '/.indexer_files';
$zipCacheDir = $indexerFilesDir . '/zip_cache';
$indexCacheDir = $indexerFilesDir . '/index_cache';
$iconsDir = $indexerFilesDir . '/icons';
$localApiDir = $indexerFilesDir . '/local_api';
$localStyleDir = $localApiDir . '/style';
$configFile = $indexerFilesDir . '/config.json';
$localExtensionMapFile = $localApiDir . '/extensionMap.json';
$localIconsFile = $localApiDir . '/icons.json';
initializeDirectories();
$config = loadConfiguration();
if (!isset($config['main']['disable_api']) || !$config['main']['disable_api']) {
    $updateResult = checkAndUpdateConfig();
    if ($updateResult) {
        $config = loadConfiguration();
    }
}
$localIcons = isset($config['main']['local_icons']) ? $config['main']['local_icons'] : false;
$disableApi = isset($config['main']['disable_api']) ? $config['main']['disable_api'] : false;
$cacheType = isset($config['main']['cache_type']) ? $config['main']['cache_type'] : 'sqlite';
$disableFileDownloads = isset($config['main']['disable_file_downloads']) ? $config['main']['disable_file_downloads'] : false;
$disableFolderDownloads = isset($config['main']['disable_folder_downloads']) ? $config['main']['disable_folder_downloads'] : false;
$denyList = parseDenyAllowList(isset($config['main']['deny_list']) ? $config['main']['deny_list'] : '');
$allowList = parseDenyAllowList(isset($config['main']['allow_list']) ? $config['main']['allow_list'] : '');
$conflictingRules = findConflictingRules($denyList, $allowList);
if ($disableApi) {
    ensureLocalResources();
}
$cacheInstance = initializeCache();
runCacheCleanup();
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
        } elseif ($rule['target'] === 'folder' && $isFolder) {
            return strpos($relativePath, $rulePath) === 0;
        }
    }
    if ($rule['type'] === 'folder_recursive') {
        return strpos($relativePath, $rulePath . '/') === 0 || $relativePath === $rulePath;
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
function checkAndUpdateConfig() {
    global $apiBaseUrl, $configFile, $config, $disableApi, $cacheInstance;
    if ($disableApi) {
        return false;
    }
    $localVersion = isset($config['version']) ? $config['version'] : '1.0';
    try {
        $cacheKey = 'version_check_' . $localVersion;
        if ($cacheInstance !== null) {
            $cachedResult = $cacheInstance->get($cacheKey, 'version');
            if ($cachedResult !== null) {
                return $cachedResult;
            }
        }
        $versionCheckUrl = $apiBaseUrl . '/api.php?action=versionCheck&current_version=' . urlencode($localVersion);
        $response = @file_get_contents($versionCheckUrl);
        if ($response === false) {
            if ($cacheInstance !== null) {
                $cacheInstance->set($cacheKey, 'version', false, 900);
            }
            return false;
        }
        $data = json_decode($response, true);
        if (!$data || !isset($data['success']) || !$data['success']) {
            if ($cacheInstance !== null) {
                $cacheInstance->set($cacheKey, 'version', false, 900);
            }
            return false;
        }
        if ($data['update_needed'] === false) {
            if ($cacheInstance !== null) {
                $cacheInstance->set($cacheKey, 'version', false, 3600);
            }
            return false;
        }
        $latestConfigUrl = $apiBaseUrl . '/api.php?action=config';
        $latestConfigResponse = @file_get_contents($latestConfigUrl);
        if ($latestConfigResponse === false) {
            return false;
        }
        $latestConfigData = json_decode($latestConfigResponse, true);
        if (!$latestConfigData || !isset($latestConfigData['config'])) {
            return false;
        }
        $latestConfig = $latestConfigData['config'];
        $latestVersion = isset($latestConfig['version']) ? $latestConfig['version'] : '1.0';
        if ($localVersion === $latestVersion) {
            if ($cacheInstance !== null) {
                $cacheInstance->set($cacheKey, 'version', false, 3600);
            }
            return false;
        }
        $updateResult = mergeConfigUpdates($config, $latestConfig);
        if ($updateResult) {
            if ($cacheInstance !== null) {
                $cacheInstance->set($cacheKey, 'version', true, 3600);
            }
            logConfigUpdate($localVersion, $latestVersion, $updateResult['changes']);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Config update error: " . $e->getMessage());
        return false;
    }
}
/**
 * Merge new config settings with existing config
 */
function mergeConfigUpdates($localConfig, $latestConfig) {
    global $configFile;
    try {
        $changes = [];
        $updated = false;
        $mergedConfig = $localConfig;
        $oldVersion = isset($localConfig['version']) ? $localConfig['version'] : '1.0';
        $newVersion = isset($latestConfig['version']) ? $latestConfig['version'] : '1.0';
        $mergedConfig['version'] = $newVersion;
        $changes[] = "Updated version from {$oldVersion} to {$newVersion}";
        $updated = true;
        $sectionsToCheck = ['main', 'exclusions', 'viewable_files'];
        foreach ($sectionsToCheck as $section) {
            if (!isset($latestConfig[$section])) {
                continue;
            }
            if (!isset($mergedConfig[$section])) {
                $mergedConfig[$section] = [];
            }
            foreach ($latestConfig[$section] as $key => $value) {
                if (!array_key_exists($key, $mergedConfig[$section])) {
                    $mergedConfig[$section][$key] = $value;
                    $changes[] = "Added new setting: {$section}.{$key} = " . json_encode($value);
                    $updated = true;
                }
            }
            ksort($mergedConfig[$section]);
        }
        if ($updated) {
            $backupFile = $configFile . '.backup.' . date('Y-m-d_H-i-s');
            copy($configFile, $backupFile);
            $jsonData = json_encode($mergedConfig, JSON_PRETTY_PRINT);
            if (file_put_contents($configFile, $jsonData) !== false) {
                global $config;
                $config = $mergedConfig;
                return [
                    'success' => true,
                    'changes' => $changes,
                    'backup_file' => $backupFile
                ];
            } else {
                copy($backupFile, $configFile);
                unlink($backupFile);
                return false;
            }
        }
        return false;
    } catch (Exception $e) {
        error_log("Config merge error: " . $e->getMessage());
        return false;
    }
}
/**
 * Log config update activity
 */
function logConfigUpdate($oldVersion, $newVersion, $changes) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'old_version' => $oldVersion,
        'new_version' => $newVersion,
        'changes_count' => count($changes),
        'changes' => $changes
    ];
    $logFile = dirname(__FILE__) . '/.indexer_files/config_updates.log';
    $logEntry = json_encode($logData) . "\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
function ensureLocalResources() {
    global $apiBaseUrl, $localApiDir, $localStyleDir, $localExtensionMapFile, $localIconsFile, $iconsDir;
    if (!is_dir($localApiDir)) {
        mkdir($localApiDir, 0755, true);
    }
    if (!is_dir($localStyleDir)) {
        mkdir($localStyleDir, 0755, true);
    }
    if (!is_dir($iconsDir)) {
        mkdir($iconsDir, 0755, true);
    }
    if (!file_exists($localExtensionMapFile)) {
        $extensionMapUrl = $apiBaseUrl . '/extensionMap.json';
        $extensionMapData = @file_get_contents($extensionMapUrl);
        if ($extensionMapData !== false) {
            file_put_contents($localExtensionMapFile, $extensionMapData);
        }
    }
    if (!file_exists($localIconsFile)) {
        $iconsJsonUrl = $apiBaseUrl . '/icons.json';
        $iconsJsonData = @file_get_contents($iconsJsonUrl);
        if ($iconsJsonData !== false) {
            file_put_contents($localIconsFile, $iconsJsonData);
            $iconsData = json_decode($iconsJsonData, true);
            if ($iconsData) {
                foreach ($iconsData as $extension => $iconFile) {
                    $localIconPath = $iconsDir . '/' . $iconFile;
                    if (!file_exists($localIconPath)) {
                        $iconUrl = $apiBaseUrl . '/icons/' . $iconFile;
                        $iconData = @file_get_contents($iconUrl);
                        if ($iconData !== false) {
                            file_put_contents($localIconPath, $iconData);
                        }
                    }
                }
            }
        }
    }
    $stylesheetPath = $localStyleDir . '/8d9f7fa8de5d3bac302028ab474b30b4.css';
    if (!file_exists($stylesheetPath)) {
        $stylesheetUrl = $apiBaseUrl . '/style/8d9f7fa8de5d3bac302028ab474b30b4.css';
        $stylesheetData = @file_get_contents($stylesheetUrl);
        if ($stylesheetData !== false) {
            file_put_contents($stylesheetPath, $stylesheetData);
            $fontFiles = [
                'cyrillic-ext-400.woff2', 'cyrillic-400.woff2', 'greek-400.woff2',
                'vietnamese-400.woff2', 'latin-ext-400.woff2', 'latin-400.woff2',
                'cyrillic-ext-500.woff2', 'cyrillic-500.woff2', 'greek-500.woff2',
                'vietnamese-500.woff2', 'latin-ext-500.woff2', 'latin-500.woff2',
                'cyrillic-ext-700.woff2', 'cyrillic-700.woff2', 'greek-700.woff2',
                'vietnamese-700.woff2', 'latin-ext-700.woff2', 'latin-700.woff2'
            ];
            foreach ($fontFiles as $fontFile) {
                $localFontPath = $localStyleDir . '/' . $fontFile;
                if (!file_exists($localFontPath)) {
                    $fontUrl = $apiBaseUrl . '/style/' . $fontFile;
                    $fontData = @file_get_contents($fontUrl);
                    if ($fontData !== false) {
                        file_put_contents($localFontPath, $fontData);
                    }
                }
            }
        }
    }
}
function loadConfiguration() {
    global $configFile, $apiBaseUrl, $cacheInstance, $disableApi;
    if (file_exists($configFile)) {
        $configData = json_decode(file_get_contents($configFile), true);
        if ($configData !== null) {
            if (isset($configData['main']['custom_exclusions']) && !isset($configData['main']['deny_list'])) {
                $configData['main']['deny_list'] = $configData['main']['custom_exclusions'];
                unset($configData['main']['custom_exclusions']);
                file_put_contents($configFile, json_encode($configData, JSON_PRETTY_PRINT));
            }
            return $configData;
        }
    }
    if (!$disableApi) {
        if ($cacheInstance !== null) {
            $cachedConfig = $cacheInstance->get('main_config', 'api');
            if ($cachedConfig !== null) {
                return $cachedConfig;
            }
        }
        $apiConfigUrl = $apiBaseUrl . '/api.php?action=config';
        $configResponse = @file_get_contents($apiConfigUrl);
        if ($configResponse !== false) {
            $apiData = json_decode($configResponse, true);
            if ($apiData !== null && isset($apiData['config'])) {
                file_put_contents($configFile, json_encode($apiData['config'], JSON_PRETTY_PRINT));
                if ($cacheInstance !== null) {
                    $cacheInstance->set('main_config', 'api', $apiData['config'], 3600);
                }
                return $apiData['config'];
            }
        }
        checkAndUpdateConfig();
    }
    return [];
}
function getExtensionSetting($extension, $type = 'indexing') {
    global $disableApi;
    if ($disableApi) {
        $extensionMappings = loadLocalExtensionMappings();
    } else {
        $extensionMappings = loadExtensionMappings();
    }
    if ($extensionMappings === null) {
        $commonTypes = ['txt', 'md', 'html', 'css', 'js', 'php', 'py', 'json', 'xml'];
        return in_array(strtolower($extension), $commonTypes);
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
    global $webPath, $iconsDir, $localIcons, $disableApi, $localStyleDir;
    if ($disableApi) {
        $iconInfo = getIconFromLocal($type, $extension);
        if ($iconInfo !== null) {
            $relativePath = str_replace($GLOBALS['scriptDir'], '', $iconInfo['path']);
            return $webPath . $relativePath;
        }
    } else {
        $iconInfo = getIconFromAPI($type, $extension);
        if ($iconInfo === null) {
            return null;
        }
        if ($localIcons) {
            $iconPath = $iconsDir . '/' . $iconInfo['filename'];
            if (file_exists($iconPath)) {
                $relativePath = str_replace($GLOBALS['scriptDir'], '', $iconPath);
                return $webPath . $relativePath;
            }
        }
        return $iconInfo['url'];
    }
    return null;
}
function getIconFromLocal($type, $extension = '') {
    global $iconsDir;
    $iconMappings = loadLocalIconMappings();
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
        'path' => $iconPath,
        'size' => filesize($iconPath),
        'last_modified' => filemtime($iconPath)
    ];
}
function getStylesheetUrl() {
    global $webPath, $disableApi, $localStyleDir, $apiBaseUrl, $scriptDir;
    if ($disableApi) {
        $relativePath = str_replace($scriptDir, '', $localStyleDir . '/8d9f7fa8de5d3bac302028ab474b30b4.css');
        return $webPath . $relativePath;
    } else {
        return $apiBaseUrl . '/style/8d9f7fa8de5d3bac302028ab474b30b4.css';
    }
}
function shouldIndexFile($filename, $extension) {
    global $config, $currentPath;
    $relativePath = $currentPath ? $currentPath . '/' . basename($filename) : basename($filename);
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
function isFileViewable($extension) {
    global $config;
    $settingKey = getExtensionSetting($extension, 'viewing');
    if ($settingKey !== null) {
        return isset($config['viewable_files'][$settingKey]) ? $config['viewable_files'][$settingKey] : false;
    }
    return false;
}
function initializeDirectories() {
    global $indexerFilesDir, $zipCacheDir, $indexCacheDir, $iconsDir, $localIcons, $localApiDir, $disableApi;
    if (!is_dir($indexerFilesDir)) {
        mkdir($indexerFilesDir, 0755, true);
    }
    $dirs = [$zipCacheDir, $indexCacheDir];
    if ($localIcons || $disableApi) {
        $dirs[] = $iconsDir;
    }
    if ($disableApi) {
        $dirs[] = $localApiDir;
    }
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
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
class IndexerCache {
    private $cacheType;
    private $indexCacheDir;
    private $pdo = null;
    public function __construct($cacheType, $indexCacheDir) {
        $this->cacheType = $cacheType;
        $this->indexCacheDir = $indexCacheDir;
        if ($this->cacheType === 'sqlite') {
            $this->initializeSQLite();
        }
    }
    private function initializeSQLite() {
        $dbPath = $this->indexCacheDir . '/cache.sqlite';
        try {
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS unified_cache (
                cache_key TEXT PRIMARY KEY,
                cache_type TEXT NOT NULL,
                data TEXT NOT NULL,
                last_modified INTEGER NOT NULL,
                expires_at INTEGER DEFAULT NULL
            )");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_cache_type ON unified_cache(cache_type)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_expires_at ON unified_cache(expires_at)");
        } catch (Exception $e) {
            $this->cacheType = 'json';
        }
    }
    public function get($key, $type = 'directory') {
        if ($this->cacheType === 'sqlite') {
            return $this->getSQLiteCache($key, $type);
        } else {
            return $this->getJSONCache($key, $type);
        }
    }
    public function set($key, $type, $data, $ttl = null) {
        if ($this->cacheType === 'sqlite') {
            $this->setSQLiteCache($key, $type, $data, $ttl);
        } else {
            $this->setJSONCache($key, $type, $data, $ttl);
        }
    }
    public function has($key, $type = 'directory') {
        $data = $this->get($key, $type);
        return $data !== null;
    }
    public function cleanup() {
        if ($this->cacheType === 'sqlite') {
            $this->cleanupSQLite();
        } else {
            $this->cleanupJSON();
        }
    }
    public function clearType($type) {
        if ($this->cacheType === 'sqlite') {
            try {
                $stmt = $this->pdo->prepare("DELETE FROM unified_cache WHERE cache_type = ?");
                $stmt->execute([$type]);
            } catch (Exception $e) {
            }
        } else {
            if (file_exists($cacheFile)) {
                $cacheData = json_decode(file_get_contents($cacheFile), true) ?: [];
                foreach ($cacheData as $cacheKey => $item) {
                    if ($item['cache_type'] === $type) {
                        unset($cacheData[$cacheKey]);
                    }
                }
                file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
            }
        }
    }
    private function getSQLiteCache($key, $type) {
        if (!$this->pdo) return null;
        try {
            $stmt = $this->pdo->prepare("SELECT data, last_modified, expires_at FROM unified_cache WHERE cache_key = ? AND cache_type = ?");
            $stmt->execute([$key, $type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                if ($result['expires_at'] && time() > $result['expires_at']) {
                    $this->pdo->prepare("DELETE FROM unified_cache WHERE cache_key = ? AND cache_type = ?")->execute([$key, $type]);
                    return null;
                }
                if ($type === 'directory') {
                    $currentModified = $this->getPathLastModified($key);
                    if ($result['last_modified'] < $currentModified) {
                        return null;
                    }
                }
                return json_decode($result['data'], true);
            }
        } catch (Exception $e) {
        }
        return null;
    }
    private function setSQLiteCache($key, $type, $data, $ttl = null) {
        if (!$this->pdo) return;
        try {
            $expiresAt = $ttl ? time() + $ttl : null;
            $lastModified = ($type === 'directory') ? $this->getPathLastModified($key) : time();
            $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO unified_cache (cache_key, cache_type, data, last_modified, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$key, $type, json_encode($data), $lastModified, $expiresAt]);
        } catch (Exception $e) {
        }
    }
    private function getJSONCache($key, $type) {
        $cacheFile = $this->indexCacheDir . '/cache.json';
        if (!file_exists($cacheFile)) {
            return null;
        }
        $cacheData = json_decode(file_get_contents($cacheFile), true) ?: [];
        $cacheKey = $type . ':' . $key;
        if (isset($cacheData[$cacheKey])) {
            $item = $cacheData[$cacheKey];
            if (isset($item['expires_at']) && $item['expires_at'] && time() > $item['expires_at']) {
                unset($cacheData[$cacheKey]);
                file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
                return null;
            }
            if ($type === 'directory') {
                $currentModified = $this->getPathLastModified($key);
                if ($item['last_modified'] < $currentModified) {
                    return null;
                }
            }
            return $item['data'];
        }
        return null;
    }
    private function setJSONCache($key, $type, $data, $ttl = null) {
        $cacheFile = $this->indexCacheDir . '/cache.json';
        $cacheData = [];
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        $cacheKey = $type . ':' . $key;
        $expiresAt = $ttl ? time() + $ttl : null;
        $lastModified = ($type === 'directory') ? $this->getPathLastModified($key) : time();
        $cacheData[$cacheKey] = [
            'cache_type' => $type,
            'data' => $data,
            'last_modified' => $lastModified,
            'expires_at' => $expiresAt
        ];
        file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
    }
    private function cleanupSQLite() {
        if (!$this->pdo) return;
        try {
            $this->pdo->prepare("DELETE FROM unified_cache WHERE expires_at IS NOT NULL AND expires_at < ?")->execute([time()]);
        } catch (Exception $e) {
        }
    }
    private function cleanupJSON() {
        $cacheFile = $this->indexCacheDir . '/cache.json';
        if (!file_exists($cacheFile)) {
            return;
        }
        $cacheData = json_decode(file_get_contents($cacheFile), true) ?: [];
        $modified = false;
        foreach ($cacheData as $cacheKey => $item) {
            if (isset($item['expires_at']) && $item['expires_at'] && time() > $item['expires_at']) {
                unset($cacheData[$cacheKey]);
                $modified = true;
            }
        }
        if ($modified) {
            file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
        }
    }
    private function getPathLastModified($path) {
        global $baseDir, $configFile;
        $fullPath = $baseDir . '/' . $path;
        if (!is_dir($fullPath)) return 0;
        $lastModified = filemtime($fullPath);
        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $itemPath = $fullPath . '/' . $item;
            $itemModified = filemtime($itemPath);
            if ($itemModified > $lastModified) {
                $lastModified = $itemModified;
            }
        }
        if (file_exists($configFile)) {
            $configModified = filemtime($configFile);
            if ($configModified > $lastModified) {
                $lastModified = $configModified;
            }
        }
        return $lastModified;
    }
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
function loadExtensionMappings() {
    global $apiBaseUrl, $disableApi;
    if ($disableApi) {
        return loadLocalExtensionMappings();
    }
    $cache = initializeCache();
    $cachedData = $cache->get('extension_mappings', 'api');
    if ($cachedData !== null) {
        return $cachedData;
    }
    $apiUrl = $apiBaseUrl . '/api.php?action=extensionMappings&type=all';
    $response = @file_get_contents($apiUrl);
    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data !== null && isset($data['success']) && $data['success'] && isset($data['mappings'])) {
            $cache->set('extension_mappings', 'api', $data['mappings'], 86400);
            return $data['mappings'];
        }
    }
    return null;
}
function loadIconCache() {
    $cache = initializeCache();
    return $cache->get('all_icons', 'icon') ?: [];
}
function saveIconCache($iconCacheData) {
    $cache = initializeCache();
    $cache->set('all_icons', 'icon', $iconCacheData);
}
function getIconFromAPI($type, $extension = '') {
    global $apiBaseUrl;
    $cache = initializeCache();
    $cacheKey = $type === 'folder' ? 'folder' : $extension;
    $cachedIcon = $cache->get($cacheKey, 'icon');
    if ($cachedIcon !== null) {
        return $cachedIcon;
    }
    $apiUrl = $apiBaseUrl . '/api.php?action=findIcon&type=' . urlencode($type);
    if ($extension) {
        $apiUrl .= '&extension=' . urlencode($extension);
    }
    $response = @file_get_contents($apiUrl);
    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data !== null && isset($data['success']) && $data['success'] && isset($data['icon'])) {
            $cache->set($cacheKey, 'icon', $data['icon'], 86400);
            return $data['icon'];
        }
    }
    return null;
}
function clearCache($type = null) {
    $cache = initializeCache();
    if ($type === null) {
        $cache->clearType('directory');
        $cache->clearType('api');
        $cache->clearType('icon');
    } else {
        $cache->clearType($type);
    }
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
if (isset($_GET['download']) && isset($_GET['file'])) {
    cleanupOldTempFiles();
    $downloadFile = $_GET['file'];
    $downloadPath = $fullPath . '/' . $downloadFile;
    $normalizedDownloadPath = str_replace('//', '/', $downloadPath);
    $normalizedBasePath = str_replace('//', '/', $baseDir);
    if (strpos($normalizedDownloadPath, $normalizedBasePath) !== 0) {
        http_response_code(403);
        die('Access denied - path traversal detected');
    }
    if (strpos($downloadFile, '../') !== false || strpos($downloadFile, './') !== false) {
        http_response_code(403);
        die('Access denied - invalid filename');
    }
    if (is_file($downloadPath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($downloadFile) . '"');
        header('Content-Length: ' . filesize($downloadPath));
        readfile($downloadPath);
        exit;
    } elseif (is_dir($downloadPath)) {
        $tempHash = bin2hex(random_bytes(16));
        $tempDir = $zipCacheDir . '/' . $tempHash;
        if (copyDirectoryExcludePhp($downloadPath, $tempDir)) {
            $zipName = basename($downloadFile) . '.zip';
            $zipPath = $zipCacheDir . '/' . $tempHash . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                addDirectoryToZip($zip, $tempDir, basename($downloadFile));
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipName . '"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                deleteDirectory($tempDir);
                unlink($zipPath);
                exit;
            } else {
                deleteDirectory($tempDir);
                http_response_code(500);
                die('Failed to create zip file');
            }
        } else {
            http_response_code(500);
            die('Failed to prepare directory for download');
        }
    }
    http_response_code(404);
    die('File not found');
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
if (!is_dir($fullPath)) {
    http_response_code(404);
    die('Directory not found');
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
function formatBytes($size) {
    if ($size === null) return '-';
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, 1) . ' ' . $units[$i];
}
function getFileUrl($path, $filename) {
    global $webPath;
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (isFileViewable($extension)) {
        return $currentScript . '?view=raw&file=' . urlencode($filename) . '&path=' . urlencode($path);
    } else {
        return $currentScript . '?' . http_build_query(['path' => $path, 'download' => '1', 'file' => $filename]);
    }
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
function getSortUrl($sortBy, $currentSort, $currentDir, $currentPath) {
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $newDir = ($sortBy === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
    $params = [
        'sort' => $sortBy,
        'dir' => $newDir
    ];
    if ($currentPath) {
        $params['path'] = $currentPath;
    }
    return $currentScript . '?' . http_build_query($params);
}
function getSortIndicator($column, $currentSort, $currentDir) {
    if ($column !== $currentSort) {
        return '';
    }
    return $currentDir === 'asc' ? ' ↑' : ' ↓';
}
if (isset($_GET['view']) && $_GET['view'] === 'raw' && isset($_GET['file'])) {
    $viewFile = $_GET['file'];
    $viewPath = $fullPath . '/' . $viewFile;
    $normalizedViewPath = str_replace('//', '/', $viewPath);
    $normalizedBasePath = str_replace('//', '/', $baseDir);
    if (strpos($normalizedViewPath, $normalizedBasePath) !== 0) {
        http_response_code(403);
        die('Access denied - path traversal detected');
    }
    if (strpos($viewFile, '../') !== false || strpos($viewFile, './') !== false) {
        http_response_code(403);
        die('Access denied - invalid filename');
    }
    if (is_file($viewPath)) {
        $extension = strtolower(pathinfo($viewFile, PATHINFO_EXTENSION));
        if (isFileViewable($extension)) {
            switch ($extension) {
                case 'pdf':
                    header('Content-Type: application/pdf');
                    break;
                case 'png':
                    header('Content-Type: image/png');
                    break;
                case 'jpg':
                case 'jpeg':
                    header('Content-Type: image/jpeg');
                    break;
                case 'gif':
                    header('Content-Type: image/gif');
                    break;
                case 'webp':
                    header('Content-Type: image/webp');
                    break;
                case 'jfif':
                    header('Content-Type: image/jpeg');
                    break;
                case 'avif':
                    header('Content-Type: image/avif');
                    break;
                case 'ico':
                    header('Content-Type: image/vnd.microsoft.icon');
                    break;
                case 'cur':
                    header('Content-Type: image/vnd.microsoft.icon');
                    break;
                case 'tiff':
                    header('Content-Type: image/tiff');
                    break;
                case 'bmp':
                    header('Content-Type: image/bmp');
                    break;
                case 'heic':
                    header('Content-Type: image/heic');
                    break;
                case 'svg':
                    header('Content-Type: image/svg+xml');
                    break;
                case 'mp4':
                    header('Content-Type: video/mp4');
                    break;
                case 'mkv':
                    header('Content-Type: video/webm');
                    break;
                case 'mp3':
                    header('Content-Type: audio/mpeg');
                    break;
                case 'aac':
                    header('Content-Type: audio/aac');
                    break;
                case 'flac':
                    header('Content-Type: audio/flac');
                    break;
                case 'm4a':
                    header('Content-Type: audio/mp4');
                    break;
                case 'ogg':
                    header('Content-Type: audio/ogg');
                    break;
                case 'opus':
                    header('Content-Type: audio/ogg');
                    break;
                case 'wma':
                    header('Content-Type: audio/x-ms-wma');
                    break;
                case 'mov':
                    header('Content-Type: video/quicktime');
                    break;
                case 'webm':
                    header('Content-Type: video/webm');
                    break;
                case 'wmv':
                    header('Content-Type: video/x-ms-wmv');
                    break;
                case '3gp':
                    header('Content-Type: video/3gpp');
                    break;
                case 'flv':
                    header('Content-Type: video/x-flv');
                    break;
                case 'm4v':
                    header('Content-Type: video/mp4');
                    break;
                case 'docx':
                    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                    header('Content-Transfer-Encoding: binary');
                    header('Accept-Ranges: bytes');
                    break;
                case 'xlsx':
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Transfer-Encoding: binary');
                    header('Accept-Ranges: bytes');
                    break;
                default:
                    header('Content-Type: text/plain; charset=utf-8');
                    break;
            }
            header('Content-Disposition: inline; filename="' . basename($viewFile) . '"');
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($viewFile) . '"');
        }
        header('Content-Length: ' . filesize($viewPath));
        readfile($viewPath);
        exit;
    }
    http_response_code(404);
    die('File not found');
}
function getBreadcrumbs($currentPath) {
    global $webPath;
    $currentScript = $_SERVER['SCRIPT_NAME'];
    $parts = array_filter(explode('/', $currentPath));
    $breadcrumbs = '<a href="' . $currentScript . '">content</a>';
    $path = '';
    foreach ($parts as $part) {
        $path .= '/' . $part;
        $breadcrumbs .= ' / <a href="' . $currentScript . '?path=' . urlencode(ltrim($path, '/')) . '">' . htmlspecialchars($part) . '</a>';
    }
    return $breadcrumbs;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Index of <?php echo htmlspecialchars($webCurrentPath); ?></title>
    <link rel="stylesheet" href="<?php echo getStylesheetUrl(); ?>">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Index of <?php echo htmlspecialchars($webCurrentPath); ?></h1>
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
                                    echo $_SERVER['SCRIPT_NAME'] . ($closeParams ? '?' . http_build_query($closeParams) : '');
                                ?>" class="options-toggle">×</a>
                                <div class="options-dropdown">
                                    <?php
                                    $baseParams = $_GET;
                                    unset($baseParams['options']);
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
                                        echo '<a href="' . $_SERVER['SCRIPT_NAME'] . '?' . http_build_query($params) . '" class="' . ($isActive ? 'active' : '') . '">' . $option['label'] . '</a>';
                                    }
                                    ?>
                                </div>
                            <?php else: ?>
                                <?php
                                $optionsParams = array_merge($_GET, ['options' => '1']);
                                ?>
                                <a href="<?php echo $_SERVER['SCRIPT_NAME'] . '?' . http_build_query($optionsParams); ?>" class="options-toggle">⋯</a>
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
                        if ($folderIconPath): ?>
                            <img src="<?php echo htmlspecialchars($folderIconPath); ?>" alt="Folder">
                        <?php else: ?>
                            <div class="file-icon-emoji">📁</div>
                        <?php endif; ?>
                    </div>
                    <div class="file-name">
                        <a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>?path=<?php echo urlencode(dirname($currentPath) === '.' ? '' : dirname($currentPath)); ?>">
                            ../
                        </a>
                    </div>
                    <div class="file-size">-</div>
                    <div class="file-date">-</div>
                    <div style="width: 65px;"></div>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach ($directories as $dir): ?>
            <div class="directory">
                <div class="file-item">
                    <div class="file-icon">
                        <?php 
                        $folderIconPath = getIconPath('folder');
                        if ($folderIconPath): ?>
                            <img src="<?php echo htmlspecialchars($folderIconPath); ?>" alt="Folder">
                        <?php else: ?>
                            <div class="file-icon-emoji">📁</div>
                        <?php endif; ?>
                    </div>
                    <div class="file-name">
                        <a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>?path=<?php echo urlencode(($currentPath ? $currentPath . '/' : '') . $dir['name']); ?>">
                            <?php echo htmlspecialchars($dir['name']); ?>/
                        </a>
                    </div>
                    <div class="file-size"><?php echo formatBytes($dir['size']); ?></div>
                    <div class="file-date"><?php echo date('Y-m-d H:i', $dir['modified']); ?></div>
                    <?php if (!$disableFolderDownloads): ?>
                    <a href="?<?php echo http_build_query(['path' => $currentPath, 'download' => '1', 'file' => $dir['name']]); ?>" class="download-btn">
                        ZIP
                    </a>
                    <?php else: ?>
                    <div style="width: 65px;"></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php foreach ($files as $file): ?>
            <div class="file">
                <div class="file-item">
                    <div class="file-icon">
                        <?php 
                        $fileIconPath = getIconPath('file', $file['extension']);
                        if ($fileIconPath): ?>
                            <img src="<?php echo htmlspecialchars($fileIconPath); ?>" alt="File">
                        <?php else: ?>
                            <div class="file-icon-emoji">📄</div>
                        <?php endif; ?>
                    </div>
                    <div class="file-name">
                        <a href="<?php echo getFileUrl($currentPath, $file['name']); ?>" <?php echo isFileViewable($file['extension']) ? 'target="_blank"' : ''; ?>>
                            <?php echo htmlspecialchars($file['name']); ?>
                        </a>
                    </div>
                    <div class="file-size"><?php echo formatBytes($file['size']); ?></div>
                    <div class="file-date"><?php echo date('Y-m-d H:i', $file['modified']); ?></div>
                    <?php if (!$disableFileDownloads): ?>
                    <a href="?<?php echo http_build_query(['path' => $currentPath, 'download' => '1', 'file' => $file['name']]); ?>" class="download-btn">
                        DL
                    </a>
                    <?php else: ?>
                    <div style="width: 65px;"></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>