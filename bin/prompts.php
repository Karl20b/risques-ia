<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function ask(string $label): string {
    fwrite(STDERR, $label . ' : ');
    $line = fgets(STDIN);
    if ($line === false) throw new RuntimeException('Saisie interrompue.');
    return trim($line);
}
function secret(string $label): string {
    if (!function_exists('exec') || !function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        throw new RuntimeException('Terminal interactif requis pour masquer les mots de passe.');
    }
    exec('stty -g 2>/dev/null', $out, $status);
    $state = $out[0] ?? '';
    if ($status !== 0 || !preg_match('/^[a-fA-F0-9:;]+$/D', $state)) throw new RuntimeException('Masquage indisponible.');
    fwrite(STDERR, $label . ' (saisie masquée) : ');
    exec('stty -echo', $unused, $status);
    if ($status !== 0) throw new RuntimeException('Masquage impossible.');
    try {
        $line = fgets(STDIN);
        if ($line === false) throw new RuntimeException('Saisie interrompue.');
        return rtrim($line, "\r\n");
    } finally {
        exec('stty ' . escapeshellarg($state));
        fwrite(STDERR, "\n");
    }
}
