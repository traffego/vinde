<?php
require_once '../includes/init.php';
require_once '../includes/auth_participante.php';

// Verificar login
requer_login_participante();

$participante = obter_participante_logado();

// Sistema temporário - buscar eventos diretamente
$eventos = [];
try {
    $eventos = buscar_todos("
        SELECT 
            p.*,
            e.nome as evento_nome,
            e.slug,
            e.data_inicio,
            e.data_fim,
            e.horario_inicio,
            e.horario_fim,
            e.local,
            e.cidade,
            e.valor,
            e.imagem,
            pg.status as pagamento_status,
            p.id as participante_id,
            p.evento_id,
            p.status,
            p.checkin_timestamp,
            e.nome as nome
        FROM participantes p 
        INNER JOIN eventos e ON p.evento_id = e.id 
        LEFT JOIN pagamentos pg ON p.id = pg.participante_id 
        WHERE p.cpf = ? 
        ORDER BY e.data_inicio DESC
    ", [$participante['cpf']]);
} catch (Exception $e) {
    error_log("Erro ao buscar eventos do participante: " . $e->getMessage());
    $eventos = [];
}

// Função para formatar status
function formatar_status($status) {
    $badges = [
        'inscrito' => '<span class="status-badge status-inscrito">Inscrito</span>',
        'pago' => '<span class="status-badge status-pago">Pago</span>',
        'presente' => '<span class="status-badge status-presente">Presente</span>',
        'cancelado' => '<span class="status-badge status-cancelado">Cancelado</span>'
    ];
    
    return $badges[$status] ?? $status;
}

// Função para formatar status de pagamento
function formatar_status_pagamento($status, $valor) {
    if ($valor == 0) {
        return '<span class="status-badge status-gratuito">Gratuito</span>';
    }
    
    $badges = [
        'pendente' => '<span class="status-badge status-pendente">Pendente</span>',
        'pago' => '<span class="status-badge status-pago">Pago</span>',
        'cancelado' => '<span class="status-badge status-cancelado">Cancelado</span>',
        'estornado' => '<span class="status-badge status-estornado">Estornado</span>'
    ];
    
    return $badges[$status] ?? '<span class="status-badge status-pendente">Pendente</span>';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Eventos - Área do Participante</title>
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .participante-area {
            min-height: 100vh;
            background: #f8f9fa;
        }

        .header-participante {
            background: white;
            border-bottom: 1px solid #e1e5e9;
            padding: 16px 0;
            margin-bottom: 30px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--cor-primaria);
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: var(--cor-texto-principal);
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: var(--cor-texto-principal);
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--cor-texto-secundario);
            font-size: 16px;
        }

        .eventos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .evento-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .evento-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        .evento-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--cor-primaria), var(--cor-primaria-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
        }

        .evento-content {
            padding: 24px;
        }

        .evento-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--cor-texto-principal);
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .evento-info {
            margin-bottom: 16px;
        }

        .evento-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--cor-texto-secundario);
        }

        .evento-status {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-inscrito { background: #e3f2fd; color: #1976d2; }
        .status-pago { background: #e8f5e8; color: #2e7d32; }
        .status-presente { background: #f3e5f5; color: #7b1fa2; }
        .status-cancelado { background: #ffebee; color: #c62828; }
        .status-pendente { background: #fff3e0; color: #f57c00; }
        .status-gratuito { background: #e8f5e8; color: #2e7d32; }
        .status-estornado { background: #ffebee; color: #c62828; }

        .evento-actions {
            display: flex;
            gap: 12px;
        }

        .btn-qr {
            flex: 1;
            background: var(--cor-primaria);
            color: white;
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-qr:hover {
            background: var(--cor-primaria-dark);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: var(--cor-texto-principal);
            padding: 12px 16px;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--cor-texto-secundario);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        @media (max-width: 768px) {
            .eventos-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 16px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="participante-area">
    <header class="header-participante">
        <div class="header-content">
            <div class="header-title">Área do Participante</div>
            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars($participante['nome']) ?></div>
                    <div class="user-actions">
                        <a href="logout.php" class="btn-logout">Sair</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="main-content">
        <!-- Aviso sobre sistema temporário -->
        <div class="alert alert-warning">
            <strong>📋 Sistema Temporário:</strong> Funcionando em modo de compatibilidade até a migração do banco ser executada.
        </div>

        <div class="page-header">
            <h1 class="page-title">Meus Eventos</h1>
            <p class="page-subtitle">Aqui estão todos os eventos nos quais você está inscrito</p>
        </div>

        <?php if (empty($eventos)): ?>
            <div class="empty-state">
                <div class="empty-icon">🎫</div>
                <h3>Nenhum evento encontrado</h3>
                <p>Você ainda não está inscrito em nenhum evento.</p>
                <a href="<?= SITE_URL ?>" class="btn-secondary">Ver eventos disponíveis</a>
            </div>
        <?php else: ?>
            <div class="eventos-grid">
                <?php foreach ($eventos as $evento): ?>
                    <div class="evento-card">
                        <div class="evento-image">
                            <?php if (!empty($evento['imagem'])): ?>
                                <img src="<?= SITE_URL ?>/uploads/<?= htmlspecialchars($evento['imagem']) ?>" 
                                     alt="<?= htmlspecialchars($evento['evento_nome']) ?>"
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                🎉
                            <?php endif; ?>
                        </div>
                        
                        <div class="evento-content">
                            <h3 class="evento-title"><?= htmlspecialchars($evento['evento_nome']) ?></h3>
                            
                            <div class="evento-info">
                                <div class="evento-info-item">
                                    📅 <?= date('d/m/Y', strtotime($evento['data_inicio'])) ?>
                                    <?php if ($evento['horario_inicio']): ?>
                                        às <?= date('H:i', strtotime($evento['horario_inicio'])) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="evento-info-item">
                                    📍 <?= htmlspecialchars($evento['local']) ?>
                                </div>
                                <?php if ($evento['valor'] > 0): ?>
                                    <div class="evento-info-item">
                                        💰 R$ <?= number_format($evento['valor'], 2, ',', '.') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evento-status">
                                <?= formatar_status($evento['status']) ?>
                                <?= formatar_status_pagamento($evento['pagamento_status'] ?? 'pendente', $evento['valor']) ?>
                                <?php if ($evento['checkin_timestamp']): ?>
                                    <span class="status-badge status-presente">Check-in: <?= date('d/m/Y H:i', strtotime($evento['checkin_timestamp'])) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="evento-actions">
                                <button class="btn-qr" onclick="alert('QR Code será implementado após migração do banco')">
                                    📱 Ver QR Code
                                </button>
                                <a href="<?= SITE_URL ?>/" class="btn-secondary" target="_blank">
                                    ℹ️ Ver Eventos
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html> 