<?php

// ============================================================================
// Matriz de Referência do ENEM — dados oficiais (redação preservada).
// Consumido pelo importador (tools/import-enem.php) para semear a taxonomia:
// eixos cognitivos, áreas, disciplinas, competências, habilidades e conteúdos.
//
// Cobertura desta versão (lote-piloto 2024 D2 C5): competências das 4 áreas e
// habilidades completas de Ciências da Natureza e de Matemática (as usadas pelo
// piloto). Habilidades de Linguagens e de Ciências Humanas ficam como próximo
// passo — a estrutura já as suporta e o importador é idempotente (re-rodar
// preenche o que faltar). Ver docs/suinda-enem-course.md.
// ============================================================================

return [

    // --- Eixos cognitivos (comuns a todas as áreas) ---
    'cognitive_axes' => [
        ['code' => 'DL', 'name' => 'Dominar linguagens', 'description' => 'Dominar a norma culta da Língua Portuguesa e fazer uso das linguagens matemática, artística e científica e das línguas espanhola e inglesa.'],
        ['code' => 'CF', 'name' => 'Compreender fenômenos', 'description' => 'Construir e aplicar conceitos das várias áreas do conhecimento para a compreensão de fenômenos naturais, de processos histórico-geográficos, da produção tecnológica e das manifestações artísticas.'],
        ['code' => 'SP', 'name' => 'Enfrentar situações-problema', 'description' => 'Selecionar, organizar, relacionar, interpretar dados e informações representados de diferentes formas, para tomar decisões e enfrentar situações-problema.'],
        ['code' => 'CA', 'name' => 'Construir argumentação', 'description' => 'Relacionar informações, representadas em diferentes formas, e conhecimentos disponíveis em situações concretas, para construir argumentação consistente.'],
        ['code' => 'EP', 'name' => 'Elaborar propostas', 'description' => 'Recorrer aos conhecimentos desenvolvidos na escola para elaboração de propostas de intervenção solidária na realidade, respeitando os valores humanos e considerando a diversidade sociocultural.'],
    ],

    // --- Áreas (knowledge_areas) ---
    'areas' => [
        ['slug' => 'enem-linguagens', 'abbr' => 'LC', 'name' => 'Linguagens, Códigos e suas Tecnologias'],
        ['slug' => 'enem-matematica', 'abbr' => 'MT', 'name' => 'Matemática e suas Tecnologias'],
        ['slug' => 'enem-ciencias-natureza', 'abbr' => 'CN', 'name' => 'Ciências da Natureza e suas Tecnologias'],
        ['slug' => 'enem-ciencias-humanas', 'abbr' => 'CH', 'name' => 'Ciências Humanas e suas Tecnologias'],
        ['slug' => 'enem-redacao', 'abbr' => 'RD', 'name' => 'Redação'],
    ],

    // --- Disciplinas por área ---
    'disciplines' => [
        ['area' => 'enem-linguagens', 'name' => 'Língua Portuguesa'],
        ['area' => 'enem-linguagens', 'name' => 'Literatura'],
        ['area' => 'enem-linguagens', 'name' => 'Artes'],
        ['area' => 'enem-linguagens', 'name' => 'Educação Física'],
        ['area' => 'enem-linguagens', 'name' => 'Tecnologias da Comunicação'],
        ['area' => 'enem-linguagens', 'name' => 'Língua Estrangeira'],
        ['area' => 'enem-matematica', 'name' => 'Matemática'],
        ['area' => 'enem-ciencias-natureza', 'name' => 'Física'],
        ['area' => 'enem-ciencias-natureza', 'name' => 'Química'],
        ['area' => 'enem-ciencias-natureza', 'name' => 'Biologia'],
        ['area' => 'enem-ciencias-humanas', 'name' => 'História'],
        ['area' => 'enem-ciencias-humanas', 'name' => 'Geografia'],
        ['area' => 'enem-ciencias-humanas', 'name' => 'Filosofia'],
        ['area' => 'enem-ciencias-humanas', 'name' => 'Sociologia'],
        ['area' => 'enem-redacao', 'name' => 'Redação'],
    ],

    // --- Competências (todas as áreas) e habilidades (CN + MT completas) ---
    // code: <ABBR>-C<n> para competência, <ABBR>-H<n> para habilidade.
    'competencies' => [

        // ===================== Ciências da Natureza =====================
        ['area' => 'enem-ciencias-natureza', 'number' => 1, 'statement' => 'Compreender as ciências naturais e as tecnologias a elas associadas como construções humanas, percebendo seus papéis nos processos de produção e no desenvolvimento econômico e social da humanidade.', 'skills' => [
            [1, 'Reconhecer características ou propriedades de fenômenos ondulatórios ou oscilatórios, relacionando-os a seus usos em diferentes contextos.'],
            [2, 'Associar a solução de problemas de comunicação, transporte, saúde ou outro, com o correspondente desenvolvimento científico e tecnológico.'],
            [3, 'Confrontar interpretações científicas com interpretações baseadas no senso comum, ao longo do tempo ou em diferentes culturas.'],
            [4, 'Avaliar propostas de intervenção no ambiente, considerando a qualidade da vida humana ou medidas de conservação, recuperação ou utilização sustentável da biodiversidade.'],
        ]],
        ['area' => 'enem-ciencias-natureza', 'number' => 2, 'statement' => 'Identificar a presença e aplicar as tecnologias associadas às ciências naturais em diferentes contextos.', 'skills' => [
            [5, 'Dimensionar circuitos ou dispositivos elétricos de uso cotidiano.'],
            [6, 'Relacionar informações para compreender manuais de instalação ou utilização de aparelhos, ou sistemas tecnológicos de uso comum.'],
            [7, 'Selecionar testes de controle, parâmetros ou critérios para a comparação de materiais e produtos, tendo em vista a defesa do consumidor, a saúde do trabalhador ou a qualidade de vida.'],
        ]],
        ['area' => 'enem-ciencias-natureza', 'number' => 3, 'statement' => 'Associar intervenções que resultam em degradação ou conservação ambiental a processos produtivos e sociais e a instrumentos ou ações científico-tecnológicos.', 'skills' => [
            [8, 'Identificar etapas em processos de obtenção, transformação, utilização ou reciclagem de recursos naturais, energéticos ou matérias-primas, considerando processos biológicos, químicos ou físicos neles envolvidos.'],
            [9, 'Compreender a importância dos ciclos biogeoquímicos ou do fluxo energia para a vida, ou da ação de agentes ou fenômenos que podem causar alterações nesses processos.'],
            [10, 'Analisar perturbações ambientais, identificando fontes, transporte e(ou) destino dos poluentes ou prevendo efeitos em sistemas naturais, produtivos ou sociais.'],
            [11, 'Reconhecer benefícios, limitações e aspectos éticos da biotecnologia, considerando estruturas e processos biológicos envolvidos em produtos biotecnológicos.'],
            [12, 'Avaliar impactos em ambientes naturais decorrentes de atividades sociais ou econômicas, considerando interesses contraditórios.'],
        ]],
        ['area' => 'enem-ciencias-natureza', 'number' => 4, 'statement' => 'Compreender interações entre organismos e ambiente, em particular aquelas relacionadas à saúde humana, relacionando conhecimentos científicos, aspectos culturais e características individuais.', 'skills' => [
            [13, 'Reconhecer mecanismos de transmissão da vida, prevendo ou explicando a manifestação de características dos seres vivos.'],
            [14, 'Identificar padrões em fenômenos e processos vitais dos organismos, como manutenção do equilíbrio interno, defesa, relações com o ambiente, sexualidade, entre outros.'],
            [15, 'Interpretar modelos e experimentos para explicar fenômenos ou processos biológicos em qualquer nível de organização dos sistemas biológicos.'],
            [16, 'Compreender o papel da evolução na produção de padrões, processos biológicos ou na organização taxonômica dos seres vivos.'],
        ]],
        ['area' => 'enem-ciencias-natureza', 'number' => 5, 'statement' => 'Entender métodos e procedimentos próprios das ciências naturais e aplicá-los em diferentes contextos.', 'skills' => [
            [17, 'Relacionar informações apresentadas em diferentes formas de linguagem e representação usadas nas ciências físicas, químicas ou biológicas, como texto discursivo, gráficos, tabelas, relações matemáticas ou linguagem simbólica.'],
            [18, 'Relacionar propriedades físicas, químicas ou biológicas de produtos, sistemas ou procedimentos tecnológicos às finalidades a que se destinam.'],
            [19, 'Avaliar métodos, processos ou procedimentos das ciências naturais que contribuam para diagnosticar ou solucionar problemas de ordem social, econômica ou ambiental.'],
        ]],
        ['area' => 'enem-ciencias-natureza', 'number' => 6, 'statement' => 'Apropriar-se de conhecimentos da física para, em situações problema, interpretar, avaliar ou planejar intervenções científico-tecnológicas.', 'skills' => [
            [20, 'Caracterizar causas ou efeitos dos movimentos de partículas, substâncias, objetos ou corpos celestes.'],
            [21, 'Utilizar leis físicas e (ou) químicas para interpretar processos naturais ou tecnológicos inseridos no contexto da termodinâmica e(ou) do eletromagnetismo.'],
            [22, 'Compreender fenômenos decorrentes da interação entre a radiação e a matéria em suas manifestações em processos naturais ou tecnológicos, ou em suas implicações biológicas, sociais, econômicas ou ambientais.'],
            [23, 'Avaliar possibilidades de geração, uso ou transformação de energia em ambientes específicos, considerando implicações éticas, ambientais, sociais e/ou econômicas.'],
        ]],
        ['area' => 'enem-ciencias-natureza', 'number' => 7, 'statement' => 'Apropriar-se de conhecimentos da química para, em situações problema, interpretar, avaliar ou planejar intervenções científico-tecnológicas.', 'skills' => [
            [24, 'Utilizar códigos e nomenclatura da química para caracterizar materiais, substâncias ou transformações químicas.'],
            [25, 'Caracterizar materiais ou substâncias, identificando etapas, rendimentos ou implicações biológicas, sociais, econômicas ou ambientais de sua obtenção ou produção.'],
            [26, 'Avaliar implicações sociais, ambientais e/ou econômicas na produção ou no consumo de recursos energéticos ou minerais, identificando transformações químicas ou de energia envolvidas nesses processos.'],
            [27, 'Avaliar propostas de intervenção no meio ambiente aplicando conhecimentos químicos, observando riscos ou benefícios.'],
        ]],
        ['area' => 'enem-ciencias-natureza', 'number' => 8, 'statement' => 'Apropriar-se de conhecimentos da biologia para, em situações problema, interpretar, avaliar ou planejar intervenções científico-tecnológicas.', 'skills' => [
            [28, 'Associar características adaptativas dos organismos com seu modo de vida ou com seus limites de distribuição em diferentes ambientes, em especial em ambientes brasileiros.'],
            [29, 'Interpretar experimentos ou técnicas que utilizam seres vivos, analisando implicações para o ambiente, a saúde, a produção de alimentos, matérias primas ou produtos industriais.'],
            [30, 'Avaliar propostas de alcance individual ou coletivo, identificando aquelas que visam à preservação e a implementação da saúde individual, coletiva ou do ambiente.'],
        ]],

        // ===================== Matemática =====================
        ['area' => 'enem-matematica', 'number' => 1, 'statement' => 'Construir significados para os números naturais, inteiros, racionais e reais.', 'skills' => [
            [1, 'Reconhecer, no contexto social, diferentes significados e representações dos números e operações - naturais, inteiros, racionais ou reais.'],
            [2, 'Identificar padrões numéricos ou princípios de contagem.'],
            [3, 'Resolver situação-problema envolvendo conhecimentos numéricos.'],
            [4, 'Avaliar a razoabilidade de um resultado numérico na construção de argumentos sobre afirmações quantitativas.'],
            [5, 'Avaliar propostas de intervenção na realidade utilizando conhecimentos numéricos.'],
        ]],
        ['area' => 'enem-matematica', 'number' => 2, 'statement' => 'Utilizar o conhecimento geométrico para realizar a leitura e a representação da realidade e agir sobre ela.', 'skills' => [
            [6, 'Interpretar a localização e a movimentação de pessoas/objetos no espaço tridimensional e sua representação no espaço bidimensional.'],
            [7, 'Identificar características de figuras planas ou espaciais.'],
            [8, 'Resolver situação-problema que envolva conhecimentos geométricos de espaço e forma.'],
            [9, 'Utilizar conhecimentos geométricos de espaço e forma na seleção de argumentos propostos como solução de problemas do cotidiano.'],
        ]],
        ['area' => 'enem-matematica', 'number' => 3, 'statement' => 'Construir noções de grandezas e medidas para a compreensão da realidade e a solução de problemas do cotidiano.', 'skills' => [
            [10, 'Identificar relações entre grandezas e unidades de medida.'],
            [11, 'Utilizar a noção de escalas na leitura de representação de situação do cotidiano.'],
            [12, 'Resolver situação-problema que envolva medidas de grandezas.'],
            [13, 'Avaliar o resultado de uma medição na construção de um argumento consistente.'],
            [14, 'Avaliar proposta de intervenção na realidade utilizando conhecimentos geométricos relacionados a grandezas e medidas.'],
        ]],
        ['area' => 'enem-matematica', 'number' => 4, 'statement' => 'Construir noções de variação de grandezas para a compreensão da realidade e a solução de problemas do cotidiano.', 'skills' => [
            [15, 'Identificar a relação de dependência entre grandezas.'],
            [16, 'Resolver situação-problema envolvendo a variação de grandezas, direta ou inversamente proporcionais.'],
            [17, 'Analisar informações envolvendo a variação de grandezas como recurso para a construção de argumentação.'],
            [18, 'Avaliar propostas de intervenção na realidade envolvendo variação de grandezas.'],
        ]],
        ['area' => 'enem-matematica', 'number' => 5, 'statement' => 'Modelar e resolver problemas que envolvem variáveis socioeconômicas ou técnico-científicas, usando representações algébricas.', 'skills' => [
            [19, 'Identificar representações algébricas que expressem a relação entre grandezas.'],
            [20, 'Interpretar gráfico cartesiano que represente relações entre grandezas.'],
            [21, 'Resolver situação-problema cuja modelagem envolva conhecimentos algébricos.'],
            [22, 'Utilizar conhecimentos algébricos/geométricos como recurso para a construção de argumentação.'],
            [23, 'Avaliar propostas de intervenção na realidade utilizando conhecimentos algébricos.'],
        ]],
        ['area' => 'enem-matematica', 'number' => 6, 'statement' => 'Interpretar informações de natureza científica e social obtidas da leitura de gráficos e tabelas, realizando previsão de tendência, extrapolação, interpolação e interpretação.', 'skills' => [
            [24, 'Utilizar informações expressas em gráficos ou tabelas para fazer inferências.'],
            [25, 'Resolver problema com dados apresentados em tabelas ou gráficos.'],
            [26, 'Analisar informações expressas em gráficos ou tabelas como recurso para a construção de argumentos.'],
        ]],
        ['area' => 'enem-matematica', 'number' => 7, 'statement' => 'Compreender o caráter aleatório e não-determinístico dos fenômenos naturais e sociais e utilizar instrumentos adequados para medidas, determinação de amostras e cálculos de probabilidade para interpretar informações de variáveis apresentadas em uma distribuição estatística.', 'skills' => [
            [27, 'Calcular medidas de tendência central ou de dispersão de um conjunto de dados expressos em uma tabela de frequências de dados agrupados (não em classes) ou em gráficos.'],
            [28, 'Resolver situação-problema que envolva conhecimentos de estatística e probabilidade.'],
            [29, 'Utilizar conhecimentos de estatística e probabilidade como recurso para a construção de argumentação.'],
            [30, 'Avaliar propostas de intervenção na realidade utilizando conhecimentos de estatística e probabilidade.'],
        ]],

        // ===== Linguagens e Ciências Humanas: competências (habilidades = próximo passo) =====
        ['area' => 'enem-linguagens', 'number' => 1, 'statement' => 'Aplicar as tecnologias da comunicação e da informação na escola, no trabalho e em outros contextos relevantes para sua vida.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 2, 'statement' => 'Conhecer e usar língua(s) estrangeira(s) moderna(s) como instrumento de acesso a informações e a outras culturas e grupos sociais.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 3, 'statement' => 'Compreender e usar a linguagem corporal como relevante para a própria vida, integradora social e formadora da identidade.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 4, 'statement' => 'Compreender a arte como saber cultural e estético gerador de significação e integrador da organização do mundo e da própria identidade.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 5, 'statement' => 'Analisar, interpretar e aplicar recursos expressivos das linguagens, relacionando textos com seus contextos, mediante a natureza, função, organização, estrutura das manifestações, de acordo com as condições de produção e recepção.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 6, 'statement' => 'Compreender e usar os sistemas simbólicos das diferentes linguagens como meios de organização cognitiva da realidade pela constituição de significados, expressão, comunicação e informação.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 7, 'statement' => 'Confrontar opiniões e pontos de vista sobre as diferentes linguagens e suas manifestações específicas.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 8, 'statement' => 'Compreender e usar a língua portuguesa como língua materna, geradora de significação e integradora da organização do mundo e da própria identidade.', 'skills' => []],
        ['area' => 'enem-linguagens', 'number' => 9, 'statement' => 'Entender os princípios, a natureza, a função e o impacto das tecnologias da comunicação e da informação na sua vida pessoal e social, no desenvolvimento do conhecimento, associando-o aos conhecimentos científicos, às linguagens que lhes dão suporte, às demais tecnologias, aos processos de produção e aos problemas que se propõem solucionar.', 'skills' => []],

        ['area' => 'enem-ciencias-humanas', 'number' => 1, 'statement' => 'Compreender os elementos culturais que constituem as identidades.', 'skills' => []],
        ['area' => 'enem-ciencias-humanas', 'number' => 2, 'statement' => 'Compreender as transformações dos espaços geográficos como produto das relações socioeconômicas e culturais de poder.', 'skills' => []],
        ['area' => 'enem-ciencias-humanas', 'number' => 3, 'statement' => 'Compreender a produção e o papel histórico das instituições sociais, políticas e econômicas, associando-as aos diferentes grupos, conflitos e movimentos sociais.', 'skills' => []],
        ['area' => 'enem-ciencias-humanas', 'number' => 4, 'statement' => 'Entender as transformações técnicas e tecnológicas e seu impacto nos processos de produção, no desenvolvimento do conhecimento e na vida social.', 'skills' => []],
        ['area' => 'enem-ciencias-humanas', 'number' => 5, 'statement' => 'Utilizar os conhecimentos históricos para compreender e valorizar os fundamentos da cidadania e da democracia, favorecendo uma atuação consciente do indivíduo na sociedade.', 'skills' => []],
        ['area' => 'enem-ciencias-humanas', 'number' => 6, 'statement' => 'Compreender a sociedade e a natureza, reconhecendo suas interações no espaço em diferentes contextos históricos e geográficos.', 'skills' => []],
    ],

    // --- Objetos de conhecimento / conteúdos (subconjunto usado pelo piloto) ---
    'contents' => [
        // Matemática
        ['area' => 'enem-matematica', 'discipline' => 'Matemática', 'name' => 'Conhecimentos numéricos'],
        ['area' => 'enem-matematica', 'discipline' => 'Matemática', 'name' => 'Conhecimentos geométricos'],
        ['area' => 'enem-matematica', 'discipline' => 'Matemática', 'name' => 'Conhecimentos de estatística e probabilidade'],
        ['area' => 'enem-matematica', 'discipline' => 'Matemática', 'name' => 'Conhecimentos algébricos'],
        ['area' => 'enem-matematica', 'discipline' => 'Matemática', 'name' => 'Conhecimentos algébricos/geométricos'],
        ['area' => 'enem-matematica', 'discipline' => 'Matemática', 'name' => 'Grandezas e medidas'],
        ['area' => 'enem-matematica', 'discipline' => 'Matemática', 'name' => 'Variação de grandezas e proporcionalidade'],
        // Física
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Física', 'name' => 'Mecânica: movimento, forças e equilíbrio'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Física', 'name' => 'Energia, trabalho e potência'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Física', 'name' => 'Calor e fenômenos térmicos'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Física', 'name' => 'Fenômenos elétricos e magnéticos'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Física', 'name' => 'Oscilações, ondas, óptica e radiação'],
        // Química
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Química', 'name' => 'Transformações químicas'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Química', 'name' => 'Materiais, propriedades e separação de misturas'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Química', 'name' => 'Água, soluções e equilíbrio ácido-base'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Química', 'name' => 'Transformações químicas e energia'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Química', 'name' => 'Compostos de carbono (química orgânica)'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Química', 'name' => 'Química, tecnologias, sociedade e meio ambiente'],
        // Biologia
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Biologia', 'name' => 'Moléculas, células e tecidos'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Biologia', 'name' => 'Hereditariedade e diversidade da vida'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Biologia', 'name' => 'Identidade dos seres vivos'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Biologia', 'name' => 'Ecologia e ciências ambientais'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Biologia', 'name' => 'Origem e evolução da vida'],
        ['area' => 'enem-ciencias-natureza', 'discipline' => 'Biologia', 'name' => 'Qualidade de vida das populações humanas'],
    ],
];
