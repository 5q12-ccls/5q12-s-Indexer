<?php
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
?>