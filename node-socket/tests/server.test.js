const request = require('supertest');
const { Server } = require('socket.io');
const Client = require('socket.io-client');
const http = require('http');
const express = require('express');
const cors = require('cors');

// Mock Telegraf
jest.mock('telegraf', () => {
  return jest.fn().mockImplementation(() => ({
    start: jest.fn(),
    command: jest.fn(),
    action: jest.fn(),
    launch: jest.fn(),
    telegram: {
      sendMessage: jest.fn()
    }
  }));
});

const { createServer } = require('../index');

describe('Node Socket Service', () => {
  let server, io, clientSocket, httpServer;

  beforeAll((done) => {
    // Mock environment
    process.env.NODE_SOCKET_TOKEN = 'test-token';
    process.env.PORT = '3001';
    process.env.LARAVEL_BASE_URL = 'http://localhost:8000';

    // Create server
    const app = express();
    app.use(cors());
    app.use(express.json());

    httpServer = http.createServer(app);
    io = new Server(httpServer, {
      cors: {
        origin: '*',
        methods: ['GET', 'POST']
      }
    });

    // Mock the server creation
    const mockServer = {
      listen: jest.fn((port, callback) => {
        httpServer.listen(port, callback);
      })
    };

    // Override the createServer to use our mock
    jest.doMock('../index', () => ({
      createServer: () => mockServer
    }));

    httpServer.listen(3001, () => {
      clientSocket = Client('http://localhost:3001');
      clientSocket.on('connect', done);
    });
  });

  afterAll((done) => {
    if (clientSocket) clientSocket.close();
    if (httpServer) httpServer.close(done);
  });

  test('health endpoint returns ok', async () => {
    const response = await request(httpServer).get('/health');
    expect(response.status).toBe(200);
    expect(response.body).toEqual({ status: 'ok' });
  });

  test('webhook without token returns 401', async () => {
    const response = await request(httpServer)
      .post('/webhooks/new-order')
      .send({ order: { id: 1 } });
    expect(response.status).toBe(401);
  });

  test('webhook with invalid token returns 401', async () => {
    const response = await request(httpServer)
      .post('/webhooks/new-order')
      .set('X-Node-Socket-Token', 'invalid-token')
      .send({ order: { id: 1 } });
    expect(response.status).toBe(401);
  });

  test('webhook with valid token accepts request', async () => {
    const response = await request(httpServer)
      .post('/webhooks/new-order')
      .set('X-Node-Socket-Token', 'test-token')
      .send({ order: { id: 1, user: { name: 'Test' } } });
    expect(response.status).toBe(200);
    expect(response.body).toEqual({ ok: true, order: { id: 1, user: { name: 'Test' } } });
  });

  test('socket connection works', (done) => {
    clientSocket.on('connect', () => {
      expect(clientSocket.connected).toBe(true);
      done();
    });
  });

  test('formatOrder function works', () => {
    // Import the formatOrder function (we'll need to export it for testing)
    const { formatOrder } = require('../index');

    const order = {
      id: 123,
      user: { name: 'John Doe', phone: '+1234567890' },
      total_price: 500,
      address: '123 Main St',
      quantity: 2,
      water_type: 'silver',
      payment_method: 'cash'
    };

    const result = formatOrder(order);
    expect(result).toContain('Новый заказ №123');
    expect(result).toContain('John Doe');
    expect(result).toContain('500₴');
    expect(result).toContain('123 Main St');
  });
});