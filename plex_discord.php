<?php

declare(strict_types=1);

/**
 * Simple Plex -> Discord webhook relay.
 *
 * Setup:
 * 1) Set your Discord webhook URL in the environment variable DISCORD_WEBHOOK_URL
 *    or directly in $discordWebhookUrl below.
 * 2) Set a webhook secret in WEBHOOK_SECRET (or directly in $webhookSecret).
 * 3) Configure this script URL as webhook in Plex and append ?secret=YOUR_SECRET.
 * 4) Optional: set LOG_ENABLED=true|false to enable/disable file logging.
 * 5) Optional for thumbnails: set PLEX_BASE_URL and PLEX_TOKEN.
 */

$discordWebhookUrl = getenv('DISCORD_WEBHOOK_URL') ?: 'https://discord.com/api/webhooks/REPLACE_WITH_YOUR_WEBHOOK';
$webhookSecret = getenv('WEBHOOK_SECRET') ?: 'REPLACE_WITH_A_SECRET';
$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'plex.log';
$logEnabled = filter_var(getenv('LOG_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);
$plexBaseUrl = rtrim(getenv('PLEX_BASE_URL') ?: '', '/');
$plexToken = getenv('PLEX_TOKEN') ?: '';
$language = strtolower(getenv('LANGUAGE') ?: 'en');

// Language strings for notifications
$translations = [
    'de' => [
        'unknownMovie' => 'Unbekannter Film',
        'unknownShow' => 'Unbekannte Serie',
        'unknownEpisode' => 'Unbekannte Episode',
        'unknownLibrary' => 'Unbekannte Library',
        'newMovie' => 'Neuer Film',
        'newShow' => 'Neue Serie',
        'newEpisode' => 'Neue Episode',
        'title' => 'Titel',
        'library' => 'Library',
        'server' => 'Server',
        'year' => 'Jahr',
    ],
    'en' => [
        'unknownMovie' => 'Unknown Movie',
        'unknownShow' => 'Unknown Series',
        'unknownEpisode' => 'Unknown Episode',
        'unknownLibrary' => 'Unknown Library',
        'newMovie' => 'New Movie',
        'newShow' => 'New Series',
        'newEpisode' => 'New Episode',
        'title' => 'Title',
        'library' => 'Library',
        'server' => 'Server',
        'year' => 'Year',
    ],
];

// Use selected language or fall back to German
if (!isset($translations[$language])) {
    $language = 'de';
}
$t = $translations[$language];

function writeLog(string $logFile, string $level, string $message): void
{
    global $logEnabled;
    if (!$logEnabled) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $line = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    writeLog($logFile, 'warn', 'Rejected request with invalid method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed';
    exit;
}

if (str_contains($discordWebhookUrl, 'REPLACE_WITH_YOUR_WEBHOOK')) {
    writeLog($logFile, 'error', 'Discord webhook URL is not configured');
    http_response_code(500);
    echo 'Discord webhook URL is not configured';
    exit;
}

if (str_contains($webhookSecret, 'REPLACE_WITH_A_SECRET')) {
    writeLog($logFile, 'error', 'Webhook secret is not configured');
    http_response_code(500);
    echo 'Webhook secret is not configured';
    exit;
}

$providedSecret = $_GET['secret'] ?? '';
if (!hash_equals($webhookSecret, (string)$providedSecret)) {
    writeLog($logFile, 'warn', 'Unauthorized request: secret mismatch');
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

/**
 * Plex usually sends multipart/form-data with a "payload" field.
 * For easier testing, plain JSON body is also supported.
 */
$payloadRaw = $_POST['payload'] ?? file_get_contents('php://input');
if (!$payloadRaw) {
    writeLog($logFile, 'warn', 'Missing payload');
    http_response_code(400);
    echo 'Missing payload';
    exit;
}

$payload = json_decode($payloadRaw, true);
if (!is_array($payload)) {
    writeLog($logFile, 'warn', 'Invalid JSON payload');
    http_response_code(400);
    echo 'Invalid JSON payload';
    exit;
}

$event = $payload['event'] ?? '';
$metadata = $payload['Metadata'] ?? [];
$type = $metadata['type'] ?? '';

// Only forward newly added library items.
if ($event !== 'library.new') {
    writeLog($logFile, 'info', 'Ignored event: ' . ($event !== '' ? $event : 'unknown'));
    http_response_code(200);
    echo 'Ignored event';
    exit;
}

$allowedTypes = ['movie', 'show', 'episode'];
if (!in_array($type, $allowedTypes, true)) {
    writeLog($logFile, 'info', 'Ignored media type: ' . ($type !== '' ? $type : 'unknown'));
    http_response_code(200);
    echo 'Ignored media type';
    exit;
}

$libraryName = $payload['Server']['title'] ?? ($payload['Account']['title'] ?? 'Plex');
$sectionName = $metadata['librarySectionTitle'] ?? 'Unbekannte Library';

if ($type === 'movie') {
    $title = $metadata['title'] ?? $t['unknownMovie'];
    $subtitle = isset($metadata['year']) ? (string)$metadata['year'] : '';
    $emoji = "🎬";
    $typeLabel = $t['newMovie'];
} elseif ($type === 'show') {
    $title = $metadata['title'] ?? $t['unknownShow'];
    $subtitle = isset($metadata['year']) ? (string)$metadata['year'] : '';
    $emoji = "📺";
    $typeLabel = $t['newShow'];
} else {
    $seriesTitle = $metadata['grandparentTitle'] ?? $t['unknownShow'];
    $episodeTitle = $metadata['title'] ?? $t['unknownEpisode'];
    $season = isset($metadata['parentIndex']) ? (int)$metadata['parentIndex'] : 0;
    $episode = isset($metadata['index']) ? (int)$metadata['index'] : 0;

    $title = sprintf('%s - S%02dE%02d - %s', $seriesTitle, $season, $episode, $episodeTitle);
    $subtitle = '';
    $emoji = "📺";
    $typeLabel = 'Neue Episode';
}

$description = "{$emoji} **{$typeLabel}** in **{$sectionName}**";
if ($subtitle !== '') {
    $description .= " ({$subtitle})";
}

$color = $type === 'movie' ? 15105570 : 3447003;

$fields = [
    [
        'name' => $t['title'],
        'value' => $title,
        'inline' => false,
    ],
    [
        'name' => $t['library'],
        'value' => $sectionName,
        'inline' => true,
    ],
    [
        'name' => $t['server'],
        'value' => $libraryName,
        'inline' => true,
    ],
];

if ($subtitle !== '') {
    $fields[] = [
        'name' => $t['year'],
        'value' => $subtitle,
        'inline' => true,
    ];
}

$thumbnailUrl = '';
$thumbPath = $metadata['thumb'] ?? ($metadata['grandparentThumb'] ?? '');
if (is_string($thumbPath) && $thumbPath !== '') {
    if (filter_var($thumbPath, FILTER_VALIDATE_URL)) {
        $thumbnailUrl = $thumbPath;
    } elseif ($plexBaseUrl !== '') {
        $thumbnailUrl = $plexBaseUrl . $thumbPath;
        if ($plexToken !== '') {
            $separator = str_contains($thumbnailUrl, '?') ? '&' : '?';
            $thumbnailUrl .= $separator . 'X-Plex-Token=' . rawurlencode($plexToken);
        }
    }
}

$embed = [
    'title' => $typeLabel,
    'description' => $description,
    'color' => $color,
    'fields' => $fields,
    'timestamp' => gmdate('c'),
    'footer' => [
        'text' => 'Plex Webhook Relay',
    ],
];

if ($thumbnailUrl !== '') {
    $embed['thumbnail'] = [
        'url' => $thumbnailUrl,
    ];
}

$discordPayload = [
    'username' => 'Plex Bot',
    'embeds' => [$embed],
    'allowed_mentions' => [
        'parse' => [],
    ],
];

$ch = curl_init($discordWebhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($discordPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$responseBody = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseBody === false || $curlError !== '') {
    writeLog($logFile, 'error', 'Discord request failed: ' . ($curlError !== '' ? $curlError : 'unknown cURL error'));
    http_response_code(502);
    echo 'Discord request failed: ' . $curlError;
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    writeLog($logFile, 'error', 'Discord returned HTTP ' . $httpCode . ': ' . (string)$responseBody);
    http_response_code(502);
    echo 'Discord returned HTTP ' . $httpCode . ': ' . $responseBody;
    exit;
}

writeLog(
    $logFile,
    'info',
    sprintf(
        'Forwarded %s item "%s" from library "%s" on server "%s"',
        $type,
        $title,
        $sectionName,
        $libraryName
    )
);

http_response_code(200);
echo 'Forwarded';
