<?php
namespace Fruitware\RobokassaBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Fruitware\RobokassaBundle\Client\Client;

class RobokassaPlugin extends AbstractPlugin
{
    const
        STATUS_NOT_FOUND  = 1,
        STATUS_PENDING    = 5,
        STATUS_CANCELLED  = 10,
        STATUS_PROCESSING = 50,
        STATUS_REFUND     = 60,
        STATUS_PAUSE      = 80,
        STATUS_COMPLETED  = 100;

    private $statuses = [
        self::STATUS_NOT_FOUND  => 'NotFound',
        self::STATUS_PENDING    => 'Pending',
        self::STATUS_CANCELLED  => 'Cancelled',
        self::STATUS_PROCESSING => 'Processing',
        self::STATUS_REFUND     => 'Refund',
        self::STATUS_PAUSE      => 'Pause',
        self::STATUS_COMPLETED  => 'Completed',
    ];

    /** @var  Client */
    private $client;

    public function __construct(Client $client)
    {
        parent::__construct(false);

        $this->client = $client;
    }

    function processes($paymentSystemName)
    {
        return 'robokassa' === $paymentSystemName;
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if ($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            throw $this->createRedirectActionException($transaction);
        }

        /** @var PaymentInstructionInterface $instruction */
        $instruction = $transaction->getPayment()->getPaymentInstruction();
        $stateCode = $this->client->requestOpState($instruction->getId());
        switch ($stateCode) {
            case self::STATUS_COMPLETED:
                break;

            case self::STATUS_PENDING:
                throw new PaymentPendingException('Payment is still pending');

            case self::STATUS_CANCELLED:
                $ex = new FinancialException('PaymentAction rejected.');
                $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
                $transaction->setReasonCode(PluginInterface::REASON_CODE_BLOCKED);
                $ex->setFinancialTransaction($transaction);

                throw $ex;

            case self::STATUS_REFUND:
                return $this->reverseDeposit($transaction, $retry);

            default:
                $ex = new FinancialException('Payment status unknow: ' . $stateCode);
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('Failed');
                $transaction->setReasonCode($stateCode);
                throw $ex;
        }

        $transaction->setProcessedAmount($instruction->getAmount());
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

    }

    public function createRedirectActionException(FinancialTransactionInterface $transaction)
    {
        $actionRequest = new ActionRequiredException('Redirect to pay');
        $actionRequest->setFinancialTransaction($transaction);
        $actionRequest->setAction(new VisitUrl($this->client->getRedirectUrl($transaction)));
        return $actionRequest;
    }
}