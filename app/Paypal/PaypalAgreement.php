<?php

declare(strict_types=1);

namespace App\Paypal;

use Exception;
use PayPal\Api\Agreement;
use PayPal\Api\Payer;
use PayPal\Api\Plan;
use PayPal\Api\ShippingAddress;

class PaypalAgreement extends Paypal
{
    public function create($id)
    {
        return redirect($this->agreement($id));
    }

    public function execute($token): void
    {
        $agreement = new Agreement;
        try {
            $agreement->execute($token, $this->apiContext);
        } catch (Exception $ex) {
            dd($ex);

        }
    }

    protected function agreement($id): string
    {
        $agreement = new Agreement;
        $agreement->setName('DayWright Agreement')
            ->setDescription('DayWright Agreement')
            ->setStartDate(gmdate("Y-m-d\TH:i:s\Z", strtotime('+1 day')));

        $agreement->setPlan($this->plan($id));

        $agreement->setPayer($this->payer());

        $agreement->setShippingAddress($this->shippingAddress());

        try {

            $agreement = $agreement->create($this->apiContext);

            return $agreement->getApprovalLink();
        } catch (Exception $ex) {
            dd($ex);
        }
    }

    protected function plan($id): Plan
    {
        $plan = new Plan;

        $plan->setId($id);

        return $plan;

    }

    protected function payer(): Payer
    {
        $payer = new Payer;

        $payer->setPaymentMethod('paypal');

        return $payer;
    }

    protected function shippingAddress(): ShippingAddress
    {
        $shippingAddress = new ShippingAddress;
        $shippingAddress->setLine1('111 First Street')
            ->setCity('Saratoga')
            ->setState('CA')
            ->setPostalCode('95070')
            ->setCountryCode('US');

        return $shippingAddress;
    }
}
