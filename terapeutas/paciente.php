<?php
// ================================
// Ficha detalhada do paciente + Histórico de atendimentos (Evoluções).
// Tudo restrito ao terapeuta dono (validação no backend).
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

$terapId = (int)$terapeutaLogado['id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$paciente = pac_find_do_terapeuta($id, $terapId);
if (!$paciente) {
  flash_set('error', 'Paciente não encontrado.');
  header('Location: pacientes.php');
  exit;
}

$flash = flash_get();
$erros = [];

// Campos aceitos numa evolução (mapeados à spec §6).
$evolVazia = [
  'data' => date('Y-m-d'), 'hora' => '', 'tipo_atendimento' => '',
  'demandas' => '', 'praticas' => '', 'descricao' => '',
  'percepcao' => '', 'encaminhamentos' => '', 'acompanhamento' => '',
];
$evolForm = $evolVazia;
$evolEditId = 0;
$abrirEvolForm = false;

// ----------- POST -----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página.';
  } else {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'inativar' || $acao === 'reativar') {
      store_update('pacientes', $id, ['status' => $acao === 'inativar' ? 'inativo' : 'ativo']);
      flash_set('success', $acao === 'inativar' ? 'Paciente inativado. Histórico preservado.' : 'Paciente reativado.');
      header('Location: paciente.php?id=' . $id);
      exit;
    }

    if ($acao === 'evolucao_inativar') {
      $evid = (int)($_POST['evolucao_id'] ?? 0);
      $ev = store_find('evolucoes', $evid);
      // Só o dono e só se a evolução for deste paciente.
      if ($ev && (int)($ev['terapeuta_id'] ?? 0) === $terapId && (int)($ev['paciente_id'] ?? 0) === $id) {
        store_update('evolucoes', $evid, ['status' => 'inativo']);
        flash_set('success', 'Evolução marcada como inativa (mantida no histórico).');
      } else {
        flash_set('error', 'Evolução não encontrada.');
      }
      header('Location: paciente.php?id=' . $id);
      exit;
    }

    if ($acao === 'evolucao_salvar') {
      $evid = (int)($_POST['evolucao_id'] ?? 0);
      foreach (array_keys($evolVazia) as $k) {
        $evolForm[$k] = trim((string)($_POST[$k] ?? ''));
      }
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $evolForm['data'])) $erros[] = 'Data do atendimento inválida.';
      if ($evolForm['descricao'] === '' && $evolForm['praticas'] === '') $erros[] = 'Descreva os procedimentos realizados na sessão.';

      if (!$erros) {
        if ($evid) {
          $ev = store_find('evolucoes', $evid);
          if (!$ev || (int)($ev['terapeuta_id'] ?? 0) !== $terapId || (int)($ev['paciente_id'] ?? 0) !== $id) {
            flash_set('error', 'Evolução não encontrada.');
            header('Location: paciente.php?id=' . $id);
            exit;
          }
          store_update('evolucoes', $evid, $evolForm);
          flash_set('success', 'Evolução atualizada.');
        } else {
          store_insert('evolucoes', array_merge($evolForm, [
            'terapeuta_id'   => $terapId,
            'paciente_id'    => $id,
            'paciente'       => pac_nome_exibicao($paciente),
            'atendimento_id' => 0,
            'status'         => 'ativo',
          ]));
          flash_set('success', 'Evolução registrada.');
        }
        header('Location: paciente.php?id=' . $id);
        exit;
      }
      $abrirEvolForm = true;
      $evolEditId = $evid;
    }
  }
}

// Pré-carrega evolução para edição (GET)
if (isset($_GET['evol_editar'])) {
  $evid = (int)$_GET['evol_editar'];
  $ev = store_find('evolucoes', $evid);
  if ($ev && (int)($ev['terapeuta_id'] ?? 0) === $terapId && (int)($ev['paciente_id'] ?? 0) === $id) {
    foreach (array_keys($evolVazia) as $k) $evolForm[$k] = $ev[$k] ?? '';
    $evolEditId = $evid;
    $abrirEvolForm = true;
  }
}
if (isset($_GET['nova_evol'])) $abrirEvolForm = true;

// ----------- Evoluções do paciente -----------
$evolucoes = store_where('evolucoes', fn($r) =>
  (int)($r['paciente_id'] ?? 0) === $id && (int)($r['terapeuta_id'] ?? 0) === $terapId);
usort($evolucoes, fn($a, $b) => strcmp(
  ($b['data'] ?? '') . ($b['criado_em'] ?? ''),
  ($a['data'] ?? '') . ($a['criado_em'] ?? '')
));

// Agendamentos vinculados a este paciente (do terapeuta logado)
$agendamentos = store_where('agendamentos', fn($r) => (int)($r['paciente_id'] ?? 0) === $id);
usort($agendamentos, fn($a, $b) => strcmp(($b['data'] ?? ''), ($a['data'] ?? '')));

$nome   = pac_nome_exibicao($paciente);
$idade  = pac_idade($paciente['data_nascimento'] ?? null);
$inativo= ($paciente['status'] ?? 'ativo') === 'inativo';

$pageTitle = $nome . ' • Pacientes • Espaço Pindorama';
$activeApp = 'pacientes';
$csrf = auth_csrf_token();
require __DIR__ . '/partials/header.php';

// Helper de exibição read-only
function ficha_row(string $label, $valor): string {
  $valor = trim((string)$valor);
  if ($valor === '') return '';
  return '<div class="ficha-row"><dt>' . htmlspecialchars($label) . '</dt><dd>' . nl2br(htmlspecialchars($valor)) . '</dd></div>';
}
$sexoLabels = pac_opcoes_sexo();
$usoLabels  = pac_opcoes_uso();
?>

<div class="terap-page-head">
  <div>
    <h1><?= htmlspecialchars($nome) ?> <?php if ($inativo): ?><em class="pac-badge pac-badge--off">Inativo</em><?php endif; ?></h1>
    <p>
      <?php if ($idade !== null): ?><?= $idade ?> anos · <?php endif; ?>
      Cadastrado em <?= htmlspecialchars(!empty($paciente['criado_em']) ? date('d/m/Y', strtotime($paciente['criado_em'])) : '—') ?>
      <?php if (!empty($paciente['atualizado_em'])): ?> · Atualizado em <?= htmlspecialchars(date('d/m/Y', strtotime($paciente['atualizado_em']))) ?><?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a class="terap-btn terap-btn--primary" href="agenda.php?novo=1&paciente_id=<?= (int)$paciente['id'] ?>">+ Novo agendamento</a>
    <a class="terap-btn" href="paciente-form.php?id=<?= (int)$paciente['id'] ?>">Editar ficha</a>
    <a class="terap-btn terap-btn--ghost" href="pacientes.php">← Pacientes</a>
  </div>
</div>

<?php if ($flash): ?>
  <div class="terap-alert terap-alert--<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>
<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div class="terap-grid">
  <!-- COLUNA FICHA -->
  <div class="terap-span-8">
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Identificação</h2>
      <dl class="ficha-list">
        <?= ficha_row('Nome completo', $paciente['nome_completo'] ?? '') ?>
        <?= ficha_row('Nome social / preferido', $paciente['nome_social'] ?? '') ?>
        <?= ficha_row('Data de nascimento', !empty($paciente['data_nascimento']) ? date('d/m/Y', strtotime($paciente['data_nascimento'])) . ($idade !== null ? " ($idade anos)" : '') : '') ?>
        <?= ficha_row('Sexo ao nascer', $sexoLabels[$paciente['sexo_nascimento'] ?? ''] ?? '') ?>
        <?= ficha_row('Identidade de gênero', $paciente['identidade_genero'] ?? '') ?>
        <?= ficha_row('Pronomes', $paciente['pronomes'] ?? '') ?>
        <?= ficha_row('CPF', $paciente['cpf'] ?? '') ?>
        <?= ficha_row('Profissão / ocupação', $paciente['ocupacao'] ?? '') ?>
      </dl>
    </section>

    <?php
    $temContato = trim(($paciente['email'] ?? '') . ($paciente['telefone'] ?? '') . ($paciente['whatsapp'] ?? '') . ($paciente['telefone_alt'] ?? '') . ($paciente['contato_emerg_nome'] ?? '') . ($paciente['endereco'] ?? '')) !== '';
    if ($temContato): ?>
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Contatos</h2>
      <dl class="ficha-list">
        <?= ficha_row('E-mail', $paciente['email'] ?? '') ?>
        <?= ficha_row('Telefone principal', $paciente['telefone'] ?? '') ?>
        <?= ficha_row('WhatsApp', $paciente['whatsapp'] ?? '') ?>
        <?= ficha_row('Telefone alternativo', $paciente['telefone_alt'] ?? '') ?>
        <?= ficha_row('Contato de emergência', trim(($paciente['contato_emerg_nome'] ?? '') . (!empty($paciente['contato_emerg_relacao']) ? ' (' . $paciente['contato_emerg_relacao'] . ')' : '') . (!empty($paciente['contato_emerg_telefone']) ? ' — ' . $paciente['contato_emerg_telefone'] : ''))) ?>
        <?= ficha_row('Endereço', $paciente['endereco'] ?? '') ?>
      </dl>
    </section>
    <?php endif; ?>

    <?php
    $temQueixa = trim(($paciente['queixa_principal'] ?? '') . ($paciente['motivo_procura'] ?? '') . ($paciente['historico_queixa'] ?? '') . ($paciente['objetivos'] ?? '') . ($paciente['observacoes_gerais'] ?? '') . ($paciente['outras_informacoes'] ?? '')) !== '';
    if ($temQueixa): ?>
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Queixa e situação clínica</h2>
      <dl class="ficha-list">
        <?= ficha_row('Queixa principal', $paciente['queixa_principal'] ?? '') ?>
        <?= ficha_row('Motivo da procura', $paciente['motivo_procura'] ?? '') ?>
        <?= ficha_row('Histórico da queixa', $paciente['historico_queixa'] ?? '') ?>
        <?= ficha_row('Objetivos do paciente', $paciente['objetivos'] ?? '') ?>
        <?= ficha_row('Observações gerais', $paciente['observacoes_gerais'] ?? '') ?>
        <?= ficha_row('Outras informações', $paciente['outras_informacoes'] ?? '') ?>
      </dl>
    </section>
    <?php endif; ?>

    <?php if (!empty($paciente['condicoes'])): ?>
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Condições de saúde</h2>
      <div class="ficha-chips">
        <?php foreach ($paciente['condicoes'] as $c): ?>
          <span class="ficha-chip">
            <?= htmlspecialchars($c['label'] ?? $c['key'] ?? '') ?>
            <?php if (!empty($c['details'])): ?><em>— <?= htmlspecialchars($c['details']) ?></em><?php endif; ?>
          </span>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php
    $temMed = !empty($paciente['medicamentos']) || trim(($paciente['tratamentos_andamento'] ?? '') . ($paciente['profissionais_acompanham'] ?? '') . ($paciente['medicamentos_obs'] ?? '')) !== '';
    if ($temMed): ?>
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Medicamentos e tratamentos</h2>
      <?php if (!empty($paciente['medicamentos'])): ?>
        <ul class="ficha-meds">
          <?php foreach ($paciente['medicamentos'] as $m): ?>
            <li>
              <strong><?= htmlspecialchars($m['nome'] ?? '') ?></strong>
              <?php $extra = array_filter([$m['dosagem'] ?? '', $m['frequencia'] ?? '', $m['notas'] ?? '']); ?>
              <?php if ($extra): ?><span><?= htmlspecialchars(implode(' · ', $extra)) ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <dl class="ficha-list">
        <?= ficha_row('Tratamentos em andamento', $paciente['tratamentos_andamento'] ?? '') ?>
        <?= ficha_row('Profissionais que acompanham', $paciente['profissionais_acompanham'] ?? '') ?>
        <?= ficha_row('Observações', $paciente['medicamentos_obs'] ?? '') ?>
      </dl>
    </section>
    <?php endif; ?>

    <?php
    $temSM = !empty($paciente['sm_prefere_nao_informar']) || trim(($paciente['sm_historico'] ?? '') . ($paciente['sm_medicacao'] ?? '') . ($paciente['sm_observacoes'] ?? '') . ($paciente['substancias_obs'] ?? '') . ($paciente['uso_alcool'] ?? '') . ($paciente['uso_tabaco'] ?? '') . ($paciente['uso_outras_substancias'] ?? '')) !== '';
    if ($temSM): ?>
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Saúde mental e uso de substâncias</h2>
      <?php if (!empty($paciente['sm_prefere_nao_informar'])): ?>
        <p class="pac-help">O paciente preferiu não informar esta seção.</p>
      <?php endif; ?>
      <dl class="ficha-list">
        <?= ficha_row('Histórico / acompanhamento', $paciente['sm_historico'] ?? '') ?>
        <?= ficha_row('Medicação (saúde mental)', $paciente['sm_medicacao'] ?? '') ?>
        <?= ficha_row('Uso de álcool', $usoLabels[$paciente['uso_alcool'] ?? ''] ?? '') ?>
        <?= ficha_row('Uso de tabaco', $usoLabels[$paciente['uso_tabaco'] ?? ''] ?? '') ?>
        <?= ficha_row('Outras substâncias', $usoLabels[$paciente['uso_outras_substancias'] ?? ''] ?? '') ?>
        <?= ficha_row('Frequência / observações', $paciente['substancias_obs'] ?? '') ?>
        <?= ficha_row('Observações para o atendimento', $paciente['sm_observacoes'] ?? '') ?>
      </dl>
    </section>
    <?php endif; ?>

    <?php
    $temTer = trim(($paciente['ter_terapias_realizadas'] ?? '') . ($paciente['ter_experiencias'] ?? '') . ($paciente['ter_resposta'] ?? '') . ($paciente['ter_preferencias'] ?? '') . ($paciente['ter_evitar'] ?? '') . ($paciente['ter_observacoes'] ?? '')) !== '';
    if ($temTer): ?>
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Histórico terapêutico</h2>
      <dl class="ficha-list">
        <?= ficha_row('Terapias já realizadas', $paciente['ter_terapias_realizadas'] ?? '') ?>
        <?= ficha_row('Experiências anteriores', $paciente['ter_experiencias'] ?? '') ?>
        <?= ficha_row('Resposta a tratamentos', $paciente['ter_resposta'] ?? '') ?>
        <?= ficha_row('Preferências', $paciente['ter_preferencias'] ?? '') ?>
        <?= ficha_row('Técnicas a evitar', $paciente['ter_evitar'] ?? '') ?>
        <?= ficha_row('Observações', $paciente['ter_observacoes'] ?? '') ?>
      </dl>
    </section>
    <?php endif; ?>

    <section class="terap-card">
      <h2>Consentimento e registro</h2>
      <dl class="ficha-list">
        <div class="ficha-row"><dt>Consentimento</dt><dd><?= !empty($paciente['consentimento']) ? 'Confirmado' : 'Não confirmado' ?><?= !empty($paciente['consentimento_data']) ? ' em ' . htmlspecialchars(date('d/m/Y', strtotime($paciente['consentimento_data']))) : '' ?></dd></div>
        <?= ficha_row('Observações', $paciente['consentimento_obs'] ?? '') ?>
        <div class="ficha-row"><dt>Terapeuta responsável</dt><dd><?= htmlspecialchars($terapeutaLogado['nome']) ?></dd></div>
      </dl>
    </section>
  </div>

  <!-- COLUNA LATERAL: ações + agendamentos -->
  <aside class="terap-span-4">
    <section class="terap-card" style="margin-bottom:16px;">
      <h2>Ações</h2>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
        <a class="terap-btn terap-btn--primary" href="agenda.php?novo=1&paciente_id=<?= (int)$paciente['id'] ?>">+ Novo agendamento</a>
        <a class="terap-btn" href="paciente.php?id=<?= (int)$paciente['id'] ?>&nova_evol=1#evolucoes">+ Registrar evolução</a>
        <?php if (!$inativo): ?>
          <form method="post" onsubmit="return confirm('Inativar este paciente? O histórico é preservado e ele sai das listas de ativos.');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="acao" value="inativar">
            <button type="submit" class="terap-btn terap-btn--danger" style="width:100%">Inativar paciente</button>
          </form>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="acao" value="reativar">
            <button type="submit" class="terap-btn" style="width:100%">Reativar paciente</button>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="terap-card">
      <h2>Agendamentos</h2>
      <?php if (!$agendamentos): ?>
        <p class="pac-help">Nenhum agendamento vinculado ainda.</p>
      <?php else: ?>
        <div class="terap-feed">
          <?php foreach (array_slice($agendamentos, 0, 6) as $a): ?>
            <div class="terap-feed__item">
              <div class="terap-feed__icon"><?= htmlspecialchars(date('d', strtotime($a['data']))) ?></div>
              <div class="terap-feed__body">
                <strong><?= htmlspecialchars(date('d/m/Y', strtotime($a['data']))) ?> · <?= htmlspecialchars(substr($a['hora_inicio'] ?? '', 0, 5)) ?></strong>
                <p><?= htmlspecialchars($salasDisponiveis[$a['sala'] ?? ''] ?? ($a['sala'] ?? '—')) ?></p>
                <div class="terap-feed__meta"><a class="terap-link" href="agenda.php?editar=<?= (int)$a['id'] ?>">Ver na agenda</a></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </aside>
</div>

<!-- HISTÓRICO DE ATENDIMENTOS / EVOLUÇÕES -->
<section class="terap-card terap-span-12" id="evolucoes" style="margin-top:18px;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <h2 style="margin:0;">Histórico de atendimentos (<?= count($evolucoes) ?>)</h2>
    <a class="terap-btn terap-btn--sm terap-btn--primary" href="paciente.php?id=<?= (int)$paciente['id'] ?>&nova_evol=1#evol-form">+ Nova evolução</a>
  </div>

  <?php if ($abrirEvolForm): ?>
    <form method="post" class="terap-form" id="evol-form" style="margin:16px 0;border:1px solid var(--line);border-radius:14px;padding:16px;">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="acao" value="evolucao_salvar">
      <input type="hidden" name="evolucao_id" value="<?= (int)$evolEditId ?>">
      <h3 style="margin:0 0 8px;"><?= $evolEditId ? 'Editar evolução' : 'Nova evolução' ?></h3>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:150px;">
          <label for="ev_data">Data</label>
          <input id="ev_data" type="date" name="data" required value="<?= htmlspecialchars($evolForm['data']) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:120px;">
          <label for="ev_hora">Horário</label>
          <input id="ev_hora" type="time" name="hora" value="<?= htmlspecialchars($evolForm['hora']) ?>">
        </div>
        <div class="terap-field" style="flex:2;min-width:180px;">
          <label for="ev_tipo">Tipo de atendimento</label>
          <input id="ev_tipo" name="tipo_atendimento" maxlength="120" value="<?= htmlspecialchars($evolForm['tipo_atendimento']) ?>" placeholder="Massagem, escuta, auriculo…">
        </div>
      </div>
      <div class="terap-field">
        <label for="ev_demandas">Queixa relatada no dia</label>
        <textarea id="ev_demandas" name="demandas" rows="2"><?= htmlspecialchars($evolForm['demandas']) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ev_praticas">Procedimentos realizados</label>
        <textarea id="ev_praticas" name="praticas" rows="2"><?= htmlspecialchars($evolForm['praticas']) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ev_descricao">Descrição do que foi feito</label>
        <textarea id="ev_descricao" name="descricao" rows="2"><?= htmlspecialchars($evolForm['descricao']) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ev_percepcao">Resposta do paciente</label>
        <textarea id="ev_percepcao" name="percepcao" rows="2"><?= htmlspecialchars($evolForm['percepcao']) ?></textarea>
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:200px;">
          <label for="ev_encaminhamentos">Orientações fornecidas</label>
          <textarea id="ev_encaminhamentos" name="encaminhamentos" rows="2"><?= htmlspecialchars($evolForm['encaminhamentos']) ?></textarea>
        </div>
        <div class="terap-field" style="flex:1;min-width:200px;">
          <label for="ev_acompanhamento">Observações</label>
          <textarea id="ev_acompanhamento" name="acompanhamento" rows="2"><?= htmlspecialchars($evolForm['acompanhamento']) ?></textarea>
        </div>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" class="terap-btn terap-btn--primary">Salvar evolução</button>
        <a href="paciente.php?id=<?= (int)$paciente['id'] ?>#evolucoes" class="terap-btn terap-btn--ghost">Cancelar</a>
      </div>
    </form>
  <?php endif; ?>

  <?php if (!$evolucoes): ?>
    <p class="pac-help" style="margin-top:12px;">Nenhuma evolução registrada. As mais recentes aparecem primeiro.</p>
  <?php else: ?>
    <div class="terap-feed" style="margin-top:14px;">
      <?php foreach ($evolucoes as $ev):
        $evInativa = ($ev['status'] ?? 'ativo') === 'inativo';
      ?>
        <div class="terap-feed__item<?= $evInativa ? ' is-inativa' : '' ?>">
          <div class="terap-feed__icon"><?= htmlspecialchars(date('d', strtotime($ev['data'] ?? 'now'))) ?></div>
          <div class="terap-feed__body">
            <strong>
              <?= htmlspecialchars(date('d/m/Y', strtotime($ev['data'] ?? 'now'))) ?>
              <?php if (!empty($ev['hora'])): ?><span style="color:var(--muted);font-weight:400">· <?= htmlspecialchars(substr($ev['hora'], 0, 5)) ?></span><?php endif; ?>
              <?php if (!empty($ev['tipo_atendimento'])): ?><span style="color:var(--muted);font-weight:400">· <?= htmlspecialchars($ev['tipo_atendimento']) ?></span><?php endif; ?>
              <?php if ($evInativa): ?><em class="pac-badge pac-badge--off">Inativa</em><?php endif; ?>
            </strong>
            <?php if (!empty($ev['demandas'])): ?><p><strong>Queixa do dia:</strong> <?= nl2br(htmlspecialchars($ev['demandas'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['praticas'])): ?><p><strong>Procedimentos:</strong> <?= nl2br(htmlspecialchars($ev['praticas'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['descricao'])): ?><p><strong>Descrição:</strong> <?= nl2br(htmlspecialchars($ev['descricao'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['percepcao'])): ?><p><strong>Resposta do paciente:</strong> <?= nl2br(htmlspecialchars($ev['percepcao'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['encaminhamentos'])): ?><p><strong>Orientações:</strong> <?= nl2br(htmlspecialchars($ev['encaminhamentos'])) ?></p><?php endif; ?>
            <?php if (!empty($ev['acompanhamento'])): ?><p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($ev['acompanhamento'])) ?></p><?php endif; ?>
            <div class="terap-feed__meta" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
              <?php if (!$evInativa): ?>
                <a class="terap-link" href="paciente.php?id=<?= (int)$paciente['id'] ?>&evol_editar=<?= (int)$ev['id'] ?>#evol-form">Editar</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Marcar esta evolução como inativa? Ela continua no histórico, apenas sinalizada.');">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="acao" value="evolucao_inativar">
                  <input type="hidden" name="evolucao_id" value="<?= (int)$ev['id'] ?>">
                  <button type="submit" class="terap-link" style="background:none;border:none;color:var(--clay2);cursor:pointer;padding:0;text-decoration:underline;font:inherit;">Inativar</button>
                </form>
              <?php endif; ?>
              <span>Registrado em <?= htmlspecialchars(!empty($ev['criado_em']) ? date('d/m/Y', strtotime($ev['criado_em'])) : '—') ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/partials/footer.php'; ?>
