<?php
namespace Fruitware\RobokassaBundle\Controller;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RobokassaController extends Controller
{
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function callbackAction(Request $request)
    {
        $outSum = $request->get('OutSum');
        $invoiceId = $request->get('InvId');
        $sign = $request->get('SignatureValue');
        if (!$this->get('fruitware.robokassa.client.auth')->validateResult($sign, $outSum, $invoiceId)) {
            return new Response('FAIL', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $instruction = $this->getInstruction($invoiceId);

        /** @var FinancialTransactionInterface $transaction */
        if (null === $transaction = $instruction->getPendingTransaction()) {
            return new Response('FAIL', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $this->get('payment.plugin_controller')->approveAndDeposit($transaction->getPayment()->getId(), $outSum);
        } catch (\Exception $e) {
            return new Response('FAIL', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $this->getDoctrine()->getManager()->flush();

        return new Response('OK' . $invoiceId);
    }

    /**
     * @param Request $request
     *
     * @return Response|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function successAction(Request $request)
    {
        $outSum = $request->get('OutSum');
        $invoiceId = $request->get('InvId');
        $sign = $request->get('SignatureValue');
        if (!$this->get('fruitware.robokassa.client.auth')->validateSuccess($sign, $outSum, $invoiceId)) {
            return new Response('FAIL', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $instruction = $this->getInstruction($invoiceId);
        $data = $instruction->getExtendedData();
        return $this->redirect($data->get('return_url'));
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function failAction(Request $request)
    {
        $invoiceId = $request->get('InvId');
        $instruction = $this->getInstruction($invoiceId);
        $data = $instruction->getExtendedData();
        return $this->redirect($data->get('cancel_url'));
    }

    /**
     * @param $id
     *
     * @return PaymentInstructionInterface
     * @throws \Exception
     */
    private function getInstruction($id)
    {
        $instruction = $this->getDoctrine()->getManager()->getRepository('JMSPaymentCoreBundle:PaymentInstruction')->find($id);
        if (empty($instruction)) {
            throw new \Exception('Cannot find instruction id='.$id);
        }
        return $instruction;
    }
}
