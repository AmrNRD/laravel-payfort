<?php

namespace MoeenBasra\Payfort\MerchantPage;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use MoeenBasra\Payfort\Abstracts\PaymentMethod;
use MoeenBasra\Payfort\Exceptions\IncompletePayment;

class ApplePay extends PaymentMethod
{
    public function __construct(array $config)
    {
        $this->configure($config);
    }

    /**
     * authorize the tokenized transaction
     *
     * @param array $params
     *
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     * @throws \MoeenBasra\Payfort\Exceptions\IncompletePayment
     */
    public function authorization(array $params): array
    {
        $params = array_merge([
            'command' => 'AUTHORIZATION',
            'digital_wallet' => 'APPLE_PAY',
            'access_code' => $this->access_code,
            'merchant_identifier' => $this->merchant_identifier,
            'language' => $this->language,
            'currency' => $this->currency,
        ], $params);

        // if signature is not already set
        if (!$signature = Arr::get($params, 'signature')) {
            // create signature
            $signature = $this->createSignature($params);

            // add signature in params
            $params['signature'] = $signature;
        }

        // get the validated data for authorization
        $validator = Validator::make($params, ValidationRules::applePayPurchase());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $this->client->authorizeTransaction($validator->validated());
    }

    /**
     * get the data for the merchant page
     *
     * @param array $params
     *
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function prepareTokenizationData(array $params): array
    {
        return [];
    }

    /**
     * query the transaction to check status
     *
     * @param array $params
     *
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     * @throws \MoeenBasra\Payfort\Exceptions\IncompletePayment
     * @throws \MoeenBasra\Payfort\Exceptions\PayfortException
     */
    public function checkStatus(array $params): array
    {
        $params = array_merge([
            'query_command' => 'CHECK_STATUS',
            'access_code' => $this->access_code,
            'merchant_identifier' => $this->merchant_identifier,
            'language' => $this->language,
        ], $params);

        // if signature is not already set
        if (!$signature = Arr::get($params, 'signature')) {

            // create the signature
            $signature = $this->createSignature($params);

            // add signature in the params
            $params['signature'] = $signature;
        }

        // get the validated data for authorization
        $validator = Validator::make($params, ValidationRules::checkStatus());

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $response = $this->client->checkStatus($validator->validated());

        $this->verifyResponse($response);

        return $response;
    }

    /**
     * check the transaction status
     *
     * @param array $params
     *
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     * @throws \MoeenBasra\Payfort\Exceptions\IncompletePayment
     * @throws \MoeenBasra\Payfort\Exceptions\PayfortException
     */
    public function checkTransactionStatus(array $params): array
    {
        $response = $this->checkStatus($params);

        $status = Arr::get($response, 'transaction_status');

        if ($status && !in_array($status, ['04', '14'])) {
            $message = Arr::get($response, 'transaction_message', 'Unknown transaction status');

            throw new IncompletePayment($message);
        }

        return $response;
    }

    /**
     * get inquiry command url
     *
     * @return string
     *
     * @deprecated
     */
    private function getInquiryUrl(): string
    {
        if ($this->is_sandbox) {
            return 'https://sbpaymentservices.payfort.com/FortAPI/paymentApi';
        }
        return 'https://paymentservices.payfort.com/FortAPI/paymentApi';
    }
}
