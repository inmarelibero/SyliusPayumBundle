<?php

/*
* This file is part of the Sylius package.
*
* (c) Paweł Jędrzejewski
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Sylius\Bundle\PayumBundle\Payum\Stripe\Action;

use Payum\Core\Action\PaymentAwareAction;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\SecuredCaptureRequest;
use Payum\Core\Security\SensitiveValue;
use Sylius\Bundle\PayumBundle\Payum\Request\ObtainCreditCardRequest;
use Sylius\Component\Core\Model\PaymentInterface;

class CaptureOrderUsingCreditCardAction extends PaymentAwareAction
{
    /**
     * {@inheritdoc}
     */
    public function execute($request)
    {
        /** @var $request SecuredCaptureRequest */
        if (!$this->supports($request)) {
            throw RequestNotSupportedException::createActionNotSupported($this, $request);
        }

        /** @var $payment PaymentInterface */
        $payment = $request->getModel();
        $order = $payment->getOrder();

        $details = $payment->getDetails();
        if (empty($details)) {
            $this->payment->execute($obtainCreditCardRequest = new ObtainCreditCardRequest($order));

            $creditCard = $obtainCreditCardRequest->getCreditCard();

            $details = array(
                'card' => new SensitiveValue(array(
                    'number'      => $creditCard->getNumber(),
                    'expiryMonth' => $creditCard->getExpiryMonth(),
                    'expiryYear'  => $creditCard->getExpiryYear(),
                    'cvv'         => $creditCard->getSecurityCode()
                )),
                'amount' => round($order->getTotal() / 100, 2),
                'currency' => $order->getCurrency(),
            );

            $payment->setDetails($details);
        }

        try {
            $request->setModel($details);
            $this->payment->execute($request);
            $request->setModel($order);
        } catch (\Exception $e) {
            $request->setModel($order);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request)
    {
        return
            $request instanceof SecuredCaptureRequest &&
            $request->getModel() instanceof PaymentInterface
        ;
    }
}
