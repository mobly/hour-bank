<?php

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Common\Type;
use Box\Spout\Reader\XLSX\Sheet;
use Maknz\Slack\Message;
use Maknz\Slack\Client;
use SimpleHelpers\Cli;
use SimpleHelpers\String;

/**
 * @author Caio Costa <caio.costa@mobly.com.br>
 * @since 09/10/2015
 * @version 03/11/2015
 */

require('common.php');

$optionList = getopt('', ['dry-run::']);
$dryRun = isset($optionList['dry-run']);

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
    Cli::writeOutput(
        'Refreshing token',
        Cli::COLOR_YELLOW_DIM
    );

    $client->refreshToken($client->getRefreshToken());

    file_put_contents($credentialsPath, $client->getAccessToken());
}

$service = new Google_Service_Gmail($client);

$user = 'me';

Cli::writeOutput(
    'Retrieving last message',
    Cli::COLOR_GREEN_DIM
);
$userMessageList = $service->users_messages->listUsersMessages(
    $user,
    [
        'maxResults' => 1,
        'q' => implode(' ', $configuration['filterList']),
        'fields' => 'messages/id',
    ]
);

$messageList = $userMessageList->getMessages();

if (empty($messageList)) {
    Cli::writeOutput(
        'No messages found' . String::newLine(2),
        Cli::COLOR_YELLOW_BOLD
    );

    exit;
}

/** @var Google_Service_Gmail_Message $message */
$messageReference = array_shift($messageList);

Cli::writeOutput(
    'Fetching message content',
    Cli::COLOR_GREEN_DIM
);
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
            String::specialGmailMessageAttachmentDecode(
                $body->getData()
            )
        );

        foreach ($headLines as $line) {
            if (false !== strpos($line, $configuration['emailBodyLineWithHeadline'])) {
                $headLine = $line;

                break;
            }
        }
    }
}

if (null === $attachmentId) {
    Cli::writeOutput(
        'No message attachment found' . String::newLine(2),
        Cli::COLOR_RED_BOLD
    );

    exit;
}

Cli::writeOutput(
    'Fetching message attachment',
    Cli::COLOR_GREEN_DIM
);
/** @var Google_Service_Gmail_MessagePartBody $attachment */
$attachment = $service->users_messages_attachments->get($user, $message->getId(), $attachmentId);
$file = $configuration['sheetFile'];
$filePath = dirname($configuration['sheetFile']);

if (!is_dir($filePath)) {
    mkdir($filePath, 0777, true);
}

file_put_contents(
    $file,
    String::specialGmailMessageAttachmentDecode(
        $attachment->getData()
    )
);

Cli::writeOutput(
    'Parsing sheet',
    Cli::COLOR_GREEN_DIM
);
$reader = ReaderFactory::create(Type::XLSX);
$reader->open($file);

$team = $teamCli = [];
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

        foreach ($configuration['team'] as $member) {
            if ($row[2] !== $member) {
                continue;
            }

            $hours = $row[4]->diff($row[5])->format('%hh%I');
            $hourDirection = strtolower($row[7]);

            $team[$member] = [
                'title' => $member,
                'value' => '_' . $hours . '_ ' . $hourDirection,
            ];
            $teamFallback .= $member . ': ' . $hours . ' ' . $hourDirection . String::newLine();
            $teamCli[$member] = [
                'name' => $member,
                'hours' => $hours,
                'hourDirection' => $hourDirection,
            ];
        }
    }
}

$reader->close();

if (!empty($configuration['slackEndpoint']) && !empty($configuration['channel'])) {
    Cli::writeOutput(
        'Notifying Slack team',
        Cli::COLOR_GREEN_DIM
    );

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
    !$dryRun && $client
        ->attach([
            'fallback' => $teamFallback,
            'color' => 'bad',
            'fields' => $team
        ])
        ->send($headLine)
    ;
}

if ($configuration['markAsDone']) {
    $modify = new Google_Service_Gmail_ModifyMessageRequest();
    $modify->setRemoveLabelIds(['INBOX']);

    !$dryRun && $service->users_messages->modify($user, $message->getId(), $modify);

    Cli::writeOutput(
        'Message marked as *done*',
        Cli::COLOR_YELLOW_DIM
    );
}

if ($configuration['removeMessage'] && !$dryRun) {
    !$dryRun && $service->users_messages->trash($user, $message->getId());

    Cli::writeOutput(
        'Message moved to trash!',
        Cli::COLOR_YELLOW_DIM
    );
}

if ($headLine) {
    Cli::writeOutput(
        String::newLine() . $headLine . String::newLine(),
        Cli::COLOR_WHITE_BOLD
    );
}

foreach ($teamCli as $member => $data) {
    Cli::writeOutput(
        $member . ': ' . $data['hours'] . ' ' . $data['hourDirection'],
        $data['hourDirection'] == 'positivas' ? Cli::COLOR_GREEN : Cli::COLOR_RED
    );
}

Cli::writeOutput(String::newLine());
