<?php

namespace StefanFroemken\AccountAnalyzer\Service;

use StefanFroemken\AccountAnalyzer\Model\Transaction;
use DateTimeImmutable;
use IntlDateFormatter;

final class ReportService
{
    public function generateReport(array $transactions): array
    {
        $months = $this->initializeMonths();
        $total = ['income' => 0.0, 'expense' => 0.0, 'diff' => 0.0];

        foreach ($transactions as $transaction) {
            $monthNum = (int)$transaction->getDate()->format('n');
            $months[$monthNum]['transactions'][] = $transaction;

            $amount = $transaction->getAmount();
            if ($amount >= 0) {
                $months[$monthNum]['income'] += $amount;
                $total['income'] += $amount;
            } else {
                $months[$monthNum]['expense'] += $amount;
                $total['expense'] += $amount;
            }
            $months[$monthNum]['diff'] += $amount;
            $total['diff'] += $amount;
        }

        return [
            'total' => $total,
            'months' => $this->sortMonths($months)
        ];
    }

    private function initializeMonths(): array
    {
        $formatter = new IntlDateFormatter('de_DE', pattern: 'MMMM');
        $months = [];

        for ($i = 1; $i <= 12; $i++) {
            $date = DateTimeImmutable::createFromFormat('!m', (string)$i);
            $months[$i] = [
                'name' => ucfirst((string)$formatter->format($date)),
                'number' => $i,
                'income' => 0.0,
                'expense' => 0.0,
                'diff' => 0.0,
                'transactions' => []
            ];
        }
        return $months;
    }

    private function sortMonths(array $months): array
    {
        foreach ($months as $key => $month) {
            usort($month['transactions'], fn(Transaction $a, Transaction $b) => $a->getDate() <=> $b->getDate());
            $months[$key] = $month;
        }
        return $months;
    }
}
