<?php

namespace yii_tc_pho\Reporter;

use pho\Runnable\Spec;
use pho\Suite\Suite;

class TeamCityReporter extends \pho\Reporter\AbstractReporter
{
    protected $beginTimes = [];

    protected function registerBeginTime($key)
    {
        $this->beginTimes[$key] = floor(microtime(true)/1000);
    }

    protected function getDurationTime($key)
    {
        if (!isset($this->beginTimes[$key]))
        {
            return 0;
        }

        return $this->beginTimes[$key] - ceil(microtime(true)/1000);
    }

    public function beforeSuite(Suite $suite)
    {
        $this->console->writeLn("\n##teamcity[testStarted name='" . $suite->getTitle() . "']");
        $this->registerBeginTime(md5($suite->getTitle()));
        return parent::beforeSuite($suite);
    }

    public function afterSuite(Suite $suite)
    {
        $this->console->writeLn("\n##teamcity[testFinished name='" . $suite->getTitle() . "' duration='" . $this->getDurationTime(md5($suite->getTitle())) . "']");
        return parent::beforeSuite($suite);
    }

    public function beforeSpec(Spec $spec)
    {
        $this->specCount += 1;
    }

    /**
     * Ran after an individual spec. May be used to display the results of that
     * particular spec.
     *
     * @param Spec $spec The spec after which to run this method
     */
    public function afterSpec(Spec $spec)
    {
        $leftPad = str_repeat(' ', self::TAB_SIZE * $this->depth);

        if ($spec->isFailed())
        {
            $this->failedSpecs[] = $spec;
            $title = $this->formatter->red($spec->getTitle());

            $this->console->writeLn("
                ##teamcity[testFailed name='" . $spec->getTitle() . "' message='" . $this->formatter->red($spec) . "' details='" . $spec->exception . "']
            ");
        }
        else if ($spec->isIncomplete())
        {
            $this->incompleteSpecs[] = $spec;
            $title = $this->formatter->cyan($spec->getTitle());
        }
        else if ($spec->isPending())
        {
            $this->pendingSpecs[] = $spec;
            $title = $this->formatter->yellow($spec->getTitle());
        }
        else
        {
            $title = $this->formatter->grey($spec->getTitle());
        }

        //$this->console->writeLn($leftPad . $title);
    }

    public function afterRun()
    {
        if (count($this->failedSpecs)) {
            $this->console->writeLn("\nFailures:");
        }

        if ($this->startTime) {
            $endTime = microtime(true);
            $runningTime = round($endTime - $this->startTime, 5);
            $this->console->writeLn("\nFinished in $runningTime seconds");
        }

        $failedCount = count($this->failedSpecs);
        $incompleteCount = count($this->incompleteSpecs);
        $pendingCount = count($this->pendingSpecs);
        $specs = ($this->specCount == 1) ? 'spec' : 'specs';
        $failures = ($failedCount == 1) ? 'failure' : 'failures';
        $incomplete = ($incompleteCount) ? ", $incompleteCount incomplete" : '';
        $pending = ($pendingCount) ? ", $pendingCount pending" : '';

        // Print ASCII art if enabled
        if ($this->console->options['ascii']) {
            $this->console->writeLn('');
            $this->drawAscii();
        }

        $summaryText = "\n{$this->specCount} $specs, $failedCount $failures" .
            $incomplete . $pending;

        // Generate the summary based on whether or not it passed
        if ($failedCount) {
            $summary = $this->formatter->red($summaryText);
        } else {
            $summary = $this->formatter->green($summaryText);
        }

        $summary = $this->formatter->bold($summary);
        $this->console->writeLn($summary);
    }
}