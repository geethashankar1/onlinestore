<?php
// config/media.php
// Image storage abstraction.
//
// Render's free tier has no persistent disk: anything written to uploads/ is
// gone on the next restart or redeploy. When the CLOUDINARY_* env vars are set,
// uploads go to Cloudinary and the DB stores the full https URL. When they are
// not set (local `docker compose up`), files are written to uploads/ exactly as
// before and the DB stores a bare filename — so the same code works in both
// environments, and rows created before this change still render.

/** True when Cloudinary credentials are present. */
function media_enabled(): bool
{
    return getenv('CLOUDINARY_CLOUD_NAME') !== false
        && getenv('CLOUDINARY_API_KEY') !== false
        && getenv('CLOUDINARY_API_SECRET') !== false
        && getenv('CLOUDINARY_CLOUD_NAME') !== ''
        && getenv('CLOUDINARY_API_KEY') !== ''
        && getenv('CLOUDINARY_API_SECRET') !== '';
}

/** Absolute path to my_eshop/uploads, independent of the caller's directory. */
function media_uploads_dir(): string
{
    return dirname(__DIR__) . '/uploads';
}

const MEDIA_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const MEDIA_MAX_BYTES   = 5 * 1024 * 1024;   // 5MB

/**
 * Validate and store an uploaded file.
 *
 * @param array  $file   One entry from $_FILES.
 * @param string $folder 'products' or 'stores'.
 * @param string $error  Set to a human-readable reason when the return is ''.
 * @return string Value to save in the DB: a full https URL (Cloudinary) or a
 *                bare filename (local disk). '' when nothing was stored.
 */
function media_store(array $file, string $folder = 'products', ?string &$error = null): string
{
    $error = null;

    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';                      // No file chosen — not an error.
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (code ' . $file['error'] . ').';
        return '';
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, MEDIA_ALLOWED_EXT, true)) {
        $error = 'Only JPG, PNG, GIF and WEBP images are allowed.';
        return '';
    }
    if (($file['size'] ?? 0) > MEDIA_MAX_BYTES) {
        $error = 'Image must be under 5MB.';
        return '';
    }

    if (media_enabled()) {
        $url = media_cloudinary_upload($file['tmp_name'], $folder, $ext);
        if ($url === '') {
            $error = 'Image upload to the media service failed. Try again.';
        }
        return $url;
    }

    $dir = media_uploads_dir() . ($folder === 'stores' ? '/stores' : '');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        $error = 'Upload directory is not writable.';
        return '';
    }
    $name = uniqid($folder === 'stores' ? 'logo_' : 'product_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $error = 'Could not save the uploaded file.';
        return '';
    }
    return $name;
}

/** Signed Cloudinary upload. Returns the secure URL, or '' on failure. */
function media_cloudinary_upload(string $tmpPath, string $folder, string $ext): string
{
    $cloud  = getenv('CLOUDINARY_CLOUD_NAME');
    $key    = getenv('CLOUDINARY_API_KEY');
    $secret = getenv('CLOUDINARY_API_SECRET');

    $timestamp = time();
    $dir       = 'eshop/' . $folder;

    // Cloudinary signs the alphabetically-sorted parameters (excluding file,
    // api_key and resource_type) with the API secret appended.
    $signature = sha1("folder={$dir}&timestamp={$timestamp}" . $secret);

    $mime = function_exists('mime_content_type') ? (mime_content_type($tmpPath) ?: null) : null;

    $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloud}/image/upload");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => [
            'file'      => new CURLFile($tmpPath, $mime ?: 'image/' . $ext),
            'api_key'   => $key,
            'timestamp' => $timestamp,
            'folder'    => $dir,
            'signature' => $signature,
        ],
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status !== 200) {
        error_log("Cloudinary upload failed (HTTP {$status}) {$curlErr}: " . substr((string)$response, 0, 500));
        return '';
    }

    $json = json_decode((string)$response, true);
    return $json['secure_url'] ?? '';
}

/**
 * Resolve a stored `image` value to something usable in a src attribute.
 * Handles both Cloudinary URLs and legacy local filenames.
 *
 * @param string $folder '' for products, 'stores' for store logos.
 */
function media_url(?string $value, string $folder = '', string $placeholder = '/uploads/default_placeholder.png'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return $placeholder;
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    $relative = ($folder !== '' ? $folder . '/' : '') . $value;
    $onDisk   = media_uploads_dir() . '/' . $relative;

    return is_file($onDisk) ? '/uploads/' . $relative : $placeholder;
}

/**
 * Remove a locally-stored image. Cloudinary assets are left in place — deleting
 * them needs the destroy API and a stored public_id, and orphans are harmless
 * inside the free tier's allowance.
 */
function media_delete(?string $value, string $folder = ''): void
{
    $value = trim((string)$value);
    if ($value === '' || preg_match('#^https?://#i', $value)) {
        return;
    }
    $path = media_uploads_dir() . ($folder !== '' ? '/' . $folder : '') . '/' . basename($value);
    if (is_file($path)) {
        @unlink($path);
    }
}
