# Mobly - Hour Bank

## Description

Parses hour bank e-mail and post on slack to a development team

## Setup

#### Application Configuration Files

1. Duplicate `./configuration/local.php.template` to `./configuration/local.php`
2. Change the **team** to an array containing the names of the team member's
3. Change the Slack **channel** that the hours need to be published
4. Change the **slackEndpoint** with the **Webhook URL** of a **Incoming WebHooks** created in Slack (see above)

#### Slack Incoming WebHook

1. Access **Integrations** in your tem Slack website `https://[your team].slack.com/services`
2. Search for **Incoming WebHooks** then click **Add**
3. Select the **Post to Channel** that the hours need to be published (will be the default channel for the hook)
4. Copy the **Webhook URL** generated to **slackEndpoint** into `./configuration/local.php` 

#### Google Developers Console

1. Create a project in the [Google Developers Console](developer console)
2. Enable Gmail API in **APIs & Auth** > **APIs** > **Google Apps APIs** > **Gmail API** > **Enable API**
3. Create a credential in **APIs & Auth** > **Credentials** > **Add credentials** > **OAuth 2.0 client ID**
4. Download the JSON credential.
5. Once downloaded, create the (default) path `./data/credential/` and move it there. 

> * It shoud look like this (as defined in the application.php): `./data/credential/client_secret.json`
> * The credential JSON file should never be committed with your source code, and should be stored securely.

## Setup

```bash
composer dump-autoload -o
```

## Run

#### Balance

Generate balance report

```bash
php src/balance.php
```

#### Log

Manage time logging

```bash
php src/log.php
```

[developer console]: https://console.developers.google.com