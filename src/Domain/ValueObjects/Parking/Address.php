<?php

namespace App\Domain\ValueObjects\Parking;

class Address
{
    private string $street;
    private string $city;
    private string $postalCode;
    private string $country;

    public function __construct(string $street, string $city, string $postalCode, string $country = 'FR')
    {
        if (empty(trim($street)) || empty(trim($city)) || empty(trim($postalCode))) {
            throw new \InvalidArgumentException('Address fields cannot be empty');
        }

        $this->street = $street;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->country = strtoupper($country);
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function __toString(): string
    {
        return $this->street . ', ' . $this->postalCode . ' ' . $this->city . ', ' . $this->country;
    }
}
