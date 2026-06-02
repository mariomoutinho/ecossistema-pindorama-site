<?php

declare(strict_types=1);

// ============================================================================
// EnemImporter — esteira reutilizável de importação de provas ENEM para o Suindá.
//
// Responsável por (idempotente, re-rodável):
//   1. semear a Matriz de Referência (eixos, áreas, disciplinas, competências,
//      habilidades, conteúdos);
//   2. garantir o curso de destino + módulos por área + baralhos por disciplina
//      vinculados ao curso (course_decks);
//   3. importar a prova: exam, exam_questions, question_alternatives, vínculos
//      pedagógicos e um card (card_type='enem') por questão;
//   4. gerar imagens oficiais (recorte das páginas do PDF) QUANDO houver
//      ferramentas (pdftoppm + cwebp/convert) e o PDF; caso contrário, registra
//      as imagens como pendentes — sem quebrar nada;
//   5. emitir manifesto + relatório (JSON e CSV) + lista de revisão manual.
//
// Não duplica questões: a unicidade é (exam_id, number); o card é reaproveitado
// via exam_questions.card_id.
// ============================================================================

final class EnemImporter
{
    /** @var array<string,int> caches resolvidos durante a importação */
    private array $maps = [
        'area' => [], 'discipline' => [], 'competency' => [], 'skill' => [],
        'content' => [], 'axis' => [], 'deck' => [],
    ];

    public function __construct(
        private PDO $db,
        private string $imageDir,   // diretório físico dos recortes (público)
        private string $imageUrlBase // base URL pública dos recortes
    ) {
    }

    // ----------------------------------------------------------------- Matriz
    public function seedMatriz(array $matriz): void
    {
        foreach (($matriz['cognitive_axes'] ?? []) as $axis) {
            $id = $this->upsert('cognitive_axes', ['code' => $axis['code']], [
                'name' => $axis['name'], 'description' => $axis['description'] ?? null,
            ]);
            $this->maps['axis'][$axis['code']] = $id;
        }

        foreach (($matriz['areas'] ?? []) as $area) {
            $id = $this->upsert('knowledge_areas', ['slug' => $area['slug']], [
                'name' => $area['name'], 'description' => 'Área do ENEM.', 'active' => 1,
            ]);
            $this->maps['area'][$area['slug']] = $id;
        }

        foreach (($matriz['disciplines'] ?? []) as $disc) {
            $areaId = $this->maps['area'][$disc['area']] ?? null;
            $slug = $this->slug($disc['name']);
            $id = $this->upsert('disciplines', ['slug' => $slug], [
                'area_id' => $areaId, 'name' => $disc['name'],
            ]);
            $this->maps['discipline'][$disc['name']] = $id;
        }

        foreach (($matriz['competencies'] ?? []) as $comp) {
            $areaId = $this->maps['area'][$comp['area']] ?? null;
            $abbr = $this->areaAbbr($matriz, $comp['area']);
            $code = $abbr . '-C' . $comp['number'];
            $compId = $this->upsert('competencies', ['code' => $code], [
                'area_id' => $areaId, 'number' => $comp['number'], 'statement' => $comp['statement'],
            ]);
            $this->maps['competency'][$code] = $compId;

            foreach (($comp['skills'] ?? []) as $skill) {
                [$num, $statement] = $skill;
                $scode = $abbr . '-H' . $num;
                $sid = $this->upsert('skills', ['code' => $scode], [
                    'competency_id' => $compId, 'number' => $num, 'statement' => $statement,
                ]);
                $this->maps['skill'][$scode] = $sid;
            }
        }

        foreach (($matriz['contents'] ?? []) as $content) {
            $areaId = $this->maps['area'][$content['area']] ?? null;
            $discId = $this->maps['discipline'][$content['discipline']] ?? null;
            $slug = $this->slug(($content['discipline'] ?? '') . '-' . $content['name']);
            $id = $this->upsert('contents', ['slug' => $slug], [
                'area_id' => $areaId, 'discipline_id' => $discId, 'name' => $content['name'],
            ]);
            $this->maps['content'][$content['name']] = $id;
        }
    }

    private function areaAbbr(array $matriz, string $slug): string
    {
        foreach (($matriz['areas'] ?? []) as $a) {
            if ($a['slug'] === $slug) {
                return $a['abbr'];
            }
        }
        return strtoupper(substr($slug, 0, 2));
    }

    // ----------------------------------------------------------------- Curso
    public function ensureCourse(string $slug, string $title, string $description): int
    {
        $courseId = $this->upsert('courses', ['slug' => $slug], [
            'title' => $title, 'description' => $description,
            'level' => 'medio', 'status' => 'available', 'active' => 1,
        ]);

        // Um módulo por área do ENEM (organização pedagógica).
        $pos = 1;
        foreach ($this->maps['area'] as $areaSlug => $areaId) {
            $areaName = (string) $this->scalar('SELECT name FROM knowledge_areas WHERE id = ?', [$areaId]);
            $this->ensureModule($courseId, 'Área: ' . $areaName, $pos++);
        }

        return $courseId;
    }

    private function ensureModule(int $courseId, string $title, int $position): int
    {
        $id = $this->scalar('SELECT id FROM course_modules WHERE course_id = ? AND title = ? LIMIT 1', [$courseId, $title]);
        if ($id !== null) {
            return (int) $id;
        }
        $this->exec('INSERT INTO course_modules (course_id, title, description, position) VALUES (?, ?, ?, ?)',
            [$courseId, $title, 'Conteúdos e questões da área.', $position]);
        return (int) $this->db->lastInsertId();
    }

    /** Baralho por disciplina, vinculado ao curso (idempotente). */
    public function ensureDisciplineDeck(int $courseId, string $discipline): int
    {
        if (isset($this->maps['deck'][$discipline])) {
            return $this->maps['deck'][$discipline];
        }
        $title = 'ENEM · ' . $discipline;
        $deckId = $this->scalar('SELECT id FROM decks WHERE title = ? AND owner_id IS NULL LIMIT 1', [$title]);
        if ($deckId === null) {
            $this->exec('INSERT INTO decks (title, description, category) VALUES (?, ?, ?)',
                [$title, 'Banco de questões ENEM — ' . $discipline . '.', 'ENEM']);
            $deckId = (int) $this->db->lastInsertId();
        }
        $deckId = (int) $deckId;

        // course_decks (curso -> baralho), idempotente
        $link = $this->scalar('SELECT id FROM course_decks WHERE course_id = ? AND deck_id = ?', [$courseId, $deckId]);
        if ($link === null) {
            $this->exec('INSERT INTO course_decks (course_id, deck_id, position) VALUES (?, ?, ?)',
                [$courseId, $deckId, 0]);
        }

        $this->maps['deck'][$discipline] = $deckId;
        return $deckId;
    }

    // ----------------------------------------------------------------- Exam
    public function importExam(array $meta): int
    {
        return $this->upsert('exams', ['slug' => $meta['slug']], [
            'name' => $meta['name'], 'year' => $meta['year'], 'day' => $meta['day'] ?? null,
            'booklet' => $meta['booklet'] ?? null, 'color' => $meta['color'] ?? null,
            'source_label' => $meta['source_label'] ?? null,
        ]);
    }

    /**
     * Importa uma questão (idempotente por exam_id+number). Cria/atualiza card,
     * alternativas e vínculos pedagógicos. Retorna o resumo para o relatório.
     */
    public function importQuestion(int $examId, int $courseId, array $q, array $examMeta): array
    {
        $discipline = $q['discipline'];
        $discId = $this->maps['discipline'][$discipline] ?? null;
        $areaId = $discId ? (int) $this->scalar('SELECT area_id FROM disciplines WHERE id = ?', [$discId]) : null;
        $contentId = isset($q['content']) ? ($this->maps['content'][$q['content']] ?? $this->ensureContent($q['content'], $areaId, $discId)) : null;
        $compId = isset($q['competency']) ? ($this->maps['competency'][$q['competency']] ?? null) : null;
        $skillId = isset($q['skill']) ? ($this->maps['skill'][$q['skill']] ?? null) : null;

        $status = $q['status'] ?? 'ativa';
        $correct = $status === 'anulada' ? null : ($q['correct'] ?? null);
        $reviewNeeded = (!empty($q['review']) || $status === 'anulada') ? 1 : 0;
        $deckId = $this->ensureDisciplineDeck($courseId, $discipline);

        // Card (card_type='enem'); reusado se a questão já existir.
        $existingCardId = $this->scalar('SELECT card_id FROM exam_questions WHERE exam_id = ? AND number = ?', [$examId, $q['n']]);
        $cardId = $existingCardId ? (int) $existingCardId : null;
        $cardData = $this->buildCard($q, $discipline, $examMeta);
        $cardId = $this->upsertCard($cardId, $deckId, $cardData);

        // exam_question
        $questionId = $this->upsert('exam_questions', ['exam_id' => $examId, 'number' => $q['n']], [
            'course_id' => $courseId, 'card_id' => $cardId, 'area_id' => $areaId,
            'discipline_id' => $discId, 'content_id' => $contentId, 'competency_id' => $compId,
            'skill_id' => $skillId, 'cognitive_axis_id' => null,
            'correct_alternative' => $correct, 'status' => $status,
            'statement_text' => $q['statement'] ?? null,
            'confidence' => $q['confidence'] ?? 'baixa', 'review_needed' => $reviewNeeded,
            'pdf_page' => $q['page'] ?? null,
            'notes' => $status === 'anulada' ? 'Questão anulada no gabarito oficial — fora das sessões de revisão.' : null,
        ]);

        // Alternativas
        foreach (($q['alternatives'] ?? []) as $letter => $body) {
            $this->upsert('question_alternatives', ['question_id' => $questionId, 'letter' => $letter], [
                'body' => $body, 'is_correct' => ($letter === $correct) ? 1 : 0,
            ]);
        }

        // Vínculos pedagógicos (principal)
        if ($contentId) {
            $this->upsert('question_contents', ['question_id' => $questionId, 'content_id' => $contentId], ['role' => 'principal']);
        }
        if ($compId) {
            $this->upsert('question_competencies', ['question_id' => $questionId, 'competency_id' => $compId], ['role' => 'principal']);
        }
        if ($skillId) {
            $this->upsert('question_skills', ['question_id' => $questionId, 'skill_id' => $skillId], ['role' => 'principal']);
        }

        // Imagem oficial: gera se possível, senão registra pendência.
        $imageStatus = $this->handleImage($questionId, $q, $examMeta);

        return [
            'number' => $q['n'], 'questionId' => $questionId, 'cardId' => $cardId,
            'discipline' => $discipline, 'content' => $q['content'] ?? null,
            'competency' => $q['competency'] ?? null, 'skill' => $q['skill'] ?? null,
            'correct' => $correct, 'status' => $status, 'confidence' => $q['confidence'] ?? 'baixa',
            'review_needed' => (bool) $reviewNeeded, 'image' => $imageStatus, 'pdf_page' => $q['page'] ?? null,
        ];
    }

    private function ensureContent(string $name, ?int $areaId, ?int $discId): int
    {
        $slug = $this->slug($name);
        $id = $this->upsert('contents', ['slug' => $slug], ['area_id' => $areaId, 'discipline_id' => $discId, 'name' => $name]);
        $this->maps['content'][$name] = $id;
        return $id;
    }

    private function buildCard(array $q, string $discipline, array $examMeta): array
    {
        $statement = (string) ($q['statement'] ?? '');
        $head = sprintf('ENEM %d · %sº dia · Questão %d · %s',
            (int) $examMeta['year'], (string) ($examMeta['day'] ?? '?'), (int) $q['n'], $discipline);

        $alts = '';
        foreach (($q['alternatives'] ?? []) as $letter => $body) {
            $alts .= '<li><strong>' . $letter . ')</strong> ' . $this->esc($body) . '</li>';
        }

        $imgNote = '<p class="enem-q__pending"><em>[Imagem oficial da questão pendente de recorte — exibindo transcrição]</em></p>';

        $questionHtml = '<div class="enem-q">'
            . '<p class="enem-q__head">' . $this->esc($head) . '</p>'
            . $imgNote
            . '<div class="enem-q__statement">' . $this->esc($statement) . '</div>'
            . '<ol class="enem-q__alts" type="A">' . $alts . '</ol>'
            . '</div>';

        if (($q['status'] ?? 'ativa') === 'anulada') {
            $answerPlain = 'Questão ANULADA no gabarito oficial.';
            $answerHtml = '<div class="enem-a enem-a--anulada"><p><strong>⚠ Questão anulada</strong> no gabarito oficial. Não conta acerto nem erro.</p></div>';
        } else {
            $answerPlain = 'Resposta oficial: ' . ($q['correct'] ?? '?');
            $answerHtml = '<div class="enem-a"><p><strong>Resposta oficial:</strong> ' . $this->esc((string) ($q['correct'] ?? '?')) . '</p>'
                . '<p class="enem-a__meta">' . $this->esc($discipline . ($q['content'] ? ' · ' . $q['content'] : '')) . '</p></div>';
        }

        return [
            'question' => $statement !== '' ? $statement : $head,
            'answer' => $answerPlain,
            'question_html' => $questionHtml,
            'answer_html' => $answerHtml,
            'card_type' => 'enem',
        ];
    }

    private function upsertCard(?int $cardId, int $deckId, array $data): int
    {
        if ($cardId) {
            $this->exec('UPDATE cards SET deck_id = ?, question = ?, answer = ?, question_html = ?, answer_html = ?, card_type = ? WHERE id = ?',
                [$deckId, $data['question'], $data['answer'], $data['question_html'], $data['answer_html'], $data['card_type'], $cardId]);
            return $cardId;
        }
        $this->exec('INSERT INTO cards (deck_id, question, answer, question_html, answer_html, card_type) VALUES (?, ?, ?, ?, ?, ?)',
            [$deckId, $data['question'], $data['answer'], $data['question_html'], $data['answer_html'], $data['card_type']]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Aplica comentários pedagógicos revisados (mapa numero => [explanation,status]).
     * Atualiza exam_questions e o verso do card. Idempotente.
     */
    public function applyExplanations(int $examId, array $map): int
    {
        $applied = 0;
        foreach ($map as $number => $info) {
            $explanation = $info['explanation'] ?? null;
            if (!$explanation) {
                continue;
            }
            $status = $info['status'] ?? 'revisada';
            $this->exec('UPDATE exam_questions SET explanation = ?, explanation_status = ? WHERE exam_id = ? AND number = ?',
                [$explanation, $status, $examId, (int) $number]);

            $r = $this->db->prepare('SELECT card_id, correct_alternative, status FROM exam_questions WHERE exam_id = ? AND number = ?');
            $r->execute([$examId, (int) $number]);
            $q = $r->fetch();
            if ($q && $q['card_id']) {
                if (($q['status'] ?? '') === 'anulada') {
                    $back = '<div class="enem-a enem-a--anulada"><p><strong>⚠ Questão anulada</strong> no gabarito oficial.</p><p>' . $this->esc($explanation) . '</p></div>';
                } else {
                    $back = '<div class="enem-a"><p><strong>Resposta oficial:</strong> ' . $this->esc((string) ($q['correct_alternative'] ?? '?')) . '</p><p>' . $this->esc($explanation) . '</p></div>';
                }
                $this->exec('UPDATE cards SET answer_html = ? WHERE id = ?', [$back, (int) $q['card_id']]);
            }
            $applied++;
        }
        return $applied;
    }

    // ----------------------------------------------------------------- Imagens
    /**
     * Gera o recorte oficial da questão se houver PDF + ferramentas; caso
     * contrário registra a pendência (sem criar linha em question_images).
     */
    private function handleImage(int $questionId, array $q, array $examMeta): array
    {
        $basename = $examMeta['image_basename'] ?? 'enem-prova';
        $fileName = sprintf('%s-q%03d.webp', $basename, (int) $q['n']);
        $relPath = rtrim($this->imageUrlBase, '/') . '/' . $fileName;
        $absPath = rtrim($this->imageDir, '/') . '/' . $fileName;

        // Já existe a imagem (gerada em execução anterior)?
        if (is_file($absPath)) {
            $this->upsert('question_images', ['question_id' => $questionId, 'position' => 0], [
                'path' => $relPath, 'kind' => 'crop', 'pdf_page' => $q['page'] ?? null,
            ]);
            return ['status' => 'present', 'path' => $relPath];
        }

        // Tentativa de geração só ocorre se o chamador habilitou (PDF + tooling).
        // Nesta versão piloto não há PDF/tooling, então marca pendente.
        return ['status' => 'pending', 'expected_path' => $relPath, 'expected_file' => $fileName];
    }

    // ----------------------------------------------------------------- Helpers
    private function upsert(string $table, array $keys, array $values): int
    {
        $where = [];
        $params = [];
        foreach ($keys as $k => $v) { $where[] = "$k = ?"; $params[] = $v; }
        $id = $this->scalar("SELECT id FROM {$table} WHERE " . implode(' AND ', $where) . ' LIMIT 1', $params);

        if ($id !== null) {
            if ($values) {
                $set = [];
                $p = [];
                foreach ($values as $k => $v) { $set[] = "$k = ?"; $p[] = $v; }
                $p[] = (int) $id;
                $this->exec("UPDATE {$table} SET " . implode(', ', $set) . ' WHERE id = ?', $p);
            }
            return (int) $id;
        }

        $cols = array_merge(array_keys($keys), array_keys($values));
        $vals = array_merge(array_values($keys), array_values($values));
        $ph = implode(', ', array_fill(0, count($cols), '?'));
        $this->exec("INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES ($ph)", $vals);
        return (int) $this->db->lastInsertId();
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();
        return $v === false ? null : $v;
    }

    private function exec(string $sql, array $params = []): void
    {
        $this->db->prepare($sql)->execute($params);
    }

    private function slug(string $text): string
    {
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($c !== false) { $text = $c; }
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
        return trim($text, '-') ?: 'item';
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function maps(): array
    {
        return $this->maps;
    }
}
