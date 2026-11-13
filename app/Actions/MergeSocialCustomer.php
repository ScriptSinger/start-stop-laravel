<?php

namespace App\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MergeSocialCustomer
{
    public function yandex(Customer $customer, string $password): ?Customer
    {
        return $this->merge($customer, $password, 'yandex', 'vk');
    }

    public function vk(Customer $customer, string $password): ?Customer
    {
        return $this->merge($customer, $password, 'vk', 'yandex');
    }

    private function merge(Customer $customer, string $password, string $provider, string $otherProvider): ?Customer
    {
        return DB::transaction(function () use ($customer, $password, $provider, $otherProvider): ?Customer {
            $source = Customer::query()->lockForUpdate()->find($customer->id);
            $providerId = $provider.'_id';
            $providerEmail = $provider.'_email';
            $otherId = $otherProvider.'_id';
            $otherEmail = $otherProvider.'_email';
            $destination = $source?->{$providerEmail} === null
                ? null
                : Customer::query()->where('email', $source->{$providerEmail})->lockForUpdate()->first();

            if (! $source || ! $destination || $source->email !== null || $source->{$providerId} === null
                || $destination->{$providerId} !== null || ($source->{$otherId} !== null && $destination->{$otherId} !== null)
                || ! (($destination->password && Hash::check($password, $destination->password)) || $destination->upgradeLegacyPassword($password))) {
                return null;
            }

            DB::table('orders')->where('customer_id', $source->id)->update(['customer_id' => $destination->id]);
            $source->delete();
            $destination->forceFill([
                $providerId => $source->{$providerId},
                $providerEmail => $source->{$providerEmail},
                ...($source->{$otherId} === null ? [] : [$otherId => $source->{$otherId}, $otherEmail => $source->{$otherEmail}]),
            ])->save();

            return $destination;
        });
    }
}
