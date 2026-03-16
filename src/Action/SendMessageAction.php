<?php

namespace App\Action;

use App\Service\CrmApiService;
use DateTime;
use Psr\Log\LoggerInterface;

class SendMessageAction
{
    private CrmApiService $crmApi;

    private LoggerInterface $logger;

    public function __construct(CrmApiService $crmApi, LoggerInterface $logger)
    {
        $this->crmApi = $crmApi;
        $this->logger = $logger;
    }

    public function runPlease()
    {
        $result = true;

        $plus24hDateTime = new DateTime('now + 1 day', new \DateTimeZone('America/Bogota'));
        $this->logger->debug(
            __METHOD__ . ': '
            . '$plus24hDateTime: '
            . $plus24hDateTime->format('Y-m-d H:00:00')
        );

        // Generator:
        $orders = $this->crmApi->getOrders($plus24hDateTime);

        if (!$orders) {
            return false;
        }

        foreach ($orders as $order) {
            $isSuccess = $this->sendMessage($order);

            if (!$isSuccess) {
                $result = false;
            }
        }

        return $result;
    }

    private function sendMessage($order)
    {
        $this->logger->info(__METHOD__ . ': ' . 'order: ' . $order->id);

        return $this->crmApi->setStatusToOrder($order);
    }
}
