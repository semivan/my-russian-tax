<?php

namespace MyRussianTax;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Добавление продаж / создание чеков прихода в МойНалог https://lknpd.nalog.ru/
 */
class MyRussianTax
{
    const API_URL = 'https://lknpd.nalog.ru/api/v1/';

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var array|null */
    private $profile;

    /** @var string|null */
    private $token;

    private $deviceInfo = [
        'sourceDeviceId' => null,
        'sourceType'     => 'WEB',
        'appVersion'     => '1.0.0',
        'metaDetails'    => [
            'userAgent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.192 Safari/537.36',
        ]
    ];

    public function __construct()
    {
        try {
            $this->deviceInfo['sourceDeviceId'] = 'd' . bin2hex(random_bytes(10));
        } catch (\Exception $e) {
            $this->deviceInfo['sourceDeviceId'] = 'd312e6923bf33e5f68729';
        }

        $this->httpClient = HttpClient::create([
            'headers' => [
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control'   => 'no-cache',
                'Content-Type'    => 'application/json',
                'Pragma'          => 'no-cache',
            ],
        ]);
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @return array
     * @throws \Throwable
     */
    private function request(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url     = self::API_URL . $endpoint;
        $options = [strtoupper($method) === 'GET' ? 'query' : 'body' => json_encode($data)];

        if ($endpoint !== 'auth/lkfl') {
            $options['headers'] = [
                'Authorization' => 'Bearer '. $this->getToken(),
            ];
        }

        try {
            return $this->httpClient->request($method, $url, $options)->toArray();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getToken(): string
    {
        if (!$this->token) {
            throw new \Exception('Пользователь не авторизован');
        }

        return $this->token;
    }

    /**
     * Авторизация пользователя
     *
     * @param string $login Логин (обычно ИНН) от личного кабинета
     * @param string $password Пароль
     * @return self
     * @throws \Throwable
     */
    public function auth(string $login, string $password): self
    {
        $response = $this->request('auth/lkfl', [
            'username'   => $login,
            'password'   => $password,
            'deviceInfo' => $this->deviceInfo,
        ]);

        if (empty($response['token']) || empty($response['profile']['inn'])) {
            throw new \Exception($response['message'] ?? 'Не получилось авторизоваться');
        }

        $this->profile = $response['profile'] ?? null;
        $this->token   = $response['token'] ?? null;

        return $this;
    }

    /**
     * Добавление "прихода"
     *
     * @param string $name Наименование товара/услуги
     * @param float $amount Стоимость за единицу
     * @param integer $quantity Количество
     * @param \DateTimeInterface|null $date Дата оплаты
     * @return string ID чека
     * @throws \Throwable
     */
    public function addIncome(string $name, float $amount, int $quantity = 1, ?\DateTimeInterface $date = null): string
    {
        $date = $date ?? new \DateTime('now');

        $response = $this->request('income', [
            'ignoreMaxTotalIncomeRestriction' => false,

            'paymentType'   => 'CASH',
            'requestTime'   => date(DATE_ATOM),
            'operationTime' => $date->format(DATE_ATOM),
            'totalAmount'   => $amount * $quantity,

            'client' => [
                'contactPhone' => null,
                'displayName'  => null,
                'incomeType'   => 'FROM_INDIVIDUAL',
                'inn'          => null,
            ],

            'services' => [[
                'name'     => $name,
                'amount'   => $amount,
                'quantity' => $quantity,
            ]],
        ]);

        $receiptId = $response['approvedReceiptUuid'] ?? null;

        if (!$receiptId) {
            throw new \Exception('Не удалось создать чек. ('. json_encode($response) .')');
        }

        return $receiptId;
    }

    /**
     * @param string $receiptId ID чека
     * @param bool $jsonUrl true - возвращает URL на JSON данные чека, false - возвращает ссылку на чек
     * @return string URL чека
     */
    public function getReceiptUrl(string $receiptId, bool $jsonUrl = false): string
    {
        return self::API_URL .'receipt/'. $this->profile['inn'] .'/'. $receiptId .'/'. ($jsonUrl ? 'json' : 'print');
    }

    /**
     * @param string $receiptId ID чека
     * @return array Информация о чеке
     */
    public function getReceiptInfo(string $receiptId): array
    {
        return json_decode(file_get_contents($this->getReceiptUrl($receiptId, true)), true);
    }
}