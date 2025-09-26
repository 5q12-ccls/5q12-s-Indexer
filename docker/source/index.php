<?php
class URLRouter {
    private $scriptDir;
    private $webPath;
    private $baseDir;
    public function __construct($scriptDir, $webPath, $baseDir) {
        $this->scriptDir = $scriptDir;
        $this->webPath = $webPath;
        $this->baseDir = $baseDir;
    }
    public function parseCleanURL() {
        $requestUri = $_SERVER['REQUEST_URI'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $scriptPath = dirname($scriptName);
        if ($scriptPath !== '/' && !empty($scriptPath)) {
            $requestUri = str_replace($scriptPath, '', $requestUri);
        }
        $requestUri = ltrim($requestUri, '/');
        $parts = explode('?', $requestUri, 2);
        $cleanPath = $parts[0];
        if (isset($parts[1])) {
            parse_str($parts[1], $queryParams);
            $_GET = array_merge($_GET, $queryParams);
        }
        if (!empty($cleanPath) && substr($cleanPath, -1) === '/') {
            $cleanPath = rtrim($cleanPath, '/');
        }
        $decodedPath = $this->decodePathFromURL($cleanPath);
        if ($decodedPath === false) {
            http_response_code(400);
            exit('Invalid path');
        }
        return $decodedPath;
    }
    public function generateFileURL($path, $filename, $action = 'view', $viewType = 'default') {
        $encodedPath = $this->encodePathForURL($path);
        $encodedFilename = rawurlencode($filename);
        $fullPath = $encodedPath ? $encodedPath . '/' . $encodedFilename : $encodedFilename;
        switch ($action) {
            case 'download':
                return $this->webPath . '/' . $fullPath . '/?download=file';
            case 'view':
            default:
                return $this->webPath . '/' . $fullPath . '/?view=' . $viewType;
        }
    }
    public function generateFolderURL($path, $foldername = '', $action = 'view') {
        $encodedPath = $this->encodePathForURL($path);
        $encodedFoldername = $foldername ? rawurlencode($foldername) : '';
        if ($action === 'download') {
            $fullPath = $encodedPath ? $encodedPath . '/' . $encodedFoldername : $encodedFoldername;
            return $this->webPath . '/' . $fullPath . '/?download=archive';
        } else {
            $fullPath = $encodedPath;
            if ($encodedFoldername) {
                $fullPath = $fullPath ? $fullPath . '/' . $encodedFoldername : $encodedFoldername;
            }
            $url = $this->webPath . '/' . $fullPath . '/';
            $url = preg_replace('#/+#', '/', $url);
            $url = str_replace(':/', '://', $url);
            return $url;
        }
    }
    public function generateSortURL($sortBy, $currentSort, $currentDir, $currentPath) {
        $newDir = ($sortBy === $currentSort && $currentDir === 'asc') ? 'desc' : 'asc';
        $params = http_build_query([
            'sort' => $sortBy,
            'dir' => $newDir
        ]);
        if (empty($currentPath)) {
            $baseUrl = $this->webPath . '/';
        } else {
            $encodedPath = $this->encodePathForURL($currentPath);
            $baseUrl = $this->webPath . '/' . $encodedPath . '/';
        }
        return $baseUrl . '?' . $params;
    }
    public function generateBreadcrumbs($currentPath) {
        $parts = array_filter(explode('/', $currentPath));
        $breadcrumbs = '<a href="' . $this->webPath . '/">files</a>';
        $path = '';
        foreach ($parts as $part) {
            $path .= '/' . $part;
            $encodedPath = $this->encodePathForURL($path);
            $breadcrumbs .= ' / <a href="' . $this->webPath . '/' . $encodedPath . '/">' . htmlspecialchars($part) . '</a>';
        }
        return $breadcrumbs;
    }
    public function isFileRequest($currentPath) {
        $fullPath = $this->baseDir . '/' . $currentPath;
        return is_file($fullPath);
    }
    public function isFolderRequest($currentPath) {
        $fullPath = $this->baseDir . '/' . $currentPath;
        return is_dir($fullPath);
    }
    private function encodePathForURL($path) {
        if (empty($path)) return '';
        $segments = explode('/', $path);
        $encodedSegments = array_map('rawurlencode', $segments);
        return implode('/', $encodedSegments);
    }
    private function decodePathFromURL($encodedPath) {
        if (empty($encodedPath)) return '';
        $decodedPath = rawurldecode($encodedPath);
        if (strpos($decodedPath, '../') !== false || strpos($decodedPath, '..\\') !== false) {
            return false;
        }
        $decodedPath = str_replace("\0", '', $decodedPath);
        return $decodedPath;
    }
    public function handleFileRequest($currentPath) {
        $fullPath = $this->baseDir . '/' . $currentPath;
        if (strpos(realpath($fullPath), realpath($this->baseDir)) !== 0) {
            http_response_code(403);
            exit('Access denied');
        }
        if (!is_file($fullPath)) {
            http_response_code(404);
            exit('File not found');
        }
        $filename = basename($currentPath);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $parentPath = dirname($currentPath);
        if ($parentPath === '.') $parentPath = '';
        if (!isFileAccessible($fullPath, $parentPath, $extension)) {
            http_response_code(403);
            exit('File not accessible');
        }
        if (isset($_GET['download'])) {
            $this->handleFileDownload($fullPath, $filename);
        }
        $viewType = isset($_GET['view']) ? $_GET['view'] : 'raw';
        switch ($viewType) {
            case 'default':
                $this->handleFileView($fullPath, $filename, $extension, 'default');
                break;
            case 'code':
                $this->handleFileView($fullPath, $filename, $extension, 'code');
                break;
            case 'markdown':
                if (in_array($extension, ['md', 'markdown'])) {
                    $this->handleFileView($fullPath, $filename, $extension, 'markdown');
                } else {
                    $newUrl = $_SERVER['REQUEST_URI'];
                    $newUrl = preg_replace('/[?&]view=markdown/', '?view=default', $newUrl);
                    if (!strpos($newUrl, '?')) {
                        $newUrl .= '?view=default';
                    }
                    header('Location: ' . $newUrl);
                    exit;
                }
                break;
            case 'raw':
            default:
                $this->handleRawFileView($fullPath, $filename, $extension);
                break;
        }
    }
    private function handleRawFileView($fullPath, $filename, $extension) {
        $mimeTypes = [
            'txt' => 'text/plain',
            'md' => 'text/plain',
            'markdown' => 'text/plain',
            'js' => 'text/plain',
            'css' => 'text/plain',
            'html' => 'text/plain',
            'htm' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'text/xml',
            'php' => 'text/plain',
            'py' => 'text/plain',
            'sql' => 'text/plain',
            'log' => 'text/plain',
            'yml' => 'text/plain',
            'yaml' => 'text/plain',
            'conf' => 'text/plain',
            'config' => 'text/plain',
            'ini' => 'text/plain',
            'env' => 'text/plain',
            'sh' => 'text/plain',
            'bat' => 'text/plain',
            'ps1' => 'text/plain',
        ];
        $directServeExtensions = [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'jfif', 'avif', 'ico', 
            'cur', 'tiff', 'bmp', 'heic', 'svg', 'mp4', 'mkv', 'mp3', 'aac', 
            'flac', 'm4a', 'ogg', 'opus', 'wma', 'mov', 'webm', 'wmv', '3gp', 
            'flv', 'm4v', 'docx', 'xlsx'
        ];
        if (in_array($extension, $directServeExtensions)) {
            $this->serveFileDirect($fullPath, $extension, $filename);
        } else {
            $mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'text/plain';
            header('Content-Type: ' . $mimeType . '; charset=utf-8');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            readfile($fullPath);
            exit;
        }
    }
    public function handleFolderRequest($currentPath) {
        $fullPath = $this->baseDir . '/' . $currentPath;
        if (strpos(realpath($fullPath), realpath($this->baseDir)) !== 0) {
            http_response_code(403);
            exit('Access denied');
        }
        if (!is_dir($fullPath)) {
            http_response_code(404);
            exit('Directory not found');
        }
        if (!isFolderAccessible($currentPath)) {
            http_response_code(403);
            exit('Folder not accessible');
        }
        if (isset($_GET['download']) && $_GET['download'] === 'archive') {
            $folderName = basename($currentPath);
            if (empty($folderName)) {
                $folderName = 'files';
            }
            $this->handleFolderDownload($fullPath, $folderName);
            exit;
        }
        return true;
    }
    private function handleFileDownload($fullPath, $filename) {
        global $disableFileDownloads;
        if ($disableFileDownloads) {
            http_response_code(403);
            exit('File downloads disabled');
        }
        $fileSize = filesize($fullPath);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-cache');
        header('Accept-Ranges: bytes');
        if (ob_get_level()) {
            ob_end_clean();
        }
        $handle = fopen($fullPath, 'rb');
        if ($handle === false) {
            http_response_code(500);
            exit('Cannot read file');
        }
        $chunkSize = 8192;
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            flush();
            if (connection_aborted()) {
                break;
            }
        }
        fclose($handle);
        exit;
    }
    private function handleFileView($fullPath, $filename, $extension, $viewMode = 'default') {
        $fileContent = file_get_contents($fullPath);
        $fileName = htmlspecialchars($filename);
        $directServeExtensions = [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'jfif', 'avif', 'ico', 
            'cur', 'tiff', 'bmp', 'heic', 'svg', 'mp4', 'mkv', 'mp3', 'aac', 
            'flac', 'm4a', 'ogg', 'opus', 'wma', 'mov', 'webm', 'wmv', '3gp', 
            'flv', 'm4v', 'docx', 'xlsx'
        ];
        if (in_array($extension, $directServeExtensions)) {
            $this->serveFileDirect($fullPath, $extension, $filename);
        } else {
            $this->serveFileAsText($fileContent, $filename, $extension, $viewMode);
        }
    }
    private function serveFileDirect($fullPath, $extension, $filename) {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'jfif' => 'image/jpeg',
            'avif' => 'image/avif',
            'ico' => 'image/vnd.microsoft.icon',
            'cur' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'bmp' => 'image/bmp',
            'heic' => 'image/heic',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mkv' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'm4a' => 'audio/mp4',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/ogg',
            'wma' => 'audio/x-ms-wma',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'wmv' => 'video/x-ms-wmv',
            '3gp' => 'video/3gpp',
            'flv' => 'video/x-flv',
            'm4v' => 'video/mp4',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
    }
    private function serveFileAsText($fileContent, $filename, $extension, $viewMode = 'default') {
        $markdownExtensions = ['md', 'markdown'];
        $isMarkdown = in_array($extension, $markdownExtensions);
        $showMarkdown = false;
        if ($viewMode === 'default' && $isMarkdown) {
            $showMarkdown = true;
        } elseif ($viewMode === 'markdown' && $isMarkdown) {
            $showMarkdown = true;
        }
        $showRaw = isset($_GET['raw']) && $_GET['raw'] === '1';
        if ($showRaw) {
            $showMarkdown = false;
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $filename; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/icon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/32x32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/48x48.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/96x96.png">
    <link rel="icon" type="image/png" sizes="144x144" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/144x144.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/192x192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $this->webPath; ?>/.indexer_files/favicon/180x180.png">
    <?php if (!$showMarkdown): ?>
    <link rel="stylesheet" href="<?php echo $this->webPath; ?>/.indexer_files/local_api/style/ecf219b0e59edefbdc0124308ade7358.css">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $this->webPath; ?>/.indexer_files/local_api/style/923fa1e76151b3d1186753fda67480ce.css">
</head>
<body>
    <?php if ($isMarkdown): ?>
        <?php if ($showRaw): ?>
            <a href="<?php echo $_SERVER['REQUEST_URI']; ?>" class="view-raw-button">View Markdown</a>
            <pre><?php echo htmlspecialchars($fileContent); ?></pre>
        <?php elseif ($showMarkdown): ?>
            <?php
            $currentUrl = $_SERVER['REQUEST_URI'];
            $baseUrlParts = parse_url($currentUrl);
            $baseUrl = $baseUrlParts['path'];
            $currentParams = [];
            if (isset($baseUrlParts['query'])) {
                parse_str($baseUrlParts['query'], $currentParams);
            }
            $viewButtons = '';
            $rawParams = array_merge($currentParams, ['view' => 'raw']);
            unset($rawParams['raw']);
            $viewButtons .= '<a href="' . $baseUrl . '?' . http_build_query($rawParams) . '" class="view-raw-button" target="_blank">View Raw</a>';
            if ($viewMode !== 'code') {
                $codeParams = array_merge($currentParams, ['view' => 'code']);
                unset($codeParams['raw']);
                $viewButtons .= '<a href="' . $baseUrl . '?' . http_build_query($codeParams) . '" class="view-raw-button" style="right: 120px;">View Code</a>';
            }
            echo $viewButtons;
            ?>
            <div class="markdown-content">
                <?php echo parseMarkdown($fileContent); ?>
            </div>
        <?php else: ?>
            <?php
            $currentUrl = $_SERVER['REQUEST_URI'];
            $baseUrlParts = parse_url($currentUrl);
            $baseUrl = $baseUrlParts['path'];
            $currentParams = [];
            if (isset($baseUrlParts['query'])) {
                parse_str($baseUrlParts['query'], $currentParams);
            }
            $viewButtons = '';
            $rawParams = array_merge($currentParams, ['view' => 'raw']);
            unset($rawParams['raw']);
            $viewButtons .= '<a href="' . $baseUrl . '?' . http_build_query($rawParams) . '" class="view-raw-button" target="_blank">View Raw</a>';
            $markdownParams = array_merge($currentParams, ['view' => 'default']);
            unset($markdownParams['raw']);
            $viewButtons .= '<a href="' . $baseUrl . '?' . http_build_query($markdownParams) . '" class="view-raw-button" style="right: 120px;">View Markdown</a>';
            echo $viewButtons;
            ?>
            <pre><?php echo htmlspecialchars($fileContent); ?></pre>
        <?php endif; ?>
    <?php else: ?>
        <?php
        $currentUrl = $_SERVER['REQUEST_URI'];
        $baseUrlParts = parse_url($currentUrl);
        $baseUrl = $baseUrlParts['path'];
        $currentParams = [];
        if (isset($baseUrlParts['query'])) {
            parse_str($baseUrlParts['query'], $currentParams);
        }
        $rawParams = array_merge($currentParams, ['view' => 'raw']);
        unset($rawParams['raw']);
        echo '<a href="' . $baseUrl . '?' . http_build_query($rawParams) . '" class="view-raw-button" target="_blank">View Raw</a>';
        ?>
        <pre><?php echo htmlspecialchars($fileContent); ?></pre>
    <?php endif; ?>
</body>
</html>
        <?php
        exit;
    }
    private function handleFolderDownload($fullPath, $folderName) {
        global $disableFolderDownloads, $zipCacheDir;
        if ($disableFolderDownloads) {
            http_response_code(403);
            exit('Folder downloads disabled');
        }
        if (!is_dir($zipCacheDir)) {
            mkdir($zipCacheDir, 0755, true);
        }
        cleanupOldTempFiles();
        $tempHash = bin2hex(random_bytes(16));
        $tempDir = $zipCacheDir . '/' . $tempHash;
        if (copyDirectoryExcludePhp($fullPath, $tempDir)) {
            $zipName = $folderName . '.zip';
            $zipPath = $zipCacheDir . '/' . $tempHash . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                addDirectoryToZip($zip, $tempDir, $folderName);
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zipName . '"');
                header('Content-Length: ' . filesize($zipPath));
                if (ob_get_level()) {
                    ob_end_clean();
                }
                readfile($zipPath);
                deleteDirectory($tempDir);
                unlink($zipPath);
                exit;
            } else {
                deleteDirectory($tempDir);
                http_response_code(500);
                exit('Cannot create ZIP file');
            }
        } else {
            http_response_code(500);
            exit('Cannot copy directory');
        }
    }
}
$scriptDir = dirname(__FILE__);
$baseDir = $scriptDir . '/files';
$isDockerEnvironment = getenv('DOCKER_ENV') === 'true' || file_exists('/.dockerenv');
$scriptDir = dirname(__FILE__);
if ($isDockerEnvironment) {
    $baseDir = '/files';
    $indexerFilesDir = '/config';
} else {
    $baseDir = $scriptDir . '/files';
    $indexerFilesDir = $scriptDir . '/.indexer_files';
}
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
        $iconFilename = ($type === 'folder') ? 'folder.png' : 'non-descript-default-file.png';
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
        $iconFile = isset($iconMappings['folder']) ? $iconMappings['folder'] : 'folder.png';
    } else {
        $extension = strtolower($extension);
        $iconFile = isset($iconMappings[$extension]) ? $iconMappings[$extension] : 'non-descript-default-file.png';
    }
    $iconPath = $iconsDir . '/' . $iconFile;
    if (!file_exists($iconPath)) {
        if ($type === 'folder') {
            $iconFile = 'folder.png';
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
        if (!is_dir($this->indexCacheDir)) {
            mkdir($this->indexCacheDir, 0755, true);
        }
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
    public function cleanup() {
        if ($this->cacheType === 'sqlite') {
            $this->cleanupSQLite();
        } else {
            $this->cleanupJSON();
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
                    $actualPath = preg_replace('/_sort_.*$/', '', $key);
                    $currentModified = $this->getPathLastModified($actualPath);
                    if ($result['last_modified'] < $currentModified) {
                        $this->pdo->prepare("DELETE FROM unified_cache WHERE cache_key = ? AND cache_type = ?")->execute([$key, $type]);
                        return null;
                    }
                }
                return json_decode($result['data'], true);
            }
        } catch (Exception $e) {
            error_log("Cache error: " . $e->getMessage());
        }
        return null;
    }
    private function setSQLiteCache($key, $type, $data, $ttl = null) {
        if (!$this->pdo) return;
        try {
            $expiresAt = $ttl ? time() + $ttl : null;
            if ($type === 'directory') {
                $actualPath = preg_replace('/_sort_.*$/', '', $key);
                $lastModified = $this->getPathLastModified($actualPath);
            } else {
                $lastModified = time();
            }
            $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO unified_cache (cache_key, cache_type, data, last_modified, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$key, $type, json_encode($data), $lastModified, $expiresAt]);
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
        }
    }
    private function getJSONCache($key, $type) {
        if (!is_dir($this->indexCacheDir)) {
            mkdir($this->indexCacheDir, 0755, true);
        }
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
                $actualPath = preg_replace('/_sort_.*$/', '', $key);
                $currentModified = $this->getPathLastModified($actualPath);
                if ($item['last_modified'] < $currentModified) {
                    unset($cacheData[$cacheKey]);
                    file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
                    return null;
                }
            }
            return $item['data'];
        }
        return null;
    }
    private function setJSONCache($key, $type, $data, $ttl = null) {
        if (!is_dir($this->indexCacheDir)) {
            mkdir($this->indexCacheDir, 0755, true);
        }
        $cacheFile = $this->indexCacheDir . '/cache.json';
        $cacheData = [];
        if (file_exists($cacheFile)) {
            $cacheData = json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        $cacheKey = $type . ':' . $key;
        $expiresAt = $ttl ? time() + $ttl : null;
        if ($type === 'directory') {
            $actualPath = preg_replace('/_sort_.*$/', '', $key);
            $lastModified = $this->getPathLastModified($actualPath);
        } else {
            $lastModified = time();
        }
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
        $fullPath = $baseDir . '/' . ltrim($path, '/');
        if (!is_dir($fullPath)) return 0;
        $lastModified = filemtime($fullPath);
        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $itemPath = $fullPath . '/' . $item;
            if (is_readable($itemPath)) {
                $itemModified = filemtime($itemPath);
                if ($itemModified > $lastModified) {
                    $lastModified = $itemModified;
                }
                if (is_dir($itemPath)) {
                    $subItems = @scandir($itemPath);
                    if ($subItems) {
                        foreach ($subItems as $subItem) {
                            if ($subItem == '.' || $subItem == '..') continue;
                            $subItemPath = $itemPath . '/' . $subItem;
                            if (is_readable($subItemPath)) {
                                $subItemModified = filemtime($subItemPath);
                                if ($subItemModified > $lastModified) {
                                    $lastModified = $subItemModified;
                                }
                            }
                        }
                    }
                }
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
function getFileActionMenu($file, $currentPath) {
    global $disableFileDownloads, $router, $webPath;
    $extension = $file['extension'];
    $fileName = $file['name'];
    $isViewable = isFileViewable($extension);
    $showActions = isset($_GET['action']) && $_GET['action'] === $fileName;
    $menu = '<div class="item-actions-menu">';
    if ($showActions) {
        $closeParams = $_GET;
        unset($closeParams['action']);
        unset($closeParams['options']);
        if (empty($currentPath)) {
            $baseUrl = $webPath . '/';
        } else {
            $encodedPath = rawurlencode($currentPath);
            $baseUrl = $webPath . '/' . $encodedPath . '/';
        }
        $closeUrl = $baseUrl . ($closeParams ? '?' . http_build_query($closeParams) : '');
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
        if (empty($currentPath)) {
            $baseUrl = $webPath . '/';
        } else {
            $encodedPath = rawurlencode($currentPath);
            $baseUrl = $webPath . '/' . $encodedPath . '/';
        }
        $actionUrl = $baseUrl . '?' . http_build_query($actionParams);
        $menu .= '<a href="' . $actionUrl . '" class="actions-toggle">⋯</a>';
    }
    $menu .= '</div>';
    return $menu;
}
function getFolderActionMenu($folder, $currentPath) {
    global $disableFolderDownloads, $router, $webPath;
    $folderName = $folder['name'];
    $showActions = isset($_GET['action']) && $_GET['action'] === $folderName;
    $menu = '<div class="item-actions-menu">';
    if ($showActions) {
        $closeParams = $_GET;
        unset($closeParams['action']);
        unset($closeParams['options']);
        $baseUrl = getBaseUrl($currentPath, $webPath);
        $closeUrl = $baseUrl . ($closeParams ? '?' . http_build_query($closeParams) : '');
        $menu .= '<a href="' . $closeUrl . '" class="actions-toggle">×</a>';
        $menu .= '<div class="actions-dropdown">';
        $openUrl = $router->generateFolderURL($currentPath, $folderName, 'view');
        $menu .= '<a href="' . htmlspecialchars($openUrl) . '">Open</a>';
        $menu .= '<a href="' . htmlspecialchars($openUrl) . '" target="_blank">Open in new tab</a>';
        if (!$disableFolderDownloads) {
            $downloadUrl = $router->generateFolderURL($currentPath, $folderName, 'download');
            $menu .= '<a href="' . htmlspecialchars($downloadUrl) . '">Download</a>';
        }
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
        $baseUrl = getBaseUrl($currentPath, $webPath);
        $actionUrl = $baseUrl . '?' . http_build_query($actionParams);
        $menu .= '<a href="' . $actionUrl . '" class="actions-toggle">⋯</a>';
    }
    $menu .= '</div>';
    return $menu;
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
    $baseUrl = getBaseUrl($currentPath, $webPath);
    $closeUrl = $baseUrl . ($closeParams ? '?' . http_build_query($closeParams) : '');
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
function parseMarkdown($text) {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $codeBlocks = [];
    $inlineCodes = [];
    $text = preg_replace_callback('/```([a-zA-Z0-9\-_]*)\n?(.*?)\n?```/s', function($matches) use (&$codeBlocks) {
        $id = count($codeBlocks);
        $placeholder = "XCODEBLOCKREPLACEX" . $id . "XCODEBLOCKREPLACEX";
        $codeBlocks[$placeholder] = trim($matches[2]);
        return "\n" . $placeholder . "\n";
    }, $text);
    $text = preg_replace_callback('/`([^`\n]+?)`/', function($matches) use (&$inlineCodes) {
        $id = count($inlineCodes);
        $placeholder = "XINLINECODEREPLACEX" . $id . "XINLINECODEREPLACEX";
        $inlineCodes[$placeholder] = $matches[1];
        return $placeholder;
    }, $text);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/^###### (.+?)$/m', '<h6>$1</h6>', $text);
    $text = preg_replace('/^##### (.+?)$/m', '<h5>$1</h5>', $text);
    $text = preg_replace('/^#### (.+?)$/m', '<h4>$1</h4>', $text);
    $text = preg_replace('/^### (.+?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.+?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.+?)$/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/!\[([^\]]*?)\]\(([^)]+?)\)/', '<img src="$2" alt="$1" class="markdown-img">', $text);
    $text = preg_replace('/\[([^\]]+?)\]\(([^)]+?)\)/', '<a href="$2" class="markdown-link">$1</a>', $text);
    $text = preg_replace('/(?<!XINLINECODEREPLACEX)\*\*\*([^*\n]+?)\*\*\*(?!XINLINECODEREPLACEX)/', '<strong><em>$1</em></strong>', $text);
    $text = preg_replace('/(?<!XINLINECODEREPLACEX)\*\*([^*\n]+?)\*\*(?!XINLINECODEREPLACEX)/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!XINLINECODEREPLACEX)(?<!\*)\*([^*\n]+?)\*(?!\*)(?!XINLINECODEREPLACEX)/', '<em>$1</em>', $text);
    $text = preg_replace('/(?<!XINLINECODEREPLACEX)___([^_\n]+?)___(?!XINLINECODEREPLACEX)/', '<strong><em>$1</em></strong>', $text);
    $text = preg_replace('/(?<!XINLINECODEREPLACEX)(?<!_)__([^_\n]+?)__(?!_)(?!XINLINECODEREPLACEX)/', '<strong>$1</strong>', $text);
    $text = preg_replace('/(?<!XINLINECODEREPLACEX)(?<!_)_([^_\n]+?)_(?!_)(?!XINLINECODEREPLACEX)/', '<em>$1</em>', $text);
    $text = preg_replace('/~~([^~\n]+?)~~/', '<del>$1</del>', $text);
    $text = preg_replace('/^\s*---\s*$/m', '<hr class="markdown-hr">', $text);
    $text = preg_replace('/^\s*\*\*\*\s*$/m', '<hr class="markdown-hr">', $text);
    $text = preg_replace('/^&gt; (.+?)$/m', '<blockquote class="markdown-blockquote">$1</blockquote>', $text);
    $text = preg_replace('/^(\s*)[\*\-\+] (.+?)$/m', '$1<li class="markdown-li">$2</li>', $text);
    $text = preg_replace('/^(\s*)\d+\. (.+?)$/m', '$1<li class="markdown-li markdown-li-ordered">$2</li>', $text);
    $lines = explode("\n", $text);
    $result = [];
    $inList = false;
    $listType = '';
    $lastWasListItem = false;
    foreach ($lines as $line) {
        if (preg_match('/^(\s*)<li class="markdown-li( markdown-li-ordered)?"/', $line, $matches)) {
            $isOrdered = !empty($matches[2]);
            $newListType = $isOrdered ? 'ol' : 'ul';
            if (!$inList) {
                $result[] = "<$newListType class=\"markdown-list\">";
                $listType = $newListType;
                $inList = true;
            } elseif ($listType !== $newListType) {
                if (!($listType === 'ol' && $newListType === 'ol')) {
                    $result[] = "</$listType>";
                    $result[] = "<$newListType class=\"markdown-list\">";
                    $listType = $newListType;
                }
            }
            $result[] = $line;
            $lastWasListItem = true;
        } else {
            if ($inList && trim($line) === '' && $lastWasListItem) {
                $lastWasListItem = false;
                continue;
            }
            if ($inList && trim($line) !== '') {
                $result[] = "</$listType>";
                $inList = false;
            }
            if (trim($line) !== '') {
                $result[] = $line;
                $lastWasListItem = false;
            }
        }
    }
    if ($inList) {
        $result[] = "</$listType>";
    }
    $text = implode("\n", $result);
    $text = preg_replace_callback('/(?:^\|.+\|\s*$\n?)+/m', function($matches) {
        $table = trim($matches[0]);
        $rows = explode("\n", $table);
        $html = '<table class="markdown-table">';
        $isHeader = true;
        foreach ($rows as $row) {
            if (empty(trim($row))) continue;
            if (preg_match('/^\|[\s\-\|:]+\|$/', $row)) {
                $isHeader = false;
                continue;
            }
            $cells = explode('|', trim($row, '|'));
            $cells = array_map('trim', $cells);
            $tag = $isHeader ? 'th' : 'td';
            $class = $isHeader ? 'markdown-th' : 'markdown-td';
            $html .= '<tr class="markdown-tr">';
            foreach ($cells as $cell) {
                $html .= "<$tag class=\"$class\">$cell</$tag>";
            }
            $html .= '</tr>';
            if ($isHeader) $isHeader = false;
        }
        $html .= '</table>';
        return $html;
    }, $text);
    $paragraphs = preg_split('/\n\s*\n/', $text);
    $result = [];
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if (empty($paragraph)) continue;
        if (preg_match('/^XCODEBLOCKREPLACEX\d+XCODEBLOCKREPLACEX$/', $paragraph)) {
            $result[] = $paragraph;
        }
        elseif (preg_match('/^<(h[1-6]|ul|ol|blockquote|pre|hr|table|div)/i', $paragraph)) {
            $result[] = $paragraph;
        }
        else {
            $paragraph = preg_replace('/\n(?!<)/', '<br>', $paragraph);
            $result[] = '<p class="markdown-p">' . $paragraph . '</p>';
        }
    }
    $text = implode("\n\n", $result);
    foreach ($codeBlocks as $placeholder => $content) {
        $escapedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $codeHtml = '<pre class="code-block"><code>' . $escapedContent . '</code></pre>';
        $text = str_replace($placeholder, $codeHtml, $text);
    }
    foreach ($inlineCodes as $placeholder => $content) {
        $escapedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $codeHtml = '<code class="inline-code">' . $escapedContent . '</code>';
        $text = str_replace($placeholder, $codeHtml, $text);
    }
    return $text;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Index of <?php echo htmlspecialchars($webCurrentPath); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo $webPath; ?>/.indexer_files/favicon/icon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $webPath; ?>/.indexer_files/favicon/16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $webPath; ?>/.indexer_files/favicon/32x32.png">
    <link rel="icon" type="image/png" sizes="48x48" href="<?php echo $webPath; ?>/.indexer_files/favicon/48x48.png">
    <link rel="icon" type="image/png" sizes="96x96" href="<?php echo $webPath; ?>/.indexer_files/favicon/96x96.png">
    <link rel="icon" type="image/png" sizes="144x144" href="<?php echo $webPath; ?>/.indexer_files/favicon/144x144.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo $webPath; ?>/.indexer_files/favicon/192x192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $webPath; ?>/.indexer_files/favicon/180x180.png">
    <link rel="stylesheet" href="<?php echo $webPath; ?>/.indexer_files/local_api/style/ecf219b0e59edefbdc0124308ade7358.css">
</head>
<body<?php if ($iconType === 'disabled') echo ' class="icon-disabled"'; ?>>
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
                                    unset($closeParams['action']);
                                    if (empty($currentPath)) {
                                        $baseUrl = $webPath . '/';
                                    } else {
                                        $baseUrl = $webPath . '/' . $currentPath . '/';
                                    }
                                    echo $baseUrl . ($closeParams ? '?' . http_build_query($closeParams) : '');
                                ?>" class="options-toggle">×</a>
                                <div class="options-dropdown">
                                    <?php
                                    $baseParams = $_GET;
                                    unset($baseParams['options']);
                                    $baseUrl = getBaseUrl($currentPath, $webPath);
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
                                $baseUrl = getBaseUrl($currentPath, $webPath);
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
    ?>
</body>
</html>