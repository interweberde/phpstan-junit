<?php

declare (strict_types = 1);

namespace Interweberde\PHPStan\ErrorFormatter;

use DOMDocument;
use DOMElement;
use PHPStan\Analyser\Error;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\ErrorFormatter\RelativePathHelper;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Style\OutputStyle;

class JunitErrorFormatter implements ErrorFormatter
{

    public function formatErrors(AnalysisResult $analysisResult, OutputStyle $style) : int {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $returnCode = 1;

        /** @var DOMElement $testsuites */
        $testsuites = $dom->appendChild($dom->createElement('testsuites'));
        $testsuites->setAttribute('name', 'phpstan');

        if (!$analysisResult->hasErrors()) {
            $testsuites->setAttribute('tests', '1');
            $testsuites->setAttribute('failures', '0');

            $testsuite = $dom->createElement('testsuite');
            $testsuite->setAttribute('name', 'phpstan');
            $testsuites->appendChild($testsuite);

            $testcase = $dom->createElement('testcase');
            $testcase->setAttribute('name', 'phpstan');
            $testsuite->appendChild($testcase);

            $returnCode = 0;
        } else {
            $currentDirectory = $analysisResult->getCurrentDirectory();

            /** @var \PHPStan\Analyser\Error[][] $fileErrors */
            $fileErrors = [];
            foreach ($analysisResult->getFileSpecificErrors() as $fileSpecificError) {
                if (!isset($fileErrors[$fileSpecificError->getFile()])) {
                    $fileErrors[$fileSpecificError->getFile()] = [];
                }

                $fileErrors[$fileSpecificError->getFile()][] = $fileSpecificError;
            }

            $totalErrors = 0;
            foreach ($fileErrors as $file => $errors) {
                $this->handleFileErrors($dom, $testsuites, $file, $errors, false, $currentDirectory);

                $totalErrors += count($errors);
            }

            $genericErrors = $analysisResult->getNotFileSpecificErrors();
            if (count($genericErrors) > 0) {
                $this->handleFileErrors($dom, $testsuites, 'Generic Errors', $genericErrors, true);
            }

            $totalErrors += count($genericErrors);

            $testsuites->setAttribute('name', 'phpstan');
            $testsuites->setAttribute('tests', (string)$totalErrors);
            $testsuites->setAttribute('failures', (string)$totalErrors);
        }

        $style->write($style->isDecorated() ? OutputFormatter::escape($dom->saveXML()) : $dom->saveXML());

        return $returnCode;
    }

    private function createTestCase(DOMDocument $dom, DOMElement $testsuite, string $reference, ? string $message) {
        $testcase = $dom->createElement('testcase');
        $testcase->setAttribute('name', $reference);
        $testcase->setAttribute('failures', '1');
        $testcase->setAttribute('errors', '0');
        $testcase->setAttribute('tests', '1');

        $failure = $dom->createElement('failure');
        $failure->setAttribute('type', 'error');
        $failure->setAttribute('message', $message);
        $testcase->appendChild($failure);

        $testsuite->appendChild($testcase);
    }

    private function handleFileErrors(DOMDocument $dom, DOMElement $testsuites, string $file, array $errors, bool $generic = false, string $currentDirectory = '') {
        $fileName = RelativePathHelper::getRelativePath($currentDirectory, $file);

        $testsuite = $dom->createElement('testsuite');
        $testsuite->setAttribute('name', $generic ? $file : $fileName);

        foreach ($errors as $error) {
            if (!$generic) {
                $this->createTestCase($dom, $testsuite, sprintf('%s:%s', $fileName, (string)$error->getLine()), $error->getMessage());
            } else {
                $this->createTestCase($dom, $testsuite, $file, $error->getMessage());
            }
        }

        $testsuite->setAttribute('errors', '0');
        $testsuite->setAttribute('failures', (string)count($errors));
        $testsuite->setAttribute('tests', (string)count($errors));

        $testsuites->appendChild($testsuite);
    }
}
