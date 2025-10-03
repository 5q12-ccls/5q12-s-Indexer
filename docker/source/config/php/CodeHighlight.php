<?php
class HighlightAutoloader {
    private static $baseDir = null;
    private static $loaded = false;
    public static function init() {
        if (self::$loaded) {
            return;
        }
        self::$baseDir = __DIR__ . '/scrivo/highlight.php';
        require_once self::$baseDir . '/HighlightUtilities/functions.php';
        spl_autoload_register([__CLASS__, 'autoload']);
        self::$loaded = true;
    }
    public static function autoload($className) {
        $prefixes = ['Highlight\\', 'HighlightUtilities\\'];
        foreach ($prefixes as $prefix) {
            if (strpos($className, $prefix) === 0) {
                $classPath = str_replace('\\', DIRECTORY_SEPARATOR, $className);
                $file = self::$baseDir . DIRECTORY_SEPARATOR . $classPath . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
        }
        return false;
    }
}
HighlightAutoloader::init();
use Highlight\Highlighter;
class CodeHighlight {
    private static $languageMap = [
        'html' => 'xml',
        'htm' => 'xml',
        'xml' => 'xml',
        'svg' => 'xml',
        'css' => 'css',
        'scss' => 'scss',
        'sass' => 'sass',
        'less' => 'less',
        'js' => 'javascript',
        'jsx' => 'javascript',
        'mjs' => 'javascript',
        'cjs' => 'javascript',
        'ts' => 'typescript',
        'tsx' => 'typescript',
        'json' => 'json',
        'jsonc' => 'json',
        'php' => 'php',
        'php3' => 'php',
        'php4' => 'php',
        'php5' => 'php',
        'phtml' => 'php',
        'py' => 'python',
        'pyw' => 'python',
        'pyc' => 'python',
        'pyo' => 'python',
        'pyd' => 'python',
        'rb' => 'ruby',
        'rbw' => 'ruby',
        'rake' => 'ruby',
        'gemspec' => 'ruby',
        'java' => 'java',
        'kt' => 'kotlin',
        'kts' => 'kotlin',
        'scala' => 'scala',
        'groovy' => 'groovy',
        'gradle' => 'gradle',
        'c' => 'c',
        'h' => 'c',
        'cpp' => 'cpp',
        'cc' => 'cpp',
        'cxx' => 'cpp',
        'hpp' => 'cpp',
        'hxx' => 'cpp',
        'hh' => 'cpp',
        'cs' => 'csharp',
        'm' => 'objectivec',
        'mm' => 'objectivec',
        'sh' => 'bash',
        'bash' => 'bash',
        'zsh' => 'bash',
        'fish' => 'bash',
        'bat' => 'dos',
        'cmd' => 'dos',
        'ps1' => 'powershell',
        'psm1' => 'powershell',
        'md' => 'markdown',
        'markdown' => 'markdown',
        'yml' => 'yaml',
        'yaml' => 'yaml',
        'toml' => 'toml',
        'ini' => 'ini',
        'cfg' => 'ini',
        'conf' => 'nginx',
        'config' => 'xml',
        'rs' => 'rust',
        'go' => 'go',
        'swift' => 'swift',
        'pl' => 'perl',
        'pm' => 'perl',
        'lua' => 'lua',
        'r' => 'r',
        'hs' => 'haskell',
        'ex' => 'elixir',
        'exs' => 'elixir',
        'erl' => 'erlang',
        'clj' => 'clojure',
        'cljs' => 'clojure',
        'cljc' => 'clojure',
        'dart' => 'dart',
        'sql' => 'sql',
        'pgsql' => 'pgsql',
        'plsql' => 'sql',
        'vb' => 'vbnet',
        'fs' => 'fsharp',
        'fsx' => 'fsharp',
        'f90' => 'fortran',
        'f95' => 'fortran',
        'f03' => 'fortran',
        'asm' => 'x86asm',
        's' => 'armasm',
        'lisp' => 'lisp',
        'cl' => 'lisp',
        'el' => 'lisp',
        'scm' => 'scheme',
        'ml' => 'ocaml',
        'mli' => 'ocaml',
        'vue' => 'xml',
        'svelte' => 'xml',
        'twig' => 'twig',
        'django' => 'django',
        'jinja' => 'django',
        'erb' => 'erb',
        'handlebars' => 'handlebars',
        'hbs' => 'handlebars',
        'mustache' => 'handlebars',
        'proto' => 'protobuf',
        'graphql' => 'graphql',
        'gql' => 'graphql',
        'dockerfile' => 'dockerfile',
        'nginx' => 'nginx',
        'htaccess' => 'apache',
        'makefile' => 'makefile',
        'make' => 'makefile',
        'mk' => 'makefile',
        'cmake' => 'cmake',
        'tex' => 'latex',
        'latex' => 'latex',
        'diff' => 'diff',
        'patch' => 'diff',
        'log' => 'accesslog',
        'bas' => 'basic',
        'vbs' => 'vbscript',
        'ada' => 'ada',
        'adb' => 'ada',
        'ads' => 'ada',
        'cob' => 'cobol',
        'cbl' => 'cobol',
        'matlab' => 'matlab',
        'jl' => 'julia',
        'nim' => 'nim',
        'cr' => 'crystal',
        'd' => 'd',
        'zig' => 'zig',
        'elm' => 'elm',
        'purs' => 'purescript',
        're' => 'reasonml',
        'vhdl' => 'vhdl',
        'vhd' => 'vhdl',
        'v' => 'verilog',
        'sv' => 'verilog',
        'pro' => 'prolog',
        'tcl' => 'tcl',
        'awk' => 'awk',
        'sed' => 'sed',
        'pig' => 'pig',
        'bf' => 'brainfuck',
    ];
    private static $filenameMap = [
        'dockerfile' => 'dockerfile',
        'makefile' => 'makefile',
        'rakefile' => 'ruby',
        'gemfile' => 'ruby',
        'vagrantfile' => 'ruby',
        'guardfile' => 'ruby',
        'capfile' => 'ruby',
        'brewfile' => 'ruby',
        'cmakelists.txt' => 'cmake',
        'gruntfile' => 'javascript',
        'gulpfile' => 'javascript',
        'jenkinsfile' => 'groovy',
        'procfile' => 'yaml',
        '.gitignore' => 'ini',
        '.dockerignore' => 'ini',
        '.editorconfig' => 'ini',
        '.env' => 'ini',
        '.eslintrc' => 'json',
        '.babelrc' => 'json',
        '.prettierrc' => 'json',
    ];
    public static function render($code, $extension, $filename = '') {
        $highlighter = new Highlighter();
        $filenameLower = strtolower($filename);
        if (isset(self::$filenameMap[$filenameLower])) {
            $language = self::$filenameMap[$filenameLower];
        } else {
            $language = self::$languageMap[$extension] ?? $extension;
        }
        try {
            $result = $highlighter->highlight($language, $code);
            $highlightedCode = $result->value;
            $displayLines = preg_split('/\r\n|\r|\n/', $highlightedCode);
            $lineCount = count($displayLines);
            if (end($displayLines) === '') {
                $lineCount--;
            }
            $lineNumbers = '';
            for ($i = 1; $i <= $lineCount; $i++) {
                $lineNumbers .= '<span>' . $i . '</span>';
            }
            return '<div class="code-viewer-wrapper">' .
                '<div class="line-numbers">' . $lineNumbers . '</div>' .
                '<pre class="code-block"><code class="hljs language-' . 
                htmlspecialchars($language) . '">' . 
                $highlightedCode . '</code></pre>' .
                '</div>';
        } catch (\Exception $e) {
            $escapedCode = htmlspecialchars($code);
            $displayLines = preg_split('/\r\n|\r|\n/', $escapedCode);
            $lineCount = count($displayLines);
            if (end($displayLines) === '') {
                $lineCount--;
            }
            $lineNumbers = '';
            for ($i = 1; $i <= $lineCount; $i++) {
                $lineNumbers .= '<span>' . $i . '</span>';
            }
            return '<div class="code-viewer-wrapper">' .
                '<div class="line-numbers">' . $lineNumbers . '</div>' .
                '<pre class="code-block"><code class="hljs">' . 
                $escapedCode . '</code></pre>' .
                '</div>';
        }
    }
    public static function isSupported($extension, $filename = '') {
        $filenameLower = strtolower($filename);
        if (isset(self::$filenameMap[$filenameLower])) {
            return true;
        }
        $language = self::$languageMap[$extension] ?? $extension;
        $highlighter = new Highlighter();
        return in_array($language, $highlighter->listLanguages());
    }
}
?>