<?php

return [
    'name' => 'Mobly Hour Bank E-mail to Slack',
    'credentialPath' => $basePath . '/data/credential/token.json',
    'clientSecretPath' => $basePath . '/data/credential/client_secret.json',
    'sheetFile' => $basePath . '/data/tmp/hourBank.xlsx',
    'scopeList' => [
        Google_Service_Gmail::GMAIL_READONLY
    ],
    'filterList' => [
        'maxResults' => 1,
        'q' => implode(' ', [
            'from:renata.arruda@mobly.com.br',
            'bcc:me',
            'has:attachment',
        ]),
        'fields' => 'messages/id',
    ],
    'partialAttachmentFileName' => 'Banco de Horas',
    'emailBodyLineWithHeadline' => 2,
    'sheetTabName' => 'Funcionários',
];