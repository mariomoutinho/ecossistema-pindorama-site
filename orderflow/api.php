<?php

declare(strict_types=1);

const ORDER_STATUSES = ['Pending', 'Processing', 'Shipped', 'Cancelled'];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    dispatchRequest();
} catch (InvalidArgumentException $exception) {
    respond(400, ['erro' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log('OrderFlow API: ' . $exception->getMessage());
    respond(500, ['erro' => 'Não foi possível acessar os pedidos.']);
}

function dispatchRequest(): never
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $route = is_string($_GET['route'] ?? null) ? $_GET['route'] : '';

    if ($method === 'GET' && $route === 'orders') {
        $orders = withOrdersLock(static fn (): array => readOrders());
        respond(200, $orders);
    }

    if ($method === 'POST' && $route === 'orders') {
        createOrder();
    }

    if ($method === 'GET' && $route === 'order') {
        getOrder();
    }

    if ($method === 'PATCH' && $route === 'status') {
        updateOrderStatus();
    }

    if (in_array($route, ['orders', 'order', 'status'], true)) {
        header('Allow: GET, POST, PATCH');
        respond(405, ['erro' => 'Método não permitido.']);
    }

    respond(404, ['erro' => 'Rota não encontrada.']);
}

function createOrder(): never
{
    $body = readJsonBody();
    $cliente = is_string($body['cliente'] ?? null) ? trim($body['cliente']) : '';
    $valorTotal = $body['valorTotal'] ?? null;

    if ($cliente === '') {
        throw new InvalidArgumentException('Cliente é obrigatório.');
    }

    if ((!is_int($valorTotal) && !is_float($valorTotal)) || $valorTotal <= 0) {
        throw new InvalidArgumentException('Valor total deve ser maior que zero.');
    }

    $order = [
        'id' => createUuid(),
        'cliente' => $cliente,
        'valorTotal' => (float) $valorTotal,
        'status' => 'Pending',
        'criadoEm' => gmdate('Y-m-d\TH:i:s\Z'),
    ];

    withOrdersLock(static function () use ($order): void {
        $orders = readOrders();
        $orders[] = $order;
        writeOrders($orders);
    });

    header('Location: /orderflow/orders/' . $order['id']);
    respond(201, $order);
}

function getOrder(): never
{
    $id = requestOrderId();
    $order = withOrdersLock(static function () use ($id): ?array {
        foreach (readOrders() as $currentOrder) {
            if (($currentOrder['id'] ?? null) === $id) {
                return $currentOrder;
            }
        }

        return null;
    });

    if ($order === null) {
        respond(404, ['erro' => 'Pedido não encontrado.']);
    }

    respond(200, $order);
}

function updateOrderStatus(): never
{
    $id = requestOrderId();
    $body = readJsonBody();
    $requestedStatus = is_string($body['status'] ?? null) ? trim($body['status']) : '';
    $status = canonicalStatus($requestedStatus);

    if ($status === null) {
        throw new InvalidArgumentException(
            'Status inválido. Use Pending, Processing, Shipped ou Cancelled.'
        );
    }

    $updatedOrder = withOrdersLock(static function () use ($id, $status): ?array {
        $orders = readOrders();

        foreach ($orders as $index => $order) {
            if (($order['id'] ?? null) !== $id) {
                continue;
            }

            $orders[$index]['status'] = $status;
            writeOrders($orders);
            return $orders[$index];
        }

        return null;
    });

    if ($updatedOrder === null) {
        respond(404, ['erro' => 'Pedido não encontrado.']);
    }

    respond(200, $updatedOrder);
}

function readJsonBody(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        throw new InvalidArgumentException('Corpo da requisição é obrigatório.');
    }

    try {
        $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('Corpo JSON inválido.');
    }

    if (!is_array($body)) {
        throw new InvalidArgumentException('Corpo JSON inválido.');
    }

    return $body;
}

function withOrdersLock(callable $callback): mixed
{
    ensureDataDirectory();
    $lockHandle = fopen(dataDirectory() . '/orders.lock', 'c+');

    if ($lockHandle === false || !flock($lockHandle, LOCK_EX)) {
        throw new RuntimeException('Não foi possível bloquear o arquivo de pedidos.');
    }

    try {
        ensureOrdersFile();
        return $callback();
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

function readOrders(): array
{
    $contents = file_get_contents(ordersFilePath());
    if ($contents === false) {
        throw new RuntimeException('Não foi possível ler o arquivo de pedidos.');
    }

    if (trim($contents) === '') {
        throw new RuntimeException('O arquivo de pedidos está vazio. Use [] para uma lista vazia.');
    }

    try {
        $orders = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('O arquivo de pedidos contém JSON inválido.', 0, $exception);
    }

    if (!is_array($orders) || !array_is_list($orders)) {
        throw new RuntimeException('O arquivo de pedidos deve conter uma lista JSON.');
    }

    return $orders;
}

function writeOrders(array $orders): void
{
    try {
        $json = json_encode(
            $orders,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    } catch (JsonException $exception) {
        throw new RuntimeException('Não foi possível serializar os pedidos.', 0, $exception);
    }

    $temporaryPath = tempnam(dataDirectory(), 'orders-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Não foi possível criar o arquivo temporário de pedidos.');
    }

    if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
        @unlink($temporaryPath);
        throw new RuntimeException('Não foi possível salvar o arquivo temporário de pedidos.');
    }

    if (!rename($temporaryPath, ordersFilePath())) {
        @unlink($temporaryPath);
        throw new RuntimeException('Não foi possível substituir o arquivo de pedidos.');
    }
}

function ensureDataDirectory(): void
{
    $directory = dataDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível criar o diretório de pedidos.');
    }
}

function ensureOrdersFile(): void
{
    $path = ordersFilePath();
    if (!file_exists($path) && file_put_contents($path, "[]\n", LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível criar o arquivo de pedidos.');
    }
}

function dataDirectory(): string
{
    $configuredDirectory = getenv('ORDERFLOW_DATA_DIR');
    return is_string($configuredDirectory) && $configuredDirectory !== ''
        ? rtrim($configuredDirectory, DIRECTORY_SEPARATOR)
        : __DIR__ . '/data';
}

function ordersFilePath(): string
{
    return dataDirectory() . '/orders.json';
}

function requestOrderId(): string
{
    $id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
        respond(404, ['erro' => 'Pedido não encontrado.']);
    }

    return strtolower($id);
}

function canonicalStatus(string $requestedStatus): ?string
{
    foreach (ORDER_STATUSES as $status) {
        if (strcasecmp($requestedStatus, $status) === 0) {
            return $status;
        }
    }

    return null;
}

function createUuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function respond(int $statusCode, array $body): never
{
    http_response_code($statusCode);
    echo json_encode(
        $body,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}
