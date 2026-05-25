<?php
/**
 * includes/foto-usuario.php — upload da foto de perfil opcional do
 * participante. Espelha o padrão de processarUploadCapaAventura():
 * valida via getimagesize, aceita JPG/PNG/WebP, limite 5 MB, nome
 * com hash aleatório, grava em uploads/usuarios/.
 */

const USUARIOS_FOTO_DIR = __DIR__ . '/../uploads/usuarios';
const USUARIOS_FOTO_URL = 'uploads/usuarios';
const USUARIOS_FOTO_MAX_BYTES = 5 * 1024 * 1024;

if (!function_exists('usuarioGarantirPastaFoto')) {
    function usuarioGarantirPastaFoto(): void
    {
        if (!is_dir(USUARIOS_FOTO_DIR)) {
            @mkdir(USUARIOS_FOTO_DIR, 0775, true);
        }
    }
}

if (!function_exists('processarUploadFotoUsuario')) {
    function processarUploadFotoUsuario(?array $arquivo, ?string &$erro = null): ?string
    {
        $erro = null;
        if (!$arquivo || !isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($arquivo['error'] !== UPLOAD_ERR_OK) {
            $erro = 'Falha ao receber o arquivo da foto.';
            return null;
        }
        if ($arquivo['size'] > USUARIOS_FOTO_MAX_BYTES) {
            $erro = 'A foto excede o tamanho máximo permitido (5 MB).';
            return null;
        }
        $tmp = $arquivo['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            $erro = 'Arquivo inválido.';
            return null;
        }
        $info = @getimagesize($tmp);
        if (!$info) {
            $erro = 'O arquivo enviado não é uma imagem válida.';
            return null;
        }
        $mimeMap = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];
        if (!isset($mimeMap[$info[2]])) {
            $erro = 'Formato não suportado. Use JPG, PNG ou WebP.';
            return null;
        }
        $ext = $mimeMap[$info[2]];

        usuarioGarantirPastaFoto();
        $tentativas = 0;
        do {
            $nome = 'u-' . bin2hex(random_bytes(10)) . '.' . $ext;
            $destinoAbs = USUARIOS_FOTO_DIR . '/' . $nome;
            $tentativas++;
        } while (file_exists($destinoAbs) && $tentativas < 5);

        if (!@move_uploaded_file($tmp, $destinoAbs)) {
            $erro = 'Não foi possível salvar a foto.';
            return null;
        }
        @chmod($destinoAbs, 0644);
        return USUARIOS_FOTO_URL . '/' . $nome;
    }
}

if (!function_exists('excluirArquivoFotoUsuario')) {
    function excluirArquivoFotoUsuario(string $relPath): void
    {
        if ($relPath === '') return;
        $relPath = ltrim($relPath, '/');
        if (strpos($relPath, USUARIOS_FOTO_URL . '/') !== 0) return;
        $abs = realpath(__DIR__ . '/../' . $relPath);
        $base = realpath(USUARIOS_FOTO_DIR);
        if ($abs && $base && strpos($abs, $base) === 0 && is_file($abs)) {
            @unlink($abs);
        }
    }
}
