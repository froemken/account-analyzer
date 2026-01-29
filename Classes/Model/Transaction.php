<?php

namespace StefanFroemken\AccountAnalyzer\Model;

use DateTimeImmutable;

class Transaction
{
    public function __construct(
        private readonly DateTimeImmutable $date,
        private readonly DateTimeImmutable $valutaDate,
        private readonly string $recipient,
        private readonly string $description,
        private readonly float $amount,
        private readonly string $currency
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
