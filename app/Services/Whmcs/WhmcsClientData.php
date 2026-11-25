<?php

namespace App\Services\Whmcs;

/** A WHMCS client as returned by GetClientsDetails, mapped to the fields we care about. */
class WhmcsClientData
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $company,
        public readonly ?string $email,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $country,
        public readonly ?string $phone,
    ) {}

    public static function fromResponse(array $r): self
    {
        return new self(
            id: (int) ($r['id'] ?? $r['userid'] ?? $r['client_id'] ?? 0),
            firstName: $r['firstname'] ?? null,
            lastName: $r['lastname'] ?? null,
            company: $r['companyname'] ?? null,
            email: $r['email'] ?? null,
            address: trim(implode(', ', array_filter([$r['address1'] ?? null, $r['address2'] ?? null]))) ?: null,
            city: $r['city'] ?? null,
            country: $r['country'] ?? null,
            phone: $r['phonenumber'] ?? null,
        );
    }

    public function personName(): string
    {
        return trim(($this->firstName ?? '').' '.($this->lastName ?? '')) ?: ($this->company ?? 'WHMCS client');
    }

    /** Short label for confirmation messages, e.g. "Ali Raza — Z-Houses (#123)". */
    public function label(): string
    {
        $who = $this->personName();
        if ($this->company && $this->company !== $who) {
            $who .= ' — '.$this->company;
        }

        return $who.' (#'.$this->id.')';
    }
}
