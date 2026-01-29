<?php

namespace StefanFroemken\AccountAnalyzer\Service;

use StefanFroemken\AccountAnalyzer\Model\Transaction;
use DateTimeImmutable;
use RuntimeException;

class CsvParser
{
    /**
     * @return Transaction[]
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Could not read file: $filePath");
        }

        // Detect encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'ISO-8859-1');
        }

        $lines = explode("\n", $content);
        $transactions = [];
        $headerFound = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line, ';');

            if (!$headerFound) {
                if (count($row) > 0 && str_contains(strtolower($row[0]), 'buchung')) {
                    $headerFound = true;
                }
                continue;
            }

            if (count($row) < 9) {
                continue;
            }

            $dateStr = $row[0];
            $recipient = $row[2];
            $description = $row[5];
            $amountStr = $row[8];
            $currency = $row[9];

            $date = DateTimeImmutable::createFromFormat('d.m.Y', $dateStr);
            if (!$date) {
                continue;
            }

            $cleanAmount = str_replace('.', '', $amountStr);
            $cleanAmount = str_replace(',', '.', $cleanAmount);
            $amount = (float) $cleanAmount;

            $transactions[] = new Transaction(
                $date,
                $date,
                $recipient,
                $description,
                $amount,
                $currency
            );
        }

        return $transactions;
    }
}
