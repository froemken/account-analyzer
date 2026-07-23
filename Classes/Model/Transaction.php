<?php

namespace StefanFroemken\AccountAnalyzer\Model;

use DateTimeImmutable;

final readonly class Transaction
{
    public function __construct(
        private DateTimeImmutable $date,
        private DateTimeImmutable $valutaDate,
        private string $recipient,
        private string $description,
        private float $amount,
        private string $currency
    ) {}

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getFormattedDate(): string
    {
        return $this->date->format('d.m.Y');
    }

    public function getValutaDate(): DateTimeImmutable
    {
        return $this->valutaDate;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
}
