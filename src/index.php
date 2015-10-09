<?php

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;
use Box\Spout\Reader\XLSX\Sheet;
use Maknz\Slack\Message;
use Maknz\Slack\Client;

$basePath = realpath(dirname(__FILE__) . '/../');

require($basePath . '/vendor/autoload.php');

$localConfigurationFile = $basePath . '/configuration/local.php';
if (!file_exists($localConfigurationFile)) {
    exit('Please copy the local.php.template to local.php and configure with your data');
}

$configuration = array_merge(
    include($basePath . '/configuration/application.php'),
    include($localConfigurationFile)
);

/**
 * @see http://stackoverflow.com/questions/25193429/cant-open-downloaded-attachments-from-gmail-api
 *
 * @param string$content
 *
 * @return string
 */
function specialDecode($content)
{
    return base64_decode(
        str_replace(
            ['-', '_'],
            ['+', '/'],
            $content
        )
    );
}

$client = new Google_Client();
$client->setApplicationName($configuration['name']);
$client->setScopes($configuration['scopeList']);
$client->setAuthConfigFile($configuration['clientSecretPath']);
$client->setAccessType('offline');

// Load previously authorized credentials from a file.
$credentialsPath = $configuration['credentialPath'];

if (file_exists($credentialsPath)) {
    $accessToken = file_get_contents($credentialsPath);
} else {
    // Request authorization from the user.
    $authUrl = $client->createAuthUrl();

    printf("Open the following link in your browser:\n%s\n", $authUrl);
    print 'Enter verification code: ';

    $authCode = trim(fgets(STDIN));

    // Exchange authorization code for an access token.
    $accessToken = $client->authenticate($authCode);

    // Store the credentials to disk.
    if (!file_exists(dirname($credentialsPath))) {
        mkdir(dirname($credentialsPath), 0700, true);
    }

    file_put_contents($credentialsPath, $accessToken);

    printf("Credentials saved to %s\n", $credentialsPath);
}

$client->setAccessToken($accessToken);

// Refresh the token if it's expired.
if ($client->isAccessTokenExpired()) {
    $client->refreshToken($client->getRefreshToken());

    file_put_contents($credentialsPath, $client->getAccessToken());
}

$service = new Google_Service_Gmail($client);

// Print the labels in the user's account.
$user = 'me';
$userMessageList = $service->users_messages->listUsersMessages(
    $user,
    $configuration['filterList']
);

$messageList = $userMessageList->getMessages();
/** @var Google_Service_Gmail_Message $message */
$messageReference = array_shift($messageList);
$message = $service->users_messages->get($user, $messageReference->getId());
/** @var Google_Service_Gmail_MessagePart $messagePart */
$messagePart = $message->getPayload();

$date = null;
/** @var Google_Service_Gmail_MessagePartHeader $header */
foreach ($messagePart->getHeaders() as $header) {
    if ($header->getName() === 'Date') {
        $date = $header->getValue();
    }
}

$attachmentId = null;
$headLine = null;

/** @var Google_Service_Gmail_MessagePart $attachment */
foreach ($messagePart->getParts() as $attachment) {
    if (strpos($attachment->getFilename(), $configuration['partialAttachmentFileName']) !== false) {
        /** @var Google_Service_Gmail_MessagePartBody $body */
        $body = $attachment->getBody();
        $attachmentId = $body->getAttachmentId();
    }

    if ($attachment->getMimeType() === 'multipart/related') {
        /** @var Google_Service_Gmail_MessagePart $parts */
        $parts = $attachment->getParts();
        /** @var Google_Service_Gmail_MessagePart $text */
        $text = array_shift($parts);
        /** @var Google_Service_Gmail_MessagePart $parts */
        $parts = $text->getParts();
        /** @var Google_Service_Gmail_MessagePart $text */
        $text = array_shift($parts);
        /** @var Google_Service_Gmail_MessagePartBody $body */
        $body = $text->getBody();

        $headLines = explode(
            PHP_EOL,
            specialDecode(
                $body->getData()
            )
        );
        $headLine = $headLines[$configuration['emailBodyLineWithHeadline']];
    }
}

if (null === $attachmentId) {
    exit('no attachment found');
}

/** @var Google_Service_Gmail_MessagePartBody $attachment */
$attachment = $service->users_messages_attachments->get($user, $message->getId(), $attachmentId);
$file = $configuration['sheetFile'];
$filePath = dirname($configuration['sheetFile']);

if (!is_dir($filePath)) {
    mkdir($filePath, 0777, true);
}

file_put_contents(
    $file,
    specialDecode(
        $attachment->getData()
    )
);

$reader = ReaderFactory::create(Type::XLSX);
$reader->open($file);

$team = [];
$teamFallback = '';
/** @var Sheet $sheet */
foreach ($reader->getSheetIterator() as $sheet) {
    if ($configuration['sheetTabName'] !== $sheet->getName()) {
        continue;
    }

    foreach ($sheet->getRowIterator() as $row) {
        if (empty($row[2])) {
            continue;
        }

        foreach ($configuration['team']() as $member) {
            if ($row[2] !== $member) {
                continue;
            }

            $zero = new DateTime('@0');
            $time = $zero->diff(
                // @see https://support.microsoft.com/en-us/kb/190633
                new DateTime('@' . ($row[6] * 24 * 60 * 60))
            );

            $hours = $time->format('%hh%I');
            $hourDirection =  strtolower($row[7]);

            $team[$member] = [
                'title' => $member,
                'value' => '_' . $hours . '_ ' . $hourDirection
            ];
            $teamFallback .= $member . ': ' . $hours . ' ' . $hourDirection . PHP_EOL;
        }
    }
}

$reader->close();

// Instantiate with defaults, so all messages created
// will be sent from 'Cyril' and to the #accounting channel
// by default. Any names like @regan or #channel will also be linked.
$client = new Client(
    $configuration['slackEndpoint'],
    [
        'username' => 'HourBank',
        'channel' => $configuration['channel'],
        'icon' => ':clock4:',
        'markdown_in_attachments' => ['fields'],
    ]
);

/** @var $client Message */
$client
    ->attach([
        'fallback' => $teamFallback,
        'color' => 'bad',
        'fields' => $team
    ])
    ->send($headLine)
;

var_dump(
    //$date,
    $headLine,
    $team
);
