<?php

return [
    'name' => 'Mobly Hour Bank E-mail to Slack',
    'credentialPath' => $basePath . '/data/credential/token.json',
    'clientSecretPath' => $basePath . '/data/credential/client_secret.json',
    'sheetFile' => $basePath . '/data/tmp/hourBank.xlsx',
    'scopeList' => [
        Google_Service_Gmail::GMAIL_READONLY
    ],
    'partialAttachmentFileName' => 'Banco de Horas',
    'emailBodyLineWithHeadline' => 2,
    'sheetTabName' => 'Funcionários',
];