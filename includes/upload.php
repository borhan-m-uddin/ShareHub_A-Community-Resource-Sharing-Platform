<?php
// Image upload helper extracted from bootstrap.

if (!function_exists('upload_image_secure')) {
    function upload_image_secure(array $file, string $targetSubdir = 'uploads/items', int $maxBytes = 2_000_000, int $maxWidth = 1600, int $maxHeight = 1200): array
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE => 'The file exceeds server limit (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE => 'The file exceeds form limit (MAX_FILE_SIZE).',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.'
            ];
            return ['ok' => false, 'pathRel' => null, 'error' => ($map[$err] ?? ('Upload error (code ' . $err . ').'))];
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Image size must be <= ' . (int)round($maxBytes / 1024 / 1024) . 'MB.'];
        }
        $mime = null;
        if (function_exists('finfo_open')) {
            $fi = @finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = @finfo_file($fi, $tmp);
                @finfo_close($fi);
            }
        }
        if (!$mime && function_exists('getimagesize')) {
            $gi = @getimagesize($tmp);
            if (is_array($gi) && !empty($gi['mime'])) {
                $mime = $gi['mime'];
            }
        }
        if (!$mime && isset($file['type'])) {
            $mime = $file['type'];
        }
        $mime = $mime ? strtolower($mime) : '';
        $allowed = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/pjpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!$mime || !isset($allowed[$mime])) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Only JPG, PNG, GIF or WEBP images are allowed.'];
        }
        $ext = $allowed[$mime];
        $dirAbs = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $targetSubdir);
        if (!is_dir($dirAbs)) {
            @mkdir($dirAbs, 0777, true);
        }
        try {
            $name = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (\Throwable $e) {
            $name = uniqid('', true) . '.' . $ext;
        }
        $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;
        $destRel = rtrim($targetSubdir, '/\\') . '/' . $name;
        if (!@move_uploaded_file($tmp, $destAbs)) {
            return ['ok' => false, 'pathRel' => null, 'error' => 'Failed to save uploaded image.'];
        }
        $canProcess = function_exists('imagecreatetruecolor') && function_exists('getimagesize');
        if ($canProcess && $ext !== 'gif') {
            $info = @getimagesize($destAbs);
            if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
                $w = (int)$info[0];
                $h = (int)$info[1];
                $scale = min($maxWidth / max(1, $w), $maxHeight / max(1, $h), 1.0);
                $reencode = function (string $extUse, $dest) use ($w, $h, $destAbs) {
                    $dst = imagecreatetruecolor($w, $h);
                    $src = null;
                    if ($extUse === 'jpg') {
                        $src = @imagecreatefromjpeg($destAbs);
                    } elseif ($extUse === 'png') {
                        $src = @imagecreatefrompng($destAbs);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                    } elseif ($extUse === 'webp' && function_exists('imagecreatefromwebp')) {
                        $src = @imagecreatefromwebp($destAbs);
                    }
                    if ($src) {
                        @imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
                        if ($extUse === 'jpg') {
                            @imagejpeg($dst, $destAbs, 85);
                        } elseif ($extUse === 'png') {
                            @imagepng($dst, $destAbs, 6);
                        } elseif ($extUse === 'webp' && function_exists('imagewebp')) {
                            @imagewebp($dst, $destAbs, 85);
                        }
                        @imagedestroy($src);
                        @imagedestroy($dst);
                    }
                };
                if ($scale < 1.0) {
                    $newW = max(1, (int)round($w * $scale));
                    $newH = max(1, (int)round($h * $scale));
                    $dst = imagecreatetruecolor($newW, $newH);
                    $src = null;
                    if ($ext === 'jpg') {
                        $src = @imagecreatefromjpeg($destAbs);
                    } elseif ($ext === 'png') {
                        $src = @imagecreatefrompng($destAbs);
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                    } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
                        $src = @imagecreatefromwebp($destAbs);
                    }
                    if ($src) {
                        @imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
                        if ($ext === 'jpg') {
                            @imagejpeg($dst, $destAbs, 85);
                        } elseif ($ext === 'png') {
                            @imagepng($dst, $destAbs, 6);
                        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
                            @imagewebp($dst, $destAbs, 85);
                        }
                        @imagedestroy($src);
                        @imagedestroy($dst);
                    }
                } else {
                    $reencode($ext, $destAbs);
                }
            }
        }
        return ['ok' => true, 'pathRel' => $destRel, 'error' => null];
    }
}
