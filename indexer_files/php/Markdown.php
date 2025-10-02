<?php
class Markdown {
    public static function parse($text) {
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
        $text = self::wrapLists($text);
        $text = preg_replace_callback('/(?:^\|.+\|\s*$\n?)+/m', function($matches) {
            return self::parseTable($matches[0]);
        }, $text);
        $text = self::wrapParagraphs($text);
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
    private static function wrapLists($text) {
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
        return implode("\n", $result);
    }
    private static function parseTable($table) {
        $table = trim($table);
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
    }
    private static function wrapParagraphs($text) {
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
        return implode("\n\n", $result);
    }
}
?>