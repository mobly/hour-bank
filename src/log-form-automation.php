<?php

use SimpleHelpers\Cli;
use SimpleHelpers\Selenium;
use SimpleHelpers\String;

class HourLogFormAutomationTest extends Selenium
{
    const URL = 'https://docs.google.com/a/mobly.com.br/forms/d/11CFIHRL33Pw-vMLbPubP5NUZe_eGhI-equ-EhB9HjIg/viewform';

    /**
     * @var array
     */
    protected static $browsers = [
        [
            'browserName' => 'chrome',
            //'browserName' => 'phantomjs',
            'host' => 'localhost',
            'port' => 4444,
        ]
    ];

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var string
     */
    protected $password;

    protected function setUp()
    {
        global $configuration;

        $this->setBrowserUrl(self::URL);
        $this->configuration = $configuration;

        // workaround to be able to see the prompt text
        ob_end_flush();

        $this->password = Cli::readInput(
            'Please type your password to the e-mail ' . $this->configuration['email'] . ': ',
            [],
            '',
            true
        );

        // return to the original state
        ob_start();
    }

    public function testPersistDatabase()
    {
        $this->url(self::URL);

        $this->clickDisplayedElementByID('Email');
        $this->keys($this->configuration['email']);

        $this->clickDisplayedElementByID('next');

        $this->clickDisplayedElementByID('Passwd');
        $this->keys($this->password);

        $this->clickDisplayedElementByID('signIn');

        $timeFormat = 'H\hi';

        $data = json_decode(file_get_contents($this->configuration['hourLogFile']), true);
        foreach ($data as $date => $keyList) {
            if (empty($keyList)) {
                continue;
            }

            $dateTime = DateTime::createFromFormat('Y-m-d', $date);

            foreach ($keyList as $key => $entryList) {
                if (empty($entryList)) {
                    continue;
                }

                $commentList = [];
                $total = new DateTime("@0");
                foreach ($entryList as $index => $entry) {
                    if (isset($entry['form'])) {
                        continue 2;
                    }

                    if (empty($entry['stop'])) {
                        continue;
                    }

                    $total->add(
                        DateTime::createFromFormat($timeFormat, $entry['start'])
                            ->diff(
                                DateTime::createFromFormat($timeFormat, $entry['stop'])
                            )
                    );

                    if (!empty($entry['comment'])) {
                        $comment = str_replace('\n', String::newLine(), $entry['comment']);

                        $commentList[$comment] = $comment;
                    }
                }

                $this->clickDisplayedElementByID('entry_116160053');
                $this->keys('MEBLO-' . $key);

                $this->executeJavaScript(
                    "document.getElementById('entry_789822953').value = '" . $dateTime->format('Y-m-d') . "';"
                );

                $this->clickDisplayedElementByID('entry_368040154');
                $this->keys(
                    number_format(
                        (int)$total->format('H') + ((int)$total->format('i') / 60),
                        2
                    )
                );

                $this->clickDisplayedElementByID('entry_1252005894');
                $this->keys(implode(String::newLine(), $commentList));

                $this->clickDisplayedElementByID('emailReceipt');

                $this->clickDisplayedElementByID('ss-submit');

                $data[$date][$key][] = [
                    'form' => $this->waitUntilShow(Selenium::BY_CLASS_NAME, 'ss-bottom-link')->attribute('href')
                ];

                $this->url(self::URL);
            }
        }

        file_put_contents(
            $this->configuration['hourLogFile'],
            json_encode(
                $data,
                JSON_PRETTY_PRINT
            )
        );
    }
}
