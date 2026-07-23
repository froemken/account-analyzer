<?php

namespace StefanFroemken\AccountAnalyzer\Service;

use StefanFroemken\AccountAnalyzer\Model\Transaction;
use DateTimeImmutable;
use RuntimeException;

final class CsvParser
{
    private const array DEFAULT_COLUMN_INDICES = [
        'date' => 0,
        'valutaDate' => 1,
        'recipient' => 2,
        'description' => 5,
        'amount' => 8,
        'currency' => 9,
        'firstCurrency' => 7
    ];

    /**
     * @return Transaction[]
     */
    public function parse(string $filePath): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $this->loadContent($filePath))));
        $transactions = [];
        $indices = null;

        foreach ($lines as $line) {
            $row = str_getcsv($line, ';');
            if ($indices === null) {
                $indices = $this->parseHeaderIndices($row);
            } elseif ($transaction = $this->parseRow($row, $indices)) {
                $transactions[] = $transaction;
            }
        }
        return $transactions;
    }

    private function loadContent(string $filePath): string
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("File not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Could not read file: $filePath");
        }

        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'ISO-8859-1');
        }

        return $content;
    }

    private function parseHeaderIndices(array $row): ?array
    {
        if ($row !== [] && str_contains(strtolower($row[0]), 'buchung')) {
            return $this->mapHeaders($row);
        }
        return null;
    }

    private function mapHeaders(array $row): array
    {
        $headerIndices = [];
        foreach ($row as $index => $columnName) {
            $normalized = strtolower($columnName);
            if ($this->isDateColumn($normalized)) {
                $headerIndices['date'] = $index;
            } elseif ($this->isValutaDateColumn($normalized)) {
                $headerIndices['valutaDate'] = $index;
            } elseif ($this->isRecipientColumn($normalized)) {
                $headerIndices['recipient'] = $index;
            } elseif ($this->isDescriptionColumn($normalized)) {
                $headerIndices['description'] = $index;
            } elseif ($this->isAmountColumn($normalized)) {
                $headerIndices['amount'] = $index;
            } elseif ($this->isCurrencyColumn($normalized)) {
                $headerIndices['currencies'][] = $index;
            }
        }

        return $this->resolveFinalIndices($headerIndices, self::DEFAULT_COLUMN_INDICES);
    }

    private function isDateColumn(string $normalized): bool
    {
        $isDate = str_contains($normalized, 'buchung') || str_contains($normalized, 'datum');
        return $isDate && !str_contains($normalized, 'text') && !str_contains($normalized, 'wertstellung');
    }

    private function isValutaDateColumn(string $normalized): bool
    {
        return str_contains($normalized, 'wertstellung');
    }

    private function isRecipientColumn(string $normalized): bool
    {
        return str_contains($normalized, 'auftraggeber') || str_contains($normalized, 'empf');
    }

    private function isDescriptionColumn(string $normalized): bool
    {
        return str_contains($normalized, 'verwendungszweck');
    }

    private function isAmountColumn(string $normalized): bool
    {
        return str_contains($normalized, 'betrag');
    }

    private function isCurrencyColumn(string $normalized): bool
    {
        return str_contains($normalized, 'whr')
            || str_contains($normalized, 'wahr')
            || str_contains($normalized, 'ährung');
    }

    private function resolveFinalIndices(array $headerIndices, array $defaults): array
    {
        $indices = $defaults;
        foreach (['date', 'valutaDate', 'recipient', 'description', 'amount'] as $key) {
            if (isset($headerIndices[$key])) {
                $indices[$key] = $headerIndices[$key];
            }
        }

        if (isset($headerIndices['currencies'])) {
            $indices['firstCurrency'] = $headerIndices['currencies'][0];
            $indices['currency'] = $this->findCurrencyNearAmount(
                $headerIndices['currencies'],
                $indices['amount']
            ) ?? $indices['firstCurrency'];
        }

        return $indices;
    }

    private function findCurrencyNearAmount(array $currencies, int $amountIdx): ?int
    {
        foreach ($currencies as $cIdx) {
            if ($cIdx > $amountIdx) {
                return $cIdx;
            }
        }
        return null;
    }

    private function parseRow(array $row, array $indices): ?Transaction
    {
        $required = [$indices['date'], $indices['recipient'], $indices['description'], $indices['amount']];
        if (count($row) < max($required) + 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('d.m.Y', $row[$indices['date']]);
        if (!$date) {
            return null;
        }

        $valutaStr = $row[$indices['valutaDate']] ?? null;
        $valutaDate = $valutaStr ? DateTimeImmutable::createFromFormat('d.m.Y', $valutaStr) : null;

        $amount = $this->parseAmount($row[$indices['amount']]);
        $currency = $row[$indices['currency']] ?? $row[$indices['firstCurrency']] ?? 'EUR';

        return new Transaction(
            $date,
            $valutaDate ?: $date,
            $row[$indices['recipient']],
            $row[$indices['description']],
            $amount,
            $currency
        );
    }

    private function parseAmount(string $amountStr): float
    {
        $cleanAmount = str_replace('.', '', $amountStr);
        $cleanAmount = str_replace(',', '.', $cleanAmount);
        return (float) $cleanAmount;
    }
}
