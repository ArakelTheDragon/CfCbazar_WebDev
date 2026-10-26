#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * ai_site_editor.php — drive your Ollama portal API as a personal web builder.
 *
 * Safe by default (dry run). Writes only with --apply. Backs up first.
 * Validates .php with `php -l` and .json with json_decode; restores on failure.
 *
 * The portal already does planner → workers → synthesis → continuations.
 * For CODE we force workers=1 so the model returns one coherent file
 * instead of a synthesis of four conflicting ones.
 *
 * Examples:
 *   php ai_site_editor.php --root=/var/www/htdocs --mode=review
 *   php ai_site_editor.php --root=/var/www/htdocs --file=index.php --mode=rewrite
 *   php ai_site_editor.php --root=/var/www/htdocs --file=style.css --mode=rewrite --apply
 *   php ai_site_editor.php --root=/var/www/htdocs --file=contact.php --mode=rewrite --php-ok --apply
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

const API_URL          = 'https://cfcbazar.8bit.ca/diy/ai/index.php?api=1';
const DEFAULT_MODEL    = 'beam';       // faster than gpt-oss:20b for small rewrites
const MAX_PROMPT_BYTES = 95000;        // portal limit is 100 KB
const CLIENT_TIMEOUT   = 900;          // seconds; pipeline makes several LLM calls
const SLEEP_BETWEEN    = 2;            // seconds between files

const TEXT_EXTS = ['html','htm','css','js','php','md','txt','json','svg'];
const SKIP_DIRS = ['.git','node_modules','vendor','.ai_backups','uploads'];

const LANGS = [
    'html'=>'html','htm'=>'html','css'=>'css','js'=>'javascript',
    'php'=>'php','md'=>'markdown','json'=>'json','svg'=>'xml','txt'=>'text',
];

function usage(): string {
    return <<<TXT
Usage: php ai_site_editor.php --root=PATH [options]

  --root=PATH       Site root, e.g. /var/www/htdocs           (required)
  --file=PATH       Single file relative to root              (optional)
  --mode=MODE       review | rewrite  (default: review)
  --model=NAME      Ollama model (default: beam)
  --workers=N       1..4  (default: 1; keep at 1 for code)
  --apply           Actually write changes (default: dry run)
  --php-ok          Allow rewriting .php files (still validated)
  --help            Show this help

TXT;
}

/* ---------- args ------------------------------------------------------- */

$opts = getopt('', ['root:', 'file:', 'mode:', 'model:', 'apply', 'php-ok', 'workers:', 'help']);

if ($opts === false || isset($opts['help']) || !isset($opts['root'])) {
    fwrite(STDERR, usage());
    exit(1);
}

$root       = rtrim(realpath((string)$opts['root']) ?: '', '/');
$only       = isset($opts['file'])    ? (string)$opts['file']             : null;
$mode       = isset($opts['mode'])    ? (string)$opts['mode']             : 'review';
$model      = isset($opts['model'])   ? (string)$opts['model']            : DEFAULT_MODEL;
$apply      = isset($opts['apply']);
$phpOk      = isset($opts['php-ok']);
$workersArg = isset($opts['workers']) ? (int)$opts['workers']             : 1;

if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Not a directory: {$root}\n");
    exit(1);
}
if (!in_array($mode, ['review', 'rewrite'], true)) {
    fwrite(STDERR, "Invalid --mode (use review or rewrite)\n");
    exit(1);
}
$workersArg = max(1, min(4, $workersArg));

/* ---------- prompts ---------------------------------------------------- */

const PROMPTS = [
    'review' => <<<'PROMPT'
You are a senior web developer reviewing a {EXT} file for a small site.
Return a concise bullet list of concrete issues only — accessibility, SEO,
performance, security, correctness, dead code. No praise. Do NOT return the file.

File: {PATH}
```{LANG}
{CONTENT}