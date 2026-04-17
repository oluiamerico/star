<?php
session_start();

$PASSWORD = 'portuga2026';

if (isset($_POST['password'])) {
    if ($_POST['password'] === $PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $error = "Senha incorreta.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html lang="pt-PT">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Painel Admin</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { background-color: #000; color: #fff; }
        </style>
    </head>
    <body class="flex items-center justify-center min-h-screen">
        <div class="bg-gray-900 border border-gray-800 p-8 rounded-xl w-full max-w-sm text-center">
            <h1 class="text-2xl font-bold mb-6">Acesso Restrito</h1>
            <?php if (isset($error)) echo "<p class='text-red-500 mb-4'>$error</p>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Senha" required
                    class="w-full bg-gray-800 border border-gray-700 rounded p-3 mb-4 text-white focus:outline-none focus:border-blue-500">
                <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded transition-colors">
                    Entrar
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../api/db.php';

// Limpar dados
if (isset($_POST['action']) && $_POST['action'] === 'clear_data') {
    save_data('sessions', []);
    save_data('events', []);
    save_data('leads', []);
    save_data('transactions', []);
    header("Location: index.php?cleared=1");
    exit;
}

// Analisar Dados
$sessions = get_data('sessions');
$events = get_data('events');
$leads = get_data('leads');
$transactions = get_data('transactions');

// Métricas de Funil
$funnel = [
    'index' => 0,
    'chip' => 0,
    'checkout' => 0,
    'gerados' => 0,
    'pagos' => 0,
    'valor_gerado' => 0.0,
    'valor_pago' => 0.0,
];

// Sessions and live users
$now = time();
$live_threshold = 30; // considered live if pinged in last 30s
$live_users = [];
$total_visitors = count($sessions);

foreach ($sessions as $s) {
    if ($now - $s['last_ping'] <= $live_threshold) {
        $live_users[] = $s;
    }
}

// Contar eventos base do funil a partir dos pings e events
$visited_index = [];
$visited_chip = [];
$visited_checkout = [];

foreach ($events as $e) {
    if ($e['event_type'] === 'index') $visited_index[$e['session_id']] = true;
    if ($e['event_type'] === 'chip') $visited_chip[$e['session_id']] = true;
    if ($e['event_type'] === 'checkout') $visited_checkout[$e['session_id']] = true;
    if ($e['event_type'] === 'gerou') $funnel['gerados']++;
    if ($e['event_type'] === 'pagou') $funnel['pagos']++;
}

$funnel['index'] = count($visited_index);
$funnel['chip'] = count($visited_chip);
$funnel['checkout'] = count($visited_checkout);

foreach ($transactions as $t) {
    if ($t['status'] === 'pending' || $t['status'] === 'COMPLETED') {
        $funnel['valor_gerado'] += (float) $t['amount'];
    }
    if ($t['status'] === 'COMPLETED') {
        $funnel['valor_pago'] += (float) $t['amount'];
    }
}

// Lead helper pra formatar
function get_status_badge($s) {
    if ($s === 'COMPLETED') return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-900 text-green-300">PAGO</span>';
    if ($s === 'pending') return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-900 text-yellow-300">GERADO</span>';
    return '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-700 text-gray-300">'.$s.'</span>';
}

function get_transaction_for_session($sid, $transactions) {
    foreach ($transactions as $t) {
        if ($t['session_id'] === $sid) return $t;
    }
    return null;
}

?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo Starlink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0a0a0a; color: #f3f4f6; font-family: 'Inter', sans-serif; }
        .glass-panel { background: rgba(31, 41, 55, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(75, 85, 99, 0.4); }
    </style>
</head>
<body class="p-4 sm:p-8">

    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row items-center justify-between gap-4 glass-panel p-6 rounded-xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center font-bold text-xl">🚀</div>
                <h1 class="text-3xl font-bold">Painel Analítico</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="?logout=1" class="text-gray-400 hover:text-white transition">Sair</a>
                <form method="POST" onsubmit="return confirm('Tem certeza que deseja APAGAR TODOS os dados?');">
                    <input type="hidden" name="action" value="clear_data">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold transition text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        Excluir Métricas
                    </button>
                </form>
            </div>
        </div>

        <?php if (isset($_GET['cleared'])): ?>
        <div class="bg-green-900 border border-green-700 text-green-300 px-4 py-3 rounded-lg">
            Dados apagados com sucesso!
        </div>
        <?php endif; ?>

        <!-- Métricas Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass-panel p-6 rounded-xl relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/10 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
                <h3 class="text-gray-400 text-sm font-semibold mb-1">Total de Visitantes</h3>
                <p class="text-4xl font-bold"><?= number_format($total_visitors, 0, ',', '.') ?></p>
            </div>
            
            <div class="glass-panel p-6 rounded-xl border-l-4 border-l-green-500 relative overflow-hidden">
                <div class="absolute top-4 right-4 flex h-3 w-3">
                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                  <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                </div>
                <h3 class="text-gray-400 text-sm font-semibold mb-1">Usuários ao Vivo</h3>
                <p class="text-4xl font-bold text-green-400"><?= count($live_users) ?></p>
            </div>

            <div class="glass-panel p-6 rounded-xl relative">
                <h3 class="text-gray-400 text-sm font-semibold mb-1">Vendas (Pagos)</h3>
                <p class="text-4xl font-bold"><?= $funnel['pagos'] ?></p>
                <div class="mt-2 text-xs text-gray-500">Valor Pago: <span class="text-green-400 font-bold">€ <?= number_format($funnel['valor_pago'], 2, ',', '.') ?></span></div>
            </div>

            <div class="glass-panel p-6 rounded-xl relative">
                <h3 class="text-gray-400 text-sm font-semibold mb-1">Gerados (Pendente)</h3>
                <p class="text-4xl font-bold"><?= $funnel['gerados'] ?></p>
                <div class="mt-2 text-xs text-gray-500">Valor Gerado: € <?= number_format($funnel['valor_gerado'], 2, ',', '.') ?></div>
            </div>
        </div>

        <!-- Funil -->
        <h2 class="text-xl font-bold mt-8 mb-4 border-b border-gray-800 pb-2">Funil de Acessos</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="glass-panel p-5 rounded-lg border-l-4 border-l-blue-500">
                <p class="text-sm text-gray-400">Página Principal</p>
                <p class="text-3xl font-bold mt-1"><?= $funnel['index'] ?></p>
            </div>
            <div class="glass-panel p-5 rounded-lg border-l-4 border-l-purple-500">
                <p class="text-sm text-gray-400">Escolher Chip</p>
                <p class="text-3xl font-bold mt-1"><?= $funnel['chip'] ?></p>
            </div>
            <div class="glass-panel p-5 rounded-lg border-l-4 border-l-pink-500">
                <p class="text-sm text-gray-400">Checkout</p>
                <p class="text-3xl font-bold mt-1"><?= $funnel['checkout'] ?></p>
            </div>
        </div>

        <!-- Usuários Ao Vivo -->
        <h2 class="text-xl font-bold mt-8 mb-4 border-b border-gray-800 pb-2">🟢 Usuários Ao Vivo (<span class="text-green-400"><?= count($live_users) ?></span>)</h2>
        <div class="glass-panel rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-800/50 text-gray-400">
                        <tr>
                            <th class="px-6 py-4 font-medium">IP / Localização</th>
                            <th class="px-6 py-4 font-medium">Página Atual</th>
                            <th class="px-6 py-4 font-medium">Tempo</th>
                            <th class="px-6 py-4 font-medium">Status Pagamento</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        <?php if (count($live_users) === 0): ?>
                        <tr><td colspan="4" class="text-center py-6 text-gray-500">Nenhum usuário online no momento.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($live_users as $lu): 
                            $tx = get_transaction_for_session($lu['session_id'], $transactions);
                            $loc = isset($lu['location']) ? $lu['location'] : 'Desconhecido';
                        ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium"><?= $lu['ip'] ?? 'Desconhecido' ?></div>
                                <div class="text-xs text-gray-500"><?= $loc ?></div>
                            </td>
                            <td class="px-6 py-4 text-blue-400">/<?= htmlspecialchars($lu['current_page']) ?></td>
                            <td class="px-6 py-4">Online (Visto agorinha)</td>
                            <td class="px-6 py-4">
                                <?php if ($tx): ?>
                                    <div class="flex flex-col gap-1">
                                        <div><?= get_status_badge($tx['status']) ?></div>
                                        <div class="text-xs text-gray-400">€ <?= number_format($tx['amount'], 2, ',', '.') ?></div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-600 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Clientes / Leads (Começaram a digitar os dados) -->
        <h2 class="text-xl font-bold mt-8 mb-4 border-b border-gray-800 pb-2">📋 Leads (Informações de Clientes)</h2>
        <div class="glass-panel rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-gray-800/50 text-gray-400">
                        <tr>
                            <th class="px-6 py-4 font-medium">Dados do Cliente</th>
                            <th class="px-6 py-4 font-medium">Contato</th>
                            <th class="px-6 py-4 font-medium">Data/Hora</th>
                            <th class="px-6 py-4 font-medium">Status Checkout</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        <?php 
                        // sort leads by newest
                        usort($leads, function($a, $b) { return $b['updated_at'] <=> $a['updated_at']; });
                        if (count($leads) === 0): ?>
                        <tr><td colspan="4" class="text-center py-6 text-gray-500">Nenhum dado capturado ainda.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($leads as $l): 
                            if (empty($l['name']) && empty($l['email']) && empty($l['document']) && empty($l['phone'])) continue;
                            $tx = get_transaction_for_session($l['session_id'], $transactions);
                        ?>
                        <tr class="hover:bg-white/[0.02] transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-bold text-white"><?= htmlspecialchars($l['name'] ?: 'Sem nome') ?></div>
                                <div class="text-xs text-gray-400">NIF/CPF: <?= htmlspecialchars($l['document'] ?: '---') ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div><?= htmlspecialchars($l['email'] ?: '---') ?></div>
                                <div class="text-gray-400"><?= htmlspecialchars($l['phone'] ?: '---') ?></div>
                            </td>
                            <td class="px-6 py-4 text-gray-400">
                                <?= date('d/m/Y H:i:s', $l['updated_at']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($tx): ?>
                                    <div class="flex flex-col gap-1 items-start">
                                        <?= get_status_badge($tx['status']) ?>
                                        <div class="text-xs text-gray-400">€ <?= number_format($tx['amount'], 2, ',', '.') ?></div>
                                    </div>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-700 text-gray-300">ABANDONOU</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Auto Refresh Scripts -->
    <script>
        // Check for updates every 10 seconds silently
        setInterval(() => {
            fetch(window.location.href)
            .then(res => res.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, "text/html");
                const newBody = doc.body.innerHTML;
                if (newBody && document.body.innerHTML !== newBody) {
                    // Update only if we are not interacting with forms maybe, but for pure dashboard it's fine
                    document.body.innerHTML = newBody;
                }
            });
        }, 10000);
    </script>
</body>
</html>
