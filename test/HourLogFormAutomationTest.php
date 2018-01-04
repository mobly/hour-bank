<?php

use SimpleHelpers\Cli;
use SimpleHelpers\Selenium;

class HourLogFormAutomationTest extends Selenium
{
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

        $this->setBrowserUrl($configuration['googleFormUrl']);

        $this->configuration = $configuration;

        // workaround to be able to see the prompt text
        ob_end_flush();

        $this->password = Cli::readInput(
            'Please type your password to the e-mail ' . $this->configuration['email'] . ': ',
            [],
            '',
            true
        );

        Cli::writeOutput(PHP_EOL);

        // return to the original state
        ob_start();
    }

    public function testPersistDatabase()
    {
        $this->url($this->configuration['googleFormUrl']);

        $this->clickDisplayedElementByName('identifier');
        $this->keys($this->configuration['email']);

        $this->clickDisplayedElementByID('identifierNext');
        sleep(2);

        $this->clickDisplayedElementByName('password');
        $this->keys($this->password);

        $this->clickDisplayedElementByID('passwordNext');
        sleep(10);

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
                        $comment = str_replace('\n', PHP_EOL, $entry['comment']);

                        $commentList[$comment] = $comment;
                    }
                }

                $this->clickDisplayedElementByID('entry_116160053');
                $this->keys('ST-' . $key);

                $this->executeJavaScript(
                    "document.getElementById('entry_789822953').value = '" . $dateTime->format('Y-m-d') . "';"
                );

                $this->clickDisplayedElementByID('entry_368040154');
                $this->keys(
                    number_format(
                        (int)$total->format('H') + ((int)$total->format('i') / 60),
                        1,
                        '.',
                        ''
                    )
                );

                $this->clickDisplayedElementByID('entry_1252005894');
                $this->keys(implode(PHP_EOL, $commentList));

                $this->clickDisplayedElementByID('emailReceipt');

                $this->clickDisplayedElementByID('ss-submit');

                $data[$date][$key][] = [
                    'form' => $this->waitUntilShow(Selenium::BY_CLASS_NAME, 'ss-bottom-link')->attribute('href')
                ];

                $this->url($this->configuration['googleFormUrl']);
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
