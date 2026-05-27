<?php
// ================================
// Gerador da base de pontos de acupuntura — uso em CLI.
//
//   php gerar_base.php
//
// O script produz/atualiza seed/pontos.json com os 361 pontos canônicos
// dos 14 meridianos principais + pontos extraordinários comuns.
//
// Estratégia:
//   - Para cada meridiano, define um array indexado pelo número do ponto,
//     com [pinyin, localização curta, categoria opcional].
//   - Pontos clássicos já modelados em pontos.json (sintomas, síndromes,
//     ações, combinações, contraindicações) são PRESERVADOS pelo script:
//     o esqueleto não sobrescreve dados existentes.
//   - Pontos sem dados clínicos completos recebem arrays vazios + flag
//     `dados_completos: false`. A UI usa isso para marcar visualmente.
//
// Fontes da nomenclatura/localização: WHO Standard Acupuncture Point
// Locations in the Western Pacific Region (documento aberto da OMS) e
// terminologia clássica padrão amplamente publicada em livros didáticos.
// Não foi copiado nenhum conteúdo protegido das referências citadas no
// briefing — somente nomes pinyin e descrições de localização padrão.
// ================================

// ---------------- Definição dos meridianos (canônica) ----------------
// Estrutura: [abrev_pt => [nome_meridiano, [num => [pinyin, localização, categoria?]]]]
$MERIDIANOS = [
  'P' => ['Pulmão', [
    1  => ['Zhongfu',  '1º espaço intercostal, 6 cun lateral à linha média anterior', 'mu do Pulmão'],
    2  => ['Yunmen',   'Depressão infraclavicular, 6 cun lateral à linha média anterior'],
    3  => ['Tianfu',   '3 cun abaixo da prega axilar, na borda lateral do bíceps braquial'],
    4  => ['Xiabai',   '4 cun abaixo da prega axilar, na borda lateral do bíceps braquial'],
    5  => ['Chize',    'Prega do cotovelo, lateral ao tendão do bíceps', 'he (mar)'],
    6  => ['Kongzui',  '7 cun acima da prega do punho, na borda radial do antebraço', 'xi (fenda)'],
    7  => ['Lieque',   '1,5 cun acima da prega do punho, na borda radial do antebraço', 'luo · confluência com Ren Mai'],
    8  => ['Jingqu',   '1 cun acima da prega do punho, na borda radial', 'jing (rio)'],
    9  => ['Taiyuan',  'Prega radial do punho, lateral à artéria radial', 'yuan (fonte) · shu (arroio) · influência dos vasos'],
    10 => ['Yuji',     'Meio do 1º metacarpo, na borda da pele palmar/dorsal', 'ying (fonte)'],
    11 => ['Shaoshang','Canto ungueal radial do polegar', 'jing (poço)'],
  ]],

  'IG' => ['Intestino Grosso', [
    1  => ['Shangyang',   'Canto ungueal radial do indicador', 'jing (poço)'],
    2  => ['Erjian',      'Depressão distal à articulação metacarpofalângica do indicador, lado radial', 'ying (fonte)'],
    3  => ['Sanjian',     'Depressão proximal à articulação metacarpofalângica do indicador, lado radial', 'shu (arroio)'],
    4  => ['Hegu',        'Dorso da mão, entre 1º e 2º metacarpos', 'yuan (fonte) · comando da face'],
    5  => ['Yangxi',      'Tabaqueira anatômica, entre tendões do extensor longo e curto do polegar', 'jing (rio)'],
    6  => ['Pianli',      '3 cun acima de IG5, na borda dorsorradial do antebraço', 'luo'],
    7  => ['Wenliu',      '5 cun acima de IG5', 'xi (fenda)'],
    8  => ['Xialian',     '4 cun abaixo de IG11'],
    9  => ['Shanglian',   '3 cun abaixo de IG11'],
    10 => ['Shousanli',   '2 cun abaixo de IG11'],
    11 => ['Quchi',       'Depressão lateral à prega do cotovelo flexionado a 90°', 'he (mar)'],
    12 => ['Zhouliao',    '1 cun proximal a IG11, na borda lateral do úmero'],
    13 => ['Shouwuli',    '3 cun proximal a IG11, na borda lateral do úmero'],
    14 => ['Binao',       'Inserção do deltoide, 7 cun proximal a IG11'],
    15 => ['Jianyu',      'Depressão anterior do ombro abduzido (entre acrômio e tubérculo maior)'],
    16 => ['Jugu',        'Depressão entre acrômio e espinha da escápula'],
    17 => ['Tianding',    '1 cun abaixo de IG18, borda posterior do esternocleidomastoideo'],
    18 => ['Futu',        'No pescoço, ao lado da maçã de Adão, entre os ventres do esternocleidomastoideo'],
    19 => ['Heliao',      'Abaixo da narina, ao lado do filtro labial'],
    20 => ['Yingxiang',   'Sulco nasolabial, lateral à asa do nariz'],
  ]],

  'E' => ['Estômago', [
    1  => ['Chengqi',     'Entre globo ocular e margem infraorbital, na pupila'],
    2  => ['Sibai',       '1 cun abaixo de E1, no forame infraorbital'],
    3  => ['Juliao',      'Sulco nasolabial, na linha vertical da pupila'],
    4  => ['Dicang',      '0,4 cun lateral ao canto da boca'],
    5  => ['Daying',      'Ângulo da mandíbula, na borda anterior do masseter'],
    6  => ['Jiache',      'Ventre do masseter, ao apertar os dentes'],
    7  => ['Xiaguan',     'Depressão abaixo do arco zigomático, anterior ao côndilo mandibular'],
    8  => ['Touwei',      'Canto fronto-temporal do couro cabeludo, 0,5 cun dentro da linha do cabelo'],
    9  => ['Renying',     'Ao lado da maçã de Adão, na borda anterior do esternocleidomastoideo'],
    10 => ['Shuitu',      'Entre E9 e E11, na borda anterior do esternocleidomastoideo'],
    11 => ['Qishe',       'Borda superior da clavícula, na cabeça esternal do esternocleidomastoideo'],
    12 => ['Quepen',      'Centro da fossa supraclavicular, 4 cun lateral à linha média anterior'],
    13 => ['Qihu',        'Borda inferior da clavícula, na linha mamilar'],
    14 => ['Kufang',      '1º espaço intercostal, na linha mamilar'],
    15 => ['Wuyi',        '2º espaço intercostal, na linha mamilar'],
    16 => ['Yingchuang',  '3º espaço intercostal, na linha mamilar'],
    17 => ['Ruzhong',     'Centro do mamilo (somente referência anatômica, não puncionar)'],
    18 => ['Rugen',       '5º espaço intercostal, na linha mamilar'],
    19 => ['Burong',      '6 cun acima do umbigo, 2 cun lateral à linha média'],
    20 => ['Chengman',    '5 cun acima do umbigo, 2 cun lateral'],
    21 => ['Liangmen',    '4 cun acima do umbigo, 2 cun lateral'],
    22 => ['Guanmen',     '3 cun acima do umbigo, 2 cun lateral'],
    23 => ['Taiyi',       '2 cun acima do umbigo, 2 cun lateral'],
    24 => ['Huaroumen',   '1 cun acima do umbigo, 2 cun lateral'],
    25 => ['Tianshu',     '2 cun lateral ao umbigo', 'mu do Intestino Grosso'],
    26 => ['Wailing',     '1 cun abaixo do umbigo, 2 cun lateral'],
    27 => ['Daju',        '2 cun abaixo do umbigo, 2 cun lateral'],
    28 => ['Shuidao',     '3 cun abaixo do umbigo, 2 cun lateral'],
    29 => ['Guilai',      '4 cun abaixo do umbigo, 2 cun lateral'],
    30 => ['Qichong',     '5 cun abaixo do umbigo, 2 cun lateral, sobre a artéria femoral'],
    31 => ['Biguan',      'Cruzamento da linha vertical da espinha ilíaca antero-superior com a linha do períneo'],
    32 => ['Futu',        '6 cun acima da margem superolateral da patela, linha de E31'],
    33 => ['Yinshi',      '3 cun acima da margem superolateral da patela'],
    34 => ['Liangqiu',    '2 cun acima da margem superolateral da patela', 'xi (fenda)'],
    35 => ['Dubi',        'Depressão lateral abaixo da patela, com o joelho flexionado'],
    36 => ['Zusanli',     '3 cun abaixo do joelho, 1 dedo lateral à crista tibial', 'he (mar) · comando do abdômen'],
    37 => ['Shangjuxu',   '6 cun abaixo do joelho, 1 dedo lateral à crista tibial', 'he inferior do Intestino Grosso'],
    38 => ['Tiaokou',     '8 cun abaixo do joelho, 1 dedo lateral à crista tibial'],
    39 => ['Xiajuxu',     '9 cun abaixo do joelho, 1 dedo lateral à crista tibial', 'he inferior do Intestino Delgado'],
    40 => ['Fenglong',    '8 cun abaixo do joelho, 2 dedos lateral à crista tibial', 'luo'],
    41 => ['Jiexi',       'Centro da prega anterior do tornozelo, entre os tendões extensores', 'jing (rio)'],
    42 => ['Chongyang',   'Dorso do pé, no ponto mais alto, sobre a artéria dorsal do pé', 'yuan (fonte)'],
    43 => ['Xiangu',      'Depressão proximal entre 2º e 3º metatarsos', 'shu (arroio)'],
    44 => ['Neiting',     'Depressão distal entre 2º e 3º artelhos', 'ying (fonte)'],
    45 => ['Lidui',       'Canto ungueal lateral do 2º artelho', 'jing (poço)'],
  ]],

  'BP' => ['Baço', [
    1  => ['Yinbai',      'Canto ungueal medial do hálux', 'jing (poço)'],
    2  => ['Dadu',        'Depressão distal à articulação metatarsofalângica do hálux, medial', 'ying (fonte)'],
    3  => ['Taibai',      'Depressão proximal à articulação metatarsofalângica do hálux, medial', 'yuan (fonte) · shu (arroio)'],
    4  => ['Gongsun',     'Borda inferior da base do 1º metatarso', 'luo · confluência com Chong Mai'],
    5  => ['Shangqiu',    'Depressão antero-inferior do maléolo medial', 'jing (rio)'],
    6  => ['Sanyinjiao',  '3 cun acima do ápice do maléolo medial, na borda posterior da tíbia', 'cruzamento dos 3 Yin do pé'],
    7  => ['Lougu',       '6 cun acima do maléolo medial, na borda posterior da tíbia'],
    8  => ['Diji',        '3 cun abaixo de BP9, na borda posterior da tíbia', 'xi (fenda)'],
    9  => ['Yinlingquan', 'Depressão posteroinferior do côndilo medial da tíbia', 'he (mar)'],
    10 => ['Xuehai',      '2 cun acima do canto medial superior da patela'],
    11 => ['Jimen',       '6 cun acima de BP10, na linha entre BP10 e BP12'],
    12 => ['Chongmen',    'Lateral da artéria femoral, na prega inguinal'],
    13 => ['Fushe',       '4 cun lateral à linha média, 0,7 cun acima de BP12'],
    14 => ['Fujie',       '4 cun lateral à linha média, 1,3 cun abaixo de BP15'],
    15 => ['Daheng',      '4 cun lateral ao umbigo'],
    16 => ['Fuai',        '4 cun lateral à linha média, 3 cun acima do umbigo'],
    17 => ['Shidou',      '5º espaço intercostal, 6 cun lateral à linha média anterior'],
    18 => ['Tianxi',      '4º espaço intercostal, 6 cun lateral'],
    19 => ['Xiongxiang',  '3º espaço intercostal, 6 cun lateral'],
    20 => ['Zhourong',    '2º espaço intercostal, 6 cun lateral'],
    21 => ['Dabao',       '6º espaço intercostal, na linha axilar média', 'grande luo do Baço'],
  ]],

  'C' => ['Coração', [
    1 => ['Jiquan',  'No centro da fossa axilar'],
    2 => ['Qingling','3 cun acima de C3, na borda medial do bíceps'],
    3 => ['Shaohai', 'Borda medial da prega do cotovelo flexionado', 'he (mar)'],
    4 => ['Lingdao', '1,5 cun acima da prega do punho, lado ulnar', 'jing (rio)'],
    5 => ['Tongli',  '1 cun acima da prega do punho, lado ulnar', 'luo'],
    6 => ['Yinxi',   '0,5 cun acima da prega do punho, lado ulnar', 'xi (fenda)'],
    7 => ['Shenmen', 'Prega do punho, na depressão ulnar ao pisiforme', 'yuan (fonte) · shu (arroio)'],
    8 => ['Shaofu',  'Palma, entre 4º e 5º metacarpos, na linha do dedo mínimo flexionado', 'ying (fonte)'],
    9 => ['Shaochong','Canto ungueal radial do dedo mínimo', 'jing (poço)'],
  ]],

  'ID' => ['Intestino Delgado', [
    1  => ['Shaoze',     'Canto ungueal ulnar do dedo mínimo', 'jing (poço)'],
    2  => ['Qiangu',     'Depressão distal à articulação MCF do dedo mínimo, lado ulnar', 'ying (fonte)'],
    3  => ['Houxi',      'Depressão proximal à articulação MCF do dedo mínimo, lado ulnar', 'shu (arroio) · confluência com Du Mai'],
    4  => ['Wangu',      'Borda ulnar da mão, depressão entre 5º metacarpo e ganchoso', 'yuan (fonte)'],
    5  => ['Yanggu',     'Depressão entre estiloide ulnar e piramidal', 'jing (rio)'],
    6  => ['Yanglao',    'Acima do estiloide ulnar, com o punho em supinação', 'xi (fenda)'],
    7  => ['Zhizheng',   '5 cun acima de ID5', 'luo'],
    8  => ['Xiaohai',    'Entre olécrano e epicôndilo medial', 'he (mar)'],
    9  => ['Jianzhen',   '1 cun acima da prega axilar posterior'],
    10 => ['Naoshu',     'Vertical de ID9, abaixo da espinha da escápula'],
    11 => ['Tianzong',   'Centro da fossa infraespinhosa'],
    12 => ['Bingfeng',   'Acima de ID11, na fossa supraespinhosa'],
    13 => ['Quyuan',     'Borda medial da fossa supraespinhosa'],
    14 => ['Jianwaishu', '3 cun lateral à borda inferior do processo espinhoso de T1'],
    15 => ['Jianzhongshu','2 cun lateral à borda inferior do processo espinhoso de C7'],
    16 => ['Tianchuang', 'Borda posterior do esternocleidomastoideo, ao nível da maçã de Adão'],
    17 => ['Tianrong',   'Atrás do ângulo da mandíbula, na borda anterior do esternocleidomastoideo'],
    18 => ['Quanliao',   'Sob a margem inferior do zigomático, na linha vertical do canto externo do olho'],
    19 => ['Tinggong',   'Anterior ao tragus, com a boca aberta'],
  ]],

  'B' => ['Bexiga', [
    1  => ['Jingming',   'Canto medial do olho, 0,1 cun acima'],
    2  => ['Cuanzhu',    'Extremidade medial da sobrancelha'],
    3  => ['Meichong',   '0,5 cun acima da linha de implantação anterior, vertical de B2'],
    4  => ['Quchai',     '0,5 cun acima da linha de implantação, 1,5 cun lateral à linha média'],
    5  => ['Wuchu',      '1 cun acima da linha de implantação, 1,5 cun lateral'],
    6  => ['Chengguang', '2,5 cun acima da linha de implantação, 1,5 cun lateral'],
    7  => ['Tongtian',   '4 cun acima da linha de implantação, 1,5 cun lateral'],
    8  => ['Luoque',     '5,5 cun acima da linha de implantação, 1,5 cun lateral'],
    9  => ['Yuzhen',     '2,5 cun lateral ao centro da protuberância occipital'],
    10 => ['Tianzhu',    '1,3 cun lateral à linha média posterior, na borda lateral do trapézio'],
    11 => ['Dazhu',      '1,5 cun lateral ao processo espinhoso de T1', 'influência dos ossos'],
    12 => ['Fengmen',    '1,5 cun lateral ao processo espinhoso de T2'],
    13 => ['Feishu',     '1,5 cun lateral ao processo espinhoso de T3', 'shu dorsal do Pulmão'],
    14 => ['Jueyinshu',  '1,5 cun lateral ao processo espinhoso de T4', 'shu dorsal do Pericárdio'],
    15 => ['Xinshu',     '1,5 cun lateral ao processo espinhoso de T5', 'shu dorsal do Coração'],
    16 => ['Dushu',      '1,5 cun lateral ao processo espinhoso de T6'],
    17 => ['Geshu',      '1,5 cun lateral ao processo espinhoso de T7', 'influência do Sangue'],
    18 => ['Ganshu',     '1,5 cun lateral ao processo espinhoso de T9', 'shu dorsal do Fígado'],
    19 => ['Danshu',     '1,5 cun lateral ao processo espinhoso de T10', 'shu dorsal da Vesícula Biliar'],
    20 => ['Pishu',      '1,5 cun lateral ao processo espinhoso de T11', 'shu dorsal do Baço'],
    21 => ['Weishu',     '1,5 cun lateral ao processo espinhoso de T12', 'shu dorsal do Estômago'],
    22 => ['Sanjiaoshu', '1,5 cun lateral ao processo espinhoso de L1', 'shu dorsal do Triplo Aquecedor'],
    23 => ['Shenshu',    '1,5 cun lateral ao processo espinhoso de L2', 'shu dorsal do Rim'],
    24 => ['Qihaishu',   '1,5 cun lateral ao processo espinhoso de L3'],
    25 => ['Dachangshu', '1,5 cun lateral ao processo espinhoso de L4', 'shu dorsal do Intestino Grosso'],
    26 => ['Guanyuanshu','1,5 cun lateral ao processo espinhoso de L5'],
    27 => ['Xiaochangshu','1,5 cun lateral à linha média, no nível do 1º forame sacral', 'shu dorsal do Intestino Delgado'],
    28 => ['Pangguangshu','1,5 cun lateral à linha média, no nível do 2º forame sacral', 'shu dorsal da Bexiga'],
    29 => ['Zhonglushu', '1,5 cun lateral à linha média, no nível do 3º forame sacral'],
    30 => ['Baihuanshu', '1,5 cun lateral à linha média, no nível do 4º forame sacral'],
    31 => ['Shangliao',  '1º forame sacral'],
    32 => ['Ciliao',     '2º forame sacral'],
    33 => ['Zhongliao',  '3º forame sacral'],
    34 => ['Xialiao',    '4º forame sacral'],
    35 => ['Huiyang',    '0,5 cun lateral à ponta do cóccix'],
    36 => ['Chengfu',    'Centro da prega glútea'],
    37 => ['Yinmen',     '6 cun abaixo de B36, na linha entre B36 e B40'],
    38 => ['Fuxi',       '1 cun acima de B39, medial ao tendão do bíceps femoral'],
    39 => ['Weiyang',    'Prega poplítea, medial ao tendão do bíceps femoral', 'he inferior do Triplo Aquecedor'],
    40 => ['Weizhong',   'Centro da prega poplítea, entre os tendões', 'he (mar) · comando do dorso'],
    41 => ['Fufen',      '3 cun lateral ao processo espinhoso de T2'],
    42 => ['Pohu',       '3 cun lateral ao processo espinhoso de T3'],
    43 => ['Gaohuang',   '3 cun lateral ao processo espinhoso de T4'],
    44 => ['Shentang',   '3 cun lateral ao processo espinhoso de T5'],
    45 => ['Yixi',       '3 cun lateral ao processo espinhoso de T6'],
    46 => ['Geguan',     '3 cun lateral ao processo espinhoso de T7'],
    47 => ['Hunmen',     '3 cun lateral ao processo espinhoso de T9'],
    48 => ['Yanggang',   '3 cun lateral ao processo espinhoso de T10'],
    49 => ['Yishe',      '3 cun lateral ao processo espinhoso de T11'],
    50 => ['Weicang',    '3 cun lateral ao processo espinhoso de T12'],
    51 => ['Huangmen',   '3 cun lateral ao processo espinhoso de L1'],
    52 => ['Zhishi',     '3 cun lateral ao processo espinhoso de L2'],
    53 => ['Baohuang',   '3 cun lateral à linha média, no nível do 2º forame sacral'],
    54 => ['Zhibian',    '3 cun lateral ao hiato sacral'],
    55 => ['Heyang',     '2 cun distal a B40, entre as cabeças do gastrocnêmio'],
    56 => ['Chengjin',   'Centro do ventre do gastrocnêmio, 5 cun distal a B40'],
    57 => ['Chengshan',  'Depressão entre as duas cabeças do gastrocnêmio, ao contrair a panturrilha'],
    58 => ['Feiyang',    '7 cun acima de B60, na borda lateral da panturrilha', 'luo'],
    59 => ['Fuyang',     '3 cun acima de B60'],
    60 => ['Kunlun',     'Entre maléolo lateral e tendão de Aquiles', 'jing (rio)'],
    61 => ['Pushen',     '1,5 cun distal a B60, na borda lateral do calcâneo'],
    62 => ['Shenmai',    '0,5 cun abaixo do maléolo lateral', 'confluência com Yang Qiao'],
    63 => ['Jinmen',     'Anterior a B62, na depressão do cuboide', 'xi (fenda)'],
    64 => ['Jinggu',     'Borda lateral do pé, proximal à tuberosidade do 5º metatarso', 'yuan (fonte)'],
    65 => ['Shugu',      'Borda lateral do pé, distal à articulação MTF do 5º artelho', 'shu (arroio)'],
    66 => ['Zutonggu',   'Borda lateral do pé, distal à articulação MTF do 5º artelho (ying)', 'ying (fonte)'],
    67 => ['Zhiyin',     'Canto ungueal lateral do 5º artelho', 'jing (poço)'],
  ]],

  'R' => ['Rim', [
    1  => ['Yongquan',   'Planta do pé, no terço anterior em depressão ao curvar os artelhos', 'jing (poço)'],
    2  => ['Rangu',      'Borda inferior do tubérculo navicular, na junção pele plantar/dorsal', 'ying (fonte)'],
    3  => ['Taixi',      'Entre maléolo medial e tendão de Aquiles', 'yuan (fonte) · shu (arroio)'],
    4  => ['Dazhong',    'Posterior e inferior ao maléolo medial, na borda do tendão de Aquiles', 'luo'],
    5  => ['Shuiquan',   '1 cun distal a R3', 'xi (fenda)'],
    6  => ['Zhaohai',    '1 cun abaixo do maléolo medial', 'confluência com Yin Qiao'],
    7  => ['Fuliu',      '2 cun acima de R3, na borda anterior do tendão de Aquiles', 'jing (rio)'],
    8  => ['Jiaoxin',    '2 cun acima de R3, na borda medial da tíbia, 0,5 cun anterior a R7', 'xi do Yin Qiao'],
    9  => ['Zhubin',     '5 cun acima de R3, no ventre medial do gastrocnêmio', 'xi do Yin Wei'],
    10 => ['Yingu',      'Prega poplítea, medial ao tendão do semitendíneo', 'he (mar)'],
    11 => ['Henggu',     '5 cun abaixo do umbigo, 0,5 cun lateral à linha média'],
    12 => ['Dahe',       '4 cun abaixo do umbigo, 0,5 cun lateral'],
    13 => ['Qixue',      '3 cun abaixo do umbigo, 0,5 cun lateral'],
    14 => ['Siman',      '2 cun abaixo do umbigo, 0,5 cun lateral'],
    15 => ['Zhongzhu',   '1 cun abaixo do umbigo, 0,5 cun lateral'],
    16 => ['Huangshu',   '0,5 cun lateral ao umbigo'],
    17 => ['Shangqu',    '2 cun acima do umbigo, 0,5 cun lateral'],
    18 => ['Shiguan',    '3 cun acima do umbigo, 0,5 cun lateral'],
    19 => ['Yindu',      '4 cun acima do umbigo, 0,5 cun lateral'],
    20 => ['Futonggu',   '5 cun acima do umbigo, 0,5 cun lateral'],
    21 => ['Youmen',     '6 cun acima do umbigo, 0,5 cun lateral'],
    22 => ['Bulang',     '5º espaço intercostal, 2 cun lateral à linha média anterior'],
    23 => ['Shenfeng',   '4º espaço intercostal, 2 cun lateral'],
    24 => ['Lingxu',     '3º espaço intercostal, 2 cun lateral'],
    25 => ['Shencang',   '2º espaço intercostal, 2 cun lateral'],
    26 => ['Yuzhong',    '1º espaço intercostal, 2 cun lateral'],
    27 => ['Shufu',      'Borda inferior da clavícula, 2 cun lateral à linha média'],
  ]],

  'PC' => ['Pericárdio', [
    1 => ['Tianchi',  '4º espaço intercostal, 1 cun lateral ao mamilo'],
    2 => ['Tianquan', '2 cun abaixo da prega axilar, entre as cabeças do bíceps'],
    3 => ['Quze',     'Prega do cotovelo, medial ao tendão do bíceps', 'he (mar)'],
    4 => ['Ximen',    '5 cun acima da prega do punho, entre os tendões palmar longo e flexor radial', 'xi (fenda)'],
    5 => ['Jianshi',  '3 cun acima da prega do punho, entre os mesmos tendões', 'jing (rio)'],
    6 => ['Neiguan',  '2 cun acima da prega do punho, entre os mesmos tendões', 'luo · confluência com Yin Wei'],
    7 => ['Daling',   'Centro da prega do punho, entre os mesmos tendões', 'yuan (fonte) · shu (arroio)'],
    8 => ['Laogong',  'Palma, entre 2º e 3º metacarpos, com o punho fechado na altura do dedo médio', 'ying (fonte)'],
    9 => ['Zhongchong','Centro da ponta do dedo médio', 'jing (poço)'],
  ]],

  'TA' => ['Triplo Aquecedor', [
    1  => ['Guanchong',   'Canto ungueal ulnar do anelar', 'jing (poço)'],
    2  => ['Yemen',       'Depressão entre 4º e 5º metacarpos, distal à MCF', 'ying (fonte)'],
    3  => ['Zhongzhu',    'Dorso da mão, depressão proximal entre 4º e 5º metacarpos', 'shu (arroio)'],
    4  => ['Yangchi',     'Centro da prega dorsal do punho', 'yuan (fonte)'],
    5  => ['Waiguan',     '2 cun acima de TA4, entre rádio e ulna no dorso', 'luo · confluência com Yang Wei'],
    6  => ['Zhigou',      '3 cun acima de TA4, entre rádio e ulna', 'jing (rio)'],
    7  => ['Huizong',     '3 cun acima de TA4, 1 dedo ulnar a TA6', 'xi (fenda)'],
    8  => ['Sanyangluo',  '4 cun acima de TA4, entre rádio e ulna'],
    9  => ['Sidu',        '5 cun abaixo do olécrano, entre rádio e ulna'],
    10 => ['Tianjing',    '1 cun acima do olécrano, com o cotovelo flexionado', 'he (mar)'],
    11 => ['Qinglengyuan','1 cun proximal a TA10'],
    12 => ['Xiaoluo',     'Meio da face dorsal do úmero, entre TA11 e TA13'],
    13 => ['Naohui',      'Borda inferior do deltoide, na face posterior do braço'],
    14 => ['Jianliao',    'Depressão posterior do ombro abduzido'],
    15 => ['Tianliao',    'Borda superior da escápula, ângulo medial superior'],
    16 => ['Tianyou',     'Atrás do ângulo da mandíbula, abaixo do processo mastoide'],
    17 => ['Yifeng',      'Atrás do lóbulo da orelha, na depressão entre mandíbula e processo mastoide'],
    18 => ['Qimai',       '1/3 superior do processo mastoide, atrás da orelha'],
    19 => ['Luxi',        'Atrás da orelha, na linha vertical entre TA18 e TA20'],
    20 => ['Jiaosun',     'Acima do ápice da orelha, na inserção do cabelo'],
    21 => ['Ermen',       'Anterior à incisura supratragal, com a boca aberta'],
    22 => ['Erheliao',    'Anterior à raiz da hélice da orelha, ao nível de TA21'],
    23 => ['Sizhukong',   'Extremidade lateral da sobrancelha'],
  ]],

  'VB' => ['Vesícula Biliar', [
    1  => ['Tongziliao',  'Canto lateral do olho, 0,5 cun lateral'],
    2  => ['Tinghui',     'Anterior à incisura intertrágica, com a boca aberta'],
    3  => ['Shangguan',   'Acima do arco zigomático, vertical de E7'],
    4  => ['Hanyan',      '1/4 superior da curva entre VB3 e VB6'],
    5  => ['Xuanlu',      'Meio da curva entre VB3 e VB6'],
    6  => ['Xuanli',      '3/4 da curva entre VB3 e VB6'],
    7  => ['Qubin',       '1 cun anterior ao ápice da orelha'],
    8  => ['Shuaigu',     '1,5 cun acima do ápice da orelha'],
    9  => ['Tianchong',   '0,5 cun posterior a VB8'],
    10 => ['Fubai',       'Posterior à orelha, na curva entre VB9 e VB12'],
    11 => ['Touqiaoyin',  'Posterior à orelha, na curva entre VB10 e VB12'],
    12 => ['Wangu',       'Atrás e abaixo do processo mastoide'],
    13 => ['Benshen',     '0,5 cun dentro da linha do cabelo, 3 cun lateral à linha média'],
    14 => ['Yangbai',     '1 cun acima da sobrancelha, vertical da pupila'],
    15 => ['Toulinqi',    '0,5 cun dentro da linha do cabelo, vertical da pupila'],
    16 => ['Muchuang',    '1,5 cun atrás de VB15'],
    17 => ['Zhengying',   '1 cun atrás de VB16'],
    18 => ['Chengling',   '1,5 cun atrás de VB17'],
    19 => ['Naokong',     'Lateral à protuberância occipital, ao nível de VG17'],
    20 => ['Fengchi',     'Depressão entre trapézio e esternocleidomastoideo, na linha occipital', 'cruzamento Yang Wei/Qiao'],
    21 => ['Jianjing',    'Meio do trapézio, entre C7 e acrômio'],
    22 => ['Yuanye',      '4º espaço intercostal, na linha axilar média'],
    23 => ['Zhejin',      '4º espaço intercostal, 1 cun anterior a VB22'],
    24 => ['Riyue',       '7º espaço intercostal, na linha mamilar', 'mu da Vesícula Biliar'],
    25 => ['Jingmen',     'Extremidade livre da 12ª costela', 'mu do Rim'],
    26 => ['Daimai',      'Vertical de F13, ao nível do umbigo'],
    27 => ['Wushu',       '3 cun abaixo do umbigo, vertical da espinha ilíaca antero-superior'],
    28 => ['Weidao',      '0,5 cun antero-inferior a VB27'],
    29 => ['Juliao',      'Depressão entre espinha ilíaca antero-superior e trocânter maior'],
    30 => ['Huantiao',    '1/3 lateral da linha entre o sacro e o trocânter maior'],
    31 => ['Fengshi',     'Lateral da coxa, na ponta do dedo médio com o braço estendido ao lado'],
    32 => ['Zhongdu',     '2 cun distal a VB31'],
    33 => ['Xiyangguan',  '3 cun acima de VB34, na depressão lateral ao côndilo femoral'],
    34 => ['Yanglingquan','Depressão antero-inferior à cabeça da fíbula', 'he (mar) · influência dos tendões'],
    35 => ['Yangjiao',    '7 cun acima do maléolo lateral, na borda posterior da fíbula', 'xi do Yang Wei'],
    36 => ['Waiqiu',      '7 cun acima do maléolo lateral, na borda anterior da fíbula', 'xi (fenda)'],
    37 => ['Guangming',   '5 cun acima do maléolo lateral, na borda anterior da fíbula', 'luo'],
    38 => ['Yangfu',      '4 cun acima do maléolo lateral, na borda anterior da fíbula', 'jing (rio)'],
    39 => ['Xuanzhong',   '3 cun acima do maléolo lateral, na borda posterior da fíbula', 'influência da medula'],
    40 => ['Qiuxu',       'Depressão antero-inferior do maléolo lateral', 'yuan (fonte)'],
    41 => ['Zulinqi',     'Depressão distal entre 4º e 5º metatarsos no dorso do pé', 'shu (arroio) · confluência com Dai Mai'],
    42 => ['Diwuhui',     'Depressão entre 4º e 5º metatarsos, 1 cun proximal a VB43'],
    43 => ['Xiaxi',       'Depressão distal entre 4º e 5º artelhos', 'ying (fonte)'],
    44 => ['Zuqiaoyin',   'Canto ungueal lateral do 4º artelho', 'jing (poço)'],
  ]],

  'F' => ['Fígado', [
    1  => ['Dadun',     'Canto ungueal lateral do hálux', 'jing (poço)'],
    2  => ['Xingjian',  'Depressão distal entre 1º e 2º artelhos', 'ying (fonte)'],
    3  => ['Taichong',  'Depressão proximal entre 1º e 2º metatarsos no dorso do pé', 'yuan (fonte) · shu (arroio)'],
    4  => ['Zhongfeng', '1 cun anterior ao maléolo medial, medial ao tendão tibial anterior', 'jing (rio)'],
    5  => ['Ligou',     '5 cun acima do maléolo medial, na face medial da tíbia', 'luo'],
    6  => ['Zhongdu',   '7 cun acima do maléolo medial, na face medial da tíbia', 'xi (fenda)'],
    7  => ['Xiguan',    '1 cun posterior a BP9'],
    8  => ['Ququan',    'Borda medial da prega poplítea, com o joelho flexionado', 'he (mar)'],
    9  => ['Yinbao',    '4 cun acima de F8, entre vasto medial e sartório'],
    10 => ['Wuli',      '3 cun abaixo de F11'],
    11 => ['Yinlian',   '2 cun abaixo de F12, no sulco inguinal'],
    12 => ['Jimai',     'Lateral à artéria femoral, ao nível de VC2'],
    13 => ['Zhangmen',  'Extremidade livre da 11ª costela', 'mu do Baço · influência dos Zang'],
    14 => ['Qimen',     '6º espaço intercostal, na linha mamilar', 'mu do Fígado'],
  ]],

  'VC' => ['Vaso Concepção', [
    1  => ['Huiyin',   'Centro do períneo'],
    2  => ['Qugu',     'Borda superior da sínfise púbica, na linha média'],
    3  => ['Zhongji',  '4 cun abaixo do umbigo', 'mu da Bexiga'],
    4  => ['Guanyuan', '3 cun abaixo do umbigo', 'mu do Intestino Delgado · ponto do Yuan Qi'],
    5  => ['Shimen',   '2 cun abaixo do umbigo', 'mu do Triplo Aquecedor'],
    6  => ['Qihai',    '1,5 cun abaixo do umbigo', 'mar do Qi'],
    7  => ['Yinjiao',  '1 cun abaixo do umbigo'],
    8  => ['Shenque',  'Centro do umbigo (não puncionar — usar moxa indireta)'],
    9  => ['Shuifen',  '1 cun acima do umbigo'],
    10 => ['Xiawan',   '2 cun acima do umbigo'],
    11 => ['Jianli',   '3 cun acima do umbigo'],
    12 => ['Zhongwan', '4 cun acima do umbigo', 'mu do Estômago · influência dos Fu'],
    13 => ['Shangwan', '5 cun acima do umbigo'],
    14 => ['Juque',    '6 cun acima do umbigo', 'mu do Coração'],
    15 => ['Jiuwei',   '7 cun acima do umbigo', 'luo do Ren Mai'],
    16 => ['Zhongting','Linha média, no centro do esterno ao nível do 5º espaço intercostal'],
    17 => ['Shanzhong','Linha média, ao nível do 4º espaço intercostal (entre os mamilos)', 'mu do Pericárdio · influência do Qi'],
    18 => ['Yutang',   'Linha média, ao nível do 3º espaço intercostal'],
    19 => ['Zigong',   'Linha média, ao nível do 2º espaço intercostal'],
    20 => ['Huagai',   'Linha média, ao nível do 1º espaço intercostal'],
    21 => ['Xuanji',   'Linha média, no ângulo de Louis (manúbrio do esterno)'],
    22 => ['Tiantu',   'Fossa supraesternal, acima da borda superior do esterno'],
    23 => ['Lianquan', 'Linha média, acima da maçã de Adão, na borda superior do osso hioide'],
    24 => ['Chengjiang','Linha média, no sulco mentoniano'],
  ]],

  'VG' => ['Vaso Governador', [
    1  => ['Changqiang','Meio do caminho entre cóccix e ânus', 'luo do Du Mai'],
    2  => ['Yaoshu',    'Hiato sacral'],
    3  => ['Yaoyangguan','Abaixo do processo espinhoso de L4'],
    4  => ['Mingmen',  'Abaixo do processo espinhoso de L2'],
    5  => ['Xuanshu',  'Abaixo do processo espinhoso de L1'],
    6  => ['Jizhong',  'Abaixo do processo espinhoso de T11'],
    7  => ['Zhongshu', 'Abaixo do processo espinhoso de T10'],
    8  => ['Jinsuo',   'Abaixo do processo espinhoso de T9'],
    9  => ['Zhiyang',  'Abaixo do processo espinhoso de T7'],
    10 => ['Lingtai',  'Abaixo do processo espinhoso de T6'],
    11 => ['Shendao',  'Abaixo do processo espinhoso de T5'],
    12 => ['Shenzhu',  'Abaixo do processo espinhoso de T3'],
    13 => ['Taodao',   'Abaixo do processo espinhoso de T1'],
    14 => ['Dazhui',   'Abaixo do processo espinhoso de C7', 'reunião dos Yang'],
    15 => ['Yamen',    'Abaixo do processo espinhoso de C1, na linha média posterior'],
    16 => ['Fengfu',   '1 cun acima da linha posterior do cabelo'],
    17 => ['Naohu',    '2,5 cun acima da linha posterior do cabelo'],
    18 => ['Qiangjian','4 cun acima da linha posterior do cabelo'],
    19 => ['Houding',  '5,5 cun acima da linha posterior do cabelo'],
    20 => ['Baihui',   'No vértice, ponto médio entre os ápices das orelhas', 'reunião do Yang · mar da medula'],
    21 => ['Qianding', '1,5 cun anterior a VG20'],
    22 => ['Xinhui',   '2 cun anterior a VG21'],
    23 => ['Shangxing','1 cun dentro da linha anterior do cabelo'],
    24 => ['Shenting', '0,5 cun dentro da linha anterior do cabelo'],
    25 => ['Suliao',   'Ponta do nariz'],
    26 => ['Renzhong', '1/3 superior do sulco filtral'],
    27 => ['Duiduan',  'Linha média, no tubérculo do lábio superior'],
    28 => ['Yinjiao',  'No frênulo do lábio superior'],
  ]],
];

// Pontos extras importantes (subset clínico relevante)
$EXTRAS = [
  ['EX-HN1', 'Sishencong',  'Pontos extraordinários', '1 cun anterior, posterior e bilateral a VG20'],
  ['EX-HN3', 'Yintang',     'Pontos extraordinários', 'Entre as duas sobrancelhas (glabela)'],
  ['EX-HN4', 'Yuyao',       'Pontos extraordinários', 'Centro da sobrancelha, vertical da pupila'],
  ['EX-HN5', 'Taiyang',     'Pontos extraordinários', 'Depressão a 1 cun lateral entre canto do olho e sobrancelha'],
  ['EX-HN7', 'Qiuhou',      'Pontos extraordinários', 'Borda infraorbital, no canto lateral do olho'],
  ['EX-HN8', 'Bitong',      'Pontos extraordinários', 'Topo do sulco nasolabial, na asa do nariz'],
  ['EX-HN15','Bailao',      'Pontos extraordinários', '2 cun acima de VG14 e 1 cun lateral'],
  ['EX-B1',  'Dingchuan',   'Pontos extraordinários', '0,5 cun lateral a VG14'],
  ['EX-B2',  'Jiaji',       'Pontos extraordinários', '0,5 cun lateral aos processos espinhosos de T1 a L5 (17 pontos bilaterais)'],
  ['EX-CA1', 'Zigong',      'Pontos extraordinários', '4 cun abaixo do umbigo, 3 cun lateral à linha média'],
  ['EX-UE1', 'Zhoujian',    'Pontos extraordinários', 'Ponta do olécrano, com o cotovelo flexionado'],
  ['EX-UE2', 'Erbai',       'Pontos extraordinários', '4 cun acima da prega do punho, em ambos os lados do tendão palmar longo'],
  ['EX-UE7', 'Yaotongdian', 'Pontos extraordinários', 'Dorso da mão, entre 2º/3º e 4º/5º metacarpos, na linha das pregas digitais'],
  ['EX-UE9', 'Baxie',       'Pontos extraordinários', '4 pontos bilaterais nas pregas entre os dedos da mão'],
  ['EX-UE11','Shixuan',     'Pontos extraordinários', 'Pontas dos 10 dedos das mãos'],
  ['EX-LE2', 'Heding',      'Pontos extraordinários', 'Centro da borda superior da patela'],
  ['EX-LE5', 'Xiyan',       'Pontos extraordinários', 'Duas depressões mediais e laterais à patela com o joelho flexionado'],
  ['EX-LE6', 'Dannangxue',  'Pontos extraordinários', '1-2 cun abaixo de VB34, ponto sensível à pressão'],
  ['EX-LE7', 'Lanweixue',   'Pontos extraordinários', '2 cun abaixo de E36'],
  ['EX-LE10','Bafeng',      'Pontos extraordinários', '4 pontos bilaterais nas pregas entre os artelhos'],
];

// ---------------- Carrega base atual (preserva apenas pontos com dados clínicos reais) ----------------
$arquivoSaida = __DIR__ . '/pontos.json';
$preExistentes = [];
if (is_file($arquivoSaida)) {
  $existeArr = json_decode((string)file_get_contents($arquivoSaida), true);
  if (is_array($existeArr)) {
    foreach ($existeArr as $p) {
      if (empty($p['codigo'])) continue;
      // Só conta como "pré-existente com dados" se tiver pelo menos ações
      // energéticas OU sintomas relacionados populados — caso contrário, é
      // um esqueleto previamente gerado e será sobrescrito.
      $temDados = !empty($p['acoes_energeticas']) || !empty($p['sintomas_relacionados']);
      if ($temDados) $preExistentes[strtoupper($p['codigo'])] = $p;
    }
  }
}

// Helper: gera entrada padrão para um ponto esqueleto
function esqueleto(string $codigo, string $nome, string $meridiano, string $localizacao, ?string $categoriaExtra = null): array {
  $cat = [];
  if ($categoriaExtra) {
    // separa categorias por " · "
    foreach (explode(' · ', $categoriaExtra) as $c) {
      $c = trim($c);
      if ($c !== '') $cat[] = $c;
    }
  }
  return [
    'codigo'                 => $codigo,
    'nome'                   => $nome,
    'meridiano'              => $meridiano,
    'categoria'              => $cat,
    'localizacao'            => $localizacao,
    'acoes_energeticas'      => [],
    'sintomas_relacionados'  => [],
    'sindromes_relacionadas' => [],
    'indicacoes_terapeuticas'=> [],
    'contraindicacoes'       => [],
    'combinacoes'            => [],
    'observacoes_clinicas'   => '',
    'coordenadas_mapa'       => null, // mapa visual removido — coordenadas não são mais usadas
    'regiao_afetada'         => [],
    'peso_base'              => 5,
    'dados_completos'        => false,
  ];
}

// ---------------- Monta a base completa ----------------
$base = [];

foreach ($MERIDIANOS as $abrev => $info) {
  [$nomeMeridiano, $pontos] = $info;
  foreach ($pontos as $num => $dados) {
    $codigo = $abrev . $num;
    [$pinyin, $loc] = $dados;
    $catExtra = $dados[2] ?? null;
    $codigoUp = strtoupper($codigo);

    if (isset($preExistentes[$codigoUp])) {
      // Preserva integralmente os pontos já modelados com dados completos.
      $preserved = $preExistentes[$codigoUp];
      $base[] = array_merge($preserved, ['meridiano' => $nomeMeridiano, 'dados_completos' => true]);
    } else {
      $base[] = esqueleto($codigo, $pinyin, $nomeMeridiano, $loc, $catExtra);
    }
  }
}

// Pontos extras
foreach ($EXTRAS as $ex) {
  [$codigo, $pinyin, $meridiano, $loc] = $ex;
  $codigoUp = strtoupper($codigo);
  if (isset($preExistentes[$codigoUp])) {
    $base[] = array_merge($preExistentes[$codigoUp], ['meridiano' => $meridiano, 'dados_completos' => true]);
  } else {
    $base[] = esqueleto($codigo, $pinyin, $meridiano, $loc);
  }
}

// Mantém pontos pré-existentes que não estão na lista canônica acima
// (ex.: ponto extra já registrado fora do conjunto EXTRAS)
$ja = [];
foreach ($base as $b) $ja[strtoupper($b['codigo'])] = true;
foreach ($preExistentes as $codigoUp => $p) {
  if (!isset($ja[$codigoUp])) {
    $base[] = array_merge($p, ['dados_completos' => true]);
  }
}

// Ordena: por meridiano (na ordem dos MERIDIANOS), depois pelo número do código.
$ordemMeridiano = array_values(array_map(fn($m) => $m[0], $MERIDIANOS));
$ordemMeridiano[] = 'Pontos extraordinários';
$rankMeridiano = array_flip($ordemMeridiano);

usort($base, function ($a, $b) use ($rankMeridiano) {
  $ra = $rankMeridiano[$a['meridiano']] ?? 999;
  $rb = $rankMeridiano[$b['meridiano']] ?? 999;
  if ($ra !== $rb) return $ra <=> $rb;
  // Extrai parte numérica do código para ordenar dentro do meridiano
  preg_match('/(\d+)/', $a['codigo'], $ma);
  preg_match('/(\d+)/', $b['codigo'], $mb);
  $na = (int)($ma[1] ?? 0);
  $nb = (int)($mb[1] ?? 0);
  return $na <=> $nb;
});

// ---------------- Salva ----------------
file_put_contents(
  $arquivoSaida,
  json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

// Relatório
$porMeridiano = [];
$completos = 0;
foreach ($base as $p) {
  $m = $p['meridiano'] ?? '?';
  $porMeridiano[$m] = ($porMeridiano[$m] ?? 0) + 1;
  if (!empty($p['dados_completos'])) $completos++;
}

echo "Pontos totais: " . count($base) . PHP_EOL;
echo "Com dados clínicos completos: " . $completos . " (esqueleto: " . (count($base) - $completos) . ")" . PHP_EOL;
echo "Distribuição por meridiano:" . PHP_EOL;
foreach ($porMeridiano as $m => $n) {
  printf("  %-30s %d%s" . PHP_EOL, $m, $n, '');
}
echo "Arquivo salvo: " . $arquivoSaida . PHP_EOL;
