# Node socket + Telegram notifier for Aqua Taxi

## Быстрый старт

1. Установить зависимости:
   ```bash
   cd node-socket
   npm install
   ```

2. Скопировать `.env.example` в `.env` и заполнить:
   - `NODE_SOCKET_TOKEN` (должен совпадать с `NODE_SOCKET_TOKEN` в Laravel .env)
   - `LARAVEL_BASE_URL` (URL Laravel API, по умолчанию `http://localhost:8000`)
   - `TELEGRAM_BOT_TOKEN` (токен бота из @BotFather)
   - `TELEGRAM_CHAT_ID` (ID чата для общих уведомлений, опционально)

3. Запустить сервис:
   ```bash
   npm run start
   ```

4. Проверить:
   - `GET /health`
   - websocket-клиент подключается к `ws://localhost:3000` (socket.io)

## Laravel интеграция

В `config/services.php`:
```php
'nodesocket' => [
  'host' => env('NODE_SOCKET_URL', 'http://localhost:3000'),
  'token' => env('NODE_SOCKET_TOKEN', null),
],
```

В `OrderController@store` и `DriverOrderController@accept` уже добавлены webhook вызовы:
- `/webhooks/new-order` (новый заказ)
- `/webhooks/order-status` (смена статуса заказа)

## WebSocket протокол (socket.io)

- Событие `new-order`: payload заказа
- Событие `order-status`: payload `{order, status}`

## Telegram

### Привязка аккаунта водителя

1. Водитель в личном кабинете вызывает `POST /api/driver/telegram/link` (нужен токен водителя).
2. Получает код, например `ABC12345`.
3. Отправляет боту: `/attach ABC12345`
4. Бот привязывает Telegram ID к аккаунту водителя.

### Уведомления

- При новом заказе бот отправляет сообщение в `TELEGRAM_CHAT_ID` (если настроен).
- Также отправляет персональные уведомления водителям с привязанным Telegram, с кнопками "Принять"/"Отклонить".
- Нажатие кнопки вызывает webhook в Laravel для принятия/отклонения заказа.

## Геолокация водителя

- Водитель отправляет `POST /api/driver/location` с `lat`, `lng`.
- В `GET /api/driver/orders/new` добавляется поле `distance_km` (расстояние до заказа, если у водителя есть координаты).