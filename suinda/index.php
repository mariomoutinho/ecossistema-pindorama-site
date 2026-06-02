<?php
// ============================================================================
// Suindá — página pública (vitrine educacional do Coletivo Pindorama).
// Reorganiza a antiga página institucional do Suindá em uma vitrine de cursos,
// trilhas e recursos. O app de repetição espaçada NÃO é a página principal:
// ele fica na área autenticada (/suinda/estudar) liberada por matrícula.
// ============================================================================
$suindaPageTitle = 'Suindá — cursos, trilhas e estudo no Coletivo Pindorama';
$suindaPageDesc  = 'O braço educacional do Coletivo Pindorama: cursos, trilhas de aprendizagem, dicas de estudo e um app de repetição espaçada para estudantes matriculados.';
$suindaActiveNav = 'home';
require __DIR__ . '/inc/header.php';
?>

<main id="conteudo">

  <!-- 3. Apresentação inicial -->
  <section class="hero">
    <div class="container hero__grid">
      <div>
        <span class="hero__eyebrow">🌱 Braço educacional do Pindorama</span>
        <h1>Aprender com calma, como a Suindá observa a noite.</h1>
        <p class="lead">
          O Suindá reúne cursos, trilhas de aprendizagem e ferramentas de estudo
          do Coletivo Pindorama — incluindo um aplicativo de revisão por
          repetição espaçada para quem está matriculado.
        </p>
        <div class="hero__cta">
          <a class="btn btn--accent btn--lg js-vamos-estudar" href="/suinda/login/">Vamos estudar</a>
          <a class="btn btn--ghost btn--lg" href="#cursos">Conhecer os cursos</a>
        </div>
      </div>
      <div class="hero__art" aria-hidden="true">
        <div>
          <div class="hero__owl">🦉</div>
          <p>Estudo guiado, no seu ritmo.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 4. Proposta educacional -->
  <section class="section section--alt" id="proposta">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">A proposta</span>
        <h2>Educação popular que cabe na sua rotina</h2>
        <p>
          Acreditamos em aprender fazendo, com acolhimento e construção coletiva.
          O Suindá organiza o conhecimento em <strong>áreas</strong>,
          <strong>trilhas</strong> e <strong>cursos</strong>, e usa repetição
          espaçada para ajudar você a fixar o que estudou — sem pressa e sem
          decoreba vazia.
        </p>
      </div>
      <div class="cards">
        <article class="card">
          <div class="card__icon" aria-hidden="true">🧭</div>
          <h3>Trilhas com sentido</h3>
          <p>Cursos em ordem lógica, para você saber por onde começar e o que vem depois.</p>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">🃏</div>
          <h3>Revisão que funciona</h3>
          <p>Baralhos de cartões revisados no momento certo, com repetição espaçada.</p>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">💛</div>
          <h3>Acolhimento</h3>
          <p>Linguagem clara e ritmo respeitoso, na identidade do Coletivo Pindorama.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- 5. Cursos em destaque -->
  <section class="section" id="cursos">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Cursos</span>
        <h2>Cursos em destaque</h2>
        <p>O conteúdo definitivo está em produção. Abaixo, exemplos do que vem por aí — os cards marcados como <strong>“Em breve”</strong> ainda estão sendo preparados.</p>
      </div>
      <div class="cards">
        <article class="card">
          <div class="card__icon" aria-hidden="true">🌿</div>
          <h3>Fundamentos de Biologia</h3>
          <p>Conceitos introdutórios de biologia para revisar com baralhos de repetição espaçada.</p>
          <div class="card__foot"><span class="badge badge--open">Disponível na demonstração</span><span class="badge badge--level">Introdutório</span></div>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">📜</div>
          <h3>História do Brasil</h3>
          <p>Marcos e processos históricos, com foco em compreensão e memória de longo prazo.</p>
          <div class="card__foot"><span class="badge badge--soon">Em breve</span></div>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">🗣️</div>
          <h3>Comunicação e escrita</h3>
          <p>Ferramentas para ler, interpretar e escrever com mais confiança.</p>
          <div class="card__foot"><span class="badge badge--soon">Em breve</span></div>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">🧮</div>
          <h3>Raciocínio e matemática básica</h3>
          <p>Do concreto ao abstrato, com exercícios e revisão espaçada.</p>
          <div class="card__foot"><span class="badge badge--soon">Em breve</span></div>
        </article>
      </div>
    </div>
  </section>

  <!-- 6. Trilhas de conhecimento -->
  <section class="section section--alt" id="trilhas">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Trilhas</span>
        <h2>Trilhas de conhecimento</h2>
        <p>Uma trilha reúne vários cursos em sequência, para um caminho de estudo coerente do início ao fim.</p>
      </div>
      <div class="cards">
        <article class="card">
          <div class="card__icon" aria-hidden="true">🌱</div>
          <h3>Trilha de Fundamentos</h3>
          <p>Para quem está começando: base de biologia, leitura e raciocínio antes de avançar.</p>
          <div class="card__foot"><span class="badge badge--open">Em construção</span></div>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">🌳</div>
          <h3>Trilha de Aprofundamento</h3>
          <p>Próximo passo depois dos fundamentos, com temas conectados entre as áreas.</p>
          <div class="card__foot"><span class="badge badge--soon">Em breve</span></div>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">🌎</div>
          <h3>Trilha Coletivo Pindorama</h3>
          <p>Saberes do ecossistema: saúde integrativa, cultura e cuidado popular.</p>
          <div class="card__foot"><span class="badge badge--soon">Em breve</span></div>
        </article>
      </div>
    </div>
  </section>

  <!-- 7. Dicas e recursos de estudo -->
  <section class="section" id="recursos">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Dicas &amp; recursos</span>
        <h2>Para estudar melhor</h2>
        <p>Pequenas práticas que fazem diferença — e ferramentas livres já disponíveis no Suindá.</p>
      </div>
      <div class="cards">
        <article class="card">
          <div class="card__icon" aria-hidden="true">⏱️</div>
          <h3>Revise no tempo certo</h3>
          <p>Revisar pouco e com frequência vence maratonas de última hora. A repetição espaçada cuida disso por você.</p>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">🗓️</div>
          <h3>Organizador de estudos</h3>
          <p>Monte sua semana, cadastre matérias e tarefas e acompanhe sua rotina.</p>
          <div class="card__foot"><a class="btn btn--ghost" href="/suinda/organizador.html">Abrir organizador</a></div>
        </article>
        <article class="card">
          <div class="card__icon" aria-hidden="true">🎯</div>
          <h3>Metas pequenas</h3>
          <p>Combine sessões curtas e objetivos claros. Constância importa mais que intensidade.</p>
        </article>
      </div>
    </div>
  </section>

  <!-- 8. Como funciona o ambiente de aprendizagem -->
  <section class="section section--alt" id="como-funciona">
    <div class="container">
      <div class="section__head">
        <span class="section__eyebrow">Como funciona</span>
        <h2>Do cadastro ao estudo</h2>
        <p>O ambiente de aprendizagem libera baralhos conforme os cursos em que você se matricula.</p>
      </div>
      <div class="steps">
        <div class="step">
          <div class="step__num">1</div>
          <h3>Entre na sua conta</h3>
          <p>Estudantes acessam com e-mail e senha em uma área protegida.</p>
        </div>
        <div class="step">
          <div class="step__num">2</div>
          <h3>Veja seus cursos</h3>
          <p>No painel aparecem os cursos e trilhas liberados para você.</p>
        </div>
        <div class="step">
          <div class="step__num">3</div>
          <h3>Estude com baralhos</h3>
          <p>Cada curso libera baralhos de cartões para revisar no app de repetição espaçada.</p>
        </div>
        <div class="step">
          <div class="step__num">4</div>
          <h3>Acompanhe o progresso</h3>
          <p>Cards novos, revisões pendentes e seu avanço por curso ficam sempre à vista.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 9. Chamada para ação principal -->
  <section class="section" id="comecar">
    <div class="container">
      <div class="cta-band">
        <h2>Pronto para começar?</h2>
        <p>Entre na sua conta de estudante para acessar seus cursos e baralhos liberados. Ainda não tem acesso? Fale com o Coletivo Pindorama.</p>
        <a class="btn btn--accent btn--lg js-vamos-estudar" href="/suinda/login/">Vamos estudar</a>
      </div>
    </div>
  </section>

</main>

<script>
  // "Vamos estudar": leva ao painel se já houver sessão; senão, ao login.
  (function () {
    var hasSession = !!localStorage.getItem("suinda_api_token");
    if (!hasSession) return;
    document.querySelectorAll(".js-vamos-estudar").forEach(function (a) {
      a.setAttribute("href", "/suinda/estudar/");
    });
  })();
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
