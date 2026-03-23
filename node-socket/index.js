const express = require('express');
const http = require('http');
const cors = require('cors');
const { Server } = require('socket.io');
const { Telegraf } = require('telegraf');

const app = express();
app.use(cors());
app.use(express.json({ limit: '1mb' }));

const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

const NODE_SOCKET_TOKEN = process.env.NODE_SOCKET_TOKEN || 'secret-token';
const TELEGRAM_BOT_TOKEN = process.env.TELEGRAM_BOT_TOKEN;
const TELEGRAM_CHAT_ID = process.env.TELEGRAM_CHAT_ID;
const LARAVEL_BASE_URL = process.env.LARAVEL_BASE_URL || 'http://localhost:8000';

let bot = null;
if (TELEGRAM_BOT_TOKEN) {
  bot = new Telegraf(TELEGRAM_BOT_TOKEN);

  bot.start((ctx) => {
    ctx.reply('Привет! Я бот для водителей Aqua Taxi. Используйте /attach <код> для привязки аккаунта.');
  });

  bot.command('attach', async (ctx) => {
    const code = ctx.message.text.split(' ')[1];
    if (!code) {
      return ctx.reply('Укажите код: /attach ABC123');
    }

    try {
      const response = await fetch(`${LARAVEL_BASE_URL}/api/webhooks/telegram/attach`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Node-Socket-Token': NODE_SOCKET_TOKEN,
        },
        body: JSON.stringify({
          code: code,
          telegram_id: ctx.chat.id.toString(),
        }),
      });

      const result = await response.json();
      if (response.ok) {
        ctx.reply('Аккаунт успешно привязан! Теперь вы будете получать уведомления о новых заказах.');
      } else {
        ctx.reply(`Ошибка: ${result.error}`);
      }
    } catch (err) {
      console.error('Attach error', err);
      ctx.reply('Произошла ошибка при привязке.');
    }
  });

  bot.action(/accept_(\d+)/, async (ctx) => {
    const orderId = ctx.match[1];
    try {
      const response = await fetch(`${LARAVEL_BASE_URL}/api/webhooks/telegram/accept-order`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Node-Socket-Token': NODE_SOCKET_TOKEN,
        },
        body: JSON.stringify({
          telegram_id: ctx.chat.id.toString(),
          order_id: orderId,
          action: 'accept',
        }),
      });

      const result = await response.json();
      if (response.ok) {
        ctx.editMessageText('Заказ принят!', { reply_markup: { inline_keyboard: [] } });
      } else {
        ctx.reply(`Ошибка: ${result.error}`);
      }
    } catch (err) {
      console.error('Accept error', err);
      ctx.reply('Произошла ошибка.');
    }
  });

  bot.action(/reject_(\d+)/, async (ctx) => {
    const orderId = ctx.match[1];
    try {
      await fetch(`${LARAVEL_BASE_URL}/api/webhooks/telegram/accept-order`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Node-Socket-Token': NODE_SOCKET_TOKEN,
        },
        body: JSON.stringify({
          telegram_id: ctx.chat.id.toString(),
          order_id: orderId,
          action: 'reject',
        }),
      });
      ctx.editMessageText('Заказ отклонен.', { reply_markup: { inline_keyboard: [] } });
    } catch (err) {
      console.error('Reject error', err);
    }
  });

  bot.launch().then(() => {
    console.log('Telegram bot launched');
  }).catch((err) => {
    console.error('Telegram bot launch failed', err);
    bot = null;
  });
} else {
  console.warn('Telegram bot not configured. Set TELEGRAM_BOT_TOKEN.');
}

io.on('connection', (socket) => {
  console.log('Client connected', socket.id);

  socket.on('disconnect', () => {
    console.log('Client disconnected', socket.id);
  });
});

app.get('/health', (req, res) => {
  res.json({ status: 'ok' });
});

function guardToken(req, res, next) {
  const token = req.headers['x-node-socket-token'] || req.query.token;
  if (!token || token !== NODE_SOCKET_TOKEN) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  next();
}

function formatOrder(order) {
  if (!order) return 'empty';
  const lines = [];
  lines.push(`<b>Новый заказ №${order.id || 'N/A'}</b>`);
  if (order.user) lines.push(`Клиент: ${order.user.name || ''} (${order.user.phone || ''})`);
  lines.push(`Сумма: ${order.total_price ? order.total_price + '₴' : '—'}`);
  lines.push(`Адрес: ${order.address || `${order.latitude}, ${order.longitude}`}`);
  lines.push(`Вода: ${order.water_type || '—'}, ${order.quantity || '—'} шт`);
  lines.push(`Оплата: ${order.payment_method || '—'}`);
  lines.push(`<a href="http://localhost:8000/orders/${order.id}">Открыть заказ</a>`);
  return lines.join('\n');
}

async function notifyTelegram(order) {
  if (!bot) return;

  // Отправка в общий чат, если настроен
  if (TELEGRAM_CHAT_ID) {
    const text = formatOrder(order);
    await bot.telegram.sendMessage(TELEGRAM_CHAT_ID, text, { parse_mode: 'HTML', disable_web_page_preview: true });
  }

  // Отправка водителям с привязанным Telegram
  try {
    const driversResponse = await fetch(`${LARAVEL_BASE_URL}/api/webhooks/get-drivers-with-telegram`, {
      method: 'GET',
      headers: {
        'X-Node-Socket-Token': NODE_SOCKET_TOKEN,
      },
    });

    if (driversResponse.ok) {
      const drivers = await driversResponse.json();
      for (const driver of drivers) {
        const text = formatOrder(order);
        const keyboard = {
          inline_keyboard: [
            [{ text: 'Принять', callback_data: `accept_${order.id}` }],
            [{ text: 'Отклонить', callback_data: `reject_${order.id}` }],
          ],
        };
        await bot.telegram.sendMessage(driver.telegram_id, text, {
          parse_mode: 'HTML',
          disable_web_page_preview: true,
          reply_markup: keyboard,
        });
      }
    }
  } catch (err) {
    console.error('Notify drivers error', err);
  }
}

// Export for testing
module.exports = {
  formatOrder,
  notifyTelegram,
  createServer: () => server
};

app.post('/webhooks/new-order', guardToken, async (req, res) => {
  const order = req.body.order || req.body;

  io.emit('new-order', order);

  try {
    await notifyTelegram(order);
  } catch (err) {
    console.error('Telegram notify error', err);
  }

  res.json({ ok: true, order });
});

app.post('/webhooks/order-status', guardToken, async (req, res) => {
  const status = req.body.status;
  const order = req.body.order || req.body;

  io.emit('order-status', { order, status });

  if (order) {
    try {
      await notifyTelegram({
        ...order,
        text: `Статус: ${status}`
      });
    } catch (err) {
      // no-op
    }
  }

  res.json({ ok: true, order, status });
});

const PORT = parseInt(process.env.PORT || '3000', 10);
server.listen(PORT, () => {
  console.log(`Node socket service listening on http://localhost:${PORT}`);
});

process.on('SIGINT', () => { bot && bot.stop(); process.exit(); });
process.on('SIGTERM', () => { bot && bot.stop(); process.exit(); });
