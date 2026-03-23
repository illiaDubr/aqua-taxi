# Aqua Taxi - Система доставки воды

Полнофункциональная система для заказа и доставки воды с веб-сокетами, Telegram ботом и геолокацией.

## 🚀 Возможности

- **Заказ воды**: Пользователи могут заказывать воду с различными опциями (серебряная/глубокая очистка, бутылки и т.д.)
- **Водители**: Управление водителями, балансом, бутылками
- **Фабрики**: Управление производителями воды
- **Админка**: Управление пользователями, заказами, фабриками
- **WebSocket**: Реал-тайм уведомления о новых заказах
- **Telegram бот**: Привязка аккаунтов водителей, принятие заказов через бота
- **Геолокация**: Отслеживание расстояния до заказов для водителей

## 🛠 Технологии

- **Backend**: Laravel 10+ (PHP 8.1+)
- **Frontend**: Vue.js 3 + Vite (в resources/)
- **База данных**: MySQL/PostgreSQL
- **WebSocket**: Socket.io (Node.js)
- **Telegram бот**: Telegraf.js
- **Очереди**: Laravel Queue (для broadcasting)
- **API**: RESTful API с Sanctum аутентификацией

## 📋 Требования

- PHP 8.1+
- Node.js 16+
- Composer
- NPM
- MySQL/PostgreSQL
- Redis (для очередей, опционально)

## 🚀 Установка и запуск

### 1. Клонирование и установка зависимостей

```bash
git clone <repo-url>
cd aqua-taxi

# PHP зависимости
composer install

# Node.js зависимости (для фронта)
npm install

# Node.js зависимости (для WebSocket/Telegram)
cd node-socket
npm install
cd ..
```

### 2. Настройка окружения

```bash
cp .env.example .env
cp node-socket/.env.example node-socket/.env
```

Заполните `.env`:

```env
# База данных
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aqua_taxi
DB_USERNAME=root
DB_PASSWORD=

# Broadcasting (для WebSocket)
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=local
PUSHER_APP_KEY=local
PUSHER_APP_SECRET=local
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http

# Node.js сервис
NODE_SOCKET_URL=http://localhost:3000
NODE_SOCKET_TOKEN=your_secret_token_here

# Telegram бот (опционально)
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_CHAT_ID=-100123...
```

Заполните `node-socket/.env`:

```env
PORT=3000
NODE_SOCKET_TOKEN=your_secret_token_here
LARAVEL_BASE_URL=http://localhost:8000
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_CHAT_ID=-100123...
```

### 3. База данных

```bash
php artisan migrate
php artisan db:seed  # если есть seeders
```

### 4. Сборка фронта

```bash
npm run build
```

### 5. Запуск сервисов

```bash
# Laravel (основной сервер)
php artisan serve

# Очереди (для broadcasting)
php artisan queue:work

# WebSocket/Telegram сервис
cd node-socket
npm start

# Фронт (для разработки)
npm run dev
```

## 📚 API Документация

### Аутентификация

Все API запросы требуют токена в заголовке: `Authorization: Bearer {token}`

#### Пользователи

- `POST /api/user/register` - Регистрация
- `POST /api/user/login` - Вход
- `POST /api/user/logout` - Выход

#### Водители

- `POST /api/driver/register` - Регистрация водителя
- `POST /api/driver/login` - Вход водителя
- `GET /api/driver/profile` - Профиль
- `POST /api/driver/telegram/link` - Получить код для привязки Telegram
- `POST /api/driver/location` - Обновить геолокацию

#### Заказы

- `POST /api/orders` - Создать заказ (пользователь)
- `GET /api/orders/active` - Активные заказы пользователя
- `POST /api/orders/{id}/complete` - Завершить заказ
- `POST /api/orders/{id}/cancel` - Отменить заказ

#### Заказы водителей

- `GET /api/driver/orders/new` - Новые заказы (с расстоянием)
- `GET /api/driver/orders/active` - Активные заказы водителя
- `POST /api/driver/orders/{id}/accept` - Принять заказ

### WebSocket

Подключение: `ws://localhost:3000`

События:
- `new-order` - Новый заказ
- `order-status` - Изменение статуса заказа

```javascript
import io from 'socket.io-client';
const socket = io('http://localhost:3000');

socket.on('new-order', (order) => {
  console.log('Новый заказ:', order);
});

socket.on('order-status', ({order, status}) => {
  console.log('Статус заказа изменен:', status);
});
```

## 🤖 Telegram бот

### Настройка

1. Создайте бота через @BotFather
2. Получите токен и вставьте в `.env`
3. Опционально: создайте группу/канал для уведомлений

### Использование

1. **Привязка аккаунта**:
   - Водитель получает код: `POST /api/driver/telegram/link`
   - Отправляет боту: `/attach {код}`

2. **Уведомления**:
   - При новом заказе бот отправляет сообщение водителям с кнопками "Принять"/"Отклонить"
   - Нажатие кнопки автоматически принимает/отклоняет заказ

## 📍 Геолокация

Водители могут отправлять свою геолокацию:

```javascript
navigator.geolocation.getCurrentPosition((position) => {
  const { latitude, longitude } = position.coords;
  fetch('/api/driver/location', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ lat: latitude, lng: longitude })
  });
});
```

В ответе на `GET /api/driver/orders/new` добавляется поле `distance_km` - расстояние до заказа.

## 🧪 Тестирование

См. файл `TESTING_REPORT.md` для подробного отчета о тестировании.

Запуск тестов:

```bash
# Laravel тесты
php artisan test

# Node.js тесты (если есть)
cd node-socket
npm test
```

## 📁 Структура проекта

```
aqua-taxi/
├── app/                    # Laravel приложение
│   ├── Events/            # События (NewOrderCreated, OrderStatusUpdated)
│   ├── Http/Controllers/  # Контроллеры
│   ├── Models/            # Модели (User, Driver, Order, etc.)
│   └── Providers/         # Провайдеры
├── config/                # Конфигурация
├── database/
│   ├── migrations/        # Миграции БД
│   └── seeders/          # Сидеры
├── node-socket/          # Node.js сервис
│   ├── index.js          # Основной файл
│   ├── package.json      # Зависимости
│   └── .env.example      # Пример конфига
├── public/               # Публичные файлы
├── resources/            # Ресурсы (Vue.js)
├── routes/               # Маршруты API
├── storage/              # Хранилище
├── tests/                # Тесты Laravel
└── README.md             # Эта документация
```

## 🔧 Разработка

### Добавление новых фич

1. Создайте миграцию: `php artisan make:migration`
2. Обновите модель
3. Добавьте контроллер/метод
4. Добавьте route в `routes/api.php`
5. Напишите тест

### Broadcasting

Для broadcasting используется Pusher-compatible сервер. В продакшене замените на настоящий Pusher или используйте Laravel Echo Server.

## 🚀 Продакшн

- Настройте веб-сервер (Nginx/Apache)
- Настройте SSL
- Используйте Redis для очередей
- Настройте мониторинг логов
- Запустите сервисы через PM2 или Docker

## 📞 Поддержка

Если возникли вопросы, проверьте логи в `storage/logs/laravel.log` и `node-socket` консоль.

## 📄 Лицензия

MIT License

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
