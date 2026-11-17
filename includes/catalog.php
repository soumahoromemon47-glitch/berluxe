<?php
declare(strict_types=1);

function slugify(string $value): string
{
    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
    }

    $slug = strtolower($value);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    return trim($slug ?? '', '-');
}

function unique_catalog_slug(PDO $connection, string $table, string $value, ?int $excludeId = null): string
{
    if (!in_array($table, ['categories', 'products'], true)) {
        throw new InvalidArgumentException('Type de catalogue invalide.');
    }

    $baseSlug = slugify($value);
    if ($baseSlug === '') {
        $baseSlug = $table === 'products' ? 'produit' : 'categorie';
    }
    $maxLength = $table === 'products' ? 200 : 140;
    $baseSlug = substr($baseSlug, 0, $maxLength);

    $candidate = $baseSlug;
    $suffix = 2;

    while (true) {
        $sql = 'SELECT id FROM ' . $table . ' WHERE slug = :slug';
        $parameters = ['slug' => $candidate];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $parameters['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        if ($statement->fetchColumn() === false) {
            return $candidate;
        }

        $ending = '-' . $suffix;
        $candidate = substr($baseSlug, 0, $maxLength - strlen($ending)) . $ending;
        $suffix++;
        if ($suffix > 10000) {
            throw new RuntimeException('Impossible de générer une adresse unique.');
        }
    }
}

function collect_product_image_uploads(array $fileBag): array
{
    if (!isset($fileBag['error'])) {
        return [];
    }
    if (
        !is_array($fileBag['error'])
        || !isset($fileBag['tmp_name'], $fileBag['size'])
        || !is_array($fileBag['tmp_name'])
        || !is_array($fileBag['size'])
    ) {
        throw new InvalidArgumentException('Le formulaire de photos est invalide.');
    }
    $mimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $uploads = [];

    foreach ($fileBag['error'] as $index => $error) {
        if ((int) $error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ((int) $error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Une photo n’a pas pu être téléversée. Vérifie sa taille et réessaie.');
        }
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('GD est nécessaire pour traiter les photos. Active-le puis redémarre Apache.');
        }

        $temporaryPath = $fileBag['tmp_name'][$index] ?? '';
        $fileSize = (int) ($fileBag['size'][$index] ?? 0);
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('Le fichier reçu n’est pas un téléversement valide.');
        }
        if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Chaque photo doit peser au maximum 5 Mo.');
        }

        $mimeType = $fileInfo->file($temporaryPath);
        $imageInfo = getimagesize($temporaryPath);
        if (
            !is_string($mimeType)
            || !isset($mimeToExtension[$mimeType])
            || !is_array($imageInfo)
            || !isset($imageInfo['mime'])
            || $imageInfo['mime'] !== $mimeType
        ) {
            throw new InvalidArgumentException('Utilise uniquement des photos JPG, PNG ou WebP valides.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if (
            $width < 1
            || $height < 1
            || $width > 8000
            || $height > 8000
            || $width * $height > 16000000
        ) {
            throw new InvalidArgumentException('Les dimensions de cette photo sont trop grandes.');
        }

        $uploads[] = [
            'tmp_name' => $temporaryPath,
            'mime_type' => $mimeType,
            'extension' => $mimeToExtension[$mimeType],
            'width' => $width,
            'height' => $height,
        ];
    }

    return $uploads;
}

function store_resized_product_image(array $image): string
{
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('GD est nécessaire pour traiter les photos. Active-le puis redémarre Apache.');
    }

    $mimeType = $image['mime_type'] ?? '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
        throw new InvalidArgumentException('Le format de la photo n’est pas pris en charge.');
    }

    $directory = dirname(__DIR__) . '/uploads/products';
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Le dossier des photos produits n’est pas accessible en écriture.');
    }

    $contents = file_get_contents((string) ($image['tmp_name'] ?? ''));
    if ($contents === false) {
        throw new RuntimeException('Impossible de lire la photo téléversée.');
    }

    $source = imagecreatefromstring($contents);
    unset($contents);
    if ($source === false) {
        throw new InvalidArgumentException('La photo téléversée est illisible.');
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth < 1 || $sourceHeight < 1 || $sourceWidth * $sourceHeight > 16000000) {
        imagedestroy($source);
        throw new InvalidArgumentException('Les dimensions de cette photo sont trop grandes.');
    }

    $scale = min(1, 1600 / $sourceWidth, 1600 / $sourceHeight);
    $targetWidth = max(1, (int) round($sourceWidth * $scale));
    $targetHeight = max(1, (int) round($sourceHeight * $scale));
    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($target === false) {
        imagedestroy($source);
        throw new RuntimeException('Impossible de préparer le redimensionnement de la photo.');
    }

    if ($mimeType !== 'image/jpeg') {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($target, 0, 0, $targetWidth - 1, $targetHeight - 1, $transparent);
        }
    }

    if (!imagecopyresampled(
        $target,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $sourceWidth,
        $sourceHeight
    )) {
        imagedestroy($source);
        imagedestroy($target);
        throw new RuntimeException('Impossible de redimensionner la photo.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    $destination = $directory . '/' . $fileName;
    $saved = match ($mimeType) {
        'image/jpeg' => imagejpeg($target, $destination, 82),
        'image/png' => imagepng($target, $destination, 7),
        'image/webp' => function_exists('imagewebp') && imagewebp($target, $destination, 82),
    };

    imagedestroy($source);
    imagedestroy($target);

    if (!$saved) {
        if (is_file($destination) && !unlink($destination)) {
            error_log('Berluxe could not remove an incomplete product image: ' . $fileName);
        }
        throw new RuntimeException('Impossible d’enregistrer la photo redimensionnée.');
    }

    return $fileName;
}

function delete_product_image_files(array $fileNames): array
{
    $directory = dirname(__DIR__) . '/uploads/products';
    $failedFiles = [];

    foreach (array_unique($fileNames) as $fileName) {
        if (!is_string($fileName) || $fileName === '' || basename($fileName) !== $fileName) {
            error_log('Berluxe refused an unsafe product image path.');
            $failedFiles[] = (string) $fileName;
            continue;
        }

        $path = $directory . '/' . $fileName;
        if (is_file($path) && !unlink($path)) {
            error_log('Berluxe could not remove product image: ' . $fileName);
            $failedFiles[] = $fileName;
        }
    }

    return $failedFiles;
}
