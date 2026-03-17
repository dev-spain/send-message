# send-message

Symfony CLI project for automatic order status updates in Simla (RetailCRM).

## What It Does

The script:
- requests orders from Simla with status `new`;
- filters orders scheduled for the next hour (based on custom fields);
- changes matching orders to status `send-message`.

Main entry point:

```bash
php bin/console run
```

## Core Logic

- Command: `App\Command\RunCommand`
- Action: `App\Action\SendMessageAction`
- CRM service: `App\Service\CrmApiService`

`SendMessageAction` builds a target datetime for `now + 1 day` in timezone `America/Bogota`, then fetches paginated orders and updates status for each matched order.

## Configuration

The project uses `.env` and `.env.local`.

Required variables:
- `APP_ENV`
- `APP_SECRET`
- `API_URL` (Simla API URL)
- `API_KEY` (Simla API key)

Service parameters are configured in `config/services.yaml`:
- `crm.api_url` <- `%env(API_URL)%`
- `crm.api_key` <- `%env(API_KEY)%`
- `crm.send_status` (default: `send-message`)

## Logs

Monolog is configured with rotating files:
- path: `var/log/<env>.log`
- retention: `15` files

## Dependencies

Key packages:
- `retailcrm/api-client-php` `~6.0`
- `symfony/framework-bundle` `5.4.*`
- `symfony/console` `5.4.*`
- `symfony/monolog-bundle` `^3.7`

## Quick Start

```bash
composer install
php bin/console cache:clear
php bin/console run
```
