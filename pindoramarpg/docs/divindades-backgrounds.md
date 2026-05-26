# Backgrounds de Divindades

Imagens de fundo usadas pelo modal **Selecionar Divindade** em `ficha.php`.

## Convenção

- Pasta: `assets/img/divindades/backgrounds/`
- Nome: `{id}-bg.webp` — onde `{id}` é o campo `id` em [data/divindades.json](../data/divindades.json)
- O `id` no JSON já é a forma normalizada: minúsculo, sem acentos, sem
  apóstrofos, sem espaços, hífen apenas quando o nome original tem hífen.
- Formato: `.webp` (proporção ampla recomendada, ex.: 16:9 ou 3:2 — o CSS
  usa `background-size: cover; background-position: center right;`).
- Fallback: `default-bg.webp` é carregado automaticamente quando a imagem
  específica falha (404 ou erro de decodificação).

## Tabela completa

| Divindade | Nome do arquivo | Caminho completo |
|---|---|---|
| Aelohim | `aelohim-bg.webp` | `assets/img/divindades/backgrounds/aelohim-bg.webp` |
| Anhangá | `anhanga-bg.webp` | `assets/img/divindades/backgrounds/anhanga-bg.webp` |
| Anhum | `anhum-bg.webp` | `assets/img/divindades/backgrounds/anhum-bg.webp` |
| Axumewá | `axumewa-bg.webp` | `assets/img/divindades/backgrounds/axumewa-bg.webp` |
| Caaporiã | `caaporia-bg.webp` | `assets/img/divindades/backgrounds/caaporia-bg.webp` |
| Ex'us | `exus-bg.webp` | `assets/img/divindades/backgrounds/exus-bg.webp` |
| Guianalá | `guianala-bg.webp` | `assets/img/divindades/backgrounds/guianala-bg.webp` |
| Gumedé | `gumede-bg.webp` | `assets/img/divindades/backgrounds/gumede-bg.webp` |
| Iacyr | `iacyr-bg.webp` | `assets/img/divindades/backgrounds/iacyr-bg.webp` |
| Kiantomerê | `kiantomere-bg.webp` | `assets/img/divindades/backgrounds/kiantomere-bg.webp` |
| Kuaracyr | `kuaracyr-bg.webp` | `assets/img/divindades/backgrounds/kuaracyr-bg.webp` |
| Micê | `mice-bg.webp` | `assets/img/divindades/backgrounds/mice-bg.webp` |
| Mondjá | `mondja-bg.webp` | `assets/img/divindades/backgrounds/mondja-bg.webp` |
| Namburuk | `namburuk-bg.webp` | `assets/img/divindades/backgrounds/namburuk-bg.webp` |
| Odéssi | `odessi-bg.webp` | `assets/img/divindades/backgrounds/odessi-bg.webp` |
| Ruach-Hakodechi | `ruach-hakodechi-bg.webp` | `assets/img/divindades/backgrounds/ruach-hakodechi-bg.webp` |
| Sãin | `sain-bg.webp` | `assets/img/divindades/backgrounds/sain-bg.webp` |
| Tessã | `tessa-bg.webp` | `assets/img/divindades/backgrounds/tessa-bg.webp` |
| Tumpã | `tumpa-bg.webp` | `assets/img/divindades/backgrounds/tumpa-bg.webp` |
| Yéxua | `yexua-bg.webp` | `assets/img/divindades/backgrounds/yexua-bg.webp` |
| _(fallback)_ | `default-bg.webp` | `assets/img/divindades/backgrounds/default-bg.webp` |

## Status atual

Apenas `axumewa-bg.webp` está presente no repositório. Os outros 20
arquivos (19 divindades + `default-bg.webp`) precisam ser adicionados —
até lá, o picker exibe um fundo roxo sólido (`#2d0c37`) para essas
divindades, sem quebrar o layout.

## Onde mexer

- Carregamento automático: [assets/js/entity-picker.js](../assets/js/entity-picker.js) — função `initDivindades` monta o caminho via `id + '-bg.webp'` e aplica o fallback.
- CSS do painel: [assets/css/ancestralidade-picker.css](../assets/css/ancestralidade-picker.css) — classe `.anc-picker-preview--bg-cover`.
