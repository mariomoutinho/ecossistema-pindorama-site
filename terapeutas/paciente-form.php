<?php
// ================================
// Cadastro / edição da ficha do paciente.
// Organizada em seções (acordeões <details>) para não virar uma tela
// única gigante. Cada terapeuta só edita os próprios pacientes.
// ================================
require_once __DIR__ . '/bootstrap.php';
auth_require_login('login.php');

$terapId = (int)$terapeutaLogado['id'];
$editarId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$erros = [];

$registro = null;
if ($editarId) {
  $registro = pac_find_do_terapeuta($editarId, $terapId);
  if (!$registro) {
    flash_set('error', 'Paciente não encontrado.');
    header('Location: pacientes.php');
    exit;
  }
}

// Working data: defaults | registro | POST (erro)
$dados = $registro ?? [
  'status' => 'ativo',
  'condicoes' => [],
  'medicamentos' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!auth_csrf_check($_POST['csrf'] ?? null)) {
    $erros[] = 'Sessão expirada. Recarregue a página e tente novamente.';
  } else {
    $dados = pac_montar_do_post($_POST);
    $erros = pac_validar($dados);

    if (!$erros) {
      if ($editarId) {
        // Não permite trocar o dono.
        unset($dados['terapeuta_id'], $dados['id'], $dados['criado_em']);
        pac_consent_normalizar($dados, $registro);
        store_update('pacientes', $editarId, $dados);
        flash_set('success', 'Ficha do paciente atualizada.');
        header('Location: paciente.php?id=' . $editarId);
        exit;
      } else {
        $dados['terapeuta_id'] = $terapId;
        pac_consent_normalizar($dados, null);
        $novo = store_insert('pacientes', $dados);
        flash_set('success', 'Paciente cadastrado com sucesso.');
        header('Location: paciente.php?id=' . (int)$novo['id']);
        exit;
      }
    }
  }
}

/** Define a data de consentimento automaticamente quando confirmado e sem data. */
function pac_consent_normalizar(array &$dados, ?array $anterior): void {
  if (!empty($dados['consentimento'])) {
    if (($dados['consentimento_data'] ?? '') === '') {
      $dados['consentimento_data'] = $anterior['consentimento_data'] ?? date('Y-m-d');
    }
  }
}

// Helpers de leitura para o template
$g = function (string $k, $def = '') use ($dados) {
  return $dados[$k] ?? $def;
};
// Condições marcadas: key => details
$condSel = [];
foreach (($dados['condicoes'] ?? []) as $c) {
  if (!empty($c['key'])) $condSel[$c['key']] = $c['details'] ?? '';
}
$meds = $dados['medicamentos'] ?? [];
if (!$meds) $meds = [['nome' => '', 'dosagem' => '', 'frequencia' => '', 'notas' => '']];

$idadePrev = pac_idade($g('data_nascimento') ?: null);

$pageTitle = ($editarId ? 'Editar paciente' : 'Novo paciente') . ' • Espaço Pindorama';
$activeApp = 'pacientes';
$csrf = auth_csrf_token();
require __DIR__ . '/partials/header.php';
?>

<div class="terap-page-head">
  <div>
    <h1><?= $editarId ? 'Editar ficha' : 'Novo paciente' ?></h1>
    <p>Campos clínicos são opcionais — preencha o que for relevante para o cuidado. Apenas o nome completo é obrigatório.</p>
  </div>
  <a class="terap-btn terap-btn--ghost" href="<?= $editarId ? 'paciente.php?id=' . $editarId : 'pacientes.php' ?>">← Voltar</a>
</div>

<?php foreach ($erros as $e): ?>
  <div class="terap-alert terap-alert--error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<form method="post" class="terap-form pac-form" action="paciente-form.php<?= $editarId ? '?id=' . $editarId : '?novo=1' ?>">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

  <!-- SEÇÃO A — Identificação -->
  <details class="pac-section" open>
    <summary><span class="pac-section__title">Identificação</span><span class="pac-section__hint">Nome, nascimento, identidade</span></summary>
    <div class="pac-section__body">
      <div class="terap-field">
        <label for="nome_completo">Nome completo *</label>
        <input id="nome_completo" name="nome_completo" required maxlength="160" value="<?= htmlspecialchars($g('nome_completo')) ?>">
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:200px;">
          <label for="nome_social">Nome social / preferido</label>
          <input id="nome_social" name="nome_social" maxlength="160" value="<?= htmlspecialchars($g('nome_social')) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:160px;">
          <label for="data_nascimento">Data de nascimento</label>
          <input id="data_nascimento" name="data_nascimento" type="date" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($g('data_nascimento')) ?>">
          <small class="pac-help" id="idade-calc"><?= $idadePrev !== null ? 'Idade: ' . $idadePrev . ' anos' : 'A idade é calculada pela data.' ?></small>
        </div>
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:200px;">
          <label for="sexo_nascimento">Sexo registrado ao nascer</label>
          <select id="sexo_nascimento" name="sexo_nascimento">
            <option value="">—</option>
            <?php foreach (pac_opcoes_sexo() as $k => $lab): ?>
              <option value="<?= htmlspecialchars($k) ?>" <?= $g('sexo_nascimento') === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="terap-field" style="flex:1;min-width:160px;">
          <label for="identidade_genero">Identidade de gênero</label>
          <input id="identidade_genero" name="identidade_genero" maxlength="80" value="<?= htmlspecialchars($g('identidade_genero')) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:120px;">
          <label for="pronomes">Pronomes</label>
          <input id="pronomes" name="pronomes" maxlength="40" value="<?= htmlspecialchars($g('pronomes')) ?>" placeholder="ela, ele, elu…">
        </div>
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:160px;">
          <label for="cpf">CPF</label>
          <input id="cpf" name="cpf" inputmode="numeric" maxlength="14" value="<?= htmlspecialchars($g('cpf')) ?>" placeholder="Apenas se necessário">
          <small class="pac-help">Opcional — registre só com justificativa operacional.</small>
        </div>
        <div class="terap-field" style="flex:1;min-width:160px;">
          <label for="ocupacao">Profissão / ocupação</label>
          <input id="ocupacao" name="ocupacao" maxlength="120" value="<?= htmlspecialchars($g('ocupacao')) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:140px;">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="ativo"   <?= $g('status', 'ativo') !== 'inativo' ? 'selected' : '' ?>>Ativo</option>
            <option value="inativo" <?= $g('status') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
      </div>
    </div>
  </details>

  <!-- SEÇÃO B — Contatos -->
  <details class="pac-section">
    <summary><span class="pac-section__title">Contatos</span><span class="pac-section__hint">Telefones, e-mail, emergência</span></summary>
    <div class="pac-section__body">
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:2;min-width:220px;">
          <label for="email">E-mail</label>
          <input id="email" name="email" type="email" maxlength="160" value="<?= htmlspecialchars($g('email')) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:150px;">
          <label for="telefone">Telefone principal</label>
          <input id="telefone" name="telefone" maxlength="30" value="<?= htmlspecialchars($g('telefone')) ?>">
        </div>
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:150px;">
          <label for="whatsapp">WhatsApp</label>
          <input id="whatsapp" name="whatsapp" maxlength="30" value="<?= htmlspecialchars($g('whatsapp')) ?>">
        </div>
        <div class="terap-field" style="flex:1;min-width:150px;">
          <label for="telefone_alt">Telefone alternativo</label>
          <input id="telefone_alt" name="telefone_alt" maxlength="30" value="<?= htmlspecialchars($g('telefone_alt')) ?>">
        </div>
      </div>
      <fieldset class="pac-subgroup">
        <legend>Contato de emergência</legend>
        <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
          <div class="terap-field" style="flex:2;min-width:200px;">
            <label for="contato_emerg_nome">Nome</label>
            <input id="contato_emerg_nome" name="contato_emerg_nome" maxlength="120" value="<?= htmlspecialchars($g('contato_emerg_nome')) ?>">
          </div>
          <div class="terap-field" style="flex:1;min-width:140px;">
            <label for="contato_emerg_relacao">Relação</label>
            <input id="contato_emerg_relacao" name="contato_emerg_relacao" maxlength="80" value="<?= htmlspecialchars($g('contato_emerg_relacao')) ?>" placeholder="mãe, parceira…">
          </div>
          <div class="terap-field" style="flex:1;min-width:140px;">
            <label for="contato_emerg_telefone">Telefone</label>
            <input id="contato_emerg_telefone" name="contato_emerg_telefone" maxlength="30" value="<?= htmlspecialchars($g('contato_emerg_telefone')) ?>">
          </div>
        </div>
      </fieldset>
      <div class="terap-field">
        <label for="endereco">Endereço</label>
        <input id="endereco" name="endereco" maxlength="200" value="<?= htmlspecialchars($g('endereco')) ?>" placeholder="Opcional — só se útil para a operação">
      </div>
    </div>
  </details>

  <!-- SEÇÃO C — Queixa e situação clínica -->
  <details class="pac-section">
    <summary><span class="pac-section__title">Queixa e situação clínica</span><span class="pac-section__hint">Motivo, histórico, objetivos</span></summary>
    <div class="pac-section__body">
      <div class="terap-field">
        <label for="queixa_principal">Queixa principal</label>
        <textarea id="queixa_principal" name="queixa_principal" rows="2"><?= htmlspecialchars($g('queixa_principal')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="motivo_procura">Motivo da procura</label>
        <textarea id="motivo_procura" name="motivo_procura" rows="2"><?= htmlspecialchars($g('motivo_procura')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="historico_queixa">Histórico da queixa atual</label>
        <textarea id="historico_queixa" name="historico_queixa" rows="2"><?= htmlspecialchars($g('historico_queixa')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="objetivos">Objetivos esperados pelo paciente</label>
        <textarea id="objetivos" name="objetivos" rows="2"><?= htmlspecialchars($g('objetivos')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="observacoes_gerais">Observações clínicas gerais</label>
        <textarea id="observacoes_gerais" name="observacoes_gerais" rows="2"><?= htmlspecialchars($g('observacoes_gerais')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="outras_informacoes">Outras informações relevantes</label>
        <textarea id="outras_informacoes" name="outras_informacoes" rows="2"><?= htmlspecialchars($g('outras_informacoes')) ?></textarea>
      </div>
    </div>
  </details>

  <!-- SEÇÃO D — Condições de saúde -->
  <details class="pac-section">
    <summary><span class="pac-section__title">Condições de saúde</span><span class="pac-section__hint">Seleção múltipla + detalhes</span></summary>
    <div class="pac-section__body">
      <p class="pac-help">Marque o que se aplica e detalhe quando necessário.</p>
      <div class="pac-cond-grid">
        <?php foreach (pac_catalogo_condicoes() as $key => $label):
          $checked = isset($condSel[$key]);
        ?>
          <div class="pac-cond <?= $checked ? 'is-on' : '' ?>">
            <label class="pac-cond__check">
              <input type="checkbox" name="condicoes[]" value="<?= htmlspecialchars($key) ?>" <?= $checked ? 'checked' : '' ?>>
              <span><?= htmlspecialchars($label) ?></span>
            </label>
            <input class="pac-cond__det" type="text" name="condicoes_detalhe[<?= htmlspecialchars($key) ?>]"
                   value="<?= htmlspecialchars($condSel[$key] ?? '') ?>" placeholder="Detalhes (opcional)">
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </details>

  <!-- SEÇÃO E — Medicamentos e tratamentos -->
  <details class="pac-section">
    <summary><span class="pac-section__title">Medicamentos e tratamentos</span><span class="pac-section__hint">Uso atual, acompanhamento</span></summary>
    <div class="pac-section__body">
      <div id="meds-wrap">
        <?php foreach ($meds as $m): ?>
          <div class="pac-med-row">
            <input type="text" name="med_nome[]" value="<?= htmlspecialchars($m['nome'] ?? '') ?>" placeholder="Medicamento">
            <input type="text" name="med_dosagem[]" value="<?= htmlspecialchars($m['dosagem'] ?? '') ?>" placeholder="Dosagem">
            <input type="text" name="med_frequencia[]" value="<?= htmlspecialchars($m['frequencia'] ?? '') ?>" placeholder="Frequência">
            <input type="text" name="med_notas[]" value="<?= htmlspecialchars($m['notas'] ?? '') ?>" placeholder="Observações">
          </div>
        <?php endforeach; ?>
      </div>
      <button type="button" class="terap-btn terap-btn--sm" id="add-med">+ Adicionar medicamento</button>

      <div class="terap-field" style="margin-top:14px;">
        <label for="tratamentos_andamento">Tratamentos em andamento</label>
        <textarea id="tratamentos_andamento" name="tratamentos_andamento" rows="2"><?= htmlspecialchars($g('tratamentos_andamento')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="profissionais_acompanham">Profissionais que acompanham</label>
        <textarea id="profissionais_acompanham" name="profissionais_acompanham" rows="2"><?= htmlspecialchars($g('profissionais_acompanham')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="medicamentos_obs">Observações adicionais</label>
        <textarea id="medicamentos_obs" name="medicamentos_obs" rows="2"><?= htmlspecialchars($g('medicamentos_obs')) ?></textarea>
      </div>
    </div>
  </details>

  <!-- SEÇÃO F — Saúde mental e uso de substâncias -->
  <details class="pac-section">
    <summary><span class="pac-section__title">Saúde mental e uso de substâncias</span><span class="pac-section__hint">Acesso restrito · sem julgamento</span></summary>
    <div class="pac-section__body">
      <p class="pac-help">Informações registradas pelo terapeuta para o cuidado. Não são usadas para diagnóstico automático.</p>
      <label class="pac-cond__check" style="margin-bottom:10px;">
        <input type="checkbox" name="sm_prefere_nao_informar" value="1" <?= !empty($g('sm_prefere_nao_informar')) ? 'checked' : '' ?>>
        <span>O paciente prefere não informar esta seção</span>
      </label>
      <div class="terap-field">
        <label for="sm_historico">Histórico / acompanhamento em saúde mental</label>
        <textarea id="sm_historico" name="sm_historico" rows="2"><?= htmlspecialchars($g('sm_historico')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="sm_medicacao">Medicação relacionada à saúde mental</label>
        <textarea id="sm_medicacao" name="sm_medicacao" rows="2"><?= htmlspecialchars($g('sm_medicacao')) ?></textarea>
      </div>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <?php foreach (['uso_alcool' => 'Uso de álcool', 'uso_tabaco' => 'Uso de tabaco', 'uso_outras_substancias' => 'Outras substâncias'] as $uk => $ul): ?>
          <div class="terap-field" style="flex:1;min-width:150px;">
            <label for="<?= $uk ?>"><?= $ul ?></label>
            <select id="<?= $uk ?>" name="<?= $uk ?>">
              <?php foreach (pac_opcoes_uso() as $k => $lab): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $g($uk) === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="terap-field">
        <label for="substancias_obs">Frequência / observações complementares</label>
        <textarea id="substancias_obs" name="substancias_obs" rows="2"><?= htmlspecialchars($g('substancias_obs')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="sm_observacoes">Observações relevantes para o atendimento</label>
        <textarea id="sm_observacoes" name="sm_observacoes" rows="2"><?= htmlspecialchars($g('sm_observacoes')) ?></textarea>
      </div>
    </div>
  </details>

  <!-- SEÇÃO G — Histórico terapêutico -->
  <details class="pac-section">
    <summary><span class="pac-section__title">Histórico terapêutico</span><span class="pac-section__hint">Experiências e preferências</span></summary>
    <div class="pac-section__body">
      <div class="terap-field">
        <label for="ter_terapias_realizadas">Terapias já realizadas</label>
        <textarea id="ter_terapias_realizadas" name="ter_terapias_realizadas" rows="2"><?= htmlspecialchars($g('ter_terapias_realizadas')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ter_experiencias">Experiências anteriores relevantes</label>
        <textarea id="ter_experiencias" name="ter_experiencias" rows="2"><?= htmlspecialchars($g('ter_experiencias')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ter_resposta">Resposta a tratamentos anteriores</label>
        <textarea id="ter_resposta" name="ter_resposta" rows="2"><?= htmlspecialchars($g('ter_resposta')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ter_preferencias">Preferências do paciente</label>
        <textarea id="ter_preferencias" name="ter_preferencias" rows="2"><?= htmlspecialchars($g('ter_preferencias')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ter_evitar">Técnicas que devem ser evitadas</label>
        <textarea id="ter_evitar" name="ter_evitar" rows="2"><?= htmlspecialchars($g('ter_evitar')) ?></textarea>
      </div>
      <div class="terap-field">
        <label for="ter_observacoes">Observações</label>
        <textarea id="ter_observacoes" name="ter_observacoes" rows="2"><?= htmlspecialchars($g('ter_observacoes')) ?></textarea>
      </div>
    </div>
  </details>

  <!-- SEÇÃO H — Consentimento -->
  <details class="pac-section">
    <summary><span class="pac-section__title">Consentimento e registro</span><span class="pac-section__hint">Autorização do paciente</span></summary>
    <div class="pac-section__body">
      <label class="pac-cond__check" style="margin-bottom:10px;">
        <input type="checkbox" name="consentimento" value="1" <?= !empty($g('consentimento')) ? 'checked' : '' ?>>
        <span>O paciente autorizou o registro das informações necessárias ao atendimento.</span>
      </label>
      <div class="terap-field--row" style="display:flex;gap:12px;flex-wrap:wrap;">
        <div class="terap-field" style="flex:1;min-width:160px;">
          <label for="consentimento_data">Data do consentimento</label>
          <input id="consentimento_data" name="consentimento_data" type="date" max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($g('consentimento_data')) ?>">
        </div>
      </div>
      <div class="terap-field">
        <label for="consentimento_obs">Observações sobre o consentimento</label>
        <textarea id="consentimento_obs" name="consentimento_obs" rows="2"><?= htmlspecialchars($g('consentimento_obs')) ?></textarea>
      </div>
      <?php if ($editarId): ?>
        <p class="pac-help">
          Ficha criada em <?= htmlspecialchars(!empty($registro['criado_em']) ? date('d/m/Y', strtotime($registro['criado_em'])) : '—') ?>.
          Terapeuta responsável: <?= htmlspecialchars($terapeutaLogado['nome']) ?>.
        </p>
      <?php endif; ?>
    </div>
  </details>

  <div class="pac-form__actions">
    <button type="submit" class="terap-btn terap-btn--primary"><?= $editarId ? 'Salvar alterações' : 'Cadastrar paciente' ?></button>
    <a href="<?= $editarId ? 'paciente.php?id=' . $editarId : 'pacientes.php' ?>" class="terap-btn terap-btn--ghost">Cancelar</a>
  </div>
</form>

<script>
(function () {
  // Idade ao vivo a partir da data de nascimento
  var dn = document.getElementById('data_nascimento');
  var out = document.getElementById('idade-calc');
  if (dn && out) {
    dn.addEventListener('change', function () {
      if (!dn.value) { out.textContent = 'A idade é calculada pela data.'; return; }
      var d = new Date(dn.value + 'T00:00:00');
      var hoje = new Date();
      if (isNaN(d) || d > hoje) { out.textContent = 'Data inválida.'; return; }
      var idade = hoje.getFullYear() - d.getFullYear();
      var m = hoje.getMonth() - d.getMonth();
      if (m < 0 || (m === 0 && hoje.getDate() < d.getDate())) idade--;
      out.textContent = 'Idade: ' + idade + ' anos';
    });
  }
  // Marca visualmente condição com checkbox
  document.querySelectorAll('.pac-cond input[type="checkbox"]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      cb.closest('.pac-cond').classList.toggle('is-on', cb.checked);
    });
  });
  // Adicionar linha de medicamento
  var add = document.getElementById('add-med');
  var wrap = document.getElementById('meds-wrap');
  if (add && wrap) {
    add.addEventListener('click', function () {
      var row = wrap.querySelector('.pac-med-row');
      var clone = row.cloneNode(true);
      clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      wrap.appendChild(clone);
    });
  }
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
