<?php

$basePath = realpath(dirname(__FILE__) . '/../');

return [
    'name' => 'Mobly Hour Bank E-mail to Slack',
    'credentialPath' => $basePath . '/data/credential/token.json',
    'clientSecretPath' => $basePath . '/data/credential/client_secret.json',
    'sheetFile' => $basePath . '/data/tmp/hourBank.xlsx',
    'hourLogFile' => $basePath . '/data/hour-log.json',
    'scopeList' => [
        Google_Service_Gmail::GMAIL_READONLY,
        Google_Service_Gmail::GMAIL_MODIFY,
    ],
    'partialAttachmentFileName' => 'Banco de Horas',
    'emailBodyLineWithHeadline' => 'banco de horas atualizado',
    'sheetTabName' => 'Funcionários',
    'filterList' => [
        'to' => '(bcc:me OR cc:me OR to:me)',
        'attachment' => 'has:attachment',
        'inbox' => 'in:inbox',
    ],
    'removeMessage' => false,
    'markAsDone' => false,
];