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
        $breadcrumbs = '<a href="' . $this->webPath . '/">root</a>';
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
            $fileSize = filesize($fullPath);
            $maxSize = getMaxDownloadSize('file');
            if ($fileSize > $maxSize) {
                http_response_code(413);
                $fileSizeFormatted = formatSizeForError($fileSize);
                $maxSizeFormatted = formatSizeForError($maxSize);
                exit("File too large for download. File size: {$fileSizeFormatted}, Maximum allowed: {$maxSizeFormatted}");
            }
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
            $folderSize = getDirectorySize($fullPath);
            $maxSize = getMaxDownloadSize('folder');
            if ($folderSize > $maxSize) {
                http_response_code(413);
                $folderSizeFormatted = formatSizeForError($folderSize);
                $maxSizeFormatted = formatSizeForError($maxSize);
                exit("Folder too large for download. Folder size: {$folderSizeFormatted}, Maximum allowed: {$maxSizeFormatted}");
            }
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
        global $currentPath;
        $relativePath = $currentPath ? $currentPath . '/' . $filename : $filename;
        $fileModTime = filemtime($fullPath);
        $cacheKey = 'fileview_' . md5($relativePath . '_' . $viewMode . '_' . $fileModTime);
        $cache = initializeCache();
        $cachedOutput = $cache->get($cacheKey, 'fileview');
        if ($cachedOutput !== null) {
            echo $cachedOutput;
            exit;
        }
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
            ob_start();
            $this->serveFileAsText($fileContent, $filename, $extension, $viewMode);
            $output = ob_get_clean();
            $cache->set($cacheKey, 'fileview', $output, 3600);
            echo $output;
            exit;
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
        $showCode = false;
        if ($viewMode === 'default' && $isMarkdown) {
            $showMarkdown = true;
        } elseif ($viewMode === 'markdown' && $isMarkdown) {
            $showMarkdown = true;
        } else {
            $showCode = true;
        }
        $showRaw = isset($_GET['raw']) && $_GET['raw'] === '1';
        if ($showRaw) {
            $showMarkdown = false;
            $showCode = false;
        }
        $currentUrl = $_SERVER['REQUEST_URI'];
        $baseUrlParts = parse_url($currentUrl);
        $baseUrl = $baseUrlParts['path'];
        $currentParams = [];
        if (isset($baseUrlParts['query'])) {
            parse_str($baseUrlParts['query'], $currentParams);
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
            <link rel="stylesheet" href="<?php echo $this->webPath; ?>/.indexer_files/local_api/style/base-1.2.1.min.css">
            <?php if ($showMarkdown): ?>
            <link rel="stylesheet" href="<?php echo $this->webPath; ?>/.indexer_files/local_api/style/viewer-markdown-1.2.0.min.css">
            <?php else: ?>
            <link rel="stylesheet" href="<?php echo $this->webPath; ?>/.indexer_files/local_api/style/viewer-code-1.2.1.min.css">
            <link rel="stylesheet" href="<?php echo $this->webPath; ?>/.indexer_files/local_api/style/atom-one-dark.min.css">
            <?php endif; ?>
        </head>
        <body>
            <?php
            $securityStatus = getSecurityStatus();
            $lockIcon = $securityStatus['secure'] 
                ? $this->webPath . '/.indexer_files/icons/app/green.png'
                : $this->webPath . '/.indexer_files/icons/app/red.png';
            ?>
            <div class="security-bar">
                <span class="security-lock" data-tooltip="<?php echo $securityStatus['secure'] ? 'Connection is secure (HTTPS)' : 'Connection is not secure - Consider using HTTPS'; ?>">
                    <img src="<?php echo htmlspecialchars($lockIcon); ?>" alt="<?php echo $securityStatus['secure'] ? 'Secure' : 'Not Secure'; ?>">
                </span>
                <div class="security-bar-filename"><?php echo htmlspecialchars($filename); ?></div>
                <div class="security-bar-buttons">
                    <?php if ($isMarkdown): ?>
                        <?php
                        $rawParams = array_merge($currentParams, ['view' => 'raw']);
                        unset($rawParams['raw']);
                        $rawUrl = $baseUrl . '?' . http_build_query($rawParams);
                        $isRawActive = ($viewMode === 'raw');
                        ?>
                        <a href="<?php echo $rawUrl; ?>" class="security-bar-btn<?php echo $isRawActive ? ' active' : ''; ?>">Raw</a>
                        <?php
                        $codeParams = array_merge($currentParams, ['view' => 'code']);
                        unset($codeParams['raw']);
                        $codeUrl = $baseUrl . '?' . http_build_query($codeParams);
                        $isCodeActive = ($viewMode === 'code');
                        ?>
                        <a href="<?php echo $codeUrl; ?>" class="security-bar-btn<?php echo $isCodeActive ? ' active' : ''; ?>">Code</a>
                        <?php
                        $markdownParams = array_merge($currentParams, ['view' => 'default']);
                        unset($markdownParams['raw']);
                        $markdownUrl = $baseUrl . '?' . http_build_query($markdownParams);
                        $isMarkdownActive = ($viewMode === 'default' || $viewMode === 'markdown');
                        ?>
                        <a href="<?php echo $markdownUrl; ?>" class="security-bar-btn<?php echo $isMarkdownActive ? ' active' : ''; ?>">Markdown</a>
                    <?php else: ?>
                        <?php
                        $rawParams = array_merge($currentParams, ['view' => 'raw']);
                        unset($rawParams['raw']);
                        $rawUrl = $baseUrl . '?' . http_build_query($rawParams);
                        $isRawActive = ($viewMode === 'raw');
                        ?>
                        <a href="<?php echo $rawUrl; ?>" class="security-bar-btn<?php echo $isRawActive ? ' active' : ''; ?>">Raw</a>
                        <?php
                        $codeParams = array_merge($currentParams, ['view' => 'code']);
                        unset($codeParams['raw']);
                        $codeUrl = $baseUrl . '?' . http_build_query($codeParams);
                        $isCodeActive = ($viewMode === 'code' || $viewMode === 'default');
                        ?>
                        <a href="<?php echo $codeUrl; ?>" class="security-bar-btn<?php echo $isCodeActive ? ' active' : ''; ?>">Code</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isMarkdown): ?>
                <?php if ($showRaw): ?>
                    <pre><?php echo htmlspecialchars($fileContent); ?></pre>
                <?php elseif ($showMarkdown): ?>
                    <div class="markdown-content">
                        <?php echo Markdown::parse($fileContent); ?>
                    </div>
                <?php else: ?>
                    <?php echo CodeHighlight::render($fileContent, $extension, $filename); ?>
                <?php endif; ?>
            <?php elseif ($showCode): ?>
                <?php echo CodeHighlight::render($fileContent, $extension, $filename); ?>
            <?php else: ?>
                <pre><?php echo htmlspecialchars($fileContent); ?></pre>
            <?php endif; ?>
        </body>
        </html>
        <?php
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
?>