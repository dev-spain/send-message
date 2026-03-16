<?php

namespace App\Service;

use DateTime;
use Psr\Log\LoggerInterface;
use RetailCrm\Api\Client;
use RetailCrm\Api\Factory\SimpleClientFactory;
use RetailCrm\Api\Model\Entity\Orders\Order;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use RetailCrm\Api\Interfaces\ApiExceptionInterface;
use RetailCrm\Api\Enum\ByIdentifier;
use RetailCrm\Api\Model\Filter\Orders\OrderFilter;
use RetailCrm\Api\Model\Request\Orders\OrdersRequest;
use RetailCrm\Api\Model\Request\Orders\OrdersEditRequest;

class CrmApiService
{
    private ContainerBagInterface $params;

    private Client $client;

    private LoggerInterface $logger;

    public function __construct(
        ContainerBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->params = $params;
        $this->logger = $logger;

        $apiUrl = $this->params->get('crm.api_url');
        $apiKey = $this->params->get('crm.api_key');

        $this->client = SimpleClientFactory::createClient($apiUrl, $apiKey);
    }

    public function getOrders(DateTime $date)
    {
        /*
        $minDateTimeString = $date->format('Y-m-d H:00:00');
        $maxDateTimeString = (
                new \DateTime($date->format('Y-m-d H:00:00') . ' + 1 hour - 1 minute')
            )->format('Y-m-d H:i:00');
        */

        $minTimeString = filter_var($date->format('H:00:00'), FILTER_SANITIZE_NUMBER_INT);
        $maxTimeString = filter_var((
            new \DateTime($date->format('H:00:00') . ' + 1 hour - 1 minute')
            )->format('H:i:00'), FILTER_SANITIZE_NUMBER_INT);

        $request = new OrdersRequest();
        $request->limit = 100;
        $request->page = 1;

        $request->filter = new OrderFilter();
        $request->filter->extendedStatus = ['new'];
        //$request->filter->createdAtFrom = $minDateTimeString;
        //$request->filter->createdAtTo = $maxDateTimeString;
        $request->filter->customFields = [
            'cita1' => [
                'min' => $date->format('Y-m-d'),
                'max' => $date->format('Y-m-d'),
            ],
        ];

        do {
            time_nanosleep(0, 100000000); // 10 requests per second

            try {
                $response = $this->client->orders->list($request);
            } catch (ApiExceptionInterface $exception) {
                $this->logger->error(__METHOD__ . ': ' . sprintf(
                    'Error from RetailCRM API (status code: %d): %s',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                ));

                if (count($exception->getErrorResponse()->errors) > 0) {
                    $this->logger->error(__METHOD__ . ': ' . 'Errors: ' . implode(
                        ', ',
                        $exception->getErrorResponse()->errors
                    ));
                }

                return false;
            }

            if (empty($response->orders)) {
                break;
            }

            $this->logger->info(__METHOD__ . ': ' . 'tomorrow orders count: ' . count($response->orders));

            foreach ($response->orders as $order) {
                //if ($order->createdAt->format('Y-m-d H:00:00') === $minDateTimeString) {
                if (
                    filter_var(mb_substr($order->customFields['hora'], 0, 8), FILTER_SANITIZE_NUMBER_INT) >= $minTimeString
                    && filter_var(mb_substr($order->customFields['hora'], 0, 8), FILTER_SANITIZE_NUMBER_INT) <= $maxTimeString
                ) {
                    $this->logger->debug(__METHOD__ . ': ' . 'yield order #' . $order->createdAt->format('Y-m-d H:i:s'));

                    yield $order;
                }
            }

            ++$request->page;
        } while ($response->pagination->currentPage < $response->pagination->totalPageCount);
    }

    public function setStatusToOrder($processedOrder)
    {
        $sendStatus = $this->params->get('crm.send_status');

        $order         = new Order();
        $order->status = $sendStatus;

        $request        = new OrdersEditRequest();
        $request->by    = ByIdentifier::ID;
        $request->site  = $processedOrder->site;
        $request->order = $order;

        try {
            $response = $this->client->orders->edit($processedOrder->id, $request);
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(__METHOD__ . ': ' . sprintf(
                    'Error from RetailCRM API (status code: %d): %s',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                ));

            if (count($exception->getErrorResponse()->errors) > 0) {
                $this->logger->error(__METHOD__ . ': ' . 'Errors: ' . implode(
                        ', ',
                        $exception->getErrorResponse()->errors
                    ));
            }

            return false;
        }

        $this->logger->debug(__METHOD__ . ': ' . 'order: ' . $response->order->id);

        return true;
    }
}
