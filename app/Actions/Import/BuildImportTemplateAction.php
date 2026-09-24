<?php

declare(strict_types=1);

namespace App\Actions\Import;

use RuntimeException;
use ZipArchive;

class BuildImportTemplateAction
{
    /**
     * Zip the export spec (README), the example CSVs and example receipts from
     * resources/import-template into a temporary file, ready to download and
     * hand to whoever builds the export. The example CSVs link to each other,
     * so the zip is itself a valid bundle.
     *
     * @return string Path to the temporary zip (caller deletes it after sending)
     *
     * @throws RuntimeException
     */
    public function __invoke(): string
    {
        $sourceDirectory = resource_path('import-template');
        $zipPath = tempnam(sys_get_temp_dir(), 'import-template-');

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the template zip.');
        }

        $zip->addFile("{$sourceDirectory}/README.md", 'README.md');

        foreach (ReadImportBundleAction::FILES as $file) {
            $zip->addFile("{$sourceDirectory}/{$file}", $file);
        }

        foreach (glob("{$sourceDirectory}/receipts/*") ?: [] as $receipt) {
            $zip->addFile($receipt, 'receipts/'.basename($receipt));
        }

        $zip->close();

        return $zipPath;
    }
}
