<?php

namespace Correios\Services\Price;

use Correios\Exceptions\ApiRequestException;
use Correios\Exceptions\MissingProductParamException;
use Correios\Includes\Traits\CepHandler;
use Correios\Includes\Product;
use Correios\Services\{
    AbstractRequest,
    Authorization\Authentication
};

class Price extends AbstractRequest
{
    use CepHandler;
    private string $requestNumber;
    private string $lotId;

    public function __construct(Authentication $authentication, string $requestNumber, string $lotId = '')
    {
        $this->requestNumber = $requestNumber;
        $this->lotId = $lotId ?? $requestNumber . 'LT';
        $this->authentication = $authentication;

        $this->setMethod('POST');
        $this->setEndpoint('preco/v1/nacional');
        $this->setEnvironment($this->authentication->getEnvironment());
    }

    private function buildBody(array $serviceCodes, array $products, array $fields): void
    {
        $productParams = [];

        foreach ($serviceCodes as $service) {
            foreach ($products as $product) {
                $productParam = [
                    "coProduto" => $service,
                    "psObjeto" => $product->getWeight(),
                    "cepOrigem" => $this->originCep,
                    "cepDestino" => $this->destinyCep,
                    "nuRequisicao" => $this->requestNumber
                ];

                $productParams[] = array_merge($fields, $this->setOptionalParams($product, $productParam));
            }
        }

        $this->setBody([
            'idLote' => $this->lotId,
            'parametrosProduto' => $productParams,
        ]);
    }

    private function setOptionalParams(Product $product, array $productParam): array
    {
        if ($product->getWidth() > 0) {
            $productParam['largura'] = $product->getWidth();
        }

        if ($product->getHeight() > 0) {
            $productParam['altura'] = $product->getHeight();
        }

        if ($product->getLength() > 0) {
            $productParam['comprimento'] = $product->getLength();
        }

        if ($product->getDiameter() > 0) {
            $productParam['diameter'] = $product->getDiameter();
        }

        if ($product->getCubicWeight() > 0) {
            $productParam['cubicWeight'] = $product->getCubicWeight();
        }

        return $productParam;
    }

    private function buildProductList(array $products): array
    {
        $productList = [];
        foreach ($products as $product) {
            if (!isset($product['weight']) || !is_numeric($product['weight'])) {
                throw new MissingProductParamException();
            }

            $product = $this->validateProductItem($product);
            $productList[] = new Product(
                $product['weight'],
                $product['width'],
                $product['height'],
                $product['length'],
                $product['diameter'],
                $product['cubicWeight']
            );
        }

        return $productList;
    }
    private function validateProductItem(array $product): array
    {
        $needed = [
            'width',
            'height',
            'length',
            'diameter',
            'cubicWeight'
        ];

        foreach ($needed as $key) {
            if (!isset($product[$key]) || !is_numeric($product[$key])) {
                $product[$key] = 0;
            }
        }

        return $product;
    }
    public function get(array $serviceCodes, array $products, string $originCep, string $destinyCep, array $fields = []): array
    {
        try {
            $this->originCep  = $this->validateCep($originCep);
            $this->destinyCep = $this->validateCep($destinyCep);

            $this->buildBody(
                $serviceCodes,
                $this->buildProductList($products),
                $fields
            );

            $this->sendRequest();
            return [
                'code' => $this->getResponseCode(),
                'data' => $this->getResponseBody(),
            ];

        } catch (ApiRequestException $e) {
            $this->errors[$e->getCode()] = $e->getMessage();
            return [];
        }
    }
}
