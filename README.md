# Создание чеков для самозанятых в МойНалог lknpd.nalog.ru

Неофициальная обёртка для API сервиса lknpd.nalog.ru

Служит для автоматизации отправки информации о доходах самозанятых и получения информации о созданных чеках.

Переписана на PHP с [alexstep/moy-nalog](https://github.com/alexstep/moy-nalog)

## Требования
* PHP >= 7.1
* [symfony/http-client](https://github.com/symfony/http-client)


## Установка
```sh
composer require semivan/my-russian-tax
```


## Использование
```php
$client = new MyRussianTax();

// Авторизация пользователя
$client->('login', 'password');

// Добавление прихода
$receiptId = $client->addIncome('Уборка 5', 1);

// URL чека
$receiptUrl = $client->getReceiptUrl($receiptId);

// Информация о чеке
$receiptInfo = $client->getReceiptInfo($receiptId);
```
