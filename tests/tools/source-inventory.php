<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

$root = dirname(__DIR__, 2);
$sources = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/low_widont', FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() === 'php') {
        $sources[substr($file->getPathname(), strlen($root) + 1)] = file_get_contents($file->getPathname());
    }
}
$archive = new Phar($root . '/low_widont/low_widont.phar');
$prefix = 'phar://' . $root . '/low_widont/low_widont.phar/';
foreach (new RecursiveIteratorIterator($archive) as $file) {
    if ($file->getExtension() === 'php') {
        $sources['low_widont/low_widont.phar!/' . substr($file->getPathname(), strlen($prefix))] = $file->getContent();
    }
}
$sources['low_widont/low_widont.phar!/{stub}'] = $archive->getStub();
ksort($sources);
$inventory = [];
foreach ($sources as $path => $contents) {
    $methods = [];
    $tokens = token_get_all($contents);
    $tokenLines = [];
    $currentLine = 1;
    foreach ($tokens as $tokenIndex => $sourceToken) {
        $tokenLines[$tokenIndex] = is_array($sourceToken) ? $sourceToken[2] : $currentLine;
        $currentLine = $tokenLines[$tokenIndex] + substr_count(is_array($sourceToken) ? $sourceToken[1] : $sourceToken, "\n");
    }
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $name = '{closure@' . $token[2] . '}';
        for ($next = $index + 1; $next < count($tokens); $next++) {
            if (is_array($tokens[$next]) && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            if ($tokens[$next] === '&') {
                continue;
            }
            if (is_array($tokens[$next]) && $tokens[$next][0] === T_STRING) {
                $name = $tokens[$next][1];
            }
            break;
        }
        $depth = 0;
        $hasBody = false;
        $endLine = $token[2];
        for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
            if (is_array($tokens[$cursor])) {
                if ($hasBody && in_array($tokens[$cursor][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                    $depth++;
                }
                continue;
            }
            if (!$hasBody && $tokens[$cursor] === ';') {
                $endLine = $tokenLines[$cursor];
                break;
            }
            if ($tokens[$cursor] === '{') {
                $depth++;
                $hasBody = true;
            }
            if ($tokens[$cursor] === '}') {
                $depth--;
                if ($hasBody && $depth === 0) {
                    $endLine = $tokenLines[$cursor];
                    break;
                }
            }
        }
        $methods[] = ['name' => $name, 'line' => $token[2], 'end_line' => $endLine, 'abstract_declaration' => !$hasBody];
    }
    $inventory[$path] = ['sha256' => hash('sha256', $contents), 'bytes' => strlen($contents), 'lines' => substr_count($contents, "\n") + 1,
        'functions_including_magic_and_closures' => $methods, 'procedural_scope_included' => true];
}
echo json_encode(['baseline' => '3a38b1fb9146daa87ed9f1a83699521ea03091da', 'archive_sha256' => hash_file('sha256', $root . '/low_widont/low_widont.phar'), 'files' => $inventory], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
