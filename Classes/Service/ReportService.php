<?php

namespace StefanFroemken\AccountAnalyzer\Service;

use StefanFroemken\AccountAnalyzer\Model\Transaction;

class ReportService
{
    public function generateReport(array $transactions): array
    {
        $total = ['income' => 0.0, 'expense' => 0.0, 'diff' => 0.0];
        $months = [];

        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = [
                'name' => $this->getMonthName($i),
                'number' => $i,
                'income' => 0.0,
                'expense' => 0.0,
                'diff' => 0.0,
                'transactions' => []
            ];
        }

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $monthNum = (int) $transaction->getDate()->format('n');

            $months[$monthNum]['transactions'][] = $transaction;

            if ($transaction->getAmount() >= 0) {
                $months[$monthNum]['income'] += $transaction->getAmount();
                $total['income'] += $transaction->getAmount();
            } else {
                $months[$monthNum]['expense'] += $transaction->getAmount();
                $total['expense'] += $transaction->getAmount();
            }
            $months[$monthNum]['diff'] += $transaction->getAmount();
            $total['diff'] += $transaction->getAmount();
        }

        foreach ($months as &$month) {
            // Sort by date ascending as requested
            usort($month['transactions'], fn(Transaction $a, Transaction $b) => $a->getDate() <=> $b->getDate());
        }
        unset($month);

        return [
            'total' => $total,
            'months' => $months
        ];
    }

    private function getMonthName(int $month): string
    {
        $names = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember'
        ];

        return $names[$month] ?? (string)$month;
    }
}
