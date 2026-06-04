<?php

namespace JsonApi\Routes\StockImages;

use FilesystemIterator;
use JsonApi\Errors\AuthorizationFailedException;
use JsonApi\Errors\BadRequestException;
use JsonApi\NonJsonApiController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nyholm\Psr7\UploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Studip\StockImages\Scaler;
use Studip\StockImages\PaletteCreator;

class StockImagesZipUpload extends NonJsonApiController
{
    use UploadHelpers;

    public function __invoke(Request $request, Response $response, $args): Response
    {
        if (!Authority::canUploadStockImage($this->getUser($request))) {
            throw new AuthorizationFailedException();
        }
        $image_count = $this->handleUpload($request);

        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(['image-count' => $image_count]));

        return $response;
    }

    private function handleUpload(Request $request): int
    {
        $uploadedFile = self::getUploadedFile($request);
        if (UPLOAD_ERR_OK !== $uploadedFile->getError()) {
            $error = self::getErrorString($uploadedFile->getError());
            throw new BadRequestException($error);
        }

        $validateError = self::validate($uploadedFile);
        if ($validateError) {
            throw new BadRequestException($validateError);
        }

        // Create temp folder
        $tmp_path = $GLOBALS['TMP_PATH'] . '/stock-images-' . uniqid() . '/';
        mkdir($tmp_path, 0777, true);

        // Move uploaded zip archive to temp folder
        $zip_path = $tmp_path . 'archiv.zip';
        $uploadedFile->moveTo($zip_path);

        // Open zip archive
        $zip = new \ZipArchive;
        if (!$zip->open($zip_path)) {
            $this->cleanTmp($tmp_path);
            throw new BadRequestException('Can not extract Zip file.');
        }

        // Extract zip archive into a structure without folders
        // Keep original filenames in a lookup table
        $file_lookup = [];
        $file_counter = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $original_name = $zip->getNameIndex($i);
            //skip directories
            if (str_ends_with($original_name, '/')) {
                continue;
            }
            $new_name = $file_counter++ . '_' . basename($original_name);
            copy('zip://' . $zip_path . '#' . $original_name, $tmp_path . $new_name);
            $file_lookup[$original_name] = $new_name;
        }
        $zip->close();

        // The meta.csv is also extracted with a new name, find it
        $meta_csv_path = null;
        foreach ($file_lookup as $original => $new) {
            if (basename($original) === 'meta.csv') {
                $meta_csv_path = $tmp_path . $new;
                break;
            }
        }

        if ($meta_csv_path === null || !file_exists($meta_csv_path)) {
            $this->cleanTmp($tmp_path);
            throw new BadRequestException('No meta.csv file provided.');
        }

        $csv_file = file($meta_csv_path);
        if (!$csv_file) {
            $this->cleanTmp($tmp_path);
            throw new BadRequestException('Could not read meta.csv file.');
        }

        $rows = array_map(
            fn($v) => str_getcsv($v, ';'),
            $csv_file
        );
        $header = array_shift($rows);
        $images = [];
        foreach ($rows as $row) {
            $images[] = array_combine($header, $row);
        }

        // Actually create the stock image entries
        $image_counter = 0;
        foreach ($images as $meta) {
            if (empty($meta['filename'])) {
                continue;
            }
            $original_filename = $meta['filename'];
            $new_filename = $file_lookup[$original_filename] ?? null;

            if ($new_filename === null) {
                continue;
            }

            $filepath = $tmp_path . $new_filename;

            if (!file_exists($filepath) || !is_readable($filepath)) {
                continue;
            }

            $filesize = filesize($filepath);
            $imagesize = getimagesize($filepath);

            if (!$imagesize) {
                continue;
            }

            $image = \StockImage::create([
                'title' => $meta['title'] ?? 'unknown',
                'description' => $meta['description'] ?? '',
                'license' => $meta['license'] ?? '',
                'author' => $meta['author'] ?? '',
                'height' => $imagesize[1],
                'width' => $imagesize[0],
                'mime_type' => $imagesize['mime'],
                'size' => $filesize,
                'tags' => json_encode(explode(',', $meta['tags'])),
            ]);

            copy($filepath, $image->getPath());
            $scaler = new Scaler();
            $scaler($image);
            $paletteCreator = new PaletteCreator();
            $paletteCreator($image);

            $image_counter++;
        }

        $this->cleanTmp($tmp_path);

        return $image_counter;
    }

    private function cleanTmp(string $tmp_path): void
    {
        if (!file_exists($tmp_path) || !is_dir($tmp_path)) {
            return;
        }
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp_path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        rmdir($tmp_path);
    }

    /**
     * @return string|null null, if the file is valid, otherwise a string containing the error
     */
    private function validate(UploadedFile $file): ?string
    {
        $mimeType = $file->getClientMediaType();
        if (!in_array($mimeType, ['application/x-zip-compressed', ' application/x-zip', 'application/zip'])) {
            return 'Unsupported archive type.';
        }
        return null;
    }
}
